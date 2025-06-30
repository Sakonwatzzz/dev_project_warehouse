function showAddModal() {
    document.getElementById("addPersonnelName").value = "";
    document.getElementById("addPersonnelUsername").value = "";
    document.getElementById("addPersonnelAddress").value = "";
    document.getElementById("addPersonnelPhone").value = "";
    document.getElementById("addPersonnelPassword").value = "";
    document.getElementById("addNameError").classList.add("hidden");
    document.getElementById("addUsernameError").classList.add("hidden");
    document.getElementById("addAddressError").classList.add("hidden");
    document.getElementById("addPhoneError").classList.add("hidden");
    document.getElementById("addPasswordError").classList.add("hidden");
    document.getElementById("addModal").classList.remove("hidden");
}

function closeAddModal() {
    document.getElementById("addModal").classList.add("hidden");
}

function showEditModal(id, name, username, address, phone, password, typeId) {
    document.getElementById("editPersonnelId").value = id;
    document.getElementById("editPersonnelName").value = name;
    document.getElementById("editPersonnelUsername").value = username;
    document.getElementById("editPersonnelAddress").value = address;
    document.getElementById("editPersonnelPhone").value = phone;
    document.getElementById("editPersonnelPassword").value = "";
    document.getElementById("editPersonnelTypeId").value = typeId;
    document.getElementById("editNameError").classList.add("hidden");
    document.getElementById("editUsernameError").classList.add("hidden");
    document.getElementById("editAddressError").classList.add("hidden");
    document.getElementById("editPhoneError").classList.add("hidden");
    document.getElementById("editPasswordError").classList.add("hidden");
    document.getElementById("editTypeError").classList.add("hidden");
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

    header.className = `flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg ${type === "success" ? "bg-green-500" : "bg-red-500"}`;
    title.textContent = "แจ้งเตือน";
    body.textContent = message;
    icon.className = `fas ${type === "success" ? "fa-check-circle" : "fa-exclamation-circle"} text-2xl`;
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
    urlParams.set("page", "personnel_group");
    urlParams.set("type", "<?php echo urlencode($type); ?>");
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
                document.getElementById("personnelGroupTable").innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } catch (e) {
                document.getElementById("personnelGroupTable").innerHTML = data;
                history.pushState({}, "", `?${urlParams.toString()}`);
            }
        })
        .catch((error) => {
            console.error("เกิดข้อผิดพลาดขณะโหลดตาราง:", error);
            showNotification("เกิดข้อผิดพลาดขณะโหลดตาราง", "error");
            document.getElementById("personnelGroupTable").innerHTML =
                '<div class="text-center py-4 text-gray-600">เกิดข้อผิดพลาด</div>';
        });
}

function goToPage(pageNum) {
    const urlParams = new URLSearchParams();
    urlParams.set("page", "personnel_group");
    urlParams.set("type", "<?php echo urlencode($type); ?>");
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
                document.getElementById("personnelGroupTable").innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } catch (e) {
                document.getElementById("personnelGroupTable").innerHTML = data;
                history.pushState({}, "", `?${urlParams.toString()}`);
            }
        })
        .catch((error) => {
            console.error("Error:", error);
            showNotification("เกิดข้อผิดพลาดขณะโหลดตาราง", "error");
            document.getElementById("personnelGroupTable").innerHTML =
                '<div class="text-center py-4 text-gray-600">เกิดข้อผิดพลาด</div>';
        });
}

function exportPDF() {
    const startDate = document.getElementById("start_date").value;
    const endDate = document.getElementById("end_date").value;
    let url = `/page/personnel/report/export_personnel_group_pdf.php?type=<?php echo urlencode($type); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>`;
    if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
    if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
    window.open(url, "_blank");
}

function exportExcel() {
    const startDate = document.getElementById("start_date").value;
    const endDate = document.getElementById("end_date").value;
    let url = `/page/personnel/report/export_personnel_group_excel.php?type=<?php echo urlencode($type); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>`;
    if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
    if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
    window.open(url, "_blank");
}

function validateForm(formId, fields) {
    let hasError = false;
    fields.forEach(({ id, errorId, label, maxLength, required, validate, errorMessage }) => {
        const input = document.getElementById(id);
        const error = document.getElementById(errorId);
        const value = input.value.trim();

        if (required && !value) {
            error.textContent = `กรุณากรอก${label}`;
            error.classList.remove("hidden");
            hasError = true;
        } else if (maxLength && value.length > maxLength) {
            error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
            error.classList.remove("hidden");
            hasError = true;
        } else if (validate && !validate(value)) {
            error.textContent = errorMessage;
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
            formData.append("action", "delete");
            formData.append("csrf_token", document.querySelector('input[name="csrf_token"]').value);

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

    // Prevent form submission
    searchForm.addEventListener("submit", (e) => {
        e.preventDefault();
        updateTable();
    });

    // Handle search input
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

    // Update table on date change
    startDateInput.addEventListener("change", updateTable);
    endDateInput.addEventListener("change", updateTable);

    // Show notifications on page load
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has("success")) {
        showNotification(decodeURIComponent(urlParams.get("success")), "success");
    } else if (urlParams.has("error")) {
        showNotification(decodeURIComponent(urlParams.get("error")), "error");
    }

    // Load table on page load
    updateTable();

    // Handle add form submission
    document.getElementById("addForm").addEventListener("submit", (e) => {
        e.preventDefault();
        const fields = [
            {
                id: "addPersonnelName",
                errorId: "addNameError",
                label: "ชื่อ-นามสกุล",
                maxLength: 255,
                required: true,
            },
            {
                id: "addPersonnelUsername",
                errorId: "addUsernameError",
                label: "ชื่อผู้ใช้",
                maxLength: 100,
                required: true,
            },
            {
                id: "addPersonnelAddress",
                errorId: "addAddressError",
                label: "ที่อยู่",
                maxLength: 500,
                required: true,
            },
            {
                id: "addPersonnelPhone",
                errorId: "addPhoneError",
                label: "เบอร์โทรศัพท์",
                validate: (value) => !value || /^[0-9]{10}$/.test(value),
                errorMessage: "ต้องเป็นตัวเลข 10 หลัก",
                required: true,
            },
            {
                id: "addPersonnelPassword",
                errorId: "addPasswordError",
                label: "รหัสผ่าน",
                validate: (value) => !value || value.length >= 6,
                errorMessage: "ต้องมีความยาวอย่างน้อย 6 ตัวอักษร",
                required: true,
            },
        ];
        if (validateForm("addForm", fields)) {
            e.target.submit();
        }
    });

    // Handle edit form submission
    document.getElementById("editForm").addEventListener("submit", (e) => {
        e.preventDefault();
        const fields = [
            {
                id: "editPersonnelName",
                errorId: "editNameError",
                label: "ชื่อ-นามสกุล",
                maxLength: 255,
                required: true,
            },
            {
                id: "editPersonnelUsername",
                errorId: "editUsernameError",
                label: "ชื่อผู้ใช้",
                maxLength: 100,
                required: true,
            },
            {
                id: "editPersonnelAddress",
                errorId: "editAddressError",
                label: "ที่อยู่",
                maxLength: 500,
                required: true,
            },
            {
                id: "editPersonnelPhone",
                errorId: "editPhoneError",
                label: "เบอร์โทรศัพท์",
                validate: (value) => !value || /^[0-9]{10}$/.test(value),
                errorMessage: "ต้องเป็นตัวเลข 10 หลัก",
                required: true,
            },
            {
                id: "editPersonnelPassword",
                errorId: "editPasswordError",
                label: "รหัสผ่าน",
                validate: (value) => !value || value.length >= 6,
                errorMessage: "ต้องมีความยาวอย่างน้อย 6 ตัวอักษร",
                required: false,
            },
            {
                id: "editPersonnelTypeId",
                errorId: "editTypeError",
                label: "ประเภทบุคลากร",
                required: true,
            },
        ];
        if (validateForm("editForm", fields)) {
            e.target.submit();
        }
    });
});

// Debounce for reducing updateTable calls
let debounceTimer;
function debounceUpdateTable() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(updateTable, 300);
}