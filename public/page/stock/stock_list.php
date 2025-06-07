<?php
require_once __DIR__ . '.././../../../config/database_server.php';
session_start();
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

// Set database timezone to match PHP
$pdo->exec("SET time_zone = '+07:00'");

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

// Handle search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';


// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total_count 
                    FROM stock_check sc
                    LEFT JOIN stock s ON sc.stock_id = s.stock_id
                    WHERE s.check_status_id = 2 
                    AND DATE(s.stock_date) = DATE(NOW())";
    $params = [];
    if (!empty($search)) {
        $count_query .= " AND (sc.stock_check_id LIKE :search OR s.stock_id LIKE :search OR sc.product_id LIKE :search)";
        $params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count stock check error: " . $e->getMessage());
    header('Location: index.php?page=stock_list&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int) $_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
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
        sc.stock_check_comment,
        per.personnel_name,
        sc.lot_product_id,
        lp.lot_product_remain,
        s.stock_id,
        s.stock_date,
        sc.stock_check_system,
        sc.stock_check_actual,  
        s.check_status_id,
        scs.check_status_name,
        scr.stock_check_remark_id,
        scr.stock_check_remark_name
    FROM stock_check sc
    LEFT JOIN product p ON sc.product_id = p.product_id
    LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
    LEFT JOIN lot l ON sc.lot_id = l.lot_id
    LEFT JOIN lot_product lp ON sc.lot_product_id = lp.lot_product_id
    LEFT JOIN stock s ON sc.stock_id = s.stock_id
    LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
    LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
    LEFT JOIN stock_check_remark scr ON sc.stock_check_remark_id = scr.stock_check_remark_id
    WHERE s.check_status_id = 2
    AND DATE(s.stock_date) = DATE(NOW())";
    $params = [];
    if (!empty($search)) {
        $query .= " AND (sc.stock_check_id LIKE :search OR s.stock_id LIKE :search)";
        $params[':search'] = "%$search%";
    }
    $query .= " ORDER BY s.stock_date DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int) $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $check_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Query stock check error: " . $e->getMessage());
    header('Location: index.php?page=stock_list&error=' . urlencode('Query error: ' . htmlspecialchars($e->getMessage())));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติรายการเช็คสต๊อกสินค้า - รายวัน</title>
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }
    </style>
    <script src="page/stock/js/stock_list.js"></script>
</head>

<body class="bg-gray-50">
    <div class="mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-6">
            <div class="flex flex-col gap-2 md:flex-row md:space-x-2 md:items-center">
                <div class="flex justify-center items-center">
                    <div class="px-4 py-2 bg-gray-200 border rounded font-bold text-blue-800" hidden>
                        <!-- <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 48 48">
                            <g fill="none" stroke="#000" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="4">
                                <path d="M5 10L8 13L14 7" />
                                <path d="M5 24L8 27L14 21" />
                                <path d="M5 38L8 41L14 35" />
                                <path d="M21 24H43" />
                                <path d="M21 38H43" />
                                <path d="M21 10H43" />
                            </g>
                        </svg> -->
                        <div class="text-xl">
                            <span class="">ออกรายงานประจำวันที่:
                                <?php echo htmlspecialchars($total_records); ?></span>
                            <span>(วันที่ <?php echo htmlspecialchars(date('d/m/Y')); ?>)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center">
                <form action="" method="GET" id="searchForm" class="w-full text-end">
                    <input type="hidden" name="page" value="stock_list">
                    <input type="hidden" name="page_num" value="<?php echo $page; ?>">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput" placeholder="ค้นหาการเช็คสต๊อก..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-full md:w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            autofocus>
                        <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div
                class="bg-white shadow-md rounded-xl p-4 space-y-4 border border-gray-200 md:flex md:gap-4 md:bg-transparent md:shadow-none md:border-none md:p-0 md:w-auto">
                <!-- Export Buttons -->
                <div class="grid grid-cols-2 gap-4 pt-2 md:grid md:grid-cols-2 md:gap-4">
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
        <div id="notification"
            class="fixed top-5 right-5 w-80 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden hidden">
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
                            <tr class="bg-gray-100">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold" hidden>
                                    รหัสรายการเช็คสต๊อก</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold" hidden>รหัสสต๊อก</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">คลังสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัสสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold" hidden>จำนวนจากระบบ
                                </th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">จำนวนที่นับได้</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">พนักงาน</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold" hidden>สถานะ</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">หมายเหตุ</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold" hidden>
                                    ข้อความเพิ่มเติม</th>
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
                                            <?php echo htmlspecialchars(date('Y-m-d', strtotime($stock['stock_date']))); ?>
                                        </td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['warehouse_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['product_id']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['product_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border" hidden>
                                            <?php echo htmlspecialchars($stock['stock_check_system']); ?>
                                        </td>
                                        <td class="px-6 py-2 border">
                                            <?php echo htmlspecialchars($stock['stock_check_actual']); ?>
                                        </td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($stock['personnel_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border" hidden>
                                            <?php echo htmlspecialchars($stock['check_status_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border">
                                            <?php echo htmlspecialchars($stock['stock_check_remark_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border" hidden>
                                            <?php echo htmlspecialchars($stock['stock_check_comment']); ?>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-gray-600">ไม่มีข้อมูลการเช็คสต๊อกในวันนี้
                                        (<?php echo htmlspecialchars(date('d/m/Y')); ?>)</td>
                                </tr>

                            <?php endif; ?>
                        </tbody>

                    </table>
                    <div class="w-full text-xl flex justify-end items-center p-2 py-2">
                        <tr>
                            <div class="text-blue-600 ">
                                <span class="">จำนวนรายการสินค้าที่ตรวจสอบ:</span>
                                <td class="px-6 py-2 border"><?php echo $total_records ?></td>
                            </div>
                        </tr>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=stock_list&page_num=<?= $page - 1 ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
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
                    <a href="?page=stock_list&page_num=<?= $i ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=stock_list&page_num=<?= $total_pages ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=stock_list&page_num=<?= $page + 1 ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>

        function exportPDF() {
            const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
            let url = '/page/stock/report/export_stock_pdf.php?csrf_token=' + encodeURIComponent(csrfToken) + '&selected_date=' + encodeURIComponent('<?php echo $selected_date; ?>');
            window.open(url, '_blank');
        }

        function exportExcel() {
            const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
            let url = '/page/stock/report/export_stock_excel.php?csrf_token=' + encodeURIComponent(csrfToken) + '&selected_date=' + encodeURIComponent('<?php echo $selected_date; ?>');
            window.open(url, '_blank');
        }
    </script>
</body>

</html>