<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Constants
const LOT_STATUS_PENDING = 1;
const LOT_STATUS_CONFIRMED = 2;
const MAX_BARCODE_LENGTH = 20;

// CSRF Token Generation
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX request for table data (for initial load or fallback)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch_table') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $lot_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if (!$lot_id) {
            throw new Exception('lot_id is missing');
        }

        // Fetch products
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

        // Calculate totals
        $total_value = 0;
        $total_quantity = 0;
        foreach ($lot_products as $item) {
            $item_total = $item['lot_product_qty'] * $item['lot_product_capital'];
            $total_value += $item_total;
            $total_quantity += $item['lot_product_qty'];
        }

        echo json_encode([
            'success' => true,
            'products' => $lot_products,
            'total_value' => $total_value,
            'total_quantity' => $total_quantity,
            'lot_status_id' => $lot_data['lot_status_id'] ?? LOT_STATUS_PENDING
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token']);
        exit();
    }

    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Handle product scan
        if (isset($_POST['barcode'])) {
            $barcode = trim($_POST['barcode']);
            if (empty($barcode)) {
                throw new Exception('รหัสบาร์โค้ดไม่สามารถว่างได้');
            }

            // Validate barcode
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

            if (!$product && empty($product_name) && !$isDeleteMode) {
                throw new Exception('กรุณาระบุชื่อสินค้าเมื่อเพิ่มสินค้าใหม่');
            }

            // Check existing product in lot
            $stmt = $pdo->prepare("SELECT lp.*, p.product_name FROM lot_product lp 
                                 LEFT JOIN product p ON p.product_id = lp.product_id 
                                 WHERE lp.lot_id = ? AND lp.product_id = ?");
            $stmt->execute([$lot_id, $barcode]);
            $existing_product = $stmt->fetch(PDO::FETCH_ASSOC);

            $pdo->beginTransaction();

            // Optimized stock check
            $sourceStockStmt = $pdo->prepare("
                SELECT SUM(lp.lot_product_remain) as total_remain
                FROM lot_product lp
                JOIN lot l ON lp.lot_id = l.lot_id
                WHERE lp.product_id = ? AND l.warehouse_from_id = (
                    SELECT warehouse_from_id FROM lot WHERE lot_id = ?
                )");
            $sourceStockStmt->execute([$barcode, $lot_id]);
            $sourceQty = (int)($sourceStockStmt->fetchColumn() ?? 0);

            $updated_product = null;

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
                        // Fetch updated product
                        $stmt = $pdo->prepare("SELECT lp.*, p.product_name, p.product_capital as default_capital,
                            COALESCE((
                                SELECT SUM(lp2.lot_product_remain)
                                FROM lot_product lp2
                                JOIN lot l ON lp2.lot_id = l.lot_id
                                WHERE lp2.product_id = lp.product_id
                                AND l.warehouse_from_id = (
                                    SELECT warehouse_from_id FROM lot WHERE lot_id = ?
                                )
                            ), 0) - lp.lot_product_qty as source_qty_remaining
                            FROM lot_product lp 
                            LEFT JOIN product p ON p.product_id = lp.product_id 
                            WHERE lp.lot_id = ? AND lp.product_id = ?");
                        $stmt->execute([$lot_id, $lot_id, $barcode]);
                        $updated_product = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                } else {
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
                    $update->execute([$newQty, $newQty, $captial, $lot_id, $barcode]);
                    // Fetch updated product
                    $stmt = $pdo->prepare("SELECT lp.*, p.product_name, p.product_capital as default_capital,
                        COALESCE((
                            SELECT SUM(lp2.lot_product_remain)
                            FROM lot_product lp2
                            JOIN lot l ON lp2.lot_id = l.lot_id
                            WHERE lp2.product_id = lp.product_id
                            AND l.warehouse_from_id = (
                                SELECT warehouse_from_id FROM lot WHERE lot_id = ?
                            )
                        ), 0) - lp.lot_product_qty as source_qty_remaining
                        FROM lot_product lp 
                        LEFT JOIN product p ON p.product_id = lp.product_id 
                        WHERE lp.lot_id = ? AND lp.product_id = ?");
                    $stmt->execute([$lot_id, $lot_id, $barcode]);
                    $updated_product = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } else {
                if ($isDeleteMode) {
                    throw new Exception("ไม่พบสินค้าที่มีรหัสบาร์โค้ด $barcode ในล็อตนี้");
                }

                if ($sourceQty < $quantity) {
                    throw new Exception("สินค้าในคลังต้นทางไม่เพียงพอ (มี $sourceQty คงเหลือ)");
                }

                // Insert new product
                if (!$product) {
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

                // Fetch new product
                $stmt = $pdo->prepare("SELECT lp.*, p.product_name, p.product_capital as default_capital,
                    COALESCE((
                        SELECT SUM(lp2.lot_product_remain)
                        FROM lot_product lp2
                        JOIN lot l ON lp2.lot_id = l.lot_id
                        WHERE lp2.product_id = lp.product_id
                        AND l.warehouse_from_id = (
                            SELECT warehouse_from_id FROM lot WHERE lot_id = ?
                        )
                    ), 0) - lp.lot_product_qty as source_qty_remaining
                    FROM lot_product lp 
                    LEFT JOIN product p ON p.product_id = lp.product_id 
                    WHERE lp.lot_id = ? AND lp.product_id = ?");
                $stmt->execute([$lot_id, $lot_id, $barcode]);
                $updated_product = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // Calculate totals
            $totalStmt = $pdo->prepare("
                SELECT SUM(lp.lot_product_qty * lp.lot_product_capital) as total_value,
                       SUM(lp.lot_product_qty) as total_quantity
                FROM lot_product lp
                WHERE lp.lot_id = ?");
            $totalStmt->execute([$lot_id]);
            $totals = $totalStmt->fetch(PDO::FETCH_ASSOC);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'product' => $updated_product,
                'total_value' => (float)($totals['total_value'] ?? 0),
                'total_quantity' => (int)($totals['total_quantity'] ?? 0)
            ]);
        }

        // Handle lot confirmation
        if (isset($_POST['confirm']) && $_POST['confirm'] === 'true') {
            $lot_id = isset($_POST['lot_id']) ? (int)$_POST['lot_id'] : null;
            if (!$lot_id) {
                throw new Exception('lot_id is missing');
            }

            $pdo->beginTransaction();

            // Update lot status to confirmed
            $stmt = $pdo->prepare("UPDATE lot SET lot_status_id = ? WHERE lot_id = ?");
            $stmt->execute([LOT_STATUS_CONFIRMED, $lot_id]);

            // Update stock in source and destination warehouses
            $stmt = $pdo->prepare("
            SELECT lp.product_id, lp.lot_product_qty
            FROM lot_product lp
            WHERE lp.lot_id = ?");
            $stmt->execute([$lot_id]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($products as $product) {
                $product_id = $product['product_id'];
                $quantity = $product['lot_product_qty'];

                // Deduct from source warehouse
                $sourceStmt = $pdo->prepare("
                UPDATE lot_product lp
                JOIN lot l ON lp.lot_id = l.lot_id
                SET lp.lot_product_remain = lp.lot_product_remain - ?
                WHERE lp.product_id = ? AND l.warehouse_from_id = (
                    SELECT warehouse_from_id FROM lot WHERE lot_id = ?
                ) AND lp.lot_product_remain >= ?");
                $sourceStmt->execute([$quantity, $product_id, $lot_id, $quantity]);

                // Add to destination warehouse
                $destStmt = $pdo->prepare("
                INSERT INTO lot_product (
                    lot_id, product_id, lot_product_qty, lot_product_remain,
                    lot_product_capital, lot_product_date, lot_product_status
                ) SELECT ?, product_id, ?, ?, lot_product_capital, NOW(), 1
                FROM lot_product WHERE lot_id = ? AND product_id = ?");
                $destStmt->execute([$lot_id, $quantity, $quantity, $lot_id, $product_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'ยืนยันการย้ายสินค้าเรียบร้อย']);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = "Error: {$e->getMessage()} | Barcode: " . ($barcode ?? 'N/A') . " | Lot ID: " . ($lot_id ?? 'N/A') . " | User: " . ($_SESSION['user_id'] ?? 'N/A');
        error_log($errorMessage);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = "Database error: {$e->getMessage()} | Barcode: " . ($barcode ?? 'N/A') . " | Lot ID: " . ($lot_id ?? 'N/A') . " | User: " . ($_SESSION['user_id'] ?? 'N/A');
        error_log($errorMessage);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'ข้อผิดพลาดของฐานข้อมูล: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch lot details
$lot_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$lot_id) {
    die("lot_id is missing");
}

try {
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
    $stmt = $pdo->prepare($query);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot_data) {
        die("ไม่พบข้อมูลล็อตสินค้า");
    }

    // Fetch products
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

    // Fetch warehouses
    $query = "SELECT warehouse_id, warehouse_name FROM warehouse ORDER BY warehouse_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: {$e->getMessage()} | Lot ID: $lot_id");
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด");
}

$lot_status_id = $lot_data['lot_status_id'] ?? null;
$lot_status_name = $lot_data['lot_status_name'] ?? null;


ob_end_flush();
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

        .loading {
            pointer-events: none;
            opacity: 0.7;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-8 mb-11">
        <h1 class="text-4xl font-bold mb-8">รายละเอียดการย้ายคลังสินค้า</h1>
        <?php if ($lot_data): ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="grid grid-cols-2 gap-6">
                    <div><label class="font-semibold mb-2">รหัส Lot สินค้า:</label><span><?php echo htmlspecialchars($lot_data['lot_id'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div><label class="font-semibold mb-2">ชื่อ Lot สินค้า:</label><span><?php echo htmlspecialchars($lot_data['lot_name'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div><label class="font-semibold mb-2">วันที่และเวลาย้ายคลังสินค้า:</label><span><?php echo htmlspecialchars($lot_data['lot_date'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div><label class="font-semibold mb-2">นำมาจากคลัง:</label><span><?php echo htmlspecialchars($lot_data['warehouse_from_name'] ?? 'ยังไม่ระบุ', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div><label class="font-semibold mb-2">ลงคลัง:</label><span><?php echo htmlspecialchars($lot_data['warehouse_name'], ENT_QUOTES, 'UTF-8'); ?></span></div>
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
                    <div><label class="font-semibold mb-2">สถานะ:</label><span><?php echo htmlspecialchars($lot_data['lot_status_name'], ENT_QUOTES, 'UTF-8'); ?></span></div>
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
                        <p><b>รูปแบบการป้อนข้อมูล:</b> จำนวน*ราคาทุน*รหัสบาร์โค้ด (เช่น 2*150*12345)</p>
                        <p>หรือจำนวน*รหัสบาร์โค้ด (เช่น 2*12345) หรือเฉพาะรหัสบาร์โค้ด (เช่น 12345)</p>
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
                            <th class="px-6 py-3 border text-center">จำนวนสินค้าคงเหลือในคลังหลังโอน</th>
                            <th class="px-3 py-3 border text-center">จำนวนที่ต้องการย้าย</th>
                            <th class="px-3 py-3 border text-right">ราคาทุน</th>
                            <th class="px-3 py-3 border text-right">มูลค่ารวม</th>
                            <?php if ($lot_status_id === LOT_STATUS_PENDING): ?>
                                <th class="px-3 py-3 border text-right">สถานะ</th>
                                <th class="px-3 py-3 border text-right">การจัดการ</th>
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
                            $product_id = htmlspecialchars($item['product_id'], ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="<?php echo $item['source_qty_remaining'] < 0 ? 'bg-red-100' : ''; ?>"
                                data-product-id="<?php echo $product_id; ?>">
                                <td class="pl-6 py-2 border"><?php echo $product_id; ?></td>
                                <td class="pl-6 py-2 border"><?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-center py-2 border"><?php echo htmlspecialchars($item['source_qty_remaining'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-center py-2 border"><?php echo htmlspecialchars($item['lot_product_qty'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-right py-2 border"><?php echo number_format($item['lot_product_capital'], 2); ?></td>
                                <td class="text-right py-2 border"><?php echo number_format($item_total, 2); ?></td>
                                <?php if ($lot_status_id === LOT_STATUS_PENDING): ?>
                                    <td class="text-right py-2 border"><?php echo htmlspecialchars($lot_data['lot_status_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-right py-2 border px-4">
                                        <button type="button" class="bg-blue-500 text-white px-4 py-1 rounded edit-product"
                                            data-id="<?php echo htmlspecialchars($item['lot_product_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-qty="<?php echo htmlspecialchars($item['lot_product_qty'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-capital="<?php echo htmlspecialchars($item['lot_product_capital'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-default-capital="<?php echo htmlspecialchars($item['default_capital'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-name="<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-barcode="<?php echo $product_id; ?>">
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
                            <tr class="bg-gradient-to-r from-gray-200 to-gray-300 text-gray-800 font-medium h-14 border-t-2 border-gray-400" id="total-row">
                                <td colspan="2" class="text-end pe-4 align-middle">ยอดรวมสินค้าที่ย้ายคลัง:</td>
                                <td class="text-center py-4 font-bold"><?php echo number_format($total_quantity); ?></td>
                                <td colspan="2" class="text-end pe-4 align-middle">มูลค่ารวมทั้งสิ้น:</td>
                                <td class="text-end py-4 font-bold" id="total-value"><?php echo number_format($total_value, 2); ?> บาท</td>
                                <?php if ($lot_status_id === LOT_STATUS_PENDING): ?>
                                    <td colspan="2"></td>
                                <?php endif; ?>
                            </tr>
                            <?php if ($lot_status_id === LOT_STATUS_PENDING): ?>
                                <tr class="h-16" id="confirm-row">
                                    <td colspan="<?php echo ($lot_status_id === LOT_STATUS_PENDING) ? 8 : 6; ?>" class="text-end pe-4 pt-4 overflow-hidden">
                                        <button type="button" id="confirm-lot" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow transition duration-200 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                                            <i class="fas fa-check-circle me-2"></i>ยืนยันการย้ายสินค้า
                                        </button>
                                        <input type="hidden" id="lot_id" value="<?php echo htmlspecialchars($lot_data['lot_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden" role="dialog" aria-labelledby="editModalTitle">
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
                    <label class="block mb-2" for="product_name">ชื่อสินค้า:</label>
                    <input type="text" class="w-full p-2 border rounded" id="product_name" name="product_name" required aria-describedby="name-help">
                </div>

                <div class="mb-4">
                    <label class="block mb-2" for="qty">จำนวนที่ต้องการย้าย:</label>
                    <input type="number" class="w-full p-2 border rounded" id="qty" name="qty" required min="1" aria-describedby="qty-help">
                </div>
                <div class="mb-4">
                    <label class="block mb-2" for="capital">ราคาทุน:</label>
                    <input type="number" class="w-full p-2 border rounded" id="capital" name="capital" required min="0" step="0.01" aria-describedby="capital-help">
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
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // State management
        const state = {
            isSubmitting: false,
            lastSubmissionTime: 0,
            activeDeleteBarcode: null,
            DEBOUNCE_DELAY: 500
        };

        // Utility functions
        const utils = {
            validateForm() {
                const productRows = $('#products-table tbody tr:not(.bg-gradient-to-r):not(.h-16)');
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
                productRows.each(function() {
                    const barcode = $(this).find('td:eq(0)').text().trim();
                    const productName = $(this).find('td:eq(1)').text().trim();
                    const sourceQtyRemaining = parseInt($(this).find('td:eq(2)').text()) || 0;
                    const transferQty = parseInt($(this).find('td:eq(3)').text()) || 0;
                    const capital = parseFloat($(this).find('td:eq(4)').text().replace(/,/g, '')) || 0;

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
            },
            handleServerResponse(response, successCallback) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: 'บันทึกข้อมูลเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        if (successCallback) successCallback(response);
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
            handleServerError(xhr) {
                const status = xhr.status;
                let errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์';

                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                } else if (status === 0) {
                    errorMessage = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต';
                } else if (status === 200) {
                    errorMessage = 'การตอบกลับจากเซิร์ฟเวอร์ไม่ใช่ JSON ที่ถูกต้อง';
                } else if (status === 400) {
                    errorMessage = 'คำขอไม่ถูกต้อง กรุณาตรวจสอบข้อมูลที่ป้อน';
                } else if (status === 403) {
                    errorMessage = 'ไม่มีสิทธิ์เข้าถึง หรือ CSRF token ไม่ถูกต้อง';
                } else if (status === 500) {
                    errorMessage = 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์ กรุณาลองใหม่ภายหลัง';
                }

                Swal.fire({
                    icon: 'error',
                    title: `ข้อผิดพลาด (รหัส: ${status})`,
                    text: errorMessage,
                    confirmButtonColor: '#3085d6'
                });

                console.error('AJAX Error:', {
                    status: status,
                    statusText: xhr.statusText,
                    response: xhr.responseText,
                    requestData: xhr
                });
            },
            sendRequest(data, successCallback) {
                if (state.isSubmitting || (Date.now() - state.lastSubmissionTime < state.DEBOUNCE_DELAY)) {
                    return;
                }
                state.isSubmitting = true;
                $('#inputField, #save-edit, #confirm-lot').addClass('loading');
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: data,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: response => {
                        state.isSubmitting = false;
                        $('#inputField, #save-edit, #confirm-lot').removeClass('loading');
                        state.lastSubmissionTime = Date.now();
                        utils.handleServerResponse(response, successCallback);
                    },
                    error: xhr => {
                        state.isSubmitting = false;
                        $('#inputField, #save-edit, #confirm-lot').removeClass('loading');
                        state.lastSubmissionTime = Date.now();
                        utils.handleServerError(xhr);
                    }
                });
            },
            validateFormFields(qty, capital, productName) {
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
            updateRowHighlighting() {
                $('#products-table tbody tr').removeClass('bg-pink-100');
                if (state.activeDeleteBarcode) {
                    $(`#products-table tbody tr[data-product-id="${state.activeDeleteBarcode}"]`).addClass('bg-pink-100');
                }
            },
            updateRow(product, totals) {
                const product_id = product.product_id;
                const item_total = product.lot_product_qty * product.lot_product_capital;
                const lot_status_id = <?php echo LOT_STATUS_PENDING; ?>;
                let rowHtml = `
                    <tr class="${product.source_qty_remaining < 0 ? 'bg-red-100' : ''}" data-product-id="${product_id}">
                        <td class="pl-6 py-2 border">${product_id}</td>
                        <td class="pl-6 py-2 border">${product.product_name}</td>
                        <td class="text-center py-2 border">${product.source_qty_remaining}</td>
                        <td class="text-center py-2 border">${product.lot_product_qty}</td>
                        <td class="text-right py-2 border">${parseFloat(product.lot_product_capital).toFixed(2)}</td>
                        <td class="text-right py-2 border">${item_total.toFixed(2)}</td>
                `;
                if (lot_status_id === <?php echo LOT_STATUS_PENDING; ?>) {
                    rowHtml += `
                        <td class="text-right py-2 border"><?php echo htmlspecialchars($lot_data['lot_status_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-right py-2 border px-4">
                            <button type="button" class="bg-blue-500 text-white px-4 py-1 rounded edit-product"
                                data-id="${product.lot_product_id}"
                                data-qty="${product.lot_product_qty}"
                                data-capital="${product.lot_product_capital}"
                                data-default-capital="${product.default_capital || 0}"
                                data-name="${product.product_name}"
                                data-barcode="${product_id}">
                                แก้ไข
                            </button>
                            <button type="button" class="bg-red-500 text-white px-4 py-1 rounded toggle-delete-mode"
                                data-barcode="${product_id}">
                                ลบ
                            </button>
                        </td>
                    `;
                }
                rowHtml += `</tr>`;

                // Check if row exists
                const $existingRow = $(`#products-table tbody tr[data-product-id="${product_id}"]`);
                if ($existingRow.length) {
                    // Update existing row
                    $existingRow.replaceWith(rowHtml);
                } else {
                    // Add new row at the top
                    $('#products-table tbody').prepend(rowHtml);
                }

                // Update totals
                if (totals) {
                    const total_quantity = totals.total_quantity;
                    const total_value = totals.total_value;
                    if (total_quantity > 0) {
                        let totalHtml = `
                            <tr class="bg-gradient-to-r from-gray-200 to-gray-300 text-gray-800 font-medium h-14 border-t-2 border-gray-400" id="total-row">
                                <td colspan="2" class="text-end pe-4 align-middle">ยอดรวมสินค้าที่ย้ายคลัง:</td>
                                <td class="text-center py-4 font-bold" id="total-quantity">${total_quantity.toLocaleString()}</td>
                                <td colspan="2" class="text-end pe-4 align-middle">มูลค่ารวมทั้งสิ้น:</td>
                                <td class="text-end py-4 font-bold" id="total-value">${total_value.toFixed(2)} บาท</td>
                                ${lot_status_id === <?php echo LOT_STATUS_PENDING; ?> ? '<td colspan="2"></td>' : ''}
                            </tr>
                            <tr class="h-16" id="confirm-row">
                                <td colspan="${lot_status_id === <?php echo LOT_STATUS_PENDING; ?> ? 8 : 6}" class="text-end pe-4 pt-4 overflow-hidden">
                                    <button type="button" id="confirm-lot" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow transition duration-200 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                                        <i class="fas fa-check-circle me-2"></i>ยืนยันการย้ายสินค้า
                                    </button>
                                    <input type="hidden" id="lot_id" value="<?php echo htmlspecialchars($lot_data['lot_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                        `;
                        if ($('#total-row').length) {
                            $('#total-row').replaceWith(totalHtml);
                        } else {
                            $('#products-table tbody').append(totalHtml);
                        }
                    } else {
                        // Remove total and confirm rows if no products
                        $('#total-row, #confirm-row').remove();
                    }
                }

                utils.updateRowHighlighting();
                $('#inputField').focus();
            },
            removeRow(product_id, totals) {
                $(`#products-table tbody tr[data-product-id="${product_id}"]`).remove();

                // Update totals
                if (totals) {
                    const total_quantity = totals.total_quantity;
                    const total_value = totals.total_value;
                    if (total_quantity > 0) {
                        let totalHtml = `
                            <tr class="bg-gradient-to-r from-gray-200 to-gray-300 text-gray-800 font-medium h-14 border-t-2 border-gray-400" id="total-row">
                                <td colspan="2" class="text-end pe-4 align-middle">ยอดรวมสินค้าที่ย้ายคลัง:</td>
                                <td class="text-center py-4 font-bold" id="total-quantity">${total_quantity.toLocaleString()}</td>
                                <td colspan="2" class="text-end pe-4 align-middle">มูลค่ารวมทั้งสิ้น:</td>
                                <td class="text-end py-4 font-bold" id="total-value">${total_value.toFixed(2)} บาท</td>
                                ${lot_status_id === <?php echo LOT_STATUS_PENDING; ?> ? '<td colspan="2"></td>' : ''}
                            </tr>
                            <tr class="h-16" id="confirm-row">
                                <td colspan="${lot_status_id === <?php echo LOT_STATUS_PENDING; ?> ? 8 : 6}" class="text-end pe-4 pt-4 overflow-hidden">
                                    <button type="button" id="confirm-lot" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow transition duration-200 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                                        <i class="fas fa-check-circle me-2"></i>ยืนยันการย้ายสินค้า
                                    </button>
                                    <input type="hidden" id="lot_id" value="<?php echo htmlspecialchars($lot_data['lot_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                        `;
                        if ($('#total-row').length) {
                            $('#total-row').replaceWith(totalHtml);
                        } else {
                            $('#products-table tbody').append(totalHtml);
                        }
                    } else {
                        $('#total-row, #confirm-row').remove();
                    }
                }

                utils.updateRowHighlighting();
                $('#inputField').focus();
            },
            refreshTable() {
                $.ajax({
                    url: `${window.location.pathname}?id=${$('#lot_id').val()}&action=fetch_table`,
                    method: 'GET',
                    dataType: 'json',
                    success: response => {
                        if (response.success) {
                            $('#products-table tbody').fadeOut(100, function() {
                                $(this).empty();

                                let total_value = response.total_value;
                                let total_quantity = response.total_quantity;
                                let lot_status_id = response.lot_status_id;
                                let html = '';

                                response.products.forEach(item => {
                                    let item_total = item.lot_product_qty * item.lot_product_capital;
                                    let product_id = item.product_id;
                                    html += `
                                        <tr class="${item.source_qty_remaining < 0 ? 'bg-red-100' : ''}" data-product-id="${product_id}">
                                            <td class="pl-6 py-2 border">${product_id}</td>
                                            <td class="pl-6 py-2 border">${item.product_name}</td>
                                            <td class="text-center py-2 border">${item.source_qty_remaining}</td>
                                            <td class="text-center py-2 border">${item.lot_product_qty}</td>
                                            <td class="text-right py-2 border">${parseFloat(item.lot_product_capital).toFixed(2)}</td>
                                            <td class="text-right py-2 border">${item_total.toFixed(2)}</td>
                                    `;
                                    if (lot_status_id === <?php echo LOT_STATUS_PENDING; ?>) {
                                        html += `
                                            <td class="text-right py-2 border"><?php echo htmlspecialchars($lot_data['lot_status_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-right py-2 border px-4">
                                                <button type="button" class="bg-blue-500 text-white px-4 py-1 rounded edit-product"
                                                    data-id="${item.lot_product_id}"
                                                    data-qty="${item.lot_product_qty}"
                                                    data-capital="${item.lot_product_capital}"
                                                    data-default-capital="${item.default_capital || 0}"
                                                    data-name="${item.product_name}"
                                                    data-barcode="${product_id}">
                                                    แก้ไข
                                                </button>
                                                <button type="button" class="bg-red-500 text-white px-4 py-1 rounded toggle-delete-mode"
                                                    data-barcode="${product_id}">
                                                    ลบ
                                                </button>
                                            </td>
                                        `;
                                    }
                                    html += `</tr>`;
                                });

                                if (response.products.length > 0) {
                                    html += `
                                        <tr class="bg-gradient-to-r from-gray-200 to-gray-300 text-gray-800 font-medium h-14 border-t-2 border-gray-400" id="total-row">
                                            <td colspan="2" class="text-end pe-4 align-middle">ยอดรวมสินค้าที่ย้ายคลัง:</td>
                                            <td class="text-center py-4 font-bold" id="total-quantity">${total_quantity.toLocaleString()}</td>
                                            <td colspan="2" class="text-end pe-4 align-middle">มูลค่ารวมทั้งสิ้น:</td>
                                            <td class="text-end py-4 font-bold" id="total-value">${total_value.toFixed(2)} บาท</td>
                                            ${lot_status_id === <?php echo LOT_STATUS_PENDING; ?> ? '<td colspan="2"></td>' : ''}
                                        </tr>
                                    `;
                                    if (lot_status_id === <?php echo LOT_STATUS_PENDING; ?>) {
                                        html += `
                                            <tr class="h-16" id="confirm-row">
                                                <td colspan="${lot_status_id === <?php echo LOT_STATUS_PENDING; ?> ? 8 : 6}" class="text-end pe-4 pt-4 overflow-hidden">
                                                    <button type="button" id="confirm-lot" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow transition duration-200 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                                                        <i class="fas fa-check-circle me-2"></i>ยืนยันการย้ายสินค้า
                                                    </button>
                                                    <input type="hidden" id="lot_id" value="<?php echo htmlspecialchars($lot_data['lot_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                </td>
                                            </tr>
                                        `;
                                    }
                                }

                                $(this).html(html).fadeIn(100);
                                utils.updateRowHighlighting();
                                $('#inputField').focus();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: response.error || 'ไม่สามารถโหลดข้อมูลตารางได้',
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    },
                    error: () => {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                });
            }
        };

        // Event handlers
        const handlers = {
            toggleDeleteMode(e) {
                const barcode = $(e.target).data('barcode');
                const isActive = state.activeDeleteBarcode === barcode;
                $('.toggle-delete-mode').removeClass('bg-pink-500').addClass('bg-red-500').text('ลบ');
                if (!isActive) {
                    $(e.target).removeClass('bg-red-500').addClass('bg-pink-500').text('ยกเลิกลบ');
                    $('#inputField').addClass('bg-pink-100');
                    state.activeDeleteBarcode = barcode;
                } else {
                    $('#inputField').removeClass('bg-pink-100');
                    state.activeDeleteBarcode = null;
                }
                utils.updateRowHighlighting();
                $('#inputField').focus();
            },
            handleBarcodeInput(e) {
                if (e.which !== 13) return; // ทำงานเมื่อกด Enter
                e.preventDefault();
                if (state.isSubmitting) return; // ป้องกันการส่งซ้ำ
                const $input = $('#inputField');
                const inputValue = $input.val().trim();
                if (!inputValue) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'รหัสบาร์โค้ดไม่ถูกต้อง',
                        text: 'กรุณาป้อนรหัสบาร์โค้ด',
                        confirmButtonColor: '#3085d6'
                    });
                    $input.focus();
                    return;
                }

                // ตรวจสอบรูปแบบ: 12345, 2*12345, 2*150*12345, -12345, -2*12345
                if (!/^-?([0-9]+(\*[0-9]+(\.[0-9]+)?)?(\*[0-9]+)?|[0-9]+)$/.test(inputValue)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'รูปแบบข้อมูลไม่ถูกต้อง',
                        text: 'กรุณาป้อนในรูปแบบ: 12345, 2*12345, 2*150*12345, -12345, หรือ -2*12345',
                        confirmButtonColor: '#3085d6'
                    });
                    $input.focus();
                    return;
                }

                const parts = inputValue.split('*');
                let isDeleteMode = inputValue.startsWith('-');
                let qty = 1; // ค่าเริ่มต้นจำนวน
                let capital = null; // ค่าเริ่มต้นราคาทุน
                let product_id;

                if (isDeleteMode) {
                    parts[0] = parts[0].substring(1); // ลบเครื่องหมาย -
                }

                // กำหนดส่วนประกอบตามจำนวน parts
                if (parts.length === 1) {
                    // รูปแบบ: 12345 หรือ -12345
                    product_id = parts[0];
                } else if (parts.length === 2) {
                    // รูปแบบ: 2*12345 หรือ -2*12345
                    qty = parseInt(parts[0]);
                    product_id = parts[1];
                } else if (parts.length === 3) {
                    // รูปแบบ: 2*150*12345
                    qty = parseInt(parts[0]);
                    capital = parseFloat(parts[1]);
                    product_id = parts[2];
                }

                if (!product_id || !/^[0-9]+$/.test(product_id)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'รหัสบาร์โค้ดไม่ถูกต้อง',
                        text: 'รหัสบาร์โค้ดต้องเป็นตัวเลขเท่านั้น',
                        confirmButtonColor: '#3085d6'
                    });
                    $input.focus();
                    return;
                }

                if (qty <= 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'จำนวนไม่ถูกต้อง',
                        text: 'จำนวนต้องมากกว่า 0',
                        confirmButtonColor: '#3085d6'
                    });
                    $input.focus();
                    return;
                }

                if (capital !== null && capital < 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ราคาทุนไม่ถูกต้อง',
                        text: 'ราคาทุนต้องไม่ติดลบ',
                        confirmButtonColor: '#3085d6'
                    });
                    $input.focus();
                    return;
                }

                let requestData = {
                    csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>',
                    barcode: product_id,
                    deleteMode: isDeleteMode
                };

                if (qty !== 1) {
                    requestData.qty = qty;
                }
                if (capital !== null) {
                    requestData.capital = capital;
                }

                utils.sendRequest(requestData, (response) => {
                    if (isDeleteMode && response.product) {
                        // Update existing row if not deleted
                        utils.updateRow(response.product, response);
                    } else if (isDeleteMode) {
                        // Remove row if deleted
                        utils.removeRow(product_id, response);
                    } else {
                        // Add or update row
                        utils.updateRow(response.product, response);
                    }
                    if (isDeleteMode && state.activeDeleteBarcode) {
                        state.activeDeleteBarcode = null;
                        $('.toggle-delete-mode').removeClass('bg-pink-500').addClass('bg-red-500').text('ลบ');
                        $input.removeClass('bg-pink-100');
                        utils.updateRowHighlighting();
                    }
                    $input.val(''); // Clear input
                });
            } // ต่อจากส่วน JavaScript ใน lot_insertdetailout.php

            ,
            handleEditProduct(e) {
                const $button = $(e.target);
                $('#lot_product_id').val($button.data('id'));
                $('#product_barcode').val($button.data('barcode'));
                $('#product_barcode_hidden').val($button.data('barcode'));
                $('#product_name').val($button.data('name'));
                $('#qty').val($button.data('qty'));
                $('#capital').val($button.data('capital'));
                document.getElementById('editModal').classList.remove('hidden');
                $('#product_name').focus();
            },
            handleSaveEdit() {
                const qty = parseInt($('#qty').val());
                const capital = parseFloat($('#capital').val());
                const productName = $('#product_name').val().trim();
                const barcode = $('#product_barcode_hidden').val();

                if (!utils.validateFormFields(qty, capital, productName)) {
                    return;
                }

                const requestData = {
                    csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>',
                    barcode: barcode,
                    qty: qty,
                    capital: capital,
                    product_name: productName
                };

                utils.sendRequest(requestData, (response) => {
                    utils.updateRow(response.product, response);
                    closeEditModal();
                });
            },
            handleConfirmLot() {
                if (!utils.validateForm()) {
                    return;
                }

                // Disable the confirm button to prevent multiple submissions
                const $confirmButton = $('#confirm-lot');
                if ($confirmButton.hasClass('disabled')) {
                    return;
                }
                $confirmButton.addClass('disabled loading').prop('disabled', true);

                Swal.fire({
                    title: 'ยืนยันการย้ายสินค้า?',
                    text: 'เมื่อยืนยันแล้วจะไม่สามารถแก้ไขข้อมูลได้',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'ยืนยัน',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const requestData = {
                            csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>',
                            confirm: 'true',
                            lot_id: $('#lot_id').val()
                        };

                        utils.sendRequest(requestData, () => {
                            Swal.fire({
                                icon: 'success',
                                title: 'ยืนยันสำเร็จ',
                                text: 'การย้ายสินค้าได้ดำเนินการเรียบร้อยแล้ว',
                                confirmButtonColor: '#3085d6'
                            }).then(() => {
                                window.location.href = 'index.php?page=lot_insertout';
                            });
                        }, () => {
                            // Re-enable the button if the request fails
                            $confirmButton.removeClass('disabled loading').prop('disabled', false);
                        });
                    } else {
                        // Re-enable the button if the user cancels
                        $confirmButton.removeClass('disabled loading').prop('disabled', false);
                    }
                });
            }
        };

        // Initialize event listeners
        $(document).ready(function() {
            $('#inputField').on('keypress', handlers.handleBarcodeInput);
            $(document).on('click', '.edit-product', handlers.handleEditProduct);
            $(document).on('click', '.toggle-delete-mode', handlers.toggleDeleteMode);
            $('#save-edit').on('click', handlers.handleSaveEdit);
            $(document).on('click', '#confirm-lot', handlers.handleConfirmLot);

            // Auto-focus input field
            $('#inputField').focus();

            // Prevent form submission on enter in edit modal
            $('#edit-form').on('submit', function(e) {
                e.preventDefault();
                handlers.handleSaveEdit();
            });

            // Validate input field for barcode
            $('#inputField').on('input', function() {
                let value = $(this).val();
                // Allow only numbers, *, and -
                value = value.replace(/[^0-9*\-]/g, '');
                $(this).val(value);
            });

            // Update table on page load
            utils.refreshTable();
        });
    </script>
</body>

</html>