<?php
// ดึงข้อมูลประเภทบุคลลากร
include_once("../../db/warehouse_db.php");

$query = "SELECT personnel_type_name FROM personnel_type ";
$result = mysqli_query($conn, $query);
$result->fetch_assoc();

