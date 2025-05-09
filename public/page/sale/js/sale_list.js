function updateSearch() {
  const input = document.getElementById("searchInput").value.trim();
  let startDate = document.getElementById("startDate").value;
  let endDate = document.getElementById("endDate").value;
  const saleStatus = document.getElementById("saleStatusSelect").value;
  const dateRange = document.getElementById("dateRangeSelect").value;

  // แปลง DD/MM/YYYY เป็น YYYY-MM-DD สำหรับ URL
  if (startDate) {
    const [day, month, year] = startDate.split("/");
    startDate = `${year}-${month}-${day}`;
  }
  if (endDate) {
    const [day, month, year] = endDate.split("/");
    endDate = `${year}-${month}-${day}`;
  }

  // อัปเดต URL
  const params = new URLSearchParams(window.location.search);
  params.set("search", input);
  params.set("start_date", dateRange === "custom" ? startDate : "");
  params.set("end_date", dateRange === "custom" ? endDate : "");
  params.set("status", saleStatus);
  params.set("date_range", dateRange);
  params.set("page_num", 1); // รีเซ็ตเป็นหน้าแรกเมื่อเปลี่ยนการกรอง
  window.location.href = `${window.location.pathname}?${params.toString()}`;
}

// รีเซ็ตการกรองทั้งหมด
function resetFilter() {
  const params = new URLSearchParams();
  params.set("page", "sale_list");
  params.set("page_num", 1);
  params.set("status", "1"); // ตั้งสถานะเป็น '1'
  window.location.href = `${window.location.pathname}?${params.toString()}`;
}

// ควบคุมการแสดง/ซ่อนช่องวันที่กำหนดเอง
function toggleCustomDateRange() {
  const dateRange = document.getElementById("dateRangeSelect").value;
  const customDateRange = document.getElementById("customDateRange");
  const startDateInput = document.getElementById("startDate");
  const endDateInput = document.getElementById("endDate");

  if (dateRange === "custom") {
    customDateRange.classList.remove("hidden", "opacity-0", "scale-95");
    customDateRange.classList.add("opacity-100", "scale-100");
    // ตั้งค่าวันที่ปัจจุบันถ้าไม่มีค่าเดิม
    if (!startDateInput.value) {
      startDateInput.value = new Date().toLocaleDateString("th-TH", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      });
    }
    if (!endDateInput.value) {
      endDateInput.value = new Date().toLocaleDateString("th-TH", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      });
    }
  } else {
    customDateRange.classList.add("hidden", "opacity-0", "scale-95");
    customDateRange.classList.remove("opacity-100", "scale-100");
    startDateInput.value = "";
    endDateInput.value = "";
  }
  updateSearch();
}

// เพิ่ม Debounce สำหรับ searchInput
let timeout;
document.getElementById("searchInput").addEventListener("keyup", () => {
  clearTimeout(timeout);
  timeout = setTimeout(updateSearch, 300);
});

// อัปเดตเมื่อเปลี่ยนวันที่, สถานะ, หรือช่วงวันที่
document.getElementById("startDate").addEventListener("change", updateSearch);
document.getElementById("endDate").addEventListener("change", updateSearch);
document
  .getElementById("saleStatusSelect")
  .addEventListener("change", updateSearch);
document
  .getElementById("dateRangeSelect")
  .addEventListener("change", toggleCustomDateRange);
document.getElementById("resetFilter").addEventListener("click", resetFilter);

// เริ่มต้น Flatpickr และ toggleCustomDateRange เมื่อโหลดหน้า
document.addEventListener("DOMContentLoaded", () => {
  flatpickr("#startDate", {
    dateFormat: "d/m/Y",
    locale: "th",
    defaultDate: new Date(),
  });
  flatpickr("#endDate", {
    dateFormat: "d/m/Y",
    locale: "th",
    defaultDate: new Date(),
  });
  // toggleCustomDateRange();
});
