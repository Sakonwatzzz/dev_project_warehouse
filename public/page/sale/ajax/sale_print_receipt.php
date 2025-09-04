<?php
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'print_receipt') {
    header('Content-Type: application/json');
    try {
        $sale_id = $_POST['sale_id'] ?? null;
        if (!$sale_id) {
            throw new Exception("ไม่พบ sale_id ในคำขอ กรุณาเปิดบิลใหม่และเพิ่มข้อมูลการขาย");
        }

        // ดึงข้อมูลการขายจาก DB รวมยอดด้วย
        $query = "SELECT s.sale_id, s.sale_date, s.member_id, m.member_name, m.member_phone,
                         s.personnel_id, p.personnel_name, s.sale_status_id, ss.sale_status_name,
                         s.sale_net_amount, s.sale_vat_amount, s.sale_total_amount, 
                         s.sale_paid_amount, s.sale_change_amount
                  FROM sale s
                  LEFT JOIN member m ON s.member_id = m.member_id
                  LEFT JOIN personnel p ON s.personnel_id = p.personnel_id
                  LEFT JOIN sale_status ss ON s.sale_status_id = ss.sale_status_id
                  WHERE s.sale_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$sale_id]);
        $sale_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale_data) {
            throw new Exception("ไม่พบข้อมูลบิล #$sale_id");
        }

        // ดึงสินค้า
        $product_query = "SELECT sp.sale_product_id, sp.product_id,
                                 COALESCE(pr.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
                                 sp.sale_product_qty, sp.sale_product_price
                          FROM sale_product sp
                          LEFT JOIN product pr ON pr.product_id = sp.product_id
                          WHERE sp.sale_id = ?";
        $stmt = $pdo->prepare($product_query);
        $stmt->execute([$sale_id]);
        $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // คำนวณ total_qty
        $total_qty = 0;
        foreach ($sale_products as $product) {
            $total_qty += $product['sale_product_qty'];
        }

        // ส่งข้อมูลกลับ (ใช้จาก DB ไม่ใช้ POST)
        $receipt_data = [
            'sale_id' => $sale_data['sale_id'],
            'sale_date' => date("d/m/Y H:i:s", strtotime($sale_data['sale_date'])),
            'member_name' => $sale_data['member_name'] ?? 'ไม่เป็นสมาชิก',
            'personnel_name' => $sale_data['personnel_name'] ?? '',
            'products' => $sale_products,
            'total_qty' => $total_qty,
            'sale_net_amount' => round($sale_data['sale_net_amount'], 2),
            'sale_vat_amount' => round($sale_data['sale_vat_amount'], 2),
            'sale_total_amount' => round($sale_data['sale_total_amount'], 2),
            'sale_paid_amount' => round($sale_data['sale_paid_amount'], 2),
            'sale_change_amount' => round($sale_data['sale_change_amount'], 2),
        ];

        echo json_encode(['status' => 'success', 'receipt' => $receipt_data]);
    } catch (Exception $e) {
        error_log("Error generating receipt: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถสร้างใบเสร็จได้: ' . $e->getMessage()
        ]);
    }
    exit();
}