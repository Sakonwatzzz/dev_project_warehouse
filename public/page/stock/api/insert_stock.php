<?php
require_once __DIR__ . '/../../../../config/database_server.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $action = $_POST['action'] ?? '';
    if ($action !== 'insert_check_product') {
        throw new Exception('Invalid action');
    }

    $stock_id = $_POST['stock_id'] ?? null;
    $product_id = $_POST['product_id'] ?? null;
    $warehouse_id = $_POST['warehouse_id'] ?? null;
    $lot_id = $_POST['lot_id'] ?? null;
    $lot_product_id = $_POST['lot_product_id'] ?? null;
    $stock_check_actual = $_POST['stock_check_actual'] ?? null;
    $stock_check_system = $_POST['stock_check_system'] ?? null;
    $stock_check_remark = $_POST['stock_check_remark'] ?? '';
    // $personnel_id = $_POST['personnel_id'] ?? '1';

    if (!$stock_id || !$product_id || !$warehouse_id || !$lot_id || !$lot_product_id || $stock_check_actual === null || $stock_check_system === null) {
        throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
    }

    $query = "SELECT stock_id FROM stock WHERE stock_id = :stock_id AND check_status_id = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':stock_id' => $stock_id]);
    if (!$stmt->fetch()) {
        throw new Exception("เซสชันสต๊อก $stock_id ไม่ถูกต้องหรือถูกยืนยันแล้ว");
    }

    $pdo->beginTransaction();
    $query = "INSERT INTO stock_check (stock_id, product_id, warehouse_id, lot_id, lot_product_id, stock_check_actual, stock_check_system, stock_check_remark)
              VALUES (:stock_id, :product_id, :warehouse_id, :lot_id, :lot_product_id, :stock_check_actual, :stock_check_system, :stock_check_remark)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':stock_id' => $stock_id,
        ':product_id' => $product_id,
        ':warehouse_id' => $warehouse_id,
        ':lot_id' => $lot_id,
        ':lot_product_id' => $lot_product_id,
        ':stock_check_actual' => $stock_check_actual,
        ':stock_check_system' => $stock_check_system,
        ':stock_check_remark' => $stock_check_remark,
        
    ]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'เพิ่มรายการตรวจสอบสต๊อกเรียบร้อย',
        'stock_id' => $stock_id
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("insert_stock.php error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>