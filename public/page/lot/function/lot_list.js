const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function showDeleteModal() {
  document.getElementById("deleteModal").classList.remove("hidden");
}

function closeDeleteModal() {
  document.getElementById("deleteModal").classList.add("hidden");
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

      fetch("/page/lot/ajax/delete_lot_list.php", {
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

  setTimeout(hideNotification, 5000);
}

function hideNotification() {
  document.getElementById("notification").classList.add("translate-x-full");
}

function updateTable(pageNum = 1) {
  const urlParams = new URLSearchParams();
  urlParams.set("page", "lot_list");
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

  fetch(`../page/lot/ajax/lot_list_table.php?${urlParams.toString()}`, {
    headers: { Accept: "text/html" },
  })
    .then((response) => {
      if (!response.ok) throw new Error("Network error");
      return response.text();
    })
    .then((data) => {
      document.getElementById("lotTable").innerHTML = data;
      history.pushState({}, "", `?${urlParams.toString()}`);
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification("เกิดข้อผิดพลาดขณะโหลดตาราง", "error");
      document.getElementById("lotTable").innerHTML =
        '<div class="text-center py-4 text-gray-600">เกิดข้อผิดพลาด</div>';
    });
}

function goToPage(pageNum) {
  updateTable(pageNum);
}

function exportPDF() {
  const startDate = document.getElementById("start_date").value;
  const endDate = document.getElementById("end_date").value;
  let url = "../page/lot/ajax/report/export_lot_list_pdf.php";
  const urlParams = new URLSearchParams();
  if (startDate) urlParams.set("start_date", startDate);
  if (endDate) urlParams.set("end_date", endDate);
  if (urlParams.toString()) url += `?${urlParams.toString()}`;
  window.open(url, "_blank");
}

function exportExcel() {
  const startDate = document.getElementById("start_date").value;
  const endDate = document.getElementById("end_date").value;
  let url = "../page/lot/ajax/report/export_lot_list_excel.php";
  const urlParams = new URLSearchParams();
  if (startDate) urlParams.set("start_date", startDate);
  if (endDate) urlParams.set("end_date", endDate);
  if (urlParams.toString()) url += `?${urlParams.toString()}`;
  window.open(url, "_blank");
}

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
});

let debounceTimer;
function debounceUpdateTable() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(updateTable, 300);
}
