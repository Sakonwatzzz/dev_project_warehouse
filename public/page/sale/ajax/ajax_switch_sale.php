<?php
// Start output buffering
ob_start();

// Set headers immediately
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Disable display errors to prevent output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Log start of script
error_log("ajax_switch_sale.php started at " . date('Y-m-d H:i:s'));

// Start session
try {
    session_start();
} catch (Exception $e) {
    error_log("Session start failed: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่สามารถเริ่มเซสชันได้'
    ]);
    ob_end_flush();
    exit();
}

// Include database configuration
try {
    require_once __DIR__ . '/../../../../config/database_server.php';
    error_log("Database configuration included successfully");
} catch (Exception $e) {
    error_log("Error including database_server.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit();
}
$pdo = Database::getInstance()->getConnection(); 
// Log request data
error_log("Request data: " . json_encode($_POST));

// Handle switch sale via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'switch_sale') {
    try {
        $new_sale_id = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : null;
        if (!$new_sale_id) {
            throw new Exception("sale_id ไม่ถูกต้องหรือไม่พบ");
        }
        if (!isset($_SESSION['active_sales']) || !is_array($_SESSION['active_sales'])) {
            throw new Exception("ไม่มีบิลที่ใช้งานอยู่ในเซสชัน");
        }
        if (!in_array($new_sale_id, $_SESSION['active_sales'])) {
            throw new Exception("บิล #$new_sale_id ไม่พบในบิลที่ใช้งานอยู่");
        }

        $_SESSION['current_sale_id'] = $new_sale_id;
        error_log("Switched to sale_id: " . $new_sale_id);

        echo json_encode([
            'status' => 'success',
            'sale_id' => $new_sale_id,
            'message' => 'เปลี่ยนไปใช้บิล #' . $new_sale_id
        ]);
    } catch (Exception $e) {
        error_log("Error in switch_sale: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถสลับบิลได้: ' . $e->getMessage()
        ]);
    }
} else {
    error_log("Invalid request: " . json_encode($_SERVER));
    echo json_encode([
        'status' => 'error',
        'message' => 'คำขอไม่ถูกต้อง'
    ]);
}
$stmt = $pdo->prepare("SELECT 1 FROM sale WHERE sale_id = ?");
$stmt->execute([$new_sale_id]);
if (!$stmt->fetchColumn()) {
    throw new Exception("บิล #$new_sale_id ไม่พบในฐานข้อมูล");
}
// Flush output and exit
ob_end_flush();
exit();
?>