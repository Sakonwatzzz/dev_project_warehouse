<?php
require_once '../../config/database_server.php'; //รวมไฟล์การตั้งค่าและการเชื่อมต่อฐานข้อมูล
require_once '../db/warehouse_db.php';

// ตรวจสอบว่ามีพารามิเตอร์ tabel และ id หรือไม่ ถ้าไม่มีข้อมูลในฐานข้อมูลจะหยุดการทำงานและแสดงข้อความว่าไม่พบข้อมูล
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

//กำหนดตัวแปร $tabel, $id และ $is_update จากค่าที่ส่งมาทาง GET
$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false;// กำหนดค่า $is_update ที่ด้านบน: ตอนนี้ $is_update ถูกกำหนดไว้ที่จุดเริ่มต้นโดยตรวจสอบจาก $_GET['is_update'] ว่ามีค่าหรือไม่

switch ($tabel) {
    // ใช้ switch เพื่อสร้างคำสั่ง SQL ที่แตกต่างกันตามค่าของ $tabel โดยจะเลือกตารางที่แตกต่างกัน
    case 'member':
        $stmt = $pdo->prepare("
            SELECT member.*, member_type.member_type_name 
            FROM member 
            INNER JOIN member_type ON member.member_type_id = member_type.member_type_id 
            WHERE member.member_id = ?
        ");
        break;
    case 'supplier':
        $stmt = $pdo->prepare("SELECT * FROM supplier WHERE supplier_id = ?");
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
    default:
        die("ระบุประเภทไม่ถูกต้อง");
}

$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
//รันคำสั่ง SQL ที่สร้างขึ้นและดึงข้อมูลจากฐานข้อมูล โดยจะนำผลลัพธ์มาเก็บไว้ใน $row
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
    <div class="main-body">
        <h1 class="title">รายละเอียด <?= htmlspecialchars($tabel) ?></h1>
        <form action="../functions_product/product_edit.php" method="POST">
            <input type="hidden" name="product_id" value="<?= htmlspecialchars($row['product_id']) ?>">
            <table class="details-table">
                <?php if ($tabel === 'product'): ?> 
                <!----บรรทัดนี้ใช้เพื่อตรวจสอบว่า tabel ที่ถูกส่งเข้ามาผ่านพารามิเตอร์ GET เป็นประเภท product หรือไม่ ถ้าใช่ จะเข้าไปในบล็อกของเงื่อนไขนี้และแสดงข้อมูลเกี่ยวกับสินค้า--->

                    <tr>
                        <th>รหัสสินค้า:</th>
                        <td><?= htmlspecialchars($row['product_id']) ?></td>
                    </tr>
                    <!--ถ้าตารางเป็น product จะแสดงรายละเอียดต่างๆ โดยใช้ htmlspecialchars() เพื่อป้องกันการโจมตีแบบ XSS-->
                    <tr>
                        <th>ชื่อสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?= htmlspecialchars($row['product_name']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($row['product_name']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>บาร์โค้ด:</th>
                        <td>
                            <?php if ($is_update){ ?>
                                <input type="text" value="<?= htmlspecialchars($row['product_barcode']) ?>">
                            <?php }else{ ?>
                                <?= htmlspecialchars($row['product_barcode']) ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่1:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?= htmlspecialchars($row['product_price1']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($row['product_price1']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่2:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?= htmlspecialchars($row['product_price2']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($row['product_price2']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่3:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?= htmlspecialchars($row['product_price3']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($row['product_price3']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่4:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?= htmlspecialchars($row['product_price4']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($row['product_price4']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาทุน:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?= htmlspecialchars($row['product_capital']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($row['product_capital']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ประเภทสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?= htmlspecialchars($row['product_type_name']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($row['product_type_name']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>แบรนด์สินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?= htmlspecialchars($row['product_brand_name']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($row['product_brand_name']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endif; ?>
            </table>
            <?php if ($is_update): // แสดงปุ่มบันทึกเฉพาะเมื่ออยู่ในโหมดแก้ไข ?>
                <button class="submit-button" type="submit">บันทึก</button>
            <?php endif; ?>
        </form>
        <a href="/?page=bar_<?= htmlspecialchars($tabel) ?>">กลับไปหน้ารายการสินค้า <?= htmlspecialchars($tabel) ?></a>
        <a href="detail.php?tabel=<?= htmlspecialchars($tabel) ?>&id=<?= htmlspecialchars($row['product_id']) ?>">กลับไปหน้ารายละเอียด <?= htmlspecialchars($tabel) ?></a>
        <a href="detail.php?tabel=<?= htmlspecialchars($tabel) ?>&id=<?= htmlspecialchars($row['product_id']) ?>&is_update=1">ไปหน้าแก้ไข <?= htmlspecialchars($tabel) ?></a>
            <!---ลิงก์เพื่อกลับไปยังหน้ารายการสินค้าหรือหน้ารายละเอียด จะมีลิงก์ไปยังหน้าที่สามารถแก้ไขข้อมูลได้ด้วย-->

    </div>
</body>

</html>