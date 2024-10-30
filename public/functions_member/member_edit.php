<?php
require_once __DIR__ . '../../../config/database_server.php';

$query = "SELECT member_type_id, member_type_name FROM member_type";
$results = $pdo->query($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $registerdate = $_POST['registerdate'];
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare("
        UPDATE member 
        SET member_name = ?, member_address = ?, member_phone = ?, member_register_date = ?, member_type_id = ? 
        WHERE member_id = ?
    ");
    $stmt->execute([$name, $address, $phone, $registerdate, $typename, $id]);

    header('Location: ../member.php');  // กลับไปหน้าแสดงผล
    exit;

}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM member WHERE member_id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="/style/member_edit.css">
</head>

<body>
    <div class="main-boy">
        <h2 class="sub-title">แก้ไขสมาชิก</h2>

        <form class="form-container" action="member_edit.php" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($member['member_id']) ?>">


            <label for="name">ชื่อ นามสกุลสมาชิก:</label>
            <input type="text" name="name" value="<?= htmlspecialchars($member['member_name']) ?>">

            <label for="address">ที่อยู่สมาชิก:</label>
            <input type="text" name="address" value="<?= htmlspecialchars($member['member_address']) ?>">

            <label for="phone">เบอร์โทรศัพท์สมาชิก:</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($member['member_phone']) ?>">

            <label for="registerdate">วันที่:</label>
            <input type="date" name="registerdate" value="<?= date('Y-m-d') ?>">

            <?php if ($results->rowCount() > 0) { ?>
                <label for="typename">ประเภทสมาชิก:</label>
                <select name="typename" id="typename">
                    <option value="">-- เลือกประเภทสมาชิก --</option> <!-- ตัวเลือกว่าง -->
                    <?php while ($row = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                        <option value="<?php echo htmlspecialchars($row['member_type_id']); ?>">
                            <?php echo htmlspecialchars($row['member_type_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทสมาชิก</p>
            <?php } ?>

            <input class="submit-button" type="submit" value="Update">
        </form>
    </div>
</body>

</html>