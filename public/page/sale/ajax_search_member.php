<?php
require_once '../../../config/database_server.php';
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$members = [];
if (strlen($query) >= 2) {
    $stmt = $pdo->prepare("
        SELECT member_id, member_name
        FROM member
        WHERE member_name LIKE ?
        LIMIT 10
    ");
    $stmt->execute(["%$query%"]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
header('Content-Type: application/json');
echo json_encode($members);
?>