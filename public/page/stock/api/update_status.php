<?php
require_once __DIR__ . '/../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $action = $_POST['action'] ?? '';
    if ($action !== 'confirm_check') {
        throw new Exception('Invalid action');
    }

    $stock_ids = $_POST['stock_ids'] ?? [];
    if (!is_array($stock_ids) || empty($stock_ids)) {
        throw new Exception('No stock sessions selected');
    }

    $placeholders = implode(',', array_fill(0, count($stock_ids), '?'));
    $query = "UPDATE stock SET check_status_id = 2 WHERE stock_id IN ($placeholders) AND check_status_id = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute($stock_ids);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No sessions were updated. They may already be confirmed or invalid.');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'ยืนยันเซสชันเรียบร้อย'
    ]);
} catch (Exception $e) {
    error_log("update_status.php error: " . $e->getMessage(), 3, "/path/to/error.log");
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>