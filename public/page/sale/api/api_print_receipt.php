<?php
require_once __DIR__ . '../../../../../config/database_server.php';
ob_start();
header('Content-Type: application/json');

// Handle print receipt via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'print_receipt') {
    header('Content-Type: application/json');
    ob_clean();
    try {
        $sale_id = $_POST['sale_id'] ?? null;
        if (!$sale_id) {
            throw new Exception("ไม่พบ sale_id ในคำขอ กรุณาเปิดบิลใหม่และเพิ่มข้อมูลการขาย");
        }

        // Query sale details
        $query = "SELECT
            s.sale_id, s.sale_date, s.member_id, m.member_name, m.member_phone,
            s.personnel_id, p.personnel_name, s.sale_status_id, ss.sale_status_name
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

        // Query sale products
        $product_query = "SELECT
            sp.sale_product_id, sp.sale_id, sp.product_id,
            COALESCE(pr.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
            sp.sale_product_qty, sp.sale_product_price, sp.sale_product_capital
        FROM sale_product sp
        LEFT JOIN product pr ON pr.product_id = sp.product_id
        WHERE sp.sale_id = ?";
        $stmt = $pdo->prepare($product_query);
        $stmt->execute([$sale_id]);
        $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sale_net_amount = 0;
        $total_qty = 0;
        foreach ($sale_products as $product) {
            $sale_net_amount += $product['sale_product_qty'] * $product['sale_product_price'];
            $total_qty += $product['sale_product_qty'];
        }
        $vat_rate = 7.00;
        $sale_vat_amount = $sale_net_amount * ($vat_rate / 100);
        $sale_total_amount = $sale_net_amount + $sale_vat_amount;

        // Prepare receipt data
        $receipt_data = [
            'sale_id' => $sale_data['sale_id'],
            'sale_date' => date("d/m/Y H:i:s", strtotime($sale_data['sale_date'])),
            'member_name' => $sale_data['member_name'] ?? 'ไม่ระบุ',
            'personnel_name' => $sale_data['personnel_name'] ?? 'ไม่ระบุ',
            'products' => $sale_products,
            'total_qty' => $total_qty,
            'sale_net_amount' => $sale_net_amount,
            'sale_vat_amount' => $sale_vat_amount,
            'sale_total_amount' => $sale_total_amount,
            'sale_paid_amount' => $_POST['sale_paid_amount'] ?? 0,
            'sale_change_amount' => $_POST['sale_change_amount'] ?? 0
        ];

        echo json_encode(['status' => 'success', 'receipt' => $receipt_data]);
    } catch (Exception $e) {
        error_log("Error generating receipt: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถสร้างใบเสร็จได้: ' . $e->getMessage()]);
    }
    ob_end_flush();
    exit();
}
?>