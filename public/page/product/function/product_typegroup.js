const showAddModal = () => {
    document.getElementById('addProductId').value = '';
    document.getElementById('addProductName').value = '';
    document.getElementById('addProductPrice1').value = '';
    document.getElementById('addProductPrice2').value = '';
    document.getElementById('addProductPrice3').value = '';
    document.getElementById('addProductPrice4').value = '';
    document.getElementById('addProductCapital').value = '';
    document.getElementById('addIdError').classList.add('hidden');
    document.getElementById('addNameError').classList.add('hidden');
    document.getElementById('addPrice1Error').classList.add('hidden');
    document.getElementById('addPrice2Error').classList.add('hidden');
    document.getElementById('addPrice3Error').classList.add('hidden');
    document.getElementById('addPrice4Error').classList.add('hidden');
    document.getElementById('addCapitalError').classList.add('hidden');
    document.getElementById('addModal').classList.remove('hidden');
};

const closeAddModal = () => {
    document.getElementById('addModal').classList.add('hidden');
};

const showEditModal = (id, name, price1, price2, price3, price4, capital, typeId) => {
    document.getElementById('editProductId').value = id;
    document.getElementById('editProductIdDisplay').value = id;
    document.getElementById('editProductName').value = name;
    document.getElementById('editProductPrice1').value = price1;
    document.getElementById('editProductPrice2').value = price2;
    document.getElementById('editProductPrice3').value = price3;
    document.getElementById('editProductPrice4').value = price4;
    document.getElementById('editProductCapital').value = capital;
    document.getElementById('editProductTypeId').value = typeId;
    document.getElementById('editIdError').classList.add('hidden');
    document.getElementById('editNameError').classList.add('hidden');
    document.getElementById('editPrice1Error').classList.add('hidden');
    document.getElementById('editPrice2Error').classList.add('hidden');
    document.getElementById('editPrice3Error').classList.add('hidden');
    document.getElementById('editPrice4Error').classList.add('hidden');
    document.getElementById('editCapitalError').classList.add('hidden');
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

const viewAllData = () => {
    document.getElementById('searchInput').value = '';
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    document.getElementById('clearSearch').classList.add('hidden');
    localStorage.removeItem('searchPreserve');
    updateTable();
};

function updateTable() {
    console.log('updateTable called');
    const urlParams = new URLSearchParams();
    urlParams.set("page", "product_typegroup");
    urlParams.set("id", document.querySelector('input[name="id"]').value || '<?php echo urlencode($productTypeId); ?>');
    urlParams.set("page_num", "1");

    const searchInput = document.getElementById("searchInput").value.trim();
    const startDate = document.getElementById("start_date").value;
    const endDate = document.getElementById("end_date").value;

    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        if (end < start) {
            showNotification("วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น", "bg-red-500");
            return;
        }
    }

    if (searchInput) {
        urlParams.set("search", searchInput);
    }
    if (startDate) {
        urlParams.set("start_date", startDate);
    }
    if (endDate) {
        urlParams.set("end_date", endDate);
    }

    fetch(`/page/product/ajax/product_typegroup_table.php?${urlParams.toString()}`)
        .then((response) => {
            if (!response.ok) throw new Error("Network error");
            return response.text();
        })
        .then((data) => {
            if (data.startsWith('{')) {
                const json = JSON.parse(data);
                showNotification(json.error, "bg-red-500");
                document.getElementById("productTable").innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } else {
                document.getElementById("productTable").innerHTML = data;
                history.pushState({}, "", `?${urlParams.toString()}`);
            }
        })
        .catch((error) => {
            console.error("เกิดข้อผิดพลาดขณะโหลดตาราง:", error);
            showNotification("เกิดข้อผิดพลาดขณะโหลดตาราง", "bg-red-500");
        });
}

function goToPage(pageNum) {
    console.log('goToPage called with page:', pageNum);
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set("page_num", pageNum);

    const startDate = document.getElementById("start_date").value;
    const endDate = document.getElementById("end_date").value;

    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        if (end < start) {
            showNotification("วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น", "bg-red-500");
            return;
        }
    }

    if (startDate) {
        urlParams.set("start_date", startDate);
    } else {
        urlParams.delete("start_date");
    }

    if (endDate) {
        urlParams.set("end_date", endDate);
    } else {
        urlParams.delete("end_date");
    }

    fetch(`/page/product/ajax/product_typegroup_table.php?${urlParams.toString()}`)
        .then((response) => {
            if (!response.ok) throw new Error("Network error");
            return response.text();
        })
        .then((data) => {
            if (data.startsWith('{')) {
                const json = JSON.parse(data);
                showNotification(json.error, "bg-red-500");
                document.getElementById("productTable").innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } else {
                document.getElementById("productTable").innerHTML = data;
                history.pushState({}, "", `?${urlParams.toString()}`);
            }
        })
        .catch((error) => {
            console.error("Error:", error);
            showNotification("เกิดข้อผิดพลาดขณะโหลดตาราง", "bg-red-500");
        });
}

const exportPDF = () => {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const exportBtn = document.getElementById('export-buttons');
    const id = exportBtn.dataset.id;
    const token = exportBtn.dataset.token;

    if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
        showNotification("วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น", "bg-red-500");
        return;
    }

    let url = `/page/product/report/export_product_typegroup_pdf.php?id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(token)}`;
    if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
    if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
    window.open(url, '_blank');
};

const exportExcel = () => {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const exportBtn = document.getElementById('export-excel-button');
    const id = exportBtn.dataset.id;
    const token = exportBtn.dataset.token;

    if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
        showNotification("วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น", "bg-red-500");
        return;
    }

    let url = `/page/product/report/export_product_typegroup_excel.php?id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(token)}`;
    if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
    if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
    window.open(url, '_blank');
};

const loadTable = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id') || '';
    const search = urlParams.get('search') || '';
    const startDate = urlParams.get('start_date') || '';
    const endDate = urlParams.get('end_date') || '';
    const pageNum = urlParams.get('page_num') || '1';

    document.getElementById('searchInput').value = search;
    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;

    const fetchUrl = new URLSearchParams();
    fetchUrl.set('id', id);
    fetchUrl.set('search', search);
    fetchUrl.set('start_date', startDate);
    fetchUrl.set('end_date', endDate);
    fetchUrl.set('page_num', pageNum);

    fetch(`/page/product/ajax/product_typegroup_table.php?${fetchUrl.toString()}`)
        .then(response => {
            if (!response.ok) throw new Error("Network error");
            return response.text();
        })
        .then(data => {
            if (data.startsWith('{')) {
                const json = JSON.parse(data);
                showNotification(json.error, "bg-red-500");
                document.getElementById('productTable').innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } else {
                document.getElementById('productTable').innerHTML = data;
            }
        })
        .catch(error => {
            console.error('Error loading table:', error);
            showNotification('เกิดข้อผิดพลาดในการโหลดข้อมูล', 'bg-red-500');
        });
};

// Debounce function to delay table updates
let debounceTimer;
function debounceUpdateTable() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(updateTable, 300);
}

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start_date') || '';
    const endDate = urlParams.get('end_date') || '';
    const searchInput = document.getElementById('searchInput');
    const clearSearch = document.getElementById('clearSearch');

    if (startDate) {
        document.getElementById('start_date').value = startDate;
    }
    if (endDate) {
        document.getElementById('end_date').value = endDate;
    }

    loadTable();

    if (searchInput) {
        searchInput.focus();
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                searchInput.value = '';
                clearSearch.classList.add('hidden');
                updateTable();
            }
            if (e.key === 'Enter') {
                localStorage.setItem('searchPreserve', searchInput.value);
                updateTable();
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
            debounceUpdateTable();
        });

        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            clearSearch.classList.add('hidden');
            updateTable();
        });
    }

    if (urlParams.has('success')) {
        showNotification(decodeURIComponent(urlParams.get('success')), 'bg-green-500');
    } else if (urlParams.has('error')) {
        showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
    }

    const validateForm = (formId, fields) => {
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
            { id: 'addProductId', errorId: 'addIdError', label: 'รหัสสินค้า', required: true },
            { id: 'addProductName', errorId: 'addNameError', label: 'ชื่อสินค้า', maxLength: 255, required: true },
            {
                id: 'addProductPrice1',
                errorId: 'addPrice1Error',
                label: 'ราคาขาย 1',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            },
            {
                id: 'addProductPrice2',
                errorId: 'addPrice2Error',
                label: 'ราคาขาย 2',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            },
            {
                id: 'addProductPrice3',
                errorId: 'addPrice3Error',
                label: 'ราคาขาย 3',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            },
            {
                id: 'addProductPrice4',
                errorId: 'addPrice4Error',
                label: 'ราคาขาย 4',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            },
            {
                id: 'addProductCapital',
                errorId: 'addCapitalError',
                label: 'ราคาทุน',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            }
        ];
        if (validateForm('addForm', fields)) {
            e.target.submit();
        }
    });

    document.getElementById('editForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const fields = [
            { id: 'editProductName', errorId: 'editNameError', label: 'ชื่อสินค้า', maxLength: 255, required: true },
            {
                id: 'editProductPrice1',
                errorId: 'editPrice1Error',
                label: 'ราคาขาย 1',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            },
            {
                id: 'editProductPrice2',
                errorId: 'editPrice2Error',
                label: 'ราคาขาย 2',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            },
            {
                id: 'editProductPrice3',
                errorId: 'editPrice3Error',
                label: 'ราคาขาย 3',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            },
            {
                id: 'editProductPrice4',
                errorId: 'editPrice4Error',
                label: 'ราคาขาย 4',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            },
            {
                id: 'editProductCapital',
                errorId: 'editCapitalError',
                label: 'ราคาทุน',
                validate: (value) => !value || (!isNaN(value) && value >= 0),
                errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                required: true
            },
            { id: 'editProductTypeId', errorId: 'editTypeError', label: 'ประเภทสินค้า', required: true }
        ];
        if (validateForm('editForm', fields)) {
            e.target.submit();
        }
    });

    document.getElementById('start_date').addEventListener('change', () => {
        console.log('start_date changed');
        updateTable();
    });
    document.getElementById('end_date').addEventListener('change', () => {
        console.log('end_date changed');
        updateTable();
    });
});