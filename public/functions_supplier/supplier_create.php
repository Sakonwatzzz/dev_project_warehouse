<?php
ob_start();
require_once __DIR__ . '../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare('INSERT INTO supplier (supplier_name,supplier_address, supplier_phone, supplier_detail) 
                           VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $address, $phone, $detail]);

    header('Location: /?page=supplier_add&success=1');  
    exit;
}
$query = "SELECT supplier_id, supplier_name,supplier_address,supplier_phone, supplier_detail FROM supplier";
$stmt = $pdo->prepare($query);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
ob_end_flush();
