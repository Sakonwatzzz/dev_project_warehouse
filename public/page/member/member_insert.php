<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';
$resultmember = fetchAll($pdo, 'member');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $registerdate = $_POST['registerdate'];
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare('INSERT INTO member (member_name, member_address, member_phone, member_register_date, member_type_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $address, $phone, $registerdate, $typename]);

    $member_id = $pdo->lastInsertId();
    header("Location: index.php?page=member_detail&tabel=member&id= $member_id");
    exit;
}


// SQL query to get all members with their types
$query = "SELECT
    member.member_id, 
    member.member_name, 
    member.member_address, 
    member.member_phone, 
    member.member_register_date,
    member_type.member_type_id,
    member_type.member_type_name
FROM member
INNER JOIN member_type ON member.member_type_id = member_type.member_type_id";
$result = $pdo->query($query);

// SQL query to get member types for the dropdown
$queryTypes = "SELECT
    member_type.member_type_id, 
    member_type.member_type_name
FROM member_type";
$results = $pdo->query($queryTypes);
ob_end_flush();

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../../style/insert.css">
    <link href="../../output.css" rel="stylesheet">
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
            <h2 class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-2 border border-blue-700 shadow  font-bold p-5">เพิ่มสมาชิก</h2>

            <div id="notification" class="notification">
                <div class="notification-header">การแจ้งเตือน</div>
                <div class="notification-body">เพิ่มสมาชิกสำเร็จ!</div>
            </div>


            <form class="p-5  space-y-2" action="" method="POST">
                <div class="flex items-center">
                    <label for="name" class="w-[150px] text-base mr-4">ชื่อสกุลสมาชิก:</label>
                    <input type="text" name="name" id="name" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                </div>

                <div class="flex items-center">
                    <label for="address" class="w-[150px] text-base mr-4">ที่อยู่สมาชิก:</label>
                    <input type="text" name="address" id="address" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">

                </div>

                <div class="flex items-center">
                    <label for="phone" class="w-[150px] text-base mr-4">เบอร์โทรศัพท์สมาชิก:</label>
                    <input type="text" name="phone" id="phone" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">

                </div>

                <div class="flex items-center">
                    <label for="registerdate" class="w-[150px] text-base mr-4">วันที่สมัครเป็นสมาชิก:</label>
                    <input type="date" name="registerdate" id="registerdate" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?= date('Y-m-d') ?>" readonly>

                </div>

                <?php if ($results->rowCount() > 0) { ?>
                    <div class="flex items-center">
                        <label for="typename" class="w-[150px] text-base mr-4">ประเภทสมาชิก:</label>
                        <select name="typename" id="typename" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                            <?php while ($member_insert = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo ($member_insert['member_type_id']); ?>">
                                    <?php echo ($member_insert['member_type_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                <?php } else { ?>
                    <p>ไม่พบข้อมูลประเภทสมาชิก</p>
                <?php } ?>

                <div class="flex justify-end mt-5 gap-2">
                    <a href="/?page=member_list" class="
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