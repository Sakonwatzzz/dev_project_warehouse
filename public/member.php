<?php
require_once __DIR__ . '/../config/database_server.php';
require_once './db/warehouse_db.php'; // Include database connection

// SQL query to get all members with their types
$query = "SELECT
    member.member_id, 
    member.member_name, 
    member.member_address, 
    member.member_phone, 
    member.member_register_date,
    member_type.member_type_id,
    member_type.member_type_name
FROM member
INNER JOIN member_type ON member.member_type_id = member_type.member_type_id";

$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error); 
}

// SQL query to get member types for the dropdown
$queryTypes = "SELECT
    member_type.member_type_id, 
    member_type.member_type_name
FROM member_type";
$results = $conn->query($queryTypes);

if (!$results) {
    die("Query failed: " . $conn->error); // Error handling for member types query
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="style/member.css">
</head>

<body>
    <div class="body-main">
        <h1 class="title">ข้อมูลสมาชิก</h1>
        <h2 class="sub-title">เพิ่มสมาชิก</h2>

        <!-- Form for adding members -->
        <form class="form-container" action="functions_member/member_create.php" method="POST">
            <label for="name">ชื่อ นามสกุลสมาชิก:</label>
            <input type="text" name="name" id="name" required>

            <label for="address">ที่อยู่สมาชิก:</label>
            <input type="text" name="address" id="address" required>

            <label for="phone">เบอร์โทรศัพท์สมาชิก:</label>
            <input type="text" name="phone" id="phone" required>

            <label for="registerdate">วันที่:</label>
            <input type="date" name="registerdate" id="registerdate" value="<?= date('Y-m-d') ?>" required>

            <?php if ($results->num_rows > 0) { ?>
                <label for="typename">ประเภทสมาชิก:</label>
                <select name="typename" id="typename" required>
                    <option value="">-- เลือกประเภทสมาชิก --</option>
                    <?php while ($row = $results->fetch_assoc()) { ?>
                        <option value="<?= htmlspecialchars($row['member_type_id']); ?>">
                            <?= htmlspecialchars($row['member_type_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทสมาชิก</p>
            <?php } ?>

            <input class="submit-button" type="submit" value="เพิ่ม">
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>รหัส</th>
                <th>ชื่อ นามสกุล</th>
                <th>ที่อยู่สมาชิก</th>
                <th>เบอร์โทรศัพท์สมาชิก</th>
                <th>วันที่</th>
                <th>ประเภทสมาชิก</th>
                <th>การจัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['member_id']) ?></td>
                    <td><?= htmlspecialchars($row['member_name']) ?></td>
                    <td><?= htmlspecialchars($row['member_address']) ?></td>
                    <td><?= htmlspecialchars($row['member_phone']) ?></td>
                    <td><?= htmlspecialchars($row['member_register_date']) ?></td>
                    <td><?= htmlspecialchars($row['member_type_name']) ?></td> <!-- Display member type name -->
                    <td>
                        <a href="functions_member/member_edit.php?id=<?= htmlspecialchars($row['member_id']) ?>">แก้ไข</a> |
                        <a href="functions_member/member_delete.php?id=<?= htmlspecialchars($row['member_id']) ?>"
                            onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>

</html>
