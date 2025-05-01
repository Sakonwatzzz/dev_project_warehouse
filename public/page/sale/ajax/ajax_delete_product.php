<?php
function isAjaxRequest()
{
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    error_log("isAjaxRequest: " . ($isAjax ? 'Yes' : 'No') . ", Headers: " . json_encode(getallheaders()));
    return $isAjax;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'delete' || !isAjaxRequest()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Access denied: AJAX request required']);
    exit();
}
require_once __DIR__ . '/../../../../config/database_server.php';
require_once __DIR__ . '/../../../../functions/adjustLotRemain.php';

header('Content-Type: application/json; charset=utf-8');
ob_clean();
try {
    $barcode = trim($_POST['barcode']);
    $qty = (int) ($_POST['qty'] ?? 1);
    $sale_id = $_POST['sale_id'] ?? null;

    if (empty($sale_id)) {
        throw new Exception("ไม่พบ sale_id");
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT sale_product_qty  
        FROM sale_product 
        WHERE sale_id = ? AND product_id = ?
    ");
    $stmt->execute([$sale_id, $barcode]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $newQty = $existing['sale_product_qty'] - $qty;
        if ($newQty <= 0) {
            $pdo->prepare("
                DELETE FROM sale_product 
                WHERE sale_id = ? AND product_id = ?
            ")->execute([$sale_id, $barcode]);
        } else {
            $pdo->prepare("
                UPDATE sale_product 
                SET sale_product_qty = ? 
                WHERE sale_id = ? AND product_id = ?
            ")->execute([$newQty, $sale_id, $barcode]);
        }
        adjustLotRemain($pdo, $barcode, $qty, true);
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'ลบสินค้าสำเร็จ']);
    } else {
        throw new Exception("ไม่พบสินค้าในรายการ");
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