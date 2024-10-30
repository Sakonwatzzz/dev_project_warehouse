<?php
// ดึงข้อมูลประเ
include_once("./db/warehouse_db.php");

$query = "SELECT member_type_name FROM member_type ";
$result = mysqli_query($conn, $query);
$result->fetch_assoc();

