<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Initialize variables
$error = null;
$warehouse_id = isset($_GET['warehouse_id']) ? $_GET['warehouse_id'] : null;

// Function to fetch data from database
function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }
}

// Check if warehouse_id is provided
if (!$warehouse_id) {
    $error = "ไม่พบรหัสคลังสินค้า กรุณาระบุคลังสินค้า";
}

// Load necessary data
try {
    // Fetch warehouse data
    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse WHERE warehouse_id = ?";
    $warehouse_data = fetchData($pdo, $query, [$warehouse_id])->fetch(PDO::FETCH_ASSOC);

    if (!$warehouse_data) {
        $error = "ไม่พบข้อมูลคลังสินค้าที่ระบุ";
    }

    // Fetch lots in this warehouse
    $query = "SELECT
        lot.lot_id, 
        lot.lot_name, 
        lot.lot_detail, 
        lot.lot_date, 
        lot.lot_status_id, 
        lot.warehouse_id, 
        lot.warehouse_from_id,
        lot.warehouse_inout,
        lot.supplier_id,
        lot_status.lot_status_name,
        supplier.supplier_name
    FROM
        lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
    WHERE
        lot.warehouse_id = ?
    ORDER BY 
        lot.lot_date DESC";

    $lots_result = fetchData($pdo, $query, [$warehouse_id]);
    $warehouse_lot = $lots_result->fetchAll(PDO::FETCH_ASSOC);

    // Get the count
    $lot_count = count($warehouse_lot);

    // Fetch warehouse data for "from" warehouses
    $query = "SELECT warehouse_id, warehouse_name FROM warehouse";
    $all_warehouses = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);
    $warehouses_map = [];
    foreach ($all_warehouses as $wh) {
        $warehouses_map[$wh['warehouse_id']] = $wh['warehouse_name'];
    }
} catch (Exception $e) {
    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// Set timezone and get current date/time
date_default_timezone_set('Asia/Bangkok');
$displayDateTime = date("d/m/Y H:i:s");

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการ Lot ในคลัง - ระบบจัดการคลังสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <header class="flex justify-between items-center mb-8">
            <div class="flex items-center">
                <a href="index.php?page=lot_listinsertcopy" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-3xl font-bold text-gray-800">
                    <?php if (!$error && $warehouse_data): ?>
                        <i class="fas fa-warehouse mr-2"></i>Lots ในคลัง: <?= htmlspecialchars($warehouse_data['warehouse_name']) ?>
                    <?php else: ?>
                        <i class="fas fa-warehouse mr-2"></i>รายการ Lot ในคลัง
                    <?php endif; ?>
                </h1>
            </div>
            <div class="text-gray-600">
                <i class="far fa-clock mr-2"></i><?= $displayDateTime ?>
            </div>
        </header>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                <div class="mt-4">
                    <a href="index.php?page=lot_list" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        กลับไปหน้าหลัก
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Warehouse Information Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] px-6 py-4 border-b border-[#8f959b]">
                    <h2 class="text-xl font-semibold text-gray-800">ข้อมูลคลังสินค้า</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-gray-600 mb-1">รหัสคลัง:</p>
                            <p class="text-xl font-medium"><?= htmlspecialchars($warehouse_data['warehouse_id']) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-1">ชื่อคลัง:</p>
                            <p class="text-xl font-medium"><?= htmlspecialchars($warehouse_data['warehouse_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-1">สถานะ:</p>
                            <p class="text-xl font-medium">
                                <?php if ($warehouse_data['warehouse_status'] == 'เปิดใช้งาน'): ?>
                                    <span class="text-green-600"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($warehouse_data['warehouse_status']) ?></span>
                                <?php else: ?>
                                    <span class="text-red-600"><i class="fas fa-times-circle mr-1"></i><?= htmlspecialchars($warehouse_data['warehouse_status']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-1">จำนวน Lot ในคลัง:</p>
                            <p class="text-xl font-medium text-blue-600"><?= $lot_count ?> รายการ</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lots List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] px-6 py-4 border-b border-[#8f959b] flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-box-open mr-2"></i>รายการ Lot ทั้งหมดในคลัง
                    </h2>
                    <a href="index.php?page=lot_listinsertcopy" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-plus mr-1"></i>เพิ่ม Lot ใหม่
                    </a>
                </div>
                <div class="p-6">
                    <?php if (empty($warehouse_lot)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-box-open text-gray-400 text-5xl mb-4"></i>
                            <p class="text-gray-500 text-xl">ไม่พบ Lot ในคลังนี้</p>
                            <div class="mt-4">
                                <a href="index.php?page=lot_listinsertcopy" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                    เพิ่ม Lot ใหม่
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <?php foreach ($warehouse_lot as $lot): ?>
                                <div class="bg-white border rounded-lg shadow-sm hover:shadow-md transition-shadow p-4 cursor-pointer"
                                    onclick="window.location.href='index.php?page=lot_listdetail&tabel=lot&id=<?= htmlspecialchars($lot['lot_id']) ?>'">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-lg font-medium text-blue-600"><?= htmlspecialchars($lot['lot_name']) ?></h3>
                                        <span class="text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($lot['lot_date'])) ?></span>
                                    </div>

                                    <p class="text-gray-600 mt-2">
                                        <?= !empty($lot['lot_detail']) ? htmlspecialchars($lot['lot_detail']) : "ไม่มีรายละเอียด" ?>
                                    </p>

                                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                            <?= htmlspecialchars($lot['lot_status_name'] ?? 'ไม่ระบุสถานะ') ?>
                                        </span>

                                        <?php if (!empty($lot['supplier_name'])): ?>
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded">
                                                <i class="fas fa-truck mr-1"></i><?= htmlspecialchars($lot['supplier_name']) ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($lot['warehouse_from_id']) && isset($warehouses_map[$lot['warehouse_from_id']])): ?>
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded">
                                                <i class="fas fa-exchange-alt mr-1"></i>จาก: <?= htmlspecialchars($warehouses_map[$lot['warehouse_from_id']]) ?>
                                            </span>
                                        <?php endif; ?>

                                        <span class="bg-<?= $lot['warehouse_inout'] == 1 ? 'green' : 'red' ?>-100 text-<?= $lot['warehouse_inout'] == 1 ? 'green' : 'red' ?>-700 px-2 py-1 rounded">
                                            <i class="fas fa-<?= $lot['warehouse_inout'] == 1 ? 'sign-in-alt' : 'sign-out-alt' ?> mr-1"></i>
                                            <?= $lot['warehouse_inout'] == 1 ? 'รับเข้า' : 'จ่ายออก' ?>
                                        </span>
                                    </div>

                                    <div class="mt-3 text-right">
                                        <span class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye mr-1"></i>ดูรายละเอียด
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-between">
                <a href="index.php?page=lot_listinsertcopy" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">
                    <i class="fas fa-arrow-left mr-2"></i>กลับ
                </a>

                <a href="index.php?page=report_warehouse&warehouse_id=<?= htmlspecialchars($warehouse_id) ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded shadow">
                    <i class="fas fa-file-alt mr-2"></i>รายงานสรุป
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>