<?php
require_once __DIR__ . '../../../config/database_server.php';
require_once __DIR__ .'../../../db/warehouse_db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare("
    UPDATE supplier 
    SET supplier_name = ?, supplier_address = ?, supplier_phone = ?, supplier_detail = ?
    WHERE supplier_id = ?
");
    $stmt->execute([$name, $address, $phone, $detail, $id]);

    header("Location: /?page=bar_supplier&success=1");
    exit;
}
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM supplier WHERE supplier_id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href=".././style/personnel_add.css">

    <style>
        .edit-box {
            margin: 3rem 10rem 4rem 8rem;
        }

        h1 {
            margin-bottom: 1rem;
        }
    </style>
    <title>Document</title>

</head>

<body>
    <div class="edit-box">
        <h2>แก้ไข ข้อมูลผู้ส่งมอบ</h2>
        <form class="form-container" action="supplier_update.php" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($supplier['supplier_id']) ?>">

            <div class="box">
                <label for="name">ชื่อผู้ส่งมอบ:</label>
                <input type="text" name="name" value="<?= htmlspecialchars($supplier['supplier_name']) ?>" required>
            </div>


            <div class="box">
                <label for="address">ที่อยู่ผู้ส่งมอบ:</label>
                <input type="text" name="address" value="<?= htmlspecialchars($supplier['supplier_address']) ?>" required>
            </div>

            <div class="box">
                <label for="phone">เบอร์โทรศัพท์ผู้ส่งมอบ:</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($supplier['supplier_phone']) ?>" required>
            </div>


            <div class="box">
                <label for="detail">รายละเอียดผู้ส่งมอบ:</label>
                <input type="text" name="detail" value="<?= htmlspecialchars($supplier['supplier_detail']) ?>" required>
            </div>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
    </div>
</body>

</html>