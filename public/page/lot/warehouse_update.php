<?php
require_once __DIR__ . '/../../../config/database_server.php';

//$warehouse_update = fetchAll($pdo, table: 'warehouse');


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

    header("Location: /?page=warehouse_list&success=1");
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

    <link rel="stylesheet" href="../../style/update.css">

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
    <div class="update-body">
        <h2 class="update-title">แก้ไข ข้อมูลคลังสินค้า</h2>
        <form class="update-form" action="" method="POST">
            <input type="hidden" name="warehouse_id" value="<?php echo ($warehouse_update['warehouse_id']) ?>">

            <div class="box">
                <label for="warehouse_name">ชื่อคลังสินค้า:</label>
                <input type="text" name="warehouse_name" value="<?php echo ($warehouse_update['warehouse_name']) ?>" required>
            </div>


            <div class="box">
                <label for="warehouse_status">สถานะคลังสินค้า:</label>
                <input type="text" name="warehouse_status" value="<?php echo ($warehouse_update['warehouse_status']) ?>" required>
            </div>

            <div class="box-action">
                <a href="?page=warehouse_list">ยกเลิก</a>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
    </div>
</body>

</html>