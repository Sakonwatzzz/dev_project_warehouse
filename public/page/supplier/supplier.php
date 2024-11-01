<?php
require_once __DIR__ . '/../config/database_server.php';
$results = fetchAll($pdo, 'supplier');
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
        <h1 class="title">ข้อมูลผู้ส่งมอบ</h1>

        <h2 class="sub-title">เพิ่มข้อมูลผู้ส่งมอบ</h2>
        <form class="form-container" action="supplier_create.php" method="POST">
            <label for="name">ชื่อผู้ส่งมอบ:</label>
            <input type="text" id="name" name="name" required>

            <label for="address">ที่อยู่ผู้ส่งมอบ:</label>
            <input type="text" id="address" name="address" required>

            <label for="phone">เบอร์โทรศัพท์:</label>
            <input type="text" id="phone" name="phone" required>

            <label for="detail">รายละเอียด:</label>
            <input type="text" id="detail" name="detail" required>

            <input class="submit-button" type="submit" value="เพิ่ม">
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ชื่อ</th>
                    <th>ที่อยู่</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>รายละเอียด</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['supplier_id']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_address']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_phone']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_detail']) ?></td>
                        <td>
                            <a href="supplier_edit.php?id=<?= htmlspecialchars($row['supplier_id']) ?>">แก้ไข</a> |
                            <a href="supplier_delete.php?id=<?= htmlspecialchars($row['supplier_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>
