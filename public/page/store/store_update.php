<?php
require_once __DIR__ . '/../../../config/database_server.php';

$store_update = fetchAll($pdo, table: 'store');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_id = $_POST['store_id'];
    $store_name = $_POST['store_name'];

    $stmt = $pdo->prepare("
    UPDATE store 
    SET store_name = ?
    WHERE store_id = ?
");
    $stmt->execute([$store_name, $store_id]);

    header("Location: /?page=bar_store&success=1");
    exit;
}
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM store WHERE store_id = ?");
$stmt->execute([$id]);
$store_update  = $stmt->fetch();
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
        <h2>แก้ไข ข้อมูลร้านค้า</h2>
        <form class="form-container" action="store_update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo ($store_update['store_id']) ?>">

            <div class="box">
                <label for="store_name">ชื่อร้านค้า:</label>
                <input type="text" name="store_name" value="<?php echo ($store_update['store_name']) ?>" required>
            </div>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
    </div>
</body>

</html>