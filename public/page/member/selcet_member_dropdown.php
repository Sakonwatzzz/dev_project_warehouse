<?php
// ดึงข้อมูลประเภท
include_once("../../../config/database_server.php");

// $query = "SELECT member_type_name FROM member_type ";
// $result = mysqli_query($conn, $query);
// $result->fetch_assoc();
// เพิ่มการเชื่อมต่อฐานข้อมูล

// รันคำสั่ง query โดยใช้ PDO
$query = "SELECT member_type_name FROM member_type";
$stmt = $pdo->query($query);

// ใช้ while loop เพื่อดึงข้อมูลทีละแถว
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['member_type_name'] . "<br>";
}


