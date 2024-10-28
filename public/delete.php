<?php
require_once __DIR__ . '/../config/database_server.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM personnel WHERE personnel_id = ?");
$stmt->execute([$id]);

header("Location: index.php");
exit;
