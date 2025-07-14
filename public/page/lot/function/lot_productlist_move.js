
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
    document.getElementById('clearSearch').classList.add('hidden');
    localStorage.removeItem('searchPreserve');
    updateTable();
};

function updateTable() {
    console.log('updateTable called');
    const urlParams = new URLSearchParams();
    urlParams.set("page", "lot_productlist");
    urlParams.set("lot_id", document.querySelector('input[name="lot_id"]')?.value || '<?php echo urlencode($lot_id); ?>');
    urlParams.set("page_num", "1");

    const searchInput = document.getElementById("searchInput").value.trim();
    if (searchInput) {
        urlParams.set("search", searchInput);
    }

    fetch(`/page/lot/ajax/move_product/lot_productlist_move_table.php?${urlParams.toString()}`)
        .then((response) => {
            if (!response.ok) throw new Error("Network error");
            return response.text();
        })
        .then((data) => {
            if (data.startsWith('{')) {
                const json = JSON.parse(data);
                showNotification(json.error, "bg-red-500");
                document.getElementById("lotProductTable").innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } else {
                document.getElementById("lotProductTable").innerHTML = data;
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

    const searchInput = document.getElementById("searchInput").value.trim();
    if (searchInput) {
        urlParams.set("search", searchInput);
    } else {
        urlParams.delete("search");
    }

    fetch(`/page/lot/ajax/lot_productlist_move_table.php?${urlParams.toString()}`)
        .then((response) => {
            if (!response.ok) throw new Error("Network error");
            return response.text();
        })
        .then((data) => {
            if (data.startsWith('{')) {
                const json = JSON.parse(data);
                showNotification(json.error, "bg-red-500");
                document.getElementById("lotProductTable").innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } else {
                document.getElementById("lotProductTable").innerHTML = data;
                history.pushState({}, "", `?${urlParams.toString()}`);
            }
        })
        .catch((error) => {
            console.error("Error:", error);
            showNotification("เกิดข้อผิดพลาดขณะโหลดตาราง", "bg-red-500");
        });
}

const exportPDF = () => {
    const exportBtn = document.getElementById('export-buttons');
    const lot_id = exportBtn.dataset.lotId;
    const token = exportBtn.dataset.token;

    let url = `/page/lot/ajax/report/lot_product_pdf.php?lot_id=${encodeURIComponent(lot_id)}&csrf_token=${encodeURIComponent(token)}`;
    window.open(url, '_blank');
};

const exportExcel = () => {
    const exportBtn = document.getElementById('export-excel-button');
    const lot_id = exportBtn.dataset.lotId;
    const token = exportBtn.dataset.token;

    let url = `/page/lot/ajax/report/lot_product_excel.php?lot_id=${encodeURIComponent(lot_id)}&csrf_token=${encodeURIComponent(token)}`;
    window.open(url, '_blank');
};

const loadTable = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const lot_id = urlParams.get('lot_id') || '';
    const search = urlParams.get('search') || '';
    const page_num = urlParams.get('page_num') || '1';

    document.getElementById('searchInput').value = search;

    const fetchUrl = new URLSearchParams();
    fetchUrl.set('lot_id', lot_id);
    fetchUrl.set('search', search);
    fetchUrl.set('page_num', page_num);

    fetch(`/page/lot/ajax/move_product/lot_productlist_move_table.php?${fetchUrl.toString()}`)
        .then(response => {
            if (!response.ok) throw new Error("Network error");
            return response.text();
        })
        .then(data => {
            if (data.startsWith('{')) {
                const json = JSON.parse(data);
                showNotification(json.error, "bg-red-500");
                document.getElementById('lotProductTable').innerHTML =
                    '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
            } else {
                document.getElementById('lotProductTable').innerHTML = data;
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
    const searchInput = document.getElementById('searchInput');
    const clearSearch = document.getElementById('clearSearch');

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
        fields.forEach(({ id, errorId, label, required, validate, errorMessage, min }) => {
            const input = document.getElementById(id);
            const error = document.getElementById(errorId);
            if (required && !input.value.trim()) {
                error.textContent = `กรุณากรอก${label}`;
                error.classList.remove('hidden');
                hasError = true;
            } else if (validate && !validate(input.value)) {
                error.textContent = errorMessage;
                error.classList.remove('hidden');
                hasError = true;
            } else if (min && parseFloat(input.value) < min) {
                error.textContent = `${label}ต้องไม่น้อยกว่า ${min}`;
                error.classList.remove('hidden');
                hasError = true;
            } else {
                error.classList.add('hidden');
            }
        });
        return !hasError;
    };


});