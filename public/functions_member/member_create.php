<?php
ob_start();
require_once __DIR__ . '../../../config/database_server.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $registerdate = $_POST['registerdate'];
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare('INSERT INTO member (member_name,  member_address, member_phone, member_register_date, member_type_id) 
                           VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $address, $phone, $registerdate, $typename]);

    header('Location: /?page=member_add&success=1');  // กลับไปหน้าแสดงผล
    exit;
    

}
ob_end_flush();
?>