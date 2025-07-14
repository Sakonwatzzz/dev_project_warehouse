<?php
ob_start();
require_once __DIR__ . '/../../../../config/database_server.php';
require_once __DIR__ . '/../../../../functions/adjustLotRemain.php';
$pdo = Database::getInstance()->getConnection(); 
function isAjaxRequest() {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    error_log("isAjaxRequest: " . ($isAjax ? 'Yes' : 'No') . ", Headers: " . json_encode(getallheaders()));
    return $isAjax;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'delete' || !isAjaxRequest()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Access denied: AJAX request required']);
    ob_end_flush();
    exit();
}

header('Content-Type: application/json; charset=utf-8');
ob_clean();

try {
    $barcode = trim($_POST['barcode'] ?? '');
    $qty = (int) ($_POST['qty'] ?? 1);
    $sale_id = $_POST['sale_id'] ?? null;

    error_log("Delete request: sale_id=$sale_id, barcode=$barcode, qty=$qty");

    if (empty($sale_id)) {
        throw new Exception("ไม่พบ sale_id");
    }
    if (empty($barcode)) {
        throw new Exception("ไม่พบรหัสสินค้า");
    }
    if ($qty <= 0) {
        throw new Exception("จำนวนต้องมากกว่า 0");
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT sale_product_qty  
        FROM sale_product 
        WHERE sale_id = ? AND product_id = ?
    ");
    $stmt->execute([$sale_id, $barcode]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Query result for sale_id=$sale_id, barcode=$barcode: " . json_encode($existing));

    if ($existing) {
        $newQty = $existing['sale_product_qty'] - $qty;
        if ($newQty <= 0) {
            $stmt = $pdo->prepare("
                DELETE FROM sale_product 
                WHERE sale_id = ? AND product_id = ?
            ");
            $stmt->execute([$sale_id, $barcode]);
            error_log("Deleted product from sale_product: sale_id=$sale_id, barcode=$barcode");
        } else {
            $stmt = $pdo->prepare("
                UPDATE sale_product 
                SET sale_product_qty = ? 
                WHERE sale_id = ? AND product_id = ?
            ");
            $stmt->execute([$newQty, $sale_id, $barcode]);
            error_log("Updated product qty to $newQty: sale_id=$sale_id, barcode=$barcode");
        }
        adjustLotRemain($pdo, $barcode, $qty, true);
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'ลบสินค้าสำเร็จ']);
    } else {
        throw new Exception("ไม่พบสินค้าในรายการสำหรับ sale_id=$sale_id, barcode=$barcode");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error deleting product: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
ob_end_flush();
exit();
?>