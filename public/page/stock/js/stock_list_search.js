function updateSearch() {
  const input = document.getElementById("searchInput").value.trim();
  let startDate = document.getElementById("startDate").value;
  let endDate = document.getElementById("endDate").value;
  const dateRange = document.getElementById("dateRangeSelect").value;

  // ตรวจสอบและแปลงวันที่ DD/MM/YYYY เป็น YYYY-MM-DD
  if (startDate) {
    const [day, month, year] = startDate.split("/");
    if (day && month && year && !isNaN(new Date(`${year}-${month}-${day}`))) {
      startDate = `${year}-${month}-${day}`;
    } else {
      startDate = "";
    }
  }
  if (endDate) {
    const [day, month, year] = endDate.split("/");
    if (day && month && year && !isNaN(new Date(`${year}-${month}-${day}`))) {
      endDate = `${year}-${month}-${day}`;
    } else {
      endDate = "";
    }
  }

  // อัปเดต URL
  const params = new URLSearchParams();
  params.set("page", "stock_list");
  params.set("page_num", 1);
  if (input) params.set("search", input);
  if (dateRange === "custom" && startDate && endDate) {
    params.set("start_date", startDate);
    params.set("end_date", endDate);
  }
  params.set("date_range", dateRange);
  window.location.href = `${window.location.pathname}?${params.toString()}`;
}

// รีเซ็ตการกรองทั้งหมด
function resetFilter() {
  const params = new URLSearchParams();
  params.set("page", "stock_list");
  params.set("page_num", 1);
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

// อัปเดตเมื่อเปลี่ยนวันที่หรือช่วงวันที่
document.getElementById("startDate").addEventListener("change", updateSearch);
document.getElementById("endDate").addEventListener("change", updateSearch);
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
  toggleCustomDateRange();
});
