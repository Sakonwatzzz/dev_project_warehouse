// อัปเดตการกรองและค้นหา
function updateSearch() {
  const input = document.getElementById('searchInput').value.trim();
  const selectedTypes = Array.from(document.querySelectorAll('input[name="product_type[]"]:checked')).map(cb => cb.value);
  const selectedBrands = Array.from(document.querySelectorAll('input[name="product_brand[]"]:checked')).map(cb => cb.value);

  // สร้าง URL parameters
  const params = new URLSearchParams(window.location.search);
  params.set('page', 'product_list');
  params.set('search', input);
  params.set('page_num', 1); // รีเซ็ตเป็นหน้าแรกเมื่อเปลี่ยนการกรอง

  // เพิ่มตัวกรองประเภทสินค้า
  if (selectedTypes.length > 0) {
      params.set('product_type', selectedTypes.join(','));
  } else {
      params.delete('product_type');
  }

  // เพิ่มตัวกรองแบรนด์สินค้า
  if (selectedBrands.length > 0) {
      params.set('product_brand', selectedBrands.join(','));
  } else {
      params.delete('product_brand');
  }

  // รักษาโหมด incomplete ถ้ามี
  if (params.has('mode') && params.get('mode') === 'incomplete') {
      params.set('mode', 'incomplete');
  } else {
      params.delete('mode');
  }

  // อัปเดต URL
  window.location.href = `${window.location.pathname}?${params.toString()}`;
}

// รีเซ็ตการกรองทั้งหมด
function resetFilters() {
  const params = new URLSearchParams();
  params.set('page', 'product_list');
  params.set('page_num', 1);
  window.location.href = `${window.location.pathname}?${params.toString()}`;
}

// เพิ่ม Debounce สำหรับ searchInput
let timeout;
document.getElementById('searchInput').addEventListener('keyup', () => {
  clearTimeout(timeout);
  timeout = setTimeout(updateSearch, 300);
});

// อัปเดตเมื่อเปลี่ยนตัวกรองประเภทหรือแบรนด์
document.addEventListener('DOMContentLoaded', () => {
  const checkboxes = document.querySelectorAll('input[name="product_type[]"], input[name="product_brand[]"]');
  checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', updateSearch);
  });

  // Toggle filters บนมือถือ
  const toggleFilters = document.getElementById('toggleFilters');
  const filters = document.getElementById('filters');
  if (toggleFilters) {
      toggleFilters.addEventListener('click', () => {
          filters.classList.toggle('hidden');
          const icon = toggleFilters.querySelector('i');
          icon.classList.toggle('fa-chevron-down');
          icon.classList.toggle('fa-chevron-up');
      });
  }

  // แสดงการแจ้งเตือนถ้ามี
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('edit_success')) showNotification('notification-edit');
  else if (urlParams.has('delete_success')) showNotification('notification-delete');
});

// แสดงการแจ้งเตือน
function showNotification(id) {
  const notification = document.getElementById(id);
  notification.classList.remove('hidden');
  setTimeout(() => notification.classList.add('hidden'), 2000);
}