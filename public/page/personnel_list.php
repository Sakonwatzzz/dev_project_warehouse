<?php
require_once __DIR__ . '../../../config/database_server.php';
$results = fetchAll($pdo, 'personnel');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style//navbar.css">
    <title>Document</title>
</head>

<body>
    <table>
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
                        <a href="edit.php?id=<?= htmlspecialchars($row['personnel_id']) ?>">แก้ไข</a>
                        |
                        <a href="delete.php?id=<?= htmlspecialchars($row['personnel_id']) ?>"
                            onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>