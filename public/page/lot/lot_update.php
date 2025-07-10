<?php
require_once __DIR__ . '../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_id = $_POST['lot_id'];
    $lot_detail = $_POST['lot_detail'];
    $lot_date = $_POST['lot_date'];
    $lot_status_id = $_POST['lot_status_id'];
    $warehouse_id = $_POST['warehouse_id'];
    $supplier_id = $_POST['supplier_id'];

    $stmt = $pdo->prepare("
    UPDATE lot 
    SET lot_detail = ?, lot_date = ?, lot_status_id = ?, warehouse_id = ?, supplier_id = ?
    WHERE lot_id = ?
    ");
    $stmt->execute([
        $lot_detail,
        $lot_date,
        $lot_status_id,
        $warehouse_id,
        $supplier_id,
        $lot_id
    ]);
    // รีไดเรกต์ไปที่หน้ารายการหลังอัปเดตสำเร็จ
    header("Location: /?page=lot_detail&tabel=lot&id=$lot_id");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM lot WHERE lot_id = ?");
$stmt->execute([$id]);
$lot = $stmt->fetch();

// ดึงชื่อคลังมาแสดงผล
$query = "SELECT lot_status_id, lot_status_name FROM lot_status";
$stmt = $pdo->prepare($query);
$stmt->execute();
$status_lot = $stmt;
// ดึงชื่อคลังมาแสดงผล
$query = "SELECT warehouse_id, warehouse_name FROM warehouse";
$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouse_lot = $stmt;
// ดึงชื่อผู่ส่งมอบมาแสดงผล
$query = "SELECT supplier_id, supplier_name FROM supplier";
$stmt = $pdo->prepare($query);
$stmt->execute();
$supplier_lot = $stmt;


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>แก้ไขข้อมูลการรับรายการสินค้า</title>

</head>

<body class="bg-[#E6F2FF] font-[Tahoma]">
    <div class="mx-[20rem] mt-11 bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
        <div class="bg-[#0046AA] text-white text-2xl font-bold p-5">
            แก้ไขข้อมูลการรับรายการสินค้า
        </div>

        <form class="p-5  space-y-2" action="" method="POST">
            <input type="hidden" name="lot_id" value="<?php echo ($lot['lot_id']) ?>">

            <div class="flex items-center ">
                <label class="w-[110px] text-base mr-4">รายละเอียดล็อต</label>
                <input
                    type="text"
                    name="lot_detail"
                    class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner"
                    value="<?php echo ($lot['lot_detail']) ?>">
            </div>

            <div class="flex items-center">
                <label class="w-[110px] text-base mr-4">วันที่รับสินค้า</label>
                <input
                    type="date"
                    name="lot_date"
                    class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner"
                    value="<?= date('Y-m-d') ?>"
                    readonly>
            </div>
            <?php if ($status_lot->rowCount() > 0): ?>
                <div class="flex items-center">
                    <label class="w-[110px] text-base mr-4">สถานะล็อต</label>
                    <select
                        name="lot_status_id"
                        class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                        <?php while ($status_lot_update = $status_lot->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $status_lot_update['lot_status_id']; ?>">
                                <?php echo $status_lot_update['lot_status_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($warehouse_lot->rowCount() > 0): ?>
                <div class="flex items-center">
                    <label class="w-[110px] text-base mr-4">คลังสินค้า</label>
                    <select
                        name="warehouse_id"
                        class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                        <?php while ($warehouse_lot_update = $warehouse_lot->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $warehouse_lot_update['warehouse_id']; ?>">
                                <?php echo $warehouse_lot_update['warehouse_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>


            <?php if ($supplier_lot->rowCount() > 0): ?>
                <div class="flex items-center">
                    <label class="w-[110px] text-base mr-4">ผู้ส่งมอบ</label>
                    <select
                        name="supplier_id"
                        class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                        <?php foreach ($supplier_lot as $supplier_lot_update): ?>
                            <option value="<?php echo $supplier_lot_update['supplier_id']; ?>">
                                <?php echo htmlspecialchars($supplier_lot_update['supplier_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="flex justify-end mt-5 gap-2">
                <a href="/?page=lot_list" class="
                        bg-[#e1e1e1] 
                        text-black 
                        border-2 
                        border-[#A0A0A0] 
                        border-t-white 
                        border-l-white 
                        px-4 
                        py-1 
                        rounded 
                        shadow-md 
                        hover:bg-[#F1F1F1]
                        active:border-[#A0A0A0]
                        active:border-b-white
                        active:border-r-white
                        active:shadow-inner 
                    ">ยกเลิก</a>
                <button
                    type="submit"
                    class="
                        bg-[#e1e1e1] 
                        text-black 
                        border-2 
                        border-[#A0A0A0] 
                        border-t-white 
                        border-l-white 
                        px-4 
                        py-1 
                        rounded 
                        shadow-md 
                        hover:bg-[#F1F1F1]
                        active:border-[#A0A0A0]
                        active:border-b-white
                        active:border-r-white
                        active:shadow-inner
                    ">
                    บันทึก
                </button>
            </div>
        </form>
    </div>
</body>

</html>