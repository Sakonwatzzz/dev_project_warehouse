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
    <link rel="stylesheet" href=".././style/personnel_add.css">
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

        <form class="form-container" action="member_edit.php" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($member['member_id']) ?>">

            <div class="box">
                <label for="name">ชื่อ นามสกุลสมาชิก:</label>
                <input type="text" name="name" value="<?= htmlspecialchars($member['member_name']) ?>">
            </div>

            <div class="box">
                <label for="address">ที่อยู่สมาชิก:</label>
                <input type="text" name="address" value="<?= htmlspecialchars($member['member_address']) ?>">
            </div>

            <div class="box">
                <label for="phone">เบอร์โทรศัพท์สมาชิก:</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($member['member_phone']) ?>">
            </div>

            <div class="box">
                <label for="registerdate">วันที่:</label>
                <input type="date" name="registerdate" value="<?= htmlspecialchars($member['member_register_date']) ?>">

            </div>

            <?php if ($results->rowCount() > 0) { ?>
                <div class="box">
                    <label for="typename">ประเภทสมาชิก:</label>
                    <select name="typename" id="typename">
                        <option value="">-- เลือกประเภทสมาชิก --</option>
                        <?php while ($row = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo htmlspecialchars($row['member_type_id']); ?>"
                                <?php if ($row['member_type_id'] == $member['member_type_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($row['member_type_name']); ?>
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