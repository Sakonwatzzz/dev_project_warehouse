<?php
require_once __DIR__ . '/../../../../../config/database_server.php';
header('Content-Type: application/json');

try {
    if (isset($_POST['id']) && filter_var($_POST['id'], FILTER_VALIDATE_INT)) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->query("SELECT COUNT(*) FROM product");
        $count = $stmt->fetchColumn();
        if ($count == 0) {
            $pdo->query("ALTER TABLE product AUTO_INCREMENT = 1");
        }

        echo json_encode(['success' => true, 'message' => 'ลบสินค้าสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'รหัสสินค้าไม่ถูกต้อง']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'ข้อผิดพลาดฐานข้อมูล: ' . htmlspecialchars($e->getMessage())]);
}
?>