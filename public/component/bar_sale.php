<?php
ob_start();
require_once __DIR__ . '../../../config/database_server.php';

// Get the current page from URL
$current_page = isset($_GET['page']) ? $_GET['page'] : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>Document</title>
    <style>
        /* พื้นหลังแบบ Windows XP */
        body {
            font-family: Tahoma, sans-serif;
            background-color: #e3f2fd;
            color: #333;
        }

        /* สไตล์ปุ่ม Windows XP */
        .button-bb {
            padding: 5px;
            display: flex;
            gap: 5px;
            align-items: center;
            background-color: #c8d8ed;
            border: 1px solid #81d4fa;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
            border-bottom: 10px solid #96aac4;
        }

        .button-bb a {
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .button-bb button {
            height: 100px;
            /* เพิ่มความสูง */
            width: 150px;
            display: flex;
            flex-direction: column;
            /* จัดเรียงเนื้อหาในแนวตั้ง */
            justify-content: center;
            align-items: center;
            background-color: #e1f5fe;
            border: 1px solid #4fc3f7;
            padding: 10px;
            /* เพิ่มพื้นที่ด้านใน */
            cursor: pointer;
            border-radius: 4px;
            box-shadow: 1px 1px 3px #888;
            text-align: center;
        }

        .button-bb button.active,
        .button-bb button:hover {
            background: rgb(255, 250, 209);
            background: -moz-linear-gradient(0deg, rgba(255, 250, 209, 1) 0%, rgba(253, 187, 45, 1) 43%);
            background: -webkit-linear-gradient(0deg, rgba(255, 250, 209, 1) 0%, rgba(253, 187, 45, 1) 43%);
            background: linear-gradient(0deg, rgba(255, 250, 209, 1) 0%, rgba(253, 187, 45, 1) 43%);
            filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#fffad1", endColorstr="#fdbb2d", GradientType=1);
        }

        .button-bb img {
            width: 48px;
            /* เพิ่มขนาดของรูปภาพ */
            height: auto;
            margin-bottom: 8px;
            /* เพิ่มช่องว่างระหว่างรูปและข้อความ */
        }

        .button-bb p {
            margin: 0;
            font-weight: bold;
            font-size: 14px;
            /* ปรับขนาดตัวอักษร */
            color: #333;
            text-align: center;
            /* จัดข้อความให้อยู่ตรงกลาง */
        }

        .button-bar-1,
        .button-bar-2 {
            display: flex;
            gap: 5px;
        }
    </style>
</head>

<body>
    <div class="button-bb">
        <div class="flex gap-1 p-2 border border--500 rounded-lg bg-gradient-to-r bg-[#cbdbee] shadow-xl">
            <a href="?page=sale_add" class="button-link">
                <button class="<?php echo ($current_page == 'sale_add') ? 'active' : ''; ?>">
                    <img src="../image/box.png" alt="">
                    <p>หน้าการขายสินค้า</p>
                </button>
            </a>
            <a href="?page=sale_list" class="button-link">
                <button class="<?php echo ($current_page == 'sale_list') ? 'active' : ''; ?>">
                    <img src="../image/add-product.png" alt="">
                    <p>รายการขายสินค้า</p>
                </button>
            </a>
            <a href="?page=sale_output" class="button-link">
                <button class="<?php echo ($current_page == 'sale_output') ? 'active' : ''; ?>">
                    <img src="../image/box.png" alt="">
                    <p>แสดงรายการขาย(ลูกค้า)</p>
                </button>
            </a>
            <a href="?page=sale_inserttest" class="button-link">
                <button class="<?php echo ($current_page == 'sale_inserttest') ? 'active' : ''; ?>">
                    <img src="../image/box.png" alt="">
                    <p>หน้าการขายสินค้า(ทดลอง4)</p>
                </button>
            </a>

        </div>
    </div>
</body>

</html>