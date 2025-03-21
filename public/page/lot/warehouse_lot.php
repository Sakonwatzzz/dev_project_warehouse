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
        supplier.supplier_name,
        w.warehouse_name 
    FROM
        lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse w ON lot.warehouse_id = w.warehouse_id
    WHERE
        lot.warehouse_id = ?
    ORDER BY 
        lot.lot_date DESC";

    $lots_result = fetchData($pdo, $query, [$warehouse_id]);
    $warehouse_lots = $lots_result->fetchAll(PDO::FETCH_ASSOC);

    // Get the count of lots
    $lot_count = count($warehouse_lots);

    // Fetch all products in this warehouse with quantities
    $query = "SELECT 
        p.product_id,
        p.product_name,
        SUM(lp.lot_product_qty) as total_qty,
        COUNT(DISTINCT lot.lot_id) as lot_count
    FROM 
        lot_product lp
        JOIN lot ON lp.lot_id = lot.lot_id
        JOIN product p ON lp.product_id = p.product_id
    WHERE 
        lot.warehouse_id = ?
    GROUP BY 
        p.product_id, p.product_name
    ORDER BY 
        p.product_id";

    $warehouse_products = fetchData($pdo, $query, [$warehouse_id])->fetchAll(PDO::FETCH_ASSOC);
    $total_product_count = count($warehouse_products);

    // Calculate total inventory quantity
    $total_inventory_qty = 0;
    foreach ($warehouse_products as $product) {
        $total_inventory_qty += $product['total_qty'];
    }

    // Fetch detailed product data with lot information
    $query = "SELECT
        lot.lot_id, 
        lot.lot_name,
        p.product_id,
        p.product_name,
        lp.lot_product_qty,
        lot.warehouse_id,
        w.warehouse_name
    FROM
        lot_product lp
        JOIN lot ON lp.lot_id = lot.lot_id
        JOIN product p ON lp.product_id = p.product_id
        JOIN warehouse w ON lot.warehouse_id = w.warehouse_id
    WHERE
        lot.warehouse_id = ?
    ORDER BY 
        lot.lot_name, p.product_id";

    $product_lots = fetchData($pdo, $query, [$warehouse_id])->fetchAll(PDO::FETCH_ASSOC);

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
    <title>รายละเอียดคลังสินค้า - ระบบจัดการคลังสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <header class="flex justify-between items-center mb-8">
            <div class="flex items-center">
                <a href="index.php?page=warehouse_list" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-3xl font-bold text-gray-800">
                    <?php if (!$error && $warehouse_data): ?>
                        <i class="fas fa-warehouse mr-2"></i>รายละเอียดคลัง: <?= htmlspecialchars($warehouse_data['warehouse_name']) ?>
                    <?php else: ?>
                        <i class="fas fa-warehouse mr-2"></i>รายละเอียดคลังสินค้า
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

            <!-- Inventory Summary -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] px-6 py-4 border-b border-[#8f959b]">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-chart-pie mr-2"></i>สรุปข้อมูลสินค้าในคลัง
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-blue-50 p-4 rounded-lg shadow-sm">
                            <p class="text-gray-600 mb-1">จำนวนรายการสินค้า:</p>
                            <p class="text-2xl font-medium text-blue-600"><?= $total_product_count ?> รายการ</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg shadow-sm">
                            <p class="text-gray-600 mb-1">จำนวนสินค้าทั้งหมด:</p>
                            <p class="text-2xl font-medium text-green-600"><?= number_format($total_inventory_qty, 0) ?> ชิ้น</p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg shadow-sm">
                            <p class="text-gray-600 mb-1">จำนวน Lot:</p>
                            <p class="text-2xl font-medium text-purple-600"><?= $lot_count ?> Lot</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products in Warehouse -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] px-6 py-4 border-b border-[#8f959b]">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-boxes mr-2"></i>สินค้าทั้งหมดในคลัง
                    </h2>
                </div>
                <div class="p-6">
                    <?php if (empty($warehouse_products)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-box-open text-gray-400 text-5xl mb-4"></i>
                            <p class="text-gray-500 text-xl">ไม่พบสินค้าในคลังนี้</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 shadow-sm">
                                <thead>
                                    <tr class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed]">
                                        <th class="py-3 px-4 border-b border-[#8f959b] text-left">รหัสสินค้า</th>
                                        <th class="py-3 px-4 border-b border-[#8f959b] text-left">ชื่อสินค้า</th>
                                        <th class="py-3 px-4 border-b border-[#8f959b] text-left">รายละเอียด</th>
                                        <th class="py-3 px-4 border-b border-[#8f959b] text-right">จำนวนคงเหลือ</th>

                                        <th class="py-3 px-4 border-b border-[#8f959b] text-right">จำนวน Lot</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($warehouse_products as $product): ?>
                                        <tr class="hover:bg-[#aec5e366]">
                                            <td class="py-3 px-4 border-b border-gray-200"><?= htmlspecialchars($product['product_id']) ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200 font-medium"><?= htmlspecialchars($product['product_name']) ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200">
                                                <?= !empty($product['product_detail']) ? htmlspecialchars($product['product_detail']) : "-" ?>
                                            </td>
                                            <td class="py-3 px-4 border-b border-gray-200 text-right font-medium"><?= number_format($product['total_qty'], 0) ?></td>

                                            <td class="py-3 px-4 border-b border-gray-200 text-right"><?= htmlspecialchars($product['lot_count']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lot Details with Products 
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] px-6 py-4 border-b border-[#8f959b] flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-box-open mr-2"></i>CheckOut รายการ Lot และสินค้าในคลัง
                    </h2>
                    <a href="index.php?page=lot_listinsertcopy" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-plus mr-1"></i>เพิ่ม Lot ใหม่
                    </a>
                </div>
                <div class="p-6">
                    <?php if (empty($product_lots)): ?>
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
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 shadow-sm">
                                <thead>
                                    <tr class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed]">
                                        <th class="py-3 px-4 border-b border-[#8f959b] text-left">ชื่อ Lot</th>
                                        <th class="py-3 px-4 border-b border-[#8f959b] text-left" hidden>รหัสสินค้า</th>
                                        <th class="py-3 px-4 border-b border-[#8f959b] text-left">ชื่อสินค้า</th>
                                        <th class="py-3 px-4 border-b border-[#8f959b] text-right">จำนวนสินค้า</th>
                                        <th class="py-3 px-4 border-b border-[#8f959b] text-center">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_lot = null;
                                    foreach ($product_lots as $index => $item):
                                        $is_new_lot = $current_lot !== $item['lot_name'];
                                        if ($is_new_lot) {
                                            $current_lot = $item['lot_name'];
                                        }
                                    ?>
                                        <tr class="hover:bg-[#aec5e366] <?= $is_new_lot ? 'border-t-2 border-gray-300' : '' ?>">
                                            <?php if ($is_new_lot): ?>
                                                <td class="py-3 px-4 border-b border-gray-200 font-medium"><?= htmlspecialchars($item['lot_name']) ?></td>
                                            <?php else: ?>
                                                <td class="py-3 px-4 border-b border-gray-200"></td>
                                            <?php endif; ?>
                                            <td class="py-3 px-4 border-b border-gray-200" hidden><?= htmlspecialchars($item['product_id']) ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200"><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200 text-right"><?= number_format($item['lot_product_qty'], 0) ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200 text-center">
                                                <?php if ($is_new_lot): ?>
                                                    <a href="index.php?page=lot_listdetail&tabel=lot&id=<?= htmlspecialchars($item['lot_id']) ?>" class="text-blue-600 hover:text-blue-800 mr-3">
                                                        <i class="fas fa-eye"></i> รายละเอียด
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            -->

            <!--
            <!-- Lots List (Card View) 
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] px-6 py-4 border-b border-[#8f959b] flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-box-open mr-2"></i>รายการ Lot ทั้งหมดในคลัง
                    </h2>
                </div>
                <div class="p-6">
                    <?php if (empty($warehouse_lots)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-box-open text-gray-400 text-5xl mb-4"></i>
                            <p class="text-gray-500 text-xl">ไม่พบ Lot ในคลังนี้</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <?php foreach ($warehouse_lots as $lot): ?>
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
             -->

            <!-- Action Buttons -->
            <div class="flex justify-between">
                <a href="index.php?page=warehouse_list" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">
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