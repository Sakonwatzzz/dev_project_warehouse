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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'personnel_list'] + ($_GET ?? []));
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
function validatePersonnelData($name, $username, $type_id, $pdo, $exclude_id = null)
{
    $errors = [];
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อบุคลากรต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (empty($username) || strlen($username) > 100) {
        $errors[] = 'ชื่อผู้ใช้ต้องไม่ว่างและมีความยาวไม่เกิน 100 ตัวอักษร';
    } else {
        // Check username uniqueness
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
    return $errors;
}

// Handle search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle POST requests for adding personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_list&error=Invalid CSRF token');
        exit;
    }

    $name = trim($_POST['add_personnel_name']);
    $username = trim($_POST['add_personnel_username']);
    $type_id = filter_var($_POST['personnel_type_id'], FILTER_VALIDATE_INT);

    $errors = validatePersonnelData($name, $username, $type_id, $pdo);

    if (!empty($errors)) {
        header('Location: index.php?page=personnel_list&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO personnel (personnel_name, personnel_username, personnel_type_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $username, $type_id]);
        $pdo->commit();
        header('Location: index.php?page=personnel_list&success=เพิ่มข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add personnel error: " . $e->getMessage());
        header('Location: index.php?page=personnel_list&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle POST requests for updating personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_list&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $name = trim($_POST['update_personnel_name']);
    $username = trim($_POST['update_personnel_username']);
    $type_id = filter_var($_POST['update_personnel_type_id'], FILTER_VALIDATE_INT);

    $errors = validatePersonnelData($name, $username, $type_id, $pdo, $id);
    if (!$id) {
        $errors[] = 'รหัสบุคลากรไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=personnel_list&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE personnel SET personnel_name = ?, personnel_username = ?, personnel_type_id = ? WHERE personnel_id = ?");
        $stmt->execute([$name, $username, $type_id, $id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=personnel_list&error=ไม่พบข้อมูลบุคลากรที่ต้องการแก้ไข');
            exit;
        }
        $pdo->commit();
        header('Location: index.php?page=personnel_list&success=แก้ไขข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update personnel error: " . $e->getMessage());
        header('Location: index.php?page=personnel_list&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_list&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: index.php?page=personnel_list&error=Invalid personnel ID');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM personnel WHERE personnel_id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=personnel_list&error=ไม่พบข้อมูลบุคลากรที่ต้องการลบ');
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=personnel_list&success=ลบข้อมูลสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete personnel error: Personnel ID=$id, Error=" . $e->getMessage());
        header("Location: index.php?page=personnel_list&error=" . urlencode('เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total_count FROM personnel";
    $params = [];
    if (!empty($search)) {
        $count_query .= " WHERE personnel_id LIKE :search OR personnel_name LIKE :search OR personnel_username LIKE :search";
        $params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count personnel error: " . $e->getMessage());
    header('Location: index.php?page=personnel_list&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
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
    header('Location: index.php?page=personnel_list&error=' . urlencode($e->getMessage()));
    exit;
}

// Fetch personnel data
try {
    $query = "
        SELECT 
            p.personnel_id,
            p.personnel_name,
            p.personnel_username,
            p.personnel_type_id,
            pt.personnel_type_name
        FROM personnel p
        INNER JOIN personnel_type pt ON p.personnel_type_id = pt.personnel_type_id";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE p.personnel_id LIKE :search OR p.personnel_name LIKE :search OR p.personnel_username LIKE :search";
        $params[':search'] = "%$search%";
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
    header('Location: index.php?page=personnel_list&error=' . urlencode($e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการบุคลากร</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-6">
                <h2 class="text-2xl font-bold text-gray-800">จำนวนบุคลากร:</h2>
                <div class="px-4 py-1 bg-white border border-gray-300 rounded shadow text-center min-w-[50px]">
                    <?php echo $total_records; ?>
                </div>
                <button onclick="showAddModal()"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition flex items-center">
                    <i class="fas fa-plus mr-2"></i>เพิ่มบุคลากร
                </button>
            </div>
            <div class="text-end">
                <form action="" method="GET" id="searchForm">
                    <input type="hidden" name="page" value="personnel_list">
                    <input type="hidden" name="page_num" value="1">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหาบุคลากร..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            oninput="checkEmptySearch(this)">
                        <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
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
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-users mr-2"></i>รายการบุคลากร
                </h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัสบุคลากร</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อ-นามสกุล</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อผู้ใช้</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ประเภทบุคลากร</th>
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
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($person['personnel_type_name']); ?></td>
                                        <td class="px-6 py-2 border text-center">
                                            <div class="flex flex-wrap justify-center gap-2">
                                                <button onclick="showEditModal('<?php echo htmlspecialchars($person['personnel_id']); ?>', '<?php echo htmlspecialchars(addslashes($person['personnel_name'])); ?>', '<?php echo htmlspecialchars($person['personnel_username']); ?>', '<?php echo htmlspecialchars($person['personnel_type_id']); ?>')"
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
                                    <td colspan="5" class="text-center py-4 text-gray-600">ไม่มีข้อมูลบุคลากร</td>
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
                    <a href="?page=personnel_list&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=personnel_list&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=personnel_list&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=personnel_list&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=personnel_list&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">เพิ่มบุคลากรใหม่</h3>
                <form id="addForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
                        <label class="block text-sm font-medium text-gray-700">ประเภทบุคลากร</label>
                        <select id="addPersonnelTypeId" name="personnel_type_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกประเภทบุคลากร</option>
                            <?php foreach ($personnel_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['personnel_type_id']); ?>">
                                    <?php echo htmlspecialchars($type['personnel_type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                        <label class="block text-sm font-medium text-gray-700">ประเภทบุคลากร</label>
                        <select id="editPersonnelTypeId" name="update_personnel_type_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกประเภทบุคลากร</option>
                            <?php foreach ($personnel_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['personnel_type_id']); ?>">
                                    <?php echo htmlspecialchars($type['personnel_type_name']); ?>
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
                window.location.href = '?page=personnel_list';
            }
        }

        function showAddModal() {
            document.getElementById('addPersonnelName').value = '';
            document.getElementById('addPersonnelUsername').value = '';
            document.getElementById('addPersonnelTypeId').value = '';
            document.getElementById('addNameError').classList.add('hidden');
            document.getElementById('addUsernameError').classList.add('hidden');
            document.getElementById('addTypeError').classList.add('hidden');
            document.getElementById('addModal').classList.remove('invisible', 'opacity-0');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('invisible', 'opacity-0');
        }

        function showEditModal(id, name, username, typeId) {
            document.getElementById('editPersonnelId').value = id;
            document.getElementById('editPersonnelName').value = name;
            document.getElementById('editPersonnelUsername').value = username;
            document.getElementById('editPersonnelTypeId').value = typeId;
            document.getElementById('editNameError').classList.add('hidden');
            document.getElementById('editUsernameError').classList.add('hidden');
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
            }, 5000); // Increased to 5 seconds
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return; // ป้องกัน error ถ้า element ไม่มีจริง
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

            // Simplified client-side validation
            function validateForm(formId, fields) {
                let hasError = false;
                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const error = document.getElementById(field.errorId);
                    if (!input.value.trim()) {
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
                        id: 'addPersonnelName',
                        errorId: 'addNameError',
                        label: 'ชื่อ-นามสกุล',
                        maxLength: 255
                    },
                    {
                        id: 'addPersonnelUsername',
                        errorId: 'addUsernameError',
                        label: 'ชื่อผู้ใช้',
                        maxLength: 100
                    },
                    {
                        id: 'addPersonnelTypeId',
                        errorId: 'addTypeError',
                        label: 'ประเภทบุคลากร',
                        maxLength: Infinity
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
                        maxLength: 255
                    },
                    {
                        id: 'editPersonnelUsername',
                        errorId: 'editUsernameError',
                        label: 'ชื่อผู้ใช้',
                        maxLength: 100
                    },
                    {
                        id: 'editPersonnelTypeId',
                        errorId: 'editTypeError',
                        label: 'ประเภทบุคลากร',
                        maxLength: Infinity
                    }
                ];
                if (validateForm('editForm', fields)) {
                    this.submit();
                }
            });
        });
    </script>
</body>

</html>