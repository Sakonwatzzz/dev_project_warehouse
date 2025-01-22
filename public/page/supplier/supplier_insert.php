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
    <link rel="stylesheet" href="../../style/insert.css">
    <link href="../../output.css" rel="stylesheet">
    <title>หน้าบุคลากร</title>
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
    <div class="bg-[#F0F0F0] font-[Tahoma]">
        <div class="mx-[20rem] mt-8 bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
            <div id="notification" class="notification">
                <div class="notification-header">การแจ้งเตือน</div>
                <div class="notification-body">เพิ่มผู้ส่งมอบสำเร็จ!</div>
            </div>

            <h2 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow  font-bold p-5">เพิ่มผู้ส่งมอบ</h2>


            <form class="p-5  space-y-2 border-0 " action="" method="POST">
                <div class="flex items-center">
                    <label for="name" class="w-[150px] text-base mr-4">ชื่อผู้ส่งมอบ:</label>
                    <input type="text" id="name" name="name" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                </div>

                <div class="flex items-center">
                    <label for="address" class="w-[150px] text-base mr-4">ที่อยู่ผู้ส่งมอบ:</label>
                    <input type="text" id="address" name="address" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                </div>

                <div class="flex items-center">
                    <label for="phone" class="w-[150px] text-base mr-4">เบอร์โทรศัพท์:</label>
                    <input type="text" id="phone" name="phone" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                </div>

                <div class="flex items-center">
                    <label for="detail" class="w-[150px] text-base mr-4">รายละเอียด:</label>
                    <input type="text" id="detail" name="detail" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                </div>

                <div class="flex justify-end mt-5 gap-2">
                    <a href="/?page=supplier_list" class="
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