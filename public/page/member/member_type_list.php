<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare('INSERT INTO member_type (member_type_name) VALUES (?)');
    $stmt->execute([$typename]);

    header('Location: /index.php?page=member_type');
    exit;
}

// Fetch member types for dropdown
$query = "SELECT member_type_id, member_type_name FROM member_type";
$stmt = $pdo->prepare($query);
$stmt->execute();

try {
    $member_Types = $pdo->query($query);
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM member_type WHERE member_type_id = ?");
    $stmt->execute([$id]);
    header("Location: /index.php?page=member_type");
    exit;
}
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
        <h2 class="sub-title">เพิ่มประเภทสมาชิก</h2>
        <form class="form-container" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
            <div class="box">
                <label for="typename">ชื่อประเภทสมาชิก:</label>
                <input type="text" id="typename" name="typename" required>
            </div>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
                <!-- แก้คำว่า "lass" เป็น "class" -->
            </div>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>รหัสประเภทสมาชิก</th>
                <th>ชื่อประเภทสมาชิก</th>
                <th>การจัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($member_type_list = $member_Types->fetch(PDO::FETCH_ASSOC)): ?>
                <tr>
                    <td><?php echo ($member_type_list['member_type_id']) ?></td>
                    <td><?php echo ($member_type_list['member_type_name']) ?></td>
                    <td>
                        <a
                            href="page/member/member_type_update.php?id=<?php echo ($member_type_list['member_type_id']) ?>">แก้ไข</a>
                        |
                        <a href="?id=<?php echo ($member_type_list['member_type_id']) ?>"
                            onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>

</html>