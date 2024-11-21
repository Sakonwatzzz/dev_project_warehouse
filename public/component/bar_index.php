<?php
ob_start();
require_once __DIR__ . '../../../config/database_server.php';
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
            background-color: #e3f2fd;
            color: #333;
        }

        .button-bb {
            display: flex;
            gap: 10px;
            padding: 10px;
            align-items: center;
            background-color: #bbdefb;
            border: 1px solid #90caf9;
            border-radius: 4px;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
        }

        .button-bb a {
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .button-bb .button-link {
            display: flex;
            align-items: center;
            background-color: #e1f5fe;
            border: 1px solid #7d9b7d;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
            box-shadow: 1px 1px 3px #888;
            text-decoration: none;
            color: #333;
        }

        .button-bb .button-link:hover {
            background-color: #b3e5fc;
            border-color: #39a9db;
        }

        .button-bb img {
            width: 32px;
            height: auto;
            margin-right: 8px;
        }

        .button-bb p {
            margin: 0;
            font-size: 12px;
        }
    </style>
    <title>Document</title>
</head>

<body>
    <div class="button-bb">
        <a href="?page=dashboard" class="button-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'dashboard') ? 'active' : ''; ?>">
            <img src="../image/team.png" alt="">
            <p>Dashboard</p>
        </a>
        
        <a href="?page=about" class="button-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'about') ? 'active' : ''; ?>">
            <img src="../image/add.png" alt="">
            <p>อื่นๆ</p>
        </a>
    </div>
</body>

</html>
