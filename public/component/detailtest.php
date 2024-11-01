<?php
require_once '../../config/database_server.php';
require_once '../db/warehouse_db.php';


// ตรวจสอบว่ามีพารามิเตอร์ type และ id หรือไม่
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

$type = $_GET['type'];
$id = $_GET['id'];

switch ($type) {
    case 'member':
        $stmt = $pdo->prepare("
            SELECT member.*, member_type.member_type_name 
            FROM member 
            INNER JOIN member_type ON member.member_type_id = member_type.member_type_id 
            WHERE member.member_id = ?
        "); // ตรวจสอบให้แน่ใจว่าชื่อคอลัมน์ถูกต้อง
        break;
    case 'supplier':
        $stmt = $pdo->prepare("SELECT * FROM supplier WHERE supplier_id = ?");

        // 
        break;
    case 'product':
        $stmt = $pdo->prepare("
                SELECT product.*, 
                       product_type.product_type_name, 
                       product_brand.product_brand_name 
                FROM product 
                INNER JOIN product_type ON product.product_type_id = product_type.product_type_id 
                INNER JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id 
                WHERE product.product_id = ?
            ");
        break;

    case 'personnel':
        $stmt = $pdo->prepare("
                SELECT personnel.*, 
                       personnel_type.personnel_type_name 
                FROM personnel 
                LEFT JOIN personnel_type ON personnel.personnel_type_id = personnel_type.personnel_type_id 
                WHERE personnel.personnel_id = ?
            ");
        break;

    case '':
    default:
        die("ระบุประเภทไม่ถูกต้อง");
}

$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die("ไม่พบข้อมูล");
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียด</title>
    <link rel="stylesheet" href="../style/detail.css">
</head>

<body>

    <div class="main-body">
        <h1 class="title">รายละเอียด <?= htmlspecialchars($type) ?></h1>

        <table class="details-table">
            <?php if ($type === 'product'): ?>
                <tr><th>รหัสสินค้า:</th><td><?= htmlspecialchars($record['product_id']) ?></td></tr>
                <tr><th>ชื่อสินค้า:</th><td><?= htmlspecialchars($record['product_name']) ?></td></tr>
                <tr><th>บาร์โค้ด:</th><td><?= htmlspecialchars($record['product_barcode']) ?></td></tr>
                <tr><th>ราคา 1:</th><td><?= htmlspecialchars($record['product_price1']) ?></td></tr>
                <tr><th>ราคา 2:</th><td><?= htmlspecialchars($record['product_price2']) ?></td></tr>
                <tr><th>ราคา 3:</th><td><?= htmlspecialchars($record['product_price3']) ?></td></tr>
                <tr><th>ราคา 4:</th><td><?= htmlspecialchars($record['product_price4']) ?></td></tr>
                <tr><th>ราคาทุน:</th><td><?= htmlspecialchars($record['product_capital']) ?></td></tr>
                <tr><th>ประเภทสินค้า:</th><td><?= htmlspecialchars($record['product_type_name']) ?></td></tr>
                <tr><th>แบรนด์สินค้า:</th><td><?= htmlspecialchars($record['product_brand_name']) ?></td></tr>

            <?php elseif ($type === 'member'): ?>
                <tr><th>รหัสสมาชิก:</th><td><?= htmlspecialchars($record['member_id']) ?></td></tr>
                <tr><th>ชื่อสมาชิก:</th><td><?= htmlspecialchars($record['member_name']) ?></td></tr>
                <tr><th>ที่อยู่สมาชิก:</th><td><?= htmlspecialchars($record['member_address']) ?></td></tr>
                <tr><th>เบอร์โทรศัพท์:</th><td><?= htmlspecialchars($record['member_phone']) ?></td></tr>
                <tr><th>วันที่สมัคร:</th><td><?= htmlspecialchars($record['member_register_date']) ?></td></tr>
                <tr><th>ประเภทสมาชิก:</th><td><?= htmlspecialchars($record['member_type_name']) ?></td></tr>

            <?php elseif ($type === 'supplier'): ?>
                <tr><th>รหัสผู้ส่งมอบ:</th><td><?= htmlspecialchars($record['supplier_id']) ?></td></tr>
                <tr><th>ชื่อผู้ส่งมอบ:</th><td><?= htmlspecialchars($record['supplier_name']) ?></td></tr>
                <tr><th>ที่อยู่ผู้ส่งมอบ:</th><td><?= htmlspecialchars($record['supplier_address']) ?></td></tr>
                <tr><th>เบอร์โทรศัพท์:</th><td><?= htmlspecialchars($record['supplier_phone']) ?></td></tr>
                <tr><th>รายละเอียด:</th><td><?= htmlspecialchars($record['supplier_detail']) ?></td></tr>

            <?php elseif ($type === 'personnel'): ?>
                <tr><th>รหัสบุคลากร:</th><td><?= htmlspecialchars($record['personnel_id']) ?></td></tr>
                <tr><th>ชื่อบุคลากร:</th><td><?= htmlspecialchars($record['personnel_name']) ?></td></tr>
                <tr><th>ประเภทบุคลากร:</th><td><?= htmlspecialchars($record['personnel_type_name']) ?></td></tr>
                <tr><th>ที่อยู่:</th><td><?= htmlspecialchars($record['personnel_address']) ?></td></tr>
                <tr><th>เบอร์โทรศัพท์:</th><td><?= htmlspecialchars($record['personnel_phone']) ?></td></tr>
                <tr><th>ชื่อผู้ใช้งาน:</th><td><?= htmlspecialchars($record['personnel_username']) ?></td></tr>
            <?php endif; ?>
        </table>

        <a href="/?page=bar_<?= htmlspecialchars($type) ?>">กลับไปหน้าข้อมูล <?= htmlspecialchars($type) ?></a>
    </div>

</body>

</html>