
<?php
//page/dashboard/ajax/product.php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json');

$pdo = Database::getInstance()->getConnection();

try {
    // นับสินค้าทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product");
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();

    // นับล็อตทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lot");
    $stmt->execute();
    $total_records_lot = (int)$stmt->fetchColumn();

    // นับสมาชิกทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM member");
    $stmt->execute();
    $total_records_member = (int)$stmt->fetchColumn();

    // นับยอดขายทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale");
    $stmt->execute();
    $total_records_sale = (int)$stmt->fetchColumn();


    // ส่งข้อมูลทั้งสองใน JSON เดียว
    echo json_encode([
        'total_count' => $total_records,
        'total_count_lot' => $total_records_lot,
        'total_count_member' => $total_records_member,
        'total_count_sale' => $total_records_sale,
    ]);
    exit;
} catch (PDOException $e) {
    error_log("Count error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'เกิดข้อผิดพลาดในการนับข้อมูล']);
    exit;
}

