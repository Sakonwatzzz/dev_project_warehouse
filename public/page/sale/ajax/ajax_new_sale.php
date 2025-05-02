<?php
// PHP Server-side Script ที่ทำงานร่วมกับ Ajax เป็น Endpoint สำหรับรับคำขอจาก Ajax เพื่อ สร้างบิลขายใหม่ (new sale) โดยใช้คำสั่ง SQL INSERT INTO sale
ob_start(); // จัดการ output buffer เพื่อไม่ให้มี output อื่นแทรกกับ JSON
session_start(); //ใช้ session ในการเก็บ active_sales และ current_sale_id
require_once __DIR__ . '/../../../../config/database_server.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Set timezone
date_default_timezone_set('Asia/Bangkok');
$currentDateTime = date("Y-m-d H:i:s");

// Handle new sale creation via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_sale') {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean(); // Clear any previous output หรือ จัดการ output buffer เพื่อไม่ให้มี output อื่นแทรกกับ JSON
    try {
        $pdo->beginTransaction();
        $insertSale = $pdo->prepare("INSERT INTO sale (sale_date, sale_status_id, member_id, personnel_id) VALUES (?, 1, ?, ?)");
        $insertSale->execute([$currentDateTime, null, null]);
        $new_sale_id = $pdo->lastInsertId();
        $_SESSION['active_sales'][] = $new_sale_id;
        $_SESSION['current_sale_id'] = $new_sale_id;
        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'sale_id' => $new_sale_id,
            'message' => 'บิลใหม่ #' . $new_sale_id . ' ถูกสร้างเรียบร้อย',
            'redirect' => "?page=sale_add&sale_id=$new_sale_id"
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error creating new sale: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถสร้างบิลใหม่ได้: ' . $e->getMessage()
        ]);
    }
    exit();
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request'
    ]);
    exit();
}
?>