// page/stock/js/stock_switch.js
$(document).ready(function () {
    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true,
        customClass: { popup: "small-toast" }
    });

    let currentStockId = $('#current_stock_id').val() || null;

    window.switchStock = function(stockId) {
        if (stockId === currentStockId) return;
        $.ajax({
            url: 'page/stock/api/switch_stock.php',
            method: 'POST',
            data: {
                action: 'switch_stock',
                stock_id: stockId
            },
            dataType: 'json',
            beforeSend: function () {
                console.log('Sending AJAX request to page/stock/api/switch_stock.php for stock_id: ' + stockId);
            },
            success: function (response) {
                console.log('Switch stock response:', response);
                if (response.status === 'success') {
                    currentStockId = stockId;
                    $('#current_stock_id').val(currentStockId);
                    $('#stock_id_select').val(currentStockId);
                    // Redirect แทนการอัปเดต UI เพื่อให้โหลดหน้าใหม่
                    window.location.href = `?page=stock_insert&stock_id=${stockId}`;
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: response.message || 'ไม่สามารถสลับใบตรวจนับสต๊อกได้'
                    });
                }
            },
            error: function (xhr, status, error) {
                console.error('Switch stock error:', xhr.responseText);
                Toast.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาดในการสลับใบตรวจนับสต๊อก: ' + error
                });
            }
        });
    };

    // Bind event สำหรับ #stock_id_select
    $('#stock_id_select').on('change', function () {
        const selectedStockId = $(this).val();
        if (selectedStockId) {
            switchStock(selectedStockId);
        } else {
            currentStockId = null;
            $('#current_stock_id').val('');
            $('#stock_check_table').empty().append(
                '<tr><td colspan="9" class="text-center py-4 text-gray-500">ไม่พบรายการตรวจสอบสต๊อกสินค้า</td></tr>'
            );
            $('#btn-confirm-check').prop('disabled', true);
        }
    });
});