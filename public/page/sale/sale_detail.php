<?php
require_once __DIR__ . '../../../../config/database_server.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้ารายละเอียดการขาย</title>
</head>
<body>
    
<!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function () {
        // Autocomplete สำหรับค้นหาสินค้า
        $('#barcode_input').on('input', function () {
            const query = $(this).val();
            if (query.length < 2) {
                $('#barcode_suggestions').addClass('hidden').empty();
                $('#product_info').addClass('hidden').empty();
                return;
            }
            $.ajax({
                url: 'page/sale/ajax_search_product.php',
                method: 'GET',
                data: { query: query },
                success: function (response) {
                    const products = JSON.parse(response);
                    const suggestions = $('#barcode_suggestions').removeClass('hidden').empty();
                    if (products.length === 0) {
                        suggestions.append('<div class="p-2 text-gray-500">ไม่พบสินค้า</div>');
                        return;
                    }
                    products.forEach(product => {
                        suggestions.append(`
                            <div class="p-2 hover:bg-gray-100 cursor-pointer" data-barcode="${product.product_id}">
                                ${product.product_id} - ${product.product_name} (สต็อก: ${product.total_stock})
                            </div>
                        `);
                    });
                    // แสดงข้อมูลสินค้าทันทีเมื่อเลือก
                    suggestions.find('div').on('click', function () {
                        const barcode = $(this).data('barcode');
                        $('#barcode_input').val(barcode);
                        suggestions.addClass('hidden').empty();
                        fetchProductInfo(barcode);
                    });
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: 'ไม่สามารถค้นหาสินค้าได้'
                    });
                }
            });
        });

        // แสดงข้อมูลสินค้าเมื่อกรอกบาร์โค้ด
        $('#barcode_input').on('blur', function () {
            const barcode = $(this).val();
            if (barcode) {
                fetchProductInfo(barcode);
            }
        });

        function fetchProductInfo(barcode) {
            $.ajax({
                url: 'page/sale/ajax_get_product.php',
                method: 'GET',
                data: { barcode: barcode, warehouse_id: $('select[name="warehouse_id"]').val() },
                success: function (response) {
                    const product = JSON.parse(response);
                    const info = $('#product_info').removeClass('hidden');
                    if (!product) {
                        info.html('<span class="text-red-500">ไม่พบสินค้าหรือสต็อกไม่เพียงพอ</span>');
                        return;
                    }
                    info.html(`
                        <p><strong>ชื่อสินค้า:</strong> ${product.product_name}</p>
                        <p><strong>ราคาขาย:</strong> ${product.product_price4} บาท</p>
                        <p><strong>สต็อกคงเหลือ:</strong> ${product.total_stock} ชิ้น</p>
                    `);
                },
                error: function () {
                    $('#product_info').addClass('hidden').empty();
                }
            });
        }

        // Autocomplete สำหรับค้นหาสมาชิก
        $('#member_search').on('input', function () {
            const query = $(this).val();
            if (query.length < 2) {
                $('#member_suggestions').addClass('hidden').empty();
                return;
            }
            $.ajax({
                url: 'page/sale/ajax_search_member.php',
                method: 'GET',
                data: { query: query },
                success: function (response) {
                    const members = JSON.parse(response);
                    const suggestions = $('#member_suggestions').removeClass('hidden').empty();
                    if (members.length === 0) {
                        suggestions.append('<div class="p-2 text-gray-500">ไม่พบสมาชิก</div>');
                        return;
                    }
                    members.forEach(member => {
                        suggestions.append(`
                            <div class="p-2 hover:bg-gray-100 cursor-pointer" data-id="${member.member_id}">
                                ${member.member_name}
                            </div>
                        `);
                    });
                    suggestions.find('div').on('click', function () {
                        const memberId = $(this).data('id');
                        const memberName = $(this).text();
                        $('#member_search').val(memberName);
                        $('#member_id').val(memberId);
                        suggestions.addClass('hidden').empty();
                    });
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: 'ไม่สามารถค้นหาสมาชิกได้'
                    });
                }
            });
        });

        // คำนวณเงินทอน
        $('#calculate_change').on('click', function () {
            const paid = parseFloat($('#paid_amount').val()) || 0;
            const total = parseFloat(<?php echo $total_amount; ?>) || 0;
            const change = paid - total;
            $('#change_amount').val(change >= 0 ? change.toFixed(2) : 'เงินไม่พอ');
            if (change >= 0) {
                $('#print_receipt').prop('disabled', false);
            } else {
                $('#print_receipt').prop('disabled', true);
            }
        });

        // อัปเดตเงินทอนเมื่อกรอกเงินที่ชำระ
        $('#paid_amount').on('input', function () {
            $('#calculate_change').click();
        });

        // สลับบิล
        $('#switch_sale').on('change', function () {
            window.location.href = '?page=sale_insert&sale_id=' + $(this).val();
        });

        // เปิดบิลใหม่
        $('#new_sale').on('click', function () {
            $.post('', { action: 'new_sale' }, function () {
                window.location.href = '?page=sale_insert';
            });
        });

        // ยกเลิกบิล
        $('#cancel_sale').on('click', function () {
            Swal.fire({
                title: 'ยืนยันการยกเลิกบิล?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ยกเลิกบิล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('', {
                        action: 'cancel_sale',
                        sale_id: '<?php echo $sale_id; ?>'
                    }, function () {
                        window.location.href = '?page=sale_insert';
                    });
                }
            });
        });

        // เปิด/ปิด modal แก้ไขสินค้า
        $('.edit-product').on('click', function () {
            $('#edit-barcode').val($(this).data('barcode'));
            $('#edit-barcode-display').val($(this).data('barcode'));
            $('#edit-product-name').val($(this).data('name'));
            $('#edit-qty').val($(this).data('qty'));
            $('#edit-price').val($(this).data('price'));
            $('#edit-capital').val($(this).data('capital'));
            $('#edit-warehouse').val($(this).data('warehouse'));
            $('#edit-booking').prop('checked', $(this).data('booking') == 1);
            $('#edit-product-modal').removeClass('hidden');
        });

        $('#close-modal, #cancel-edit').on('click', function () {
            $('#edit-product-modal').addClass('hidden');
        });

        // ยืนยันการลบสินค้า
        function confirmDelete() {
            return confirm('ยืนยันการลบสินค้านี้?');
        }

        // อัปเดตตารางสินค้าแบบเรียลไทม์
        $('#form_add_product').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'page/sale/sale_status.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (response) {
                    // รีโหลดหน้าเพื่ออัปเดตตาราง
                    window.location.reload();
                },
                error: function (xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: xhr.responseText || 'ไม่สามารถเพิ่มสินค้าได้'
                    });
                }
            });
        });
    });
</script> -->
</body>
</html>