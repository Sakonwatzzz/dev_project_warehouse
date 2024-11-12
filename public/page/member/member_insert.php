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

    $stmt = $pdo->prepare('INSERT INTO member (member_name, member_address, member_phone, member_register_date, member_type_id) 
                           VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $address, $phone, $registerdate, $typename]);

    header('Location: /?page=member_insert&success=1');
    exit;
}

ob_end_flush();

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
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
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
    </style>
</head>

<body>
    <div class="add-box">
        <h2 class="sub-title">เพิ่มสมาชิก</h2>

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">เพิ่มสมาชิกสำเร็จ!</div>
        </div>


        <form class="form-container" action="" method="POST">
            <div class="box">
                <label for="name">ชื่อสกุลสมาชิก:</label>
                <input type="text" name="name" id="name">
            </div>

            <div class="box">
                <label for="address">ที่อยู่สมาชิก:</label>
                <input type="text" name="address" id="address">

            </div>

            <div class="box">
                <label for="phone">เบอร์โทรศัพท์สมาชิก:</label>
                <input type="text" name="phone" id="phone">

            </div>

            <div class="box">
                <label for="registerdate">วันที่สมัครเป็นสมาชิก:</label>
                <input type="date" name="registerdate" id="registerdate" value="<?= date('Y-m-d') ?>" readonly>

            </div>

            <?php if ($results->rowCount() > 0) { ?>
                <div class="box">
                    <label for="typename">ประเภทสมาชิก:</label>
                    <select name="typename" id="typename">
                        <?php while ($member_insert = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo($member_insert['member_type_id']); ?>">
                                <?php echo($member_insert['member_type_name']); ?>
                            </option>
                        <?php } ?>
                    </select> 
                </div>

            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทสมาชิก</p>
            <?php } ?>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
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