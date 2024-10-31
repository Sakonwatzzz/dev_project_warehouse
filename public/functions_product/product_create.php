<?php
require_once __DIR__ . '../../../config/database_server.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['barcode'];
    $name = $_POST['name'];
    $price_1 = $_POST['price_1'];
    $price_2 = $_POST['price_2'];
    $price_3 = $_POST['price_3'];
    $price_4 = $_POST['price_4'];
    $capital = $_POST['capital'];
    $typename = $_POST['typename'];
    $typebrandname = $_POST['typebrandname'];

    $stmt = $pdo->prepare('INSERT INTO product (product_barcode, product_name, product_price1, product_price2, product_price3, product_price4, product_capital, product_type_id, product_brand_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$barcode, $name, $price_1, $price_2, $price_3, $price_4, $capital, $typename, $typebrandname]);

    header('Location: ../..');  // กลับไปหน้าแสดงผล
    exit;
}
