<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare('INSERT INTO personnel_type (personnel_type_name) 
                           VALUES (?)');
    $stmt->execute([ $typename]);

    header('Location: /index.php?page=personnel_type');
    exit;
}

// Fetch personnel types for dropdown
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
        <h2 class="sub-title">เพิ่มประเภทบุคลากร</h2>
        <form class="form-container" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
            <div class="box">
                <label for="typename">ชื่อประเภทคุลากร:</label>
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
                <th>รหัสประเภทบุคลากร</th>
                <th>ชื่อประเภทบุคลากร</th>
                <th>การจัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($personnelTypes as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['personnel_type_id']) ?></td>
                    <td><?= htmlspecialchars($row['personnel_type_name']) ?></td>
                    <td>
                        <a href="functions_personnel/type_edit.php?id=<?= htmlspecialchars($row['personnel_type_id']) ?>">แก้ไข</a> |
                        <a href="functions_personnel/type_delete.php?id=<?= htmlspecialchars($row['personnel_type_id']) ?>"
                            onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>