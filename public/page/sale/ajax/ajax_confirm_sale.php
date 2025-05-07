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
try {
    require_once __DIR__ . '/../../../../functions/adjustLotRemain.php';
} catch (Exception $e) {
    error_log("Error" . $e->getMessage());
}

function isAjaxRequest()
{
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    error_log("isAjaxRequest: " . ($isAjax ? 'Yes' : 'No') . ", Headers: " . json_encode(getallheaders()));
    return $isAjax;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_sale') {
    header('Content-Type: application/json');
    ob_clean();
    try {
        $pdo->beginTransaction();

        // ตรวจสอบ sale_id
        $sale_id = $_POST['sale_id'] ?? null;
        error_log("Confirm sale: sale_id = " . ($sale_id ?? 'null')); // บันทึก sale_id
        if (empty($sale_id)) {
            throw new Exception("ไม่พบ sale_id ในคำขอ กรุณาเปิดบิลและเพิ่มข้อมูลการขาย");
        }

        // ตรวจสอบว่าบิลมีอยู่ในฐานข้อมูลและอยู่ในสถานะรอดำเนินการ
        $check_sale = $pdo->prepare("SELECT sale_status_id FROM sale WHERE sale_id = ?");
        $check_sale->execute([$sale_id]);
        $sale = $check_sale->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            throw new Exception("ไม่พบข้อมูลบิล #$sale_id ในระบบ");
        }
        if ($sale['sale_status_id'] != 2) {
            throw new Exception("บิล #$sale_id ไม่สามารถยืนยันได้ เนื่องจากไม่ได้อยู่ในสถานะรอดำเนินการ");
        }

        // ตรวจสอบว่ามีสินค้าในบิล
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

        // อัปเดตสถานะบิลเป็นยืนยันแล้ว (sale_status_id = 1 / sale_status_name = 'ขายปกติ')
        $updateSale = $pdo->prepare("UPDATE sale SET sale_status_id = 1 WHERE sale_id = ?");
        $updateSale->execute([$sale_id]);

        // อัปเดต member_id หากระบุ
        if (isset($_POST['member_id']) && !empty($_POST['member_id'])) {
            $updateMember = $pdo->prepare("UPDATE sale SET member_id = ? WHERE sale_id = ?");
            $updateMember->execute([$_POST['member_id'], $sale_id]);
        }

        // อัปเดต personnel_id หากระบุ
        if (isset($_POST['personnel_id']) && !empty($_POST['personnel_id'])) {
            $updatePersonnel = $pdo->prepare("UPDATE sale SET personnel_id = ? WHERE sale_id = ?");
            $updatePersonnel->execute([$_POST['personnel_id'], $sale_id]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'ยืนยันการขายสำเร็จ']);
        // echo json_encode(['status' => 'success', 'message' => 'ยืนยันการขายสำเร็จ', 'redirect' => '?page=sale_add']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error confirming sale: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
// Remove from active sales // ไม่ลบ sale_id ออกจาก active_sales เพื่อให้บิลยังคงอยู่ในแท็บ
// $_SESSION['active_sales'] = array_diff($_SESSION['active_sales'], [$sale_id]);
// $_SESSION['current_sale_id'] = !empty($_SESSION['active_sales']) ? reset($_SESSION['active_sales']) : null;

?>