<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection();
date_default_timezone_set('Asia/Bangkok');


$accessToken = getToken('access_token');
if (!$accessToken || !validateToken($accessToken)) {
    $refreshToken = getToken('refresh_token');
    if ($refreshToken && validateToken($refreshToken)) {
        $accessToken = refreshToken($refreshToken);
    } else {
        header('Location: login.php');
        exit();
    }
}

// ตรวจสอบ session (คงเดิม)

// if (!isset($_SESSION['personnel_id']) || !is_numeric($_SESSION['personnel_id'])) {
//     header("Location: login.php?error=กรุณาเข้าสู่ระบบ");
//     exit;
// }

// Create CSRF token ()
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// Check session timeout (คงเดิม)
// if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {
//     session_unset();
//     session_destroy();
//     $query_params = http_build_query(['timeout' => 1, 'page' => 'stock_list'] + ($_GET ?? []));
//     header("Location: login.php?$query_params");
//     exit;
// }
// $_SESSION['LAST_ACTIVITY'] = time();

// Function to fetch data ()
function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            // ใช้ PDO::PARAM_INT สำหรับ :offset และ :limit, อื่น ๆ ใช้ PDO::PARAM_STR
            $paramType = ($key === ':offset' || $key === ':limit') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        header('Location: index.php?page=stock_list&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Verify CSRF token ()
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
// if (!isset($_SESSION['csrf_token']) || !isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
//     error_log("CSRF Validation Failed: Session Token: " . ($_SESSION['csrf_token'] ?? 'unset') . ", GET Token: " . ($_GET['csrf_token'] ?? 'unset'));
//     header('HTTP/1.1 403 Forbidden');
//     exit('Invalid CSRF token');
// }

// Validate stock check data ()
function validateStockCheckData($stock_check_actual, $stock_check_remark_id)
{
    $errors = [];
    if (!is_numeric($stock_check_actual) || $stock_check_actual < 0) {
        $errors[] = 'จำนวนสินค้าที่นับได้จริงต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!in_array($stock_check_remark_id, ['', '1', '2'])) {
        $errors[] = 'หมายเหตุไม่ถูกต้อง';
    }
    return $errors;
}

// Handle search parameters ()
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '';
$date_range = isset($_GET['date_range']) ? htmlspecialchars($_GET['date_range']) : 'this_year';
if ($date_range === 'custom' && empty($start_date)) {
    $start_date = date('Y-m-d');
}
if ($date_range === 'custom' && empty($end_date)) {
    $end_date = date('Y-m-d');
}
// สร้างเงื่อนไข WHERE และพารามิเตอร์
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search OR p.product_id LIKE :search OR per.personnel_name LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($date_range === 'today') {
    $where_conditions[] = "DATE(s.stock_date) = CURDATE()";
} elseif ($date_range === 'this_month') {
    $where_conditions[] = "YEAR(s.stock_date) = YEAR(CURDATE()) AND MONTH(s.stock_date) = MONTH(CURDATE())";
} elseif ($date_range === 'this_year') {
    $where_conditions[] = "YEAR(s.stock_date) = YEAR(CURDATE())";
} elseif ($date_range === 'custom' && $start_date && $end_date) {
    $where_conditions[] = "DATE(s.stock_date) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}
$where_clause = !empty($where_conditions) ? 'WHERE s.check_status_id = 2 AND ' . implode(' AND ', $where_conditions) : 'WHERE s.check_status_id = 2';
// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total_count 
                    FROM stock_check sc
                    LEFT JOIN product p ON sc.product_id = p.product_id
                    LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
                    LEFT JOIN stock s ON sc.stock_id = s.stock_id
                    LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
                    $where_clause";
    $stmt = fetchData($pdo, $count_query, $params);
    $total_record = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Database error in stock_list.php: " . $e->getMessage());
    header('Location: index.php?page=stock_list&error=' . urlencode('เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่'));
    exit;
}
// Pagination logic
$records_per_page = 10;
$page = isset($_GET['page_num']) ? max(1, min((int) $_GET['page_num'], ceil($total_record / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_record / $records_per_page));

// Fetch stock check data
try {
    $query = "
        SELECT 
            sc.stock_check_id,
            sc.product_id,
            p.product_name,
            sc.lot_id,
            sc.warehouse_id,
            w.warehouse_name,
            scr.stock_check_remark_name,
            per.personnel_name,
            sc.lot_product_id,
            lp.lot_product_remain,
            s.stock_id,
            s.stock_date,
            sc.stock_check_system,
            sc.stock_check_actual,
            s.check_status_id,
            scs.check_status_name,
            sc.stock_check_remark_id
        FROM stock_check sc
        LEFT JOIN product p ON sc.product_id = p.product_id
        LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
        LEFT JOIN lot l ON sc.lot_id = l.lot_id
        LEFT JOIN lot_product lp ON sc.lot_product_id = lp.lot_product_id
        LEFT JOIN stock s ON sc.stock_id = s.stock_id
        LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
        LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
        LEFT JOIN stock_check_remark scr ON sc.stock_check_remark_id = scr.stock_check_remark_id
        $where_clause
        ORDER BY s.stock_date DESC LIMIT :offset, :limit";
    $params[':offset'] = (int) $offset;
    $params[':limit'] = (int) $records_per_page;
    $stmt = fetchData($pdo, $query, $params);
    $check_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=stock_list&error=' . urlencode($e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติรายการเช็คสต๊อกสินค้า</title>
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }

        /* สไตล์สำหรับ Flatpickr */
        .flatpickr-input {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' /%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5em;
        }

        /* ปรับแต่ง Flatpickr Calendar */
        .flatpickr-calendar {
            @apply bg-white shadow-lg rounded-lg border border-gray-200;
        }

        .flatpickr-day.selected {
            @apply bg-blue-500 border-blue-500 text-white;
        }

        .flatpickr-day.today {
            @apply border-blue-400;
        }

        .flatpickr-day:hover {
            @apply bg-blue-100;
        }

        .flatpickr-monthDropdown-month,
        .flatpickr-current-month .numInput {
            @apply text-gray-700;
        }

        .flatpickr-prev-month,
        .flatpickr-next-month {
            @apply text-blue-500 hover:text-blue-700;
        }
    </style>
</head>

<body class="bg-gray-50">

    <div class="mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-6">
            <div class="flex flex-col gap-2 md:flex-row md:space-x-2 md:items-center">
                <div class="flex space-x-2">
                    <div class="w-auto px-4 py-2 bg-blue-200 border rounded font-bold text-blue-800 flex gap-2">
                        <i class="fas fa-boxes mr-2"></i>
                        จำนวนรายการเช็คสต๊อก <P class=""> <?php echo $total_record; ?> </P> รายการ
                    </div>
                </div>
            </div>
            <div class="flex gap-4
      ">
                <div class="flex items-center gap-2">
                    <h2 class="text-gray-700 font-medium">ช่วงวันที่</h2>
                    <select id="dateRangeSelect" name="date_range"
                        class="custom-select border border-gray-200 rounded-md px-4 py-2 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition duration-200 hover:bg-blue-50 w-40">
                        <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>วันนี้</option>
                        <option value="this_month" <?php echo $date_range === 'this_month' ? 'selected' : ''; ?>>เดือนนี้
                        </option>
                        <option value="this_year" <?php echo $date_range === 'this_year' ? 'selected' : ''; ?>>ปีนี้
                        </option>
                        <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>กำหนดเอง</option>
                    </select>
                </div>
                <div id="customDateRange"
                    class="flex items-center space-x-2 transition-all duration-300 transform <?php echo $date_range !== 'custom' ? 'opacity-0 scale-95 hidden' : 'opacity-100 scale-100'; ?>">
                    <input type="text" id="startDate"
                        class="flatpickr-input border border-gray-200 rounded-md px-4 py-2 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition duration-200 hover:bg-gray-50 w-36"
                        value="<?php echo htmlspecialchars($start_date ? date('d/m/Y', strtotime($start_date)) : date('d/m/Y')); ?>">
                    <span class="text-gray-600 bg-gray-100 px-3 py-1 rounded-full font-medium">ถึง</span>
                    <input type="text" id="endDate"
                        class="flatpickr-input border border-gray-200 rounded-md px-4 py-2 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition duration-200 hover:bg-gray-50 w-36"
                        value="<?php echo htmlspecialchars($end_date ? date('d/m/Y', strtotime($end_date)) : date('d/m/Y')); ?>">
                </div>
                <div class="flex items-center">
                    <form action="" method="GET" id="searchForm" class="w-full text-end">
                        <input type="hidden" name="page" value="stock_list">
                        <input type="hidden" name="page_num" value="1">
                        <div class="relative">
                            <input type="text" name="search" id="searchInput" placeholder="ค้นหาการเช็คสต๊อก..."
                                value="<?php echo htmlspecialchars($search); ?>"
                                class="w-full md:w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit"
                                class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <button id="resetFilter"
                    class="py-2 p-2 rounded-lg bg-purple-500 hover:bg-purple-600 text-white shadow-sm transition duration-200 hover:bg-purple-600">รีเซ็ตการกรอง</button>

            </div>
            <div
                class="bg-white shadow-md rounded-xl p-4 space-y-4 border border-gray-200 md:flex md:gap-4 md:bg-transparent md:shadow-none md:border-none md:p-0 md:w-auto">
                <!-- Export Buttons -->
                <div class="grid grid-cols-2 gap-4 pt-2 md:grid md:grid-cols-2 md:gap-4">
                    <button id="exportPDFBtn"
                        class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-red-500 text-white text-sm sm:text-base hover:bg-red-600 transition duration-200 shadow-md md:auto">
                        <i class="fas fa-file-pdf"></i> ส่งออก PDF
                    </button>
                    <button id="exportExcelBtn"
                        class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-emerald-500 text-white text-sm sm:text-base hover:bg-emerald-600 transition duration-200 shadow-md md:auto">
                        <i class="fas fa-file-excel"></i> ส่งออก Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-blue-600 p-4 border-b border-[#cfd5dc]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <h2 class="text-xl sm:text-2xl font-bold flex items-center text-white">
                        <i class="fas fa-list mr-2"></i> ประวัติรายการเช็คสต๊อกสินค้า
                    </h2>
                    <div class="flex items-center gap-4 text-white">
                        <span id="clock-realtime"></span>
                        <i class="fas fa-user h-6 w-6"></i>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold" hidden>
                                    รหัสรายการเช็คสต๊อก</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold" hidden>รหัสสต๊อก</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">คลังสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัสสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">จำนวนจากระบบ</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">จำนวนที่นับได้</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">พนักงาน</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">สถานะ</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">หมายเหตุ</th>
                                <th class="px-6 py-3 border text-center text-gray-700 font-semibold hidden">การจัดการ
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($check_stock) > 0): ?>
                                <?php foreach ($check_stock as $stock): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-6 py-2 border" hidden>
                                            <?php echo htmlspecialchars($stock['stock_check_id']); ?>
                                        </td>
                                        <td class="px-6 py-2 border" hidden><?php echo htmlspecialchars($stock['stock_id']); ?>
                                        </td>
                                        <td class="px-6 py-2 border">
                                            <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($stock['stock_date']))); ?>
                                        </td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['warehouse_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['product_id']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['product_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border">
                                            <?php echo htmlspecialchars($stock['stock_check_system']); ?>
                                        </td>
                                        <td class="px-6 py-2 border">
                                            <?php echo htmlspecialchars($stock['stock_check_actual']); ?>
                                        </td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['personnel_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border">
                                            <?php echo htmlspecialchars($stock['check_status_name']); ?>
                                        </td>
                                        <td
                                            class="px-6 py-2 border <?php echo ($stock['stock_check_remark_id'] == 2) ? 'text-red-600 font-bold' : ''; ?>">
                                            <?php echo htmlspecialchars($stock['stock_check_remark_name'] ?? '-'); ?>
                                        </td>
                                        <td class="px-6 py-2 border text-center hidden">
                                            <button
                                                onclick="showEditModal(<?php echo $stock['stock_check_id']; ?>, '<?php echo $stock['stock_check_actual']; ?>', '<?php echo $stock['stock_check_remark_id']; ?>')"
                                                class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">
                                                แก้ไข
                                            </button>
                                            <form method="POST" style="display:inline;"
                                                onsubmit="confirmDelete(this); return false;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="stock_check_id"
                                                    value="<?php echo $stock['stock_check_id']; ?>">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <button type="submit"
                                                    class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
                                                    ลบ
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-gray-600">ไม่มีข้อมูลการเช็คสต๊อก</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=stock_list&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=stock_list&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=stock_list&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=stock_list&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=stock_list&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Edit Modal -->
        <div id="editModal"
            class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 invisible opacity-0 transition-all duration-300">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">แก้ไขข้อมูลการเช็คสต๊อก</h3>
                <form id="editForm" method="POST" class="space-y-4">
                    <input type="hidden" id="editStockCheckId" name="stock_check_id">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">จำนวนที่นับได้</label>
                        <input type="number" step="0.01" id="editStockCheckActual" name="stock_check_actual"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="editActualError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">หมายเหตุ</label>
                        <select id="editStockCheckRemark" name="stock_check_remark_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">เลือกหมายเหตุ</option>
                            <option value="1">ปกติ</option>
                            <option value="2">ขาดหาย</option>
                        </select>
                        <span id="editRemarkError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                            ยกเลิก
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                            บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal"
            class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 invisible opacity-0 transition-all duration-300">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">ยืนยันการลบ</h3>
                <p class="text-gray-600 mb-6">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?</p>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        ยกเลิก
                    </button>
                    <button type="button" id="confirmDeleteBtn"
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                        ลบ
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script src="page/stock/js/stock_list_search.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('searchInput');

            function showNotification(message, bgClass) {
                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white ${bgClass}`;
                notification.textContent = message;
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 5000);
            }

            // Export PDF
            document.getElementById('exportPDFBtn').addEventListener('click', () => {
                document.getElementById('exportPDFBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังส่งออก...';
                const data = {
                    csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>',
                    search: searchInput.value,
                    date_range: document.getElementById('dateRangeSelect').value,
                    start_date: document.getElementById('startDate').value ? document.getElementById('startDate').value.split('/').reverse().join('-') : '',
                    end_date: document.getElementById('endDate').value ? document.getElementById('endDate').value.split('/').reverse().join('-') : ''
                };
                fetch('<?php echo dirname($_SERVER['PHP_SELF'], 3); ?>/page/stock/report/export_stock_pdf.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.blob())
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'stock_report.pdf';
                        a.click();
                        window.URL.revokeObjectURL(url);
                    })
                    .catch(error => {
                        console.error('Error exporting PDF:', error);
                        showNotification('เกิดข้อผิดพลาดในการส่งออก PDF', 'bg-red-500');
                    })
                    .finally(() => {
                        document.getElementById('exportPDFBtn').innerHTML = '<i class="fas fa-file-pdf"></i> ส่งออก PDF';
                    });
            });

            // Export Excel
            document.getElementById('exportExcelBtn').addEventListener('click', () => {
                document.getElementById('exportExcelBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังส่งออก...';
                const data = {
                    csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>',
                    search: searchInput.value,
                    date_range: document.getElementById('dateRangeSelect').value,
                    start_date: document.getElementById('startDate').value ? document.getElementById('startDate').value.split('/').reverse().join('-') : '',
                    end_date: document.getElementById('endDate').value ? document.getElementById('endDate').value.split('/').reverse().join('-') : ''
                };
                fetch('<?php echo dirname($_SERVER['PHP_SELF'], 3); ?>/page/stock/report/export_stock_excel.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.blob())
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'stock_report.xlsx';
                        a.click();
                        window.URL.revokeObjectURL(url);
                    })
                    .catch(error => {
                        console.error('Error exporting Excel:', error);
                        showNotification('เกิดข้อผิดพลาดในการส่งออก Excel', 'bg-red-500');
                    })
                    .finally(() => {
                        document.getElementById('exportExcelBtn').innerHTML = '<i class="fas fa-file-excel"></i> ส่งออก Excel';
                    });
            });

            function updateClock() {
                const now = new Date();
                const day = String(now.getDate()).padStart(2, '0');
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const year = now.getFullYear();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                const formattedDate = `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
                document.getElementById('clock-realtime').textContent = formattedDate;
            }
            setInterval(updateClock, 1000);
            updateClock();
        });
    </script>
</body>

</html>