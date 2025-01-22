<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $personnel_name = $_POST['personnel_name'];
    $personnel_type_name = $_POST['personnel_type_name'];
    $personnel_address = $_POST['personnel_address'];
    $personnel_phone = $_POST['personnel_phone'];
    $personnel_username = $_POST['personnel_username'];
    $personnel_password = $_POST['personnel_password'];

    $stmt = $pdo->prepare('INSERT INTO personnel (personnel_name,  personnel_type_id, personnel_address, personnel_phone, personnel_username, personnel_password) 
                           VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$personnel_name, $personnel_type_name, $personnel_address, $personnel_phone, $personnel_username, $personnel_password]);


    $personnel_id = $pdo->lastInsertId();
    header("Location: index.php?page=personnel_detail&tabel=personnel&id=$personnel_id");
    exit;
}

// ดึงประเภทบุคลากรมาแสดงผล
$query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$stmt = $pdo->prepare($query);
$stmt->execute();
$personnelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_end_flush();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/insert.css">
    <link href="../../output.css" rel="stylesheet">
    <title>Document</title>

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
    </style>
</head>

<body>

    <div class="bg-[#F0F0F0] font-[Tahoma]">
        <div class="mx-[20rem] mt-8 bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
            <div id="notification" class="notification">
                <div class="notification-header">การแจ้งเตือน</div>
                <div class="notification-body">เพิ่มบุคลากรสำเร็จ!</div>
            </div>


            <h2 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">เพิ่มสมาชิก</h2>

            <form class="p-5  space-y-2  border-0" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
                <div class="flex items-center ">
                    <label for="personnel_name" class="w-[150px] text-base mr-4">ชื่อ นามสกุลบุคลากร:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" id="personnel_name" name="personnel_name">
                </div>

                <!-- loop -->
                <?php
                if (count($personnelTypes) > 0) {
                ?>
                    <div class="flex items-center ">
                        <label for="personnel_type_name" class="w-[150px] text-base mr-4">ประเภทบุคลากร:</label>
                        <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="personnel_type_name" id="personnel_type_name">
                            <?php foreach ($personnelTypes as $personnel_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
                                <option value="<?php echo ($personnel_insert['personnel_type_id']); ?>">
                                    <?php echo ($personnel_insert['personnel_type_name']); ?>
                                </option>
                            <?php } ?>
                        </select><br>
                    </div>

                <?php
                } else {
                    echo "No personnel types available.";
                }
                ?>
                <!--  -->
                <div class="flex items-center ">
                    <label for="personnel_address" class="w-[150px] text-base mr-4">ที่อยู่บุคลากร:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" id="personnel_address" name="personnel_address">
                </div>
                <div class="flex items-center ">
                    <label for="personnel_phone" class="w-[150px] text-base mr-4">เบอร์โทรศัพท์:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" id="personnel_phone" name="personnel_phone">
                </div>

                <div class="flex items-center ">
                    <label for="personnel_username" class="w-[150px] text-base mr-4">ชื่อผู้ใช้:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" id="personnel_username" name="personnel_username">
                </div>

                <div class="flex items-center ">
                    <label for="personnel_password" class="w-[150px] text-base mr-4">รหัสผ่าน:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="password" id="personnel_password" name="personnel_password">
                </div>

                <div class="flex justify-end mt-5 gap-2">
                    <a href="/?page=personnel_list" class="
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