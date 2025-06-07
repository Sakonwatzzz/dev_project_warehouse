document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');

    function showNotification(message, className) {
        const notification = document.getElementById('notification');
        const header = document.getElementById('notification-header');
        const messageDiv = document.getElementById('notification-message');
        header.className = `px-4 py-2 font-bold text-white ${className}`;
        header.textContent = 'การแจ้งเตือน';
        messageDiv.textContent = message;
        notification.classList.remove('hidden');
        setTimeout(() => {
            notification.classList.add('hidden');
        }, 5000);
    }

    function updateClock() {
        const now = new Date();
        const day = String(now.getDate()).padStart(2, '0');
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const year = now.getFullYear();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const formattedDate = `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
        document.getElementById('clock-realtime').textContent = formattedDate;
    }
    setInterval(updateClock, 1000);
    updateClock();

    function checkMidnightRefresh() {
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes();
        const seconds = now.getSeconds();
        if (hours === 0 && minutes === 0 && seconds === 0) {
            window.location.reload();
        }
    }
    setInterval(checkMidnightRefresh, 1000);

    function checkEmptySearch(input) {
        if (input.value.trim() === '') {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', 'stock_list');
            urlParams.delete('search');
            window.location.search = urlParams.toString();
        }
    }


    // Search handling
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            this.value = '';
            checkEmptySearch(this);
        }
        if (e.key === 'Enter') {
            localStorage.setItem('searchPreserve', this.value);
            this.form.submit();
        }
    });

    const preserved = localStorage.getItem('searchPreserve');
    if (preserved) {
        searchInput.value = preserved;
        searchInput.focus();
        searchInput.selectionStart = searchInput.selectionEnd = preserved.length;
        localStorage.removeItem('searchPreserve');
        searchForm.submit();
    }

    searchInput.addEventListener('input', function () {
        checkEmptySearch(this);
    });

    searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const urlParams = new URLSearchParams();
        urlParams.set('page', 'stock_list');
        urlParams.set('page_num', 1);
        if (searchInput.value) urlParams.set('search', searchInput.value);
        window.location.search = urlParams.toString();
    });

    // Handle URL parameters on page load
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showNotification(urlParams.get('success'), 'bg-green-500');
    } else if (urlParams.has('error')) {
        showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
    }

    // Helper function to escape HTML
    function htmlspecialchars(str) {
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});