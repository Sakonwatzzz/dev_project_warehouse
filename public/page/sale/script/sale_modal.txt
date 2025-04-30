$(document).on('click', '.edit-product', function (e) {
    e.preventDefault();
    const barcode = $(this).data('barcode');
    const name = $(this).data('name');
    const qty = $(this).data('qty');
    const price = $(this).data('price');
    const capital = $(this).data('capital');
    $('#edit-barcode').val(barcode);
    $('#edit-barcode-display').val(barcode);
    $('#edit-product-name').val(name);
    $('#edit-qty').val(qty);
    $('#edit-price').val(parseFloat(price).toFixed(2));
    $('#edit-capital').val(parseFloat(capital).toFixed(2));
    $('.main-body').attr('aria-hidden', 'true');
    $('#edit-product-modal').removeClass('hidden');
});

$('#edit-product-form').on('submit', function (e) {
    e.preventDefault();
    if (window.isProcessing) return;
    const qty = parseInt($('#edit-qty').val()) || 0;
    if (qty <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด',
            text: 'จำนวนต้องมากกว่า 0',
            didOpen: () => {
                $('.main-body').removeAttr('aria-hidden');
            }
        });
        return;
    }
    $.ajax({
        url: 'page/sale/ajax/ajax_update_product.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        beforeSend: function () {
            window.isProcessing = true;
        },
        success: function (response) {
            if (response.status === 'success') {
                Toast.fire({
                    icon: 'success',
                    title: response.message
                });
                $('#edit-product-modal').addClass('hidden');
                updateProductTable('<?php echo htmlspecialchars($sale_id); ?>');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: response.message,
                    didOpen: () => {
                        $('.main-body').removeAttr('aria-hidden');
                    }
                });
            }
        },
        complete: function () {
            window.isProcessing = false;
            $('.main-body').removeAttr('aria-hidden');
            if (!$('#edit-product-modal').is(':visible')) {
                $('#barcode_input').focus();
            }
        },
        error: function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: xhr.responseText || 'ไม่สามารถแก้ไขสินค้าได้',
                didOpen: () => {
                    $('.main-body').removeAttr('aria-hidden');
                }
            });
            window.isProcessing = false;
        }
    });
});

$('#close-modal, #cancel-edit').on('click', function () {
    $('#edit-product-modal').addClass('hidden');
    $('.main-body').removeAttr('aria-hidden');
    $('#barcode_input').focus();
});

function bindEditProductEvents() {
    $('.edit-product').off('click').on('click', function (e) {
        e.preventDefault();
        const barcode = $(this).data('barcode');
        const name = $(this).data('name');
        const qty = $(this).data('qty');
        const price = $(this).data('price');
        const capital = $(this).data('capital');
        $('#edit-barcode').val(barcode);
        $('#edit-barcode-display').val(barcode);
        $('#edit-product-name').val(name);
        $('#edit-qty').val(qty);
        $('#edit-price').val(parseFloat(price).toFixed(2));
        $('#edit-capital').val(parseFloat(capital).toFixed(2));
        $('.main-body').attr('aria-hidden', 'true');
        $('#edit-product-modal').removeClass('hidden');
    });
}