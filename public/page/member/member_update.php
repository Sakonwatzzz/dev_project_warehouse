<?php
require_once __DIR__ . '../../../../config/database_server.php';

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

    header('Location: /?page=member_list&success=1');
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
    <link rel="stylesheet" href="../../style/member_edit.css">
    <style>
        .edit-box {
            margin: 3rem 10rem 4rem 8rem;
        }
    </style>
    <title>Document</title>
</head>

<body>
    <div class="edit-box">
        <h2 class="sub-title">แก้ไขสมาชิก</h2>

        <form class="form-container" action="member_update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo ($member['member_id']) ?>">

            <div class="box">
                <label for="name">ชื่อกุลสมาชิก:</label>
                <input type="text" name="name" value="<?php echo ($member['member_name']) ?>">
            </div>

            <div class="box">
                <label for="address">ที่อยู่สมาชิก:</label>
                <input type="text" name="address" value="<?php echo ($member['member_address']) ?>">
            </div>

            <div class="box">
                <label for="phone">เบอร์โทรศัพท์สมาชิก:</label>
                <input type="text" name="phone" value="<?php echo ($member['member_phone']) ?>">
            </div>

            <div class="box">
                <label for="registerdate">วันที่สมัคเป็นสมาชิก:</label>
                <input type="date" name="registerdate"
                    value="<?php echo !empty($member['member_register_date']) ? $member['member_register_date'] : date('Y-m-d'); ?>">

            </div>

            <?php if ($results->rowCount() > 0) { ?>
                <div class="box">
                    <label for="typename">ประเภทสมาชิก:</label>
                    <select name="typename" id="typename">
                        <?php while ($member_update = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($member_update['member_type_id']); ?>"
                                <?php if ($member_update['member_type_id'] == $member['member_type_id']) echo 'selected'; ?>>
                                <?php echo ($member_update['member_type_name']); ?>
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
</body>

</html>