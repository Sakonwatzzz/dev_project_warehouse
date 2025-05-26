<?php
require_once __DIR__ . '../../../../config/database_server.php';
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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'lot_list'] + ($_GET ?? []));
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
function validateLotData($lot_name, $lot_detail, $lot_status_id, $warehouse_id, $supplier_id, $pdo, $exclude_id = null)
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
    if (!$supplier_id) {
        $errors[] = 'กรุณาเลือกเจ้าหน้าที่';
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

// Handle search and date parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Handle POST requests for adding lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=lot_list&error=Invalid CSRF token');
        exit;
    }

    $lot_name = trim($_POST['lot_name']);
    $lot_detail = trim($_POST['lot_detail']);
    $lot_status_id = filter_var($_POST['lot_status_id'], FILTER_VALIDATE_INT);
    $warehouse_id = filter_var($_POST['warehouse_id'], FILTER_VALIDATE_INT);
    $supplier_id = filter_var($_POST['supplier_id'], FILTER_VALIDATE_INT);

    $errors = validateLotData($lot_name, $lot_detail, $lot_status_id, $warehouse_id, $supplier_id, $pdo);

    if (!empty($errors)) {
        header('Location: index.php?page=lot_list&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO lot (lot_name, lot_detail, lot_status_id, warehouse_id, supplier_id, lot_date, warehouse_inout) VALUES (?, ?, ?, ?, ?, NOW(), 1)");
        $stmt->execute([$lot_name, $lot_detail, $lot_status_id, $warehouse_id, $supplier_id]);
        $pdo->commit();
        header('Location: index.php?page=lot_list&success=เพิ่มล็อตสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add lot error: " . $e->getMessage());
        header('Location: index.php?page=lot_list&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle POST requests for updating lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=lot_list&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $lot_name = trim($_POST['lot_name']);
    $lot_detail = trim($_POST['lot_detail']);
    $lot_status_id = filter_var($_POST['lot_status_id'], FILTER_VALIDATE_INT);
    $warehouse_id = filter_var($_POST['warehouse_id'], FILTER_VALIDATE_INT);
    $supplier_id = filter_var($_POST['supplier_id'], FILTER_VALIDATE_INT);

    $errors = validateLotData($lot_name, $lot_detail, $lot_status_id, $warehouse_id, $supplier_id, $pdo, $id);
    if (!$id) {
        $errors[] = 'รหัสล็อตไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=lot_list&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE lot SET lot_name = ?, lot_detail = ?, lot_status_id = ?, warehouse_id = ?, supplier_id = ? WHERE lot_id = ? AND warehouse_inout = 1");
        $stmt->execute([$lot_name, $lot_detail, $lot_status_id, $warehouse_id, $supplier_id, $id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=lot_list&error=ไม่พบข้อมูลล็อตที่ต้องการแก้ไข');
            exit;
        }
        $pdo->commit();
        header('Location: index.php?page=lot_list&success=แก้ไขล็อตสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update lot error: " . $e->getMessage());
        header('Location: index.php?page=lot_list&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=lot_list&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: index.php?page=lot_list&error=Invalid lot ID');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $dependencies = checkLotDependencies($pdo, $id);

        if (!empty($dependencies)) {
            $pdo->rollBack();
            $dependency_list = implode(', ', $dependencies);
            header('Location: index.php?page=lot_list&error=' . urlencode("ไม่สามารถลบล็อตได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM lot WHERE lot_id = ? AND warehouse_inout = 1");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=lot_list&error=ไม่พบข้อมูลล็อตที่ต้องการลบ');
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=lot_list&success=ลบล็อตสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete lot error: Lot ID=$id, Error=" . $e->getMessage());
        $error_message = strpos($e->getMessage(), '1451') !== false ? 'ไม่สามารถลบล็อตได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ' : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
        header("Location: index.php?page=lot_list&error=" . urlencode($error_message));
        exit;
    }
}

// Fetch dropdown data
try {
    $status_lot = fetchData($pdo, "SELECT lot_status_id, lot_status_name FROM lot_status")->fetchAll(PDO::FETCH_ASSOC);
    $warehouse_lot = fetchData($pdo, "SELECT warehouse_id, warehouse_name FROM warehouse")->fetchAll(PDO::FETCH_ASSOC);
    $supplier_lot = fetchData($pdo, "SELECT supplier_id, supplier_name FROM supplier")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=lot_list&error=' . urlencode($e->getMessage()));
    exit;
}

// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total_count FROM lot WHERE warehouse_inout = 1";
    $params = [];
    if (!empty($search)) {
        $count_query .= " AND (lot_id LIKE :search OR lot_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date || $end_date) {
        $count_query .= (!empty($search) ? " AND" : " WHERE") . " 1=1";
        if ($start_date) {
            $count_query .= " AND lot_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $count_query .= " AND lot_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count lot error: " . $e->getMessage());
    header('Location: index.php?page=lot_list&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch lot data
try {
    $query = "
        SELECT
            lot.lot_id,
            lot.lot_name,
            lot.lot_detail,
            lot.lot_date,
            lot.lot_status_id,
            lot.warehouse_id,
            lot.supplier_id,
            lot_status.lot_status_name,
            warehouse.warehouse_name,
            supplier.supplier_name
        FROM lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        WHERE lot.warehouse_inout = 1";
    $params = [];
    if (!empty($search) || $start_date || $end_date) {
        $query .= " AND 1=1";
        if (!empty($search)) {
            $query .= " AND (lot.lot_id LIKE :search OR lot.lot_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $query .= " AND lot.lot_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND lot.lot_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }
    $query .= " ORDER BY lot.lot_date DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $result_lot_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
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
                <button "
                    class=" bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                    <a href="/?page=lot_listinsert"
                        class="">
                        <i class="fas fa-plus mr-2"></i>เพิ่มล็อตสินค้า
                    </a>
                </button>
            </div>
            <div class="flex items-center">
                <form action="" method="GET" id="searchForm" class="w-full text-end">
                    <input type="hidden" name="page" value="lot_list">
                    <input type="hidden" name="page_num" value="1">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหาล็อตสินค้า..."
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
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">เจ้าหน้าที่</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่และเวลา</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">สถานะ</th>
                                <th class="px-6 py-3 border text-center text-gray-700 font-semibold">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($result_lot_list) > 0): ?>
                                <?php foreach ($result_lot_list as $lot): ?>
                                    <tr class="hover:bg-gray-100">
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
                                            onclick="window.location.href='?page=supplier_lot&supplier_id=<?php echo htmlspecialchars($lot['supplier_id']); ?>'">
                                            <?php echo htmlspecialchars($lot['supplier_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border">
                                            <a href="?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                                class="text-blue-600 hover:underline cursor-pointer block w-full h-full">
                                                <?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($lot['lot_date']))); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-2 border cursor-pointer hover:underline text-green-600"
                                            onclick="window.location.href='?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>'">
                                            <?php echo htmlspecialchars($lot['lot_status_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border text-center">
                                            <div class="flex flex-wrap justify-center gap-2">
                                                <?php if ($lot['lot_status_id'] == 2): ?>
                                                    <a href="?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                                        class="bg-blue-500 text-white text-sm px-3 py-1 rounded hover:bg-blue-600 transition flex items-center whitespace-nowrap">
                                                        <i class="fas fa-eye mr-1"></i> ดูรายละเอียด
                                                    </a>
                                                <?php else: ?>
                                                    <button
                                                        class="bg-yellow-500 text-white text-sm px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center whitespace-nowrap">
                                                        <a href="?page=lot_listdetail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
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
                    <a href="?page=lot_list&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=lot_list&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=lot_list&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=lot_list&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_list&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">เพิ่มล็อตสินค้าใหม่</h3>
                <form id="addForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อล็อต</label>
                        <input type="text" id="addLotName" name="lot_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="addNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">รายละเอียด</label>
                        <textarea id="addLotDetail" name="lot_detail"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        <span id="addDetailError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">สถานะล็อต</label>
                        <select id="addLotStatusId" name="lot_status_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกสถานะล็อต</option>
                            <?php foreach ($status_lot as $status): ?>
                                <option value="<?php echo htmlspecialchars($status['lot_status_id']); ?>">
                                    <?php echo htmlspecialchars($status['lot_status_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="addStatusError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">คลังปลายทาง</label>
                        <select id="addWarehouseId" name="warehouse_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกคลัง</option>
                            <?php foreach ($warehouse_lot as $warehouse): ?>
                                <option value="<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>">
                                    <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="addWarehouseError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เจ้าหน้าที่</label>
                        <select id="addSupplierId" name="supplier_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกเจ้าหน้าที่</option>
                            <?php foreach ($supplier_lot as $supplier): ?>
                                <option value="<?php echo htmlspecialchars($supplier['supplier_id']); ?>">
                                    <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="addSupplierError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeAddModal()"
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

        <!-- Edit Modal -->
        <div id="editModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 invisible opacity-0 transition-all duration-300">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">แก้ไขข้อมูลล็อตสินค้า</h3>
                <form id="editForm" method="POST" class="space-y-4">
                    <input type="hidden" id="editLotId" name="id">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อล็อต</label>
                        <input type="text" id="editLotName" name="lot_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="editNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">รายละเอียด</label>
                        <textarea id="editLotDetail" name="lot_detail"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        <span id="editDetailError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">สถานะล็อต</label>
                        <select id="editLotStatusId" name="lot_status_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกสถานะล็อต</option>
                            <?php foreach ($status_lot as $status): ?>
                                <option value="<?php echo htmlspecialchars($status['lot_status_id']); ?>">
                                    <?php echo htmlspecialchars($status['lot_status_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="editStatusError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">คลังปลายทาง</label>
                        <select id="editWarehouseId" name="warehouse_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกคลัง</option>
                            <?php foreach ($warehouse_lot as $warehouse): ?>
                                <option value="<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>">
                                    <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="editWarehouseError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เจ้าหน้าที่</label>
                        <select id="editSupplierId" name="supplier_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกเจ้าหน้าที่</option>
                            <?php foreach ($supplier_lot as $supplier): ?>
                                <option value="<?php echo htmlspecialchars($supplier['supplier_id']); ?>">
                                    <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="editSupplierError" class="text-red-500 text-xs mt-1 hidden"></span>
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
                <p class="text-gray-600 mb-6">คุณแน่ใจหรือไม่ว่าต้องการลบล็อตนี้?</p>
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
                urlParams.set('page', 'lot_list');
                urlParams.delete('search');
                window.location.search = urlParams.toString();
            }
        }

        function showAddModal() {
            document.getElementById('addLotName').value = '';
            document.getElementById('addLotDetail').value = '';
            document.getElementById('addLotStatusId').value = '';
            document.getElementById('addWarehouseId').value = '';
            document.getElementById('addSupplierId').value = '';
            document.getElementById('addNameError').classList.add('hidden');
            document.getElementById('addDetailError').classList.add('hidden');
            document.getElementById('addStatusError').classList.add('hidden');
            document.getElementById('addWarehouseError').classList.add('hidden');
            document.getElementById('addSupplierError').classList.add('hidden');
            document.getElementById('addModal').classList.remove('invisible', 'opacity-0');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('invisible', 'opacity-0');
        }

        function showEditModal(id, name, detail, statusId, warehouseId, supplierId) {
            document.getElementById('editLotId').value = id;
            document.getElementById('editLotName').value = name;
            document.getElementById('editLotDetail').value = detail;
            document.getElementById('editLotStatusId').value = statusId;
            document.getElementById('editWarehouseId').value = warehouseId;
            document.getElementById('editSupplierId').value = supplierId;
            document.getElementById('editNameError').classList.add('hidden');
            document.getElementById('editDetailError').classList.add('hidden');
            document.getElementById('editStatusError').classList.add('hidden');
            document.getElementById('editWarehouseError').classList.add('hidden');
            document.getElementById('editSupplierError').classList.add('hidden');
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
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/export_lot_list_pdf.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/export_lot_list_excel.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
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
                if (this.value.trim() === '') {
                    this.form.submit();
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

            document.getElementById('addForm').addEventListener('submit', function(e) {
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
                        id: 'addSupplierId',
                        errorId: 'addSupplierError',
                        label: 'เจ้าหน้าที่',
                        maxLength: Infinity,
                        required: true
                    }
                ];
                if (validateForm('addForm', fields)) {
                    this.submit();
                }
            });

            document.getElementById('editForm').addEventListener('submit', function(e) {
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
                        id: 'editSupplierId',
                        errorId: 'editSupplierError',
                        label: 'เจ้าหน้า�ที่',
                        maxLength: Infinity,
                        required: true
                    }
                ];
                if (validateForm('editForm', fields)) {
                    this.submit();
                }
            });

            document.getElementById('start_date').addEventListener('change', updateDateFilter);
            document.getElementById('end_date').addEventListener('change', updateDateFilter);

            function updateDateFilter() {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', 'lot_list');

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

                window.location.search = urlParams.toString();
            }
        });
    </script>
</body>

</html>