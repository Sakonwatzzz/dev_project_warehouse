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

// Database Functions
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

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validatePersonnelData($name, $username, $type_id, $address, $phone, $password, $pdo, $exclude_id = null, $is_edit = false)
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
    if (!$is_edit && empty($password)) {
        $errors[] = 'รหัสผ่านต้องไม่ว่าง';
    } elseif ($password && (strlen($password) < 6 || strlen($password) > 100)) {
        $errors[] = 'รหัสผ่านต้องมีความยาว 6-100 ตัวอักษร';
    } elseif ($password && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{6,100}$/', $password)) {
        $errors[] = 'รหัสผ่านต้องมีตัวอักษรพิมพ์เล็ก พิมพ์ใหญ่ ตัวเลข และอักขระพิเศษอย่างน้อย 1 ตัว';
    }
    return $errors;
}

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

// Handle Requests
$type = isset($_GET['type']) ? urldecode(trim($_GET['type'])) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=Invalid CSRF token');
        exit;
    }

    if ($_POST['action'] === 'add') {
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
            $stmt->execute([$name, $username, $type_id, $address, $phone, password_hash($password, PASSWORD_DEFAULT)]);
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

    if ($_POST['action'] === 'edit') {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        $name = trim($_POST['update_personnel_name']);
        $username = trim($_POST['update_personnel_username']);
        $type_id = filter_var($_POST['update_personnel_type_id'], FILTER_VALIDATE_INT);
        $address = trim($_POST['update_personnel_address']);
        $phone = trim($_POST['update_personnel_phone']);
        $password = trim($_POST['update_personnel_password']);

        $errors = validatePersonnelData($name, $username, $type_id, $address, $phone, $password, $pdo, $id, true);
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
            if ($password !== '') {
                $query .= ", personnel_password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
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
            header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
            exit;
        }
    }

    if ($_POST['action'] === 'delete') {
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
}

// Fetch personnel types for dropdown
try {
    $personnel_types = fetchData($pdo, "SELECT personnel_type_id, personnel_type_name FROM personnel_type ORDER BY personnel_type_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=personnel_group&type=' . urlencode($type) . '&error=' . urlencode($e->getMessage()));
    exit;
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

$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กลุ่มบุคลากร: <?php echo htmlspecialchars($type); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link href="../../output.css" rel="stylesheet">
    <link href="../../style/page_list.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-white min-h-screen font-sans text-base">
    <div class="container mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">กลุ่มบุคลากร: <?php echo htmlspecialchars($type); ?></h1>
            <p class="text-gray-600">ข้อมูลบุคลากรทั้งหมดในประเภท <?php echo htmlspecialchars($type); ?></p>
        </div>

        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span id="totalRecords" class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fas fa-users mr-2"></i> <?php echo number_format($total_records); ?> รายการ
                </span>
                <button onclick="showAddModal()" class="btn-primary text-white py-2 px-4 rounded-lg font-medium hover-lift w-full md:w-auto">
                    <i class="fas fa-plus mr-2"></i> เพิ่มบุคลากร
                </button>
            </div>
            <form id="searchForm" action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="personnel_group">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="hidden" name="page_num" value="1">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหาบุคลากร..." value="<?php echo htmlspecialchars($search); ?>"
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
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
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
        <div id="personnelTable" class="glass-effect rounded-xl p-6 card-shadow animate-fade-in">
            <!-- ตารางจะถูกโหลดผ่าน AJAX -->
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="modal hidden">
            <div class="modal-content">
                <div class="mb-5">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>เพิ่มบุคลากรใหม่
                    </h3>
                    
                    <p class="text-sm text-gray-500">กรุณากรอกข้อมูลบุคลากร</p>
                </div>
                <form id="addForm" method="POST" class="space-y-4" autocomplete="off">
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
                            required aria-describedby="addNameError" autocomplete="off">
                        <span id="addNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                        <input type="text" id="addPersonnelUsername" name="add_personnel_username"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addUsernameError" autocomplete="off">
                        <span id="addUsernameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                        <textarea id="addPersonnelAddress" name="add_personnel_address"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addAddressError" autocomplete="off"></textarea>
                        <span id="addAddressError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                        <input type="text" id="addPersonnelPhone" name="add_personnel_phone" maxlength="10"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="addPhoneError" autocomplete="off">
                        <span id="addPhoneError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                        <div class="relative">
                            <input type="password" id="addPersonnelPassword" name="add_personnel_password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addPasswordError" autocomplete="off">
                            <button type="button" id="toggleAddPassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
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
                            required hour-lift aria-describedby="editUsernameError">
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
                        <input type="text" id="editPersonnelPhone" name="update_personnel_phone" maxlength="10"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required aria-describedby="editPhoneError">
                        <span id="editPhoneError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                        <div class="relative">
                            <input type="password" id="editPersonnelPassword" name="update_personnel_password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                aria-describedby="editPasswordError" placeholder="เว้นว่างหากไม่ต้องการเปลี่ยนรหัสผ่าน">
                            <button type="button" id="toggleEditPassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
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

    <script src="/page/personnel/function/personnel_group.js"></script>
</body>
</html>