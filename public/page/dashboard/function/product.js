//page/dashboard/function/product.js
function loadCounts() {
    fetch('/page/dashboard/ajax/product.php')
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            document.getElementById('totalCountDisplay').textContent = data.total_count;
            document.getElementById('totalCountLotDisplay').textContent = data.total_count_lot;
            document.getElementById('totalCountMemberDisplay').textContent = data.total_count_member;
            document.getElementById('totalCountSaleDisplay').textContent = data.total_count_sale;
        })
}

document.addEventListener('DOMContentLoaded', loadCounts);