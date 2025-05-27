<?php
require_once __DIR__ . '../../../../../config/database_server.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $action = $_POST['action'] ?? '';
    if ($action !== 'insert_new_stock') {
        throw new Exception('Invalid action');
    }

    $stock_date = $_POST['stock_date'] ?? null;
    $personnel_id = $_POST['personnel_id'] ?? '1';
    $check_status_id = $_POST['check_status_id'] ?? '1';

    if (!$stock_date) {
        throw new Exception('Stock date is required');
    }

    $query = "INSERT INTO stock (stock_date, check_status_id, personnel_id) 
              VALUES (:stock_date, :check_status_id, :personnel_id)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':stock_date' => $stock_date,
        ':check_status_id' => $check_status_id,
        ':personnel_id' => $personnel_id
    ]);

    $stock_id = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'สร้างเซสชันสต๊อกเรียบร้อย',
        'stock_id' => $stock_id
    ]);
} catch (Exception $e) {
    error_log("insert_new_stock.php error: " . $e->getMessage(), 3, "/path/to/error.log");
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>