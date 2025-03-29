<?php
require_once __DIR__ . '../../../../config/database_server.php';

function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// ดึงข้อมูลรายการย้ายคลัง
$query = "SELECT
    lot.lot_id, 
    lot.lot_name, 
    lot.lot_date, 
    lot.lot_status_id, 
    warehouse_from.warehouse_name AS from_warehouse_name,
    warehouse_to.warehouse_name AS to_warehouse_name,
    lot_status.lot_status_name
FROM lot
LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
LEFT JOIN warehouse AS warehouse_from ON lot.warehouse_from_id = warehouse_from.warehouse_id
LEFT JOIN warehouse AS warehouse_to ON lot.warehouse_id = warehouse_to.warehouse_id
LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
WHERE lot.warehouse_inout = 2
ORDER BY lot.lot_date DESC";

$transfer_list = fetchData($pdo, $query);

// Group the transfer list by to_warehouse_name
$grouped_by_warehouse = [];
foreach ($transfer_list as $transfer) {
    $grouped_by_warehouse[$transfer['to_warehouse_name']][] = $transfer;
}

$warehouse_lot_counts = [];
foreach ($grouped_by_warehouse as $to_warehouse_name => $transfers) {
    $warehouse_lot_counts[$to_warehouse_name] = count($transfers);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการโยกย้ายสินค้า</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>

<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow-lg mt-6">
        
        <h2 class="text-2xl font-bold text-gray-800 mb-4">📦 รายการ Lot ที่ต้องรับ</h2>
        <h3 class="text-xl font-bold text-gray-800 mb-4">คลังที่ต้องรับ</h3>
        <!-- กล่องแสดงข้อมูลแต่ละคลัง -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8 ">
            <?php foreach ($grouped_by_warehouse as $to_warehouse_name => $transfer): ?>
                <div class="bg-white  rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 cursor-pointer"
                onclick="window.location.href='?page=lot_insertdetailout&tabel=lot&id=<?= htmlspecialchars($transfer[0]['lot_id']) ?>'">
                    <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                        <h3 class="text-xl font-semibold text-gray-800">
                            <?= htmlspecialchars($to_warehouse_name) ?>
                        </h3>
                    </div>
                    <div class="p-6 justify-between items-center ">
                        <p class="text-gray-600 mb-2"><i class="fas fa-box mr-2"></i>จำนวน Lot การย้ายคลังทั้งหมด</p>
                        <p class="text-3xl font-bold text-blue-600">
                            <?= $warehouse_lot_counts[$to_warehouse_name] ?? 0 ?> รายการ
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ข้อความแนะนำเพิ่มเติม -->
        <div class="text-center mt-6">
            <p class="text-sm text-gray-500"><i class="fas fa-info-circle mr-1"></i> คลิกเพื่อดูรายละเอียด Lot
                ทั้งหมดในคลังนี้</p>
        </div>
    </div>

    <!-- ตารางข้อมูลรายการการย้ายคลัง -->
    <div class="max-w-6xl mx-auto mt-6 bg-white p-6 rounded-lg shadow-lg">
        <table class="w-full border-collapse bg-white shadow-md">
            <thead>
                <tr class="bg-gray-200 text-left">
                    <th class="p-3 border">เลขที่ Lot</th>
                    <th class="p-3 border">วันที่</th>
                    <th class="p-3 border">นำมาจากคลัง</th>
                    <th class="p-3 border">ย้ายไปยังคลัง</th>
                    <th class="p-3 border" hidden>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transfer_list as $transfer): ?>
                    <tr class="border-t hover:bg-blue-100 cursor-pointer" onclick="window.location.href='?page=lot_insertdetailout&tabel=lot&id=<?= htmlspecialchars($transfer['lot_id']) ?>'">
                        <td class="p-3 border"><?= htmlspecialchars($transfer['lot_name']) ?></td>
                        <td class="p-3 border"><?= htmlspecialchars($transfer['lot_date']) ?></td>
                        <td class="p-3 border"><?= htmlspecialchars($transfer['from_warehouse_name'] ?? '-') ?></td>
                        <td class="p-3 border"><?= htmlspecialchars($transfer['to_warehouse_name'] ?? '-') ?></td>
                        <td class="p-3 border" hidden><?= htmlspecialchars($transfer['lot_status_name'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>


</html>