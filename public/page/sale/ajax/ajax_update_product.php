<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../functions/adjustLotRemain.php';
header('Content-Type: application/json');
$pdo = Database::getInstance()->getConnection(); 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    ob_clean();
    try {
        $sale_id = $_POST['sale_id'] ?? null;
        if (!$sale_id) {
            throw new Exception("ไม่พบ sale_id ในคำขอ");
        }
        $barcode = trim($_POST['barcode'] ?? '');
        if (!$barcode) {
            throw new Exception("ไม่พบ barcode ในคำขอ");
        }
        $qty = (int) ($_POST['qty'] ?? 1);
        $price = (float) ($_POST['price'] ?? 0);
        $capital = (float) ($_POST['capital'] ?? 0);

        // ดีบัก
        error_log("ajax_update_product: sale_id=$sale_id, barcode=$barcode, qty=$qty, price=$price, capital=$capital");

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
                  SELECT sale_product_qty, sale_product_price, sale_product_capital 
                  FROM sale_product 
                  WHERE sale_id = ? AND product_id = ?
              ");
        $stmt->execute([$sale_id, $barcode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $oldQty = $existing['sale_product_qty'];
            $qtyDiff = $qty - $oldQty;

            $pdo->prepare("
                      UPDATE sale_product 
                      SET sale_product_qty = ?, sale_product_price = ?, sale_product_capital = ? 
                      WHERE sale_id = ? AND product_id = ?
                  ")->execute([$qty, $price, $capital, $sale_id, $barcode]);

            if ($qtyDiff > 0) {
                adjustLotRemain($pdo, $barcode, $qtyDiff, false);
            } elseif ($qtyDiff < 0) {
                adjustLotRemain($pdo, $barcode, abs($qtyDiff), true);
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'แก้ไขสินค้าสำเร็จ']);
        } else {
            throw new Exception("ไม่พบสินค้าในรายการสำหรับ sale_id=$sale_id และ barcode=$barcode");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating product: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// กรณี request ไม่ถูกต้อง
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
exit();
?>