<?php
require_once __DIR__ . '/../../../config/database_server.php';

$warehouse_update = fetchAll($pdo, table: 'warehouse');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouse_id = $_POST['warehouse_id'];
    $warehouse_name = $_POST['warehouse_name'];
    $warehouse_status = $_POST['warehouse_status'];

    $stmt = $pdo->prepare("
    UPDATE warehouse 
    SET warehouse_name = ?, warehouse_status = ?
    WHERE warehouse_id = ?
");
    $stmt->execute([$warehouse_name, $warehouse_status, $warehouse_id]);

    header("Location: /?page=bar_warehouse&success=1");
    exit;
}
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM warehouse WHERE warehouse_id = ?");
$stmt->execute([$id]);
$warehouse_update  = $stmt->fetch();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../../style/member_edit.css">

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
        <h2>แก้ไข ข้อมูลคลังสินค้า</h2>
        <form class="form-container" action="warehouse_update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo ($warehouse_update['warehouse_id']) ?>">

            <div class="box">
                <label for="warehouse_name">ชื่อคลังสินค้า:</label>
                <input type="text" name="warehouse_name" value="<?php echo ($warehouse_update['warehouse_name']) ?>" required>
            </div>


            <div class="box">
                <label for="warehouse_status">ที่อยู่คลังสินค้า:</label>
                <input type="text" name="warehouse_status" value="<?php echo ($warehouse_update['warehouse_status']) ?>" required>
            </div>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
    </div>
</body>

</html>