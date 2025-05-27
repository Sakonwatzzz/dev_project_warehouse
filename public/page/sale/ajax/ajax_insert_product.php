<?php

// Start output buffering
ob_start();

// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Disable display errors to prevent output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Log start of script
error_log("sale_add.php (add_product) started at " . date('Y-m-d H:i:s'));

// Start session
try {
    session_start();
} catch (Exception $e) {
    error_log("Session start failed: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่สามารถเริ่มเซสชันได้'
    ]);
    ob_end_flush();
    exit();
}

// Include database configuration
try {
    require_once __DIR__ . '/../../../../config/database_server.php';
    error_log("Database configuration included successfully");
} catch (Exception $e) {
    error_log("Error including database_server.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit();
}


$sale_id = $_POST['sale_id'] ?? null;
if (empty($sale_id)) {
    throw new Exception("กรุณาระบุ sale_id");
}
function adjustLotRemain(PDO $pdo, string $productId, int $qty, bool $addMode = false)
{
    $remainingToProcess = $qty;
    $lot_ids = [];
    try {
        while ($remainingToProcess > 0) {
            $query = "
                SELECT lot_product_id, lot_product_remain 
                FROM lot_product 
                WHERE product_id = ? AND lot_product_remain > 0
                ORDER BY lot_product_date ASC, lot_product_id ASC LIMIT 1
            ";
            $stmtLot = $pdo->prepare($query);
            $stmtLot->execute([$productId]);
            $lot = $stmtLot->fetch(PDO::FETCH_ASSOC);

            if (!$lot) {
                throw new Exception("สต็อกล็อตไม่เพียงพอสำหรับสินค้า $productId");
            }

            $current = (int) $lot['lot_product_remain'];
            if ($addMode) {
                $newRemain = $current + $remainingToProcess;
                $remainingToProcess = 0;
            } else {
                if ($current >= $remainingToProcess) {
                    $newRemain = $current - $remainingToProcess;
                    $lot_ids[] = ['lot_product_id' => $lot['lot_product_id'], 'qty' => $remainingToProcess];
                    $remainingToProcess = 0;
                } else {
                    $newRemain = 0;
                    $lot_ids[] = ['lot_product_id' => $lot['lot_product_id'], 'qty' => $current];
                    $remainingToProcess -= $current;
                }
            }

            $updateLot = $pdo->prepare("
                UPDATE lot_product 
                SET lot_product_remain = ? 
                WHERE lot_product_id = ?
            ");
            $updateLot->execute([$newRemain, $lot['lot_product_id']]);
        }
    } catch (Exception $e) {
        error_log("Error in adjustLotRemain for product $productId: " . $e->getMessage());
        throw $e;
    }
    return $lot_ids;
}
function isAjaxRequest()
{
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    error_log("isAjaxRequest: " . ($isAjax ? 'Yes' : 'No') . ", Headers: " . json_encode(getallheaders()));
    return $isAjax;
}
// Handle add product via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {

    if (!isAjaxRequest()) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'Access denied: AJAX request required']);
        exit();
    }
    header('Content-Type: application/json');
    ob_clean();
    try {
        $barcode = trim($_POST['barcode']);
        $qty = is_numeric($_POST['qty'] ?? null) ? (int) $_POST['qty'] : 1;
        $price = is_numeric($_POST['price'] ?? null) ? (float) $_POST['price'] : 0;
        $price_type = $_POST['price_type'] ?? 'price4';

        if (empty($barcode)) {
            throw new Exception("กรุณาป้อนรหัสสินค้า");
        }
        if ($qty <= 0) {
            throw new Exception("จำนวนต้องมากกว่า 0");
        }

        $pdo->beginTransaction();

        // ตรวจสอบสินค้า
        $stmtP = $pdo->prepare("
            SELECT product_name, product_price1, product_price2, product_price3, product_price4, product_capital 
            FROM product 
            WHERE product_id = ?
        ");
        $stmtP->execute([$barcode]);
        $prod = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new Exception("รหัสสินค้า $barcode ไม่พบในระบบ");
        }

        // เลือกราคา
        $unitPrice = $price > 0 ? $price : $prod['product_' . $price_type];
        $unitCapital = $prod['product_capital'];

        // ตรวจสอบว่ามีสินค้านี้ในบิลแล้วหรือไม่
        $stmt = $pdo->prepare("
            SELECT sale_product_qty, sale_product_price, sale_product_capital 
            FROM sale_product 
            WHERE sale_id = ? AND product_id = ?
        ");
        $stmt->execute([$sale_id, $barcode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = $existing['sale_product_qty'] + $qty;
            $newPrice = $price > 0 ? $price : $existing['sale_product_price'];
            $newCapital = $prod['product_capital'];

            $stmt = $pdo->prepare("
                UPDATE sale_product 
                SET sale_product_qty = ?, sale_product_price = ?, sale_product_capital = ?
                WHERE sale_id = ? AND product_id = ?
            ");
            $stmt->execute([$newQty, $newPrice, $newCapital, $sale_id, $barcode]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO sale_product 
                (sale_id, product_id, sale_product_qty, sale_product_price, sale_product_capital)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$sale_id, $barcode, $qty, $unitPrice, $unitCapital]);
        }

        // ตัดสต็อก
        try {
            $lot_ids = adjustLotRemain($pdo, $barcode, $qty, false);
        } catch (Exception $e) {
            throw new Exception("ไม่สามารถตัดสต็อกได้: " . $e->getMessage());
        }

        $pdo->commit();
        // คำนวณ sale_net_amount ใหม่หลังเพิ่มสินค้า
        $stmt = $pdo->prepare("
    SELECT SUM(sale_product_qty * sale_product_price) as sale_net_amount
    FROM sale_product 
    WHERE sale_id = ?
");
        $stmt->execute([$sale_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sale_net_amount = (float)($result['sale_net_amount'] ?? 0);

        echo json_encode(['status' => 'success', 'message' => 'เพิ่มสินค้าสำเร็จ', 'sale_net_amount' => $sale_net_amount]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error adding product: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
} else {
    // Handle non-add_product requests (not included here)
    echo json_encode([
        'status' => 'error',
        'message' => 'คำขอไม่ถูกต้อง'
    ]);
}

// Flush output and exit
ob_end_flush();
exit();
