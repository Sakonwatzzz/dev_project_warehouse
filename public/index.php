<?php
require_once __DIR__ . '/../config/database_server.php';
include_once("./db/warehouse_db.php");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าบุคลากร</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style/index.css">

    <style>
        .main-body {
            padding-bottom: 2rem;
        }
    </style>
</head>

<body>
    <div class="main-body">
        <?php include 'home.php'; ?>
    </div>
</body>

</html>