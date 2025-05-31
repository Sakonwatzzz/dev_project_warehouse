<?php
require_once __DIR__ . '../../../../../config/database_server.php';
session_start();
header('Content-Type: application/json');

try {
    $sale_id = $_GET['sale_id'] ?? null;
    if (!$sale_id) {
        throw new Exception("sale_id is required");
    }

    // Query sale details
    $query = "
        SELECT
            s.sale_id, s.sale_date, s.member_id, m.member_name, m.member_phone,
            s.personnel_id, p.personnel_name, s.sale_status_id, ss.sale_status_name,
            s.sale_net_amount, s.sale_vat_amount, s.sale_total_amount
        FROM sale s
        LEFT JOIN member m ON s.member_id = m.member_id
        LEFT JOIN personnel p ON s.personnel_id = p.personnel_id
        LEFT JOIN sale_status ss ON s.sale_status_id = ss.sale_status_id
        WHERE s.sale_id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sale_id]);
    $sale_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale_data) {
        throw new Exception("Sale not found");
    }

    // Query sale products
    $product_query = "
        SELECT
            sp.sale_product_id, sp.sale_id, sp.product_id,
            COALESCE(pr.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
            sp.sale_product_qty, sp.sale_product_price, sp.sale_product_capital
        FROM sale_product sp
        LEFT JOIN product pr ON pr.product_id = sp.product_id
        WHERE sp.sale_id = ?
    ";
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

    // Get payment details from session
    $payment_details = $_SESSION['payment_details'][$sale_id] ?? [
        'sale_paid_amount' => 0,
        'sale_change_amount' => 0
    ];

    // Return JSON response
    echo json_encode([
        'status' => 'success',
        'data' => [
            'sale' => $sale_data,
            'products' => $sale_products,
            'sale_net_amount' => $sale_net_amount,
            'sale_vat_amount' => $sale_vat_amount,
            'sale_total_amount' => $sale_total_amount,
            'total_qty' => $total_qty,
            'sale_paid_amount' => $payment_details['sale_paid_amount'],
            'sale_change_amount' => $payment_details['sale_change_amount']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
