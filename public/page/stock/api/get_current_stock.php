<?php
// page/stock/api/get_current_stock.php
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

try {
    session_start();
} catch (Exception $e) {
    error_log("Session start failed: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเริ่มเซสชันได้']);
    ob_end_flush();
    exit();
}

try {
    require_once __DIR__ . '../../../../../config/database_server.php';
} catch (Exception $e) {
    error_log("Error including database_server.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว']);
    ob_end_flush();
    exit();
}

if (isset($_SESSION['current_stock_id']) && (int)$_SESSION['current_stock_id'] > 0) {
    $stock_id = (int)$_SESSION['current_stock_id'];
    $stmt = $pdo->prepare("SELECT stock_id FROM stock WHERE stock_id = ? AND check_status_id = 1");
    $stmt->execute([$stock_id]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'success', 'stock_id' => $stock_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ใบตรวจนับสต๊อกไม่พบหรือถูกยืนยันแล้ว']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีเซสชันสต๊อกที่ใช้งาน']);
}

ob_end_flush();
exit();
?>