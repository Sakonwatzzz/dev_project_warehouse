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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
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
    if (empty($password) && !$exclude_id) {
        $errors[] = 'รหัสผ่านต้องไม่ว่าง';
    }
    if (!empty($password) && (strlen($password) < 6 || strlen($password) > 255)) {
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

// Handle search, date, and type parameters
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
    $password = trim($_POST['add_personnel_password']);

    $errors = validatePersonnelData($name, $username, $type_id, $address, $phone, $password, $pdo);

    if (!empty($errors)) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO personnel (personnel_name, personnel_username, personnel_type_id, personnel_address, personnel_phone, personnel_password, personnel_create_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $username, $type_id, $address, $phone, $personnel_password]);
        $pdo->commit();
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&success=เพิ่มข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add personnel error: " . $e->getMessage());
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
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
    $password = trim($_POST['update_personnel_password']);

    $errors = validatePersonnelData($name, $username, $type_id, $address, $phone, $password ?: 'dummy', $pdo, $id);
    if (!$id) {
        $errors[] = 'รหัสบุคลากรไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $query = "UPDATE personnel SET personnel_name = ?, personnel_username = ?, personnel_type_id = ?, personnel_address = ?, personnel_phone = ? , personnel_password = ?";
        $params = [$name, $username, $type_id, $address, $phone, $password];

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
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=รหัสบุคลากรไม่ถูกต้อง');
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
        error_log("Delete personnel error: ID=$id, Error=" . $e->getMessage());
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
    if (!empty($search)) {
        $count_query .= " AND (personnel.personnel_id LIKE :search OR personnel.personnel_name LIKE :search OR personnel.personnel_username LIKE :search OR personnel.personnel_phone LIKE :search)";
        $params[':search'] = "%$search%";
    }
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
    header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode('เกิดข้อผิดพลาดในการนับข้อมูล: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch personnel types for dropdown
try {
    $personnel_type_query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type ORDER BY personnel_type_name";
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
            p.personnel_password,
            p.personnel_address,
            p.personnel_phone,
            p.personnel_create_date,
            p.personnel_type_id,
            pt.personnel_type_name
        FROM personnel p
        INNER JOIN personnel_type pt ON p.personnel_type_id = pt.personnel_type_id
        WHERE pt.personnel_type_name = :type";
    $params = [':type' => $type];
    if (!empty($search)) {
        $query .= " AND (p.personnel_id LIKE :search OR p.personnel_name LIKE :search OR p.personnel_username LIKE :search OR p.personnel_phone LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date) {
        $query .= " AND p.personnel_create_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $query .= " AND p.personnel_create_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }
    $query .= " ORDER BY p.personnel_id DESC LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">กลุ่มบุคลากร: <?php echo htmlspecialchars($type); ?></h1>
            <p class="text-gray-600">ข้อมูลบุคลากรทั้งหมดในประเภท <?php echo htmlspecialchars($type); ?></p>
        </div>

        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fas fa-users mr-2"></i> <?php echo number_format($total_records); ?> รายการ
                </span>
                <button onclick="showAddModal()" class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition justify-center md:justify-start">
                    <i class="fas fa-plus mr-2"></i> เพิ่มบุคลากร
                </button>
            </div>
            <form action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="personnel_group">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="hidden" name="page_num" value="1">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหาบุคลากร..." value="<?php echo htmlspecialchars($search); ?>"
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
                            <th class="px-6 py-3 text-left font-semibold">รหัสบุคลากร</th>
                            <th class="px-6 py-3 text-left font-semibold">ชื่อ-นามสกุล</th>
                            <th class="px-6 py-3 text-left font-semibold">ชื่อผู้ใช้</th>
                            <th class="px-6 py-3 text-left font-semibold">เบอร์โทรศัพท์</th>
                            <th class="px-6 py-3 text-left font-semibold">วันที่และเวลา</th>
                            <th class="px-6 py-3 text-center font-semibold">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($personnel) > 0): ?>
                            <?php foreach ($personnel as $person): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($person['personnel_id']); ?></td>
                                    <td class="px-6 py-3 border-t">
                                        <a href="?page=personnel_detail&tabel=personnel&id=<?php echo htmlspecialchars($person['personnel_id']); ?>"
                                            class="text-blue-600 hover:underline cursor-pointer">
                                            <?php echo htmlspecialchars($person['personnel_name']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($person['personnel_username']); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($person['personnel_phone']); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($person['personnel_create_date']); ?></td>
                                    <td class="px-6 py-3 border-t text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="showEditModal('<?php echo htmlspecialchars($person['personnel_id']); ?>', '<?php echo htmlspecialchars(addslashes($person['personnel_name'])); ?>', '<?php echo htmlspecialchars($person['personnel_username']); ?>', '<?php echo htmlspecialchars(addslashes($person['personnel_address'])); ?>', '<?php echo htmlspecialchars($person['personnel_phone']); ?>', '<?php echo htmlspecialchars($person['personnel_password']); ?>', '<?php echo htmlspecialchars($person['personnel_type_id']); ?>')"
                                                class="flex items-center justify-center px-3 py-1 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition">
                                                <div class="hidden md:flex">
                                                    <i class="fas fa-edit mr-1"></i>
                                                </div>
                                                <p>แก้ไข</p>
                                            </button>
                                            <form action="" method="POST" class="delete-form inline-flex">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($person['personnel_id']); ?>">
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
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center py-6">
                                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                        <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                                        <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                                            <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                                <a href="?page=personnel_group&type=<?php echo urlencode($type); ?>" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลบุคลากรในกลุ่มนี้</p>
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
                <?php if (count($personnel) > 0): ?>
                    <?php foreach ($personnel as $person): ?>
                        <div class="bg-white p-4 mb-4 rounded-lg shadow" data-id="<?php echo htmlspecialchars($person['personnel_id']); ?>">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">รหัสบุคลากร:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($person['personnel_id']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ชื่อ-นามสกุล:</span>
                                <a href="?page=personnel_detail&tabel=personnel&id=<?php echo htmlspecialchars($person['personnel_id']); ?>"
                                    class="text-blue-600 hover:underline text-sm">
                                    <?php echo htmlspecialchars($person['personnel_name']); ?>
                                </a>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ชื่อผู้ใช้:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($person['personnel_username']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">เบอร์โทรศัพท์:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($person['personnel_phone']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">วันที่และเวลา:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($person['personnel_create_date']); ?></span>
                            </div>
                            <div class="flex justify-center space-x-2 mt-4">
                                <button onclick="showEditModal('<?php echo htmlspecialchars($person['personnel_id']); ?>', '<?php echo htmlspecialchars(addslashes($person['personnel_name'])); ?>', '<?php echo htmlspecialchars($person['personnel_username']); ?>', '<?php echo htmlspecialchars(addslashes($person['personnel_address'])); ?>', '<?php echo htmlspecialchars($person['personnel_phone']); ?>', '<?php echo htmlspecialchars($person['personnel_password']); ?>', '<?php echo htmlspecialchars($person['personnel_type_id']); ?>')"
                                    class="flex items-center justify-center px-3 py-1 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition">
                                    <i class="fas fa-edit mr-1"></i> แก้ไข
                                </button>
                                <form action="" method="POST" class="delete-form inline-flex">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($person['personnel_id']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="button" onclick="confirmDelete(this.parentElement)"
                                        class="flex items-center justify-center px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition">
                                        <i class="fas fa-trash-alt mr-1"></i> ลบ
                                    </button>
                                </form>
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
                                    <a href="?page=personnel_group&type=<?php echo urlencode($type); ?>" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลบุคลากรในกลุ่มนี้</p>
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
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">« ก่อนหน้า</a>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded-md transition <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition"><?= $total_pages ?></a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=personnel_group&type=<?= urlencode($type) ?>&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">ถัดไป »</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Navigation Links -->
        <!-- <div class="flex justify-center space-x-4 mt-6">
            <a href="/?page=personnel_list" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-list mr-2"></i> หน้ารายการ
            </a>
            <a href="/?page=personnel_type" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-tags mr-2"></i> หน้าประเภท
            </a>
        </div> -->

        <!-- Add Modal -->
        <div id="addModal" class="modal hidden">
            <div class="modal-content">
                <div class="mb-5">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>เพิ่มบุคลากรใหม่
                    </h3>
                    <p class="text-sm text-gray-500">กรุณากรอกข้อมูลบุคลากร</p>
                </div>
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
                            required aria-describedby="addNameError">
                        <span id="addNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                        <input type="text" id="addPersonnelUsername" name="add_personnel_username"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addUsernameError">
                        <span id="addUsernameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                        <textarea id="addPersonnelAddress" name="add_personnel_address"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addAddressError"></textarea>
                        <span id="addAddressError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                        <input type="text" id="addPersonnelPhone" name="add_personnel_phone"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addPhoneError">
                        <span id="addPhoneError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                        <input type="password" id="addPersonnelPassword" name="add_personnel_password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addPasswordError">
                        <span id="addPasswordError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ประเภทบุคลากร</label>
                        <p class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700">
                            <?php echo htmlspecialchars($type); ?>
                        </p>
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
                    <h3 class="text-xl font-bold text-gray-800">แก้ไขข้อมูลบุคลากร</h3>
                </div>
                <form id="editForm" method="POST" class="space-y-4">
                    <input type="hidden" id="editPersonnelId" name="id">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อ-นามสกุล</label>
                        <input type="text" id="editPersonnelName" name="update_personnel_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="editNameError">
                        <span id="editNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                        <input type="text" id="editPersonnelUsername" name="update_personnel_username"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="editUsernameError">
                        <span id="editUsernameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                        <textarea id="editPersonnelAddress" name="update_personnel_address"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="editAddressError"></textarea>
                        <span id="editAddressError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                        <input type="text" id="editPersonnelPhone" name="update_personnel_phone"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="editPhoneError">
                        <span id="editPhoneError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">รหัสผ่าน (เว้นว่างหากไม่ต้องการเปลี่ยน)</label>
                        <input type="password" id="editPersonnelPassword" name="update_personnel_password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-describedby="editPasswordError">
                        <span id="editPasswordError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ประเภทบุคลากร</label>
                        <select id="editPersonnelTypeId" name="update_personnel_type_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="editTypeError">
                            <option value="">เลือกประเภทบุคลากร</option>
                            <?php foreach ($personnel_types as $ptype): ?>
                                <option value="<?php echo htmlspecialchars($ptype['personnel_type_id']); ?>">
                                    <?php echo htmlspecialchars($ptype['personnel_type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="editTypeError" class="text-red-500 text-xs mt-1 hidden"></span>
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
            document.getElementById('addModal').classList.remove('hidden');
        };

        const closeAddModal = () => {
            document.getElementById('addModal').classList.add('hidden');
        };

        const showEditModal = (id, name, username, address, phone, password, typeId) => {
            document.getElementById('editPersonnelId').value = id;
            document.getElementById('editPersonnelName').value = name;
            document.getElementById('editPersonnelUsername').value = username;
            document.getElementById('editPersonnelPassword').value = password || '';
            document.getElementById('editPersonnelPhone').value = phone;
            document.getElementById('editPersonnelAddress').value = address;
            document.getElementById('editPersonnelTypeId').value = typeId;
            document.getElementById('editNameError').classList.add('hidden');
            document.getElementById('editUsernameError').classList.add('hidden');
            document.getElementById('editAddressError').classList.add('hidden');
            document.getElementById('editPhoneError').classList.add('hidden');
            document.getElementById('editPasswordError').classList.add('hidden');
            document.getElementById('editTypeError').classList.add('hidden');
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
            let url = '/page/personnel/report/export_personnel_group_pdf.php?type=<?php echo urlencode($type); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        };

        const exportExcel = () => {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/personnel/report/export_personnel_group_excel.php?type=<?php echo urlencode($type); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
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
                    required,
                    validate,
                    errorMessage
                }) => {
                    const input = document.getElementById(id);
                    const error = document.getElementById(errorId);
                    if (required && !input.value.trim()) {
                        error.textContent = `กรุณากรอก${label}`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (maxLength && input.value.length > maxLength) {
                        error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (validate && !validate(input.value)) {
                        error.textContent = errorMessage;
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
                        validate: (value) => !value || /^[0-9]{10}$/.test(value),
                        errorMessage: 'ต้องเป็นตัวเลข 10 หลัก',
                        required: true
                    },
                    {
                        id: 'addPersonnelPassword',
                        errorId: 'addPasswordError',
                        label: 'รหัสผ่าน',
                        validate: (value) => !value || value.length >= 6,
                        errorMessage: 'ต้องมีความยาวอย่างน้อย 6 ตัวอักษร',
                        required: true
                    }
                ];
                if (validateForm('addForm', fields)) {
                    e.target.submit();
                }
            });

            document.getElementById('editForm').addEventListener('submit', (e) => {
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
                        validate: (value) => !value || /^[0-9]{10}$/.test(value),
                        errorMessage: 'ต้องเป็นตัวเลข 10 หลัก',
                        required: true
                    },
                    {
                        id: 'editPersonnelPassword',
                        errorId: 'editPasswordError',
                        label: 'รหัสผ่าน',
                        validate: (value) => !value || value.length >= 6,
                        errorMessage: 'ต้องมีความยาวอย่างน้อย 6 ตัวอักษร',
                        required: false
                    },
                    {
                        id: 'editPersonnelTypeId',
                        errorId: 'editTypeError',
                        label: 'ประเภทบุคลากร',
                        required: true
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
                urlParams.set('page', 'personnel_group');
                urlParams.set('type', '<?php echo urlencode($type); ?>');
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