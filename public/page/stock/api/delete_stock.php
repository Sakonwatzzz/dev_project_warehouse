<?php
require_once __DIR__ . '/../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $action = $_POST['action'] ?? '';
    $pdo->beginTransaction();

    if ($action === 'delete_stock_check') {
        $stock_check_id = $_POST['stock_check_id'] ?? null;
        if (!$stock_check_id) {
            throw new Exception('No stock check ID provided');
        }

        $query = "DELETE FROM stock_check WHERE stock_check_id = :stock_check_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':stock_check_id' => $stock_check_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Stock check ID not found');
        }

        $message = 'ลบรายการตรวจสอบเรียบร้อย';
    } elseif ($action === 'delete_stock_session') {
        $stock_id = $_POST['stock_id'] ?? null;
        if (!$stock_id) {
            throw new Exception('No stock ID provided');
        }

        // ลบจาก stock_check ก่อน
        $query = "DELETE FROM stock_check WHERE stock_id = :stock_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':stock_id' => $stock_id]);

        // ลบจาก stock
        $query = "DELETE FROM stock WHERE stock_id = :stock_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':stock_id' => $stock_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Stock session not found');
        }

        $message = 'ลบเซสชันสต๊อกเรียบร้อย';
    } else {
        throw new Exception('Invalid action');
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => $message
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("delete_stock.php error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>