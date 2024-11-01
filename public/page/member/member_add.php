<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once './db/warehouse_db.php'; // Include database connection
$resultmember = fetchAll($pdo, 'member');

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
    <link rel="stylesheet" href="../../style/personnel_add.css">
</head>

<body>
    <div class="add-box">
        <h2 class="sub-title">เพิ่มสมาชิก</h2>

        <!-- Form for adding members -->
        <form class="form-container" action="functions_member/member_create.php" method="POST">
            <div class="box">
                <label for="name">ชื่อ นามสกุลสมาชิก:</label>
                <input type="text" name="name" id="name" required>
            </div>

            <div class="box">
                <label for="address">ที่อยู่สมาชิก:</label>
                <input type="text" name="address" id="address" required>

            </div>

            <div class="box">
                <label for="phone">เบอร์โทรศัพท์สมาชิก:</label>
                <input type="text" name="phone" id="phone" required>

            </div>

            <div class="box">
                <label for="registerdate">วันที่:</label>
                <input type="date" name="registerdate" id="registerdate" value="<?= date('Y-m-d') ?>" required>

            </div>

            <?php if ($results->num_rows > 0) { ?>
                <div class="box">
                    <label for="typename">ประเภทสมาชิก:</label>
                    <select name="typename" id="typename" required>
                        <option value="">-- เลือกประเภทสมาชิก --</option>
                        <?php while ($row = $results->fetch_assoc()) { ?>
                            <option value="<?= htmlspecialchars($row['member_type_id']); ?>">
                                <?= htmlspecialchars($row['member_type_name']); ?>
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