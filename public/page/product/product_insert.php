<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $barcode = $_POST['barcode'];
    $name = $_POST['name'];
    $price_1 = $_POST['price_1'];
    $price_2 = $_POST['price_2'];
    $price_3 = $_POST['price_3'];
    $price_4 = $_POST['price_4'];
    $capital = $_POST['capital'];
    $typename = $_POST['typename'];
    $typebrandname = $_POST['typebrandname'];

    try {
        // การเพิ่มข้อมูลสินค้า
        $stmt = $pdo->prepare('INSERT INTO product (product_barcode, product_name, product_price1, product_price2, product_price3, product_price4, product_capital, product_type_id, product_brand_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$barcode, $name, $price_1, $price_2, $price_3, $price_4, $capital, $typename, $typebrandname]);

        // ใช้ lastInsertId() เพื่อดึง product_id ของสินค้าที่เพิ่งเพิ่ม
        $product_id = $pdo->lastInsertId();

        // รีไดเรกต์ไปที่หน้ารายละเอียดสินค้า
        header("Location: index.php?page=product_detail&tabel=product&id=$product_id");
        exit();
    } catch (PDOException $e) {
        die('Error: ' . $e->getMessage());
    }
}
ob_end_flush();

// Query สำหรับข้อมูลสินค้า
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
    product_type ON product.product_type_id = product_type.product_type_id";

$productResults = $pdo->query($query); // ใช้ PDO query

// Query สำหรับประเภทสินค้า
$queryTypes = "SELECT
    product_type.product_type_id, 
    product_type.product_type_name
FROM product_type";
$typeResults = $pdo->query($queryTypes); // ใช้ PDO query

if (!$typeResults) {
    die("Query failed: " . $pdo->errorInfo());
}

// Query สำหรับประเภทแบรนด์สินค้า
$querybrandTypes = "SELECT
    product_brand.product_brand_id, 
    product_brand.product_brand_name
FROM product_brand";
$brandResults = $pdo->query($querybrandTypes); // ใช้ PDO query

if (!$brandResults) {
    die("Query failed: " . $pdo->errorInfo());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>product_insert</title>

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
    <div class="bg-[#F0F0F0] font-[Tahoma] ">
        <div class="mx-[40rem] mt-8 bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
            <div id="notification" class="notification">
                <div class="notification-header">การแจ้งเตือน</div>
                <div class="notification-body">เพิ่มสินค้าสำเร็จ!</div>
            </div>

            <h2 class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-2 border border-blue-700 shadow  font-bold p-5">เพิ่มสินค้า</h2>

            <!-- Form for adding products -->
            <form class="p-5  space-y-2" action="" method="POST">
                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="barcode">บาร์โค้ดสินค้า:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="barcode" id="barcode">
                </div>
                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="name">ชื่อสินค้า:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="name" id="name">
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="price_1">ราคาที่1:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="price_1" id="price_1">
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="price_2">ราคาที่2:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="price_2" id="price_2">
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="price_3">ราคาที่3:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="price_3" id="price_3">
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="price_4">ราคาที่4:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="price_4" id="price_4">
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="capital">ราคาทุน:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="capital" id="capital">
                </div>

                <?php if ($typeResults->rowCount() > 0) { ?>
                    <div class="flex items-center ">
                        <label class="w-[110px] text-base mr-4" for="typename">ประเภทสินค้า:</label>
                        <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="typename" id="typename">
                            <?php while ($row = $typeResults->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo ($row['product_type_id']); ?>">
                                    <?php echo ($row['product_type_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <p>ไม่พบข้อมูลประเภทสินค้า</p>
                <?php } ?>

                <?php if ($brandResults->rowCount() > 0) { ?>
                    <div class="flex items-center ">
                        <label class="w-[110px] text-base mr-4" for="typebrandname">ประเภทแบรนด์สินค้า:</label>
                        <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="typebrandname" id="typebrandname" required>
                            <?php while ($row = $brandResults->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo ($row['product_brand_id']); ?>">
                                    <?php echo ($row['product_brand_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <p>ไม่พบข้อมูลประเภทแบรนด์สินค้า</p>
                <?php } ?>

                <div class="flex justify-end mt-5 gap-2">
                    <a href="/?page=product_list" class="
                        bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 
                     border-t-white 
                     border-l-white 
                     px-4 
                     py-1 
                     rounded 
                                    shadow-md 
                    hover:bg-[#FFEBEE] 
                    active:border-[#EF9A9A] 
                    active:border-b-white 
                    active:border-r-white 
                    active:shadow-inner
                    ">ยกเลิก</a>

                    <button
                        type="submit"
                        class="
                            bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg border border-green-700 
                            border-t-white 
                            border-l-white 
                            px-4 
                            py-1 
                            rounded 
                            shadow-md 
                            hover:bg-[#E8F5E9] 
                            active:border-[#A5D6A7] 
                            active:border-b-white 
                            active:border-r-white 
                                active:shadow-inner
                            ">
                        บันทึก
                    </button>
                </div>

            </form>
        </div>
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