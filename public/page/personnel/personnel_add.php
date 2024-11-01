<?php
// ตรวจสอบให้แน่ใจว่าไม่มีช่องว่างหรือข้อความก่อน <?php ที่นี่

require_once __DIR__ . '../../../../config/database_server.php';

// ส่วนของโค้ดที่เหลือของคุณ...

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบการรับค่าข้อมูล
    $name = $_POST['name'];
    $typename = $_POST['typename'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Insert ข้อมูลไปยังฐานข้อมูล
    $stmt = $pdo->prepare('INSERT INTO personnel (personnel_name, personnel_type_id, personnel_address, personnel_phone, personnel_username, personnel_password) 
                           VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $typename, $address, $phone, $username, $password]);

    // เปลี่ยนเส้นทาง
    header('Location: /index.php?page=bar_personnel');
    exit;
}

// ดึงประเภทบุคลากรมาแสดงผล
$query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$stmt = $pdo->prepare($query);
$stmt->execute();
$personnelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_add.css">
    <title>Document</title>
</head>

<body>

    <div class="add-box">
        <h2 class="sub-title">เพิ่มสมาชิก</h2>
        <form class="form-container" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
            <div class="box">
                <label for="name">ชื่อ นามสกุลบุคลากร:</label>
                <input type="text" id="name" name="name" required>
            </div>

            <!-- loop -->
            <?php
            if (count($personnelTypes) > 0) {
            ?>
                <div class="box">
                    <label for="typename">ประเภทบุคลากร:</label>
                    <select name="typename" id="typename" required>
                        <?php foreach ($personnelTypes as $row) { ?> <!-- เปลี่ยนเป็น foreach -->
                            <option value="<?php echo htmlspecialchars($row['personnel_type_id']); ?>">
                                <?php echo htmlspecialchars($row['personnel_type_name']); ?>
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
                <input type="text" id="address" name="address" required>
            </div>
            <div class="box">
                <label for="phone">เบอร์โทรศัพท์:</label>
                <input type="text" id="phone" name="phone" required>
            </div>

            <div class="box">
                <label for="username">ชื่อผู้ใช้:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="box">
                <label for="password">รหัสผ่าน:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button> <!-- แก้คำว่า "lass" เป็น "class" -->
            </div>
        </form>
    </div>
</body>

</html>
