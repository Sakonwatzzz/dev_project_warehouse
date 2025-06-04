<?php
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

// รับค่า lot_id จาก URL
$lot_id = $_GET['id'] ?? null;

// ตรวจสอบว่า lot_id มีค่าหรือไม่
if (!$lot_id) {
    die(json_encode(['success' => false, 'error' => 'lot_id is missing']));
}

try {
    // ดึงข้อมูล lot
    $query = "SELECT
        l.lot_id,
        l.lot_name,
        l.lot_detail,
        l.lot_date,
        l.warehouse_from_id,
        w1.warehouse_name as warehouse_from_name,
        l.warehouse_inout,
        l.warehouse_id,
        w2.warehouse_name,
        sup.supplier_id,
        sup.supplier_name,
        per.personnel_id,
        per.personnel_name,
        st.store_id,
        st.store_name,
        ls.lot_status_id,
        ls.lot_status_name
    FROM
        lot l
        LEFT JOIN warehouse w1 ON l.warehouse_from_id = w1.warehouse_id
        LEFT JOIN warehouse w2 ON l.warehouse_id = w2.warehouse_id
        INNER JOIN supplier sup ON l.supplier_id = sup.supplier_id
        INNER JOIN personnel per ON l.personnel_id = per.personnel_id
        INNER JOIN store st ON l.store_id = st.store_id
        INNER JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
    WHERE l.lot_id = :lot_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot_data) {
        die("ไม่พบข้อมูลล็อตสินค้า");
    }

    // ดึงข้อมูลคลังสินค้า
    $query = "SELECT warehouse_id, warehouse_name FROM warehouse ORDER BY warehouse_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดล็อตสินค้า</title>
    <link href="../../output.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Existing styles remain unchanged */
        body {
            background-color: #f8f9fa;
        }

        .card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .highlight-row {
            background-color: #fff3cd;
        }

        .status-pending {
            color: #f59e0b;
            font-weight: 600;
        }

        .status-confirmed {
            color: #10b981;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f3f4f6;
            text-align: left;
            padding: 0.75rem;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        td {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .total-row {
            background-color: #f9fafb;
            font-weight: 500;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            max-height: 90vh;
            overflow-y: auto;
        }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .bg-pink-100 {
            background-color: #fbcfe8 !important;
        }

        .bg-pink-500 {
            background-color: #ec4899 !important;
        }

        .bg-red-100 {
            background-color: #fee2e2 !important;
        }

        .loading {
            pointer-events: none;
            opacity: 0.7;
        }
    </style>
</head>

<body class="min-h-screen">
    <div class="mx-auto px-4 py-8 container max-w-7xl">
        <h1 class="text-4xl font-bold text-gray-800 mb-6">รายละเอียดล็อตสินค้า</h1>

        <!-- Lot Details Card -->
        <div class="card mb-6">
            <div class="text-end mb-4">
                <span class="<?php echo $lot_data['lot_status_id'] == 1 ? 'status-pending' : 'status-confirmed'; ?> font-semibold">
                    <i class="fas <?php echo $lot_data['lot_status_id'] == 1 ? 'fa-clock mr-1' : 'fa-check-circle mr-1'; ?>"></i>
                    <?php echo htmlspecialchars($lot_data['lot_status_name']); ?>
                </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">รหัส Lot สินค้า:</span>
                    <span><?php echo htmlspecialchars($lot_data['lot_id']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">ชื่อ Lot สินค้า:</span>
                    <span><?php echo htmlspecialchars($lot_data['lot_name']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">วันที่รับสินค้า:</span>
                    <span><?php echo htmlspecialchars($lot_data['lot_date']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">รับมาจากผู้ส่งมอบ:</span>
                    <span><?php echo htmlspecialchars($lot_data['supplier_name']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">ลงคลัง:</span>
                    <span><?php echo htmlspecialchars($lot_data['warehouse_name']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">นำมาจากคลัง:</span>
                    <span><?php echo !empty($lot_data['warehouse_from_name']) ? htmlspecialchars($lot_data['warehouse_from_name']) : 'ยังไม่ระบุ'; ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">รับมาจากร้าน:</span>
                    <span><?php echo htmlspecialchars($lot_data['store_name']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">พนักงาน:</span>
                    <span><?php echo htmlspecialchars($lot_data['personnel_name']); ?></span>
                </div>
                <div class="flex items-center">
                    <?php
                    if (!empty($lot_data['warehouse_inout'])) {
                        echo $lot_data['warehouse_inout'] == 1 ?
                            '<span class="text-green-600"><i class="fas fa-sign-in-alt mr-1"></i>รับเข้า</span>' :
                            '<span class="text-red-600"><i class="fas fa-sign-out-alt mr-1"></i>นำออก</span>';
                    } else {
                        echo "ยังไม่ระบุ";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Barcode Scanner Section -->
        <?php if ($lot_data['lot_status_id'] != 2): ?>
            <div class="card mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">สแกนสินค้าเข้าล็อต</h2>
                <form id="barcode-form" class="space-y-4">
                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <label class="block text-gray-600 font-medium w-32" for="barcode">รหัสบาร์โค้ด:</label>
                        <div class="flex-1">
                            <input type="text" id="barcode" name="barcode"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                placeholder="เช่น 12354685 หรือ 50*12354685 หรือ 120/50*12354685 หรือ -12354685 หรือ -50*12354685"
                                pattern="[0-9*/\-\.]*"
                                autocomplete="off"
                                autofocus>
                        </div>
                        <button type="submit" class="btn bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                            <i class="fas fa-plus mr-2"></i>เพิ่ม/ลบ
                        </button>
                    </div>
                    <div class="flex flex-row space-x-8">
                        <p class="text-sm text-green-700 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            รูปแบบการนำเข้า: รหัส หรือ จำนวน*รหัส หรือ ราคาทุน/จำนวน*รหัส
                        </p>
                        <p class="text-sm text-red-700 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            รูปแบบการลบ: -รหัส หรือ -จำนวน*รหัส
                        </p>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Product Table -->
        <div class="card">
            <div class="overflow-x-auto">
                <table class="w-full" id="products-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">รหัสบาร์โค้ด</th>
                            <th class="px-4 py-3 text-left">ชื่อสินค้า</th>
                            <th class="px-4 py-3 text-center">จำนวน</th>
                            <th class="px-4 py-3 text-right">ราคาทุน</th>
                            <th class="px-4 py-3 text-right">มูลค่ารวม</th>
                            <th class="px-4 py-3 text-center">สถานะ</th>
                            <?php if ($lot_data['lot_status_id'] != 2): ?>
                                <th class="px-4 py-3 text-center">การจัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- ตารางจะถูกอัปเดตผ่าน AJAX -->
                    </tbody>
                </table>
            </div>
            <?php if ($lot_data['lot_status_id'] != 2): ?>
                <div class="mt-6 text-center sm:text-right">
                    <button class="btn bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-all confirm-lot"
                        data-lot-id="<?php echo $lot_data['lot_id']; ?>">
                        <i class="fas fa-check-circle mr-2"></i>ยืนยันรายการ
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between mt-6">
            <a href="?page=lot_listinsert" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">
                <i class="fas fa-arrow-left mr-2"></i>กลับ
            </a>
        </div>

        <!-- Edit Modal -->
        <div id="edit-modal" class="modal">
            <div class="modal-content">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">แก้ไขข้อมูลสินค้า</h2>
                    <button type="button" id="close-modal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form id="edit-form" class="space-y-4">
                    <input type="hidden" id="edit-lot-product-id">
                    <input type="hidden" id="edit-barcode">
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit-barcode-display">บาร์โค้ด:</label>
                        <input type="text" id="edit-barcode-display" class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100" readonly>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit-product-name">ชื่อสินค้า:</label>
                        <input type="text" id="edit-product-name" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit-qty">จำนวน:</label>
                        <input type="number" id="edit-qty" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="1">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit-capital">ราคาทุน:</label>
                        <input type="number" id="edit-capital" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" step="0.01">
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" class="btn bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium" id="cancel-edit">ยกเลิก</button>
                        <button type="submit" class="btn bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg font-medium">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Shared state
                const state = {
                    activeDeleteBarcode: null,
                    barcodeInput: $('#barcode')
                };

                // Utility functions
                const utils = {
                    refreshProductTable: function() {
                        $.ajax({
                            url: 'page/lot/ajax/lot_product/ajax_get_product_table.php',
                            method: 'GET',
                            data: {
                                lot_id: <?php echo $lot_id; ?>
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $('#products-table tbody').html(response.tableHtml);
                                    utils.updateRowHighlighting();
                                    state.barcodeInput.focus();
                                    utils.rebindTableEvents();
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'เกิดข้อผิดพลาด',
                                        text: response.error || 'กรุณาลองใหม่อีกครั้ง'
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'
                                });
                            }
                        });
                    },

                    rebindTableEvents: function() {
                        $('.edit-product').off('click').on('click', function() {
                            const lot_product_id = $(this).data('id');
                            const qty = $(this).data('qty');
                            const capital = $(this).data('capital');
                            const name = $(this).data('name');
                            const barcode = $(this).data('barcode');

                            $('#edit-lot-product-id').val(lot_product_id);
                            $('#edit-barcode').val(barcode);
                            $('#edit-barcode-display').val(barcode);
                            $('#edit-product-name').val(name);
                            $('#edit-qty').val(qty);
                            $('#edit-capital').val(capital);
                            $('#edit-modal').fadeIn(200);
                            $('#edit-product-name').focus();
                        });

                        $('.delete-product').off('click').on('click', function() {
                            const lot_product_id = $(this).data('id');
                            handleDeleteProductClick(lot_product_id);
                        });
                    },

                    handleServerResponse: function(response) {
                        if (response.success) {
                            utils.refreshProductTable();
                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: 'อุ๊ย! มีปัญหานิดหน่อย',
                                text: response.error || 'กรุณาลองใหม่อีกครั้ง'
                            });
                            state.barcodeInput.focus();
                        }
                    },

                    handleServerError: function(xhr) {
                        console.error('Server response:', xhr.responseText);
                        try {
                            const htmlContent = xhr.responseText;
                            const jsonMatch = htmlContent.match(/\{.*\}/);
                            if (jsonMatch) {
                                const jsonResponse = JSON.parse(jsonMatch[0]);
                                if (jsonResponse.success) {
                                    utils.refreshProductTable();
                                    return;
                                }
                            }
                        } catch (e) {
                            console.error('JSON parsing error:', e);
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'ข้อผิดพลาด',
                            text: 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง'
                        });
                        state.barcodeInput.focus();
                    },

                    sendRequest: function(data) {
                        $.ajax({
                            url: 'page/lot/ajax/lot_product/ajax_lot_process.php',
                            method: 'POST',
                            data: data,
                            dataType: 'json',
                            success: function(response) {
                                utils.handleServerResponse(response);
                            },
                            error: function(xhr) {
                                utils.handleServerError(xhr);
                            }
                        });
                    },

                    updateRowHighlighting: function() {
                        $('#products-table tbody tr').removeClass('bg-pink-100');
                        if (state.activeDeleteBarcode) {
                            $(`#products-table tbody tr[data-product-id="${state.activeDeleteBarcode}"]`).addClass('bg-pink-100');
                        }
                    }
                };

                // Handle delete product
                function handleDeleteProductClick(lot_product_id) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ยืนยันการลบ',
                        text: 'คุณต้องการลบสินค้านี้ออกจากรายการหรือไม่?',
                        showCancelButton: true,
                        confirmButtonText: 'ยืนยัน',
                        cancelButtonText: 'ยกเลิก',
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            utils.sendRequest({
                                lot_product_id,
                                action: 'delete_product'
                            });
                        }
                    });
                }

                // Bind events
                function bindEvents() {
                    // Barcode input color change
                    $('#barcode').on('input', function() {
                        const value = $(this).val();
                        if (value.startsWith('-')) {
                            $(this).addClass('bg-pink-100').removeClass('bg-white');
                        } else {
                            $(this).removeClass('bg-pink-100').addClass('bg-white');
                        }
                    });

                    // Barcode form submission
                    $('#barcode-form').on('submit', function(e) {
                        e.preventDefault();
                        const barcode = $('#barcode').val().trim();
                        const lot_id = <?php echo $lot_id; ?>;

                        if (!barcode) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'กรุณาป้อนรหัสบาร์โค้ด',
                                showConfirmButton: true
                            });
                            return;
                        }

                        // Updated regex to support price/qty*barcode, qty*barcode, -barcode, -qty*barcode, or just barcode
                        if (!/^(?:-?[\d.]+\/[\d.]+\*\d+|-[\d.]+\*\d+|-\d+|[\d.]+\/[\d.]+\*\d+|[\d.]+\*\d+|\d+)$/.test(barcode)) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'รูปแบบไม่ถูกต้อง',
                                text: 'รูปแบบต้องเป็น รหัส หรือ จำนวน*รหัส หรือ ราคาทุน/จำนวน*รหัส หรือ -รหัส หรือ -จำนวน*รหัส'
                            });
                            return;
                        }

                        utils.sendRequest({
                            barcode,
                            lot_id,
                            action: 'scan_product'
                        });

                        $('#barcode').val('').focus();
                    });

                    // Edit form submission
                    $('#edit-form').on('submit', function(e) {
                        e.preventDefault();
                        const lot_product_id = $('#edit-lot-product-id').val();
                        const qty = parseInt($('#edit-qty').val());
                        const capital = parseFloat($('#edit-capital').val());
                        const product_name = $('#edit-product-name').val();
                        const barcode = $('#edit-barcode').val();

                        if (!product_name || product_name.trim() === '') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'กรุณาระบุชื่อสินค้า',
                                showConfirmButton: true
                            });
                            return;
                        }

                        if (isNaN(qty) || qty <= 0) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'จำนวนไม่ถูกต้อง',
                                text: 'จำนวนต้องมากกว่า 0'
                            });
                            return;
                        }

                        if (isNaN(capital) || capital < 0) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'ราคาทุนไม่ถูกต้อง',
                                text: 'ราคาทุนต้องไม่ต่ำกว่า 0'
                            });
                            return;
                        }

                        utils.sendRequest({
                            lot_product_id,
                            qty,
                            capital,
                            product_name,
                            barcode,
                            action: 'edit_product'
                        });

                        $('#edit-modal').fadeOut(200);
                    });

                    // Confirm lot
                    $('.confirm-lot').on('click', function() {
                        const lot_id = $(this).data('lot-id');

                        const hasProducts = $('#products-table tbody tr').length > 1 || !$('#products-table tbody tr').hasClass('total-row');

                        if (!hasProducts) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'ไม่มีสินค้า',
                                text: 'ต้องมีสินค้าอย่างน้อย 1 ชิ้นเพื่อยืนยันรายการ',
                                confirmButtonText: 'ตกลง'
                            });
                            return;
                        }

                        Swal.fire({
                            icon: 'question',
                            title: 'ยืนยันรายการ',
                            text: 'คุณต้องการยืนยันรายการนี้หรือไม่?',
                            showCancelButton: true,
                            confirmButtonText: 'ยืนยัน',
                            cancelButtonText: 'ยกเลิก',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $.ajax({
                                    url: 'page/lot/ajax/lot_product/ajax_lot_process.php',
                                    type: 'POST',
                                    data: {
                                        lot_id,
                                        action: 'confirm_lot'
                                    },
                                    dataType: 'json',
                                    beforeSend: function() {
                                        $('.confirm-lot').addClass('loading').prop('disabled', true);
                                    },
                                    success: function(response) {
                                        $('.confirm-lot').removeClass('loading').prop('disabled', false);
                                        if (response.success) {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'ยืนยันสำเร็จ',
                                                text: response.message || 'การยืนยันล็อตสำเร็จ',
                                                showConfirmButton: false,
                                                timer: 1500
                                            }).then(() => {
                                                window.location.href = 'index.php?page=lot_listinsert';
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: '⚠️ ไม่สามารถยืนยันได้',
                                                text: response.error || 'กรุณาลองใหม่อีกครั้ง'
                                            });
                                        }
                                    },
                                    error: function(xhr) {
                                        $('.confirm-lot').removeClass('loading').prop('disabled', false);
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'ข้อผิดพลาด',
                                            text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + (xhr.responseJSON?.error || 'กรุณาลองใหม่')
                                        });
                                    }
                                });
                            }
                        });
                    });

                    // Close modal
                    $('#cancel-edit, #close-modal').on('click', function() {
                        $('#edit-modal').fadeOut(200);
                    });

                    $('#edit-modal').on('click', function(e) {
                        if (e.target.id === 'edit-modal') {
                            $('#edit-modal').fadeOut(200);
                        }
                    });

                    $(document).keydown(function(e) {
                        if (e.key === "Escape" && $('#edit-modal').is(':visible')) {
                            $('#edit-modal').fadeOut(200);
                        }
                    });

                    $('#barcode').focus();
                }

                // Initialize events
                bindEvents();

                // Initial table load
                utils.refreshProductTable();
            });
        </script>
</body>

</html>