<?php
require_once __DIR__ . '../../../config/database_server.php';
require_once __DIR__ . '../../db/warehouse_db.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM product_type WHERE product_type_id = ?");
$stmt->execute([$id]);

header('Location: /index.php?page=product_type');  // กลับไปหน้าแสดงผล
exit;
