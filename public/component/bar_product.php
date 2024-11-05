<?php
ob_start();
require_once __DIR__ . '../../../config/database_server.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        /* พื้นหลังแบบ Windows XP */
        body {
            font-family: Tahoma, sans-serif;
            background-color: #e3f2fd;
            /* เปลี่ยนเป็นฟ้าอ่อน */
            color: #333;
        }

        /* สไตล์ปุ่ม Windows XP */
        .button-bb {
            padding: 5px;
            display: flex;
            gap: 5px;
            align-items: center;
            background-color: #bbdefb;
            border: 1px solid #81d4fa;
            padding: 10px;
            border-radius: 4px;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
        }

        .button-bb a {
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .button-bb button {
            display: flex;
            align-items: center;
            background-color: #e1f5fe; /* สีฟ้าอ่อนปุ่ม */
            border: 1px solid #4fc3f7;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
            box-shadow: 1px 1px 3px #888;
        }

        .button-bb button:hover {
            background-color: #b3e5fc;
            /* เปลี่ยนสีเมื่อ hover */
            border-color: #39a9db;
            /* เปลี่ยนสีขอบเมื่อ hover */
        }

        /* ขนาดภาพ */
        .button-bb img {
            width: 32px;
            height: auto;
            margin-right: 8px;
        }

        /* สไตล์ข้อความ */
        .button-bb p {
            margin: 0;
            font-size: 12px;
            color: #333;
        }

        .button-bar-1 {
            display: flex;
            gap: 5px;
        }

        .button-bar-2 {
            display: flex;
            gap: 5px;
        }
    </style>
</head>

<body>
    <div class="button-bb">
        <div class="button-bar-1">
            <a href="?page=product_list" class="button-link">
                <button>
                    <img src="../image/box.png" alt="">
                    <p>รายการ</p>
                </button>
            </a>
            <a href="?page=product_insert" class="button-link">
                <button>
                    <img src="../image/add-product.png" alt="">
                    <p>เพิ่มสินค้า</p>
                </button>
            </a>
            <a href="?page=product_type" class="button-link">
                <button>
                    <img src="../image/box.png" alt="">
                    <p>ประเภทสินค้า</p>
                </button>
            </a>
            <a href="?page=product_brand" class="button-link">
                <button>
                    <img src="../image/box.png" alt="">
                    <p>แบรนด์</p>
                </button>
            </a>
        </div>

        <div class="button-bar-2">
            <a href="#" class="button-link">
                <button>
                    <img src="../image/warehouse.png" alt="">
                    <p>คลัง</p>
                </button>
            </a>
            <a href="#" class="button-link">
                <button>
                    <img src="../image/wooden-box.png" alt="">
                    <p>รอบสินค้า</p>
                </button>
            </a>
            <a href="#" class="button-link">
                <button>
                    <img src="../image/divide.png" alt="">
                    <p>แบ่งสินค้า</p>
                </button>
            </a>
        </div>

    </div>
</body>

</html>