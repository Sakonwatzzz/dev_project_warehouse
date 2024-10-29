<?php
require_once __DIR__ . '/../config/database_server.php';
$result = fetchAll($pdo, 'personnel');

include_once("./db/warehouse_db.php");

$query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$results = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าบุคลากร</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style/index.css">
</head>

<body>
    <div class="main-body">
        <h1 class="title">ข้อมูลบุคลากร</h1>

        <h2 class="sub-title">เพิ่มบุคลากร</h2>
        <form class="form-container" action="create.php" method="POST">
            <label for="name">ชื่อ นามสกุลบุคลากร:</label>
            <input type="text" id="name" name="name" required>

<!-- loop -->
            <?php
            if ($results->num_rows > 0) {
                ?>
                <label for="typename">ประเภทบุคคลากร:</label>
                <select name="typename" id="typename" required>
                    <?php while ($row = $results->fetch_assoc()) { ?>
                        <option value="<?php echo htmlspecialchars($row['personnel_type_id']); ?>">
                            <?php echo htmlspecialchars($row['personnel_type_name']); ?>
                        </option>
                    <?php } ?>
                </select><br>
                <?php
            } else {
                echo "No personnel types available.";
            }
            ?>
<!--  -->

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
                    <th>ชื่อ นามสกุล</th>
                    <th>ประเภทบุคคลากร</th>
                    <th>ที่อยู่</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>ชื่อผู้ใช้</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['personnel_id']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_name']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_type_id']) ?></td>
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