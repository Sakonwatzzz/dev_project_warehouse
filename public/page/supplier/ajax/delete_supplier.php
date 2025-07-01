<?php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION)) {
    session_start();
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function checkSupplierDependencies($pdo, $supplier_id) {
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE supplier_id = ?");
    $stmt->execute([$supplier_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "สินค้า ({$count} รายการ)";
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
    echo json_encode(['error' => 'รหัสผู้ส่งมอบไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();
    $dependencies = checkSupplierDependencies($pdo, $id);
    if (!empty($dependencies)) {
        $pdo->rollBack();
        $dependency_list = implode(', ', $dependencies);
        echo json_encode(['error' => "ไม่สามารถลบผู้ส่งมอบได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM supplier WHERE supplier_id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['error' => 'ไม่พบข้อมูลผู้ส่งมอบที่ต้องการลบ']);
        exit;
    }
    $pdo->commit();
    echo json_encode(['success' => 'ลบข้อมูลสำเร็จ']);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete supplier error: Supplier ID=$id, Error=" . $e->getMessage());
    $error_message = strpos($e->getMessage(), '1451') !== false
        ? 'ไม่สามารถลบผู้ส่งมอบได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
        : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
    echo json_encode(['error' => $error_message]);
    exit;
}
?>