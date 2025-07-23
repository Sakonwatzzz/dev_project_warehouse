<?php

// นี่คือโค้ดในไฟล์ index.php ตำแหน่งไฟล์คืออยู่ ภายใต้โฟลเดอร์ (Dev_Project_warehouse/public/index.php )
require_once __DIR__ . '../../config/database_server.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link href="../../output.css" rel="stylesheet">
    <title>Document</title>
    <style>
        .main-body {
            padding-bottom: 2rem;
        }
    </style>
</head>

<body class="bg-[#F0F0F0]">

    <div class="main-body">
        <?php include 'home.php'; ?>
    </div>
</body>

</html>