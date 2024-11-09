<?php
require_once __DIR__ . '../../../../config/database_server.php';

if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $supplier_id = $_POST['supplier_id'];
    header("Location: supplier_detail.php?tabel=supplier&id=" . $supplier_id);
    exit();
}

$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false;

switch ($tabel) {
    case 'supplier':
        $stmt = $pdo->prepare("SELECT supplier.* FROM supplier WHERE supplier_id = ?");
        break;
    default:
        die("ระบุประเภทไม่ถูกต้อง");
}

$stmt->execute([$id]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$supplier) {
    echo "ไม่พบข้อมูล";
    exit();
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียด</title>
    <link rel="stylesheet" href="../../style/detail.css"> <!-- ลิงก์ไปยังไฟล์ CSS เพื่อจัดการสไตล์ของหน้าเว็บ -->
</head>

<body>
    <div class="main-body">
        <!-- แสดงหัวเรื่องของหน้าเว็บโดยนำประเภทข้อมูลที่เรียกจาก URL มาทำเป็นหัวเรื่อง -->
        <h1 class="title">รายละเอียด <?php echo $tabel; ?></h1>

        <!-- ฟอร์มที่ใช้ในการส่งข้อมูลสำหรับอัพเดตรายการผู้ส่งมอบ โดยจะส่งไปยังไฟล์ `supplier_update.php` -->
        <form action="supplier_update.php" method="POST">
            <!-- กำหนดค่า supplier_id เป็น input ซ่อน (hidden) เพื่อใช้ส่ง ID ของผู้ส่งมอบนี้ในฟอร์ม -->
            <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">

            <!-- ตารางแสดงรายละเอียดผู้ส่งมอบ -->
            <table class="details-table">
                <?php if ($tabel === 'supplier'): ?>
                    <!-- ตรวจสอบว่าประเภทข้อมูลเป็น 'supplier' เพื่อแสดงรายละเอียดผู้ส่งมอบเท่านั้น -->

                    <tr>
                        <th>รหัสผู้ส่งมอบ:</th>
                        <!-- แสดงรหัสผู้ส่งมอบ -->
                        <input type="hidden" name="id" value="<?php echo $supplier['supplier_id']; ?>">
                    </tr>
                    <tr>
                        <th>ชื่อผู้ส่งมอบ:</th>
                        <td>
                            <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                <input type="text" name="name" value="<?php echo $supplier['supplier_name']; ?>">
                            <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                <?php echo $supplier['supplier_name']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ที่อยู่ผู้ส่งมอบ:</th>
                        <td>
                            <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                <input type="text" name="address" value="<?php echo $supplier['supplier_address']; ?>">
                            <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                <?php echo $supplier['supplier_address']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
            
                    <tr>
                        <th>เบอร์ผู้ส่งมอบ:</th>
                        <td>
                            <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                <input type="text" name="phone" value="<?php echo $supplier['supplier_phone']; ?>">
                            <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                <?php echo $supplier['supplier_phone']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
            
                    <tr>
                        <th>รายละเอียดผู้ส่งมอบ:</th>
                        <td>
                            <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                <input type="text" name="detail" value="<?php echo $supplier['supplier_detail']; ?>">
                            <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                <?php echo $supplier['supplier_detail']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
            
                <?php endif; ?>
            </table>

            <!-- แสดงปุ่ม "บันทึก" เฉพาะในโหมดแก้ไขเท่านั้น -->
            <?php if ($is_update): ?>
                <button class="submit-button" type="submit">บันทึก </button>

            <?php endif; ?>
        </form>

        <!-- ลิงก์กลับไปยังหน้ารายการผู้ส่งมอบ, หน้ารายละเอียด และหน้าสำหรับแก้ไข -->
        <a href="/?page=bar_<?php echo $tabel; ?>">กลับไปหน้ารายการผู้ส่งมอบ <?php echo $tabel; ?></a>
        <a href="supplier_detail.php?tabel=<?php echo $tabel; ?>&id=<?php echo $supplier['supplier_id']; ?>">กลับไปหน้ารายละเอียด
            <?php echo $tabel; ?></a>
        <a href="supplier_detail.php?tabel=<?php echo $tabel; ?>&id=<?php echo $supplier['supplier_id']; ?>&is_update=1">ไปหน้าแก้ไข
            <?php echo $tabel; ?></a>
    </div>
</body>

</html>
