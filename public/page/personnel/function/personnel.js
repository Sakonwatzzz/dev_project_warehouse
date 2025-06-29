// personnel.js
// ฟังก์ชันสำหรับจัดการโมดัลและการทำงานของหน้า
function showAddModal() {
  document.getElementById("addPersonnelName").value = "";
  document.getElementById("addPersonnelUsername").value = "";
  document.getElementById("addPersonnelAddress").value = "";
  document.getElementById("addPersonnelPhone").value = "";
  document.getElementById("addPersonnelPassword").value = "";
  document.getElementById("addPersonnelTypeId").value = "";
  [
    "addNameError",
    "addUsernameError",
    "addAddressError",
    "addPhoneError",
    "addPasswordError",
    "addTypeError",
  ].forEach((id) => document.getElementById(id).classList.add("hidden"));
  document.getElementById("addModal").classList.remove("hidden");
}

function closeAddModal() {
  document.getElementById("addModal").classList.add("hidden");
}

function showEditModal(id, name, username, typeId, address, phone, password) {
  document.getElementById("editPersonnelId").value = id;
  document.getElementById("editPersonnelName").value = name;
  document.getElementById("editPersonnelUsername").value = username;
  document.getElementById("editPersonnelAddress").value = address;
  document.getElementById("editPersonnelPhone").value = phone;
  document.getElementById("editPersonnelPassword").value = password || "";
  document.getElementById("editPersonnelTypeId").value = typeId;
  [
    "editNameError",
    "editUsernameError",
    "editAddressError",
    "editPhoneError",
    "editPasswordError",
    "editTypeError",
  ].forEach((id) => document.getElementById(id).classList.add("hidden"));
  document.getElementById("editModal").classList.remove("hidden");
}

function closeEditModal() {
  document.getElementById("editModal").classList.add("hidden");
}

function showNotification(message, type) {
  const notification = document.getElementById("notification");
  const header = document.getElementById("notification-header");
  const title = document.getElementById("notificationTitle");
  const body = document.getElementById("notificationMessage");
  const icon = document.getElementById("notificationIcon");

  header.className = `flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg ${
    type === "success" ? "bg-green-500" : "bg-red-500"
  }`;
  title.textContent = "แจ้งเตือน";
  body.textContent = message;
  icon.className = `fas ${
    type === "success" ? "fa-check-circle" : "fa-exclamation-circle"
  } text-2xl`;
  notification.classList.remove("translate-x-full");

  setTimeout(() => {
    hideNotification();
  }, 5000);
}

function hideNotification() {
  document.getElementById("notification").classList.add("translate-x-full");
}

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

  fetch(`/page/personnel/ajax/personnel_table.php?${urlParams.toString()}`)
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

  fetch(`/page/personnel/ajax/personnel_table.php?${urlParams.toString()}`)
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
  const startDate = document.getElementById("start_date").value;
  const endDate = document.getElementById("end_date").value;
  let url = "/page/personnel/report/export_personnel_pdf.php";
  if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
  if (endDate)
    url += `${startDate ? "&" : "?"}end_date=${encodeURIComponent(endDate)}`;
  window.open(url, "_blank");
};

function exportExcel() {
  const startDate = document.getElementById("start_date").value;
  const endDate = document.getElementById("end_date").value;
  let url = "/page/personnel/report/export_personnel_excel.php";
  const urlParams = new URLSearchParams();
  if (startDate) urlParams.set("start_date", startDate);
  if (endDate) urlParams.set("end_date", endDate);
  if (urlParams.toString()) url += `?${urlParams.toString()}`;
  window.open(url, "_blank");
}

function validateForm(formId, fields) {
  let hasError = false;
  fields.forEach(
    ({ id, errorId, label, maxLength, regex, regexError, isOptional }) => {
      const input = document.getElementById(id);
      const error = document.getElementById(errorId);
      const value = input.value.trim();

      if (id === "editPersonnelPassword") {
        if (value) {
          if (value.length > maxLength) {
            error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
            error.classList.remove("hidden");
            hasError = true;
          } else if (regex && !regex.test(value)) {
            error.textContent = regexError;
            error.classList.remove("hidden");
            hasError = true;
          } else {
            error.classList.add("hidden");
          }
        } else {
          error.classList.add("hidden");
        }
      } else {
        if (!isOptional && !value) {
          error.textContent = `กรุณากรอก${label}`;
          error.classList.remove("hidden");
          hasError = true;
        } else if (value.length > maxLength) {
          error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
          error.classList.remove("hidden");
          hasError = true;
        } else if (regex && value && !regex.test(value)) {
          error.textContent = regexError || `${label}รูปแบบไม่ถูกต้อง`;
          error.classList.remove("hidden");
          hasError = true;
        } else {
          error.classList.add("hidden");
        }
      }
    }
  );
  return !hasError;
}

const showDeleteModal = () => {
  document.getElementById("deleteModal").classList.remove("hidden");
};

const closeDeleteModal = () => {
  document.getElementById("deleteModal").classList.add("hidden");
};

function confirmDelete(id) {
  showDeleteModal();
  const confirmBtn = document.getElementById("confirmDeleteBtn");
  if (confirmBtn) {
    confirmBtn.onclick = () => {
      const formData = new FormData();
      formData.append("id", id);
      formData.append(
        "csrf_token",
        document.querySelector('input[name="csrf_token"]').value
      );

      fetch("/page/personnel/ajax/delete_personnel.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showNotification(data.success, "success");
            updateTable();
          } else {
            showNotification(data.error, "error");
          }
          closeDeleteModal();
        })
        .catch((error) => {
          console.error("Error:", error);
          showNotification("เกิดข้อผิดพลาดในการลบ", "error");
          closeDeleteModal();
        });
    };
  } else {
    console.error("confirmDeleteBtn not found");
    showNotification("เกิดข้อผิดพลาด: ไม่พบปุ่มยืนยันการลบ", "error");
  }
}

// Event Listeners
document.addEventListener("DOMContentLoaded", () => {
  const searchForm = document.getElementById("searchForm");
  const searchInput = document.getElementById("searchInput");
  const clearSearch = document.getElementById("clearSearch");
  const startDateInput = document.getElementById("start_date");
  const endDateInput = document.getElementById("end_date");

  // ป้องกันการ submit ฟอร์ม
  searchForm.addEventListener("submit", (e) => {
    e.preventDefault();
    updateTable();
  });

  // การจัดการช่องค้นหา
  searchInput.addEventListener("input", () => {
    clearSearch.style.display = searchInput.value.length > 0 ? "block" : "none";
    debounceUpdateTable();
  });

  searchInput.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      searchInput.value = "";
      clearSearch.style.display = "none";
      updateTable();
    }
  });

  clearSearch.addEventListener("click", () => {
    searchInput.value = "";
    clearSearch.style.display = "none";
    updateTable();
  });

  // อัปเดตเมื่อเปลี่ยนวันที่
  startDateInput.addEventListener("change", updateTable);
  endDateInput.addEventListener("change", updateTable);

  // การแจ้งเตือนเมื่อโหลดหน้า
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has("success")) {
    showNotification(decodeURIComponent(urlParams.get("success")), "success");
  } else if (urlParams.has("error")) {
    showNotification(decodeURIComponent(urlParams.get("error")), "error");
  }

  // อัปเดตตารางเมื่อโหลดหน้า
  updateTable();

  // การจัดการฟอร์มเพิ่ม
  document.getElementById("addForm").addEventListener("submit", (e) => {
    e.preventDefault();
    const fields = [
      {
        id: "addPersonnelName",
        errorId: "addNameError",
        label: "ชื่อ-นามสกุล",
        maxLength: 255,
      },
      {
        id: "addPersonnelUsername",
        errorId: "addUsernameError",
        label: "ชื่อผู้ใช้",
        maxLength: 100,
      },
      {
        id: "addPersonnelAddress",
        errorId: "addAddressError",
        label: "ที่อยู่",
        maxLength: 500,
      },
      {
        id: "addPersonnelPhone",
        errorId: "addPhoneError",
        label: "เบอร์โทรศัพท์",
        maxLength: 10,
        regex: /^[0-9]{10}$/,
        regexError: "เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก",
      },
      {
        id: "addPersonnelPassword",
        errorId: "addPasswordError",
        label: "รหัสผ่าน",
        maxLength: 100,
        regex:
          /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{6,100}$/,
        regexError:
          "รหัสผ่านต้องมีตัวอักษรพิมพ์เล็ก พิมพ์ใหญ่ ตัวเลข และอักขระพิเศษอย่างน้อย 1 ตัว",
      },
      {
        id: "addPersonnelTypeId",
        errorId: "addTypeError",
        label: "ประเภทบุคลากร",
        maxLength: Infinity,
      },
    ];
    if (validateForm("addForm", fields)) {
      e.target.submit();
    }
  });

  // การจัดการฟอร์มแก้ไข
  document.getElementById("editForm").addEventListener("submit", (e) => {
    e.preventDefault();
    const fields = [
      {
        id: "editPersonnelName",
        errorId: "editNameError",
        label: "ชื่อ-นามสกุล",
        maxLength: 255,
      },
      {
        id: "editPersonnelUsername",
        errorId: "editUsernameError",
        label: "ชื่อผู้ใช้",
        maxLength: 100,
      },
      {
        id: "editPersonnelAddress",
        errorId: "editAddressError",
        label: "ที่อยู่",
        maxLength: 500,
      },
      {
        id: "editPersonnelPhone",
        errorId: "editPhoneError",
        label: "เบอร์โทรศัพท์",
        maxLength: 10,
        regex: /^[0-9]{10}$/,
        regexError: "เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก",
      },
      {
        id: "editPersonnelPassword",
        errorId: "editPasswordError",
        label: "รหัสผ่าน",
        maxLength: 100,
        regex:
          /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{6,100}$/,
        regexError:
          "รหัสผ่านต้องมีตัวอักษรพิมพ์เล็ก พิมพ์ใหญ่ ตัวเลข และอักขระพิเศษอย่างน้อย 1 ตัว",
        isOptional: true,
      },
      {
        id: "editPersonnelTypeId",
        errorId: "editTypeError",
        label: "ประเภทบุคลากร",
        maxLength: Infinity,
      },
    ];
    if (validateForm("editForm", fields)) {
      e.target.submit();
    }
  });

  // การจัดการ toggle รหัสผ่าน
  document.getElementById("togglePassword").addEventListener("click", () => {
    const passwordInput = document.getElementById("editPersonnelPassword");
    const toggleIcon = document
      .getElementById("togglePassword")
      .querySelector("i");
    if (passwordInput.type === "password") {
      passwordInput.type = "text";
      toggleIcon.className = "fas fa-eye-slash";
    } else {
      passwordInput.type = "password";
      toggleIcon.className = "fas fa-eye";
    }
  });
});

// Debounce สำหรับลดการเรียก updateTable
let debounceTimer;
function debounceUpdateTable() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(updateTable, 300);
}
