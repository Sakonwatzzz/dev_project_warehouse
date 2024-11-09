<?php
// ดึงข้อมูลประเภท
include_once("../../../config/database_server.php");

// Query ที่แก้ไขแล้ว
$query = "SELECT product_type.product_type_name, product_brand.product_brand_name 
          FROM product_type 
          INNER JOIN product_brand ON product_type.product_type_id = product_brand.product_type_id"; 

// เตรียมคำสั่ง SQL
$stmt = $pdo->query($query);

// แสดงข้อมูลที่ได้จากฐานข้อมูล
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row["product_type_name"] . " - " . $row["product_brand_name"] . "<br>";
}
?>
