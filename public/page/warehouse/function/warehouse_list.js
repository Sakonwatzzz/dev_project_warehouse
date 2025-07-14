const showAddModal = () => {
    document.getElementById('addWarehouseName').value = '';
    document.getElementById('addWarehouseStatus').value = '';
    ['addNameError', 'addStatusError'].forEach(id => document.getElementById(id).classList.add('hidden'));
    document.getElementById('addModal').classList.remove('hidden');
};

const closeAddModal = () => {
    document.getElementById('addModal').classList.add('hidden');
};

const showEditModal = (id, name, status) => {
    document.getElementById('editWarehouseId').value = id;
    document.getElementById('editWarehouseName').value = name;
    document.getElementById('editWarehouseStatus').value = status;
    ['editNameError', 'editStatusError'].forEach(id => document.getElementById(id).classList.add('hidden'));
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

const confirmDelete = (form) => {
    showDeleteModal();
    document.getElementById('confirmDeleteBtn').onclick = () => form.submit();
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

const exportPDF = () => {
    window.open('/page/warehouse/report/export_warehouse_pdf.php', '_blank');
};

const exportExcel = () => {
    window.open('/page/warehouse/report/export_warehouse_excel.php', '_blank');
};

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const searchInput = document.getElementById('searchInput');

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
            if (searchInput.value.trim() === '') {
                searchInput.form.submit();
            }
        });
    }

    if (urlParams.has('success')) {
        showNotification(decodeURIComponent(urlParams.get('success')), 'bg-green-500');
    } else if (urlParams.has('error')) {
        showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
    }

    const validateForm = (formId, fields) => {
        let hasError = false;
        fields.forEach(({ id, errorId, label, maxLength, regex, isOptional }) => {
            const input = document.getElementById(id);
            const error = document.getElementById(errorId);
            if (!isOptional && !input.value.trim()) {
                error.textContent = `กรุณากรอก${label}`;
                error.classList.remove('hidden');
                hasError = true;
            } else if (input.value.length > maxLength) {
                error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
                error.classList.remove('hidden');
                hasError = true;
            } else if (regex && input.value && !regex.test(input.value)) {
                error.textContent = `${label}ไม่ถูกต้อง`;
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
            {
                id: 'addWarehouseName',
                errorId: 'addNameError',
                label: 'ชื่อคลังสินค้า',
                maxLength: 255
            },
            {
                id: 'addWarehouseStatus',
                errorId: 'addStatusError',
                label: 'สถานะ',
                maxLength: Infinity
            }
        ];
        if (validateForm('addForm', fields)) {
            e.target.submit();
        }
    });

    document.getElementById('editForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const fields = [
            {
                id: 'editWarehouseName',
                errorId: 'editNameError',
                label: 'ชื่อคลังสินค้า',
                maxLength: 255
            },
            {
                id: 'editWarehouseStatus',
                errorId: 'editStatusError',
                label: 'สถานะ',
                maxLength: Infinity
            }
        ];
        if (validateForm('editForm', fields)) {
            e.target.submit();
        }
    });
});