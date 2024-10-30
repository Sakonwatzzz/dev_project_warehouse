<?php
// ดึงข้อมูลประเภท
include_once("./db/warehouse_db.php");

$query = "SELECT product_type_name, procuct_brand_name FROM product_type, product_brand ";
$result = mysqli_query($conn, $query);
$result->fetch_assoc();

