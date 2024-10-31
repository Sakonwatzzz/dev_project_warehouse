<?php
require_once __DIR__ . '../../../config/database_server.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM personnel WHERE personnel_id = ?");
$stmt->execute([$id]);

header("Location: http://localhost:8000/home.php?page=personnel_list");    
exit;
