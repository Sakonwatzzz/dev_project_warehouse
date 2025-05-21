<?php
require_once __DIR__ . '../../../../../config/database_server.php';

header('Content-Type: application/json');
ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'confirm_check') {
            $stock_check_ids = isset($_POST['stock_check_ids']) ? (array)$_POST['stock_check_ids'] : [];
            
            if (empty($stock_check_ids)) {
                echo json_encode(['status' => 'error', 'message' => 'กรุณาเลือกรายการที่ต้องการยืนยัน']);
                exit;
            }

            // ตรวจสอบว่า stock_check_ids เป็นตัวเลขทั้งหมด
            foreach ($stock_check_ids as $id) {
                if (!is_numeric($id)) {
                    echo json_encode(['status' => 'error', 'message' => 'รหัสรายการไม่ถูกต้อง']);
                    exit;
                }
            }

            // ตรวจสอบว่ารายการทั้งหมดมี check_status_id = 1 (กำลังตรวจสอบ)
            $placeholders = implode(',', array_fill(0, count($stock_check_ids), '?'));
            $stmt = $pdo->prepare("
                SELECT stock_check_id 
                FROM stock_check 
                WHERE stock_check_id IN ($placeholders) 
                AND check_status_id = 1
            ");
            $stmt->execute($stock_check_ids);
            $valid_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($valid_ids) !== count($stock_check_ids)) {
                echo json_encode(['status' => 'error', 'message' => 'บางรายการไม่ได้อยู่ในสถานะกำลังตรวจสอบ']);
                exit;
            }

            // อัปเดตสถานะเป็น 2 (ถูกตรวจสอบแล้ว)
            $stmt = $pdo->prepare("
                UPDATE stock_check 
                SET check_status_id = 2 
                WHERE stock_check_id IN ($placeholders)
            ");
            $stmt->execute($stock_check_ids);

            $affected_rows = $stmt->rowCount();
            error_log("Updated $affected_rows stock_check records to status 2");

            echo json_encode([
                'status' => 'success',
                'message' => "ยืนยัน $affected_rows รายการสำเร็จ"
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("update_status.php error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>