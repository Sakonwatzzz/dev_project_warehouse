<script>
$(document).ready(function() {
    function loadStockCheck() {
        $.ajax({
            url: 'fetch_stock_check.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                let tbody = $('#stock_check_table');
                tbody.empty(); // ล้างข้อมูลเก่า
                
                if (data.length === 0) {
                    tbody.append('<tr><td colspan="12" class="text-center py-4 text-gray-500">ไม่พบรายการตรวจสอบสต็อกสินค้า</td></tr>');
                } else {
                    $.each(data, function(index, item) {
                        let row = `
                            <tr>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                    <input type="checkbox" class="stock-list-select rounded text-blue-500 focus:ring-blue-400"
                                        data-product-id="${item.product_id}"
                                        data-lot-id="${item.lot_id || ''}"
                                        data-lot-product-id="${item.lot_product_id || ''}"
                                        data-system-quantity="${item.stock_check_system || 0}">
                                </td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.stock_check_id || '-'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.stock_date || '-'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.warehouse_name || '-'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.product_id || '-'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.product_name || '-'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.stock_check_system || '0'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.stock_check_actual || '0'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.personnel_name || '-'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.check_status_name || '-'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.stock_check_remark || '-'}</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm text-blue-600">
                                    <a href="edit_stock_check.php?id=${item.stock_check_id}">แก้ไข</a>
                                </td>
                            </tr>`;
                        tbody.append(row);
                    });
                }
            },
            error: function() {
                alert('เกิดข้อผิดพลาดในการดึงข้อมูล');
            }
        });
    }

    // โหลดข้อมูลเมื่อหน้าเว็บโหลด
    loadStockCheck();

    // ตัวอย่าง: รีเฟรชข้อมูลทุก 30 วินาที
    setInterval(loadStockCheck, 30000);
});
</script>