<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once __DIR__ . '../../../db/warehouse_db.php'; //รวมไฟล์การตั้งค่าและการเชื่อมต่อฐานข้อมูล


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
    <link rel="stylesheet" href="../style/detail.css"> <!-- ลิงก์ไปยังไฟล์ CSS เพื่อจัดการสไตล์ของหน้าเว็บ -->
</head>

<body>
    <div class="main-body">
        <!-- แสดงหัวเรื่องของหน้าเว็บโดยนำประเภทข้อมูลที่เรียกจาก URL มาทำเป็นหัวเรื่อง -->
        <h1 class="title">รายละเอียด <?php echo $tabel; ?></h1>

        <!-- ฟอร์มที่ใช้ในการส่งข้อมูลสำหรับอัพเดตรายการสินค้า โดยจะส่งไปยังไฟล์ `product_update.php` -->
        <form action="./product_update.php" method="POST">
            <!-- กำหนดค่า product_id เป็น input ซ่อน (hidden) เพื่อใช้ส่ง ID ของสินค้านี้ในฟอร์ม -->
            <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">

            <!-- ตารางแสดงรายละเอียดสินค้า -->
            <table class="details-table">
                <?php if ($tabel === 'product'): ?>
                    <!-- ตรวจสอบว่าประเภทข้อมูลเป็น 'product' เพื่อแสดงรายละเอียดสินค้าเท่านั้น -->

                    <tr>
                        <th>รหัสสินค้า:</th>
                        <!-- แสดงรหัสสินค้า -->
                        <td><?php echo $row['product_id']; ?></td>
                    </tr>

                    <tr>
                        <th>ชื่อสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                <input type="text" value="<?php echo $row['product_name']; ?>">
                            <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                <?php echo $row['product_name']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>บาร์โค้ด:</th>
                        <td>
                            <?php if ($is_update) { ?>
                                <input type="text" value="<?php echo $row['product_barcode']; ?>">
                            <?php } else { ?>
                                <?php echo $row['product_barcode']; ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่1:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?php echo $row['product_price1']; ?>">
                            <?php else: ?>
                                <?php echo $row['product_price1']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่2:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?php echo $row['product_price2']; ?>">
                            <?php else: ?>
                                <?php echo $row['product_price2']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่3:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?php echo $row['product_price3']; ?>">
                            <?php else: ?>
                                <?php echo $row['product_price3']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่4:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?php echo $row['product_price4']; ?>">
                            <?php else: ?>
                                <?php echo $row['product_price4']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาทุน:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?php echo $row['product_capital']; ?>">
                            <?php else: ?>
                                <?php echo $row['product_capital']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ประเภทสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?php echo $row['product_type_name']; ?>">
                            <?php else: ?>
                                <?php echo $row['product_type_name']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>แบรนด์สินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" value="<?php echo $row['product_brand_name']; ?>">
                            <?php else: ?>
                                <?php echo $row['product_brand_name']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endif; ?>
            </table>

            <!-- แสดงปุ่ม "บันทึก" เฉพาะในโหมดแก้ไขเท่านั้น -->
            <?php if ($is_update): ?>
                <button class="submit-button" type="submit">บันทึก</button>
            <?php endif; ?>
        </form>

        <!-- ลิงก์กลับไปยังหน้ารายการสินค้า, หน้ารายละเอียด และหน้าสำหรับแก้ไข -->
        <a href="/?page=bar_<?php echo $tabel; ?>">กลับไปหน้ารายการสินค้า <?php echo $tabel; ?></a>
        <a href="product_detail.php?tabel=<?php echo $tabel; ?>&id=<?php echo $row['product_id']; ?>">กลับไปหน้ารายละเอียด
            <?php echo $tabel; ?></a>
        <a href="product_detail.php?tabel=<?php echo $tabel; ?>&id=<?php echo $row['product_id']; ?>&is_update=1">ไปหน้าแก้ไข
            <?php echo $tabel; ?></a>
    </div>
</body>

</html>