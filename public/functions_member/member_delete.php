<?php
require_once __DIR__ . '../../../config/database_server.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM member WHERE member_id = ?");
$stmt->execute([$id]);

header("Location: ../member.php");    
exit;
