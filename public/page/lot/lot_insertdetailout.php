<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Handle POST requests (product scan and lot confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Handle product scan
        if (isset($_POST['barcode'])) {
            $barcode = trim($_POST['barcode']);
            if (empty($barcode)) {
                throw new Exception('รหัสบาร์โค้ดไม่สามารถว่างได้');
            }

            // Validate barcode format (numbers only, max 20 digits)
            if (!preg_match('/^[0-9]{1,20}$/', $barcode)) {
                throw new Exception('รหัสบาร์โค้ดต้องเป็นตัวเลขและมีความยาวไม่เกิน 20 ตัวอักษร');
            }

            $lot_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$lot_id) {
                throw new Exception('lot_id is missing');
            }

            $isDeleteMode = isset($_POST['deleteMode']) && $_POST['deleteMode'] === 'true';
            $quantity = isset($_POST['qty']) && is_numeric($_POST['qty']) && $_POST['qty'] > 0 ? (int)$_POST['qty'] : 1;
            $capital = isset($_POST['capital']) && is_numeric($_POST['capital']) && $_POST['capital'] >= 0 ? (float)$_POST['capital'] : null;
            $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : null;

            // Fetch product details
            $productStmt = $pdo->prepare("SELECT product_capital, product_name FROM product WHERE product_id = ?");
            $productStmt->execute([$barcode]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            $capital = $capital ?? ($product ? (float)$product['product_capital'] : 0);
            $product_name = $product_name ?? ($product ? $product['product_name'] : null);

            // Validate product_name for new products
            if (!$product && empty($product_name) && !$isDeleteMode) {
                throw new Exception('กรุณาระบุชื่อสินค้าเมื่อเพิ่มสินค้าใหม่');
            }

            // Check if product exists in lot_product (ensure unique product_id per lot_id)
            $stmt = $pdo->prepare("SELECT lp.*, p.product_name FROM lot_product lp 
                                 LEFT JOIN product p ON p.product_id = lp.product_id 
                                 WHERE lp.lot_id = ? AND lp.product_id = ?");
            $stmt->execute([$lot_id, $barcode]);
            $existing_product = $stmt->fetch(PDO::FETCH_ASSOC);

            $pdo->beginTransaction();

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
                    // Check stock in source warehouse
                    $sourceStockStmt = $pdo->prepare("
                        SELECT SUM(lp.lot_product_remain) as total_remain
                        FROM lot_product lp
                        JOIN lot l ON lp.lot_id = l.lot_id
                        WHERE lp.product_id = ? AND l.warehouse_from_id = (
                            SELECT warehouse_from_id FROM lot WHERE lot_id = ?
                        )");
                    $sourceStockStmt->execute([$barcode, $lot_id]);
                    $sourceQty = (int)($sourceStockStmt->fetchColumn() ?? 0);

                    $newQty = $existing_product['lot_product_qty'] + $quantity;
                    if ($sourceQty < $newQty) {
                        throw new Exception("สินค้าในคลังต้นทางไม่เพียงพอ (มี $sourceQty คงเหลือ)");
                    }

                    $newCapital = $capital > 0 ? $capital : $existing_product['lot_product_capital'];
                    $update = $pdo->prepare("UPDATE lot_product SET 
                        lot_product_qty = ?,
                        lot_product_remain = ?,
                        lot_product_capital = ?
                        WHERE lot_id = ? AND product_id = ?");
                    $update->execute([$newQty, $newQty, $newCapital, $lot_id, $barcode]);
                }
            } else {
                if ($isDeleteMode) {
                    throw new Exception("ไม่พบสินค้าที่มีรหัสบาร์โค้ด $barcode ในล็อตนี้");
                }

                // Check stock in source warehouse
                $sourceStockStmt = $pdo->prepare("
                    SELECT SUM(lp.lot_product_remain) as total_remain
                    FROM lot_product lp
                    JOIN lot l ON lp.lot_id = l.lot_id
                    WHERE lp.product_id = ? AND l.warehouse_from_id = (
                        SELECT warehouse_from_id FROM lot WHERE lot_id = ?
                    )");
                $sourceStockStmt->execute([$barcode, $lot_id]);
                $sourceQty = (int)($sourceStockStmt->fetchColumn() ?? 0);

                if ($sourceQty < $quantity) {
                    throw new Exception("สินค้าในคลังต้นทางไม่เพียงพอ (มี $sourceQty คงเหลือ)");
                }

                // Insert new product if it doesn't exist
                if (!$product) {
                    $insert_product = $pdo->prepare("INSERT INTO product (
                        product_id, product_name, product_price1, product_price2,
                        product_price3, product_price4, product_capital,
                        product_type_id, product_brand_id
                    ) VALUES (?, ?, 0, 0, 0, 0, ?, NULL, NULL)");
                    $insert_product->execute([$barcode, $product_name, $capital]);
                }

                $insert = $pdo->prepare("INSERT INTO lot_product (
                    lot_id, product_id, lot_product_qty, lot_product_remain,
                    lot_product_capital, lot_product_date, lot_product_status
                ) VALUES (?, ?, ?, ?, ?, NOW(), 1)");
                $insert->execute([$lot_id, $barcode, $quantity, $quantity, $capital]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        }

        // Handle lot confirmation
        if (isset($_POST['lot_id']) && isset($_POST['confirm'])) {
            $lot_id = (int)$_POST['lot_id'];
            $pdo->beginTransaction();

            // Check stock availability
            $stockCheck = $pdo->prepare("
                SELECT lp.product_id, lp.lot_product_qty, 
                       SUM(lp2.lot_product_remain) as total_remain
                FROM lot_product lp
                LEFT JOIN lot_product lp2 ON lp2.product_id = lp.product_id
                JOIN lot l ON lp2.lot_id = l.lot_id
                WHERE lp.lot_id = ? 
                AND l.warehouse_from_id = (
                    SELECT warehouse_from_id FROM lot WHERE lot_id = ?
                )
                GROUP BY lp.product_id, lp.lot_product_qty
            ");
            $stockCheck->execute([$lot_id, $lot_id]);
            $stockResults = $stockCheck->fetchAll(PDO::FETCH_ASSOC);

            foreach ($stockResults as $item) {
                $available = (int)($item['total_remain'] ?? 0);
                if ($available < $item['lot_product_qty']) {
                    throw new Exception("สินค้า {$item['product_id']} ในคลังต้นทางไม่เพียงพอ (มี $available คงเหลือ)");
                }
            }

            // Update lot status to confirmed
            $updateLot = $pdo->prepare("UPDATE lot SET lot_status_id = 2 WHERE lot_id = ?");
            $updateLot->execute([$lot_id]);

            // Decrease stock in source warehouse
            $updateSource = $pdo->prepare("
                UPDATE lot_product lp
                JOIN lot l ON lp.lot_id = l.lot_id
                SET lp.lot_product_remain = lp.lot_product_remain - (
                    SELECT lot_product_qty 
                    FROM lot_product lp2 
                    WHERE lp2.lot_id = ? AND lp2.product_id = lp.product_id
                )
                WHERE l.warehouse_from_id = (
                    SELECT warehouse_from_id FROM lot WHERE lot_id = ?
                ) AND lp.product_id IN (
                    SELECT product_id FROM lot_product WHERE lot_id = ?
                )
            ");
            $updateSource->execute([$lot_id, $lot_id, $lot_id]);

            // Increase stock in destination warehouse
            $updateDest = $pdo->prepare("
                INSERT INTO lot_product (lot_id, product_id, lot_product_qty, lot_product_remain, lot_product_capital, lot_product_date, lot_product_status)
                SELECT ?, lp.product_id, lp.lot_product_qty, lp.lot_product_qty, lp.lot_product_capital, NOW(), 1
                FROM lot_product lp
                WHERE lp.lot_id = ?
            ");
            $updateDest->execute([$lot_id, $lot_id]);

            $pdo->commit();
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error: " . $e->getMessage() . " | Barcode: " . ($barcode ?? 'N/A') . " | Lot ID: " . ($lot_id ?? 'N/A'));
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error: " . $e->getMessage() . " | Barcode: " . ($barcode ?? 'N/A') . " | Lot ID: " . ($lot_id ?? 'N/A'));
        echo json_encode(['success' => false, 'error' => 'ข้อผิดพลาดของฐานข้อมูล: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch lot details
$lot_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$lot_id) {
    die("lot_id is missing");
}

$query = "SELECT
    l.lot_id, l.lot_name, l.lot_detail, l.lot_date, l.warehouse_from_id,
    w1.warehouse_name as warehouse_from_name, l.warehouse_inout, l.warehouse_id,
    w2.warehouse_name, sup.supplier_id, sup.supplier_name,
    ls.lot_status_id, ls.lot_status_name
FROM lot l
LEFT JOIN warehouse w1 ON l.warehouse_from_id = w1.warehouse_id
LEFT JOIN warehouse w2 ON l.warehouse_id = w2.warehouse_id
LEFT JOIN supplier sup ON l.supplier_id = sup.supplier_id
INNER JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
WHERE l.lot_id = :lot_id";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot_data) {
        die("ไม่พบข้อมูลล็อตสินค้า");
    }

    // Fetch unique products for the lot
    $product_query = "
    SELECT
        lp.lot_id, lp.lot_product_id, lp.product_id,
        COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
        lp.lot_product_qty, lp.lot_product_remain, lp.lot_product_capital,
        lp.lot_product_date, lp.lot_product_status,
        p.product_capital as default_capital,
        COALESCE((
            SELECT SUM(lp2.lot_product_remain)
            FROM lot_product lp2
            JOIN lot l ON lp2.lot_id = l.lot_id
            WHERE lp2.product_id = lp.product_id
            AND l.warehouse_from_id = (
                SELECT warehouse_from_id FROM lot WHERE lot_id = :lot_id
            )
        ), 0) - lp.lot_product_qty as source_qty_remaining,
        CASE WHEN p.product_id IS NULL THEN 1 ELSE 0 END as needs_update
    FROM lot_product lp
    LEFT JOIN product p ON p.product_id = lp.product_id
    WHERE lp.lot_id = :lot_id
    GROUP BY lp.product_id, lp.lot_id
    ORDER BY needs_update DESC, lp.lot_product_date DESC";

    $stmt = $pdo->prepare($product_query);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}

// Fetch warehouse list
$query = "SELECT warehouse_id, warehouse_name FROM warehouse ORDER BY warehouse_name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lot_status_id = $lot_data['lot_status_id'] ?? null;
$lot_status_name = $lot_data['lot_status_name'] ?? null;

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดการย้ายคลังสินค้า</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .input-delete {
            background-color: red;
            color: white;
        }

        .input-add {
            background-color: green;
            color: white;
        }

        .input-normal {
            background-color: white;
            color: black;
        }

        .modal-content {
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

        .bg-pink-100:hover {
            background-color: #fbcfe8 !important;
        }

        .bg-red-100 {
            background-color: #fee2e2 !important;
        }

        table {
            table-layout: fixed;
        }

        th,
        td {
            word-wrap: break-word;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-8 mb-11">
        <h1 class="text-4xl font-bold mb-8">รายละเอียดการย้ายคลังสินค้า</h1>
        <?php if ($lot_data): ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="grid grid-cols-2 gap-6">
                    <div><label class="font-semibold mb-2">รหัส Lot สินค้า:</label><span><?php echo htmlspecialchars($lot_data['lot_id']); ?></span></div>
                    <div><label class="font-semibold mb-2">ชื่อ Lot สินค้า:</label><span><?php echo htmlspecialchars($lot_data['lot_name']); ?></span></div>
                    <div><label class="font-semibold mb-2">วันที่และเวลาย้ายคลังสินค้า:</label><span><?php echo htmlspecialchars($lot_data['lot_date']); ?></span></div>
                    <div><label class="font-semibold mb-2">นำมาจากคลัง:</label><span><?php echo htmlspecialchars($lot_data['warehouse_from_name'] ?? 'ยังไม่ระบุ'); ?></span></div>
                    <div><label class="font-semibold mb-2">ลงคลัง:</label><span><?php echo htmlspecialchars($lot_data['warehouse_name']); ?></span></div>
                    <div>
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
                    <div><label class="font-semibold mb-2">สถานะ:</label><span><?php echo htmlspecialchars($lot_data['lot_status_name']); ?></span></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว"): ?>
            <h2 class="text-2xl font-semibold mb-4">สแกนสินค้าเข้าล็อต</h2>
            <form id="barcode-form" action="" method="POST">
                <div class="mb-4 flex items-center gap-4">
                    <label class="block mb-2" for="inputField">รหัสบาร์โค้ด:</label>
                    <input type="text" id="inputField" name="barcode"
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                        pattern="[0-9*]*" placeholder="กรอกเฉพาะตัวเลขหรือ * , -" autofocus>
                </div>
                <div class="flex space-x-4">
                    <div class="p-4 text-sm text-gray-600 mt-1 mb-4 border border-green-300 bg-green-100">
                        <p><b>รูปแบบการป้อนข้อมูล:</b> รหัสบาร์โค้ด*จำนวน*ราคาทุน*ชื่อสินค้า (เช่น 12345*2*150*สินค้าA)</p>
                        <p>หากไม่ระบุจำนวนหรือราคาทุน ระบบจะใช้ค่าเริ่มต้นจากตาราง product</p>
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
                            <th class="px-6 py-3 border text-center">จำนวนที่ต้องการย้าย</th>
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
                        foreach ($lot_products as $item):
                            $item_total = $item['lot_product_qty'] * $item['lot_product_capital'];
                            $total_value += $item_total;
                            $total_quantity += $item['lot_product_qty'];
                            $product_id = isset($item['product_id']) ? htmlspecialchars($item['product_id'], ENT_QUOTES, 'UTF-8') : '';
                        ?>
                            <tr class="<?php echo $item['source_qty_remaining'] < 0 ? 'bg-red-100' : ''; ?>"
                                data-product-id="<?php echo $product_id; ?>">
                                <td class="pl-6 py-2 border"><?php echo htmlspecialchars($item['product_id']); ?></td>
                                <td class="pl-6 py-2 border"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-center py-2 border"><?php echo htmlspecialchars($item['source_qty_remaining']); ?></td>
                                <td class="text-center py-2 border"><?php echo htmlspecialchars($item['lot_product_qty']); ?></td>
                                <td class="text-right py-2 border"><?php echo number_format($item['lot_product_capital'], 2); ?></td>
                                <td class="text-right py-2 border"><?php echo number_format($item_total, 2); ?></td>
                                <?php if (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว"): ?>
                                    <td class="text-right py-2 border"><?php echo htmlspecialchars($lot_data['lot_status_name']); ?></td>
                                    <td class="text-right py-2 border px-4">
                                        <button type="button" class="bg-blue-500 text-white px-4 py-1 rounded edit-product"
                                            data-id="<?php echo htmlspecialchars($item['lot_product_id']); ?>"
                                            data-qty="<?php echo htmlspecialchars($item['lot_product_qty']); ?>"
                                            data-capital="<?php echo htmlspecialchars($item['lot_product_capital']); ?>"
                                            data-default-capital="<?php echo htmlspecialchars($item['default_capital'] ?? 0); ?>"
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
                            <tr class="bg-gradient-to-r from-gray-200 to-gray-300 text-gray-800 font-medium h-14 border-t-2 border-gray-400">
                                <td colspan="2" class="text-end pe-4 align-middle">ยอดรวมสินค้าที่ย้ายคลัง:</td>
                                <td class="text-center py-4 font-bold"><?php echo number_format($total_quantity); ?></td>
                                <td colspan="2" class="text-end pe-4 align-middle">มูลค่ารวมทั้งสิ้น:</td>
                                <td class="text-end py-4 font-bold"><?php echo number_format($total_value, 2); ?> บาท</td>
                                <?php if (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว"): ?>
                                    <td colspan="2"></td>
                                <?php endif; ?>
                            </tr>
                            <?php if ($lot_status_id == 1): ?>
                                <tr class="h-16">
                                    <td colspan="<?php echo (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== 'ยืนยันแล้ว') ? 8 : 6; ?>" class="text-end pe-4 pt-4 overflow-hidden">
                                        <button type="button" id="confirm-lot" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow transition duration-200 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                                            <i class="fas fa-check-circle me-2"></i>ยืนยันการย้ายสินค้า
                                        </button>
                                        <input type="hidden" id="lot_id" value="<?php echo htmlspecialchars($lot_data['lot_id']); ?>">
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
                    <input type="text" class="w-full p-2 border rounded" id="product_name" name="product_name" required>
                </div>
                <div class="mb-4">
                    <label class="block mb-2">จำนวนที่ต้องการย้าย:</label>
                    <input type="number" class="w-full p-2 border rounded" id="qty" name="qty" required min="1">
                </div>
                <div class="mb-4">
                    <label class="block mb-2">ราคาทุน:</label>
                    <input type="number" class="w-full p-2 border rounded" id="capital" name="capital" required min="0" step="0.01">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded" onclick="closeEditModal()">ยกเลิก</button>
                    <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded" id="save-edit">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let isSubmitting = false;
        let lastSubmissionTime = 0;
        const DEBOUNCE_DELAY = 500;

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function validateForm() {
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

            let invalidProducts = [];
            productRows.forEach(row => {
                const barcode = row.cells[0].textContent.trim();
                const productName = row.cells[1].textContent.trim();
                const sourceQtyRemaining = parseInt(row.cells[2].textContent.trim()) || 0;
                const transferQty = parseInt(row.cells[3].textContent.trim()) || 0;
                const capital = parseFloat(row.cells[4].textContent.replace(/,/g, '')) || 0;

                let issues = [];
                if (productName === "ยังไม่ระบุ" || productName === "ไม่มีสินค้าในระบบ") {
                    issues.push("ชื่อสินค้าไม่ถูกต้อง");
                }
                if (capital <= 0) {
                    issues.push("ราคาทุนต้องมากกว่า 0");
                }
                if (sourceQtyRemaining < 0) {
                    issues.push(`สินค้าในคลังต้นทางไม่เพียงพอ (คงเหลือ ${sourceQtyRemaining + transferQty} แต่ต้องการย้าย ${transferQty})`);
                }
                if (issues.length > 0) {
                    invalidProducts.push({
                        barcode,
                        name: productName,
                        issues
                    });
                }
            });

            if (invalidProducts.length > 0) {
                let errorHTML = "<div style='text-align:left; max-height:300px; overflow-y:auto;'>";
                errorHTML += "<p>กรุณาตรวจสอบและแก้ไขข้อมูลสินค้าต่อไปนี้ก่อนยืนยัน:</p><ul>";
                invalidProducts.forEach(product => {
                    errorHTML += `<li>บาร์โค้ด <b>${product.barcode}</b>: ${product.issues.join(" และ ")}</li>`;
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
            return true;
        }

        const inputField = document.getElementById('inputField');

        function changeInputBackground(event) {
            if (event.keyCode === 8 || event.keyCode === 46) {
                inputField.classList.remove('input-normal');
                inputField.classList.add('input-delete');
            }
            if (event.keyCode === 13) {
                inputField.classList.remove('input-normal');
                inputField.classList.add('input-add');
            }
        }

        function resetInputBackground() {
            inputField.classList.remove('input-delete');
            inputField.classList.remove('input-add');
            inputField.classList.add('input-normal');
        }

        inputField.addEventListener('keydown', changeInputBackground);
        inputField.addEventListener('focus', resetInputBackground);

        $(document).ready(function() {
            const state = {
                activeDeleteBarcode: null,
                barcodeInput: $('input[name="product_id"]')
            };

            const utils = {
                handleServerResponse: function(response, successCallback) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: 'บันทึกข้อมูลเรียบร้อยแล้ว',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            if (successCallback) successCallback();
                            else window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: response.error || 'กรุณาลองใหม่อีกครั้ง',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                },
                handleServerError: function(xhr) {
                    let errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง';
                    try {
                        const jsonResponse = JSON.parse(xhr.responseText.match(/\{.*\}/)?.[0] || '{}');
                        errorMessage = jsonResponse.error || errorMessage;
                    } catch (e) {
                        console.error('Error parsing server response:', xhr.responseText);
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: errorMessage,
                        confirmButtonColor: '#3085d6'
                    });
                    state.barcodeInput.focus();
                },
                sendRequest: function(data, successCallback) {
                    const currentTime = Date.now();
                    if (isSubmitting || (currentTime - lastSubmissionTime < DEBOUNCE_DELAY)) {
                        console.warn('Request blocked: Submitting too quickly or already in progress');
                        return;
                    }
                    isSubmitting = true;
                    lastSubmissionTime = currentTime;
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: data,
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(response) {
                            utils.handleServerResponse(response, successCallback);
                        },
                        error: utils.handleServerError,
                        complete: function() {
                            isSubmitting = false;
                            state.barcodeInput.prop('disabled', false).val('').focus();
                        }
                    });
                },
                validateFormFields: function(qty, capital, productName) {
                    if (!productName || productName.trim() === '') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'ชื่อสินค้าไม่ถูกต้อง',
                            text: 'กรุณาระบุชื่อสินค้า',
                            confirmButtonColor: '#3085d6'
                        });
                        $('#product_name').focus();
                        return false;
                    }
                    if (qty <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'จำนวนไม่ถูกต้อง',
                            text: 'จำนวนสินค้าต้องมากกว่า 0',
                            confirmButtonColor: '#3085d6'
                        });
                        $('#qty').focus();
                        return false;
                    }
                    if (capital < 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'ราคาทุนไม่ถูกต้อง',
                            text: 'ราคาทุนต้องไม่ต่ำกว่า 0',
                            confirmButtonColor: '#3085d6'
                        });
                        $('#capital').focus();
                        return false;
                    }
                    return true;
                },
                updateRowHighlighting: function() {
                    $('#products-table tbody tr').removeClass('bg-pink-100');
                    if (state.activeDeleteBarcode) {
                        $(`#products-table tbody tr[data-product-id="${state.activeDeleteBarcode}"]`).addClass('bg-pink-100');
                    }
                }
            };

            const handlers = {
                toggleDeleteMode: function(e) {
                    const $target = $(e.target);
                    const barcode = $target.data('barcode');
                    const isActive = state.activeDeleteBarcode === barcode;
                    $('.toggle-delete-mode').removeClass('bg-pink-500').addClass('bg-red-500').text('ลบ');
                    if (!isActive) {
                        $target.removeClass('bg-red-500').addClass('bg-pink-500').text('ยกเลิกลบ');
                        state.barcodeInput.addClass('bg-pink-100');
                        state.activeDeleteBarcode = barcode;
                    } else {
                        state.barcodeInput.removeClass('bg-pink-100');
                        state.activeDeleteBarcode = null;
                    }
                    utils.updateRowHighlighting();
                    state.barcodeInput.focus();
                },
                handleBarcodeInput: function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        const $input = $(e.target);
                        if ($input.prop('disabled') || isSubmitting) return;
                        $input.prop('disabled', true);
                        const inputValue = $input.val().trim();
                        if (!inputValue) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'รหัสบาร์โค้ดไม่ถูกต้อง',
                                text: 'กรุณาป้อนรหัสบาร์โค้ด',
                                confirmButtonColor: '#3085d6'
                            });
                            $input.prop('disabled', false).focus();
                            return;
                        }

                        const parts = inputValue.split('*');
                        let product_id = parts[0];
                        let isDeleteMode = state.activeDeleteBarcode !== null || product_id.startsWith('-');
                        let requestData = {};

                        if (product_id.startsWith('-')) {
                            product_id = product_id.substring(1);
                            isDeleteMode = true;
                        }

                        if (!product_id || !/^[0-9]+$/.test(product_id)) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'รหัสบาร์โค้ดไม่ถูกต้อง',
                                text: 'รหัสบาร์โค้ดต้องเป็นตัวเลขเท่านั้น',
                                confirmButtonColor: '#3085d6'
                            });
                            $input.prop('disabled', false).focus();
                            return;
                        }

                        requestData = {
                            barcode: product_id,
                            deleteMode: isDeleteMode
                        };

                        if (parts.length > 1 && parts[1] && !isNaN(parts[1]) && parseInt(parts[1]) > 0) {
                            requestData.qty = parseInt(parts[1]);
                        }
                        if (!isDeleteMode && parts.length > 2 && parts[2] && !isNaN(parts[2]) && parseFloat(parts[2]) >= 0) {
                            requestData.capital = parseFloat(parts[2]);
                        }
                        if (!isDeleteMode && parts.length > 3 && parts[3]) {
                            requestData.product_name = parts[3];
                        }

                        utils.sendRequest(requestData);

                        if (isDeleteMode && state.activeDeleteBarcode) {
                            state.activeDeleteBarcode = null;
                            $('.toggle-delete-mode').removeClass('bg-pink-500').addClass('bg-red-500').text('ลบ');
                            state.barcodeInput.removeClass('bg-pink-100');
                            utils.updateRowHighlighting();
                        }
                    }
                },
                handleEditProduct: function(e) {
                    const $target = $(e.currentTarget);
                    const productData = {
                        id: $target.data('id'),
                        qty: $target.data('qty'),
                        capital: $target.data('capital'),
                        defaultCapital: $target.data('default-capital'),
                        name: $target.data('name'),
                        barcode: $target.data('barcode')
                    };
                    $('#lot_product_id').val(productData.id);
                    $('#product_barcode').val(productData.barcode);
                    $('#product_barcode_hidden').val(productData.barcode);
                    $('#product_name').val(productData.name);
                    $('#qty').val(productData.qty);
                    $('#capital').val(productData.capital || productData.defaultCapital);
                    $('#editModal').removeClass('hidden');
                },
                handleSaveEdit: function(e) {
                    const form = $('#edit-form');
                    const $submitButton = $(e.target);
                    if (!form[0].checkValidity()) {
                        form[0].reportValidity();
                        return;
                    }
                    const qty = parseFloat($('#qty').val());
                    const capital = parseFloat($('#capital').val());
                    const productName = $('#product_name').val();
                    if (!utils.validateFormFields(qty, capital, productName)) return;
                    $submitButton.prop('disabled', true).text('กำลังบันทึก...');
                    const formData = form.serializeArray();
                    formData.push({
                        name: 'deleteMode',
                        value: false
                    });
                    utils.sendRequest(formData, () => {
                        closeEditModal();
                        window.location.reload();
                    });
                },
                handleConfirmLot: function() {
                    if (!validateForm()) return;
                    Swal.fire({
                        icon: "question",
                        title: "ยืนยันการย้ายสินค้า?",
                        text: "คุณต้องการยืนยันการย้ายสินค้านี้หรือไม่?",
                        showCancelButton: true,
                        confirmButtonColor: "#28a745",
                        cancelButtonColor: "#d33",
                        confirmButtonText: "ใช่, ยืนยัน",
                        cancelButtonText: "ยกเลิก"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const $confirmBtn = $('#confirm-lot');
                            $confirmBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>กำลังย้ายสินค้า...');
                            utils.sendRequest({
                                lot_id: $('#lot_id').val(),
                                confirm: true
                            }, () => {
                                window.location.href = 'index.php?page=lot_insertout&success=1';
                            });
                        }
                    });
                },
                handleInputValidation: function(e) {
                    const $input = $(e.target);
                    const value = parseFloat($input.val());
                    const minValue = $input.attr('id') === 'qty' ? 0 : -1;
                    $input.toggleClass('border-red-500', value <= minValue);
                }
            };

            // Bind events
            $(document).off('click', '.toggle-delete-mode').on('click', '.toggle-delete-mode', handlers.toggleDeleteMode);
            state.barcodeInput.off('keypress').on('keypress', handlers.handleBarcodeInput);
            $(document).off('click', '.edit-product').on('click', '.edit-product', handlers.handleEditProduct);
            $(document).off('click', '#save-edit').on('click', '#save-edit', handlers.handleSaveEdit);
            $(document).off('click', '#confirm-lot').on('click', '#confirm-lot', handlers.handleConfirmLot);
            $(document).off('input', '#qty, #capital').on('input', '#qty, #capital', handlers.handleInputValidation);
            $(document).off('click', '#editModal').on('click', '#editModal', function(e) {
                if (e.target === this) closeEditModal();
            });
        });
    </script>
</body>

</html>