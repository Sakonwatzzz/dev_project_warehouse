<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../../config/database_server.php';
header('Content-Type: application/json');
$pdo = Database::getInstance()->getConnection();

try {
    $sale_id = $_GET['sale_id'] ?? null;
    if (!$sale_id) {
        throw new Exception("sale_id is required");
    }

    $stmt = $pdo->prepare("
        SELECT sale_net_amount, sale_vat_amount, sale_total_amount, sale_paid_amount, sale_change_amount
        FROM sale
        WHERE sale_id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale) {
        throw new Exception("ไม่พบข้อมูลบิลสำหรับ sale_id=$sale_id");
    }

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

    if ($calculated_net_rounded == 1.00 || $calculated_vat == 1.00 || $calculated_total == 1.00) {
        error_log("Warning: Suspicious value 1.00 detected in api_get_sale: net=$calculated_net_rounded, vat=$calculated_vat, total=$calculated_total");
    }

    echo json_encode([
        'status' => 'success',
        'sale_net_amount' => $calculated_net_rounded,
        'sale_vat_amount' => $calculated_vat,
        'sale_total_amount' => $calculated_total,
        'sale_paid_amount' => (float)($sale['sale_paid_amount'] ?? 0),
        'sale_change_amount' => (float)($sale['sale_change_amount'] ?? 0),
    ]);
} catch (Exception $e) {
    error_log("Error in api_get_sale: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
ob_end_flush();
?>