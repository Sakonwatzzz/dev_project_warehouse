<?php
require_once __DIR__ . '/../../../config/database_server.php';
require_once __DIR__ . '../../../db/warehouse_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare('INSERT INTO supplier (supplier_name,supplier_address, supplier_phone, supplier_detail) 
                           VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $address, $phone, $detail]);
    header('Location: /?page=bar_supplier');  // กลับไปหน้าแสดงผล
    exit;
}
$query = "SELECT supplier_id, supplier_name,supplier_address,supplier_phone, supplier_detail FROM supplier";
$stmt = $pdo->prepare($query);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าบุคลากร</title>
    <link rel="stylesheet" href="style/index.css">
</head>

<body>
    <div class="main-body">
        <h1 class="title">ข้อมูลผู้ส่งมอบ</h1>

        <h2 class="sub-title">เพิ่มข้อมูลผู้ส่งมอบ</h2>
        <form class="form-container" action="" method="POST">
            <label for="name">ชื่อผู้ส่งมอบ:</label>
            <input type="text" id="name" name="name" required>

            <label for="address">ที่อยู่ผู้ส่งมอบ:</label>
            <input type="text" id="address" name="address" required>

            <label for="phone">เบอร์โทรศัพท์:</label>
            <input type="text" id="phone" name="phone" required>

            <label for="detail">รายละเอียด:</label>
            <input type="text" id="detail" name="detail" required>

            <button class="submit-button" type="submit" > เพิ่ม</button>
        </form>

       
    </div>
</body>

</html>
