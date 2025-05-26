<?php
require_once __DIR__ . '/../../../../config/database_server.php';
header('Content-Type: application/json');

try {
    $stock_check_id = $_POST['stock_check_id'] ?? null;
    if (!$stock_check_id) {
        throw new Exception('No stock ID provided');
    }

    $pdo->beginTransaction();
    $query = "DELETE FROM stock_check WHERE stock_check_id = :stock_check_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':stock_check_id' => $stock_check_id]);

    $query = "DELETE FROM stock WHERE stock_check_id = :stock_check_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':stock_check_id' => $stock_check_id]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'ลบเซสชันเรียบร้อย'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("delete_stock.php error: " . $e->getMessage(), 3, "/path/to/error.log");
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>