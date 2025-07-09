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

// Pagination logic
$records_per_page = 5; // Number of records per page
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1; // Current page
$offset = ($page - 1) * $records_per_page;

// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) FROM lot WHERE warehouse_inout = 2";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
    $total_pages = max(1, ceil($total_records / $records_per_page));
} catch (PDOException $e) {
    $error = "เกิดข้อผิดพลาดในการนับข้อมูล: " . htmlspecialchars($e->getMessage());
}

// Load necessary data
try {
    // Fetch lot status data
    $query = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $status_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch warehouse data
    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse ORDER BY warehouse_id";
    $warehouse_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch personnel data
    $query = "SELECT personnel_id, personnel_name FROM personnel";
    $personnel_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Generate new lot_name
    $query = "SELECT lot_name FROM lot WHERE lot_name LIKE 'WT-%' ORDER BY lot_id DESC LIMIT 1";
    $result = fetchData($pdo, $query)->fetch(PDO::FETCH_ASSOC);
    $newLotNumber = $result ? (int)preg_replace('/WT-(\d+)/', '$1', $result['lot_name']) + 1 : 1;
    $new_lot_name = "WT-" . str_pad($newLotNumber, 3, '0', STR_PAD_LEFT);

    // Fetch lots for table (warehouse-to-warehouse transfers) with pagination
    $query = "
        SELECT 
            l.lot_id,
            l.lot_name,
            l.lot_date,
            l.lot_status_id,
            pn.personnel_id,
            pn.personnel_name,
            ls.lot_status_name,
            w1.warehouse_name AS warehouse_from_name,
            w2.warehouse_name AS warehouse_name
        FROM lot l
        LEFT JOIN warehouse w1 ON l.warehouse_from_id = w1.warehouse_id
        LEFT JOIN warehouse w2 ON l.warehouse_id = w2.warehouse_id
        INNER JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
        LEFT JOIN personnel pn ON l.personnel_id = pn.personnel_id
        WHERE l.warehouse_inout = 2
        ORDER BY l.lot_id DESC
        LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set default status ID
    $selected_status_id = $_POST['lot_status_id'] ?? ($status_lot[0]['lot_status_id'] ?? '');

    // Calculate statistics
    $total_lot_count = $total_records; // Use the total from count query
    $completed_lot_count = 0;
    $pending_lot_count = 0;

    // Fetch all lots for statistics (without pagination)
    $query = "SELECT lot_status_id FROM lot WHERE warehouse_inout = 2";
    $all_lots = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_lots as $lot) {
        if ($lot['lot_status_id'] == 1) {
            $pending_lot_count++;
        } elseif ($lot['lot_status_id'] == 2) {
            $completed_lot_count++;
        }
    }
} catch (Exception $e) {
    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_name = $_POST['lot_name'] ?? '';
    $lot_detail = $_POST['lot_detail'] ?? '';
    $lot_date = $_POST['lot_date'] ?? date("Y-m-d H:i:s");
    $lot_status_id = $_POST['lot_status_id'] ?? $selected_status_id;
    $warehouse_id = $_POST['warehouse_id'] ?? '';
    $warehouse_from_id = $_POST['warehouse_from_id'] ?? '';
    $warehouse_inout = $_POST['warehouse_inout'] ?? '2';
    $personnel_id = $_POST['personnel_id'] ?? null;

    // Validate form data
    if (empty($lot_name) || empty($warehouse_id) || empty($warehouse_from_id)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน: ชื่อ Lot, คลังต้นทาง, และคลังปลายทาง";
    } elseif ($warehouse_id == $warehouse_from_id) {
        $error = "คลังต้นทางและคลังปลายทางต้องไม่เป็นคลังเดียวกัน";
    } else {
        try {
            $pdo->beginTransaction();

            // Insert new lot
            $stmt = $pdo->prepare(
                'INSERT INTO lot (lot_name, lot_detail, lot_date, lot_status_id, warehouse_id, personnel_id, warehouse_from_id, warehouse_inout) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$lot_name, $lot_detail, $lot_date, $lot_status_id, $warehouse_id, $personnel_id, $warehouse_from_id, $warehouse_inout]);

            $lot_id = $pdo->lastInsertId();
            if (!$lot_id) {
                throw new Exception("ไม่สามารถดึงค่า lot_id ได้");
            }

            $pdo->commit();

            // Redirect to lot details page
            header("Location: index.php?page=lot_listdetailout&tabel=lot&id=" . urlencode($lot_id) . "&success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Set timezone
date_default_timezone_set('Asia/Bangkok');
$displayDateTime = date("d/m/Y H:i:s");
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ย้ายสินค้า - ระบบจัดการคลังสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../style/page_insert.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .status-badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }
        }

        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-100">
    <div class="container mx-auto px-4 py-4">
        <!-- Error Alert -->
        <?php if (isset($error)): ?>
            <div id="alert" class="mb-6">
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg shadow-sm">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                        <p class="text-red-700 font-medium"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="px-4 py-4">
            <i class="far fa-clock text-blue-600 mr-2"></i>
            <span class="text-gray-700"><?= htmlspecialchars($displayDateTime) ?></span>
        </div>

        <!-- Main Content Section -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
            <!-- Form Section -->
            <div class="xl:col-span-2">
                <div class="glass-effect rounded-2xl shadow-2xl overflow-hidden hover-lift">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h3 class="text-xl font-bold text-white flex items-center">
                            <i class="fas fa-exchange-alt mr-2"></i>
                            ย้ายสินค้าจากคลัง
                        </h3>
                    </div>

                    <form class="p-6 space-y-4" method="POST" action="" id="lotForm">
                        <input type="hidden" name="lot_id" id="lot_id" value="">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Lot Name -->
                            <div class="form-group">
                                <label for="lot_name" class="block text-gray-700 font-semibold mb-1 text-base">
                                    ชื่อ Lot ย้ายสินค้า <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-tag text-blue-400 text-base"></i>
                                    </div>
                                    <input type="text" name="lot_name" id="lot_name"
                                        class="w-full border-2 border-blue-200 rounded-xl px-3 py-2 pl-10 form-input focus:outline-none focus:border-blue-500 transition-all duration-300 bg-blue-50/50"
                                        value="<?= htmlspecialchars($new_lot_name) ?>" required>
                                </div>
                            </div>

                            <!-- Warehouse From Selection -->
                            <div class="form-group">
                                <label for="warehouse_from_id" class="block text-gray-700 font-semibold mb-1 text-base">
                                    นำมาจากคลัง <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-dolly text-blue-400 text-base"></i>
                                    </div>
                                    <select name="warehouse_from_id" id="warehouse_from_id"
                                        class="w-full border-2 border-blue-200 rounded-xl pl-10 pr-10 py-2 appearance-none form-input focus:outline-none focus:border-blue-500 transition-all duration-300 bg-blue-50/50"
                                        required>
                                        <option value="">เลือกคลังต้นทาง</option>
                                        <?php foreach ($warehouse_lot as $warehouse): ?>
                                            <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                                <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-blue-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Warehouse To Selection -->
                            <div class="form-group">
                                <label for="warehouse_id" class="block text-gray-700 font-semibold mb-1 text-base">
                                    ลงคลัง <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-warehouse text-blue-400 text-base"></i>
                                    </div>
                                    <select name="warehouse_id" id="warehouse_id"
                                        class="w-full border-2 border-blue-200 rounded-xl pl-10 pr-10 py-2 appearance-none form-input focus:outline-none focus:border-blue-500 transition-all duration-300 bg-blue-50/50"
                                        required>
                                        <option value="">เลือกคลังปลายทาง</option>
                                        <?php foreach ($warehouse_lot as $warehouse): ?>
                                            <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                                <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-blue-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Personnel Selection -->
                            <div class="form-group">
                                <label for="personnel_id" class="block text-gray-700 font-semibold mb-1 text-base">
                                    พนักงาน <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-user text-blue-400 text-base"></i>
                                    </div>
                                    <select name="personnel_id" id="personnel_id"
                                        class="w-full border-2 border-blue-200 rounded-xl pl-10 pr-10 py-2 appearance-none form-input focus:outline-none focus:border-blue-500 transition-all duration-300 bg-blue-50/50"
                                        required>
                                        <option value="">เลือกพนักงาน</option>
                                        <?php foreach ($personnel_lot as $personnel): ?>
                                            <option value="<?= htmlspecialchars($personnel['personnel_id']) ?>">
                                                <?= htmlspecialchars($personnel['personnel_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-blue-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Lot Detail -->
                            <div class="form-group">
                                <label for="lot_detail" class="block text-gray-700 font-semibold mb-1 text-base">
                                    รายละเอียดการย้ายสินค้า
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-info-circle text-blue-400 text-base"></i>
                                    </div>
                                    <textarea name="lot_detail" id="lot_detail" rows="2"
                                        class="w-full border-2 border-blue-200 rounded-xl px-3 py-2 pl-10 form-input focus:outline-none focus:border-blue-500 transition-all duration-300 bg-blue-50/50 resize-none"
                                        placeholder="รายละเอียด..."></textarea>
                                </div>
                            </div>

                            <!-- Hidden Fields -->
                            <div class="form-group" hidden>
                                <input type="hidden" name="lot_status_id" value="<?= htmlspecialchars($selected_status_id) ?>">
                                <input type="hidden" name="warehouse_inout" value="2">
                                <input type="hidden" name="lot_date" value="<?= date("Y-m-d H:i:s") ?>">
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4">
                            <button type="submit"
                                class="w-full bg-gradient-to-r from-green-500 to-green-700 hover:from-green-600 hover:to-green-800 text-white text-base font-bold py-3 px-6 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center">
                                <i class="fas fa-save mr-2 text-base"></i>
                                บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="xl:col-span-1">
                <div class="glass-effect rounded-2xl shadow-2xl overflow-hidden hover-lift">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h3 class="text-xl font-bold text-white flex items-center">
                            <i class="fas fa-chart-bar mr-2"></i>
                            สถิติการย้ายสินค้าคลัง
                        </h3>
                    </div>

                    <div class="p-6 space-y-4">
                        <!-- Total Lots Card -->
                        <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white p-4 rounded-xl shadow-lg card-hover">
                            <div class="flex items-center">
                                <div class="bg-white/20 p-3 rounded-full mr-3">
                                    <i class="fas fa-boxes text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-base font-semibold mb-1">จำนวนย้ายทั้งหมด</h4>
                                    <p class="text-2xl font-bold"><?= htmlspecialchars($total_lot_count) ?> <span class="text-xs font-normal">ล็อต</span></p>
                                </div>
                            </div>
                        </div>

                        <!-- Completed Lots Card -->
                        <div class="bg-gradient-to-r from-green-500 to-green-700 text-white p-4 rounded-xl shadow-lg card-hover">
                            <div class="flex items-center">
                                <div class="bg-white/20 p-3 rounded-full mr-3">
                                    <i class="fas fa-check-circle text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-base font-semibold mb-1">จำนวนเสร็จสิ้น</h4>
                                    <p class="text-2xl font-bold"><?= htmlspecialchars($completed_lot_count) ?> <span class="text-xs font-normal">ล็อต</span></p>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Lots Card -->
                        <div class="bg-gradient-to-r from-amber-500 to-orange-600 text-white p-4 rounded-xl shadow-lg card-hover">
                            <div class="flex items-center">
                                <div class="bg-white/20 p-3 rounded-full mr-3">
                                    <i class="fas fa-hourglass-half text-xl status-badge"></i>
                                </div>
                                <div>
                                    <h4 class="text-base font-semibold mb-1">จำนวนรอดำเนินการ</h4>
                                    <p class="text-2xl font-bold"><?= htmlspecialchars($pending_lot_count) ?> <span class="text-xs font-normal">ล็อต</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Lots Table Section -->
        <div class="glass-effect rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        รายการย้ายสินค้าคลังล่าสุด
                    </h3>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gradient-to-r from-blue-50 to-blue-100 border-b border-blue-200">
                            <th class="px-6 py-3 text-left font-semibold text-gray-700">รหัส Lot</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700">ชื่อ Lot</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700">คลังต้นทาง</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700">คลังปลายทาง</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700">เจ้าหน้าที่</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700">วันที่</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700">สถานะ</th>
                            <th class="px-6 py-3 text-center font-semibold text-gray-700">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="lotsTableBody">
                        <?php if (empty($lots)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-600">ไม่มีรายการย้ายสินค้าคลัง</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lots as $lot): ?>
                                <tr class=" bg-white border-b border-blue-100 hover:bg-blue-50 transition-colors duration-200">
                                    <td class="px-6 py-3 font-medium text-blue-600"><?= htmlspecialchars($lot['lot_id']) ?></td>
                                    <td class="px-6 py-3 text-gray-700"><?= htmlspecialchars($lot['lot_name']) ?></td>
                                    <td class="px-6 py-3 text-gray-700"><?= htmlspecialchars($lot['warehouse_from_name'] ?? 'ไม่ระบุ') ?></td>
                                    <td class="px-6 py-3 text-gray-700"><?= htmlspecialchars($lot['warehouse_name'] ?? 'ไม่ระบุ') ?></td>
                                    <td class="px-6 py-3 text-gray-700"><?= htmlspecialchars($lot['personnel_name'] ?? 'ไม่ระบุ') ?></td>
                                    <td class="px-6 py-3 text-gray-700"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($lot['lot_date']))) ?></td>
                                    <td class="px-6 py-3">
                                        <span class="px-3 py-1 rounded-full text-sm font-medium
                                            <?php
                                            foreach ($status_lot as $status) {
                                                if ($status['lot_status_id'] == $lot['lot_status_id']) {
                                                    if ($status['lot_status_name'] == 'รอดำเนินการ') {
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                    } elseif ($status['lot_status_name'] == 'เสร็จสิ้น') {
                                                        echo 'bg-green-100 text-green-800';
                                                    } else {
                                                        echo 'bg-blue-100 text-blue-800';
                                                    }
                                                    break;
                                                }
                                            }
                                            ?>">
                                            <?= htmlspecialchars($lot['lot_status_name']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <a href="index.php?page=lot_listdetailout&tabel=lot&id=<?= urlencode($lot['lot_id']) ?>"
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 inline-flex items-center">
                                            <i class="fas fa-eye mr-1"></i>ดู
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Footer with Pagination -->
            <div class="bg-blue-50 px-6 py-4 border-t border-blue-200">
                <div class="flex justify-between items-center">
                    <p class="text-gray-600">แสดง <?= count($lots) ?> จาก <?= htmlspecialchars($total_lot_count) ?> รายการ</p>
                    <div class="flex space-x-2">
                        <?php if ($total_pages > 1): ?>
                            <?php if ($page > 1): ?>
                                <a href="?page=lot_insertout&page_num=<?= $page - 1 ?>"
                                    class="bg-white border border-blue-300 text-blue-600 px-3 py-1 rounded-lg hover:bg-blue-50 transition-colors duration-200">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            if ($start_page > 1): ?>
                                <a href="?page=lot_insertout&page_num=1"
                                    class="bg-white border border-blue-300 text-blue-600 px-3 py-1 rounded-lg hover:bg-blue-50 transition-colors duration-200">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="px-3 py-1 text-gray-500">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=lot_insertout&page_num=<?= $i ?>"
                                    class="px-3 py-1 rounded-lg transition-colors duration-200 <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white border border-blue-300 text-blue-600 hover:bg-blue-50' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="px-3 py-1 text-gray-500">...</span>
                                <?php endif; ?>
                                <a href="?page=lot_insertout&page_num=<?= $total_pages ?>"
                                    class="bg-white border border-blue-300 text-blue-600 px-3 py-1 rounded-lg hover:bg-blue-50 transition-colors duration-200"><?= $total_pages ?></a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=lot_insertout&page_num=<?= $page + 1 ?>"
                                    class="bg-white border border-blue-300 text-blue-600 px-3 py-1 rounded-lg hover:bg-blue-50 transition-colors duration-200">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse Lots Modal -->
        <?php if (isset($_GET['page']) && $_GET['page'] === 'warehouse_lot' && isset($_GET['warehouse_id'])): ?>
            <?php
            $warehouse_id = $_GET['warehouse_id'];
            $warehouse_name = "";
            foreach ($warehouse_lot as $warehouse) {
                if ($warehouse['warehouse_id'] == $warehouse_id) {
                    $warehouse_name = $warehouse['warehouse_name'];
                    break;
                }
            }
            $query = "
                SELECT 
                    l.lot_id,
                    l.lot_name,
                    l.lot_detail,
                    l.lot_date,
                    l.lot_status_id,
                    ls.lot_status_name,
                    pn.personnel_name,
                    w1.warehouse_name AS warehouse_from_name
                FROM lot l
                LEFT JOIN warehouse w1 ON l.warehouse_from_id = w1.warehouse_id
                INNER JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
                LEFT JOIN personnel pn ON l.personnel_id = pn.personnel_id
                WHERE l.warehouse_id = :warehouse_id AND l.warehouse_inout = 2";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['warehouse_id' => $warehouse_id]);
            $warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" id="warehouseLotsModal">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4 border-b border-blue-200 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-white flex items-center">
                            <i class="fas fa-warehouse mr-2"></i>Lots ในคลัง: <?= htmlspecialchars($warehouse_name) ?>
                        </h2>
                        <button onclick="window.history.back()" class="text-white hover:text-gray-200">
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
                                    <div class="bg-white border rounded-lg shadow-sm hover:shadow-md transition-shadow p-4 cursor-pointer hover:bg-blue-50"
                                        onclick="window.location.href='?page=lot_listdetailout&tabel=lot&id=<?= htmlspecialchars($lot['lot_id']) ?>'">
                                        <div class="flex justify-between items-center">
                                            <h3 class="text-base font-medium text-blue-600"><?= htmlspecialchars($lot['lot_name']) ?></h3>
                                            <span class="text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($lot['lot_date'])) ?></span>
                                        </div>
                                        <p class="text-gray-600 mt-2 text-sm">
                                            <?= !empty($lot['lot_detail']) ? htmlspecialchars($lot['lot_detail']) : "ไม่มีรายละเอียด" ?>
                                        </p>
                                        <div class="mt-2 flex items-center text-sm">
                                            <span class="px-2 py-1 rounded
                                                <?php
                                                foreach ($status_lot as $status) {
                                                    if ($status['lot_status_id'] == $lot['lot_status_id']) {
                                                        if ($status['lot_status_name'] == 'รอดำเนินการ') {
                                                            echo 'bg-yellow-100 text-yellow-800';
                                                        } elseif ($status['lot_status_name'] == 'เสร็จสิ้น') {
                                                            echo 'bg-green-100 text-green-800';
                                                        } else {
                                                            echo 'bg-blue-100 text-blue-800';
                                                        }
                                                        break;
                                                    }
                                                }
                                                ?>">
                                                <?= htmlspecialchars($lot['lot_status_name']) ?>
                                            </span>
                                            <?php if (!empty($lot['personnel_name'])): ?>
                                                <span class="ml-2 text-gray-500">
                                                    <i class="fas fa-user mr-1"></i><?= htmlspecialchars($lot['personnel_name']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="bg-blue-50 px-6 py-3 border-t border-blue-200 text-right">
                        <button onclick="window.history.back()"
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg transition-colors duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>กลับ
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- JavaScript Functions -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const alert = document.getElementById('alert');
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
    </div>
</body>

</html>