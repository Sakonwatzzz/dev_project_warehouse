<?php
require_once __DIR__ . '/../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed');
    }

    $stock_id = $_GET['stock_id'] ?? null;
    if (!$stock_id || !is_numeric($stock_id)) {
        throw new Exception('Invalid stock ID');
    }

    $query = "SELECT stock_id, stock_date, check_status_id 
              FROM stock 
              WHERE stock_id = :stock_id AND check_status_id = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':stock_id' => $stock_id]);
    $stock = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stock) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Stock session not found or already confirmed'
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'stock_id' => $stock['stock_id'],
            'stock_date' => $stock['stock_date']
        ]);
    }
} catch (Exception $e) {
    error_log("get_stock_session.php error: " . $e->getMessage(), 3, "/path/to/error.log");
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>