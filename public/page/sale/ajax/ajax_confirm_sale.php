<?php
ob_start();
session_start();
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
$pdo = Database::getInstance()->getConnection();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_sale') {
    header('Content-Type: application/json');
    ob_clean();
    try {
        $pdo->beginTransaction();

        // ตรวจสอบ CSRF token
        // $csrf_token = $_POST['csrf_token'] ?? '';
        // if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        //     throw new Exception("Invalid CSRF token");
        // }

        // รับข้อมูล
        $sale_id = $_POST['sale_id'] ?? null;
        $member_id = $_POST['member_id'] ?? null;
        if ($member_id === '' || $member_id === null) {
            $member_id = null;
        }
        $sale_net_amount = isset($_POST['sale_net_amount']) ? (float) $_POST['sale_net_amount'] :
        $sale_vat_amount = isset($_POST['sale_vat_amount']) ? (float) $_POST['sale_vat_amount'] :
        $sale_total_amount = isset($_POST['sale_total_amount']) ? (float) $_POST['sale_total_amount'] :
        $sale_paid_amount = isset($_POST['sale_paid_amount']) ? (float) $_POST['sale_paid_amount'] :
        $sale_change_amount = isset($_POST['sale_change_amount']) ? (float) $_POST['sale_change_amount'] :

            error_log("Confirm sale: sale_id = " . ($sale_id ?? 'null'));

        if (empty($sale_id)) {
            throw new Exception("ไม่พบ sale_id ในคำขอ กรุณาเปิดบิลและเพิ่มข้อมูลการขาย");
        }

        // ตรวจสอบสถานะบิล
        $check_sale = $pdo->prepare("SELECT sale_status_id FROM sale WHERE sale_id = ?");
        $check_sale->execute([$sale_id]);
        $sale = $check_sale->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            throw new Exception("ไม่พบข้อมูลบิล #$sale_id ในระบบ");
        }
        if ($sale['sale_status_id'] != 2) {
            throw new Exception("บิล #$sale_id ไม่สามารถยืนยันได้ เนื่องจากไม่ได้อยู่ในสถานะรอดำเนินการ");
        }

        // ตรวจสอบสินค้า
        $check_products = $pdo->prepare("SELECT COUNT(*) FROM sale_product WHERE sale_id = ?");
        $check_products->execute([$sale_id]);
        if ($check_products->fetchColumn() == 0) {
            throw new Exception("ไม่พบสินค้าในรายการขาย กรุณาเพิ่มสินค้าก่อนยืนยันการขาย");
        }

        // ตรวจสอบความสมบูรณ์ของข้อมูลสินค้า
        $check_query = "SELECT sp.product_id, COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name, 
                       sp.sale_product_price, sp.sale_product_capital 
                       FROM sale_product sp
                       LEFT JOIN product p ON p.product_id = sp.product_id
                       WHERE sp.sale_id = ? 
                       AND (p.product_name IS NULL OR p.product_name = 'ยังไม่ระบุ' OR sp.sale_product_price = 0 OR sp.sale_product_capital = 0)";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$sale_id]);
        $invalid_products = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($invalid_products) > 0) {
            $invalid_list = '';
            foreach ($invalid_products as $product) {
                $invalid_list .= "- รหัส: {$product['product_id']} ชื่อ: {$product['product_name']}\n";
            }
            throw new Exception("พบสินค้าที่ข้อมูลไม่ครบถ้วน:\n" . $invalid_list);
        }

        // ตรวจสอบการชำระเงิน
        if ($sale_paid_amount < $sale_total_amount) {
            throw new Exception("จำนวนเงินที่ชำระไม่เพียงพอ");
        }

        // อัปเดตบิล
        $updateSale = $pdo->prepare("
            UPDATE sale 
            SET sale_status_id = 1, member_id = ?, sale_net_amount = ?, sale_vat_amount = ?, 
                sale_total_amount = ?, sale_paid_amount = ?, sale_change_amount = ? 
            WHERE sale_id = ?
        ");
        $updateSale->execute([
            $member_id,
            $sale_net_amount,
            $sale_vat_amount,
            $sale_total_amount,
            $sale_paid_amount,
            $sale_change_amount,
            $sale_id
        ]);

        // ดึงสถานะล่าสุด
        $status_query = "SELECT ss.sale_status_name
                        FROM sale s
                        JOIN sale_status ss ON s.sale_status_id = ss.sale_status_id
                        WHERE s.sale_id = ?";
        $status_stmt = $pdo->prepare($status_query);
        $status_stmt->execute([$sale_id]);
        $sale_status = $status_stmt->fetch(PDO::FETCH_ASSOC);
        $sale_status_name = $sale_status ? $sale_status['sale_status_name'] : 'ไม่ทราบสถานะ';

        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'ยืนยันการขายสำเร็จ',
            'sale_status_name' => $sale_status_name
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error confirming sale: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
