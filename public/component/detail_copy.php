<?php
require_once '../../config/database_server.php';
require_once '../db/warehouse_db.php';


// ตรวจสอบว่ามีพารามิเตอร์ tabel และ id หรือไม่
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

$tabel = $_GET['tabel'];
$id = $_GET['id'];

switch ($tabel) {
    case 'member':
        $stmt = $pdo->prepare("
            SELECT member.*, member_type.member_type_name 
            FROM member 
            INNER JOIN member_type ON member.member_type_id = member_type.member_type_id 
            WHERE member.member_id = ?
        "); // 
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
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
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
    <?php if (isset($_GET['is_update'])) {
        $is_update = $_GET['is_update'];
    }
    ?>
    <div class="main-body">
        <h1 class="title">รายละเอียด <?= htmlspecialchars($table) ?></h1>

        <table class="details-table">
            <?php if ($table === 'product'): ?>
                <tr>
                    <th>รหัสสินค้า:</th>
                    <td><?= htmlspecialchars($row['product_id']) ?></td>
                </tr>

                <!-- new code by mr.benzquery  its code look simple and Working principles that look more systematic!!-------------------------------->
                <th>ชื่อสินค้า:</th>
                <td>
                    <?php if ($is_update) { ?>
                        <input type="text" value="  <?php echo $row['product_name']; ?>" placeholder="benz">
                    <?php } else { ?>

                        <?php echo $row['product_name']; ?>
                    <?php } ?>

                </td>
                
                <tr>
                    <th>บาร์โค้ด:</th>
                    
                        <?php if ($is_update) { ?>
                            <input type="text" value=" <?php echo $row['product_barcode']; ?>">
                        <?php } else { ?>

                            <?php echo $row['product_barcode']; ?>
                        <?php } ?>
                </tr>
                <tr>
                    <th>ราคาที่1:</th>
                    <?php if ($is_update) { ?>
                            <input type="text" value=" <?php echo $row['product_price1']; ?>">
                        <?php } else { ?>

                            <?php echo $row['product_price1']; ?>
                        <?php } ?>
                </tr>
                <tr>
                    <th>ราคาที่2:</th>
                    <?php if ($is_update) { ?>
                            <input type="text" value=" <?php echo $row['product_price2']; ?>">
                        <?php } else { ?>

                            <?php echo $row['product_price2']; ?>
                        <?php } ?>
                </tr>
                <tr>
                    <th>ราคาที่3:</th>
                    <?php if ($is_update) { ?>
                            <input type="text" value=" <?php echo $row['product_price3']; ?>">
                        <?php } else { ?>

                            <?php echo $row['product_price3']; ?>
                        <?php } ?>
                </tr>
                <tr>
                    <th>ราคาที่4:</th>
                    <?php if ($is_update) { ?>
                            <input type="text" value=" <?php echo $row['product_price4']; ?>">
                        <?php } else { ?>

                            <?php echo $row['product_price4']; ?>
                        <?php } ?>
                </tr>
                <tr>
                    <th>ราคาทุน:</th>
                    <?php if ($is_update) { ?>
                            <input type="text" value=" <?php echo $row['product_capital']; ?>">
                        <?php } else { ?>

                            <?php echo $row['product_capital']; ?>
                        <?php } ?>
                </tr>
                <tr>
                    <th>ประเภทสินค้า:</th>
                    <?php if ($is_update) { ?>
                            <input type="text" value=" <?php echo $row['product_type_name']; ?>">
                        <?php } else { ?>

                            <?php echo $row['product_type_name']; ?>
                        <?php } ?>
                </tr>
                <tr>
                    <th>แบรนด์สินค้า:</th>
                    <?php if ($is_update) { ?>
                            <input type="text" value=" <?php echo $row['product_brand_name']; ?>">
                        <?php } else { ?>

                            <?php echo $row['product_brand_name']; ?>
                        <?php } ?>
                </tr>


                <!------------------------old code by tar but its code incorrect for mr.benz-------------------- -->
            <?php elseif ($type === 'member'): ?>
                <tr>
                    <th>รหัสสมาชิก:</th>
                    <td><?= htmlspecialchars($record['member_id']) ?></td>
                </tr>
                <tr>
                    <th>ชื่อสมาชิก:</th>
                    <td><?= htmlspecialchars($record['member_name']) ?></td>
                </tr>
                <tr>
                    <th>ที่อยู่สมาชิก:</th>
                    <td><?= htmlspecialchars($record['member_address']) ?></td>
                </tr>
                <tr>
                    <th>เบอร์โทรศัพท์:</th>
                    <td><?= htmlspecialchars($record['member_phone']) ?></td>
                </tr>
                <tr>
                    <th>วันที่สมัคร:</th>
                    <td><?= htmlspecialchars($record['member_register_date']) ?></td>
                </tr>
                <tr>
                    <th>ประเภทสมาชิก:</th>
                    <td><?= htmlspecialchars($record['member_type_name']) ?></td>
                </tr>

            <?php elseif ($type === 'supplier'): ?>
                <tr>
                    <th>รหัสผู้ส่งมอบ:</th>
                    <td><?= htmlspecialchars($record['supplier_id']) ?></td>
                </tr>
                <tr>
                    <th>ชื่อผู้ส่งมอบ:</th>
                    <td><?= htmlspecialchars($record['supplier_name']) ?></td>
                </tr>
                <tr>
                    <th>ที่อยู่ผู้ส่งมอบ:</th>
                    <td><?= htmlspecialchars($record['supplier_address']) ?></td>
                </tr>
                <tr>
                    <th>เบอร์โทรศัพท์:</th>
                    <td><?= htmlspecialchars($record['supplier_phone']) ?></td>
                </tr>
                <tr>
                    <th>รายละเอียด:</th>
                    <td><?= htmlspecialchars($record['supplier_detail']) ?></td>
                </tr>

            <?php elseif ($type === 'personnel'): ?>
                <tr>
                    <th>รหัสบุคลากร:</th>
                    <td><?= htmlspecialchars($record['personnel_id']) ?></td>
                </tr>
                <tr>
                    <th>ชื่อบุคลากร:</th>
                    <td><?= htmlspecialchars($record['personnel_name']) ?></td>
                </tr>
                <tr>
                    <th>ประเภทบุคลากร:</th>
                    <td><?= htmlspecialchars($record['personnel_type_name']) ?></td>
                </tr>
                <tr>
                    <th>ที่อยู่:</th>
                    <td><?= htmlspecialchars($record['personnel_address']) ?></td>
                </tr>
                <tr>
                    <th>เบอร์โทรศัพท์:</th>
                    <td><?= htmlspecialchars($record['personnel_phone']) ?></td>
                </tr>
                <tr>
                    <th>ชื่อผู้ใช้งาน:</th>
                    <td><?= htmlspecialchars($record['personnel_username']) ?></td>
                </tr>
            <?php endif; ?>
        </table>

        <a href="/?page=bar_product<?= htmlspecialchars($table) ?>">กลับไปหน้าข้อมูล <?= htmlspecialchars($table) ?></a>
    </div>

</body>

</html>