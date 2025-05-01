<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Initialize variables
$error = null;
$selected_status_id = '';

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

// Load necessary data
try {
    // Fetch lot list
    $query = "SELECT
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
    FROM
        lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
        WHERE lot.warehouse_inout = 1";
    $result_lot_list = fetchData($pdo, $query);

    // Store result for later use in warehouse cards
    $lots = $result_lot_list->fetchAll(PDO::FETCH_ASSOC);

    // Reset the pointer to use in the table
    $result_lot_list = fetchData($pdo, $query);

    // Fetch lot_id options
    $query = "SELECT lot_id FROM lot";
    $lot_id_options = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch lot status data
    $query = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $status_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch warehouse data
    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse ORDER BY warehouse_id";
    $warehouse_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch supplier data
    $query = "SELECT supplier_id, supplier_name FROM supplier";
    $supplier_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch lot product data
    $query = "SELECT lot_product_id, lot_product_qty, lot_product_remain, lot_product_capital FROM lot_product";
    $result = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Set default status ID if not selected
    $selected_status_id = $_POST['lot_status_id'] ?? '';
    if ($selected_status_id === '') {
        $selected_status_id = $status_lot[0]['lot_status_id'] ?? '';
    }

    // Count lots per warehouse
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
    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get lot_id from POST
    $lot_id = $_POST['lot_id'] ?? '';

    // Add new lot
    if (!isset($_POST[''])) {
        // Get form data
        $lot_name = $_POST['lot_name'] ?? '';
        $lot_detail = $_POST['lot_detail'] ?? '';
        $lot_date = $_POST['lot_date'] ?? '';
        $lot_status_id = $_POST['lot_status_id'] ?? '';
        $warehouse_id = $_POST['warehouse_id'] ?? '';
        $warehouse_from_id = $_POST['warehouse_from_id'] ?? '';
        $warehouse_inout = $_POST['warehouse_inout'] ?? '1';
        $supplier_id = $_POST['supplier_id'] ?? '';
        $lot_product_qty = $_POST['lot_product_qty'] ?? '';
        $lot_product_remain = $_POST['lot_product_remain'] ?? '';
        $lot_product_capital = $_POST['lot_product_capital'] ?? '';
        $product_id = $_POST['product_id'] ?? '';

        // Validate form data
        if (empty($lot_name)) {
            $error = "ข้อมูลไม่ครบ กรุณากรอกข้อมูลให้ครบถ้วน lot_name, lot_date, warehouse_id";
        } else {
            try {
                $pdo->beginTransaction();

                // Insert new lot
                $stmt = $pdo->prepare('INSERT INTO lot (lot_name, lot_detail, lot_date, lot_status_id, warehouse_id, supplier_id, warehouse_from_id, warehouse_inout) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$lot_name, $lot_detail, $lot_date, $lot_status_id, $warehouse_id, $supplier_id, $warehouse_from_id, $warehouse_inout]);

                // Get the newly inserted lot_id
                $lot_id = $pdo->lastInsertId();

                // Check if lot_id was obtained
                if (!$lot_id) {
                    throw new Exception("ไม่สามารถดึงค่า lot_id ได้");
                }

                // Commit transaction
                $pdo->commit();

                // Redirect to lot details page
                header("Location: index.php?page=lot_listdetail&tabel=lot&id=" . urlencode($lot_id) . "&success=1");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
}

// Set timezone and get current date/time
date_default_timezone_set('Asia/Bangkok');
$currentDateTime = date("Y-m-d H:i:s");
$displayDateTime = date("d/m/Y H:i:s");

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการคลังสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800">รอบของสินค้า</h1>
            <div class="text-gray-600">
                <i class="far fa-clock mr-2"></i><?= $displayDateTime ?>
            </div>
        </header>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Form Section -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-box-open mr-3 "></i>เพิ่มรอบสินค้าใหม่
                </h2>
            </div>

            <form method="POST" action="" class="p-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                    <!-- External source form (supplier) -->
                    <div class="space-y-6 bg-gray-50 p-6 rounded-lg border border-gray-200">
                        <h3 class="text-xl font-semibold text-blue-700 mb-4 flex items-center">
                            <i class="fas fa-truck-loading mr-2"></i>รับจากภายนอก (supplier)
                        </h3>

                        <input type="hidden" name="lot_id" id="lot_id"
                            value="<?php echo isset($lot_id) ? $lot_id : ''; ?>">

                        <div class="form-group">
                            <label for="lot_name" class="block text-gray-700 font-medium mb-2">
                                ชื่อ Lot สินค้า <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-tag text-gray-400"></i>
                                </div>
                                <input type="text" name="lot_name" id="lot_name"
                                    class="w-full border rounded-lg px-3 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
                                    placeholder="กรอกชื่อหรือรหัส lot รายการสินค้า" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="lot_detail"
                                class="block text-gray-700 font-medium mb-2">รายละเอียดการรับ</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-info-circle text-gray-400"></i>
                                </div>
                                <input type="text" name="lot_detail" id="lot_detail"
                                    class="w-full border rounded-lg px-3 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    placeholder="กรอกรายะละเอียดการรับ">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="warehouse_id" class="block text-gray-700 font-medium mb-2">
                                ลงคลัง <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-warehouse text-gray-400"></i>
                                </div>
                                <select name="warehouse_id" id="warehouse_id"
                                    class="w-full border rounded-lg pl-10 pr-10 py-3 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-400">
                                    <?php foreach ($warehouse_lot as $warehouse): ?>
                                        <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                            <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Internal transfer form (warehouse transfer) -->
                    <div class="space-y-6 bg-gray-50 p-6 rounded-lg border border-gray-200">
                        <h3 class="text-xl font-semibold text-blue-700 mb-4 flex items-center">
                            <i class="fas fa-exchange-alt mr-2"></i>รับมาจากคลัง (ย้ายคลัง/กิจการตัวเอง)
                        </h3>

                        <div class="form-group">
                            <label for="supplier_id"
                                class="block text-gray-700 font-medium mb-2">รับมาจากผู้ส่งมอบ</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-users text-gray-400"></i>
                                </div>
                                <select name="supplier_id" id="supplier_id"
                                    class="w-full border rounded-lg pl-10 pr-10 py-3 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-400">
                                    <?php foreach ($supplier_lot as $supplier): ?>
                                        <option value="<?= htmlspecialchars($supplier['supplier_id']) ?>">
                                            <?= htmlspecialchars($supplier['supplier_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="warehouse_from_id"
                                class="block text-gray-700 font-medium mb-2">นำมาจากคลัง</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-dolly text-gray-400"></i>
                                </div>
                                <select name="warehouse_from_id" id="warehouse_from_id"
                                    class="w-full border rounded-lg pl-10 pr-10 py-3 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-400">
                                    <?php foreach ($warehouse_lot as $warehouse): ?>
                                        <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                            <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" hidden>
                            <label for="lot_status_name" class="block text-gray-700 font-medium mb-2">สถานะ</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-flag text-gray-400"></i>
                                </div>
                                <select name="lot_status_id" id="lot_status_name"
                                    class="w-full border rounded-lg pl-10 pr-10 py-3 appearance-none bg-gray-100 cursor-not-allowed"
                                    disabled>
                                    <?php foreach ($status_lot as $status): ?>
                                        <option value="<?= htmlspecialchars($status['lot_status_id']) ?>"
                                            <?= ($status['lot_status_id'] == $selected_status_id) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($status['lot_status_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                            </div>
                            <input type="hidden" name="lot_status_id" value="<?= $selected_status_id ?>">
                        </div>

                        <div class="form-group">
                            <label for="warehouse_inout"
                                class="block text-gray-700 font-medium mb-2">สถานะการรับ-จ่าย</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-exchange-alt text-gray-400"></i>
                                </div>
                                <select id="warehouse_inout"
                                    class="w-full border rounded-lg pl-10 pr-10 py-3 appearance-none bg-gray-100 cursor-not-allowed"
                                    disabled>
                                    <option value="1" selected>รับเข้า (1)</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                            </div>
                            <input type="hidden" name="warehouse_inout" value="1">
                        </div>

                        <input type="hidden" name="lot_date" value="<?= $currentDateTime ?>">
                    </div>
                </div>

                <div class="mt-10 flex justify-center">
                    <button type="submit"
                        class="bg-gradient-to-r from-green-500 to-green-700 text-white text-lg px-10 py-3 rounded-lg shadow-lg hover:from-green-600 hover:to-green-800 transition-all flex items-center">
                        <i class="fas fa-save mr-3"></i>บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>

        <!-- Warehouse Cards Section
        <h2 class="text-2xl font-bold text-gray-800 mb-6">คลังสินค้า</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
            <?php foreach ($warehouse_lot as $warehouse): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300"
                    onclick="window.location.href='?page=warehouse_lot&warehouse_id=<?= htmlspecialchars($warehouse['warehouse_id']) ?>'">
                    <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                        <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($warehouse['warehouse_name']) ?></h3>
                    </div>
                    <div class="p-6 flex justify-between items-center">
                        <div>
                            <p class="text-gray-600 mb-2"><i class="fas fa-box mr-2"></i>จำนวน Lot ทั้งหมด</p>
                            <p class="text-3xl font-bold text-blue-600"><?= $warehouse_lot_counts[$warehouse['warehouse_id']] ?? 0 ?></p>
                        </div>
                        <div class="text-blue-500">
                            <i class="fas fa-chevron-right text-2xl"></i>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 border-t">
                        <p class="text-sm text-gray-500"><i class="fas fa-info-circle mr-1"></i> คลิกเพื่อดู Lot ทั้งหมดในคลังนี้</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div> -->

        <!-- Recent Lots Table Section -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] px-6 py-4 border-b border-[#8f959b]">
                <h2 class="text-2xl font-semibold text-gray-800">รายการ Lot ล่าสุด</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full" id="dataTable">
                    <thead>
                        <tr>
                            <th class="bg-gray-100 text-gray-700 px-6 py-3 text-left border-b">ชื่อ Lot สินค้า</th>
                            <th class="bg-gray-100 text-gray-700 px-6 py-3 text-left border-b">วันที่และเวลาที่ล็อต</th>
                            <th class="bg-gray-100 text-gray-700 px-6 py-3 text-left border-b">นำมาจากคลัง</th>
                            <th class="bg-gray-100 text-gray-700 px-6 py-3 text-left border-b">ลงคลัง</th>
                            <th class="bg-gray-100 text-gray-700 px-6 py-3 text-left border-b">ผู้ส่งมอบ</th>

                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($lot_list = $result_lot_list->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr class="hover:bg-[#aec5e366] cursor-pointer border-b"
                                onclick="window.location.href='?page=lot_listdetail&tabel=lot&id=<?php echo htmlspecialchars($lot_list['lot_id']) ?>'">
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($lot_list['lot_name']) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo date('d/m/Y H:i', strtotime($lot_list['lot_date'])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $from_warehouse = "";
                                    foreach ($warehouse_lot as $warehouse) {
                                        if ($warehouse['warehouse_id'] == $lot_list['warehouse_from_id']) {
                                            $from_warehouse = $warehouse['warehouse_name'];
                                            break;
                                        }
                                    }
                                    echo !empty($from_warehouse) ? htmlspecialchars($from_warehouse) : "ยังไม่ระบุ";
                                    ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($lot_list['warehouse_name']) ?>
                                </td>

                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($lot_list['supplier_name']) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add this new page template for viewing lots in a specific warehouse -->
    <?php if (isset($_GET['page']) && $_GET['page'] === 'warehouse_lot' && isset($_GET['warehouse_id'])): ?>
        <?php
        $warehouse_id = $_GET['warehouse_id'];
        $warehouse_name = "";

        // Get warehouse name
        foreach ($warehouse_lot as $warehouse) {
            if ($warehouse['warehouse_id'] == $warehouse_id) {
                $warehouse_name = $warehouse['warehouse_name'];
                break;
            }
        }

        // Get lots in this warehouse
        $warehouse_lot = [];
        foreach ($lots as $lot) {
            if ($lot['warehouse_id'] == $warehouse_id) {
                $warehouse_lot[] = $lot;
            }
        }
        ?>

        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" id="warehouseLotsModal">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                <div
                    class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] px-6 py-4 border-b border-[#8f959b] flex justify-between items-center">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fas fa-warehouse mr-2"></i>Lots ในคลัง: <?= htmlspecialchars($warehouse_name) ?>
                    </h2>
                    <button onclick="window.history.back()" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 120px);">
                    <?php if (empty($warehouse_lot)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-box-open text-gray-400 text-5xl mb-4"></i>
                            <p class="text-gray-500 text-xl">ไม่พบ Lot ในคลังนี้</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 gap-4">
                            <?php foreach ($warehouse_lot as $lot): ?>
                                <div class="bg-white border rounded-lg shadow-sm hover:shadow-md transition-shadow p-4 cursor-pointer"
                                    onclick="window.location.href='?page=lot_listdetail&tabel=lot&id=<?= htmlspecialchars($lot['lot_id']) ?>'">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-lg font-medium text-blue-600"><?= htmlspecialchars($lot['lot_name']) ?></h3>
                                        <span
                                            class="text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($lot['lot_date'])) ?></span>
                                    </div>

                                    <p class="text-gray-600 mt-2">
                                        <?= !empty($lot['lot_detail']) ? htmlspecialchars($lot['lot_detail']) : "ไม่มีรายละเอียด" ?>
                                    </p>

                                    <div class="mt-3 flex items-center text-sm">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                            <?php
                                            foreach ($status_lot as $status) {
                                                if ($status['lot_status_id'] == $lot['lot_status_id']) {
                                                    echo htmlspecialchars($status['lot_status_name']);
                                                    break;
                                                }
                                            }
                                            ?>
                                        </span>

                                        <?php if (!empty($lot['supplier_name'])): ?>
                                            <span class="ml-2 text-gray-500">
                                                <i class="fas fa-truck mr-1"></i><?= htmlspecialchars($lot['supplier_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-gray-50 px-6 py-3 border-t text-right">
                    <button onclick="window.history.back()"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
                        <i class="fas fa-arrow-left mr-2"></i>กลับ
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- JavaScript functions -->
    <script>
        // Alert handling
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.getElementById('success-alert');
            if (alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 2000);
            }
        });
    </script>
</body>

</html>