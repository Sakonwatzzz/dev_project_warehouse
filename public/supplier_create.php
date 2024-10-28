<?php
require_once __DIR__ . '/../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare('INSERT INTO supplier (supplier_name,supplier_address, supplier_phone, supplier_detail) 
                           VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $address, $phone, $detail]);

    header('Location: supplier.php');  // กลับไปหน้าแสดงผล
    exit;
}
