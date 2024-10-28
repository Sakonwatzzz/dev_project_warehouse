<?php
require_once __DIR__ . '/../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $surname = $_POST['surname'];  
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);  // เข้ารหัสรหัสผ่าน

    $stmt = $pdo->prepare('INSERT INTO personnel (personnel_name, personnel_surname, personnel_address, personnel_phone, personnel_username, personnel_password) 
                           VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $surname,$address, $phone, $username, $password]);

    header('Location: index.php');  // กลับไปหน้าแสดงผล
    exit;
}
?>
