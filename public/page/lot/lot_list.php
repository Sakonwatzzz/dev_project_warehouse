<?php
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

$pdo = Database::getInstance()->getConnection(); 

// Session Management
if (!isset($_SESSION)) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'lot_list'] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Database Functions
function fetchData($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        throw new Exception("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage()));
    }
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validateLotData($lot_name, $lot_detail, $lot_status_id, $warehouse_id, $supplier_id, $pdo, $exclude_id = null) {
    $errors = [];
    if (empty($lot_name) || strlen($lot_name) > 255) {
        $errors[] = 'ชื่อล็อตต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (strlen($lot_detail) > 1000) {
        $errors[] = 'รายละเอียดล็อตต้องมีความยาวไม่เกิน 1000 ตัวอักษร';
    }
    if (!$lot_status_id) {
        $errors[] = 'กรุณาเลือกสถานะล็อต';
    }
    if (!$warehouse_id) {
        $errors[] = 'กรุณาเลือกคลังปลายทาง';
    }
    if (!$supplier_id) {
        $errors[] = 'กรุณาเลือกผู้ส่งมอบ';
    }
    if ($exclude_id) {
        $query = "SELECT COUNT(*) FROM lot WHERE lot_name = :lot_name AND lot_id != :exclude_id AND warehouse_inout = 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':lot_name', $lot_name);
        $stmt->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'ชื่อล็อตนี้มีอยู่ในระบบแล้ว';
        }
    } else {
        $query = "SELECT COUNT(*) FROM lot WHERE lot_name = :lot_name AND warehouse_inout = 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':lot_name', $lot_name);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'ชื่อล็อตนี้มีอยู่ในระบบแล้ว';
        }
    }
    return $errors;
}

function checkLotDependencies($pdo, $lot_id) {
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lot_product WHERE lot_id = ?");
    $stmt->execute([$lot_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "สินค้าในล็อต ({$count} รายการ)";
    }
    return $dependencies;
}

// Handle Requests
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

try {
    // Validate dates
    $startDateTime = $start_date ? DateTime::createFromFormat('Y-m-d', $start_date) : false;
    $endDateTime = $end_date ? DateTime::createFromFormat('Y-m-d', $end_date) : false;
    if ($start_date && !$startDateTime) {
        throw new Exception("รูปแบบวันที่เริ่มต้นไม่ถูกต้อง");
    }
    if ($end_date && !$endDateTime) {
        throw new Exception("รูปแบบวันที่สิ้นสุดไม่ถูกต้อง");
    }
    if ($startDateTime && $endDateTime && $startDateTime > $endDateTime) {
        throw new Exception("วันที่เริ่มต้นต้องไม่มากกว่าวันที่สิ้นสุด");
    }

    // Fetch Dropdown Data
    $status_lot = fetchData($pdo, "SELECT lot_status_id, lot_status_name FROM lot_status")->fetchAll(PDO::FETCH_ASSOC);
    $warehouse_lot = fetchData($pdo, "SELECT warehouse_id, warehouse_name FROM warehouse")->fetchAll(PDO::FETCH_ASSOC);
    $supplier_lot = fetchData($pdo, "SELECT supplier_id, supplier_name FROM supplier")->fetchAll(PDO::FETCH_ASSOC);

    // Count Total Records
    $count_query = "SELECT COUNT(*) as total_count FROM lot WHERE warehouse_inout = 1";
    $params = [];
    if (!empty($search)) {
        $count_query .= " AND (lot_id LIKE :search OR lot_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date) {
        $count_query .= " AND lot_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $count_query .= " AND lot_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    header('Location: index.php?page=lot_list&error=' . urlencode($e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการล็อตสินค้า</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link href="../output.css" rel="stylesheet">
    <link href="../style/page_list.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-white min-h-screen font-sans text-base">
    <div class="container mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span id="totalRecords" class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fas fa-list mr-2"></i> <?php echo $total_records; ?> รายการ
                </span>
            </div>
            <form id="searchForm" action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="lot_list">
                <input type="hidden" name="page_num" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหาล็อตสินค้า..." value="<?php echo htmlspecialchars($search); ?>"
                        class="w-full md:w-80 px-4 py-2 border border-gray-300 rounded-lg pl-12 pr-12 bg-white/70 backdrop-blur-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition-all duration-200">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-base md:text-lg"></i>
                    <button type="button" id="clearSearch" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-red-500 transition <?php echo $search ? '' : 'hidden'; ?>">
                        <i class="fas fa-times-circle text-base md:text-lg"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Date Filter and Export Buttons -->
        <div class="glass-effect rounded-xl p-4 mb-6 card-shadow">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex flex-col sm:flex-row p-3 gap-3 w-full sm:w-auto">
                    <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-blue-500"></i>กรองตามวันที่
                    </h3>
                    <div class="flex flex-col xs:flex-row gap-2">
                        <input type="date" id="start_date" name="start_date"
                            class="border border-blue-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white/80 standard-input-mobile"
                            value="<?php echo htmlspecialchars($start_date); ?>">
                        <input type="date" id="end_date" name="end_date"
                            class="border border-blue-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white/80 standard-input-mobile"
                            value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                </div>
                <div class="flex flex-col xs:flex-row gap-2">
                    <button onclick="exportPDF()" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition hover-lift flex items-center gap-2 standard-button-mobile">
                        <i class="fas fa-file-pdf"></i><span>PDF</span>
                    </button>
                    <button onclick="exportExcel()" class="bg-emerald-500 text-white py-2 px-4 rounded-lg hover:bg-emerald-600 transition hover-lift flex items-center gap-2 standard-button-mobile">
                        <i class="fas fa-file-excel"></i><span>Excel</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div id="notification" class="fixed top-4 right-4 max-w-sm w-full bg-white rounded-lg shadow-lg border-l-4 transform translate-x-full transition-transform duration-300 z-50 flex items-center">
            <div id="notification-header" class="flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg">
                <i id="notificationIcon" class="fas fa-check-circle text-2xl"></i>
            </div>
            <div class="flex-1 ml-4 pr-4 py-4">
                <p id="notificationTitle" class="text-sm font-medium text-gray-900"></p>
                <p id="notificationMessage" class="mt-1 text-sm text-gray-500"></p>
            </div>
            <button onclick="hideNotification()" class="mr-4 text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Table -->
        <div id="lotTable" class="glass-effect rounded-xl p-6 card-shadow animate-fade-in">
            <!-- ตารางจะถูกโหลดผ่าน AJAX -->
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal hidden">
            <div class="modal-content">
                <h3 class="text-xl font-bold text-gray-800 mb-4">ยืนยันการลบ</h3>
                <p class="text-gray-600 mb-6">คุณแน่ใจหรือไม่ว่าต้องการลบล็อตนี้?</p>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="confirmDelete(<?php echo (int)$lot['lot_id']; ?>)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        ยกเลิก
                    </button>
                    <button type="button" id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        ลบ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../page/lot/function/lot_list.js"></script>
</body>
</html>