<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Session Management
if (!isset($_SESSION)) {
    session_start();
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$error = null;
$warehouse_id = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null;

// Function to fetch data from database
function fetchData($pdo, $query, $params = []) {
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
    $query = "
        SELECT
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
            lot.warehouse_id = ? AND lot.lot_status_id = 2
        ORDER BY 
            lot.lot_date DESC";
    $lots_result = fetchData($pdo, $query, [$warehouse_id]);
    $warehouse_lots = $lots_result->fetchAll(PDO::FETCH_ASSOC);

    // Get the count of lots
    $lot_count = count($warehouse_lots);

    // Fetch all products in this warehouse with quantities
    $query = "
        SELECT 
            p.product_id,
            p.product_name,
            SUM(lp.lot_product_remain) as total_qty,
            COUNT(DISTINCT lot.lot_id) as lot_count
        FROM 
            lot_product lp
            JOIN lot ON lp.lot_id = lot.lot_id
            JOIN product p ON lp.product_id = p.product_id
        WHERE 
            lot.warehouse_id = ? AND lot.lot_status_id = 2
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
                        <i class="fas fa-warehouse mr-2"></i>รายละเอียดคลัง: <?= htmlspecialchars($warehouse_data['warehouse_id']) ?> : <?= htmlspecialchars($warehouse_data['warehouse_name']) ?>
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
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                <div class="mt-4">
                    <a href="index.php?page=lot_list" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        กลับไปหน้าหลัก
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="p-6 bg-gray-100 min-h-screen">
                <!-- Warehouse Information Card -->
                <div class="bg-white rounded-lg shadow-md mb-8">
                    <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] px-6 py-4 border-b border-[#8f959b]">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-chart-pie mr-2"></i>สรุปข้อมูลสินค้าในคลัง
                        </h2>
                    </div>
                </div>

                <!-- Inventory Summary -->
                <div class="bg-white rounded-lg shadow-md mb-8">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-violet-50 p-4 rounded-lg shadow-sm">
                                <div class="flex justify-between items-center">
                                    <p class="text-gray-600 mb-1 text-xl">สถานะ:</p>
                                    <img src="../../image/signal.png" alt="check-icon" class="w-[35px]">
                                </div>
                                <p class="text-2xl font-medium">
                                    <?php if ($warehouse_data['warehouse_status'] == 'พร้อมใช้งาน'): ?>
                                        <span class="text-green-600"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($warehouse_data['warehouse_status']) ?></span>
                                    <?php else: ?>
                                        <span class="text-red-600"><i class="fas fa-times-circle mr-1"></i><?= htmlspecialchars($warehouse_data['warehouse_status']) ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-lg shadow-sm">
                                <div class="flex justify-between items-center">
                                    <p class="text-gray-600 mb-1 text-xl">จำนวนรายการสินค้า:</p>
                                    <img src="../../image/schedule.png" alt="quantity-icon" class="w-[35px]">
                                </div>
                                <p class="text-2xl font-medium text-blue-600"><?= $total_product_count ?> รายการ</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg shadow-sm">
                                <div class="flex justify-between items-center">
                                    <p class="text-gray-600 mb-1 text-xl">จำนวนสินค้าทั้งหมด:</p>
                                    <img src="../../image/thinking.png" alt="product-icon" class="w-[35px]">
                                </div>
                                <p class="text-2xl font-medium text-green-600"><?= number_format($total_inventory_qty, 0) ?> ชิ้น</p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg shadow-sm">
                                <div class="flex justify-between items-center">
                                    <p class="text-gray-600 mb-1">จำนวน Lot:</p>
                                    <img src="../../image/number.png" alt="" class="w-[35px]">
                                </div>
                                <p class="text-2xl font-medium text-purple-600"><?= $lot_count ?> Lot</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products in Warehouse -->
                <div class="bg-white rounded-lg shadow-md mb-8">
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
                                            <th class="py-3 px-4 border-b border-[#8f959b] text-right">จำนวนคงเหลือ</th>
                                            <th class="py-3 px-4 border-b border-[#8f959b] text-right">จำนวน Lot</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($warehouse_products as $product): ?>
                                            <tr class="hover:bg-[#aec5e366]">
                                                <td class="py-3 px-4 border-b border-gray-200"><?= htmlspecialchars($product['product_id']) ?></td>
                                                <td class="py-3 px-4 border-b border-gray-200 font-medium"><?= htmlspecialchars($product['product_name']) ?></td>
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

                <!-- Action Buttons -->
                <div class="flex justify-between">
                    <a href="index.php?page=warehouse_list" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">
                        <i class="fas fa-arrow-left mr-2"></i>กลับ
                    </a>
                    <a href="index.php?page=report_warehouse&warehouse_id=<?= htmlspecialchars($warehouse_id) ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded shadow">
                        <i class="fas fa-file-alt mr-2"></i>รายงานสรุป
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>