<?php
ob_start();

require_once __DIR__ . '../../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเริ่มเซสชันได้']);
    ob_end_flush();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'switch_stock') {
    try {
        $new_stock_id = isset($_POST['stock_id']) ? (int) $_POST['stock_id'] : null;
        if (!$new_stock_id) {
            throw new Exception("stock_id ไม่ถูกต้องหรือไม่พบ");
        }

        // ตรวจสอบว่า stock_id มีอยู่ในฐานข้อมูลและอยู่ในสถานะที่ใช้งานได้
        $stmt = $pdo->prepare("SELECT stock_id FROM stock WHERE stock_id = ? AND check_status_id = 1");
        $stmt->execute([$new_stock_id]);
        if (!$stmt->fetch()) {
            throw new Exception("ใบตรวจนับสต๊อก #$new_stock_id ไม่พบหรือถูกยืนยันแล้ว");
        }

        // บันทึก stock_id ใน session
        $_SESSION['current_stock_id'] = $new_stock_id;
        error_log("Switched to stock_id: " . $new_stock_id);

        echo json_encode([
            'status' => 'success',
            'stock_id' => $new_stock_id,
            'message' => 'เปลี่ยนไปใช้ใบตรวจนับสต๊อก #' . $new_stock_id
        ]);
    } catch (Exception $e) {
        error_log("Error in switch_stock: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถสลับใบตรวจนับสต๊อกได้: ' . $e->getMessage()]);
    }
} else {
    error_log("Invalid request: " . json_encode($_SERVER));
    echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้อง']);
}

ob_end_flush();
exit();
?>