// personnel_group.js
const showAddModal = () => {
    document.getElementById('addPersonnelName').value = '';
    document.getElementById('addPersonnelUsername').value = '';
    document.getElementById('addPersonnelAddress').value = '';
    document.getElementById('addPersonnelPhone').value = '';
    document.getElementById('addPersonnelPassword').value = '';
    document.getElementById('addNameError').classList.add('hidden');
    document.getElementById('addUsernameError').classList.add('hidden');
    document.getElementById('addAddressError').classList.add('hidden');
    document.getElementById('addPhoneError').classList.add('hidden');
    document.getElementById('addPasswordError').classList.add('hidden');
    document.getElementById('addModal').classList.remove('hidden');
};

const closeAddModal = () => {
    document.getElementById('addModal').classList.add('hidden');
};

const showEditModal = (id, name, username, typeId, address, phone) => {
    document.getElementById('editPersonnelId').value = id;
    document.getElementById('editPersonnelName').value = name;
    document.getElementById('editPersonnelUsername').value = username;
    document.getElementById('editPersonnelPassword').value = '';
    document.getElementById('editPersonnelPhone').value = phone;
    document.getElementById('editPersonnelAddress').value = address;
    document.getElementById('editPersonnelTypeId').value = typeId;
    document.getElementById('editNameError').classList.add('hidden');
    document.getElementById('editUsernameError').classList.add('hidden');
    document.getElementById('editAddressError').classList.add('hidden');
    document.getElementById('editPhoneError').classList.add('hidden');
    document.getElementById('editPasswordError').classList.add('hidden');
    document.getElementById('editTypeError').classList.add('hidden');
    document.getElementById('editModal').classList.remove('hidden');
};

const closeEditModal = () => {
    document.getElementById('editModal').classList.add('hidden');
};

const showDeleteModal = () => {
    document.getElementById('deleteModal').classList.remove('hidden');
};

const closeDeleteModal = () => {
    document.getElementById('deleteModal').classList.add('hidden');
};

const confirmDelete = (id) => {
    Swal.fire({
        title: 'ยืนยันการลบ',
        text: 'คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.innerHTML = `
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]').value}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
};

const showNotification = (message, headerClass) => {
    const notification = document.getElementById('notification');
    const header = document.getElementById('notification-header');
    const title = document.getElementById('notificationTitle');
    const body = document.getElementById('notificationMessage');
    const icon = document.getElementById('notificationIcon');

    header.className = `flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg ${headerClass}`;
    title.textContent = 'แจ้งเตือน';
    body.textContent = message;
    icon.className = `fas ${headerClass.includes('bg-green') ? 'fa-check-circle' : 'fa-exclamation-circle'} text-2xl`;
    notification.classList.remove('translate-x-full');

    setTimeout(() => {
        hideNotification();
    }, 5000);
};

const hideNotification = () => {
    document.getElementById('notification').classList.add('translate-x-full');
};

function updateTable() {
  const urlParams = new URLSearchParams();
  urlParams.set("page", "personnel_list");
  urlParams.set("page_num", "1");

  const searchInput = document.getElementById("searchInput").value.trim();
  if (searchInput) {
    urlParams.set("search", searchInput);
  }

  const startDate = document.getElementById("start_date").value;
  if (startDate) {
    urlParams.set("start_date", startDate);
  }

  const endDate = document.getElementById("end_date").value;
  if (endDate) {
    urlParams.set("end_date", endDate);
  }

  fetch(`/page/personnel/ajax/personnel_group_table.php?${urlParams.toString()}`)
    .then((response) => {
      if (!response.ok) throw new Error("Network error");
      return response.text();
    })
    .then((data) => {
      try {
        const json = JSON.parse(data);
        showNotification(json.error, "error");
        document.getElementById("personnelTable").innerHTML =
          '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
      } catch (e) {
        document.getElementById("personnelTable").innerHTML = data;
        history.pushState({}, "", `?${urlParams.toString()}`);
      }
    })
    .catch((error) => {
      console.error("เกิดข้อผิดพลาดขณะโหลดตาราง:", error);
      document.getElementById("personnelTable").innerHTML =
        '<div class="text-center py-4 text-gray-600">เกิดข้อผิดพลาด</div>';
    });
}

function goToPage(pageNum) {
  const urlParams = new URLSearchParams();
  urlParams.set("page", "personnel_list");
  urlParams.set("page_num", pageNum);
  const searchInput = document.getElementById("searchInput").value.trim();
  if (searchInput) {
    urlParams.set("search", searchInput);
  }
  const startDate = document.getElementById("start_date").value;
  if (startDate) {
    urlParams.set("start_date", startDate);
  }
  const endDate = document.getElementById("end_date").value;
  if (endDate) {
    urlParams.set("end_date", endDate);
  }

  fetch(`/page/personnel/ajax/personnel_group_table.php?${urlParams.toString()}`)
    .then((response) => {
      if (!response.ok) throw new Error("Network error");
      return response.text();
    })
    .then((data) => {
      try {
        const json = JSON.parse(data);
        showNotification(json.error, "error");
        document.getElementById("personnelTable").innerHTML =
          '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
      } catch (e) {
        document.getElementById("personnelTable").innerHTML = data;
        history.pushState({}, "", `?${urlParams.toString()}`);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification("เกิดข้อผิดพลาดขณะโหลดตาราง", "error");
      document.getElementById("personnelTable").innerHTML =
        '<div class="text-center py-4 text-gray-600">เกิดข้อผิดพลาด</div>';
    });
}


const exportPDF = () => {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const exportBtn = document.getElementById('export-buttons');
    const type = exportBtn.dataset.type;
    const token = exportBtn.dataset.token;

    let url = `/page/personnel/report/export_personnel_group_pdf.php?type=${encodeURIComponent(type)}&csrf_token=${encodeURIComponent(token)}`;
    if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
    if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
    window.open(url, '_blank');
};

const exportExcel = () => {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const exportBtn = document.getElementById('export-excel-button');
    const type = exportBtn.dataset.type;
    const token = exportBtn.dataset.token;

    let url = `/page/personnel/report/export_personnel_group_excel.php?type=${encodeURIComponent(type)}&csrf_token=${encodeURIComponent(token)}`;
    if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
    if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
    window.open(url, '_blank');
};


const loadTable = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const type = urlParams.get('type') || '';
    const search = urlParams.get('search') || '';
    const startDate = urlParams.get('start_date') || '';
    const endDate = urlParams.get('end_date') || '';
    const pageNum = urlParams.get('page_num') || '1';

    fetch(`/page/personnel/ajax/personnel_group_table.php?type=${encodeURIComponent(type)}&search=${encodeURIComponent(search)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&page_num=${pageNum}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('personnelTable').innerHTML = data;
        })
        .catch(error => {
            console.error('Error loading table:', error);
            showNotification('เกิดข้อผิดพลาดในการโหลดข้อมูล', 'bg-red-500');
        });
};

document.addEventListener('DOMContentLoaded', () => {
    loadTable();

    const urlParams = new URLSearchParams(window.location.search);
    const searchInput = document.getElementById('searchInput');
    const clearSearch = document.getElementById('clearSearch');

    if (searchInput) {
        searchInput.focus();
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                searchInput.value = '';
                searchInput.form.submit();
            }
            if (e.key === 'Enter') {
                localStorage.setItem('searchPreserve', searchInput.value);
            }
        });

        const preserved = localStorage.getItem('searchPreserve');
        if (preserved) {
            searchInput.value = preserved;
            searchInput.focus();
            searchInput.selectionStart = searchInput.selectionEnd = preserved.length;
            localStorage.removeItem('searchPreserve');
        }

        searchInput.addEventListener('input', () => {
            clearSearch.classList.toggle('hidden', !searchInput.value.trim());
            if (searchInput.value.trim() === '') {
                searchInput.form.submit();
            }
        });

        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.form.submit();
        });
    }

    if (urlParams.has('success')) {
        showNotification(decodeURIComponent(urlParams.get('success')), 'bg-green-500');
    } else if (urlParams.has('error')) {
        showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
    }

    const validateFormAscForm = (formId, fields) => {
        let hasError = false;
        fields.forEach(({ id, errorId, label, maxLength, required, validate, errorMessage }) => {
            const input = document.getElementById(id);
            const error = document.getElementById(errorId);
            if (required && !input.value.trim()) {
                error.textContent = `กรุณากรอก${label}`;
                error.classList.remove('hidden');
                hasError = true;
            } else if (maxLength && input.value.length > maxLength) {
                error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
                error.classList.remove('hidden');
                hasError = true;
            } else if (validate && !validate(input.value)) {
                error.textContent = errorMessage;
                error.classList.remove('hidden');
                hasError = true;
            } else {
                error.classList.add('hidden');
            }
        });
        return !hasError;
    };

    document.getElementById('addForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const fields = [
            { id: 'addPersonnelName', errorId: 'addNameError', label: 'ชื่อ-นามสกุล', maxLength: 255, required: true },
            { id: 'addPersonnelUsername', errorId: 'addUsernameError', label: 'ชื่อผู้ใช้', maxLength: 100, required: true },
            { id: 'addPersonnelAddress', errorId: 'addAddressError', label: 'ที่อยู่', maxLength: 500, required: true },
            {
                id: 'addPersonnelPhone',
                errorId: 'addPhoneError',
                label: 'เบอร์โทรศัพท์',
                validate: (value) => !value || /^[0-9]{10}$/.test(value),
                errorMessage: 'ต้องเป็นตัวเลข 10 หลัก',
                required: true
            },
            {
                id: 'addPersonnelPassword',
                errorId: 'addPasswordError',
                label: 'รหัสผ่าน',
                validate: (value) => !value || /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{6,100}$/.test(value),
                errorMessage: 'ต้องมีตัวอักษรพิมพ์เล็ก พิมพ์ใหญ่ ตัวเลข และอักขระพิเศษอย่างน้อย 1 ตัว',
                required: true
            }
        ];
        if (validateFormAscForm('addForm', fields)) {
            e.target.submit();
        }
    });

    document.getElementById('editForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const fields = [
            { id: 'editPersonnelName', errorId: 'editNameError', label: 'ชื่อ-นามสกุล', maxLength: 255, required: true },
            { id: 'editPersonnelUsername', errorId: 'editUsernameError', label: 'ชื่อผู้ใช้', maxLength: 100, required: true },
            { id: 'editPersonnelAddress', errorId: 'editAddressError', label: 'ที่อยู่', maxLength: 500, required: true },
            {
                id: 'editPersonnelPhone',
                errorId: 'editPhoneError',
                label: 'เบอร์โทรศัพท์',
                validate: (value) => !value || /^[0-9]{10}$/.test(value),
                errorMessage: 'ต้องเป็นตัวเลข 10 หลัก',
                required: true
            },
            {
                id: 'editPersonnelPassword',
                errorId: 'editPasswordError',
                label: 'รหัสผ่าน',
                validate: (value) => !value || /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{6,100}$/.test(value),
                errorMessage: 'ต้องมีตัวอักษรพิมพ์เล็ก พิมพ์ใหญ่ ตัวเลข และอักขระพิเศษอย่างน้อย 1 ตัว',
                required: false
            },
            { id: 'editPersonnelTypeId', errorId: 'editTypeError', label: 'ประเภทบุคลากร', required: true }
        ];
        if (validateFormAscForm('editForm', fields)) {
            e.target.submit();
        }
    });

    // Toggle Password Visibility
    document.getElementById('toggleAddPassword').addEventListener('click', () => {
        const passwordInput = document.getElementById('addPersonnelPassword');
        const icon = document.getElementById('toggleAddPassword').querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    document.getElementById('toggleEditPassword').addEventListener('click', () => {
        const passwordInput = document.getElementById('editPersonnelPassword');
        const icon = document.getElementById('toggleEditPassword').querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});