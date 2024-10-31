<?php
require_once __DIR__ . '../../../config/database_server.php';
    
   

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $typename = $_POST['typename'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare('INSERT INTO personnel (personnel_name,  personnel_type_id, personnel_address, personnel_phone, personnel_username, personnel_password) 
                           VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $typename, $address, $phone, $username, $password]);

    header('Location: /index.php?page=personnel_add');  
    exit;
}
?>