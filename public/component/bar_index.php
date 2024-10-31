<?php
require_once __DIR__ . '../../../config/database_server.php';
$results = fetchAll($pdo, 'personnel');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        /* พื้นหลังแบบ Windows XP */
        body {
            font-family: Tahoma, sans-serif;
            background-color: #e8f5e9; 
            color: #333;
        }

        /* สไตล์ปุ่ม Windows XP */
        .button-bb {
            padding: 5px;
            display: flex;
            gap: 5px;
            align-items: center;
            background-color: #c3e6c5;
            /* พื้นหลังปุ่มโทนเขียว */
            border: 1px solid #8bb58a;
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
            background-color: #e2f5e3;
            /* สีเขียวอ่อนปุ่ม */
            border: 1px solid #7d9b7d;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
            box-shadow: 1px 1px 3px #888;
        }

        .button-bb button:hover {
            background-color: #c8e6c9;
            border-color: #6e8e6e;
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
    </style>
    <title>Document</title>
</head>

<body>
    <div class="button-bb">
        <a href="?page=dashboard" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'dashboard') ? 'active' : ''; ?>" class="button-link">
            <button>
                <img src="../image/team.png" alt="">
                <p>Dashboard</p>
            </button>
        </a>
        <a href="?page=about" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'about') ? 'active' : ''; ?>" class="button-link">
            <button>
                <img src="../image/add.png" alt="">
                <p>อื่นๆ</p>
            </button>
        </a>

    </div>
</body>

</html>