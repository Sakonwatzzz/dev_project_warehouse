<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once './db/warehouse_db.php';
$results = fetchAll($pdo, 'personnel');

$query = "SELECT    
    persennel.persennel_name, 
    persennel.persennel_adress, 
    persennel.persennel_phone, 
    persennel.persennel_username , 
    persennel.persennel_type_id, 
    persennel_type.persennel_type_name
FROM
    persennel
INNER JOIN
    persennel_type ON persennel.persennel_type_id = persennel_type.persennel_type_id;
";

// Query สำหรับประเภท
$queryTypes = "SELECT
    personnel_type.personnel_type_id, 
    personnel_type.personnel_type_name
FROM personnel_type";
$typeResults = $conn->query($queryTypes); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

if (!$typeResults) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/style/product.css">


    <title>Document</title>
</head>

<body>
    <div class="personnel-list">
        <!--<div class="search">
            <p>ค้นหารายการ</p>
            <input type="search" name="" id="">
        </div> -->


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
                <?php while ($row = $results->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['personnel_id']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_name']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_type_name']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_address']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_phone']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_username']) ?></td>
                        <td>
                            <a href="functions_personnel/edit.php?id=<?= htmlspecialchars($row['personnel_id']) ?>">แก้ไข</a> |
                            <a href="functions_personnel/delete.php?id=<?= htmlspecialchars($row['personnel_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </div>
</body>

</html>