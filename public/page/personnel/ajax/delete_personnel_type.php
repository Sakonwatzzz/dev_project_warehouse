<?php
require_once __DIR__ . '../../../../config/database_server.php';
session_start();
$pdo = Database::getInstance()->getConnection(); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid personnel type ID']);
    exit;
}

$id = (int)$_POST['id'];

try {
    $pdo->beginTransaction();

    // Check dependencies
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_type_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => "ไม่สามารถลบประเภทบุคลากรได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: บุคลากร ($count รายการ)"]);
        exit;
    }

    // Delete record
    $stmt = $pdo->prepare("DELETE FROM personnel_type WHERE personnel_type_id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'ไม่พบข้อมูลประเภทบุคลากรที่ต้องการลบ']);
        exit;
    }

    $pdo->commit();
    echo json_encode(['success' => 'ลบข้อมูลสำเร็จ']);
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete personnel type error: ID=$id, Error=" . $e->getMessage());
    $error_message = strpos($e->getMessage(), '1451') !== false
        ? 'ไม่สามารถลบประเภทบุคลากรได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
        : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $error_message]);
    exit;
}
?>