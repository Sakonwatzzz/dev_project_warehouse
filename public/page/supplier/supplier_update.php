<?php
require_once __DIR__ . '/../../../config/database_server.php';

$supplier_update = fetchAll($pdo, table: 'supplier');


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
$supplier_update  = $stmt->fetch();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/update.css">
    <title>Document</title>

</head>

<body>
    <div class="update-body">
        <h2 class="update-title">แก้ไข ข้อมูลผู้ส่งมอบ</h2>
        <form class="update-form" action="../../page/supplier/supplier_update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo ($supplier_update['supplier_id']) ?>">

            <div class="box">
                <label for="name">ชื่อผู้ส่งมอบ</label>
                <input type="text" name="name" value="<?php echo ($supplier_update['supplier_name']) ?>" required>
            </div>


            <div class="box">
                <label for="address">ที่อยู่ผู้ส่งมอบ</label>
                <input type="text" name="address" value="<?php echo ($supplier_update['supplier_address']) ?>" required>
            </div>

            <div class="box">
                <label for="phone">เบอร์โทรศัพท์ผู้ส่งมอบ</label>
                <input type="text" name="phone" value="<?php echo ($supplier_update['supplier_phone']) ?>" required>
            </div>


            <div class="box">
                <label for="detail">รายละเอียดผู้ส่งมอบ</label>
                <input type="text" name="detail" value="<?php echo ($supplier_update['supplier_detail']) ?>" required>
            </div>

            <div class="box-action">
                <a href="/?page=bar_supplier">ยกเลิก</a>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
    </div>
</body>

</html>