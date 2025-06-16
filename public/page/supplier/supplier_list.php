<?php
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

// Session Management
if (!isset($_SESSION)) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'supplier_list'] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Database Functions
function fetchData($pdo, $query, $params = [])
{
    try {
        if (!$pdo) {
            throw new Exception("PDO object is not initialized");
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        die("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage()));
    }
}

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validateSupplierData($name, $address, $phone, $personnel_id, $pdo, $exclude_id = null)
{
    $errors = [];
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อผู้ส่งมอบต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (empty($address) || strlen($address) > 500) {
        $errors[] = 'ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร';
    }
    if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก';
    }
    if (!$personnel_id) {
        $errors[] = 'กรุณาเลือกพนักงาน';
    }
    return $errors;
}

function checkSupplierDependencies($pdo, $supplier_id)
{
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE supplier_id = ?");
    $stmt->execute([$supplier_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "สินค้า ({$count} รายการ)";
    }
    return $dependencies;
}

// Handle Requests
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=supplier_list&error=Invalid CSRF token');
        exit;
    }

    if ($_POST['action'] === 'add') {
        $name = trim($_POST['add_supplier_name']);
        $address = trim($_POST['add_supplier_address']);
        $phone = trim($_POST['add_supplier_phone'] ?? '');
        $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);

        $errors = validateSupplierData($name, $address, $phone, $personnel_id, $pdo);
        if (!empty($errors)) {
            header('Location: index.php?page=supplier_list&error=' . urlencode(implode(', ', $errors)));
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO supplier (supplier_name, supplier_address, supplier_phone, personnel_id, supplier_create_date) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $address, $phone ?: null, $personnel_id]);
            $pdo->commit();
            header('Location: index.php?page=supplier_list&success=เพิ่มข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Add supplier error: " . $e->getMessage());
            header('Location: index.php?page=supplier_list&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
            exit;
        }
    }

    if ($_POST['action'] === 'edit') {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        $name = trim($_POST['update_supplier_name']);
        $address = trim($_POST['update_supplier_address']);
        $phone = trim($_POST['update_supplier_phone'] ?? '');
        $personnel_id = filter_var($_POST['update_personnel_id'], FILTER_VALIDATE_INT);

        $errors = validateSupplierData($name, $address, $phone, $personnel_id, $pdo, $id);
        if (!$id) {
            $errors[] = 'รหัสผู้ส่งมอบไม่ถูกต้อง';
        }

        if (!empty($errors)) {
            header('Location: index.php?page=supplier_list&error=' . urlencode(implode(', ', $errors)));
            exit;
        }

        try {
            $pdo->beginTransaction();
            $query = "UPDATE supplier SET supplier_name = ?, supplier_address = ?, supplier_phone = ?, personnel_id = ? WHERE supplier_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$name, $address, $phone ?: null, $personnel_id, $id]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                header('Location: index.php?page=supplier_list&error=ไม่พบข้อมูลผู้ส่งมอบที่ต้องการแก้ไข');
                exit;
            }
            $pdo->commit();
            header('Location: index.php?page=supplier_list&success=แก้ไขข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update supplier error: " . $e->getMessage());
            header('Location: index.php?page=supplier_list&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
            exit;
        }
    }

    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            header('Location: index.php?page=supplier_list&error=รหัสผู้ส่งมอบไม่ถูกต้อง');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $dependencies = checkSupplierDependencies($pdo, $id);
            if (!empty($dependencies)) {
                $pdo->rollBack();
                $dependency_list = implode(', ', $dependencies);
                header('Location: index.php?page=supplier_list&error=' . urlencode("ไม่สามารถลบผู้ส่งมอบได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM supplier WHERE supplier_id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                header('Location: index.php?page=supplier_list&error=ไม่พบข้อมูลผู้ส่งมอบที่ต้องการลบ');
                exit;
            }
            $pdo->commit();
            header("Location: index.php?page=supplier_list&success=ลบข้อมูลสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Delete supplier error: Supplier ID=$id, Error=" . $e->getMessage());
            $error_message = strpos($e->getMessage(), '1451') !== false
                ? 'ไม่สามารถลบผู้ส่งมอบได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
                : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
            header("Location: index.php?page=supplier_list&error=" . urlencode($error_message));
            exit;
        }
    }
}

// Pagination
try {
    $count_query = "SELECT COUNT(*) as total_count FROM supplier";
    $params = [];
    if (!empty($search)) {
        $count_query .= " WHERE supplier_id LIKE :search OR supplier_name LIKE :search OR supplier_phone LIKE :search";
        $params[':search'] = "%$search%";
    }
    // ลบเงื่อนไขวันที่ชั่วคราวเพื่อทดสอบ
    /*
    if ($start_date || $end_date) {
        $count_query .= (!empty($search) ? " AND" : " WHERE") . " 1=1";
        if ($start_date) {
            $count_query .= " AND supplier_create_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $count_query .= " AND supplier_create_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }
    */
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
    error_log("Total Records: $total_records");
} catch (PDOException $e) {
    error_log("Count supplier error: " . $e->getMessage());
    die("เกิดข้อผิดพลาดในการนับข้อมูล: " . htmlspecialchars($e->getMessage()));
}

$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));
error_log("Page: $page, Offset: $offset, Total Pages: $total_pages");

// Fetch Personnel Data
try {
    $personnels = fetchData($pdo, "SELECT personnel_id, personnel_name FROM personnel")->fetchAll(PDO::FETCH_ASSOC);
    error_log("Personnels: " . print_r($personnels, true));
} catch (Exception $e) {
    error_log("Fetch personnel error: " . $e->getMessage());
    die("เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage()));
}

// Fetch Supplier Data
try {
    $query = "
        SELECT 
            sup.supplier_id, 
            sup.supplier_name, 
            sup.supplier_address, 
            sup.supplier_phone, 
            sup.supplier_create_date,
            sup.personnel_id, 
            COALESCE(per.personnel_name, 'ไม่ระบุ') AS personnel_name
        FROM supplier sup
        LEFT JOIN personnel per ON sup.personnel_id = per.personnel_id";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE (sup.supplier_id LIKE :search OR sup.supplier_name LIKE :search OR sup.supplier_phone LIKE :search)";
        $params[':search'] = "%$search%";
    }
    // ลบเงื่อนไขวันที่ชั่วคราวเพื่อทดสอบ
    /*
    if ($start_date) {
        $query .= (!empty($search) ? " AND" : " WHERE") . " sup.supplier_create_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $query .= (!empty($search) || $start_date ? " AND" : " WHERE") . " sup.supplier_create_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }
    */
    $query .= " ORDER BY sup.supplier_id DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("SQL Query: $query");
    error_log("Params: " . print_r($params, true));
    error_log("Suppliers: " . print_r($suppliers, true));
} catch (Exception $e) {
    error_log("Fetch supplier error: " . $e->getMessage());
    die("เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการผู้ส่งมอบ</title>
    <link href="/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/style/page_list.css">
    <style>
        /* เพิ่ม CSS เพื่อให้แน่ใจว่าตารางแสดง */
        table {
            display: table !important;
            visibility: visible !important;
        }

        tbody tr {
            display: table-row !important;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fas fa-users mr-2"></i> <?php echo $total_records; ?> รายการ
                </span>
                <button onclick="showAddModal()" class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition justify-center md:justify-start">
                    <i class="fas fa-plus mr-2"></i> เพิ่มผู้ส่งมอบ
                </button>
            </div>
            <form action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="supplier_list">
                <input type="hidden" name="page_num" value="1">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหาผู้ส่งมอบ..." value="<?php echo htmlspecialchars($search); ?>"
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
                    <button onclick="exportPDF()" class="w-full px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition">
                        <i class="fas fa-file-pdf mr-2"></i> ส่งออก PDF
                    </button>
                    <button onclick="exportExcel()" class="w-full px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition">
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

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 text-sm">
                            <th class="px-6 py-3 text-left font-semibold">รหัสผู้ส่งมอบ</th>
                            <th class="px-6 py-3 text-left font-semibold">ชื่อ</th>
                            <th class="px-6 py-3 text-left font-semibold">ที่อยู่</th>
                            <th class="px-6 py-3 text-left font-semibold">เบอร์โทรศัพท์</th>
                            <th class="px-6 py-3 text-left font-semibold">วันที่และเวลา</th>
                            <th class="px-6 py-3 text-left font-semibold">พนักงาน</th>
                            <th class="px-6 py-3 text-center font-semibold">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($suppliers) > 0): ?>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($supplier['supplier_id']); ?></td>
                                    <td class="px-6 py-3 border-t">
                                        <a href="?page=supplier_detail&tabel=supplier&id=<?php echo htmlspecialchars($supplier['supplier_id']); ?>"
                                            class="text-blue-600"><?php echo htmlspecialchars($supplier['supplier_name']); ?></a>
                                    </td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($supplier['supplier_address']); ?></td>
                                    <td class="px-6 py-3 border-t">
                                        <?php echo $supplier['supplier_phone'] ? htmlspecialchars($supplier['supplier_phone']) : '<span class="text-gray-400">-</span>'; ?>
                                    </td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($supplier['supplier_create_date']); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($supplier['personnel_name']); ?></td>
                                    <td class="px-6 py-3 border-t text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="showEditModal('<?php echo htmlspecialchars($supplier['supplier_id']); ?>', '<?php echo htmlspecialchars(addslashes($supplier['supplier_name'])); ?>', '<?php echo htmlspecialchars(addslashes($supplier['supplier_address'])); ?>', '<?php echo htmlspecialchars($supplier['supplier_phone'] ?? ''); ?>', '<?php echo htmlspecialchars($supplier['personnel_id']); ?>')"
                                                class="flex items-center justify-center px-3 py-1 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition">
                                                <div class="hidden md:flex">
                                                    <i class="fas fa-edit mr-1"></i>
                                                </div>
                                                <p>แก้ไข</p>
                                            </button>
                                            <form action="" method="POST" class="delete-form inline-flex">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($supplier['supplier_id']); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <button type="button" onclick="confirmDelete(this.parentElement)"
                                                    class="flex items-center justify-center px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition">
                                                    <div class="hidden md:flex">
                                                        <i class="fas fa-trash-alt mr-1"></i>
                                                    </div>
                                                    <p>ลบ</p>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center py-6">
                                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                        <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                                        <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                                            <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                                <a href="?page=supplier_list" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลผู้ส่งมอบในระบบ</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=supplier_list&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">« ก่อนหน้า</a>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=supplier_list&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=supplier_list&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded-md transition <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=supplier_list&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition"><?= $total_pages ?></a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=supplier_list&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">ถัดไป »</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="modal hidden">
            <div class="modal-content">
                <div class="mb-5">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>เพิ่มผู้ส่งมอบใหม่
                    </h3>
                    <p class="text-sm text-gray-500">กรุณากรอกข้อมูลผู้ส่งมอบ</p>
                </div>
                <form id="addForm" method="POST" class="space-y-4" autocomplete="off">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อผู้ส่งมอบ</label>
                        <input type="text" id="addSupplierName" name="add_supplier_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addNameError" autocomplete="off">
                        <span id="addNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                        <textarea id="addSupplierAddress" name="add_supplier_address"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addAddressError" autocomplete="off"></textarea>
                        <span id="addAddressError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                        <input type="text" id="addSupplierPhone" name="add_supplier_phone" maxlength="10"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-describedby="addPhoneError" autocomplete="off">
                        <span id="addPhoneError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">พนักงาน</label>
                        <select id="addPersonnelId" name="personnel_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addPersonnelError">
                            <option value="">เลือกพนักงาน</option>
                            <?php foreach ($personnels as $personnel): ?>
                                <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                                    <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="addPersonnelError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            ยกเลิก
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editModal" class="modal hidden">
            <div class="modal-content">
                <div class="flex items-center space-x-2 mb-4">
                    <i class="far fa-edit text-blue-600 text-xl"></i>
                    <h3 class="text-xl font-bold text-gray-800">แก้ไขข้อมูลผู้ส่งมอบ</h3>
                </div>
                <form id="editForm" method="POST" class="space-y-4">
                    <input type="hidden" id="editSupplierId" name="id">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อผู้ส่งมอบ</label>
                        <input type="text" id="editSupplierName" name="update_supplier_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="editNameError">
                        <span id="editNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                        <textarea id="editSupplierAddress" name="update_supplier_address"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="editAddressError"></textarea>
                        <span id="editAddressError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                        <input type="text" id="editSupplierPhone" name="update_supplier_phone" maxlength="10"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-describedby="editPhoneError">
                        <span id="editPhoneError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">พนักงาน</label>
                        <select id="editPersonnelId" name="update_personnel_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="editPersonnelError">
                            <option value="">เลือกพนักงาน</option>
                            <?php foreach ($personnels as $personnel): ?>
                                <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                                    <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="editPersonnelError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            ยกเลิก
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal hidden">
            <div class="modal-content">
                <h3 class="text-xl font-bold text-gray-800 mb-4">ยืนยันการลบ</h3>
                <p class="text-gray-600 mb-6">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?</p>
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
        const showAddModal = () => {
            document.getElementById('addSupplierName').value = '';
            document.getElementById('addSupplierAddress').value = '';
            document.getElementById('addSupplierPhone').value = '';
            document.getElementById('addPersonnelId').value = '';
            ['addNameError', 'addAddressError', 'addPhoneError', 'addPersonnelError'].forEach(id => document.getElementById(id).classList.add('hidden'));
            document.getElementById('addModal').classList.remove('hidden');
        };

        const closeAddModal = () => {
            document.getElementById('addModal').classList.add('hidden');
        };

        const showEditModal = (id, name, address, phone, personnelId) => {
            document.getElementById('editSupplierId').value = id;
            document.getElementById('editSupplierName').value = name;
            document.getElementById('editSupplierAddress').value = address;
            document.getElementById('editSupplierPhone').value = phone;
            document.getElementById('editPersonnelId').value = personnelId;
            ['editNameError', 'editAddressError', 'editPhoneError', 'editPersonnelError'].forEach(id => document.getElementById(id).classList.add('hidden'));
            document.getElementById('editModal').classList.remove('hidden');
        };

        const closeEditModal = () => {
            document.getElementById('editModal').classList.add('hidden');
        };

        const showDeleteModal = () => {
            document.getElementById('deleteModal').classList.remove('hidden');
        };

        const closeDeleteModal = () => {
            document.getElementById('deleteModal').classList.add('hidden');
        };

        const confirmDelete = (form) => {
            showDeleteModal();
            document.getElementById('confirmDeleteBtn').onclick = () => form.submit();
        };

        const showNotification = (message, headerClass) => {
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

            setTimeout(() => {
                hideNotification();
            }, 5000);
        };

        const hideNotification = () => {
            document.getElementById('notification').classList.add('translate-x-full');
        };

        const exportPDF = () => {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/supplier/report/export_supplier_pdf.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        };

        const exportExcel = () => {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/supplier/report/export_supplier_excel.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        };

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

            const validateForm = (formId, fields) => {
                let hasError = false;
                fields.forEach(({
                    id,
                    errorId,
                    label,
                    maxLength,
                    regex,
                    isOptional
                }) => {
                    const input = document.getElementById(id);
                    const error = document.getElementById(errorId);
                    if (!isOptional && !input.value.trim()) {
                        error.textContent = `กรุณากรอก${label}`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (input.value.length > maxLength) {
                        error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (regex && input.value && !regex.test(input.value)) {
                        error.textContent = `${label}ต้องเป็นตัวเลข 10 หลัก`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else {
                        error.classList.add('hidden');
                    }
                });
                return !hasError;
            };

            document.getElementById('addForm').addEventListener('submit', (e) => {
                e.preventDefault();
                const fields = [{
                        id: 'addSupplierName',
                        errorId: 'addNameError',
                        label: 'ชื่อผู้ส่งมอบ',
                        maxLength: 255
                    },
                    {
                        id: 'addSupplierAddress',
                        errorId: 'addAddressError',
                        label: 'ที่อยู่',
                        maxLength: 500
                    },
                    {
                        id: 'addSupplierPhone',
                        errorId: 'addPhoneError',
                        label: 'เบอร์โทรศัพท์',
                        maxLength: 10,
                        regex: /^[0-9]{10}$/,
                        isOptional: true
                    },
                    {
                        id: 'addPersonnelId',
                        errorId: 'addPersonnelError',
                        label: 'พนักงาน',
                        maxLength: Infinity
                    }
                ];
                if (validateForm('addForm', fields)) {
                    e.target.submit();
                }
            });

            document.getElementById('editForm').addEventListener('submit', (e) => {
                e.preventDefault();
                const fields = [{
                        id: 'editSupplierName',
                        errorId: 'editNameError',
                        label: 'ชื่อผู้ส่งมอบ',
                        maxLength: 255
                    },
                    {
                        id: 'editSupplierAddress',
                        errorId: 'editAddressError',
                        label: 'ที่อยู่',
                        maxLength: 500
                    },
                    {
                        id: 'editSupplierPhone',
                        errorId: 'editPhoneError',
                        label: 'เบอร์โทรศัพท์',
                        maxLength: 10,
                        regex: /^[0-9]{10}$/,
                        isOptional: true
                    },
                    {
                        id: 'editPersonnelId',
                        errorId: 'editPersonnelError',
                        label: 'พนักงาน',
                        maxLength: Infinity
                    }
                ];
                if (validateForm('editForm', fields)) {
                    e.target.submit();
                }
            });

            document.getElementById('start_date').addEventListener('change', updateDateFilter);
            document.getElementById('end_date').addEventListener('change', updateDateFilter);

            function updateDateFilter() {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', 'supplier_list');
                if (startDate) urlParams.set('start_date', startDate);
                else urlParams.delete('start_date');
                if (endDate) urlParams.set('end_date', endDate);
                else urlParams.delete('end_date');
                window.location.search = urlParams.toString();
            }
        });
    </script>
</body>

</html>