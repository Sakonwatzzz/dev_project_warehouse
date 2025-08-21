<?php
session_start();
require_once __DIR__ . '../../../../../config/database_server.php';
header('Content-Type: application/json');
$pdo = Database::getInstance()->getConnection();

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
    SELECT SUM(sp.sale_product_qty * sp.sale_product_price) as calculated_net
    FROM sale_product sp
    WHERE sp.sale_id = ?
");
    $stmt->execute([$sale_id]);
    $calculated_net = (float)($stmt->fetchColumn() ?: 0);

    // ใช้ BCMath สำหรับ precision (ถ้าไม่มี extension ใช้ round แต่ multiply 100)
    // $calculated_net_rounded = round($calculated_net * 100) / 100; // หรือ bcadd('0', $calculated_net, 2) ถ้ามี BCMath
    $calculated_net_rounded = round($calculated_net, 2);
    $calculated_vat = round($calculated_net_rounded * 0.07, 2);
    $calculated_total = round($calculated_net_rounded + $calculated_vat, 2);

    if (
        round($sale_net_amount, 2) !== $calculated_net_rounded ||
        round($sale_vat_amount, 2) !== $calculated_vat ||
        round($sale_total_amount, 2) !== $calculated_total
    ) {
        error_log("Amount mismatch for sale_id $sale_id: sent_net=$sale_net_amount, calculated_net=$calculated_net_rounded");
        $sale_net_amount = $calculated_net_rounded;
        $sale_vat_amount = $calculated_vat;
        $sale_total_amount = $calculated_total;
    }

    // Recalculate change หลัง verify (เพื่อ sync กับ total ใหม่ถ้ามี override)
    $sale_change_amount = round($sale_paid_amount - $sale_total_amount, 2);
    if ($sale_paid_amount < $sale_total_amount) {
        throw new Exception("จำนวนเงินที่ชำระไม่เพียงพอ");
    }
    if ($sale_net_amount < 0 || $sale_vat_amount < 0 || $sale_total_amount < 0 || $sale_paid_amount < 0 || $sale_change_amount < 0) {
        throw new Exception("Total amount, paid amount, and change amount cannot be negative");
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
    echo json_encode(['status' => 'success', 'message' => 'อัพเดทสำเร็จ', 'updated_total' => $sale_total_amount]);
} catch (Exception $e) {
    error_log("Error in api_update_payment: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
