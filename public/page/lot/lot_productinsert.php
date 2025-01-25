<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

$lot_id = []; // กำหนดค่าเริ่มต้นเพื่อป้องกันปัญหา
$product_id = []; // กำหนดค่าเริ่มต้นสำหรับ product_id

try {
    // ดึงข้อมูล lot_id
    $stmt = $pdo->query("SELECT lot_id FROM lot");
    $lot_id = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูล product_id
    $stmt = $pdo->query("SELECT product_id FROM product");
    $product_id = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the form data
    $lot_id = $_POST['lot_id']; // ตอนนี้มันจะเป็นค่าหนึ่งเดียว ไม่ใช่อาเรย์
    $lot_product_qty = $_POST['lot_product_qty'];
    $lot_product_remain = $_POST['lot_product_remain'];
    $lot_product_capital = $_POST['lot_product_capital'];
    $lot_product_date = $_POST['lot_product_date'];
    $lot_product_status = $_POST['lot_product_status'];
    $product_id = $_POST['product_id']; // นี่ก็ควรจะเป็นค่าหนึ่งเดียว

    // Prepare the SQL insert statement
    $stmt = $pdo->prepare('INSERT INTO lot_product (lot_id, lot_product_qty, lot_product_remain, lot_product_capital, lot_product_date, lot_product_status, product_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?)');

    // Execute the query with the data from the form
    $stmt->execute([
        $lot_id, // ตอนนี้มันเป็นค่าหนึ่งเดียว
        $lot_product_qty,
        $lot_product_remain,
        $lot_product_capital,
        $lot_product_date,
        $lot_product_status,
        $product_id // นี่ก็คือค่าหนึ่งเดียวเช่นกัน
    ]);

    // Get the last inserted ID and redirect
    $lot_product_id = $pdo->lastInsertId();
    header("Location: lot/lot_product_detail.php?tabel=lot_product&id=$lot_product_id");
    exit;
}

$query = "SELECT
    lot_product.lot_product_id, 
    lot_product.lot_id, 
    lot_product.lot_product_qty, 
    lot_product.lot_product_capital, 
    lot_product.lot_product_date, 
    lot_product.product_id, 
    product.product_id
FROM
    lot_product
    INNER JOIN
    lot
    ON 
    lot_product.lot_id = lot.lot_id
    INNER JOIN
    product
    ON 
    lot_product.product_id = product.product_id";

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>Document</title>

    <style>
        .notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            color: #000;
            border: 2px solid #A0A0A0;
            border-radius: 5px;
            font-family: "Tahoma", "Verdana", sans-serif;
            width: 400px;
            height: 120px;
            box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
        }

        .notification-header {
            background-color: #A0C4FF;
            color: #000;
            padding: 10px;
            font-weight: bold;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }

        .notification-body {
            padding: 20px;
            font-size: 16px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="add-box">

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">การรับสินค้าสำเร็จ!</div>
        </div>


        <h2 class="sub-title">การรับรายการสินค้า</h2>
        <form class="form-container" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->

            <div class="box">
                <label for="lot_product_id">รหัสรายการรับสินค้า:</label>
                <p type="id" id="lot_product_id" name="lot_product_id" value="<?php echo $lot['lot_product_id']; ?>"
                    readonly>
            </div>
            <!-- loop -->
            <?php
            if (count(value: $lot_id) > 0) {
                ?>
                <div class="box">
                    <label for="lot_id">รหัสล็อตสินค้า:</label>
                    <select name="lot_id" id="lot_id">
                        <?php foreach ($lot_id as $lot_id_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
                            <option value="<?php echo ($lot_id_insert['lot_id']); ?>">
                                <?php echo ($lot_id_insert['lot_id']); ?>
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
                <input type="text" id="lot_product_qty" name="lot_product_qty">
            </div>
            <div class="box">
                <label for="lot_product_remain">จำนวนสินค้าคงเหลือ:</label>
                <input type="text" id="lot_product_remain" name="lot_product_remain">
            </div>
            <div class="box">
                <label for="lot_product_capital">ราคาต้นทุน:</label>
                <input type="text" id="lot_product_capital" name="lot_product_capital">
            </div>
            <div class="box">
                <label for="lot_product_date">วันที่รับสินค้า:</label>
                <input type="date" id="lot_product_date" name="lot_product_date" value="<?= date('Y-m-d') ?>" readonly>
            </div>
            <div class="box">
                <label for="lot_product_status">สถานะสินค้า:</label>
                <input type="id" id="lot_product_status" name="lot_product_status">
            </div>
            <!-- loop -->
            <?php
            if (count($product_id) > 0) {
                ?>
                <div class="box">
                    <label for="product_id">รหัสสินค้า:</label>
                    <select name="product_id" id="product_id">
                        <?php foreach ($product_id as $lot_product_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
                            <option value="<?php echo ($lot_product_insert['product_id']); ?>">
                                <?php echo ($lot_product_insert['product_id']); ?>
                            </option>
                        <?php } ?>
                    </select><br>
                </div>

                <?php
            } else {
                echo "No product_id available.";
            }
            ?>
            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
                <!-- แก้คำว่า "lass" เป็น "class" -->
            </div>
        </form>
    </div>


    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.style.display = 'block'; // แสดงการแจ้งเตือน

                // ลบการแจ้งเตือนหลัง 2 วินาที
                setTimeout(() => {
                    notification.style.display = 'none'; // ซ่อนการแจ้งเตือน
                }, 2000);
            }
        });
    </script>
</body>

</html>