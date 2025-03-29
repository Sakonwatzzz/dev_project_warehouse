<?php
require_once __DIR__ . '../../../../config/database_server.php';

// รับค่า lot_id จาก URL
$lot_id = $_GET['id'] ?? null;

// ตรวจสอบว่า lot_id มีค่าหรือไม่
if (!$lot_id) {
    die(json_encode(['success' => false, 'error' => 'lot_id is missing. Debug: ' . json_encode($_GET)]));
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        if (!isset($_POST['barcode']) || !isset($_POST['product_name']) || !isset($_POST['qty']) || !isset($_POST['capital']) || !isset($_POST['lot_product_id'])) {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }

        // Start transaction for consistent data
        $pdo->beginTransaction();

        // Update product name in product table
        $productStmt = $pdo->prepare("UPDATE product SET 
            product_name = ?
            WHERE product_id = ?");
        $productStmt->execute([$_POST['product_name'], $_POST['barcode']]);

        // Update quantity and price in lot_product table - FIX: correct parameter order
        $stmt = $pdo->prepare("UPDATE lot_product SET 
            lot_product_qty = ?, 
            lot_product_remain = ?,
            lot_product_capital = ?
            WHERE lot_product_id = ? AND lot_id = ?");
        $stmt->execute([
            $_POST['qty'],
            $_POST['qty'], // Set remain to same as qty
            $_POST['capital'],
            $_POST['lot_product_id'],
            $lot_id
        ]);

        // Commit transaction
        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lot_id'])) {
    try {
        $pdo->beginTransaction();

        // First check if there are any products with missing information
        $check_query = "SELECT lp.product_id, COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name, 
                        lp.lot_product_capital 
                        FROM lot_product lp
                        LEFT JOIN product p ON p.product_id = lp.product_id
                        WHERE lp.lot_id = ? 
                        AND (p.product_name IS NULL OR p.product_name = 'ยังไม่ระบุ' OR lp.lot_product_capital = 0)";

        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$_POST['lot_id']]);
        $invalid_products = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($invalid_products) > 0) {
            // There are products with missing information
            throw new Exception("พบสินค้าที่ข้อมูลไม่ครบถ้วน กรุณาตรวจสอบและแก้ไขก่อนยืนยันรายการ");
        }

        // Update product names if needed
        if (isset($_POST['product_name']) && isset($_POST['barcode'])) {
            $productStmt = $pdo->prepare("UPDATE product SET product_name = ? WHERE product_id = ?");
            $productStmt->execute([$_POST['product_name'], $_POST['barcode']]);
        }

        // Update lot status to confirmed (status_id = 2)
        $updateLot = $pdo->prepare("UPDATE lot SET lot_status_id = 2 WHERE lot_id = ?");
        $updateLot->execute([$_POST['lot_id']]);

        $pdo->commit();

        // Redirect with success message
        header('Location: index.php?page=lot_warehouseinout&success=1');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // For AJAX requests, return JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }

        // For regular form submissions, redirect with error
        header('Location: index.php?page=lot_view&id=' . $_POST['lot_id'] . '&error=' . urlencode($e->getMessage()));
        exit();
    }
}

// Handle product scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {
    header('Content-Type: application/json');
    try {
        $barcode = $_POST['barcode'];
        $isDeleteMode = isset($_POST['deleteMode']) && $_POST['deleteMode'] === 'true';
        $quantity = isset($_POST['qty']) && is_numeric($_POST['qty']) ? (int) $_POST['qty'] : 1;
        $capital = isset($_POST['capital']) && is_numeric($_POST['capital']) ? (float) $_POST['capital'] : 0;

        // Check if product exists in lot
        $stmt = $pdo->prepare("SELECT lp.*, p.product_name FROM lot_product lp 
                              LEFT JOIN product p ON p.product_id = lp.product_id 
                              WHERE lp.lot_id = ? AND lp.product_id = ?");
        $stmt->execute([$lot_id, $barcode]);
        $existing_product = $stmt->fetch();

        if ($existing_product) {
            if ($isDeleteMode) {
                $newQty = $existing_product['lot_product_qty'] - $quantity;
                if ($newQty <= 0) {
                    $delete = $pdo->prepare("DELETE FROM lot_product WHERE lot_id = ? AND product_id = ?");
                    $delete->execute([$lot_id, $barcode]);
                } else {
                    $update = $pdo->prepare("UPDATE lot_product SET 
                        lot_product_qty = ?,
                        lot_product_remain = ?
                        WHERE lot_id = ? AND product_id = ?");
                    $update->execute([$newQty, $newQty, $lot_id, $barcode]);
                }
            } else {
                $newQty = $existing_product['lot_product_qty'] + $quantity;
                $newCapital = $capital > 0 ? $capital : $existing_product['lot_product_capital'];

                $update = $pdo->prepare("UPDATE lot_product SET 
                    lot_product_qty = ?,
                    lot_product_remain = ?,
                    lot_product_capital = ?
                    WHERE lot_id = ? AND product_id = ?");
                $update->execute([$newQty, $newQty, $newCapital, $lot_id, $barcode]);
            }
        } else {
            if (!$isDeleteMode) {
                $stmt_product = $pdo->prepare("SELECT * FROM product WHERE product_id = ?");
                $stmt_product->execute([$barcode]);
                $product_exists = $stmt_product->fetch();

                if (!$product_exists) {
                    $insert_product = $pdo->prepare("INSERT INTO product (
                        product_id, 
                        product_name,
                        product_price1,
                        product_price2,
                        product_price3,
                        product_price4,
                        product_capital,
                        product_type_id,
                        product_brand_id
                    ) VALUES (?, ?, 0, 0, 0, 0, ?, NULL, NULL)");

                    $product_name = isset($_POST['product_name']) ? $_POST['product_name'] : "ยังไม่ระบุ";
                    $insert_product->execute([$barcode, $product_name, $capital]);
                }

                $insert = $pdo->prepare("INSERT INTO lot_product (
                    lot_id, 
                    product_id, 
                    lot_product_qty, 
                    lot_product_remain,
                    lot_product_capital, 
                    lot_product_date,
                    lot_product_status
                ) VALUES (?, ?, ?, ?, ?, NOW(), 1)");

                $insert->execute([$lot_id, $barcode, $quantity, $quantity, $capital]);
            }
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

$query = "SELECT
    l.lot_id,
    l.lot_name,
    l.lot_detail,
    l.lot_date,
    l.warehouse_from_id,
    w1.warehouse_name as warehouse_from_name,  -- ดึงชื่อคลังต้นทาง
    l.warehouse_inout,
    l.warehouse_id,
    w2.warehouse_name,  -- ดึงชื่อคลังปลายทาง
    sup.supplier_id,
    sup.supplier_name,
    ls.lot_status_id,
    ls.lot_status_name
FROM
    lot l
    LEFT JOIN warehouse w1 ON l.warehouse_from_id = w1.warehouse_id  -- คลังต้นทาง
    LEFT JOIN warehouse w2 ON l.warehouse_id = w2.warehouse_id  -- คลังปลายทาง
    INNER JOIN supplier sup ON l.supplier_id = sup.supplier_id
    INNER JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
WHERE l.lot_id = :lot_id";


try {
    $stmt = $pdo->prepare($query);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot_data) {
        die("ไม่พบข้อมูลล็อตสินค้า");
    }

    // Query products in lot
    $product_query = "SELECT
    lp.lot_id,
    lp.lot_product_id,
    lp.product_id,
    COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
    lp.lot_product_qty,
    lp.lot_product_remain,
    lp.lot_product_capital,
    lp.lot_product_date,
    lp.lot_product_status,
    CASE 
        WHEN p.product_id IS NULL THEN 1
        ELSE 0
    END as needs_update
FROM
    lot_product lp
    LEFT JOIN product p ON p.product_id = lp.product_id
WHERE 
    lp.lot_id = :lot_id
ORDER BY 
    needs_update DESC,  
    lp.lot_product_date DESC";

    $stmt = $pdo->prepare($product_query);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}
// ดึงข้อมูลคลังสินค้าจากฐานข้อมูล
$query = "SELECT warehouse_id, warehouse_name FROM warehouse ORDER BY warehouse_name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lot_status_id = $lot_data['lot_status_id'] ?? null;
$lot_status_name = $lot_data['lot_status_name'] ?? null;

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดล็อตสินค้า</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* กำหนดพื้นหลังสีแดงเมื่อลบข้อมูล */
        .input-delete {
            background-color: red;
            color: white;
        }

        /* กำหนดพื้นหลังสีเขียวเมื่อเพิ่มข้อมูล */
        .input-add {
            background-color: green;
            color: white;
        }

        /* ปกติช่อง input ไม่มีการเปลี่ยนสี */
        .input-normal {
            background-color: white;
            color: black;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-8 mb-11">
        <h1 class="text-4xl font-bold mb-8">รายละเอียดการย้ายคลังสินค้า</h1>
        <?php if ($lot_data): ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="font-semibold mb-2">รหัส Lot สินค้า:</label>
                        <span><?php echo htmlspecialchars($lot_data['lot_id']); ?></span>
                    </div>
                    <div>
                        <label class="font-semibold mb-2">ชื่อ Lot สินค้า:</label>
                        <span><?php echo htmlspecialchars($lot_data['lot_name']); ?></span>
                    </div>
                    <div>
                        <label class="font-semibold mb-2">วันที่และเวลาย้ายคลังสินค้า:</label>
                        <span><?php echo htmlspecialchars($lot_data['lot_date']); ?></span>
                    </div>
                    <div>
                        <label class="font-semibold mb-2" hidden>รับมาจากผู้ส่งมอบ:</label>
                        <span hidden><?php echo htmlspecialchars($lot_data['supplier_name']); ?></span>
                    </div>
                    <div>
                        <label class="font-semibold mb-2">ลงคลัง:</label>
                        <span><?php echo htmlspecialchars($lot_data['warehouse_name']); ?></span>
                    </div>

                    <div>
                        <label class="font-semibold mb-2">นำมาจากคลัง:</label>
                        <span>
                            <?php
                            if (!empty($lot_data['warehouse_from_name'])) {
                                echo htmlspecialchars($lot_data['warehouse_from_name']);
                            } elseif (!empty($lot_data['warehouse_from_id'])) {
                                echo htmlspecialchars($lot_data['warehouse_from_id']) . " (ไม่พบชื่อคลัง)";
                            } else {
                                echo "ยังไม่ระบุ";
                            }
                            ?>
                        </span>
                    </div>
                    <div>
                        <?php
                        if (!empty($lot_data['warehouse_inout'])) {
                            if ($lot_data['warehouse_inout'] == 1) {
                                echo '<span class="text-green-600"><i class="fas fa-sign-in-alt mr-1"></i>รับเข้า</span>';
                            } elseif ($lot_data['warehouse_inout'] == 2) {
                                echo '<span class="text-red-600"><i class="fas fa-sign-out-alt mr-1"></i>นำออก</span>';
                            } else {
                                echo htmlspecialchars($lot_data['warehouse_inout']);
                            }
                        } else {
                            echo "ยังไม่ระบุ";
                        }
                        ?>
                    </div>

                    <div>
                        <label class="font-semibold mb-2">สถานะ:</label>
                        <span><?php echo htmlspecialchars($lot_data['lot_status_name']); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว"): ?>
            <h2 class="text-2xl font-semibold mb-4">สแกนสินค้าเข้าล็อต</h2>
            <form id="barcode-form" action="" method="POST">
                <div class="mb-4 flex items-center gap-4">
                    <label class="block mb-2" id="barcodeInput">รหัสบาร์โค้ด:</label>
                    <input type="text" id="inputField" name="product_id"
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                        pattern="[0-9*]*" placeholder="กรอกเฉพาะตัวเลขหรือ * , -" autofocus>
                </div>
                <!-- เพิ่มข้อความแนะนำวิธีการใช้งานใหม่ -->
                <div class="flex space-x-4">
                    <div class="p-4 text-sm text-gray-600 mt-1 mb-4 border border-green-300 bg-green-100">
                        <p><b>รูปแบบการป้อนข้อมูล:</b> รหัสบาร์โค้ด*จำนวน*ราคาทุน (เช่น 12345*2*150)</p>
                        <p>หากไม่ระบุจำนวนหรือราคาทุน ระบบจะใช้ค่าเริ่มต้น คือ 0 </p>
                    </div>
                    <div class="p-4 text-sm text-gray-600 mt-1 mb-4 border border-red-300 bg-red-100">
                        <p><b>รูปแบบการลบข้อมูล:</b> -รหัสบาร์โค้ด*จำนวน (เช่น -12345*3)</p>
                        <p>หากไม่ระบุจำนวน ระบบจะลบออกทีละ 1 ชิ้น</p>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold mb-4">รายการสินค้าที่ย้ายคลัง</h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse" id="products-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 border text-left">รหัสบาร์โค้ดสินค้า</th>
                            <th class="px-6 py-3 border text-left">ชื่อสินค้า</th>
                            <th class="px-6 py-3 border text-center">จำนวนสินค้าคงเหลือในคลัง</th>
                            <th class="px-6 py-3 border text-center">จำนวนสินค้าออกจากคลัง</th>
                            <th class="px-6 py-3 border text-right">ราคาทุน</th>
                            <th class="px-6 py-3 border text-right">มูลค่ารวม</th>
                            <?php if (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว"): ?>
                                <th class="px-6 py-3 border text-right">สถานะ</th>
                                <th class="px-6 py-3 border text-right">การจัดการ</th>
                            <?php endif; ?>

                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_value = 0;
                        $total_quantity = 0;
                        foreach ($lot_products as $index => $item):
                            $item_total = $item['lot_product_qty'] * $item['lot_product_capital'];
                            $total_value += $item_total;
                            $total_quantity += $item['lot_product_qty'];
                            ?>
                            <tr class="bg-white hover:bg-[#aec5e366] cursor-pointer"
                                data-product-id="<?php echo htmlspecialchars($item['product_id']); ?>">
                                <td class="pl-6 py-2 border"><?php echo htmlspecialchars($item['product_id']); ?></td>
                                <td class="pl-6 py-2 border"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-center py-2 border">
                                    <?php echo htmlspecialchars($item['lot_product_qty']); ?>
                                </td>
                                <td class="text-center py-2 border">
                                    <?php echo htmlspecialchars($item['lot_product_qty']); ?>
                                </td>
                                <td class="text-right py-2 border">
                                    <?php echo number_format($item['lot_product_capital'], 2); ?>
                                </td>
                                <td class="text-right py-2 border"><?php echo number_format($item_total, 2); ?></td>
                                <?php if (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว"): ?>

                                    <td class="text-right py-2 border">
                                        <?php echo htmlspecialchars($lot_data['lot_status_name']); ?>
                                    </td>
                                    <td class="text-right py-2 border px-4">
                                        <button type="button" class="bg-blue-500 text-white px-4 py-1 rounded edit-product"
                                            data-id="<?php echo htmlspecialchars($item['lot_product_id']); ?>"
                                            data-qty="<?php echo htmlspecialchars($item['lot_product_qty']); ?>"
                                            data-capital="<?php echo htmlspecialchars($item['lot_product_capital']); ?>"
                                            data-name="<?php echo htmlspecialchars($item['product_name']); ?>"
                                            data-barcode="<?php echo htmlspecialchars($item['product_id']); ?>">
                                            แก้ไข
                                        </button>
                                        <button type="button" class="bg-red-500 text-white px-4 py-1 rounded toggle-delete-mode"
                                            data-barcode="<?php echo htmlspecialchars($item['product_id']); ?>">
                                            ลบ
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($lot_products) > 0): ?>
                            <!-- Summary row with improved styling -->
                            <tr
                                class="bg-gradient-to-r from-gray-200 to-gray-300 text-gray-800 font-medium h-14 border-t-2 border-gray-400">
                                <td colspan="2" class="text-end pe-4 align-middle">ยอดรวมสินค้าที่ย้ายคลัง:</td>
                                <td class="text-center py-4 font-bold"><?php echo number_format($total_quantity); ?></td>
                                <td colspan="2" class="text-end pe-4 align-middle">มูลค่ารวมทั้งสิ้น:</td>
                                <td class="text-end py-4 font-bold"><?php echo number_format($total_value, 2); ?> บาท</td>
                            </tr>

                            <?php if (count($lot_products) > 0 && $lot_data['lot_status_id'] == 1): ?>
                                <!-- แสดงปุ่มยืนยันเฉพาะเมื่อสถานะเป็น "รอการอนุมัติ" -->
                                <form id="confirm-form" method="POST" action="" onsubmit="return validateForm(event)">
                                    <!-- ใส่ path ของไฟล์ PHP ที่ประมวลผลการอัปเดต -->
                                    <tr class="h-16">
                                        <td colspan="9" class="text-end pe-4 pt-4 overflow-hidden">
                                            <button type="submit"
                                                class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow transition duration-200 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                                                <i class="fas fa-check-circle me-2"></i>ยืนยันรายการ
                                            </button>
                                            <input type="hidden" name="lot_id" value="<?php echo $lot_data['lot_id']; ?>">
                                            <!-- ส่ง lot_id ไปในฟอร์ม -->
                                        </td>
                                    </tr>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="modal-content bg-white p-6 rounded-lg shadow-lg w-96 mx-auto mt-20">
            <h3 class="text-xl font-semibold mb-4">แก้ไขข้อมูลสินค้า</h3>
            <form id="edit-form">
                <input type="hidden" id="lot_product_id" name="lot_product_id">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="barcode" id="product_barcode_hidden">

                <div class="mb-4">
                    <label class="block mb-2">บาร์โค้ด:</label>
                    <input type="text" class="w-full p-2 border rounded bg-gray-100" id="product_barcode" readonly>
                </div>
                <div class="mb-4">
                    <label class="block mb-2">ชื่อสินค้า:</label>
                    <input type="text" class="w-full p-2 border rounded" id="product_name" name="product_name">
                </div>

                <div class="mb-4">
                    <label class="block mb-2">จำนวน:</label>
                    <input type="number" class="w-full p-2 border rounded" id="qty" name="qty" required min="1">
                </div>

                <div class="mb-4">
                    <label class="block mb-2">ราคาทุน:</label>
                    <input type="number" class="w-full p-2 border rounded" id="capital" name="capital" required min="0"
                        step="0.01">
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded"
                        onclick="closeEditModal()">ยกเลิก</button>
                    <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded"
                        id="save-edit">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function validateForm(event) {
            event.preventDefault(); // Prevent form submission before validation

            // Select only product rows, not the summary row
            const productRows = document.querySelectorAll('#products-table tbody tr:not(.bg-gradient-to-r):not(.h-16)');

            if (productRows.length === 0) {
                Swal.fire({
                    icon: "warning",
                    title: "ไม่มีสินค้า",
                    text: "กรุณาเพิ่มสินค้าก่อนยืนยันรายการ",
                    confirmButtonColor: "#3085d6"
                });
                return false;
            }

            // Collect items with incomplete data
            let invalidProducts = [];
            productRows.forEach(row => {
                const barcode = row.cells[0].textContent.trim();
                const productName = row.cells[1].textContent.trim();
                const capital = parseFloat(row.cells[3].textContent.replace(/,/g, '')) || 0;

                // Check product name and capital price
                if (productName === "ยังไม่ระบุ" || productName === "ไม่มีสินค้าในระบบ" || capital === 0) {
                    invalidProducts.push({
                        barcode: barcode,
                        name: productName,
                        capital: capital
                    });
                }
            });

            // If there are invalid products
            if (invalidProducts.length > 0) {
                let errorHTML = "<div style='text-align:left; max-height:300px; overflow-y:auto;'>";
                errorHTML += "<p>กรุณาตรวจสอบและแก้ไขข้อมูลสินค้าต่อไปนี้ก่อนยืนยัน:</p><ul>";

                invalidProducts.forEach(product => {
                    errorHTML += `<li>บาร์โค้ด <b>${product.barcode}</b>: `;
                    let issues = [];
                    if (product.name === "ยังไม่ระบุ" || product.name === "ไม่มีสินค้าในระบบ") {
                        issues.push("ชื่อสินค้าไม่ถูกต้อง");
                    }
                    if (product.capital === 0) {
                        issues.push("ราคาทุนเป็น 0");
                    }
                    errorHTML += issues.join(" และ ") + "</li>";
                });

                errorHTML += "</ul></div>";

                Swal.fire({
                    icon: "warning",
                    title: "ข้อมูลไม่ครบถ้วน",
                    html: errorHTML,
                    confirmButtonColor: "#3085d6",
                    confirmButtonText: "ตกลง"
                });
                return false;
            }

            // If all data is complete, show confirmation dialog
            Swal.fire({
                icon: "question",
                title: "ยืนยันรายการ?",
                text: "คุณต้องการยืนยันรายการนี้หรือไม่?",
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#d33",
                confirmButtonText: "ใช่, ยืนยัน",
                cancelButtonText: "ยกเลิก"
            }).then((result) => {
                if (result.isConfirmed) {
                    // Disable the button to prevent multiple clicks
                    let confirmBtn = document.querySelector('#confirm-form button[type="submit"]');
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังดำเนินการ...';
                    }

                    // Submit the form
                    document.getElementById("confirm-form").submit();
                }
            });

            return false; // Prevent automatic form submission
        }
    </script>

    <script>
        const inputField = document.getElementById('inputField');

        // ฟังก์ชันสำหรับการเปลี่ยนสีพื้นหลังตามการกดปุ่ม
        function changeInputBackground(event) {
            if (event.keyCode === 8 || event.keyCode === 46) { // 8 = Backspace, 46 = Delete
                inputField.classList.remove('input-normal');
                inputField.classList.add('input-delete'); // เพิ่มคลาสสำหรับการลบข้อมูล
            }

            if (event.keyCode === 13) { // 13 = Enter
                inputField.classList.remove('input-normal');
                inputField.classList.add('input-add'); // เพิ่มคลาสสำหรับการเพิ่มข้อมูล
            }
        }

        // ฟังก์ชันสำหรับการรีเซ็ตพื้นหลังเมื่อเริ่มกรอกข้อมูล
        function resetInputBackground() {
            inputField.classList.remove('input-delete');
            inputField.classList.remove('input-add');
            inputField.classList.add('input-normal');
        }

        // ฟังการกดปุ่มและการเริ่มกรอกข้อมูล
        inputField.addEventListener('keydown', function (event) {
            changeInputBackground(event); // เปลี่ยนพื้นหลังตามการกดปุ่ม
        });

        // รีเซ็ตพื้นหลังเมื่อเริ่มกรอกข้อมูล
        inputField.addEventListener('focus', function () {
            resetInputBackground(); // รีเซ็ตพื้นหลังเมื่อเริ่มกรอกข้อมูล
        });
    </script>

    <script>
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        $(document).ready(function () {
            // Shared state
            const state = {
                activeDeleteBarcode: null,
                barcodeInput: $('input[name="product_id"]')
            };

            // Utility functions
            const utils = {
                handleServerResponse: function (response) {
                    console.log('Server response:', response);
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (response.error || 'กรุณาลองใหม่อีกครั้ง'));
                    }
                },

                handleServerError: function (xhr) {
                    console.error('Server response:', xhr.responseText);
                    try {
                        const htmlContent = xhr.responseText;
                        const jsonMatch = htmlContent.match(/\{.*\}/);
                        if (jsonMatch) {
                            const jsonResponse = JSON.parse(jsonMatch[0]);
                            if (jsonResponse.success) {
                                location.reload();
                                return;
                            }
                        }
                    } catch (e) {
                        console.error('JSON parsing error:', e);
                    }
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง');
                    state.barcodeInput.focus();
                },

                sendRequest: function (data) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: data,
                        dataType: 'json',
                        success: function (response) {
                            utils.handleServerResponse(response);
                        },
                        error: function (xhr) {
                            utils.handleServerError(xhr);
                        }
                    });
                },

                validateFormFields: function (qty, capital, productName) {
                    if (!productName || productName.trim() === '') {
                        alert('กรุณาระบุชื่อสินค้า');
                        $('#product_name').focus();
                        return false;
                    }

                    if (qty <= 0) {
                        alert('จำนวนสินค้าต้องมากกว่า 0');
                        $('#qty').focus();
                        return false;
                    }

                    if (capital < 0) {
                        alert('ราคาทุนต้องไม่ต่ำกว่า 0');
                        $('#capital').focus();
                        return false;
                    }

                    return true;
                },

                updateRowHighlighting: function () {
                    // Remove highlight from all rows
                    $('#products-table tbody tr').removeClass('bg-pink-100');

                    // Add highlight to the active delete row
                    if (state.activeDeleteBarcode) {
                        $(`#products-table tbody tr[data-product-id="${state.activeDeleteBarcode}"]`).addClass('bg-pink-100');
                    }
                }
            };

            // Event handlers
            const handlers = {
                toggleDeleteMode: function (e) {
                    const $target = $(e.target);
                    const barcode = $target.data('barcode');
                    const isActive = state.activeDeleteBarcode === barcode;

                    // Reset all buttons
                    $('.toggle-delete-mode')
                        .removeClass('bg-pink-500')
                        .addClass('bg-red-500')
                        .text('ลบ');

                    if (!isActive) {
                        $target
                            .removeClass('bg-red-500')
                            .addClass('bg-pink-500')
                            .text('ยกเลิกลบ');
                        state.barcodeInput.addClass('bg-pink-100');
                        state.activeDeleteBarcode = barcode;
                    } else {
                        state.barcodeInput.removeClass('bg-pink-100');
                        state.activeDeleteBarcode = null;
                    }

                    utils.updateRowHighlighting();
                    state.barcodeInput.focus();
                },

                handleBarcodeInput: function (e) {
                    if (e.which === 13) { // Enter key
                        e.preventDefault();
                        const inputValue = $(e.target).val();

                        // Split input by "*" delimiter
                        const parts = inputValue.split('*');
                        let product_id = parts[0]; // First part is always the barcode

                        // Check if the barcode starts with '-', indicating delete mode
                        let isDeleteMode = state.activeDeleteBarcode !== null;
                        if (product_id.startsWith('-')) {
                            isDeleteMode = true;
                            product_id = product_id.substring(1); // Remove the '-' prefix
                        }

                        // Create the request data
                        let requestData = {
                            barcode: product_id,
                            deleteMode: isDeleteMode
                        };

                        // If there's a quantity specified (format: barcode*qty)
                        if (parts.length > 1 && parts[1]) {
                            requestData.qty = parseInt(parts[1]);
                        }

                        // If there's a capital price specified (format: barcode*qty*price)
                        if (parts.length > 2 && parts[2]) {
                            requestData.capital = parseFloat(parts[2]);
                        }

                        // Clear the input field
                        $(e.target).val('');

                        // Send the request with all gathered data
                        utils.sendRequest(requestData);

                        // If we're not in delete mode initiated by '-', keep the active delete barcode
                        if (!product_id.startsWith('-') && isDeleteMode) {
                            state.activeDeleteBarcode = product_id;
                        } else {
                            state.activeDeleteBarcode = null;
                        }
                    }
                },

                handleEditProduct: function (e) {
                    const $target = $(e.currentTarget);
                    const productData = {
                        id: $target.data('id'),
                        qty: $target.data('qty'),
                        capital: $target.data('capital'),
                        name: $target.data('name'),
                        barcode: $target.data('barcode')
                    };

                    console.log('Edit product data:', productData);

                    $('#lot_product_id').val(productData.id);
                    $('#product_barcode').val(productData.barcode);
                    $('#product_barcode_hidden').val(productData.barcode);
                    $('#product_name').val(productData.name);
                    $('#qty').val(productData.qty);
                    $('#capital').val(productData.capital);

                    $('#editModal').removeClass('hidden');
                },

                handleSaveEdit: function (e) {
                    const form = $('#edit-form');
                    const $submitButton = $(e.target);

                    if (!form[0].checkValidity()) {
                        form[0].reportValidity();
                        return;
                    }

                    const qty = parseFloat($('#qty').val());
                    const capital = parseFloat($('#capital').val());
                    const productName = $('#product_name').val();

                    if (!utils.validateFormFields(qty, capital, productName)) {
                        return;
                    }

                    $submitButton.prop('disabled', true).text('กำลังบันทึก...');

                    const formData = form.serialize();
                    console.log('Sending data:', formData);

                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function (response) {
                            utils.handleServerResponse(response);
                            closeEditModal();
                        },
                        error: function (xhr) {
                            utils.handleServerError(xhr);
                        },
                        complete: function () {
                            $submitButton.prop('disabled', false).text('บันทึก');
                        }
                    });
                },

                handleInputValidation: function (e) {
                    const $input = $(e.target);
                    const value = parseFloat($input.val());
                    const minValue = $input.attr('id') === 'qty' ? 0 : -1;

                    $input.toggleClass('border-red-500', value <= minValue);
                }
            };

            // Event bindings
            $('.toggle-delete-mode').on('click', handlers.toggleDeleteMode);
            state.barcodeInput.on('keypress', handlers.handleBarcodeInput);
            $('.edit-product').on('click', handlers.handleEditProduct);
            $('#save-edit').on('click', handlers.handleSaveEdit);
            $('#qty, #capital').on('input', handlers.handleInputValidation);

            // Modal close handlers
            $('#editModal').on('click', function (e) {
                if (e.target === this) closeEditModal();
            });
        });
    </script>

    <style>
        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Add the styles for delete mode */
        .bg-pink-100 {
            background-color: #fbcfe8 !important;
        }

        .bg-pink-500 {
            background-color: #ec4899 !important;
        }

        /* Override hover behavior when in pink-100 mode */
        .bg-pink-100:hover {
            background-color: #fbcfe8 !important;
        }
    </style>

</body>

</html>