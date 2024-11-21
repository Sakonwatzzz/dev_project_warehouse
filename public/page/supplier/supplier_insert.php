<?php
require_once __DIR__ . '../../../../config/database_server.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare('INSERT INTO supplier (supplier_name,supplier_address, supplier_phone, supplier_detail) 
                           VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $address, $phone, $detail]);

    header('Location: /?page=supplier_list&success=1');
    exit;
}
$query = "SELECT supplier_id, supplier_name,supplier_address,supplier_phone, supplier_detail FROM supplier";
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
    <link rel="stylesheet" href="../../style/insert.css">

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
            <div class="notification-body">เพิ่มผู้ส่งมอบสำเร็จ!</div>
        </div>

        <h2 class="title">เพิ่มผู้ส่งมอบ</h2>

        <div class="area">

            <form class="form-container" action="" method="POST">
                <div class="box">
                    <label for="name">ชื่อผู้ส่งมอบ:</label>
                    <input type="text" id="name" name="name">
                </div>

                <div class="box">
                    <label for="address">ที่อยู่ผู้ส่งมอบ:</label>
                    <input type="text" id="address" name="address">
                </div>

                <div class="box">
                    <label for="phone">เบอร์โทรศัพท์:</label>
                    <input type="text" id="phone" name="phone">
                </div>

                <div class="box">
                    <label for="detail">รายละเอียด:</label>
                    <input type="text" id="detail" name="detail">
                </div>


                <div class="box">
                    <label for=""></label>
                    <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
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