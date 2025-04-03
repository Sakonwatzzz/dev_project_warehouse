<?php
require_once __DIR__ . '../../../../config/database_server.php';

function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die('Database error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Handle POST requests (Insert and Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['warehousename'])) {
        $warehousename = $_POST['warehousename'] ?? '';
        $warehousestatus = $_POST['warehousestatus'] ?? '';
        try {
            $stmt = $pdo->prepare('INSERT INTO warehouse (warehouse_name, warehouse_status) VALUES (?, ?)');
            $stmt->execute([$warehousename, $warehousestatus]);
            header('Location: /index.php?page=warehouse_list&add_success=1');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    } elseif (isset($_POST['update_warehousename'])) {
        $id = $_POST['id'];
        $newWarehouseName = $_POST['update_warehousename'];
        $newwarehousestatus = $_POST['update_warehousestatus'];
        try {
            $stmt = $pdo->prepare("UPDATE warehouse SET warehouse_name = ?, warehouse_status = ? WHERE warehouse_id = ?");
            $stmt->execute([$newWarehouseName, $newwarehousestatus, $id]);
            header('Location: /index.php?page=warehouse_list&edit_success=1');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle DELETE request
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM warehouse WHERE warehouse_id = ?");
        $stmt->execute([$id]);
        header("Location: ?page=warehouse_list&delete_success=1");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}

// Count total records in the warehouse table
$query = "SELECT COUNT(*) as total_count FROM warehouse";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $total_records = $pdo->query("SELECT COUNT(*) FROM warehouse")->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}

// Fetch warehouse data with lot count (for both table and cards)
try {
    $query = "
        SELECT 
            w.warehouse_id,
            w.warehouse_name,
            w.warehouse_status,
            COALESCE(COUNT(l.lot_id), 0) as lot_count
        FROM warehouse w
        LEFT JOIN lot l ON w.warehouse_id = l.warehouse_id
        GROUP BY w.warehouse_id, w.warehouse_name, w.warehouse_status
        LIMIT :offset, :records_per_page
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $warehouses = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}

// Fetch additional data (unchanged)
try {
    $query = "
        SELECT
            lot_status.lot_status_id, 
            lot_status.lot_status_name, 
            supplier.supplier_id, 
            supplier.supplier_name, 
            warehouse.warehouse_name AS warehouse_in_name, 
            warehouse.warehouse_name AS warehouse_name, 
            warehouse.warehouse_id,
            lot.lot_id, 
            lot.lot_name, 
            lot.lot_detail, 
            lot.lot_date, 
            lot.lot_status_id, 
            lot.warehouse_id, 
            lot.warehouse_from_id,
            lot.warehouse_inout
        FROM lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
    ";
    $result_lot_list = fetchData($pdo, $query);
    $lots = $result_lot_list->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT lot_id FROM lot";
    $lot_id_options = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $status_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse ORDER BY warehouse_id";
    $warehouse_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT supplier_id, supplier_name FROM supplier";
    $supplier_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT lot_product_id, lot_product_qty, lot_product_remain, lot_product_capital FROM lot_product";
    $result = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $selected_status_id = $_POST['lot_status_id'] ?? '';
    if ($selected_status_id === '') {
        $selected_status_id = $status_lot[0]['lot_status_id'] ?? '';
    }

    $warehouse_lot_counts = [];
    foreach ($warehouse_lot as $warehouse) {
        $warehouse_id = $warehouse['warehouse_id'];
        $warehouse_lot_counts[$warehouse_id] = 0;
        foreach ($lots as $lot) {
            if ($lot['warehouse_id'] == $warehouse_id) {
                $warehouse_lot_counts[$warehouse_id]++;
            }
        }
    }
} catch (Exception $e) {
    $error = "เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage());
}
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
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสรุปคลังสินค้า</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 1200px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        @media print {
            body * {
                visibility: visible;
            }
            
            .no-print, .no-print * {
                display: none !important;
            }
            
            #printSection, #printSection * {
                visibility: visible;
            }
            
            #printSection {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            
            /* Additional styles to ensure proper printing */
            table {
                width: 100%;
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            thead {
                display: table-header-group;
            }
            
            tfoot {
                display: table-footer-group;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Main content container -->
    <div class="container mx-auto px-4 py-8">
        <!-- Print button - will be hidden when printing -->
        <div class="flex justify-between gap-5 pb-3 no-print">
            <a href="index.php?page=warehouse_list" class=""></a>
            <button id="printButton" onclick="window.print()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded shadow">
                <i class="fas fa-print mr-2"></i>พิมพ์
            </button>
        </div>
        
        <!-- Printable content section -->
        <div id="printSection" class="bg-white">
            <!-- Report Header -->
            <div class="text-center p-4">
                <h1 class="text-2xl font-bold">รายงานสรุปคลังสินค้า</h1>
                <p class="text-gray-600">วันที่พิมพ์: <?= date('d/m/Y') ?></p>
            </div>
            
            <div class="p-6 flex flex-wrap">
                <div class="w-full md:w-1/2 p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">ข้อมูลคลังสินค้า</h2>
                    <div class="mb-4 no-print">
                        <label for="warehouse" class="block text-lg font-semibold text-gray-900 mb-2">คลังสินค้า</label>
                        <select id="warehouse" name="warehouse_id" class="w-full p-3 border border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">ทั้งหมด</option>
                            <?php foreach ($warehouse_lot as $warehouse): ?>
                                <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                    <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2 no-print">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-900 mb-1">วันที่เริ่มต้น</label>
                            <input type="date" id="start_date" name="start_date" value="<?= date('Y-m-d'); ?>" class="w-full p-3 border border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-900 mb-1">วันที่สิ้นสุด</label>
                            <input type="date" id="end_date" name="end_date" value="<?= date('Y-m-d'); ?>" class="w-full p-3 border border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>
                <div class="w-full md:w-1/2 p-6">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-300 p-3 text-left">หัวข้อ</th>
                                <th class="border border-gray-300 p-3 text-left">รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="border border-gray-300 p-3">จำนวนคลังสินค้า</td>
                                <td class="border border-gray-300 p-3"><?= count($warehouse_lot) ?> คลัง</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 p-3">จำนวนรายการสินค้าทั้งหมด</td>
                                <td class="border border-gray-300 p-3"><?= count($result) ?> รายการ</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 p-3">จำนวน Lot</td>
                                <td class="border border-gray-300 p-3"><?= count($lots) ?> Lot</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Report Data Tables -->
            <div class="p-6 flex flex-wrap">
                <!-- Goods Receipt Table -->
                <div class="w-full lg:w-1/2 mb-8 pr-0 lg:pr-4">
                    <div class="px-6 py-4 border-b">
                        <h2 class="text-xl font-semibold text-gray-800">รายการรับสินค้า</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full bg-white border border-gray-200 shadow-sm" id="dataTable">
                            <thead>
                                <tr class="bg-gray-200 text-gray-700">
                                    <th class="p-2 px-3 text-left border">ชื่อ Lot สินค้า</th>
                                    <th class="p-2 px-3 text-left border">วันที่และเวลาที่ล็อต</th>
                                    <th class="p-2 px-3 text-left border">รับจากผู้ส่งมอบ</th>
                                    <th class="p-2 px-3 text-left border">นำมาจากคลัง</th>
                                    <th class="p-2 px-3 text-left border">ลงคลัง</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lots as $lot): ?>
                                    <tr class="hover:bg-[#aec5e366] cursor-pointer border-b">
                                        <td class="px-6 py-4"><?= htmlspecialchars($lot['lot_name']) ?></td>
                                        <td class="px-6 py-4"><?= date('d/m/Y H:i', strtotime($lot['lot_date'])) ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($lot['supplier_name'] ?? '-') ?></td>
                                        <td class="px-6 py-4">
                                            <?php 
                                            if (!empty($lot['warehouse_from_id']) && isset($warehouses_map[$lot['warehouse_from_id']])) {
                                                echo htmlspecialchars($warehouses_map[$lot['warehouse_from_id']]);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($lot['warehouse_name'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Warehouse Products Table -->
                <div class="w-full lg:w-1/2 pl-0 lg:pl-4">
                    <div class="px-6 py-4 border-b">
                        <h2 class="text-xl font-semibold text-gray-800">รายการสินค้าในคลัง</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full bg-white border border-gray-200 shadow-sm">
                            <thead>
                                <tr class="bg-gray-200 text-gray-700">
                                    <th class="py-3 px-4 border-b border-[#8f959b] text-left">รหัสสินค้า</th>
                                    <th class="py-3 px-4 border-b border-[#8f959b] text-left">ชื่อสินค้า</th>
                                    <th class="py-3 px-4 border-b border-[#8f959b] text-right">จำนวนคงเหลือ</th>
                                    <th class="py-3 px-4 border-b border-[#8f959b] text-right">จำนวน Lot</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($warehouse_products as $product): ?>
                                    <tr class="hover:bg-[#aec5e366]">
                                        <td class="py-3 px-4 border-b border-gray-200">
                                            <?= htmlspecialchars($product['product_id']) ?>
                                        </td>
                                        <td class="py-3 px-4 border-b border-gray-200 font-medium">
                                            <?= htmlspecialchars($product['product_name']) ?>
                                        </td>
                                        <td class="py-3 px-4 border-b border-gray-200 text-right font-medium">
                                            <?= number_format($product['total_qty'], 0) ?>
                                        </td>
                                        <td class="py-3 px-4 border-b border-gray-200 text-right">
                                            <?= htmlspecialchars($product['lot_count']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Report Footer -->
            <div class="text-center p-4 border-t mt-4">
                <p class="text-gray-500">© <?= date('Y') ?> สำเพ็งบุรีรัมย์สินค้าราคาปลีกและส่ง</p>
            </div>
        </div>
    </div>

    <!-- JavaScript for warehouse selection and filtering -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const warehouseSelect = document.getElementById('warehouse');
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            // Add event listeners to filter data when selections change
            if (warehouseSelect) {
                warehouseSelect.addEventListener('change', filterData);
            }
            
            if (startDateInput) {
                startDateInput.addEventListener('change', filterData);
            }
            
            if (endDateInput) {
                endDateInput.addEventListener('change', filterData);
            }
            
            function filterData() {
                // You can implement AJAX to filter data based on selections
                // or redirect to the same page with filter parameters
                console.log('Filtering data...');
                // Example: window.location.href = `?page=warehouse_report&warehouse_id=${warehouseSelect.value}&start_date=${startDateInput.value}&end_date=${endDateInput.value}`;
            }
        });
    </script>
</body>

</html>