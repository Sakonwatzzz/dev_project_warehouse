<?php
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
        throw new Exception("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage()));
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
        $errors[] = 'กรุณาเลือกผู้ส่งมอบ';
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
        header('Location: index.php?page=lot_list&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
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
        header('Location: index.php?page=lot_list&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
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
        header('Location: index.php?page=lot_list&error=รหัสล็อตไม่ถูกต้อง');
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
    $count_query = "SELECT COUNT(*) FROM lot WHERE warehouse_inout = 1";
    $params = [];
    if (!empty($search)) {
        $count_query .= " AND (lot_id LIKE :search OR lot_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date || $end_date) {
        $count_query .= " AND 1=1";
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
    $stmt->execute($params);
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count lot error: " . $e->getMessage());
    header('Location: index.php?page=lot_list&error=' . urlencode('เกิดข้อผิดพลาดในการนับข้อมูล: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 10;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
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
            supplier.supplier_name,
            COALESCE(COUNT(lp.lot_product_id), 0) as product_count
        FROM lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN lot_product lp ON lot.lot_id = lp.lot_id
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
    $query .= " GROUP BY lot.lot_id ORDER BY lot.lot_date DESC LIMIT :offset, :limit";
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
    <link href="../../output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style/page_list.css">
    <style>
        @media (max-width: 768px) {
            .table-responsive {
                display: none;
            }

            .card-responsive {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .table-responsive {
                display: block;
            }

            .card-responsive {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fas fa-list mr-2"></i> <?php echo number_format($total_records); ?> รายการ
                </span>
            </div>

            <form action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="lot_list">
                <input type="hidden" name="page_num" value="1">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหาล็อตสินค้า..." value="<?php echo htmlspecialchars($search); ?>"
                        class="w-full md:w-80 px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Date Filter and Export Buttons -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">เริ่มจาก</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">ถึงวันที่</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex items-end space-x-2">
                    <button onclick="exportPDF()" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                        <i class="fas fa-file-pdf mr-2"></i> ส่งออก PDF
                    </button>
                    <button onclick="exportExcel()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-file-excel mr-2"></i> ส่งออก Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div id="notification"
            class="fixed top-4 right-4 max-w-sm w-full bg-white rounded-lg shadow-lg border-l-4 transform translate-x-full transition-transform duration-300 z-50 flex items-center">
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

        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto table-responsive">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 text-sm">
                            <th class="px-6 py-3 text-left font-semibold">รหัสล็อต</th>
                            <th class="px-6 py-3 text-left font-semibold">ชื่อล็อต</th>
                            <th class="px-6 py-3 text-left font-semibold">คลังปลายทาง</th>
                            <th class="px-6 py-3 text-left font-semibold">ผู้ส่งมอบ</th>
                            <th class="px-6 py-3 text-left font-semibold">วันที่และเวลา</th>
                            <th class="px-6 py-3 text-left font-semibold">สถานะ</th>
                            <th class="px-6 py-3 text-left font-semibold">จำนวนสินค้า</th>
                            <th class="px-6 py-3 text-center font-semibold">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (count($result_lot_list) > 0): ?>
                            <?php foreach ($result_lot_list as $lot): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-3"><?php echo htmlspecialchars($lot['lot_id']); ?></td>
                                    <td class="px-6 py-3">
                                        <a href="?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                            class="text-blue-600 hover:underline cursor-pointer">
                                            <?php echo htmlspecialchars($lot['lot_name']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-3"><?php echo htmlspecialchars($lot['warehouse_name']); ?></td>
                                    <td class="px-6 py-3"><?php echo htmlspecialchars($lot['supplier_name']); ?></td>
                                    <td class="px-6 py-3"><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($lot['lot_date']))); ?></td>
                                    <td class="px-6 py-3"><?php echo htmlspecialchars($lot['lot_status_name']); ?></td>
                                    <td class="px-6 py-3">
                                        <a href="?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                            class="text-blue-600 hover:underline cursor-pointer">
                                            <?php echo number_format($lot['product_count']); ?> รายการ
                                        </a>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <div class="flex justify-center space-x-2">
                                            <?php if ($lot['lot_status_id'] == 2): ?>
                                                <!-- <a href="?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                                    class="flex items-center justify-center px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition">
                                                    <i class="fas fa-eye mr-1"></i>
                                                    <span class="hidden md:inline">ดู</span>
                                                    <span class="md:hidden">ดู</span>
                                                </a> -->
                                                <a href="index.php?page=lot_listdetail&tabel=lot&id=<?= urlencode($lot['lot_id']) ?>"
                                                    class="bg-blue-500 text-white px-4 py-1 rounded hover:bg-blue-600 transition">
                                                    <i class="fas fa-eye mr-1"></i>ดู
                                                </a>
                                            <?php else: ?>
                                                <button
                                                    class="bg-yellow-500 text-white text-sm px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center whitespace-nowrap">
                                                    <a href="?page=lot_listdetail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                                        class="">
                                                        <i class="fas fa-edit mr-1"></i> แก้ไข
                                                    </a>
                                                </button>
                                                <form action="" method="POST" class="delete-form inline-flex">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($lot['lot_id']); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <button type="button" onclick="confirmDelete(this.form)"
                                                        class="flex items-center justify-center px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition">
                                                        <i class="fas fa-trash-alt mr-1"></i>
                                                        <span class="hidden md:inline">ลบ</span>
                                                        <span class="md:hidden">ลบ</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center py-6">
                                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                        <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                                        <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                                            <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                                <a href="?page=lot_list" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลล็อตสินค้าในระบบ</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Card View (Mobile) -->
            <div class="card-responsive p-6">
                <?php if (count($result_lot_list) > 0): ?>
                    <?php foreach ($result_lot_list as $lot): ?>
                        <div class="bg-white p-4 mb-4 rounded-lg shadow" data-id="<?php echo htmlspecialchars($lot['lot_id']); ?>">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">รหัสล็อต:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot['lot_id']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ชื่อล็อต:</span>
                                <a href="?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                    class="text-blue-600 hover:underline text-sm">
                                    <?php echo htmlspecialchars($lot['lot_name']); ?>
                                </a>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">คลังปลายทาง:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot['warehouse_name']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ผู้ส่งมอบ:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot['supplier_name']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">วันที่และเวลา:</span>
                                <span class="text-sm"><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($lot['lot_date']))); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">สถานะ:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot['lot_status_name']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">จำนวนสินค้า:</span>
                                <a href="?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                    class="text-blue-600 hover:underline text-sm">
                                    <?php echo number_format($lot['product_count']); ?> รายการ
                                </a>
                            </div>
                            <div class="flex justify-center space-x-2 mt-4">
                                <?php if ($lot['lot_status_id'] == 2): ?>
                                    <a href="?page=lot_listdetail&lot_id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                        class="flex items-center justify-center px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition">
                                        <i class="fas fa-eye mr-1"></i> ดู
                                    </a>
                                <?php else: ?>
                                    <button
                                        class="bg-yellow-500 text-white text-sm px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center whitespace-nowrap">
                                        <a href="?page=lot_listdetail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                            class="">
                                            <i class="fas fa-edit mr-1"></i> แก้ไข
                                        </a>
                                    </button>
                                    <form action="" method="POST" class="delete-form inline-flex">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($lot['lot_id']); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="button" onclick="confirmDelete(this.form)"
                                            class="flex items-center justify-center px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition">
                                            <i class="fas fa-trash-alt mr-1"></i> ลบ
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-600 text-sm">
                        <div class="flex flex-col items-center justify-center py-6">
                            <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                            <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                            <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                                <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                    <a href="?page=lot_list" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลล็อตสินค้าในระบบ</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=lot_list&page_num=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">« ก่อนหน้า</a>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=lot_list&page_num=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=lot_list&page_num=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; ?>"
                        class="px-4 py-2 rounded-md transition <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=lot_list&page_num=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_list&page_num=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">ถัดไป »</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal hidden fixed  inset-0 bg-gray-600 bg-opacity-50  items-center justify-center">
            <div class="modal-content bg-white rounded-lg p-6 max-w-sm w-full">
                <h3 class="text-xl font-bold text-gray-800 mb-4">ยืนยันการลบ</h3>
                <p class="text-gray-600 mb-6">คุณแน่ใจหรือไม่ว่าต้องการลบล็อตนี้?</p>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        ยกเลิก
                    </button>
                    <button type="button" id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        ลบ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showDeleteModal() {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function confirmDelete(form) {
            showDeleteModal();
            document.getElementById('confirmDeleteBtn').onclick = () => form.submit();
        }

        function showNotification(message, headerClass) {
            const notification = document.getElementById('notification');
            const header = document.getElementById('notification-header');
            const title = document.getElementById('notificationTitle');
            const body = document.getElementById('notificationMessage');
            const icon = document.getElementById('notificationIcon');

            header.className = `flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg ${headerClass}`;
            title.textContent = 'แจ้งเตือน';
            body.textContent = message;
            icon.className = `fas ${headerClass.includes('bg-green') ? 'fa-check-circle' : 'fa-exclamation-circle'} text-2xl`;
            notification.classList.remove('translate-x-full');

            setTimeout(hideNotification, 5000);
        }

        function hideNotification() {
            document.getElementById('notification').classList.add('translate-x-full');
        }

        function exportPDF() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/export_lot_list_pdf.php';
            const params = [];
            if (startDate) params.push(`start_date=${encodeURIComponent(startDate)}`);
            if (endDate) params.push(`end_date=${encodeURIComponent(endDate)}`);
            if (params.length) url += `?${params.join('&')}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/export_lot_list_excel.php';
            const params = [];
            if (startDate) params.push(`start_date=${encodeURIComponent(startDate)}`);
            if (endDate) params.push(`end_date=${encodeURIComponent(endDate)}`);
            if (params.length) url += `?${params.join('&')}`;
            window.open(url, '_blank');
        }

        function updateDateFilter() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', 'lot_list');
            urlParams.set('page_num', '1');
            if (startDate) urlParams.set('start_date', startDate);
            else urlParams.delete('start_date');
            if (endDate) urlParams.set('end_date', endDate);
            else urlParams.delete('end_date');
            window.location.search = urlParams.toString();
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('searchInput');

            if (searchInput) {
                searchInput.focus();
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        searchInput.value = '';
                        searchInput.form.submit();
                    }
                    if (e.key === 'Enter') {
                        localStorage.setItem('searchPreserve', searchInput.value);
                    }
                });

                const preserved = localStorage.getItem('searchPreserve');
                if (preserved) {
                    searchInput.value = preserved;
                    searchInput.focus();
                    searchInput.selectionStart = searchInput.selectionEnd = preserved.length;
                    localStorage.removeItem('searchPreserve');
                }

                searchInput.addEventListener('input', () => {
                    if (searchInput.value.trim() === '') {
                        searchInput.form.submit();
                    }
                });
            }

            if (urlParams.has('success')) {
                showNotification(decodeURIComponent(urlParams.get('success')), 'bg-green-500');
            } else if (urlParams.has('error')) {
                showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
            }

            function validateForm(formId, fields) {
                let hasError = false;
                fields.forEach(({
                    id,
                    errorId,
                    label,
                    maxLength,
                    required
                }) => {
                    const input = document.getElementById(id);
                    const error = document.getElementById(errorId);
                    if (!input.value.trim() && required) {
                        error.textContent = `กรุณากรอก${label}`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (maxLength !== Infinity && input.value.length > maxLength) {
                        error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else {
                        error.classList.add('hidden');
                    }
                });
                return !hasError;
            }



            document.getElementById('start_date').addEventListener('change', updateDateFilter);
            document.getElementById('end_date').addEventListener('change', updateDateFilter);
        });
    </script>
</body>

</html>