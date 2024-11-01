<?php
require_once __DIR__ . '/../../../config/database_server.php';
require_once __DIR__ . '../../../db/warehouse_db.php';
$results = fetchAll($pdo,'supplier');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare('INSERT INTO supplier (supplier_name,supplier_address, supplier_phone, supplier_detail) 
                           VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $address, $phone, $detail]);
    header('Location: /?page=bar_supplier');  // กลับไปหน้าแสดงผล
    exit;
}

// $search = $_GET['search'] ?? '';
// if ($search) {
//     $query = "SELECT supplier_id, supplier_name,supplier_address,supplier_phone, supplier_detail FROM supplier 
//     WHERE supplier_name LIKE ? OR supplier_address LIKE ? OR supplier_phone LIKE ? OR supplier_detail LIKE ?";
//     $stmt = $pdo->prepare($query);
//     $stmt->execute(["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
// } else {
//     $query = "SELECT supplier_id, supplier_name, supplier_address, supplier_phone, supplier_detail FROM supplier";
//     $stmt = $pdo->prepare($query);
//     $stmt->execute();
// }


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
        <!-- <form action="" method="GET">
            <input type="text" name="search" placeholder="ค้นหาผู้ส่งมอบ.......">
            <button type="submit">ค้นหา</button>

        </form> -->
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
                    <tr onclick="window.location='component/detailtest.php?type=supplier&id=<?= htmlspecialchars($row['supplier_id']) ?>'">
                        <td><?= htmlspecialchars($row['supplier_id']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_address']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_phone']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_detail']) ?></td>
                        <td>

                            <a href="functions_supplier/supplier_edit.php?id=<?= htmlspecialchars($row['supplier_id']) ?>">แก้ไข</a> |
                            <a href="functions_supplier/supplier_delete.php?id=<?= htmlspecialchars($row['supplier_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>