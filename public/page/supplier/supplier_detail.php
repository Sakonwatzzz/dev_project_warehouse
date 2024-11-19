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
    <link rel="stylesheet" href="../../style/detail.css">
    <title>รายละเอียด</title>

</head>

<body>
    <div class="detail-body">
        <h1 class="detail-title">รายละเอียดผู้ส่งมอบ</h1>


        <form class="detail-form" action="../../page/supplier/supplier_update.php" method="POST">
            <!-- กำหนดค่า supplier_id เป็น input ซ่อน (hidden) เพื่อใช้ส่ง ID ของผู้ส่งมอบนี้ในฟอร์ม -->
            <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">

            <!-- ตารางแสดงรายละเอียดผู้ส่งมอบ -->
            <table class="detail-table">
                <?php if ($tabel === 'supplier'): ?>
                    <!-- ตรวจสอบว่าประเภทข้อมูลเป็น 'supplier' เพื่อแสดงรายละเอียดผู้ส่งมอบเท่านั้น -->

                    <tr>
                        <th>รหัสผู้ส่งมอบ:</th>
                        <!-- แสดงรหัสสมาชิก -->
                        <td>
                            <?php if ($is_update): ?>
                                <input type="hidden" name="id" value="<?php echo $supplier['supplier_id']; ?>">
                            <?php else: ?>
                                <?php echo $supplier['supplier_id']; ?>
                            <?php endif; ?>
                        </td>
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
                <button class="submit-button" type="submit"
                    style=" border-radius: 6px;
                        width: 120px;
                        height: 40px;
                        padding: 10px;
                        margin-top:10px;
                        /*border: 1px solid rgb(29, 188, 55);*/
                        background-color: #333;
                        text-decoration: none;
                        color: #fff; ">
                    บันทึก</button>
            <?php endif; ?>
        </form>

        <div class="detail-action">
            <a href="/?page=bar_supplier">ย้อนกลับหน้า List_<?php echo ($tabel); ?></a>

            <?php if ($is_update): ?>
                <a href="?page=supplier_detail&tabel=<?php echo $tabel; ?>&id=<?php echo $supplier['supplier_id']; ?>" style="margin-right: 6px;">ย้อนกลับไปที่ Detail
                </a>
            <?php endif; ?>

            <a href="?page=supplier_detail&tabel=supplier&id=<?php echo $supplier['supplier_id']; ?>&is_update=1">
                ไปหน้าแก้ไข <?php echo $tabel; ?>
            </a>
        </div>
    </div>
</body>

</html>