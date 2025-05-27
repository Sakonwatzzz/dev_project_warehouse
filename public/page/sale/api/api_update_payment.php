<?php
session_start();
require_once __DIR__ . '../../../../../config/database_server.php';
header('Content-Type: application/json');

try {
    // $csrf_token = $_POST['csrf_token'] ?? '';
    // if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    //     throw new Exception("Invalid CSRF token");
    // }
    $sale_id = $_POST['sale_id'] ?? null;
    $sale_net_amount = isset($_POST['sale_net_amount']) ? (float)$_POST['sale_net_amount'] : 0;
    $sale_vat_amount = isset($_POST['sale_vat_amount']) ? (float)$_POST['sale_vat_amount'] : 0;
    $sale_total_amount = isset($_POST['sale_total_amount']) ? (float)$_POST['sale_total_amount'] : 0;
    $sale_paid_amount = isset($_POST['sale_paid_amount']) ? (float)$_POST['sale_paid_amount'] : 0;
    $sale_change_amount = isset($_POST['sale_change_amount']) ? (float)$_POST['sale_change_amount'] : 0;

    if (!$sale_id) {
        throw new Exception("sale_id is required");
    }
    if ($sale_net_amount < 0 || $sale_vat_amount < 0 || $sale_total_amount < 0 || $sale_paid_amount < 0 || $sale_change_amount < 0) {
        throw new Exception("Total amount, paid amount, and change amount cannot be negative");
    }
    if ($sale_paid_amount < $sale_total_amount) {
        throw new Exception("จำนวนเงินที่ชำระไม่เพียงพอ");
    }

    // Verify sale_total_amount from sale_product
    $stmt = $pdo->prepare("
        SELECT SUM(sp.sale_product_qty * sp.sale_product_price) as calculated_total
        FROM sale_product sp
        WHERE sp.sale_id = ?
    ");
    $stmt->execute([$sale_id]);
    $calculated_total = $stmt->fetchColumn() ?: 0;

    // คำนวณ calculated_net และ calculated_vat
    $calculated_net = $calculated_total / 1.07; // ไม่รวม VAT
    $calculated_vat = $calculated_total - $calculated_net; // VAT

    if (
        round($sale_vat_amount, 2) !== round($calculated_vat, 2) ||
        round($sale_net_amount, 2) !== round($calculated_net, 2) ||
        round($sale_total_amount, 2) !== round($calculated_total, 2)
    ) {
        error_log("Total amount mismatch for sale_id $sale_id: sent=$sale_total_amount, calculated=$calculated_total");
        $sale_vat_amount = $calculated_vat;
        $sale_net_amount = $calculated_net;
        $sale_total_amount = $calculated_total;
    }

    // Store in session
    $_SESSION['payment_details'][$sale_id] = [
        'sale_net_amount' => $sale_net_amount,
        'sale_vat_amount' => $sale_vat_amount,
        'sale_total_amount' => $sale_total_amount,
        'sale_paid_amount' => $sale_paid_amount,
        'sale_change_amount' => $sale_change_amount,
    ];

    // Update database
    $stmt = $pdo->prepare("
        UPDATE sale 
        SET sale_net_amount = ?, sale_vat_amount = ?, sale_total_amount = ?, 
            sale_paid_amount = ?, sale_change_amount = ? 
        WHERE sale_id = ?
    ");
    $stmt->execute([
        $sale_net_amount,
        $sale_vat_amount,
        $sale_total_amount,
        $sale_paid_amount,
        $sale_change_amount,
        $sale_id
    ]);
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log("Error in api_update_payment: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
