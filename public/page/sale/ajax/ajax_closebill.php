<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 
$sale_id = $_POST['sale_id'] ?? $_GET['sale_id'] ?? $_SESSION['current_sale_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_tab') {
    header('Content-Type: application/json');
    ob_clean();
    $close_sale_id = $_POST['sale_id'] ?? null;
    if (!$close_sale_id || !in_array($close_sale_id, $_SESSION['active_sales'])) {
        echo json_encode(['status' => 'error', 'message' => 'บิลไม่ถูกต้องหรือไม่พบ']);
        exit();
    }
    try {
        // Update session
        $_SESSION['active_sales'] = array_diff($_SESSION['active_sales'], [$close_sale_id]);
        if ($_SESSION['current_sale_id'] == $close_sale_id) {
            $_SESSION['current_sale_id'] = !empty($_SESSION['active_sales']) ? reset($_SESSION['active_sales']) : null;
        }
        $redirect_url = $_SESSION['current_sale_id'] ? "?page=sale_add&sale_id={$_SESSION['current_sale_id']}" : "?page=sale_add";
        echo json_encode(['status' => 'success', 'message' => 'ปิดแท็บบิล #' . $close_sale_id . ' เรียบร้อย', 'redirect' => $redirect_url]);
    } catch (Exception $e) {
        error_log("Error closing tab $close_sale_id: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถปิดแท็บบิลได้: ' . $e->getMessage()]);
    }
    ob_end_flush();

    exit();
}
