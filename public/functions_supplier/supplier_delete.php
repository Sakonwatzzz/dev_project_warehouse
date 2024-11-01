<?php
require_once __DIR__ . '../../../config/database_server.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM supplier WHERE supplier_id = ?");
$stmt->execute([$id]);

header("Location: /?page=bar_supplier");
exit;
