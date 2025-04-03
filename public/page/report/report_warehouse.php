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
        @media print {
            .no-print {
                display: none;
            }

            .print-break {
                page-break-before: always;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <form class="">
            <div class="grid grid-cols-3 gap-4 p-6">
                <!-- 1. ส่วนเลือกคลังสินค้า -->
                <div class="col-span-1 bg-white p-4 shadow-md rounded-lg">
                    <label for="warehouse" class="block mb-2 text-sm font-medium text-gray-900">เลือกคลังสินค้า</label>
                    <select id="warehouse" name="warehouse_id" class="w-full p-2 border rounded">
                        <?php foreach ($warehouse_lot as $warehouse): ?>
                            <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. ส่วนแสดงรายงานสรุป -->
                <div class="col-span-2 bg-white p-4 shadow-md rounded-lg">
                    <h2 class="text-lg font-bold mb-2">สรุปข้อมูลคลังสินค้า</h2>
                    <div class="grid grid-cols-4 gap-4">
                        <div class="p-4 bg-green-50 rounded-lg">
                            <p class="text-sm">สถานะ:</p>
                            <p
                                class="text-lg font-bold <?= $warehouse_data['warehouse_status'] == 'พร้อมใช้งาน' ? 'text-green-600' : 'text-red-600' ?>">
                                <?= htmlspecialchars($warehouse_data['warehouse_status']) ?>
                            </p>
                        </div>
                        <div class="p-4 bg-blue-50 rounded-lg">
                            <p class="text-sm">จำนวนรายการสินค้า:</p>
                            <p class="text-lg font-bold text-blue-600"><?= $total_product_count ?> รายการ</p>
                        </div>
                        <div class="p-4 bg-yellow-50 rounded-lg">
                            <p class="text-sm">จำนวนสินค้าทั้งหมด:</p>
                            <p class="text-lg font-bold text-yellow-600"><?= number_format($total_inventory_qty, 0) ?>
                                ชิ้น</p>
                        </div>
                        <div class="p-4 bg-purple-50 rounded-lg">
                            <p class="text-sm">จำนวน Lot:</p>
                            <p class="text-lg font-bold text-purple-600"><?= $lot_count ?> Lot</p>
                        </div>
                    </div>
                </div>

                <!-- 3. ส่วนสินค้าทั้งหมดในคลัง -->
                <div class="col-span-3 bg-white p-4 shadow-md rounded-lg">
                    <h2 class="text-lg font-bold mb-2">รายการสินค้าในคลัง</h2>
                    <table class="w-full border">
                        <thead>
                            <tr class="bg-gray-200">
                                <th class="p-2">รหัสสินค้า</th>
                                <th class="p-2">ชื่อสินค้า</th>
                                <th class="p-2">รายละเอียด</th>
                                <th class="p-2">จำนวนคงเหลือ</th>
                                <th class="p-2">จำนวน Lot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warehouse_products as $product): ?>
                                <tr class="border-t">
                                    <td class="p-2 text-center"><?= htmlspecialchars($product['product_id']) ?></td>
                                    <td class="p-2 text-center font-medium">
                                        <?= htmlspecialchars($product['product_name']) ?>
                                    </td>
                                    <td class="p-2 text-center">
                                        <?= !empty($product['product_detail']) ? htmlspecialchars($product['product_detail']) : "-" ?>
                                    </td>
                                    <td class="p-2 text-center text-green-600 font-bold">
                                        <?= number_format($product['total_qty'], 0) ?>
                                    </td>
                                    <td class="p-2 text-center">
                                        <?= htmlspecialchars($product['lot_count']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>
</body>

</html>