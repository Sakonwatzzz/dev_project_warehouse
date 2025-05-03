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
    header("Location: lot_detail.php?tabel=lot&id=" . $lot_id);
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
    lot.lot_id, 
    lot.lot_detail, 
    lot.lot_date, 
    lot.lot_status_id, 
    lot.warehouse_id
FROM
	lot
	LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
    LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
    LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
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
    SET lot_id  = ? ,lot_detail = ?, lot_date = ?, lot_status_id = ?, 
        warehouse_id = ?, store_id = ?, supplier_id = ?
    WHERE lot_id = ?
");

    $stmt->execute([$lot_id, $lot_detail, $lot_date, $lot_status_id, $warehouse_id, $store_id, $supplier_id, $lot_id]);

    // รีไดเรกต์ไปที่หน้ารายการหลังอัปเดตสำเร็จ
    header("Location: /?page=lot_detail&tabel=lot&id=$lot_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/detail.css">
    <link href="../../output.css" rel="stylesheet">
    <title>รายละเอียด</title>
</head>

<body>
    <div class="detail-body mx-[30rem] my-11  bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md ">
        <!-- เปลี่ยนสีหัวข้อเมื่ออยู่ในโหมดแก้ไข -->
        <h2
            class="detail-title bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 <?php echo $is_update ? '' : ''; ?> text-white text-lg px-3 py-2 border border-blue-700 shadow">
            <?php echo $is_update ? 'กำลังแก้ไขล็อตสินค้า' : 'รายละเอียดล็อตสินค้า'; ?>
        </h2>
        <form class="detail-form p-2" action="../../page/lot/lot_update.php" method="POST">
            <input type="hidden" name="lot_id" value="<?php echo $lot['lot_id']; ?>">
            <table class="detail-table">
                <?php if ($tabel === 'lot'): ?>
                    <tr>
                        <th class="text-start px-7 py-2 w-[30%]">รหัสล็อต :</th>
                        <td class="border border-gray-300  w-[70%]">
                            <?php if ($is_update): ?>
                                <input type="text" name="lot_id" value="<?php echo htmlspecialchars($lot['lot_id']); ?>" class="w-full p-2 border border-gray-400  bg-gray-100" readonly>
                            <?php else: ?>
                                <span class="p-2 block"><?php echo htmlspecialchars($lot['lot_id']); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th class="text-start px-7 py-2 w-[30%]">รายละเอียดล็อต :</th>
                        <td class="border border-gray-300 w-[70%]">
                            <?php if ($is_update): ?>
                                <input type="text" name="lot_detail" id="lot-name" value="<?php echo ($lot['lot_detail']); ?>" class="w-full p-2 border border-gray-400  bg-gray-100">
                            <?php else: ?>
                                <?php echo ($lot['lot_detail']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-start px-7 py-2 w-[30%]">วันที่ล็อต :</th>
                        <td class="border border-gray-300 w-[70%]">
                            <?php if ($is_update): ?>
                                <input type="date" name="lot_date" value="<?= date('Y-m-d') ?>" class="w-full p-2 border border-gray-400  bg-gray-100">
                            <?php else: ?>
                                <?php echo ($lot['lot_date']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th class="text-start px-7 py-2 w-[30%]">สถานะล็อต :</th>
                        <td class="border border-gray-300 w-[70%]">
                            <?php if ($is_update): ?>
                                <select name="lot_status_id"  class="w-full p-2 border border-gray-400  bg-gray-100">
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
                        <th class="text-start px-7 py-2 w-[30%]">คลังสินค้า :</th>
                        <td class="border border-gray-300 w-[70%]">
                            <?php if ($is_update): ?>
                                <select name="warehouse_id" class="w-full p-2 border border-gray-400  bg-gray-100">
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
                        <th class="text-start px-7 py-2 w-[30%]">ผู้ส่งมอบ :</th>
                        <td class="border border-gray-300 w-[70%]">
                            <?php if ($is_update): ?>
                                <select name="supplier_id" class="w-full p-2 border border-gray-400  bg-gray-100">
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

            <!-- ปุ่มต่างๆ -->
            <div class="detail-action flex gap-2  justify-end<?php echo $is_update ? '' : ''; ?> mx-3 mb-4">
                <?php if ($is_update): ?>
                    <div class="flex gap-2 mt-4 justify-end">
                        <a href="/?page=lot_detail&tabel=lot&id=<?php echo $lot['lot_id']; ?>"
                            class="w-full p-2 px-5 text-center bg-red-500 text-white border rounded-lg shadow-lg hover:bg-red-600">
                            ยกเลิก
                        </a>
                        <button type="submit"
                            class="w-full p-2 px-5 bg-green-500 text-white border rounded-lg shadow-lg hover:bg-green-600">
                            บันทึก
                        </button>
                    </div>
                <?php else: ?>
                    <a href="/?page=lot_list"
                        class="mt-4 p-2 px-6 bg-gray-700 text-white border rounded-lg shadow-lg mr-2 hover:bg-gray-800">
                        ย้อนกลับ
                    </a>
                    <a href="?page=lot_detail&tabel=lot&id=<?php echo $lot['lot_id']; ?>&is_update=1"
                        class="mt-4 p-2 px-6 bg-blue-700 text-white border rounded-lg shadow-lg hover:bg-blue-800">
                        ต้องการแก้ไข
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php if ($is_update): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('lot-name').focus();
            });
        </script>
    <?php endif; ?>
</body>

</html>