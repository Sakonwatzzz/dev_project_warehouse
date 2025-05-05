
//ยังไม่ได้ถูกเรียกใช้งานจากหน้าหลัก แยกออกมารอ

$(document).on('click', '.delete-product', function (e) {
    e.preventDefault();
    console.log('Delete button clicked:', $(this).data('barcode')); // Log การคลิก
    if (isProcessing) {
        console.warn('Processing in progress, ignoring click');
        return;
    }
    const barcode = $(this).data('barcode');
    const qty = parseInt($(this).data('qty')) || 1;
    console.log("qty" + qty);
    Swal.fire({
        title: 'ยืนยันการลบสินค้า?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            console.log('Sending AJAX request to delete:', {
                barcode,
                qty
            }); // Log ก่อนส่ง
            $.ajax({
                url: 'page/sale/ajax/ajax_delete_product.php', // ชี้ตรงไปที่ไฟล์ PHP ที่ handle
                method: 'POST',
                data: {
                    action: 'delete',
                    sale_id: '<?php echo htmlspecialchars($sale_id); ?>',
                    barcode: barcode,
                    qty: qty
                },
                dataType: 'json',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    isProcessing = true;
                },
                success: function (response) {
                    if (response.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: response.message
                        });
                        updateProductTable();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ข้อผิดพลาด',
                            text: response.message,

                        });
                    }
                },
                complete: function () {
                    isProcessing = false;
                    $('#barcode_input').focus();
                },
                error: function (xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        html: `<pre>${xhr.responseText}</pre>`
                    });
                    isProcessing = false;
                }
            });

        }
    });
});