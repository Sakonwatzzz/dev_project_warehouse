<?php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection();

if (!isset($_SESSION)) {
    session_start();
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function checkStoreDependencies($pdo, $store_id) {
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lot WHERE store_id = ?");
    $stmt->execute([$store_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "ล็อต ({$count} รายการ)";
    }
    return $dependencies;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'delete') {
    echo json_encode(['error' => 'คำขอไม่ถูกต้อง']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['error' => 'รหัสร้านค้าไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();
    $dependencies = checkStoreDependencies($pdo, $id);
    if (!empty($dependencies)) {
        $pdo->rollBack();
        $dependency_list = implode(', ', $dependencies);
        echo json_encode(['error' => "ไม่สามารถลบร้านค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM store WHERE store_id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['error' => 'ไม่พบข้อมูลร้านค้าที่ต้องการลบ']);
        exit;
    }
    $pdo->commit();
    echo json_encode(['success' => 'ลบข้อมูลสำเร็จ']);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete store error: Store ID=$id, Error=" . $e->getMessage());
    $error_message = strpos($e->getMessage(), '1451') !== false
        ? 'ไม่สามารถลบร้านค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
        : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
    echo json_encode(['error' => $error_message]);
}