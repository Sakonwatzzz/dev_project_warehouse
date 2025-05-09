<?php
require_once __DIR__ . '../../../../../config/database_server.php';
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$members = [];
if (strlen($query) >= 2) {
    $stmt = $pdo->prepare("
        SELECT member_id, member_name
        FROM member
        WHERE member_name LIKE ? OR member_phone LIKE ?
        LIMIT 10
    ");
    $stmt->execute(["%$query%", "%$query%"]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
header('Content-Type: application/json');
echo json_encode($members);
