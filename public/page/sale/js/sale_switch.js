$(document).ready(function () {
    // Current sale ID (can be set globally or passed from sale_add.php)
    let currentSaleId = '<?php echo htmlspecialchars($sale_id); ?>';

    // Function to update tab styles
    function updateTabStyles() {
        $('[id^="tab_"]').each(function () {
            const tabSaleId = $(this).attr('id').replace('tab_', '');
            $(this).toggleClass('tab-active', tabSaleId === currentSaleId)
                   .toggleClass('tab-inactive', tabSaleId !== currentSaleId);
        });
    }

    // Function to switch to a different bill
    window.switchBill = function(saleId) {
        if (saleId === currentSaleId) return;
        $.ajax({
            url: 'page/sale/ajax/ajax_switch_sale.php',
            method: 'POST',
            data: {
                action: 'switch_sale',
                sale_id: saleId
            },
            dataType: 'json',
            beforeSend: function () {
                console.log('Sending AJAX request to page/sale/ajax/ajax_switch_sale.php for sale_id: ' + saleId);
            },
            success: function (response) {
                console.log('Switch sale response:', response);
                if (response.status === 'success') {
                    currentSaleId = saleId;
                    updateTabStyles();
                    window.location.href = `?page=sale_add&sale_id=${saleId}`;
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: response.message,
                        confirmButtonText: 'ตกลง'
                    });
                }
            },
            error: function (xhr, status, error) {
                console.log('Switch sale error:', xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'เกิดข้อผิดพลาดในการสลับบิล: ' + error,
                    confirmButtonText: 'ตกลง'
                });
            }
        });
    };
});