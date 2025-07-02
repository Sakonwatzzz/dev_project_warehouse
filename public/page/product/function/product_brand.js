function showAddModal() {
  document.getElementById("addBrandName").value = "";
  document.getElementById("addPersonnelId").value = "";
  document.getElementById("addNameError").classList.add("hidden");
  document.getElementById("addPersonnelError").classList.add("hidden");
  document.getElementById("addModal").classList.remove("hidden");
}

function closeAddModal() {
  document.getElementById("addModal").classList.add("hidden");
}

function showEditModal(id, name, personnelId) {
  document.getElementById("editBrandId").value = id;
  document.getElementById("editBrandName").value = name;
  document.getElementById("editPersonnelId").value = personnelId;
  document.getElementById("editNameError").classList.add("hidden");
  document.getElementById("editPersonnelError").classList.add("hidden");
  document.getElementById("editModal").classList.remove("hidden");
}

function closeEditModal() {
  document.getElementById("editModal").classList.add("hidden");
}

function showDeleteModal() {
  document.getElementById("deleteModal").classList.remove("hidden");
}

function closeDeleteModal() {
  document.getElementById("deleteModal").classList.add("hidden");
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
  urlParams.set("page", "product_brand");
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

  fetch(`/page/product/ajax/product_brand_table.php?${urlParams.toString()}`)
    .then((response) => {
      if (!response.ok) throw new Error("Network error");
      return response.text();
    })
    .then((data) => {
      try {
        const json = JSON.parse(data);
        showNotification(json.error, "error");
        document.getElementById("productBrandTable").innerHTML =
          '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
      } catch (e) {
        document.getElementById("productBrandTable").innerHTML = data;
        history.pushState({}, "", `?${urlParams.toString()}`);
      }
    })
    .catch((error) => {
      console.error("เกิดข้อผิดพลาดขณะโหลดตาราง:", error);
      showNotification("เกิดข้อผิดพลาดขณะโหลดตาราง", "error");
      document.getElementById("productBrandTable").innerHTML =
        '<div class="text-center py-4 text-gray-600">เกิดข้อผิดพลาด</div>';
    });
}

function goToPage(pageNum) {
  const urlParams = new URLSearchParams();
  urlParams.set("page", "product_brand");
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

  fetch(`/page/product/ajax/product_brand_table.php?${urlParams.toString()}`)
    .then((response) => {
      if (!response.ok) throw new Error("Network error");
      return response.text();
    })
    .then((data) => {
      try {
        const json = JSON.parse(data);
        showNotification(json.error, "error");
        document.getElementById("productBrandTable").innerHTML =
          '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
      } catch (e) {
        document.getElementById("productBrandTable").innerHTML = data;
        history.pushState({}, "", `?${urlParams.toString()}`);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification("เกิดข้อผิดพลาดขณะโหลดตาราง", "error");
      document.getElementById("productBrandTable").innerHTML =
        '<div class="text-center py-4 text-gray-600">เกิดข้อผิดพลาด</div>';
    });
}

function exportPDF() {
  const startDate = document.getElementById("start_date").value;
  const endDate = document.getElementById("end_date").value;
  let url = "/page/product/report/export_product_brand_pdf.php";
  if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
  if (endDate)
    url += `${startDate ? "&" : "?"}end_date=${encodeURIComponent(endDate)}`;
  window.open(url, "_blank");
}

function exportExcel() {
  const startDate = document.getElementById("start_date").value;
  const endDate = document.getElementById("end_date").value;
  let url = "/page/product/report/export_product_brand_excel.php";
  const urlParams = new URLSearchParams();
  if (startDate) urlParams.set("start_date", startDate);
  if (endDate) urlParams.set("end_date", endDate);
  if (urlParams.toString()) url += `?${urlParams.toString()}`;
  window.open(url, "_blank");
}

function validateForm(formId, fields) {
  let hasError = false;
  fields.forEach(({ id, errorId, label, maxLength, isSelect }) => {
    const input = document.getElementById(id);
    const error = document.getElementById(errorId);
    const value = input.value.trim();

    if (!value) {
      error.textContent = `กรุณา${isSelect ? "เลือก" : "กรอก"}${label}`;
      error.classList.remove("hidden");
      hasError = true;
    } else if (!isSelect && value.length > maxLength) {
      error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
      error.classList.remove("hidden");
      hasError = true;
    } else {
      error.classList.add("hidden");
    }
  });
  return !hasError;
}

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
      formData.append("action", "delete");

      fetch("/page/product/ajax/delete_product_brand.php", {
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

  searchForm.addEventListener("submit", (e) => {
    e.preventDefault();
    updateTable();
  });

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

  startDateInput.addEventListener("change", updateTable);
  endDateInput.addEventListener("change", updateTable);

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has("success")) {
    showNotification(decodeURIComponent(urlParams.get("success")), "success");
  } else if (urlParams.has("error")) {
    showNotification(decodeURIComponent(urlParams.get("error")), "error");
  }

  updateTable();

  document.getElementById("addForm").addEventListener("submit", (e) => {
    e.preventDefault();
    const fields = [
      {
        id: "addBrandName",
        errorId: "addNameError",
        label: "ชื่อแบรนด์สินค้า",
        maxLength: 255,
      },
      {
        id: "addPersonnelId",
        errorId: "addPersonnelError",
        label: "พนักงาน",
        maxLength: Infinity,
        isSelect: true,
      },
    ];
    if (validateForm("addForm", fields)) {
      e.target.submit();
    }
  });

  document.getElementById("editForm").addEventListener("submit", (e) => {
    e.preventDefault();
    const fields = [
      {
        id: "editBrandName",
        errorId: "editNameError",
        label: "ชื่อแบรนด์สินค้า",
        maxLength: 255,
      },
      {
        id: "editPersonnelId",
        errorId: "editPersonnelError",
        label: "พนักงาน",
        maxLength: Infinity,
        isSelect: true,
      },
    ];
    if (validateForm("editForm", fields)) {
      e.target.submit();
    }
  });
});

let debounceTimer;
function debounceUpdateTable() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(updateTable, 300);
}
