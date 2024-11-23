<?php
// เรียกไฟล์ที่ใช้ในการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . "../../../../config/database_server.php";
// ตรวจสอบว่ามีการระบุประเภท (tabel) และ ID (id) มาหรือไม่ ถ้าไม่เจอให้หยุดการทำงาน
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

// ตรวจสอบว่าเป็นการเรียกด้วย POST (เมื่อมีการส่งข้อมูลจากฟอร์ม)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lot_id = $_POST['lot_id'];
    // ทำการรีไดเรกต์ไปที่หน้ารายละเอียดสินค้า
    header("Location: lot_detail.php?tabel=lot&id=" .$lot_id);
    exit();
}


// ดึงค่าตัวแปรจาก URL เพื่อระบุประเภทและ ID
$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false; // เช็คว่าอยู่ในโหมดแก้ไขหรือไม่


// ตรวจสอบประเภทที่กำหนดมาและทำการ query เพื่อดึงข้อมูลสินค้า
switch ($tabel) {
    case 'lot':
        // เตรียม query เพื่อดึงข้อมูลสินค้าพร้อมข้อมูลประเภทและแบรนด์
        $stmt = $pdo->prepare("SELECT
    lot_status.lot_status_id, 
    lot_status.lot_status_name, 
    supplier.supplier_id, 
    supplier.supplier_name, 
    warehouse.warehouse_name, 
    warehouse.warehouse_id,
    store.store_id, 
    store.store_name, 
    lot.lot_id, 
    lot.lot_detail, 
    lot.lot_date, 
    lot.lot_status_id, 
    lot.warehouse_id, 
    lot.store_id
FROM
	lot
	LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
    LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
    LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
    LEFT JOIN store ON lot.store_id = store.store_id

        WHERE lot.lot_id = ?
        ");
        break;
    default:
        die("ระบุประเภทไม่ถูกต้อง");
}


// ดำเนินการ query ด้วย product_id ที่ระบุ
$stmt->execute([$id]);
$lot = $stmt->fetch(PDO::FETCH_ASSOC); // เก็บผลลัพธ์เป็นแถวข้อมูลในรูปแบบ array
if (!$lot) {
    echo "ไม่พบข้อมูล";
    exit();
}
// ดึงชื่อคลังมาแสดงผล
$querylot = "SELECT lot_status_id, lot_status_name FROM lot_status";
$status_lot = $pdo->query($querylot);

// ดึงชื่อคลังมาแสดงผล
$querywarehoue = "SELECT warehouse_id, warehouse_name FROM warehouse";
$warehouse_lot = $pdo->query($querywarehoue);

// ดึงชื่อผู่ส่งมอบมาแสดงผล
$querysupplier = "SELECT supplier_id, supplier_name FROM supplier";
$supplier_lot = $pdo->query($querysupplier);


$querystore = "SELECT store_id, store_name FROM store";
$store_lot = $pdo->query($querystore);



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_id = $_POST['lot_id'];
    $lot_detail = $_POST['lot_detail'];
    $lot_date = $_POST['lot_date'];
    $lot_status_id = $_POST['lot_status_id'];
    $warehouse_id = $_POST['warehouse_id'];
    $store_id = $_POST['store_id'];
    $supplier_id = $_POST['supplier_id'];


    $stmt = $pdo->prepare("
    UPDATE lot 
    SET lot_id = ?, lot_detail = ?, lot_date = ?, lot_status_id = ?, 
        warehouse_id = ?, store_id = ?, supplier_id = ?
    WHERE lot_id = ?
");

    $stmt->execute([$lot_id, $lot_detail, $lot_date, $lot_status_id, $warehouse_id, $store_id, $supplier_id, $lot_id]);

    // รีไดเรกต์ไปที่หน้ารายการหลังอัปเดตสำเร็จ
    header("Location: ?page=lot_detail&tabel=lot&id=25&success=1");
    exit;
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
        <h2 class="detail-title">รายละเอียดล็อต</h2>
        <form class="detail-form" action="../../page/lot/lot_detail.php" method="POST">
            <input type="hidden" name="lot_id" value="<?php echo $lot['lot_id']; ?>">
            <table class="detail-table">
                <?php if ($tabel === 'lot'): ?>
                    <tr>
                        <th>รหัสล็อต:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="id" name="lot_id" value="<?php echo ($lot['lot_id']); ?>" readonly>
                            <?php else: ?>
                                <?php echo ($lot['lot_id']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>รายละเอียดล็อต:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="lot_detail" value="<?php echo ($lot['lot_detail']); ?>">
                            <?php else: ?>
                                <?php echo ($lot['lot_detail']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>วันที่การล็อต:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="date" name="lot_date" value="<?php echo ($lot['lot_date']); ?>">
                            <?php else: ?>
                                <?php echo ($lot['lot_date']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>สถานะล็อต:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <select name="lot_status_name">
                                    <option value="<?php echo ($lot['lot_status_id']); ?>" selected>
                                        <?php echo ($lot['lot_status_name']); ?>
                                    </option>
                                    <?php while ($lot_status_result = $status_lot->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo ($lot_status_result['lot_status_id']); ?>">
                                            <?php echo ($lot_status_result['lot_status_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <?php echo ($lot['lot_status_name']); ?>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>คลังสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <select name="warehouse_name">
                                    <option value="<?php echo ($lot['warehouse_id']); ?>" selected>
                                        <?php echo ($lot['warehouse_name']); ?>
                                    </option>
                                    <?php while ($warehouse_result = $warehouse_lot->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo ($warehouse_result['warehouse_id']); ?>">
                                            <?php echo ($warehouse_result['warehouse_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <?php echo ($lot['warehouse_name']); ?>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                   
                    <tr>
                        <th>ร้านค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <select name="store_name">
                                    <option value="<?php echo ($lot['store_id']); ?>" selected>
                                        <?php echo ($lot['store_name']); ?>
                                    </option>
                                    <?php while ($store_result = $store_lot->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo ($store_result['store_id']); ?>">
                                            <?php echo ($store_result['store_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            <?php else: ?>
                                <?php echo ($lot['store_name']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ผู้ส่งมอบ:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <select name="supplier_name">
                                    <option value="<?php echo ($lot['supplier_id']); ?>" selected>
                                        <?php echo ($lot['supplier_name']); ?>
                                    </option>
                                    <?php while ($supplier_result = $supplier_lot->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo ($supplier_result['supplier_id']); ?>">
                                            <?php echo ($supplier_result['supplier_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            <?php else: ?>
                                <?php echo ($lot['supplier_name']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>

            <!-- แสดงปุ่ม "บันทึก" เฉพาะในโหมดแก้ไขเท่านั้น -->
            <?php if ($is_update): ?>
                <button class="submit-button" type="submit" style=" border-radius: 6px;
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
            <a href="/?page=lot_list">ย้อนกลับหน้ารายการ<?php echo ($tabel); ?></a>

            <?php if ($is_update): ?>
                <a href="?page=lot_detail&tabel=<?php echo $tabel; ?>&id=<?php echo $lot['lot_id']; ?>"
                    style="margin-right: 6px;">ย้อนกลับไปที่ Detail
                </a>
            <?php endif; ?>

            <a href="?page=lot_detail&tabel=lot&id=<?php echo $lot['lot_id']; ?>&is_update=1">
                ไปหน้าแก้ไข <?php echo $tabel; ?>
            </a>
        </div>



    </div>
</body>

</html>