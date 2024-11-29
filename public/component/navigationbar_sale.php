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
            background-color: #0046AA;
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
            height: 80px;
            width: 150px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #e1f5fe;
            border: 1px solid #4fc3f7;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
            box-shadow: 1px 1px 3px #888;
        }

        .button-bb button.active,
        .button-bb button:hover {
            background-color: #e8bd4b;
            border-color: #e8bd4b;
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
            font-weight: bold;
            font-size: 12px;
            color: #333;
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
        <div class="button-bar-1">
            <a href="?page=sale_bill" class="button-link">
                <button class="<?php echo ($current_page == 'sale_bill') ? 'active' : ''; ?>">
                    <img src="../image/box.png" alt="">
                    <p>ข้อมูลบิลการขาย</p>
                </button>
            </a>
            <a href="?page=sale_list" class="button-link">
                <button class="<?php echo ($current_page == 'sale_list') ? 'active' : ''; ?>">
                    <img src="../image/add-product.png" alt="">
                    <p>ข้อมูลการขายสินค้า</p>
                </button>
            </a>
            <a href="?page=sale_status" class="button-link">
                <button class="<?php echo ($current_page == 'sale_status') ? 'active' : ''; ?>">
                    <img src="../image/box.png" alt="">
                    <p>ข้อมูลสถานะสินค้า</p>
                </button>
            </a>

        </div>
    </div>
</body>

</html>