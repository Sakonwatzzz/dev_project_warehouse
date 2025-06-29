<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'stock_list'] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Function to fetch data
function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        throw new Exception("Database error: " . htmlspecialchars($e->getMessage()));
    }
}

// Verify CSRF token
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Validate stock check data
function validateStockCheckData($stock_check_actual, $stock_check_remark)
{
    $errors = [];
    if (!is_numeric($stock_check_actual) || $stock_check_actual < 0) {
        $errors[] = 'จำนวนสินค้าที่นับได้จริงต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (strlen($stock_check_remark) > 255) {
        $errors[] = 'หมายเหตุต้องมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    return $errors;
}

// Handle search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle POST requests for updating stock check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=stock_list&error=Invalid CSRF token');
        exit;
    }

    $stock_check_id = filter_var($_POST['stock_check_id'], FILTER_VALIDATE_INT);
    $stock_check_actual = trim($_POST['stock_check_actual']);
    $stock_check_remark = trim($_POST['stock_check_remark']);

    $errors = validateStockCheckData($stock_check_actual, $stock_check_remark);
    if (!$stock_check_id) {
        $errors[] = 'รหัสการเช็คสต๊อกไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=stock_list&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE stock_check SET stock_check_actual = ?, stock_check_remark = ? WHERE stock_check_id = ?");
        $stmt->execute([$stock_check_actual, $stock_check_remark, $stock_check_id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=stock_list&error=ไม่พบข้อมูลการเช็คสต๊อกที่ต้องการแก้ไข');
            exit;
        }
        $pdo->commit();
        header('Location: index.php?page=stock_list&success=แก้ไขข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update stock check error: " . $e->getMessage());
        header('Location: index.php?page=stock_list&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['stock_check_id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=stock_list&error=Invalid CSRF token');
        exit;
    }

    $stock_check_id = filter_var($_POST['stock_check_id'], FILTER_VALIDATE_INT);
    if (!$stock_check_id) {
        header('Location: index.php?page=stock_list&error=Invalid stock check ID');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM stock_check WHERE stock_check_id = ?");
        $stmt->execute([$stock_check_id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=stock_list&error=ไม่พบข้อมูลการเช็คสต๊อกที่ต้องการลบ');
            exit;
        }
        $pdo->commit();
        header("Location: index.php?page=stock_list&success=ลบข้อมูลสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete stock check error: Stock Check ID=$stock_check_id, Error=" . $e->getMessage());
        header("Location: index.php?page=stock_list&error=" . urlencode('เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total_count 
                    FROM stock_check sc
                    LEFT JOIN product p ON sc.product_id = p.product_id
                    LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
                    LEFT JOIN stock s ON sc.stock_id = s.stock_id
                    WHERE s.check_status_id = 2 
                    AND s.stock_date >= :today_start 
                    AND s.stock_date <= :today_end";
    $params = [
        ':today_start' => date('Y-m-d 00:00:00'),
        ':today_end' => date('Y-m-d 23:59:59')
    ];
    if (!empty($search)) {
        $count_query .= " AND (sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_record = (int) $stmt->fetchColumn();
    // error_log("Total records: " . $total_record); // Debug log

} catch (PDOException $e) {
    error_log("Count stock check error: " . $e->getMessage());
    // header('Location: index.php?page=stock_list&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

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
            sc.stock_check_remark,
            per.personnel_name,
            sc.lot_product_id,
            lp.lot_product_remain,
            s.stock_id,
            s.stock_date,
            sc.stock_check_system,
            sc.stock_check_actual,
            s.check_status_id,
            scs.check_status_name
        FROM stock_check sc
        LEFT JOIN product p ON sc.product_id = p.product_id
        LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
        LEFT JOIN lot l ON sc.lot_id = l.lot_id
        LEFT JOIN lot_product lp ON sc.lot_product_id = lp.lot_product_id
        LEFT JOIN stock s ON sc.stock_id = s.stock_id
        LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
        LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
        WHERE s.check_status_id = 2
        AND s.stock_date >= :today_start 
        AND s.stock_date <= :today_end";
    $params = [
        ':today_start' => date('Y-m-d 00:00:00'),
        ':today_end' => date('Y-m-d 23:59:59')
    ];
    if (!empty($search)) {
        $query .= " AND (sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    // $query .= " ORDER BY s.stock_date DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    // $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
    // $stmt->bindValue(':limit', (int) $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $check_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Fetched rows: " . count($check_stock)); // Debug log
} catch (PDOException $e) {
    error_log("Query stock check error: " . $e->getMessage());
    // header('Location: index.php?page=stock_list&error=' . urlencode('Query error: ' . htmlspecialchars($e->getMessage())));
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

        .input-update {
            border-color: black;
            border-radius: 3px;
        }

        .flatpickr-input {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' /%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5em;
        }

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
    </style>
</head>

<body class="bg-gray-50">
    <div class="mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-6">
            <div class="flex flex-col gap-2 md:flex-row md:space-x-2 md:items-center">
                <div class="flex space-x-2">
                    <div class="w-auto px-4 py-2 bg-blue-200 border rounded font-bold text-blue-800">
                        <i class="fas fa-boxes mr-2"></i>
                        <?php echo $total_record; ?>
                        <span>รายการ (วันที่ <?php echo (date('d/m/Y', strtotime($selected_date))); ?>)</span>
                    </div>
                </div>

            </div>
            <div class="flex items-center">
                <form action="" method="GET" id="searchForm" class="w-full text-end">
                    <input type="hidden" name="page" value="stock_list">
                    <input type="hidden" name="page_num" value="1">
                    <input type="hidden" name="selected_date" id="hiddenSelectedDate" value="<?php echo ($selected_date); ?>">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput" placeholder="ค้นหาการเช็คสต๊อก..."
                            value="<?php echo ($search); ?>"
                            class="w-full md:w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="bg-white shadow-md rounded-xl p-4 space-y-4 border border-gray-200 md:flex md:gap-4 md:bg-transparent md:shadow-none md:border-none md:p-0 md:w-auto">
                <!-- Export Buttons -->
                <div class="grid grid-cols-2 gap-4 pt-2  md:grid md:grid-cols-2 md:gap-4">
                    <button onclick="exportPDF()"
                        class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-red-500 text-white text-sm sm:text-base hover:bg-red-600 transition duration-200 shadow-md md:auto">
                        <i class="fas fa-file-pdf"></i> ส่งออก PDF
                    </button>
                    <button onclick="exportExcel()"
                        class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-emerald-500 text-white text-sm sm:text-base hover:bg-emerald-600 transition duration-200 shadow-md md:auto">
                        <i class="fas fa-file-excel"></i> ส่งออก Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div id="notification" class="fixed top-5 right-5 w-80 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden hidden">
            <div id="notification-header" class="px-4 py-2 font-bold text-white"></div>
            <div class="px-4 py-3" id="notification-message"></div>
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
                        <span>ชื่อผู้ใช้งาน</span>
                        <i class="fas fa-user h-6 w-6"></i>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold" hidden>รหัสรายการเช็คสต๊อก</th>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($check_stock) > 0): ?>
                                <?php foreach ($check_stock as $stock): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-6 py-2 border" hidden><?php echo htmlspecialchars($stock['stock_check_id']); ?></td>
                                        <td class="px-6 py-2 border" hidden><?php echo htmlspecialchars($stock['stock_id']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['stock_date']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['warehouse_name']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['product_id']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['product_name']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['stock_check_system']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['stock_check_actual']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['personnel_name']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['check_status_name']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['stock_check_remark_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-gray-600">ไม่มีข้อมูลการเช็คสต๊อก</td>
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
        <div id="editModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 invisible opacity-0 transition-all duration-300">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">แก้ไขข้อมูลการเช็คสต๊อก</h3>
                <form id="editForm" method="POST" class="space-y-4">
                    <input type="hidden" id="editStockCheckId" name="stock_check_id">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">จำนวนที่นับได้</label>
                        <input type="number" step="0.01" id="editStockCheckActual" name="stock_check_actual"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 input-update"
                            required>
                        <span id="editActualError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">หมายเหตุ</label>
                        <input type="text" id="editStockCheckRemark" name="stock_check_remark"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 input-update">
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
        <div id="deleteModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 invisible opacity-0 transition-all duration-300">
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
    <script>
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

        // Midnight refresh
        function checkMidnightRefresh() {
            const now = new Date();
            const hours = now.getHours();
            const minutes = now.getMinutes();
            const seconds = now.getSeconds();
            // Check if it's 00:00:00
            if (hours === 0 && minutes === 0 && seconds === 0) {
                window.location.reload();
            }
        }
        setInterval(checkMidnightRefresh, 1000);

        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', 'stock_list');
                urlParams.delete('search');
                window.location.search = urlParams.toString();
            }
        }

        function showEditModal(id, actual, remark) {
            document.getElementById('editStockCheckId').value = id;
            document.getElementById('editStockCheckActual').value = actual;
            document.getElementById('editStockCheckRemark').value = remark;
            document.getElementById('editActualError').classList.add('hidden');
            document.getElementById('editRemarkError').classList.add('hidden');
            document.getElementById('editModal').classList.remove('invisible', 'opacity-0');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('invisible', 'opacity-0');
        }

        function showDeleteModal() {
            document.getElementById('deleteModal').classList.remove('invisible', 'opacity-0');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('invisible', 'opacity-0');
        }

        function confirmDelete(form) {
            showDeleteModal();
            document.getElementById('confirmDeleteBtn').onclick = () => {
                form.submit();
            };
        }

        function showNotification(message, className) {
            const notification = document.getElementById('notification');
            const header = document.getElementById('notification-header');
            const messageDiv = document.getElementById('notification-message');
            header.className = `px-4 py-2 font-bold text-white ${className}`;
            header.textContent = 'การแจ้งเตือน';
            messageDiv.textContent = message;
            notification.classList.remove('hidden');
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 5000);
        }

        function exportPDF() {
            const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
            let url = '/page/stock/report/export_stock_pdf.php?csrf_token=' + encodeURIComponent(csrfToken);
            window.open(url, '_blank');
        }

        function exportExcel() {
            const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
            let url = '/page/stock/report/export_stock_excel.php?csrf_token=' + encodeURIComponent(csrfToken);
            window.open(url, '_blank');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;

            searchInput.focus();

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    this.form.submit();
                }
                if (e.key === 'Enter') {
                    localStorage.setItem('searchPreserve', this.value);
                }
            });

            const preserved = localStorage.getItem('searchPreserve');
            if (preserved) {
                searchInput.value = preserved;
                searchInput.focus();
                searchInput.selectionStart = searchInput.selectionEnd = preserved.length;
                localStorage.removeItem('searchPreserve');
            }

            searchInput.addEventListener('input', function() {
                checkEmptySearch(this);
            });

            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const urlParams = new URLSearchParams();
                urlParams.set('page', 'stock_list');
                urlParams.set('page_num', 1);
                if (searchInput.value) urlParams.set('search', searchInput.value);
                window.location.search = urlParams.toString();
            });

            if (urlParams.has('success')) {
                showNotification(urlParams.get('success'), 'bg-green-500');
            } else if (urlParams.has('error')) {
                showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
            }

            function validateForm(formId, fields) {
                let hasError = false;
                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const error = document.getElementById(field.errorId);
                    if (field.required && !input.value.trim()) {
                        error.textContent = `กรุณากรอก${field.label}`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (field.maxLength && input.value.length > field.maxLength) {
                        error.textContent = `${field.label}ยาวเกิน ${field.maxLength} ตัวอักษร`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (field.type === 'number' && (isNaN(input.value) || input.value < 0)) {
                        error.textContent = `${field.label}ต้องเป็นตัวเลขที่ไม่ติดลบ`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else {
                        error.classList.add('hidden');
                    }
                });
                return !hasError;
            }

            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fields = [{
                        id: 'editStockCheckActual',
                        errorId: 'editActualError',
                        label: 'จำนวนที่นับได้',
                        type: 'number',
                        required: true,
                        maxLength: Infinity
                    },
                    {
                        id: 'editStockCheckRemark',
                        errorId: 'editRemarkError',
                        label: 'หมายเหตุ',
                        required: false,
                        maxLength: 255
                    }
                ];
                if (validateForm('editForm', fields)) {
                    this.submit();
                }
            });
        });
    </script>
</body>

</html>