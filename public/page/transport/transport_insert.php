<?php
require_once __DIR__ . '../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

$accessToken = getToken('access_token');
if (!$accessToken || !validateToken($accessToken)) {
    $refreshToken = getToken('refresh_token');
    if ($refreshToken && validateToken($refreshToken)) {
        $accessToken = refreshToken($refreshToken);
    } else {
        header('Location: login.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transport_name = $_POST['transport_name'];
    $transport_date_out = $_POST['transport_date_out'];
    $transport_date_in = $_POST['transport_date_in'];
    $transport_status_id = $_POST['transport_status_id'];
    $transport_product_id = $_POST['transport_product_id'];
    $store_id =$_POST['$store_id'];
    

    $stmt = $pdo->prepare('INSERT INTO transport (transport_name,transport_date_out, transport_status_id, transport_product_id,store_id) 
                           VALUES (?, ?, ?, ?,?,?)');
    $stmt->execute([$transport_name, $transport_date_out, $transport_date_in, $transport_status_id,$transport_product_id, $store_id]);

    header('Location: /?page=sale_list&success=1');
    exit;
}
$query = "SELECT transport_id, transport_name,transport_date_out,transport_status_id, transport_product_id,store_id FROM transport";
$stmt = $pdo->prepare($query);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าบุคลากร</title>
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

        h1 {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body>
    <div class="add-box">

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">ข้อมูลการส่งสินค้าสำเร็จ!</div>
        </div>

        <h1 class="title">ข้อมูลการส่งสินค้า</h1>

        <form class="form-container" action="" method="POST">
            <div class="box">
                <label for="transport_name">รายการส่ง:</label>
                <input type="text" id="transport_name" name="transport_name" >
            </div>

            <div class="box">
                <label for="transport_date_out">วันที่ส่งสินค้าออก:</label>
                <input type="date" id="transport_date_out" name="transport_date_out" >
            </div>

            <div class="box">
                <label for="transport_date_in">วันที่ส่งสินค้าเข้า:</label>
                <input type="date" id="transport_date_in" name="transport_date_in" >
            </div>
            <div class="box">
                <label for="transport_status">รหัสสถานะ:</label>
                <input type="date" id="transport_status" name="transport_status" >
            </div>
            <div class="box">
                <label for="personne_id">รหัสบุคลากร:</label>
                <input type="date" id="personne_id" name="personne_id" >
            </div>
            <div class="box">
                <label for="warehouse_id">รหัสคลังสินค้า:</label>
                <input type="date" id="warehouse_id" name="warehouse_id" >
            </div>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
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