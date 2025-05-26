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
    $stock_check_actual = $_POST['stock_check_actual'] ?? null;
    $stock_check_date = $_POST['stock_check_date'] ?? null;
    $stock_check_remark = $_POST['stock_check_remark'] ?? '';
    $personnel_id = $_POST['personnel_id'] ?? '1';
    $lot_id = $_POST['lot_id'] ?? null;
    $lot_product_id = $_POST['lot_product_id'] ?? null;
    $stock_check_system = $_POST['stock_check_system'] ?? '0';

    // ตรวจสอบข้อมูลที่จำเป็น
    if (!$stock_id || !$product_id || !$warehouse_id || $stock_check_actual === null) {
        throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน: รหัสเซสชัน, รหัสสินค้า, คลัง, และจำนวน');
    }

    // ตรวจสอบว่าเป็นตัวเลข
    if (!preg_match('/^\d+$/', $product_id) || !preg_match('/^-?\d+$/', $stock_check_actual)) {
        throw new Exception('รหัสสินค้าและจำนวนต้องเป็นตัวเลข');
    }

    // ตรวจสอบ stock_id และสถานะ
    $query = "SELECT stock_id FROM stock WHERE stock_id = :stock_id AND check_status_id = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':stock_id' => $stock_id]);
    if (!$stmt->fetch()) {
        throw new Exception("เซสชันสต๊อก $stock_id ไม่ถูกต้องหรือถูกยืนยันแล้ว");
    }

    // เริ่ม transaction
    $pdo->beginTransaction();

    // เพิ่มรายการใน stock_check
    $query = "INSERT INTO stock_check (
        stock_id, lot_id, lot_product_id, product_id, warehouse_id, 
        stock_check_system, stock_check_actual, stock_check_remark
    ) VALUES (
        :stock_id, :lot_id, :lot_product_id, :product_id, :warehouse_id, 
        :stock_check_system, :stock_check_actual, :stock_check_remark
    )";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':stock_id' => $stock_id,
        ':lot_id' => $lot_id,
        ':lot_product_id' => $lot_product_id,
        ':product_id' => $product_id,
        ':warehouse_id' => $warehouse_id,
        ':stock_check_system' => $stock_check_system,
        ':stock_check_actual' => $stock_check_actual,
        ':stock_check_remark' => $stock_check_remark
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
    error_log("insert_stock.php error: stock_id=$stock_id, message=" . $e->getMessage(), 3, "/path/to/error.log");
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>