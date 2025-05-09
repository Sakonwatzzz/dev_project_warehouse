<?php
session_start();
require_once __DIR__ . '../../../../../config/database_server.php';
header('Content-Type: application/json');

try {
    $sale_id = $_POST['sale_id'] ?? null;
    $total_amount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0;
    $paid_amount = isset($_POST['paid_amount']) ? (float)$_POST['paid_amount'] : 0;
    $change_amount = isset($_POST['change_amount']) ? (float)$_POST['change_amount'] : 0;

    if (!$sale_id) {
        throw new Exception("sale_id is required");
    }
    if ($total_amount < 0 || $paid_amount < 0 || $change_amount < 0) {
        throw new Exception("Total amount, paid amount, and change amount cannot be negative");
    }

    // Verify total_amount from sale_product
    $stmt = $pdo->prepare("
        SELECT SUM(sp.sale_product_qty * sp.sale_product_price) as calculated_total
        FROM sale_product sp
        WHERE sp.sale_id = ?
    ");
    $stmt->execute([$sale_id]);
    $calculated_total = $stmt->fetchColumn() ?: 0;

    if (round($total_amount, 2) !== round($calculated_total, 2)) {
        error_log("Total amount mismatch for sale_id $sale_id: sent=$total_amount, calculated=$calculated_total");
        // Optionally: Use calculated_total instead
        $total_amount = $calculated_total;
    }

    // Store in session
    $_SESSION['payment_details'][$sale_id] = [
        'total_amount' => $total_amount,
        'paid_amount' => $paid_amount,
        'change_amount' => $change_amount
    ];

    // Update database
    $stmt = $pdo->prepare("
        UPDATE sale 
        SET total_amount = ?, paid_amount = ?, change_amount = ? 
        WHERE sale_id = ?
    ");
    $stmt->execute([$total_amount, $paid_amount, $change_amount, $sale_id]);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log("Error in api_update_payment: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>