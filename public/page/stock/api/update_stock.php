<?php
require_once __DIR__ . '../../../../../config/database_server.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock_check') {
        $stock_check_id = isset($_POST['stock_check_id']) ? (int)$_POST['stock_check_id'] : 0;
        $stock_check_actual = isset($_POST['stock_check_actual']) ? (int)$_POST['stock_check_actual'] : 0;
        $stock_check_remark = isset($_POST['stock_check_remark']) ? trim($_POST['stock_check_remark']) : '';

        if ($stock_check_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'รหัสรายการไม่ถูกต้อง']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE stock_check 
            SET stock_check_actual = ?, stock_check_remark = ?
            WHERE stock_check_id = ?
        ");
        $stmt->execute([$stock_check_actual, $stock_check_remark, $stock_check_id]);

        echo json_encode(['status' => 'success', 'message' => 'อัปเดตรายการเรียบร้อย']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้อง']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>