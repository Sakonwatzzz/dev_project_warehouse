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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'personnel_group', 'type' => $_GET['type'] ?? ''] + ($_GET ?? []));
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

// Validate personnel data
function validatePersonnelData($name, $username, $type_id, $address, $phone, $password, $pdo, $exclude_id = null)
{
    $errors = [];
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อบุคลากรต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (empty($username) || strlen($username) > 100) {
        $errors[] = 'ชื่อผู้ใช้ต้องไม่ว่างและมีความยาวไม่เกิน 100 ตัวอักษร';
    } else {
        $query = "SELECT COUNT(*) FROM personnel WHERE personnel_username = :username";
        if ($exclude_id) {
            $query .= " AND personnel_id != :exclude_id";
        }
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':username', $username);
        if ($exclude_id) {
            $stmt->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว';
        }
    }
    if (!$type_id) {
        $errors[] = 'กรุณาเลือกประเภทบุคลากร';
    }
    if (empty($address) || strlen($address) > 500) {
        $errors[] = 'ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร';
    }
    if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก';
    }
    if (empty($password) || strlen($password) < 6 || strlen($password) > 255) {
        $errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษรและไม่เกิน 255 ตัวอักษร';
    }
    return $errors;
}

// Check personnel dependencies
function checkPersonnelDependencies($pdo, $personnel_id)
{
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_type WHERE personnel_id = ?");
    $stmt->execute([$personnel_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "ประเภทสินค้า ({$count} รายการ)";
    }
    return $dependencies;
}

// Handle search, date, and type parameters - แก้ไขการ decode
$type = isset($_GET['type']) ? urldecode(trim($_GET['type'])) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Handle POST requests for adding personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=Invalid CSRF token');
        exit;
    }

    $name = trim($_POST['add_personnel_name']);
    $username = trim($_POST['add_personnel_username']);
    $type_id = filter_var($_POST['personnel_type_id'], FILTER_VALIDATE_INT);
    $address = trim($_POST['add_personnel_address']);
    $phone = trim($_POST['add_personnel_phone']);
    $password = password_hash(trim($_POST['add_personnel_password']), PASSWORD_DEFAULT);

    $errors = validatePersonnelData($name, $username, $type_id, $address, $phone, $_POST['add_personnel_password'], $pdo);

    if (!empty($errors)) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO personnel (personnel_name, personnel_username, personnel_type_id, personnel_address, personnel_phone, personnel_password, personnel_create_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $username, $type_id, $address, $phone, $password]);
        $pdo->commit();
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&success=เพิ่มข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add personnel error: " . $e->getMessage());
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle POST requests for updating personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $name = trim($_POST['update_personnel_name']);
    $username = trim($_POST['update_personnel_username']);
    $type_id = filter_var($_POST['update_personnel_type_id'], FILTER_VALIDATE_INT);
    $address = trim($_POST['update_personnel_address']);
    $phone = trim($_POST['update_personnel_phone']);
    $password = !empty(trim($_POST['update_personnel_password'])) ? password_hash(trim($_POST['update_personnel_password']), PASSWORD_DEFAULT) : null;

    $errors = validatePersonnelData($name, $username, $type_id, $address, $phone, $_POST['update_personnel_password'] ?: 'dummy', $pdo, $id);
    if (!$id) {
        $errors[] = 'รหัสบุคลากรไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $query = "UPDATE personnel SET personnel_name = ?, personnel_username = ?, personnel_type_id = ?, personnel_address = ?, personnel_phone = ?";
        $params = [$name, $username, $type_id, $address, $phone];
        if ($password) {
            $query .= ", personnel_password = ?";
            $params[] = $password;
        }
        $query .= " WHERE personnel_id = ?";
        $params[] = $id;
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=ไม่พบข้อมูลบุคลากรที่ต้องการแก้ไข');
            exit;
        }
        $pdo->commit();
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&success=แก้ไขข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update personnel error: " . $e->getMessage());
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=Invalid personnel ID');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $dependencies = checkPersonnelDependencies($pdo, $id);

        if (!empty($dependencies)) {
            $pdo->rollBack();
            $dependency_list = implode(', ', $dependencies);
            header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode("ไม่สามารถลบบุคลากรได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM personnel WHERE personnel_id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=ไม่พบข้อมูลบุคลากรที่ต้องการลบ');
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=personnel_group&type=" . urlencode($type) . "&success=ลบข้อมูลสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete personnel error: Personnel ID=$id, Error=" . $e->getMessage());
        $error_message = strpos($e->getMessage(), '1451') !== false
            ? 'ไม่สามารถลบบุคลากรได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
            : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
        header("Location: index.php?page=personnel_group&type=" . urlencode($type) . "&error=" . urlencode($error_message));
        exit;
    }
}

// Count total records for pagination
try {
    $count_query = "
        SELECT COUNT(*) as total_count
        FROM personnel
        INNER JOIN personnel_type ON personnel.personnel_type_id = personnel_type.personnel_type_id
        WHERE personnel_type.personnel_type_name = :type";
    $params = [':type' => $type];

    // เพิ่มเงื่อนไขการค้นหา
    if (!empty($search)) {
        $count_query .= " AND (personnel.personnel_id LIKE :search OR personnel.personnel_name LIKE :search OR personnel.personnel_username LIKE :search OR personnel.personnel_phone LIKE :search)";
        $params[':search'] = "%$search%";
    }

    // เพิ่มเงื่อนไขวันที่
    if ($start_date) {
        $count_query .= " AND personnel_create_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $count_query .= " AND personnel_create_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }

    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count personnel error: " . $e->getMessage());
    header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch personnel types for dropdown
try {
    $personnel_type_query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
    $personnel_types = fetchData($pdo, $personnel_type_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode($e->getMessage()));
    exit;
}
// Fetch personnel data
try {
    $query = "
        SELECT 
            p.personnel_id,
            p.personnel_name,
            p.personnel_username,
            p.personnel_address,
            p.personnel_phone,
            p.personnel_create_date,
            p.personnel_type_id,
            pt.personnel_type_name
        FROM personnel p
        INNER JOIN personnel_type pt ON p.personnel_type_id = pt.personnel_type_id
        WHERE pt.personnel_type_name = :type";
    $params = [':type' => $type];

    // เพิ่มเงื่อนไขการค้นหา
    if (!empty($search)) {
        $query .= " AND (p.personnel_id LIKE :search OR p.personnel_name LIKE :search OR p.personnel_username LIKE :search OR p.personnel_phone LIKE :search)";
        $params[':search'] = "%$search%";
    }

    // เพิ่มเงื่อนไขวันที่
    if ($start_date) {
        $query .= " AND p.personnel_create_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $query .= " AND p.personnel_create_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }

    $query .= " ORDER BY p.personnel_id DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กลุ่มบุคลากร: <?php echo htmlspecialchars($type); ?></title>
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
                        <i class="fas fa-users mr-2"></i>
                        <?php echo $total_records; ?>
                        <span>รายการ</span>
                    </div>
                </div>
                <button onclick="showAddModal()"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                    <i class="fas fa-plus mr-2"></i>เพิ่มบุคลากร
                </button>
            </div>
            <div class="flex items-center">
                <form action="" method="GET" id="searchForm" class="w-full text-end">
                    <input type="hidden" name="page" value="personnel_group">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                    <input type="hidden" name="page_num" value="1">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหาบุคลากร..."
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
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-list mr-2"></i> รายชื่อบุคลากร: <?php echo htmlspecialchars($type); ?>
                    </h2>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัสบุคลากร</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อ-นามสกุล</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อผู้ใช้</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">เบอร์โทรศัพท์</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่และเวลา</th>
                                <th class="px-6 py-3 border text-center text-gray-700 font-semibold">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($personnel) > 0): ?>
                                <?php foreach ($personnel as $person): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($person['personnel_id']); ?></td>
                                        <td class="px-6 py-2 border">
                                            <a href="?page=personnel_detail&tabel=personnel&id=<?php echo htmlspecialchars($person['personnel_id']); ?>"
                                                class="text-blue-600 hover:underline cursor-pointer">
                                                <?php echo htmlspecialchars($person['personnel_name']); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($person['personnel_username']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($person['personnel_phone']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($person['personnel_create_date']); ?></td>
                                        <td class="px-6 py-2 border text-center">
                                            <div class="flex flex-wrap justify-center gap-2">
                                                <button onclick="showEditModal('<?php echo htmlspecialchars($person['personnel_id']); ?>', '<?php echo htmlspecialchars(addslashes($person['personnel_name'])); ?>', '<?php echo htmlspecialchars($person['personnel_username']); ?>', '<?php echo htmlspecialchars($person['personnel_address']); ?>', '<?php echo htmlspecialchars($person['personnel_phone']); ?>', '<?php echo htmlspecialchars($person['personnel_type_id']); ?>')"
                                                    class="bg-yellow-500 text-white text-sm px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center whitespace-nowrap">
                                                    <i class="fas fa-edit mr-1"></i> แก้ไข
                                                </button>
                                                <form action="" method="POST" style="display:inline;" class="delete-form">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($person['personnel_id']); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <button type="button" onclick="confirmDelete(this.parentElement)"
                                                        class="bg-red-500 text-white text-sm px-3 py-1 rounded hover:bg-red-600 transition flex items-center whitespace-nowrap">
                                                        <i class="fas fa-trash-alt mr-1"></i> ลบ
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-gray-600">ไม่มีข้อมูลบุคลากร</td>
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
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Navigation Links -->
        <div class="flex space-x-2 mt-5 justify-center">
            <div class="detail-action">
                <a href="/?page=personnel_list" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">หน้ารายการ</a>
            </div>
            <div class="detail-action">
                <a href="/?page=personnel_type" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">หน้าประเภท</a>
            </div>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">เพิ่มบุคลากรใหม่</h3>
                <form id="addForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="personnel_type_id" id="addPersonnelTypeId"
                        value="<?php
                                $selected_type = array_filter($personnel_types, function ($ptype) use ($type) {
                                    return $ptype['personnel_type_name'] === $type;
                                });
                                echo !empty($selected_type) ? htmlspecialchars(reset($selected_type)['personnel_type_id']) : '';
                                ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อ-นามสกุล</label>
                        <input type="text" id="addPersonnelName" name="add_personnel_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="addNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                        <input type="text" id="addPersonnelUsername" name="add_personnel_username"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="addUsernameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                        <textarea id="addPersonnelAddress" name="add_personnel_address"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required></textarea>
                        <span id="addAddressError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                        <input type="text" id="addPersonnelPhone" name="add_personnel_phone"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="addPhoneError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                        <input type="password" id="addPersonnelPassword" name="add_personnel_password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="addPasswordError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ประเภทบุคลากร</label>
                        <p class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700">
                            <?php echo htmlspecialchars($type); ?>
                        </p>
                        <span id="addTypeError" class="text-red-500 text-xs mt-1 hidden"></span>
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
                <h3 class="text-xl font-bold text-gray-800 mb-4">แก้ไขข้อมูลบุคลากร</h3>
                <form id="editForm" method="POST" class="space-y-4">
                    <input type="hidden" id="editPersonnelId" name="id">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อ-นามสกุล</label>
                        <input type="text" id="editPersonnelName" name="update_personnel_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="editNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                        <input type="text" id="editPersonnelUsername" name="update_personnel_username"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="editUsernameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                        <textarea id="editPersonnelAddress" name="update_personnel_address"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required></textarea>
                        <span id="editAddressError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                        <input type="text" id="editPersonnelPhone" name="update_personnel_phone"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="editPhoneError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">รหัสผ่าน (เว้นว่างหากไม่ต้องการเปลี่ยน)</label>
                        <input type="password" id="editPersonnelPassword" name="update_personnel_password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span id="editPasswordError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ประเภทบุคลากร</label>
                        <select id="editPersonnelTypeId" name="update_personnel_type_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกประเภทบุคลากร</option>
                            <?php foreach ($personnel_types as $ptype): ?>
                                <option value="<?php echo htmlspecialchars($ptype['personnel_type_id']); ?>">
                                    <?php echo htmlspecialchars($ptype['personnel_type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="editTypeError" class="text-red-500 text-xs mt-1 hidden"></span>
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
        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', 'personnel_group');
                urlParams.set('type', '<?php echo urlencode($type); ?>');
                urlParams.delete('search');
                window.location.search = urlParams.toString();
            }
        }

        function showAddModal() {
            document.getElementById('addPersonnelName').value = '';
            document.getElementById('addPersonnelUsername').value = '';
            document.getElementById('addPersonnelAddress').value = '';
            document.getElementById('addPersonnelPhone').value = '';
            document.getElementById('addPersonnelPassword').value = '';
            document.getElementById('addNameError').classList.add('hidden');
            document.getElementById('addUsernameError').classList.add('hidden');
            document.getElementById('addAddressError').classList.add('hidden');
            document.getElementById('addPhoneError').classList.add('hidden');
            document.getElementById('addPasswordError').classList.add('hidden');
            document.getElementById('addTypeError').classList.add('hidden');
            document.getElementById('addModal').classList.remove('invisible', 'opacity-0');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('invisible', 'opacity-0');
        }

        function showEditModal(id, name, username, address, phone, typeId) {
            document.getElementById('editPersonnelId').value = id;
            document.getElementById('editPersonnelName').value = name;
            document.getElementById('editPersonnelUsername').value = username;
            document.getElementById('editPersonnelAddress').value = address;
            document.getElementById('editPersonnelPhone').value = phone;
            document.getElementById('editPersonnelPassword').value = '';
            document.getElementById('editPersonnelTypeId').value = typeId;
            document.getElementById('editNameError').classList.add('hidden');
            document.getElementById('editUsernameError').classList.add('hidden');
            document.getElementById('editAddressError').classList.add('hidden');
            document.getElementById('editPhoneError').classList.add('hidden');
            document.getElementById('editPasswordError').classList.add('hidden');
            document.getElementById('editTypeError').classList.add('hidden');
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
            let url = '/page/personnel/report/export_personnel_group_pdf.php?type=<?php echo urlencode($type); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/personnel/report/export_personnel_group_excel.php?type=<?php echo urlencode($type); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
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
                    } else if (field.id.includes('Phone') && input.value && !/^[0-9]{10}$/.test(input.value)) {
                        error.textContent = `${field.label}ต้องเป็นตัวเลข 10 หลัก`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (field.id.includes('Password') && input.value && input.value.length < 6) {
                        error.textContent = `${field.label}ต้องมีความยาวอย่างน้อย 6 ตัวอักษร`;
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
                        id: 'addPersonnelName',
                        errorId: 'addNameError',
                        label: 'ชื่อ-นามสกุล',
                        maxLength: 255,
                        required: true
                    },
                    {
                        id: 'addPersonnelUsername',
                        errorId: 'addUsernameError',
                        label: 'ชื่อผู้ใช้',
                        maxLength: 100,
                        required: true
                    },
                    {
                        id: 'addPersonnelAddress',
                        errorId: 'addAddressError',
                        label: 'ที่อยู่',
                        maxLength: 500,
                        required: true
                    },
                    {
                        id: 'addPersonnelPhone',
                        errorId: 'addPhoneError',
                        label: 'เบอร์โทรศัพท์',
                        maxLength: 10,
                        required: true
                    },
                    {
                        id: 'addPersonnelPassword',
                        errorId: 'addPasswordError',
                        label: 'รหัสผ่าน',
                        maxLength: 255,
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
                        id: 'editPersonnelName',
                        errorId: 'editNameError',
                        label: 'ชื่อ-นามสกุล',
                        maxLength: 255,
                        required: true
                    },
                    {
                        id: 'editPersonnelUsername',
                        errorId: 'editUsernameError',
                        label: 'ชื่อผู้ใช้',
                        maxLength: 100,
                        required: true
                    },
                    {
                        id: 'editPersonnelAddress',
                        errorId: 'editAddressError',
                        label: 'ที่อยู่',
                        maxLength: 500,
                        required: true
                    },
                    {
                        id: 'editPersonnelPhone',
                        errorId: 'editPhoneError',
                        label: 'เบอร์โทรศัพท์',
                        maxLength: 10,
                        required: true
                    },
                    {
                        id: 'editPersonnelPassword',
                        errorId: 'editPasswordError',
                        label: 'รหัสผ่าน',
                        maxLength: 255,
                        required: false
                    },
                    {
                        id: 'editPersonnelTypeId',
                        errorId: 'editTypeError',
                        label: 'ประเภทบุคลากร',
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
                urlParams.set('page', 'personnel_group');
                urlParams.set('type', '<?php echo urlencode($type); ?>');

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