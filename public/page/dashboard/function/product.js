function loadCounts() {
    fetch('/page/dashboard/ajax/product.php')
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            // อัปเดตข้อมูลตัวเลขใน stat cards
            document.getElementById('totalCountDisplay').textContent = data.total_count;
            document.getElementById('totalCountLotDisplay').textContent = data.total_count_lot;
            document.getElementById('totalCountMemberDisplay').textContent = data.total_count_member;
            document.getElementById('totalCountSaleDisplay').textContent = data.total_count_sale;
            document.getElementById('totalCountSaleTodayDisplay').textContent = data.total_sale_today;

            // อัปเดตตารางสินค้าใกล้หมด
            const lowStockTable = document.getElementById('lowStockProductsTable');
            lowStockTable.innerHTML = ''; // ล้างข้อมูลเก่า

            if (data.low_stock_products && data.low_stock_products.length > 0) {
                data.low_stock_products.forEach(product => {
                    const status = product.total_qty <= 3 ? 'วิกฤต' : 'ต่ำ';
                    const statusClass = product.total_qty <= 3 ? 'cancelled' : 'pending';
                    const row = `
                        <tr>
                            <td>${product.product_id}</td>
                            <td>${product.product_name}</td>
                            <td>${product.total_qty}</td>
                            <td>${product.warehouse_name}</td>
                            <td><span class="status ${statusClass}">${status}</span></td>
                        </tr>
                    `;
                    lowStockTable.innerHTML += row;
                });
            } else {
                lowStockTable.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4">ไม่มีสินค้าใกล้หมด</td>
                    </tr>
                `;
            }


            
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            document.getElementById('lowStockProductsTable').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td>
                </tr>
            `;
        });
}

document.addEventListener('DOMContentLoaded', loadCounts);