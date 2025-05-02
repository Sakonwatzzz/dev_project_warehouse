<?php
require_once __DIR__ . '../../../../config/database_server.php';

// รับค่า lot_id และ warehouse_id จาก URL
$lot_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$warehouse_id = isset($_GET['warehouse_id']) ? $_GET['warehouse_id'] : null;

// ตรวจสอบว่า lot_id มีค่าหรือไม่
if (!$lot_id) {
    die(json_encode(['success' => false, 'error' => 'lot_id is missing. Debug: ' . json_encode($_GET)]));
}

// Initialize variables
$error = null;

// Function to fetch data from database
function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }
}

try {
    // Fetch supplier data
    $query = "SELECT supplier_id, supplier_name FROM supplier";
    $supplier_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูล lot และตรวจสอบสถานะ
    $query_lot = "SELECT
                lot.lot_id, 
                lot.lot_name, 
                lot.lot_date, 
                lot.warehouse_inout, 
                lot.lot_status_id,
                s.supplier_id,
                s.supplier_name,
                lot_status.lot_status_name, 
                w1.warehouse_id AS warehouse_from_id,
                w1.warehouse_name AS warehouse_from_name, 
                w2.warehouse_name AS warehouse_in_name
            FROM lot lot
            LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
            LEFT JOIN supplier s ON lot.supplier_id = s.supplier_id
            LEFT JOIN warehouse w1 ON lot.warehouse_from_id = w1.warehouse_id
            LEFT JOIN warehouse w2 ON lot.warehouse_id = w2.warehouse_id
            WHERE lot.lot_id = :lot_id AND lot.warehouse_inout = 2";
    $stmt = $pdo->prepare($query_lot);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-center my-4">ไม่พบข้อมูล Lot ที่ระบุ</div>';
        exit;
    }

    // ตรวจสอบว่า lot ยืนยันแล้วหรือไม่
    $is_confirmed = $lot['lot_status_id'] == 2;

    // ดึงข้อมูลสินค้าใน lot พร้อมยอดคงเหลือจาก warehouse
    $query_products = "
    SELECT
        lp.lot_product_id, 
        lp.lot_product_qty, 
        lp.lot_product_remain, 
        p.product_id,
        p.product_name,
        p.product_capital,
        COALESCE((
            SELECT SUM(lp2.lot_product_remain)
            FROM lot_product lp2
            JOIN lot l2 ON lp2.lot_id = l2.lot_id
            WHERE lp2.product_id = p.product_id 
            AND l2.warehouse_id = :warehouse_from_id 
            AND l2.lot_status_id = 2
        ), 0) as source_qty_remaining
    FROM lot_product lp
    LEFT JOIN product p ON lp.product_id = p.product_id
    WHERE lp.lot_id = :lot_id";
    $stmt = $pdo->prepare($query_products);
    $stmt->execute(['lot_id' => $lot_id, 'warehouse_from_id' => $lot['warehouse_from_id']]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch warehouse data
    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse WHERE warehouse_id = ?";
    $warehouse_data = fetchData($pdo, $query, [$warehouse_id])->fetch(PDO::FETCH_ASSOC);

    if (!$warehouse_data) {
        $error = "ไม่พบข้อมูลคลังสินค้าที่ระบุ";
    }
} catch (PDOException $e) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-center my-4">เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดการย้ายคลังสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
            margin: 15% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="min-h-screen">
    <div class="mx-auto px-4 py-8 container max-w-6xl">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-6 text-center">รายละเอียดการย้ายคลังสินค้า</h1>

        <!-- Lot Details Card -->
        <div class="card mb-6">
            <div class="text-end mb-4">
                <span class="<?php echo $lot['lot_status_id'] == 1 ? 'status-pending' : 'status-confirmed'; ?> font-semibold">
                    <i class="fas <?php echo $lot['lot_status_id'] == 1 ? 'fa-clock mr-1' : 'fa-check-circle mr-1'; ?>"></i>
                    <?php echo htmlspecialchars($lot['lot_status_name']); ?>
                </span>
            </div>
            <div id="lot-info" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">รหัส Lot สินค้า:</span>
                    <span class="font-semibold"><?php echo htmlspecialchars($lot['lot_id']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">ชื่อ Lot สินค้า:</span>
                    <span class=""><?php echo htmlspecialchars($lot['lot_name']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">วันที่ย้ายคลังสินค้า:</span>
                    <span class=""><?php echo htmlspecialchars($lot['lot_date']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">ผู้ส่งมอบ:</span>
                    <span class=""><?php echo htmlspecialchars($lot['supplier_name']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">นำมาจากคลัง:</span>
                    <span class=""><?php echo htmlspecialchars($lot['warehouse_from_name']); ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 font-medium w-40">ลงคลัง:</span>
                    <span class=""><?php echo htmlspecialchars($lot['warehouse_in_name']); ?></span>
                </div>
                <div>
                    <?php
                    if (!empty($lot['warehouse_inout'])) {
                        if ($lot['warehouse_inout'] == 1) {
                            echo '<span class="text-green-600"><i class="fas fa-sign-in-alt mr-1"></i>รับเข้า</span>';
                        } elseif ($lot['warehouse_inout'] == 2) {
                            echo '<span class="text-red-600"><i class="fas fa-sign-out-alt mr-1"></i>นำออก</span>';
                        } else {
                            echo htmlspecialchars($lot['warehouse_inout']);
                        }
                    } else {
                        echo "ยังไม่ระบุ";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Barcode Scanner Section -->
        <?php if (!$is_confirmed): ?>
            <div class="card mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">สแกนสินค้าเข้าล็อต</h2>
                <form id="barcode-form" class="space-y-4">
                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <label class="block text-gray-600 font-medium w-32" for="barcode">รหัสบาร์โค้ด:</label>
                        <div class="flex-1">
                            <input type="text" id="barcode" name="barcode"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="เช่น 12345 หรือ 2*12345"
                                pattern="[0-9*\-]*"
                                autocomplete="off"
                                autofocus>
                            <p class="text-sm text-green-700 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                รูปแบบการย้าย: รหัส หรือ จำนวน*รหัส
                            </p>
                            <p class="text-sm mt-1 text-red-700">
                                <i class="fas fa-info-circle mr-1"></i>
                                รูปแบบการลบ: -รหัส หรือ -จำนวน*รหัส
                            </p>
                        </div>
                        <button type="submit" class="btn bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                            <i class="fas fa-plus mr-2"></i>เพิ่ม/ลบ
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Product Table -->
        <div class="card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">รายการสินค้าที่ย้ายคลัง</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full" id="products-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">รหัสบาร์โค้ด</th>
                            <th class="px-4 py-3 text-left">ชื่อสินค้า</th>
                            <th class="px-4 py-3 text-center">คงเหลือต้นทาง</th>
                            <th class="px-4 py-3 text-center">จำนวนย้าย</th>
                            <th class="px-4 py-3 text-right">ราคาทุน</th>
                            <th class="px-4 py-3 text-right">มูลค่ารวม</th>
                            <th class="px-4 py-3 text-center">สถานะ</th>
                            <?php if (!$is_confirmed): ?>
                                <th class="px-4 py-3 text-center">การจัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($products) == 0) {
                            echo '<tr><td colspan="' . ($is_confirmed ? 7 : 8) . '" class="text-center py-4 text-gray-500">ไม่พบรายการสินค้า</td></tr>';
                        } else {
                            $total_qty = 0;
                            $total_value = 0;
                            foreach ($products as $product) {
                                $total = $product['lot_product_qty'] * $product['product_capital'];
                                $total_qty += $product['lot_product_qty'];
                                $total_value += $total;

                        ?>
                                <tr class=" hover:bg-gray-50" data-product-id="<?php echo htmlspecialchars($product['product_id']); ?>">
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($product['product_id']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td class="px-4 py-3 text-center"><?php echo number_format($product['source_qty_remaining'], 0); ?></td>
                                    <td class="px-4 py-3 text-center"><?php echo number_format($product['lot_product_qty'], 0); ?></td>
                                    <td class="px-4 py-3 text-right"><?php echo number_format($product['product_capital'], 2); ?></td>
                                    <td class="px-4 py-3 text-right"><?php echo number_format($total, 2); ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full <?php echo $is_confirmed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <i class="fas <?php echo $is_confirmed ? 'fa-check mr-1' : 'fa-clock mr-1'; ?>"></i>
                                            <?php echo $is_confirmed ? 'ยืนยันแล้ว' : 'รอดำเนินการ'; ?>
                                        </span>
                                    </td>
                                    <?php if (!$is_confirmed): ?>
                                        <td class="px-4 py-3 text-center">
                                            <button class="btn bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded mr-1 edit-product"
                                                data-id="<?php echo $product['lot_product_id']; ?>"
                                                data-qty="<?php echo $product['lot_product_qty']; ?>"
                                                data-source-qty="<?php echo $product['source_qty_remaining']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded delete-product"
                                                data-id="<?php echo $product['lot_product_id']; ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php
                            }
                            ?>
                            <tr class="total-row border-t-2 border-gray-200">
                                <td colspan="3" class="px-4 py-3 text-right font-medium">ยอดรวม:</td>
                                <td class="px-4 py-3 text-center font-bold"><?php echo number_format($total_qty, 0); ?></td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right font-bold"><?php echo number_format($total_value, 2); ?> บาท</td>
                                <td colspan="<?php echo $is_confirmed ? '1' : '2'; ?>" class="px-4 py-3"></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$is_confirmed && count($products) > 0): ?>
                <div class="mt-6 text-center sm:text-right">
                    <button class="btn bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-all confirm-transfer"
                        data-lot-id="<?php echo $lot['lot_id']; ?>">
                        <i class="fas fa-check-circle mr-2"></i>ยืนยันการย้าย
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between mt-6">
            <a href="?page=lot_insertout" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">
                <i class="fas fa-arrow-left mr-2"></i>กลับ
            </a>

        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-800">แก้ไขจำนวน</h2>
                <button type="button" id="close-modal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="edit-form" class="space-y-4">
                <input type="hidden" id="edit-lot-product-id">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2" for="edit-qty">จำนวน</label>
                    <input type="number" id="edit-qty" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="1">
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
            // ฟังก์ชันแสดง SweetAlert2 สำหรับข้อผิดพลาด
            function showError(message) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ไม่สามารถดำเนินการได้',
                    text: message,
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#d33'
                });
            }

            // ฟังก์ชันแสดง SweetAlert2 สำหรับความสำเร็จ
            function showSuccess(message) {
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: message,
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#3085d6'
                });
            }

            // สแกนบาร์โค้ด
            $('#barcode-form').on('submit', function(e) {
                e.preventDefault();
                const barcode = $('#barcode').val().trim();
                const lot_id = <?php echo $lot_id; ?>;

                if (!barcode) {
                    showError('กรุณาป้อนรหัสบาร์โค้ด');
                    return;
                }

                if (!/^[0-9*\-]+$/.test(barcode)) {
                    showError('รหัสบาร์โค้ดต้องเป็นตัวเลขและเครื่องหมาย * หรือ - เท่านั้น');
                    return;
                }

                $.ajax({
                    url: 'page/lot/ajax/move_product/ajax_move_lot.php',
                    type: 'POST',
                    data: {
                        barcode,
                        lot_id,
                        action: 'scan_product'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            showError(response.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
                        }
                    },
                    error: function() {
                        showError('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง');
                    }
                });

                // ล้างช่องบาร์โค้ดหลังจากส่ง
                $('#barcode').val('').focus();
            });

            // ลบสินค้า
            $('.delete-product').on('click', function() {
                const lot_product_id = $(this).data('id');
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
                        $.ajax({
                            url: 'page/lot/ajax/move_product/ajax_move_lot.php',
                            type: 'POST',
                            data: {
                                lot_product_id,
                                action: 'delete_product'
                            },
                            success: function(response) {
                                if (response.success) {
                                    showSuccess(response.message || 'ลบสินค้าสำเร็จ');
                                    location.reload();
                                } else {
                                    showError(response.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
                                }
                            },
                            error: function() {
                                showError('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง');
                            }
                        });
                    }
                });
            });

            // แก้ไขสินค้า
            $('.edit-product').on('click', function() {
                const lot_product_id = $(this).data('id');
                const qty = $(this).data('qty');

                $('#edit-lot-product-id').val(lot_product_id);
                $('#edit-qty').val(qty);
                $('#edit-modal').fadeIn(200);
                $('#edit-qty').focus();
            });

            // ปิด Modal
            $('#cancel-edit, #close-modal').on('click', function() {
                $('#edit-modal').fadeOut(200);
            });

            // ปิด Modal เมื่อคลิกที่พื้นที่นอก modal content
            $('#edit-modal').on('click', function(e) {
                if (e.target.id === 'edit-modal') {
                    $('#edit-modal').fadeOut(200);
                }
            });

            // บันทึกการแก้ไข
            $('#edit-form').on('submit', function(e) {
                e.preventDefault();
                const lot_product_id = $('#edit-lot-product-id').val();
                const qty = parseInt($('#edit-qty').val());

                if (isNaN(qty) || qty <= 0) {
                    showError('จำนวนต้องมากกว่า 0');
                    return;
                }

                $.ajax({
                    url: 'page/lot/ajax/move_product/ajax_move_lot.php',
                    type: 'POST',
                    data: {
                        lot_product_id,
                        qty,
                        action: 'edit_product'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            showError(response.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
                        }
                    },
                    error: function() {
                        showError('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง');
                    }
                });
            });

            // ยืนยันการย้าย
            $('.confirm-transfer').on('click', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'ยืนยันการย้าย',
                    text: 'ยืนยันการย้ายสินค้าทั้งหมด? เมื่อยืนยันแล้วจะไม่สามารถแก้ไขได้',
                    showCancelButton: true,
                    confirmButtonText: 'ยืนยัน',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const lot_id = $(this).data('lot-id');
                        $.ajax({
                            url: 'page/lot/ajax/move_product/ajax_move_lot.php',
                            type: 'POST',
                            data: {
                                lot_id,
                                action: 'confirm_transfer'
                            },
                            success: function(response) {
                                if (response.success) {
                                    window.location.href = 'http://localhost:8000/index.php?page=lot_insertout';
                                } else {
                                    showError(response.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
                                }
                            },
                            error: function() {
                                showError('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง');
                            }
                        });
                    }
                });
            });

            // Auto-focus on barcode input
            $('#barcode').focus();

            // Close modal when Escape key is pressed
            $(document).keydown(function(e) {
                if (e.key === "Escape" && $('#edit-modal').is(':visible')) {
                    $('#edit-modal').fadeOut(200);
                }
            });
        });
    </script>
</body>

</html>