<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once __DIR__ . '../../../db/warehouse_db.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM product_brand WHERE product_brand_id = ?");
$stmt->execute([$id]);

header('Location: /index.php?page=product_brand');  // กลับไปหน้าแสดงผล
exit;
