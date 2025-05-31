<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();
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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'lot_warehouseinout'] + ($_GET ?? []));
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

// Validate lot data
function validateLotData($lot_name, $lot_detail, $lot_status_id, $warehouse_id, $personnel_id, $pdo, $exclude_id = null)
{
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
    if (!$personnel_id) {
        $errors[] = 'กรุณาเลือกพนักงาน';
    }
    return $errors;
}

// Check lot dependencies
function checkLotDependencies($pdo, $lot_id)
{
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lot_product WHERE lot_id = ?");
    $stmt->execute([$lot_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "สินค้าในล็อต ({$count} รายการ)";
    }
    return $dependencies;
}

// Handle POST requests for adding lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=lot_warehouseinout&error=Invalid CSRF token');
        exit;
    }

    $lot_name = trim(filter_var($_POST['lot_name']));
    $lot_detail = trim(filter_var($_POST['lot_detail']));
    $lot_status_id = filter_var($_POST['lot_status_id'], FILTER_VALIDATE_INT);
    $warehouse_id = filter_var($_POST['warehouse_id'], FILTER_VALIDATE_INT);
    $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);

    $errors = validateLotData($lot_name, $lot_detail, $lot_status_id, $warehouse_id, $personnel_id, $pdo);

    if (!empty($errors)) {
        header('Location: index.php?page=lot_warehouseinout&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO lot (lot_name, lot_detail, lot_status_id, warehouse_id, personnel_id, lot_date, warehouse_inout) VALUES (?, ?, ?, ?, ?, NOW(), 2)");
        $stmt->execute([$lot_name, $lot_detail, $lot_status_id, $warehouse_id, $personnel_id]);
        $pdo->commit();
        header('Location: index.php?page=lot_warehouseinout&success=เพิ่มล็อตสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add lot error: " . $e->getMessage());
        header('Location: index.php?page=lot_warehouseinout&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle POST requests for updating lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=lot_warehouseinout&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $lot_name = trim(filter_var($_POST['lot_name']));
    $lot_detail = trim(filter_var($_POST['lot_detail']));
    $lot_status_id = filter_var($_POST['lot_status_id'], FILTER_VALIDATE_INT);
    $warehouse_id = filter_var($_POST['warehouse_id'], FILTER_VALIDATE_INT);
    $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);

    $errors = validateLotData($lot_name, $lot_detail, $lot_status_id, $warehouse_id, $personnel_id, $pdo, $id);
    if (!$id) {
        $errors[] = 'รหัสล็อตไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=lot_warehouseinout&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE lot SET lot_name = ?, lot_detail = ?, lot_status_id = ?, warehouse_id = ?, personnel_id = ? WHERE lot_id = ? AND warehouse_inout = 2");
        $stmt->execute([$lot_name, $lot_detail, $lot_status_id, $warehouse_id, $personnel_id, $id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=lot_warehouseinout&error=ไม่พบข้อมูลล็อตที่ต้องการแก้ไข');
            exit;
        }
        $pdo->commit();
        header('Location: index.php?page=lot_warehouseinout&success=แก้ไขล็อตสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update lot error: " . $e->getMessage());
        header('Location: index.php?page=lot_warehouseinout&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=lot_warehouseinout&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: index.php?page=lot_warehouseinout&error=Invalid lot ID');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $dependencies = checkLotDependencies($pdo, $id);

        if (!empty($dependencies)) {
            $pdo->rollBack();
            $dependency_list = implode(', ', $dependencies);
            header('Location: index.php?page=lot_warehouseinout&error=' . urlencode("ไม่สามารถลบล็อตได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM lot WHERE lot_id = ? AND warehouse_inout = 2");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=lot_warehouseinout&error=ไม่พบข้อมูลล็อตที่ต้องการลบ');
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=lot_warehouseinout&success=ลบล็อตสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete lot error: Lot ID=$id, Error=" . $e->getMessage());
        $error_message = strpos($e->getMessage(), '1451') !== false ? 'ไม่สามารถลบล็อตได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ' : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
        header("Location: index.php?page=lot_warehouseinout&error=" . urlencode($error_message));
        exit;
    }
}

// Fetch dropdown data
try {
    $status_lot = fetchData($pdo, "SELECT lot_status_id, lot_status_name FROM lot_status")->fetchAll(PDO::FETCH_ASSOC);
    $warehouse_lot = fetchData($pdo, "SELECT warehouse_id, warehouse_name FROM warehouse")->fetchAll(PDO::FETCH_ASSOC);
    $personnel_lot = fetchData($pdo, "SELECT personnel_id, personnel_name FROM personnel")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=lot_warehouseinout&error=' . urlencode($e->getMessage()));
    exit;
}

// Add search and date filter variables with improved validation and sanitization
$search = '';
if (isset($_GET['search'])) {
    $search = trim(strip_tags($_GET['search']));
    // Limit search length to prevent abuse
    if (strlen($search) > 255) {
        $search = substr($search, 0, 255);
    }
}

$start_date = '';
if (isset($_GET['start_date'])) {
    $start_date = trim(strip_tags($_GET['start_date']));
    // Validate date format (YYYY-MM-DD)
    if (!empty($start_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $start_date = '';
    }
}

$end_date = '';
if (isset($_GET['end_date'])) {
    $end_date = trim(strip_tags($_GET['end_date']));
    // Validate date format (YYYY-MM-DD)
    if (!empty($end_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $end_date = '';
    }
}

// Ensure end_date is not before start_date
if (!empty($start_date) && !empty($end_date)) {
    if (strtotime($end_date) < strtotime($start_date)) {
        $start_date = '';
        $end_date = '';
        header('Location: index.php?page=lot_warehouseinout&error=' . urlencode('วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น'));
        exit;
    }
}

// Pagination setup
try {
    $records_per_page = 5;
    $page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
    $offset = ($page - 1) * $records_per_page;

    // Get total count with search and date filters
    $count_query = "SELECT COUNT(*) as total_count FROM lot WHERE lot.warehouse_inout = 2";
    $params = [];
    if ($start_date || $end_date || !empty($search)) {
        $count_query .= " AND 1=1";
        if ($start_date) {
            $count_query .= " AND lot.lot_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $count_query .= " AND lot.lot_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        if (!empty($search)) {
            $count_query .= " AND (lot.lot_id LIKE :search OR lot.lot_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
    $total_pages = max(1, ceil($total_records / $records_per_page));
} catch (PDOException $e) {
    error_log("Count lot error: " . $e->getMessage());
    header('Location: index.php?page=lot_warehouseinout&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Fetch paginated data with search and date filters
try {
    $query = "
    SELECT
        lot_status.lot_status_id, 
        lot_status.lot_status_name, 
        lot.personnel_id, 
        personnel.personnel_name, 
        warehouse.warehouse_name, 
        warehouse.warehouse_id,
        lot.lot_id, 
        lot.lot_name, 
        lot.lot_detail, 
        lot.lot_date, 
        lot.lot_status_id
    FROM
        lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN personnel ON lot.personnel_id = personnel.personnel_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
    WHERE lot.warehouse_inout = 2";

    $params = [];
    if ($start_date || $end_date || !empty($search)) {
        $query .= " AND 1=1";
        if ($start_date) {
            $query .= " AND lot.lot_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND lot.lot_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        if (!empty($search)) {
            $query .= " AND (lot.lot_id LIKE :search OR lot.lot_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
    }

    $query .= " ORDER BY lot.lot_date DESC LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $result_lot_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Location: index.php?page=lot_warehouseinout&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการล็อตสินค้า - การย้ายสินค้า</title>
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .zoom-in {
            cursor: zoom-in;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-6">
            <div class="flex flex-col gap-2 md:flex-row md:space-x-2 md:items-center">
                <div class="flex space-x-2">
                    <div class="w-auto px-4 py-2 bg-blue-200 border rounded font-bold text-blue-800">
                        <i class="fas fa-boxes mr-2"></i>
                        <?php echo $total_records; ?>
                        <span>รายการ</span>
                    </div>
                </div>
                <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                    <a href="/?page=lot_insertout">
                        <i class="fas fa-plus mr-2"></i>เพิ่มล็อตสินค้า
                    </a>
                </button>
            </div>
            <div class="flex items-center">
                <form action="" method="GET" id="searchForm" class="w-full text-end">
                    <input type="hidden" name="page" value="lot_warehouseinout">
                    <input type="hidden" name="page_num" value="1">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหารายการย้ายสินค้า..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-full md:w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="bg-white shadow-md rounded-xl p-4 space-y-4 border border-gray-200 md:flex md:gap-4 md:bg-transparent md:shadow-none md:border-none md:p-0 md:w-auto">
                <!-- Mobile Date Filter -->
                <div class="grid grid-cols-2 gap-4 md:w-[100%]">
                    <div>
                        <label for="start_date" class="text-sm font-medium text-gray-700">เริ่มจาก</label>
                        <input type="date" id="start_date" name="start_date"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition"
                            value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div>
                        <label for="end_date" class="text-sm font-medium text-gray-700">ถึงวันที่</label>
                        <input type="date" id="end_date" name="end_date"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition"
                            value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                </div>

                <!-- Export Buttons -->
                <div class="grid grid-cols-2 gap-4 pt-2 md:w-[100%] md:grid md:grid-cols-2 md:gap-4">
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
            <div class="bg-gradient-to-b from-[#d7e1ec] to-[#e6effb] p-4 border-b border-[#cfd5dc]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-list mr-2"></i> รายการล็อตสินค้า
                    </h2>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัสล็อต</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อล็อต</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">คลังปลายทาง</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">พนักงาน</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่และเวลา</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">สถานะ</th>
                                <th class="px-6 py-3 border text-center text-gray-700 font-semibold">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($result_lot_list) > 0): ?>
                                <?php foreach ($result_lot_list as $lot): ?>
                                    <tr class="hover:bg-gray-100 ">
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($lot['lot_id']); ?></td>
                                        <td class="px-6 py-2 border">
                                            <a href="?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                                class="text-blue-600 hover:underline cursor-pointer">
                                                <?php echo htmlspecialchars($lot['lot_name']); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-2 border cursor-pointer hover:underline"
                                            onclick="window.location.href='?page=warehouse_lot&warehouse_id=<?php echo htmlspecialchars($lot['warehouse_id']); ?>'">
                                            <?php echo htmlspecialchars($lot['warehouse_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border cursor-pointer hover:underline"
                                            onclick="window.location.href='?page=personnel_lot&personnel_id=<?php echo htmlspecialchars($lot['personnel_id']); ?>'">
                                            <?php echo htmlspecialchars($lot['personnel_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border">
                                            <a href="?page=lot_productlistmove&lot_id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                                class="text-blue-600 hover:underline cursor-pointer block w-full h-full">
                                                <?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($lot['lot_date']))); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-2  border cursor-pointer hover:underline text-green-600"
                                            onclick="window.location.href='?page=lot_detailmove&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>'">
                                            <?php echo htmlspecialchars($lot['lot_status_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border text-center">
                                            <div class="flex flex-wrap justify-center gap-2">
                                                <?php if ($lot['lot_status_id'] == 2): ?>
                                                    <a href="?page=lot_productlistmove&lot_id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                                        class="bg-blue-500 text-white text-sm px-3 py-1 rounded hover:bg-blue-600 transition flex items-center whitespace-nowrap">
                                                        <i class="fas fa-eye mr-1"></i> ดูรายละเอียด
                                                    </a>
                                                <?php else: ?>
                                                    <button
                                                        class="bg-yellow-500 text-white text-sm px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center whitespace-nowrap">
                                                        <a href="?page=lot_insertdetailout&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                                            class="">
                                                            <i class="fas fa-edit mr-1"></i> แก้ไข
                                                        </a>
                                                    </button>
                                                    <form action="" method="POST" style="display:inline;" class="delete-form">
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($lot['lot_id']); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <button type="button" onclick="confirmDelete(this.parentElement)"
                                                            class="bg-red-500 text-white text-sm px-3 py-1 rounded hover:bg-red-600 transition flex items-center whitespace-nowrap">
                                                            <i class="fas fa-trash-alt mr-1"></i> ลบ
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-gray-600">ไม่มีรายการล็อตสินค้า</td>
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
                    <a href="?page=lot_warehouseinout&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=lot_warehouseinout&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=lot_warehouseinout&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=lot_warehouseinout&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_warehouseinout&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 invisible opacity-0 transition-all duration-300">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">ยืนยันการลบ</h3>
                <p class="text-gray-600 mb-6">คุณแน่ใจหรือไม่ว่าต้องการลบการย้ายสินค้านี้?</p>
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
        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', 'lot_warehouseinout');
                urlParams.delete('search');
                window.location.search = urlParams.toString();
            }
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
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/export_lot_move_pdf.php';
            const params = [];
            if (startDate) params.push(`start_date=${encodeURIComponent(startDate)}`);
            if (endDate) params.push(`end_date=${encodeURIComponent(endDate)}`);
            if (params.length) url += `?${params.join('&')}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/export_lot_move_excel.php';
            const params = [];
            if (startDate) params.push(`start_date=${encodeURIComponent(startDate)}`);
            if (endDate) params.push(`end_date=${encodeURIComponent(endDate)}`);
            if (params.length) url += `?${params.join('&')}`;
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
                    checkEmptySearch(this);
                }
                if (e.key === 'Enter') {
                    localStorage.setItem('searchPreserve', this.value);
                    this.form.submit();
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
                if (this.value.trim() === '') {
                    checkEmptySearch(this);
                }
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
                    if (!input.value.trim() && field.required) {
                        error.textContent = `กรุณากรอก${field.label}`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (input.value.length > field.maxLength) {
                        error.textContent = `${field.label}ยาวเกิน ${field.maxLength} ตัวอักษร`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else {
                        error.classList.add('hidden');
                    }
                });
                return !hasError;
            }

            const addForm = document.getElementById('addForm');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const fields = [{
                            id: 'addLotName',
                            errorId: 'addNameError',
                            label: 'ชื่อล็อต',
                            maxLength: 255,
                            required: true
                        },
                        {
                            id: 'addLotDetail',
                            errorId: 'addDetailError',
                            label: 'รายละเอียด',
                            maxLength: 1000,
                            required: false
                        },
                        {
                            id: 'addLotStatusId',
                            errorId: 'addStatusError',
                            label: 'สถานะล็อต',
                            maxLength: Infinity,
                            required: true
                        },
                        {
                            id: 'addWarehouseId',
                            errorId: 'addWarehouseError',
                            label: 'คลังปลายทาง',
                            maxLength: Infinity,
                            required: true
                        },
                        {
                            id: 'addPersonnelId',
                            errorId: 'addPersonnelError',
                            label: 'พนักงาน',
                            maxLength: Infinity,
                            required: true
                        }
                    ];
                    if (validateForm('addForm', fields)) {
                        this.submit();
                    }
                });
            }

            const editForm = document.getElementById('editForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const fields = [{
                            id: 'editLotName',
                            errorId: 'editNameError',
                            label: 'ชื่อล็อต',
                            maxLength: 255,
                            required: true
                        },
                        {
                            id: 'editLotDetail',
                            errorId: 'editDetailError',
                            label: 'รายละเอียด',
                            maxLength: 1000,
                            required: false
                        },
                        {
                            id: 'editLotStatusId',
                            errorId: 'editStatusError',
                            label: 'สถานะล็อต',
                            maxLength: Infinity,
                            required: true
                        },
                        {
                            id: 'editWarehouseId',
                            errorId: 'editWarehouseError',
                            label: 'คลังปลายทาง',
                            maxLength: Infinity,
                            required: true
                        },
                        {
                            id: 'editPersonnelId',
                            errorId: 'editPersonnelError',
                            label: 'เจ้าหน้าที่',
                            maxLength: Infinity,
                            required: true
                        }
                    ];
                    if (validateForm('editForm', fields)) {
                        this.submit();
                    }
                });
            }

            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');

            function updateDateFilter() {
                const startDate = startDateInput.value;
                const endDate = endDateInput.value;
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', 'lot_warehouseinout');

                if (startDate) {
                    urlParams.set('start_date', startDate);
                } else {
                    urlParams.delete('start_date');
                }

                if (endDate) {
                    urlParams.set('end_date', endDate);
                } else {
                    urlParams.delete('end_date');
                }

                // Client-side validation for date range
                if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
                    showNotification('วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น', 'bg-red-500');
                    return;
                }

                window.location.search = urlParams.toString();
            }

            startDateInput.addEventListener('change', updateDateFilter);
            endDateInput.addEventListener('change', updateDateFilter);

            // Add button click handler
            document.querySelector('button a[href="/?page=lot_insertout"]').parentElement.addEventListener('click', (e) => {
                e.preventDefault();
                showAddModal();
            });
        });
    </script>
</body>

</html>