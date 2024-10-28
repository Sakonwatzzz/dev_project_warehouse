<?php
require_once __DIR__ . '/../config/database_server.php';
$results = fetchAll($pdo, 'personnel');
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าบุคลากร</title>
    <link rel="stylesheet" href="style/index.css">
</head>

<body>
    <div class="main-body">
        <h1 class="title">ข้อมูลบุคลากร</h1>

        <h2 class="sub-title">เพิ่มบุคลากร</h2>
        <form class="form-container" action="create.php" method="POST">
            <label for="name">ชื่อบุคลากร:</label>
            <input type="text" id="name" name="name" required>

            <label for="surname">นามสกุลบุคลากร:</label>
            <input type="text" id="surname" name="surname" required>

            <label for="address">ที่อยู่บุคลากร:</label>
            <input type="text" id="address" name="address" required>

            <label for="phone">เบอร์โทรศัพท์:</label>
            <input type="text" id="phone" name="phone" required>

            <label for="username">ชื่อผู้ใช้:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">รหัสผ่าน:</label>
            <input type="password" id="password" name="password" required>

            <input class="submit-button" type="submit" value="เพิ่ม">
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ชื่อ</th>
                    <th>นามสกุล</th>
                    <th>ที่อยู่</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>ชื่อผู้ใช้</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['personnel_id']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_name']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_surname']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_address']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_phone']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_username']) ?></td>
                        <td>
                            <a href="edit.php?id=<?= htmlspecialchars($row['personnel_id']) ?>">แก้ไข</a> |
                            <a href="delete.php?id=<?= htmlspecialchars($row['personnel_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>