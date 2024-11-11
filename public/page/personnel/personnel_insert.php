<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $personnel_name = $_POST['personnel_name'];
    $typename = $_POST['typename'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare('INSERT INTO personnel (personnel_name,  personnel_type_id, personnel_address, personnel_phone, personnel_username, personnel_password) 
                           VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $typename, $address, $phone, $username, $password]);

    header('Location: /?page=personnel_add&success=1');
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
    <link rel="stylesheet" href="../../style/personnel_insert.css">
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

    <div class="add-box">

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">เพิ่มบุคลากรสำเร็จ!</div>
        </div>


        <h2 class="sub-title">เพิ่มสมาชิก</h2>
        <form class="form-container" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
            <div class="box">
                <label for="name">ชื่อ นามสกุลบุคลากร:</label>
                <input type="text" id="name" name="name">
            </div>

            <!-- loop -->
            <?php
            if (count($personnelTypes) > 0) {
            ?>
                <div class="box">
                    <label for="typename">ประเภทบุคลากร:</label>
                    <select name="typename" id="typename">
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
            <div class="box">
                <label for="address">ที่อยู่บุคลากร:</label>
                <input type="text" id="address" name="address">
            </div>
            <div class="box">
                <label for="phone">เบอร์โทรศัพท์:</label>
                <input type="text" id="phone" name="phone">
            </div>

            <div class="box">
                <label for="username">ชื่อผู้ใช้:</label>
                <input type="text" id="username" name="username">
            </div>

            <div class="box">
                <label for="password">รหัสผ่าน:</label>
                <input type="password" id="password" name="password">
            </div>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button> <!-- แก้คำว่า "lass" เป็น "class" -->
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