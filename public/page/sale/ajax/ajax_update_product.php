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
        $qty = isset($_POST['qty']) && is_numeric($_POST['qty']) && (int)$_POST['qty'] > 0 ? (int)$_POST['qty'] : null;
        if ($qty === null) {
            throw new Exception("จำนวนสินค้าไม่ถูกต้อง ต้องมากกว่า 0");
        }
        $price = isset($_POST['price']) && is_numeric($_POST['price']) && (float)$_POST['price'] >= 0 ? (float)$_POST['price'] : null;
        if ($price === null) {
            throw new Exception("ราคาขายไม่ถูกต้อง ต้องมากกว่าหรือเท่ากับ 0");
        }
        $capital = isset($_POST['capital']) && is_numeric($_POST['capital']) && (float)$_POST['capital'] >= 0 ? (float)$_POST['capital'] : null;
        if ($capital === null) {
            throw new Exception("ต้นทุนไม่ถูกต้อง ต้องมากกว่าหรือเท่ากับ 0");
        }

        // ดีบักข้อมูลดิบ
        error_log("ajax_update_product: RAW_POST=" . json_encode($_POST));
        error_log("ajax_update_product: sale_id=$sale_id, barcode=$barcode, qty=$qty, price=$price, capital=$capital");
        if ($qty === 1 || $price === 1.0) {
            error_log("Warning: Suspicious value 1 detected in ajax_update_product: qty=$qty, price=$price");
        }

        $pdo->beginTransaction();

        // ตรวจสอบสินค้าที่มีอยู่
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

            // อัปเดต sale_product
            $stmt = $pdo->prepare("
                UPDATE sale_product 
                SET sale_product_qty = ?, sale_product_price = ?, sale_product_capital = ? 
                WHERE sale_id = ? AND product_id = ?
            ");
            $stmt->execute([$qty, $price, $capital, $sale_id, $barcode]);

            // ปรับจำนวนคงเหลือ
            if ($qtyDiff > 0) {
                adjustLotRemain($pdo, $barcode, $qtyDiff, false);
            } elseif ($qtyDiff < 0) {
                adjustLotRemain($pdo, $barcode, abs($qtyDiff), true);
            }

            // คำนวณ sale_net_amount ใหม่
            $stmt = $pdo->prepare("
                SELECT SUM(sale_product_qty * sale_product_price) as calculated_net
                FROM sale_product
                WHERE sale_id = ?
            ");
            $stmt->execute([$sale_id]);
            $calculated_net = (float)($stmt->fetchColumn() ?: 0);
            $calculated_net_rounded = round($calculated_net, 2);
            $calculated_vat = round($calculated_net_rounded * 0.07, 2);
            $calculated_total = round($calculated_net_rounded + $calculated_vat, 2);

            if ($calculated_net_rounded === 1.0 || $calculated_vat === 1.0 || $calculated_total === 1.0) {
                error_log("Warning: Suspicious value 1.00 detected in calculated amounts: net=$calculated_net_rounded, vat=$calculated_vat, total=$calculated_total");
            }

            // อัปเดตตาราง sale
            $stmt = $pdo->prepare("
                UPDATE sale 
                SET sale_net_amount = ?, sale_vat_amount = ?, sale_total_amount = ?
                WHERE sale_id = ?
            ");
            $stmt->execute([$calculated_net_rounded, $calculated_vat, $calculated_total, $sale_id]);

            $pdo->commit();
            echo json_encode([
                'status' => 'success',
                'message' => 'แก้ไขสินค้าสำเร็จ',
                'sale_net_amount' => $calculated_net_rounded,
                'sale_vat_amount' => $calculated_vat,
                'sale_total_amount' => $calculated_total
            ]);
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

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
exit();