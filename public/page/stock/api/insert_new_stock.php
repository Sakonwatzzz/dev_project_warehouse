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
    $personnel_id = $_POST['personnel_id'] ?? null;
    $check_status_id = $_POST['check_status_id'] ?? '1';
    $warehouse_id = $_POST['warehouse_id'] ?? null;

    if (!$stock_date || !$personnel_id || !$warehouse_id) {
        throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
    }

    $pdo->beginTransaction();

    // ตรวจสอบว่าคลังถูกใช้งานโดยผู้ใช้คนอื่นหรือไม่
    $stmt = $pdo->prepare("SELECT stock_id, personnel_id FROM stock WHERE warehouse_id = ? AND check_status_id = 1 AND personnel_id != ? LIMIT 1");
    $stmt->execute([$warehouse_id, $personnel_id]);
    $other_user_stock = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($other_user_stock) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'warning',
            'message' => "คลังนี้กำลังถูกตรวจสอบโดยผู้ใช้คนอื่น (stock_id: {$other_user_stock['stock_id']})"
        ]);
        exit;
    }

    // ตรวจสอบว่า personnel_id มีเซสชันที่รอดำเนินการในคลังนี้หรือไม่
    $stmt = $pdo->prepare("SELECT stock_id FROM stock WHERE personnel_id = ? AND warehouse_id = ? AND check_status_id = 1 LIMIT 1");
    $stmt->execute([$personnel_id, $warehouse_id]);
    $existing_stock = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_stock) {
        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'stock_id' => $existing_stock['stock_id'],
            'message' => 'พบเซสชันที่รอดำเนินการ ใช้ stock_id เดิม'
        ]);
        exit;
    }

    // สร้างเซสชันใหม่
    $query = "INSERT INTO stock (stock_date, personnel_id, check_status_id, warehouse_id) VALUES (:stock_date, :personnel_id, :check_status_id, :warehouse_id)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':stock_date' => $stock_date,
        ':personnel_id' => $personnel_id,
        ':check_status_id' => $check_status_id,
        ':warehouse_id' => $warehouse_id
    ]);
    $stock_id = $pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'stock_id' => $stock_id,
        'message' => 'สร้างเซสชันสต๊อกใหม่สำเร็จ'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("insert_new_stock.php error: " . $e->getMessage(), 3, "/var/log/php_errors.log");
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>