<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_product_id = $_POST['lot_product_id'];
    $lot_id = $_POST['lot_id'];
    $lot_product_qty = $_POST['lot_product_qty'];
    $lot_product_remain = $_POST['lot_product_remain'];
    $lot_product_capital = $_POST['lot_product_capital'];
    $lot_product_date = $_POST['lot_product_date'];
    $lot_product_status = $_POST['lot_product_status'];
    $product_id = $_POST['product_id'];


    $stmt = $pdo->prepare("
        UPDATE lot_product 
        SET product_lot_id = ?, product_lot_product_qty = ?, product_lot_product_remain = ?, lot_product_capital = ?, 
            lot_product_date = ?, lot_product_status = ?, product_id 
        WHERE lot_product_id = ?
    ");
    $stmt->execute([$lot_id, $lot_product_qty, $lot_product_remain, $lot_product_capital, $lot_product_date, $lot_product_status, $lot_product_status, $product_id, $lot_product_id]);

    // รีไดเรกต์ไปที่หน้ารายการหลังอัปเดตสำเร็จ
    header("Location: /home.php?page=lot_product_list&success=1");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM lot");
$stmt->execute();
$result_lot_id = $stmt;

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM lot_product WHERE lot_product_id = ?");
$stmt->execute([$id]);
$lot_product = $stmt->fetch();

$stmt_product = $pdo->prepare("SELECT * FROM product WHERE product_id = ?");
$stmt_product->execute([$lot_product['product_id']]);
$result_product_id = $stmt_product->fetch();


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../../style/personnel_insert.css">

    <style>
        .edit-box {
            margin: 3rem 10rem 4rem 8rem;
        }

        h1 {
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="edit-box">
        <h1 class="title">แก้ไขข้อมูลการรับรายการสินค้า</h1>

        <form class="form-container" action="" method="POST">
            <input type="hidden" name="id" value="<?php echo ($lot_product['lot_product_id']) ?>">
            <?php
            if ($result_lot_id->rowCount() > 0) {
            ?>
                <div class="box">
                    <label for="lot_id">รหัสล็อตสินค้า:</label>
                    <select name="lot_id" id="lot_id">
                        <?php while ($lot_id_update = $result_lot_id->fetch(PDO::FETCH_ASSOC)) { ?> <!-- เปลี่ยนเป็น foreach -->
                            <option value="<?php echo ($lot_id_update['lot_id']); ?>">
                                <?php echo ($lot_id_update['lot_id']); ?>
                            </option>

                        <?php } ?>
                    </select><br>
                </div>

            <?php
            } else {
                echo "No lot_id available.";
            }
            ?>
            <div class="box">
                <label for="lot_product_qty">จำนวนสินค้าเข้า:</label>
                <input type="text" name="lot_product_qty" value="<?php echo ($lot_product['lot_product_qty']) ?>">

            </div>
            <div class="box">
                <label for="lot_product_remain">จำนวนสินค้าคงเหลือ:</label>
                <input type="text" name="lot_product_remain" value="<?php echo ($lot_product['lot_product_remain']) ?>">
            </div>
            <div class="box">
                <label for="lot_product_capital">ราคาต้นทุน:</label>
                <input type="text" name="lot_product_capital" value="<?php echo ($lot_product['lot_product_capital']) ?>">

            </div>
            <div class="box">
                <label for="lot_product_date">วันที่รับสินค้า</label>
                <input type="date" id="lot_product_date" name="lot_product_date" value="<?= date('Y-m-d') ?>" readonly>
            </div>
            <div class="box">
                <label for="lot_product_status">สถานะสินค้า:</label>
                <input type="text" name="lot_product_status" value="<?php echo ($lot_product['lot_product_status']) ?>">
            </div>
            <?php
            if ($result_product_id) {
            ?>
                <div class="box">
                    <label for="product_id">รหัสสินค้า:</label>
                    <select name="product_id" id="product_id">
                        <option value="<?php echo ($result_product_id['product_id']); ?>" selected>
                            <?php echo ($result_product_id['product_name']); ?>
                        </option>
                        <!-- Add more options here if needed -->
                    </select><br>
                </div>
            <?php
            } else {
                echo "No product available.";
            }
            ?>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
    </div>


</body>

</html>