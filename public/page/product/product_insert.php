<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['barcode'];
    $name = $_POST['name'];
    $price_1 = $_POST['price_1'];
    $price_2 = $_POST['price_2'];
    $price_3 = $_POST['price_3'];
    $price_4 = $_POST['price_4'];
    $capital = $_POST['capital'];
    $typename = $_POST['typename'];
    $typebrandname = $_POST['typebrandname'];

    $stmt = $pdo->prepare('INSERT INTO product (product_barcode, product_name, product_price1, product_price2, product_price3, product_price4, product_capital, product_type_id, product_brand_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$barcode, $name, $price_1, $price_2, $price_3, $price_4, $capital, $typename, $typebrandname]);

    /* เมื่อ INSERT INTO เสร็จสิ้นแล้ว เราจะใช้ lastInsertId() เพื่อดึง product_id ของสินค้าที่เพิ่งเพิ่มขึ้นมา
จากนั้นใช้ header() เพื่อทำการรีไดเรกต์ไปที่ product_detail.php โดยส่ง id ที่ได้รับจาก lastInsertId() ไปใน URL */

    $product_id = $pdo->lastInsertId(); 
    
    header("Location: product/product_detail.php?tabel=product&id=" . $product_id);

    exit();
}
ob_end_flush();

$query = "SELECT
    product.product_id, 
    product.product_barcode, 
    product.product_name, 
    product.product_price1, 
    product.product_price2, 
    product.product_price3, 
    product.product_price4, 
    product.product_capital, 
    product.product_type_id, 
    product.product_brand_id,
    product_brand.product_brand_name, 
    product_type.product_type_name
FROM
    product
INNER JOIN
    product_brand ON product.product_brand_id = product_brand.product_brand_id
INNER JOIN
    product_type ON product.product_type_id = product_type.product_type_id;
";
$productResults = $conn->query($query); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

// Query สำหรับประเภทสินค้า
$queryTypes = "SELECT
    product_type.product_type_id, 
    product_type.product_type_name
FROM product_type";
$typeResults = $conn->query($queryTypes); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

if (!$typeResults) {
    die("Query failed: " . $conn->error);
}

// Query สำหรับประเภทแบรนด์สินค้า
$querybrandTypes = "SELECT
    product_brand.product_brand_id, 
    product_brand.product_brand_name
FROM product_brand";
$brandResults = $conn->query($querybrandTypes); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

if (!$brandResults) {
    die("Query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../../style/personnel_insert.css">

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
            <div class="notification-body">เพิ่มสินค้าสำเร็จ!</div>
        </div>

        <h2 class="sub-title">เพิ่มสินค้า</h2>

        <!-- Form for adding products -->
        <form class="form-container" action="" method="POST">
            <div class="box">
                <label for="barcode">บาร์โค้ดสินค้า:</label>
                <input type="text" name="barcode" id="barcode">
            </div>
            <div class="box">
                <label for="name">ชื่อสินค้า:</label>
                <input type="text" name="name" id="name">
            </div>

            <div class="box">
                <label for="price_1">ราคาที่1:</label>
                <input type="text" name="price_1" id="price_1">
            </div>

            <div class="box">
                <label for="price_2">ราคาที่2:</label>
                <input type="text" name="price_2" id="price_2">
            </div>

            <div class="box">
                <label for="price_3">ราคาที่3:</label>
                <input type="text" name="price_3" id="price_3">
            </div>

            <div class="box">
                <label for="price_4">ราคาที่4:</label>
                <input type="text" name="price_4" id="price_4">
            </div>

            <div class="box">
                <label for="capital">ราคาทุน:</label>
                <input type="text" name="capital" id="capital">
            </div>


            <?php if ($typeResults->num_rows > 0) { ?>
                <div class="box">
                    <label for="typename">ประเภทสินค้า:</label>
                    <select name="typename" id="typename">
                        <option value="">-- เลือกประเภทสินค้า --</option>
                        <?php while ($row = $typeResults->fetch_assoc()) { ?>
                            <option value="<?php echo($row['product_type_id']); ?>">
                                <?php echo($row['product_type_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทสินค้า</p>
            <?php } ?>

            <?php if ($brandResults->num_rows > 0) { ?>
                <div class="box">
                    <label for="typebrandname">ประเภทแบรนด์สินค้า:</label>
                    <select name="typebrandname" id="typebrandname" required>
                        <option value="">-- ประเภทแบรนด์สินค้า --</option>
                        <?php while ($row = $brandResults->fetch_assoc()) { ?>
                            <option value="<?php echo($row['product_brand_id']); ?>">
                                <?php echo($row['product_brand_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทแบรนด์สินค้า</p>
            <?php } ?>
            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>

        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
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