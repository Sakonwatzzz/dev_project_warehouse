<?php
require_once __DIR__ . '../../../config/database_server.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
$stmt->execute([$id]);

header('Location: /index.php?page=bar_product');  // กลับไปหน้าแสดงผล
exit;
