<?php
require_once __DIR__ . '/../../../../config/database_server.php';
session_start();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     throw new Exception('Invalid CSRF token');
    // }

    // $action = $_POST['action'] ?? '';
    $personnel_id = $_POST['personnel_id'] ?? 1;
    $stock_date = $_POST['stock_date'] ?? date('Y-m-d H:i:s');

    // if ($action !== 'start_new_stock_check') {
    //     throw new Exception('Invalid action');
    // }

    // Optionally insert a new record to track the stock check session
    // Example: $stmt = $pdo->prepare("INSERT INTO stock_check_sessions (personnel_id, start_date) VALUES (?, ?)");
    // $stmt->execute([$personnel_id, $stock_date]);

    echo json_encode([
        'status' => 'success',
        'message' => 'เริ่มรายการตรวจสอบสต๊อกใหม่เรียบร้อย'
    ]);
} catch (Exception $e) {
    error_log("insert_new_stock_check.php error: " . $e->getMessage(), 3, "/path/to/error.log");
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}