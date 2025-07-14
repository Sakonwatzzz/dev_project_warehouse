<?php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection(); 
header('Content-Type: application/json');

if (!isset($_SESSION)) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'delete') {
    echo json_encode(['error' => 'คำขอไม่ถูกต้อง']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function checkMemberDependencies($pdo, $member_id)
{
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "คำสั่งซื้อ ({$count} รายการ)";
    }
    return $dependencies;
}

$id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['error' => 'รหัสสมาชิกไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();
    $dependencies = checkMemberDependencies($pdo, $id);
    if (!empty($dependencies)) {
        $pdo->rollBack();
        $dependency_list = implode(', ', $dependencies);
        echo json_encode(['error' => "ไม่สามารถลบสมาชิกได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM member WHERE member_id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['error' => 'ไม่พบข้อมูลสมาชิกที่ต้องการลบ']);
        exit;
    }
    $pdo->commit();
    echo json_encode(['success' => 'ลบข้อมูลสำเร็จ']);
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete member error: Member ID=$id, Error=" . $e->getMessage());
    $error_message = strpos($e->getMessage(), '1451') !== false
        ? 'ไม่สามารถลบสมาชิกได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
        : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
    echo json_encode(['error' => $error_message]);
    exit;
}
