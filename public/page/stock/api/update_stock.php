<?php
require_once __DIR__ . '../../../../../config/database_server.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'update_stock_check') {
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้อง']);
        exit;
    }

    $stock_check_id = isset($_POST['stock_check_id']) ? (int) $_POST['stock_check_id'] : 0;
    $stock_check_actual = isset($_POST['stock_check_actual']) ? (int) $_POST['stock_check_actual'] : 0;
    $stock_check_comment = isset($_POST['stock_check_comment']) ? trim($_POST['stock_check_comment']) : '';
    $stock_check_remark_id = isset($_POST['stock_check_remark_id']) ? (int) $_POST['stock_check_remark_id'] : null;

    if ($stock_check_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสรายการไม่ถูกต้อง']);
        exit;
    }

    // ดึง stock_check_system จากฐานข้อมูล
    $stmt = $pdo->prepare("SELECT stock_check_system FROM stock_check WHERE stock_check_id = ?");
    $stmt->execute([$stock_check_id]);
    $stock_check_system = $stmt->fetchColumn();
    $stock_check_system = $stock_check_system !== false ? (int) $stock_check_system : null;

    // ตั้ง stock_check_remark_id อัตโนมัติถ้าไม่ได้รับจาก frontend
    if ($stock_check_remark_id === null && $stock_check_system !== null) {
        if ($stock_check_actual === $stock_check_system) {
            $stock_check_remark_id = 1; // ครบถ้วน
        } elseif ($stock_check_actual < $stock_check_system) {
            $stock_check_remark_id = 2; // ขาดหาย
        } else {
            $stock_check_remark_id = 3; // จำนวนสินค้าเกิน
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE stock_check 
        SET stock_check_actual = :stock_check_actual, 
            stock_check_remark_id = :stock_check_remark_id, 
            stock_check_comment = :stock_check_comment
        WHERE stock_check_id = :stock_check_id
    ");
    $stmt->execute([
        ':stock_check_actual' => $stock_check_actual,
        ':stock_check_remark_id' => $stock_check_remark_id,
        ':stock_check_comment' => $stock_check_comment,
        ':stock_check_id' => $stock_check_id
    ]);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'อัปเดตรายการเรียบร้อย']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("update_stock.php error: " . $e->getMessage(), 3, "/var/log/php_errors.log");
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>