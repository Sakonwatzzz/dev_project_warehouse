const showAddModal = () => {
    document.getElementById('addMemberName').value = '';
    document.getElementById('addMemberPhone').value = '';
    document.getElementById('addNameError').classList.add('hidden');
    document.getElementById('addPhoneError').classList.add('hidden');
    document.getElementById('addModal').classList.remove('hidden');
};

const closeAddModal = () => {
    document.getElementById('addModal').classList.add('hidden');
};

const showEditModal = (id, name, phone, typeId) => {
    document.getElementById('editMemberId').value = id;
    document.getElementById('editMemberName').value = name;
    document.getElementById('editMemberPhone').value = phone;
    document.getElementById('editMemberTypeId').value = typeId;
    document.getElementById('editNameError').classList.add('hidden');
    document.getElementById('editPhoneError').classList.add('hidden');
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
    // รีเซ็ตช่องค้นหาและวันที่
    document.getElementById('searchInput').value = '';
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    document.getElementById('clearSearch').classList.add('hidden');
    localStorage.removeItem('searchPreserve');

    // อัปเดตตารางโดยไม่มีตัวกรอง
    updateTable();
};

// อัปเดตฟังก์ชัน updateTable เพื่อจัดการกรณีที่ไม่มีตัวกรอง
function updateTable() {
    console.log('updateTable called');
    const urlParams = new URLSearchParams();
    urlParams.set("page", "member_group");
    urlParams.set("type", document.querySelector('input[name="type"]')?.value || '<?php echo urlencode($type); ?>');
    urlParams.set("page_num", "1");

    const searchInput = document.getElementById("searchInput").value.trim();
    const startDate = document.getElementById("start_date").value;
    const endDate = document.getElementById("end_date").value;

    // ตรวจสอบวันที่ก่อนเพิ่มพารามิเตอร์
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        if (end < start) {
            showNotification("วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น", "bg-red-500");
            return;
        }
    }

    // เพิ่มพารามิเตอร์เฉพาะเมื่อมีค่า
    if (searchInput) {
        urlParams.set("search", searchInput);
    }
    if (startDate) {
        urlParams.set("start_date", startDate);
    }
    if (endDate) {
        urlParams.set("end_date", endDate);
    }

    fetch(`/page/member/ajax/member_group_table.php?${urlParams.toString()}`)
        .then((response) => {
            if (!response.ok) throw new Error("Network error");
            return response.text();
        })
        .then((data) => {
            if (data.startsWith('{')) {
                const json = JSON.parse(data);
                showNotification(json.error, "bg-red-500");
                document.getElementById("memberTable").innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } else {
                document.getElementById("memberTable").innerHTML = data;
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

    // อัปเดตวันที่จาก input fields
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

    fetch(`/page/member/ajax/member_group_table.php?${urlParams.toString()}`)
        .then((response) => {
            if (!response.ok) throw new Error("Network error");
            return response.text();
        })
        .then((data) => {
            if (data.startsWith('{')) {
                const json = JSON.parse(data);
                showNotification(json.error, "bg-red-500");
                document.getElementById("memberTable").innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } else {
                document.getElementById("memberTable").innerHTML = data;
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
    const type = exportBtn.dataset.type;
    const token = exportBtn.dataset.token;

    if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
        showNotification("วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น", "bg-red-500");
        return;
    }

    let url = `/page/member/report/export_member_group_pdf.php?type=${encodeURIComponent(type)}&csrf_token=${encodeURIComponent(token)}`;
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

    if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
        showNotification("วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น", "bg-red-500");
        return;
    }

    let url = `/page/member/report/export_member_group_excel.php?type=${encodeURIComponent(type)}&csrf_token=${encodeURIComponent(token)}`;
    if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
    if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
    window.open(url, '_blank');
};

const loadTable = () => {
    const urlParams = new URLSearchParams(window.location.search);

    // ดึงพารามิเตอร์จาก URL
    const type = urlParams.get('type') || '';
    const search = urlParams.get('search') || '';
    const startDate = urlParams.get('start_date') || '';
    const endDate = urlParams.get('end_date') || '';
    const pageNum = urlParams.get('page_num') || '1';

    // ตั้งค่าให้ input fields
    document.getElementById('searchInput').value = search;
    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;

    // สร้าง URL สำหรับ fetch
    const fetchUrl = new URLSearchParams();
    fetchUrl.set('type', type);
    fetchUrl.set('search', search);
    fetchUrl.set('start_date', startDate);
    fetchUrl.set('end_date', endDate);
    fetchUrl.set('page_num', pageNum);

    fetch(`/page/member/ajax/member_group_table.php?${fetchUrl.toString()}`)
        .then(response => {
            if (!response.ok) throw new Error("Network error");
            return response.text();
        })
        .then(data => {
            if (data.startsWith('{')) {
                const json = JSON.parse(data);
                showNotification(json.error, "bg-red-500");
                document.getElementById('memberTable').innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } else {
                document.getElementById('memberTable').innerHTML = data;
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

    // ตั้งค่าให้ input fields
    if (startDate) {
        document.getElementById('start_date').value = startDate;
    }
    if (endDate) {
        document.getElementById('end_date').value = endDate;
    }

    // เรียกใช้ loadTable หลังจากตั้งค่าเสร็จ
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

        // แก้ไข input event เพื่ออัปเดตทันทีเมื่อพิมพ์
        searchInput.addEventListener('input', () => {
            clearSearch.classList.toggle('hidden', !searchInput.value.trim());
            debounceUpdateTable(); // เรียก debounceUpdateTable ทุกครั้งที่พิมพ์
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
            { id: 'addMemberName', errorId: 'addNameError', label: 'ชื่อ-นามสกุล', maxLength: 255, required: true },
            {
                id: 'addMemberPhone',
                errorId: 'addPhoneError',
                label: 'เบอร์โทรศัพท์',
                validate: (value) => !value || /^[0-9]{10}$/.test(value),
                errorMessage: 'ต้องเป็นตัวเลข 10 หลัก',
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
            { id: 'editMemberName', errorId: 'editNameError', label: 'ชื่อ-นามสกุล', maxLength: 255, required: true },
            {
                id: 'editMemberPhone',
                errorId: 'editPhoneError',
                label: 'เบอร์โทรศัพท์',
                validate: (value) => !value || /^[0-9]{10}$/.test(value),
                errorMessage: 'ต้องเป็นตัวเลข 10 หลัก',
                required: true
            },
            { id: 'editMemberTypeId', errorId: 'editTypeError', label: 'ประเภทสมาชิก', required: true }
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