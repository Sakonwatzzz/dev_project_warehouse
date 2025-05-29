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
    $personnel_id = $_POST['personnel_id'] ?? null;

    if (!$stock_id || !$product_id || !$warehouse_id || !$lot_id || !$lot_product_id || $stock_check_actual === null || $stock_check_system === null || !$personnel_id) {
        throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
    }

    $pdo->beginTransaction();

    // ตรวจสอบ stock_id และ personnel_id
    $query = "SELECT stock_id FROM stock WHERE stock_id = :stock_id AND check_status_id = 1 AND personnel_id = :personnel_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':stock_id' => $stock_id, ':personnel_id' => $personnel_id]);
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        throw new Exception("เซสชันสต๊อก $stock_id ไม่ถูกต้อง, ถูกยืนยันแล้ว, หรือไม่ใช่ของผู้ใช้");
    }

    // บันทึก stock_check
    $query = "INSERT INTO stock_check (stock_id, product_id, warehouse_id, lot_id, lot_product_id, stock_check_actual, stock_check_system, stock_check_remark, personnel_id)
              VALUES (:stock_id, :product_id, :warehouse_id, :lot_id, :lot_product_id, :stock_check_actual, :stock_check_system, :stock_check_remark, :personnel_id)";
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
        ':personnel_id' => $personnel_id
    ]);

    // อัปเดต lot_product_remain
    $query = "UPDATE lot_product SET lot_product_remain = lot_product_remain + (:stock_check_actual - :stock_check_system) WHERE lot_product_id = :lot_product_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':stock_check_actual' => $stock_check_actual,
        ':stock_check_system' => $stock_check_system,
        ':lot_product_id' => $lot_product_id
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
    error_log("insert_stock.php error: " . $e->getMessage(), 3, "/var/log/php_errors.log");
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>