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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'supplier_lot', 'supplier_id' => $_GET['supplier_id'] ?? 0]);
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
function validateLotData($lot_detail, $warehouse_id, $lot_status_id, $lot_date, $pdo, $exclude_id = null)
{
    $errors = [];
    if (empty($lot_detail) || strlen($lot_detail) > 500) {
        $errors[] = 'รายละเอียดล็อตต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร';
    }
    if (!$warehouse_id) {
        $errors[] = 'กรุณาเลือกคลังสินค้า';
    }
    if (!$lot_status_id) {
        $errors[] = 'กรุณาเลือกสถานะล็อต';
    }
    if (empty($lot_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lot_date)) {
        $errors[] = 'วันที่ล็อตต้องอยู่ในรูปแบบ YYYY-MM-DD';
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

// Get supplier ID from URL
$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
if (!$supplier_id) {
    header("Location: index.php?page=lot_list");
    exit;
}

// Fetch supplier information
try {
    $stmt = $pdo->prepare("SELECT supplier_id, supplier_name FROM supplier WHERE supplier_id = ?");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        header("Location: index.php?page=lot_list");
        exit;
    }
} catch (PDOException $e) {
    header("Location: index.php?page=lot_list&error=" . urlencode("Error fetching supplier: " . htmlspecialchars($e->getMessage())));
    exit;
}

// Handle search and date parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Handle POST requests for adding lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=Invalid CSRF token');
        exit;
    }

    $lot_detail = trim($_POST['lot_detail']);
    $warehouse_id = filter_var($_POST['warehouse_id'], FILTER_VALIDATE_INT);
    $lot_status_id = filter_var($_POST['lot_status_id'], FILTER_VALIDATE_INT);
    $lot_date = trim($_POST['lot_date']);

    $errors = validateLotData($lot_detail, $warehouse_id, $lot_status_id, $lot_date, $pdo);

    if (!empty($errors)) {
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO lot (lot_detail, warehouse_id, lot_status_id, lot_date, supplier_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$lot_detail, $warehouse_id, $lot_status_id, $lot_date, $supplier_id]);
        $pdo->commit();
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&success=เพิ่มล็อตสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add lot error: " . $e->getMessage());
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle POST requests for updating lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=Invalid CSRF token');
        exit;
    }

    $lot_id = filter_var($_POST['lot_id'], FILTER_VALIDATE_INT);
    $lot_detail = trim($_POST['lot_detail']);
    $warehouse_id = filter_var($_POST['warehouse_id'], FILTER_VALIDATE_INT);
    $lot_status_id = filter_var($_POST['lot_status_id'], FILTER_VALIDATE_INT);
    $lot_date = trim($_POST['lot_date']);

    $errors = validateLotData($lot_detail, $warehouse_id, $lot_status_id, $lot_date, $pdo, $lot_id);
    if (!$lot_id) {
        $errors[] = 'รหัสล็อตไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE lot SET lot_detail = ?, warehouse_id = ?, lot_status_id = ?, lot_date = ? WHERE lot_id = ? AND supplier_id = ?");
        $stmt->execute([$lot_detail, $warehouse_id, $lot_status_id, $lot_date, $lot_id, $supplier_id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=ไม่พบข้อมูลล็อตที่ต้องการแก้ไข');
            exit;
        }
        $pdo->commit();
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&success=แก้ไขล็อตสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update lot error: " . $e->getMessage());
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['lot_id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=Invalid CSRF token');
        exit;
    }

    $lot_id = filter_var($_POST['lot_id'], FILTER_VALIDATE_INT);
    if (!$lot_id) {
        header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=Invalid lot ID');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $dependencies = checkLotDependencies($pdo, $lot_id);

        if (!empty($dependencies)) {
            $pdo->rollBack();
            $dependency_list = implode(', ', $dependencies);
            header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=' . urlencode("ไม่สามารถลบล็อตได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM lot WHERE lot_id = ? AND supplier_id = ?");
        $stmt->execute([$lot_id, $supplier_id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=ไม่พบข้อมูลล็อตที่ต้องการลบ');
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=supplier_lot&supplier_id=" . $supplier_id . "&success=ลบล็อตสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete lot error: Lot ID=$lot_id, Error=" . $e->getMessage());
        $error_message = strpos($e->getMessage(), '1451') !== false
            ? 'ไม่สามารถลบล็อตได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
            : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
        header("Location: index.php?page=supplier_lot&supplier_id=" . $supplier_id . "&error=" . urlencode($error_message));
        exit;
    }
}

// Count total records for pagination
try {
    $count_query = "
        SELECT COUNT(*) as total_count
        FROM lot
        WHERE supplier_id = :supplier_id";
    $params = [':supplier_id' => $supplier_id];

    if (!empty($search)) {
        $count_query .= " AND (lot_id LIKE :search OR lot_detail LIKE :search)";
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
} catch (PDOException $e) {
    error_log("Count lot error: " . $e->getMessage());
    header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch warehouse and status options
try {
    $warehouse_query = "SELECT warehouse_id, warehouse_name FROM warehouse";
    $warehouses = fetchData($pdo, $warehouse_query)->fetchAll(PDO::FETCH_ASSOC);
    $status_query = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $lot_statuses = fetchData($pdo, $status_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=' . urlencode($e->getMessage()));
    exit;
}

// Fetch lot data
try {
    $query = "
        SELECT
            l.lot_id,
            l.lot_detail,
            l.lot_date,
            l.lot_status_id,
            l.warehouse_id,
            ls.lot_status_name,
            w.warehouse_name
        FROM lot l
        LEFT JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
        LEFT JOIN warehouse w ON l.warehouse_id = w.warehouse_id
        WHERE l.supplier_id = :supplier_id";
    $params = [':supplier_id' => $supplier_id];

    if (!empty($search)) {
        $query .= " AND (l.lot_id LIKE :search OR l.lot_detail LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date) {
        $query .= " AND l.lot_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $query .= " AND l.lot_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }

    $query .= " ORDER BY l.lot_date DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=supplier_lot&supplier_id=' . $supplier_id . '&error=' . urlencode($e->getMessage()));
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการล็อตสินค้าจาก: <?php echo htmlspecialchars($supplier['supplier_name']); ?></title>
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
            </div>
            <div class="flex items-center">
                <form action="" method="GET" id="searchForm" class="w-full text-end">
                    <input type="hidden" name="page" value="supplier_lot">
                    <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplier_id); ?>">
                    <input type="hidden" name="page_num" value="1">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหาล็อต..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-full md:w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="bg-white shadow-md rounded-xl p-4 space-y-4 border border-gray-200 md:flex md:gap-4 md:bg-transparent md:shadow-none md:border-none md:p-0 md:w-auto">
                <!-- Date Filter -->
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
                    <h2 class="text-[18px] flex items-center md:text-[22px]">
                        <div class="hidden md:flex items-center mr-2">
                            <i class="fas fa-list mr-2"></i>
                        </div>
                        รายการล็อตสินค้าจาก: <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                    </h2>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัสล็อต</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่และเวลา</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">คลังสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รายละเอียด</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">สถานะ</th>
                                <th class="px-6 py-3 border text-center text-gray-700 font-semibold">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lots) > 0): ?>
                                <?php foreach ($lots as $lot): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($lot['lot_id']); ?></td>
                                        <td class="px-6 py-2 border">
                                            <a href="?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                                class="text-blue-600 hover:underline cursor-pointer">
                                                <?php echo htmlspecialchars($lot['lot_date']); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-2 border zoom-in"
                                            onclick="window.location.href='?page=warehouse_lot&warehouse_id=<?php echo htmlspecialchars($lot['warehouse_id']); ?>'">
                                            <?php echo htmlspecialchars($lot['warehouse_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($lot['lot_detail']); ?></td>
                                        <td class="px-6 py-2 border"
                                            onclick="window.location.href='?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>'">
                                            <?php echo htmlspecialchars($lot['lot_status_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border text-center">
                                            <?php if ($lot['lot_status_id'] != 2): // Assuming 2 is 'ยืนยันแล้ว' 
                                            ?>
                                                <div class="flex flex-wrap justify-center gap-2">
                                                    <button onclick="showEditModal('<?php echo htmlspecialchars($lot['lot_id']); ?>', '<?php echo htmlspecialchars(addslashes($lot['lot_detail'])); ?>', '<?php echo htmlspecialchars($lot['warehouse_id']); ?>', '<?php echo htmlspecialchars($lot['lot_status_id']); ?>', '<?php echo htmlspecialchars($lot['lot_date']); ?>')"
                                                        class="bg-yellow-500 text-white text-sm px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center whitespace-nowrap">
                                                        <i class="fas fa-edit mr-1"></i> แก้ไข
                                                    </button>
                                                    <form action="" method="POST" style="display:inline;" class="delete-form">
                                                        <input type="hidden" name="lot_id" value="<?php echo htmlspecialchars($lot['lot_id']); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <button type="button" onclick="confirmDelete(this.parentElement)"
                                                            class="bg-red-500 text-white text-sm px-3 py-1 rounded hover:bg-red-600 transition flex items-center whitespace-nowrap">
                                                            <i class="fas fa-trash-alt mr-1"></i> ลบ
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-gray-600">ไม่มีข้อมูลล็อต</td>
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
                    <a href="?page=supplier_lot&supplier_id=<?= $supplier_id ?>&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=supplier_lot&supplier_id=<?= $supplier_id ?>&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=supplier_lot&supplier_id=<?= $supplier_id ?>&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=supplier_lot&supplier_id=<?= $supplier_id ?>&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=supplier_lot&supplier_id=<?= $supplier_id ?>&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Navigation Links -->
        <div class="flex space-x-2 mt-5 justify-end">
            <div class="detail-action">
                <a href="/?page=lot_list" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">
                    <i class="fas fa-arrow-left mr-2"></i>ย้อนกลับ
                </a>
            </div>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">เพิ่มล็อตใหม่</h3>
                <form id="addForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">รายละเอียดล็อต</label>
                        <textarea id="addLotDetail" name="lot_detail"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required></textarea>
                        <span id="addDetailError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">คลังสินค้า</label>
                        <select id="addWarehouseId" name="warehouse_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกคลังสินค้า</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>">
                                    <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="addWarehouseError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">สถานะล็อต</label>
                        <select id="addLotStatusId" name="lot_status_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกสถานะล็อต</option>
                            <?php foreach ($lot_statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status['lot_status_id']); ?>">
                                    <?php echo htmlspecialchars($status['lot_status_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="addStatusError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">วันที่ล็อต</label>
                        <input type="date" id="addLotDate" name="lot_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="addDateError" class="text-red-500 text-xs mt-1 hidden"></span>
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
                urlParams.set('page', 'supplier_lot');
                urlParams.set('supplier_id', '<?php echo $supplier_id; ?>');
                urlParams.delete('search');
                window.location.search = urlParams.toString();
            }
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
            let url = '/page/lot/ajax/report/supplier_lot_pdf.php?supplier_id=<?php echo urlencode($supplier_id); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/supplier_lot_excel.php?supplier_id=<?php echo urlencode($supplier_id); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
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
                    } else if (field.id.includes('Date') && input.value && !/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
                        error.textContent = `${field.label}ต้องอยู่ในรูปแบบ YYYY-MM-DD`;
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
                        id: 'addLotDetail',
                        errorId: 'addDetailError',
                        label: 'รายละเอียดล็อต',
                        maxLength: 500,
                        required: true
                    },
                    {
                        id: 'addWarehouseId',
                        errorId: 'addWarehouseError',
                        label: 'คลังสินค้า',
                        maxLength: Infinity,
                        required: true
                    },
                    {
                        id: 'addLotStatusId',
                        errorId: 'addStatusError',
                        label: 'สถานะล็อต',
                        maxLength: Infinity,
                        required: true
                    },
                    {
                        id: 'addLotDate',
                        errorId: 'addDateError',
                        label: 'วันที่ล็อต',
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
                        id: 'editLotDetail',
                        errorId: 'editDetailError',
                        label: 'รายละเอียดล็อต',
                        maxLength: 500,
                        required: true
                    },
                    {
                        id: 'editWarehouseId',
                        errorId: 'editWarehouseError',
                        label: 'คลังสินค้า',
                        maxLength: Infinity,
                        required: true
                    },
                    {
                        id: 'editLotStatusId',
                        errorId: 'editStatusError',
                        label: 'สถานะล็อต',
                        maxLength: Infinity,
                        required: true
                    },
                    {
                        id: 'editLotDate',
                        errorId: 'editDateError',
                        label: 'วันที่ล็อต',
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
                urlParams.set('page', 'supplier_lot');
                urlParams.set('supplier_id', '<?php echo $supplier_id; ?>');

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