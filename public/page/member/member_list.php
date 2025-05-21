<?php
ob_start();
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
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Function to fetch data from database
function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        die("Database error: " . htmlspecialchars($e->getMessage()));
    }
}

// Verify CSRF token
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Add Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=member_list&add_error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    $name = trim($_POST['add_member_name']);
    $address = trim($_POST['add_member_address']);
    $phone = trim($_POST['add_member_phone'] ?? '');
    $member_type_id = filter_var($_POST['member_type_id'], FILTER_VALIDATE_INT);
    $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);

    // Validation
    $errors = [];
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อสมาชิกต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (empty($address) || strlen($address) > 500) {
        $errors[] = 'ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร';
    }
    if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว';
    }
    if (!$member_type_id) {
        $errors[] = 'กรุณาเลือกประเภทสมาชิก';
    }
    if (!$personnel_id) {
        $errors[] = 'กรุณาเลือกพนักงาน';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=member_list&add_error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO member (member_name, member_address, member_phone, member_type_id, personnel_id, member_register_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $address, $phone, $member_type_id, $personnel_id]);
        header('Location: index.php?page=member_list&add_success=1');
        exit;
    } catch (PDOException $e) {
        error_log("Add member error: " . $e->getMessage());
        header('Location: index.php?page=member_list&add_error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Update Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=member_list&edit_error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $name = trim($_POST['update_member_name']);
    $address = trim($_POST['update_member_address']);
    $phone = trim($_POST['update_member_phone'] ?? '');
    $member_type_id = filter_var($_POST['update_member_type_id'], FILTER_VALIDATE_INT);
    $personnel_id = filter_var($_POST['update_personnel_id'], FILTER_VALIDATE_INT);

    // Validation
    $errors = [];
    if (!$id) {
        $errors[] = 'Invalid member ID';
    }
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อสมาชิกต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (empty($address) || strlen($address) > 500) {
        $errors[] = 'ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร';
    }
    if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว';
    }
    if (!$member_type_id) {
        $errors[] = 'กรุณาเลือกประเภทสมาชิก';
    }
    if (!$personnel_id) {
        $errors[] = 'กรุณาเลือกพนักงาน';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=member_list&edit_error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE member SET member_name = ?, member_address = ?, member_phone = ?, member_type_id = ?, personnel_id = ? WHERE member_id = ?");
        $stmt->execute([$name, $address, $phone, $member_type_id, $personnel_id, $id]);
        header('Location: index.php?page=member_list&edit_success=1');
        exit;
    } catch (PDOException $e) {
        error_log("Update member error: " . $e->getMessage());
        header('Location: index.php?page=member_list&edit_error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Delete Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=member_list&delete_error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: index.php?page=member_list&delete_error=' . urlencode('Invalid member ID'));
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Check for foreign key constraints (e.g., orders table, if applicable)
        /*
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE member_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: index.php?page=member_list&delete_error=' . urlencode('ไม่สามารถลบได้เนื่องจากมีข้อมูลที่เกี่ยวข้อง'));
            exit;
        }
        */

        $stmt = $pdo->prepare("DELETE FROM member WHERE member_id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=member_list&delete_error=' . urlencode('ไม่พบข้อมูลสมาชิกที่ต้องการลบ'));
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=member_list&delete_success=1");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete member error: Member ID=$id, Error=" . $e->getMessage());
        header("Location: index.php?page=member_list&delete_error=" . urlencode('เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle search and date parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Total member count
try {
    $count_query = "SELECT COUNT(*) as total_count FROM member";
    $params = [];
    if (!empty($search) || $start_date || $end_date) {
        $count_query .= " WHERE 1=1";
        if (!empty($search)) {
            $count_query .= " AND (member_id LIKE :search OR member_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $count_query .= " AND member_register_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $count_query .= " AND member_register_date <= :end_date";
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
    error_log("Count member error: " . $e->getMessage());
    header('Location: index.php?page=member_list&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch member types
try {
    $member_types = fetchData($pdo, "SELECT member_type_id, member_type_name FROM member_type")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch member types error: " . $e->getMessage());
    header('Location: index.php?page=member_list&error=' . urlencode('Member types fetch error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Fetch personnel
try {
    $personnels = fetchData($pdo, "SELECT personnel_id, personnel_name FROM personnel")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch personnel error: " . $e->getMessage());
    header('Location: index.php?page=member_list&error=' . urlencode('Personnel fetch error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Fetch members
try {
    $query = "
        SELECT 
            m.member_id,
            m.member_name,
            m.member_address,
            m.member_phone,
            m.member_register_date,
            m.member_type_id,
            mt.member_type_name,
            m.personnel_id,
            per.personnel_name
        FROM member m
        INNER JOIN member_type mt ON m.member_type_id = mt.member_type_id
        INNER JOIN personnel per ON m.personnel_id = per.personnel_id";
    $params = [];
    if (!empty($search) || $start_date || $end_date) {
        $query .= " WHERE 1=1";
        if (!empty($search)) {
            $query .= " AND (m.member_id LIKE :search OR m.member_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $query .= " AND m.member_register_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND m.member_register_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }
    $query .= " ORDER BY m.member_id DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch member error: " . $e->getMessage());
    header('Location: index.php?page=member_list&error=' . urlencode('Fetch error: ' . htmlspecialchars($e->getMessage())));
    exit;
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>รายการสมาชิก | ระบบจัดการข้อมูล</title>
</head>

<body class="font-sans bg-gray-50 text-gray-800">
    <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">รายการสมาชิก</h1>
            <p class="text-gray-600">ข้อมูลสมาชิกทั้งหมดในระบบ</p>
        </div>

        <!-- Search and Count Section -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6 border border-gray-100">
            <div class="flex items-center space-x-4 mb-4">
                <span class="bg-indigo-100 text-indigo-800 font-medium px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-users mr-2"></i>
                    <span class="font-bold"><?php echo number_format($total_records); ?></span>
                    <span class="ml-1">รายการ</span>
                </span>
                <button onclick="showAddModal()"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition flex items-center">
                    <i class="fas fa-plus mr-2"></i>เพิ่มสมาชิก
                </button>
            </div>
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Date Range Selection and Export Buttons -->
                <div class="flex flex-col md:flex-row md:items-center md:space-x-4 space-y-2 md:space-y-0">
                    <div class="flex items-center space-x-2">
                        <label for="start_date" class="text-sm font-medium text-gray-700">วันที่เริ่มต้น</label>
                        <input type="date" id="start_date" name="start_date"
                            class="mt-1 block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                            value="<?php echo htmlspecialchars($start_date); ?>">
                        <label for="end_date" class="text-sm font-medium text-gray-700">ถึง</label>
                        <input type="date" id="end_date" name="end_date"
                            class="mt-1 block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                            value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="exportPDF()"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center text-sm md:text-base">
                            <i class="fas fa-file-pdf mr-2"></i> ส่งออก PDF
                        </button>
                        <button onclick="exportExcel()"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center text-sm md:text-base">
                            <i class="fas fa-file-excel mr-2"></i> ส่งออก Excel
                        </button>
                    </div>
                </div>
                <!-- Search Form -->
                <div class="w-full lg:w-1/2 xl:w-1/3">
                    <form action="" method="GET" id="searchForm" class="relative">
                        <input type="hidden" name="page" value="member_list">
                        <input type="hidden" name="page_num" value="1">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหารหัส หรือชื่อสมาชิก..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-full px-4 py-3 pr-10 rounded-lg border border-gray-200 focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition-all">
                        <?php if (!empty($search)): ?>
                            <button type="button" onclick="clearSearch()"
                                class="absolute right-10 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                        <button type="submit"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-indigo-600">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">
                                รหัสสมาชิก
                            </th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">
                                ชื่อ
                            </th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">
                                ที่อยู่
                            </th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">
                                เบอร์โทรศัพท์
                            </th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">
                                วันที่สมัคร
                            </th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">
                                ประเภทสมาชิก
                            </th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">
                                พนักงาน
                            </th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b text-center">
                                จัดการ
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $member): ?>
                                <tr data-id="<?php echo htmlspecialchars($member['member_id']); ?>"
                                    class="hover:bg-indigo-50/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium"><?php echo htmlspecialchars($member['member_id']); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="?page=member_detail&tabel=member&id=<?php echo htmlspecialchars($member['member_id']); ?>"
                                            class="text-indigo-700 hover:text-indigo-900 font-medium hover:underline member-name">
                                            <?php echo htmlspecialchars($member['member_name']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 member-address">
                                        <div class="max-w-xs truncate">
                                            <?php echo htmlspecialchars($member['member_address']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($member['member_phone'])): ?>
                                            <p><?php echo htmlspecialchars($member['member_phone']); ?></p>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $date = new DateTime($member['member_register_date']);
                                        echo $date->format('d/m/Y H:i:s');
                                        ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center bg-blue-50 text-blue-800 px-2 py-1 rounded-md text-xs">
                                            <?php echo htmlspecialchars($member['member_type_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center bg-blue-50 text-blue-800 px-2 py-1 rounded-md text-xs">
                                            <i class="fas fa-user-tie mr-1"></i>
                                            <?php echo htmlspecialchars($member['personnel_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="flex justify-center items-center space-x-2">
                                            <button onclick="showEditModal('<?php echo htmlspecialchars($member['member_id']); ?>', '<?php echo htmlspecialchars(addslashes($member['member_name'])); ?>', '<?php echo htmlspecialchars(addslashes($member['member_address'])); ?>', '<?php echo htmlspecialchars($member['member_phone']); ?>', '<?php echo htmlspecialchars($member['member_type_id']); ?>', '<?php echo htmlspecialchars($member['personnel_id']); ?>')"
                                                class="bg-amber-500 text-white px-3 py-1.5 rounded-md hover:bg-amber-600 transition flex items-center text-sm">
                                                <i class="fas fa-edit mr-1"></i>แก้ไข
                                            </button>
                                            <form action="" method="POST" style="display:inline;" class="delete-form">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($member['member_id']); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <button type="button" onclick="confirmDelete(this.parentElement)"
                                                    class="bg-rose-500 text-white px-3 py-1.5 rounded-md hover:bg-rose-600 transition">
                                                    <i class="fas fa-trash-alt mr-1"></i>ลบ
                                                </button>
                                            </form>
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
                                                <a href="?page=member_list" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลสมาชิกในระบบ</p>
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
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6">
                <nav class="inline-flex rounded-md shadow">
                    <?php if ($page > 1): ?>
                        <a href="?page=member_list&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                            class="relative inline-flex items-center px-4 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-chevron-left mr-1"></i> ก่อนหน้า
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center px-4 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                            <i class="fas fa-chevron-left mr-1"></i> ก่อนหน้า
                        </span>
                    <?php endif; ?>

                    <div class="hidden md:flex">
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1): ?>
                            <a href="?page=member_list&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                1
                            </a>
                            <?php if ($start_page > 2): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                    ...
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=member_list&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i == $page ? 'text-indigo-700 z-10 border-indigo-500' : 'text-gray-700 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                    ...
                                </span>
                            <?php endif; ?>
                            <a href="?page=member_list&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <?= $total_pages ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <span class="relative md:hidden inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        หน้า <?= $page ?>/<?= $total_pages ?>
                    </span>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=member_list&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                            class="relative inline-flex items-center px-4 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            ถัดไป <i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center px-4 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                            ถัดไป <i class="fas fa-chevron-right ml-1"></i>
                        </span>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Modal -->
    <div id="addModal"
        class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
        <div class="bg-white rounded-lg w-full max-w-md p-6 relative transform scale-95 transition-all duration-300">
            <div class="absolute top-0 right-0 pt-4 pr-4">
                <button type="button" onclick="closeAddModal()"
                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mb-5">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-plus-circle text-indigo-600 mr-2"></i>เพิ่มสมาชิกใหม่
                </h3>
                <p class="text-sm text-gray-500">กรุณากรอกข้อมูลสมาชิก</p>
            </div>

            <form id="addForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div>
                    <label for="addMemberName" class="block text-sm font-medium text-gray-700">ชื่อสมาชิก</label>
                    <input type="text" id="addMemberName" name="add_member_name"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    <span id="addNameError" class="text-rose-500 text-xs mt-1 hidden">กรุณากรอกชื่อสมาชิก</span>
                </div>

                <div>
                    <label for="addMemberAddress" class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                    <textarea id="addMemberAddress" name="add_member_address"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 resize-none" rows="3" required></textarea>
                    <span id="addAddressError" class="text-rose-500 text-xs mt-1 hidden">กรุณากรอกที่อยู่</span>
                </div>

                <div>
                    <label for="addMemberPhone" class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                    <input type="text" id="addMemberPhone" name="add_member_phone" maxlength="10" pattern="[0-9]{10}"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <span id="addPhoneError" class="text-rose-500 text-xs mt-1 hidden">เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว</span>
                </div>

                <div>
                    <label for="addMemberTypeId" class="block text-sm font-medium text-gray-700">ประเภทสมาชิก</label>
                    <select id="addMemberTypeId" name="member_type_id"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">เลือกประเภทสมาชิก</option>
                        <?php foreach ($member_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['member_type_id']); ?>">
                                <?php echo htmlspecialchars($type['member_type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="addMemberTypeError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกประเภทสมาชิก</span>
                </div>

                <div>
                    <label for="addPersonnelId" class="block text-sm font-medium text-gray-700">พนักงาน</label>
                    <select id="addPersonnelId" name="personnel_id"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">เลือกพนักงาน</option>
                        <?php foreach ($personnels as $personnel): ?>
                            <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                                <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="addPersonnelError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกพนักงาน</span>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="closeAddModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        ยกเลิก
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal"
        class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
        <div class="bg-white rounded-lg w-full max-w-md p-6 relative transform scale-95 transition-all duration-300">
            <div class="absolute top-0 right-0 pt-4 pr-4">
                <button type="button" onclick="closeEditModal()"
                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mb-5">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-edit text-indigo-600 mr-2"></i>แก้ไขข้อมูลสมาชิก
                </h3>
                <p class="text-sm text-gray-500">กรุณากรอกข้อมูลที่ต้องการแก้ไข</p>
            </div>

            <form id="editForm" method="POST" class="space-y-4">
                <input type="hidden" id="editMemberId" name="id">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div>
                    <label for="editMemberName" class="block text-sm font-medium text-gray-700">ชื่อสมาชิก</label>
                    <input type="text" id="editMemberName" name="update_member_name"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    <span id="editNameError" class="text-rose-500 text-xs mt-1 hidden">กรุณากรอกชื่อสมาชิก</span>
                </div>

                <div>
                    <label for="editMemberAddress" class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                    <textarea id="editMemberAddress" name="update_member_address"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 resize-none" rows="3" required></textarea>
                    <span id="editAddressError" class="text-rose-500 text-xs mt-1 hidden">กรุณากรอกที่อยู่</span>
                </div>

                <div>
                    <label for="editMemberPhone" class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                    <input type="text" id="editMemberPhone" name="update_member_phone" maxlength="10" pattern="[0-9]{10}"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <span id="editPhoneError" class="text-rose-500 text-xs mt-1 hidden">เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว</span>
                </div>

                <div>
                    <label for="editMemberTypeId" class="block text-sm font-medium text-gray-700">ประเภทสมาชิก</label>
                    <select id="editMemberTypeId" name="update_member_type_id"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">เลือกประเภทสมาชิก</option>
                        <?php foreach ($member_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['member_type_id']); ?>">
                                <?php echo htmlspecialchars($type['member_type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="editMemberTypeError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกประเภทสมาชิก</span>
                </div>

                <div>
                    <label for="editPersonnelId" class="block text-sm font-medium text-gray-700">พนักงาน</label>
                    <select id="editPersonnelId" name="update_personnel_id"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">เลือกพนักงาน</option>
                        <?php foreach ($personnels as $personnel): ?>
                            <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                                <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="editPersonnelError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกพนักงาน</span>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        ยกเลิก
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal"
        class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
        <div class="bg-white rounded-lg w-full max-w-md p-6 relative transform scale-95 transition-all duration-300">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">ยืนยันการลบข้อมูล</h3>
                <p class="text-sm text-gray-500 mb-6">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้? การกระทำนี้ไม่สามารถเรียกคืนได้</p>

                <div class="flex justify-center gap-3">
                    <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        ยกเลิก
                    </button>
                    <button type="button" id="confirmDeleteBtn"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                        ยืนยันการลบ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
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

    <script>
        function showAddModal() {
            document.getElementById('addMemberName').value = '';
            document.getElementById('addMemberAddress').value = '';
            document.getElementById('addMemberPhone').value = '';
            document.getElementById('addMemberTypeId').value = '';
            document.getElementById('addPersonnelId').value = '';
            document.getElementById('addNameError').classList.add('hidden');
            document.getElementById('addAddressError').classList.add('hidden');
            document.getElementById('addPhoneError').classList.add('hidden');
            document.getElementById('addMemberTypeError').classList.add('hidden');
            document.getElementById('addPersonnelError').classList.add('hidden');
            document.getElementById('addModal').classList.remove('invisible', 'opacity-0');
            document.getElementById('addModal').querySelector('.transform').classList.remove('scale-95');
            document.getElementById('addModal').querySelector('.transform').classList.add('scale-100');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('invisible', 'opacity-0');
            document.getElementById('addModal').querySelector('.transform').classList.remove('scale-100');
            document.getElementById('addModal').querySelector('.transform').classList.add('scale-95');
        }

        function showEditModal(id, name, address, phone, memberTypeId, personnelId) {
            document.getElementById('editMemberId').value = id;
            document.getElementById('editMemberName').value = name;
            document.getElementById('editMemberAddress').value = address;
            document.getElementById('editMemberPhone').value = phone || '';
            document.getElementById('editMemberTypeId').value = memberTypeId;
            document.getElementById('editPersonnelId').value = personnelId;
            document.getElementById('editNameError').classList.add('hidden');
            document.getElementById('editAddressError').classList.add('hidden');
            document.getElementById('editPhoneError').classList.add('hidden');
            document.getElementById('editMemberTypeError').classList.add('hidden');
            document.getElementById('editPersonnelError').classList.add('hidden');
            document.getElementById('editModal').classList.remove('invisible', 'opacity-0');
            document.getElementById('editModal').querySelector('.transform').classList.remove('scale-95');
            document.getElementById('editModal').querySelector('.transform').classList.add('scale-100');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('invisible', 'opacity-0');
            document.getElementById('editModal').querySelector('.transform').classList.remove('scale-100');
            document.getElementById('editModal').querySelector('.transform').classList.add('scale-95');
        }

        function showDeleteModal() {
            document.getElementById('deleteModal').classList.remove('invisible', 'opacity-0');
            document.getElementById('deleteModal').querySelector('.transform').classList.remove('scale-95');
            document.getElementById('deleteModal').querySelector('.transform').classList.add('scale-100');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('invisible', 'opacity-0');
            document.getElementById('deleteModal').querySelector('.transform').classList.remove('scale-100');
            document.getElementById('deleteModal').querySelector('.transform').classList.add('scale-95');
        }

        function confirmDelete(form) {
            showDeleteModal();
            document.getElementById('confirmDeleteBtn').onclick = () => {
                form.submit();
            };
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

            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        function hideNotification() {
            document.getElementById('notification').classList.add('translate-x-full');
        }

        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('searchForm').submit();
        }

        function exportPDF() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/member/report/export_member_pdf.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/member/report/export_member_excel.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        document.getElementById('addForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('addMemberName').value.trim();
            const address = document.getElementById('addMemberAddress').value.trim();
            const phone = document.getElementById('addMemberPhone').value.trim();
            const memberTypeId = document.getElementById('addMemberTypeId').value;
            const personnelId = document.getElementById('addPersonnelId').value;
            const nameError = document.getElementById('addNameError');
            const addressError = document.getElementById('addAddressError');
            const phoneError = document.getElementById('addPhoneError');
            const memberTypeError = document.getElementById('addMemberTypeError');
            const personnelError = document.getElementById('addPersonnelError');

            let hasError = false;
            if (!name) {
                nameError.classList.remove('hidden');
                nameError.textContent = 'กรุณากรอกชื่อสมาชิก';
                hasError = true;
            } else if (name.length > 255) {
                nameError.classList.remove('hidden');
                nameError.textContent = 'ชื่อสมาชิกยาวเกิน 255 ตัวอักษร';
                hasError = true;
            } else {
                nameError.classList.add('hidden');
            }

            if (!address) {
                addressError.classList.remove('hidden');
                addressError.textContent = 'กรุณากรอกที่อยู่';
                hasError = true;
            } else if (address.length > 500) {
                addressError.classList.remove('hidden');
                addressError.textContent = 'ที่อยู่ยาวเกิน 500 ตัวอักษร';
                hasError = true;
            } else {
                addressError.classList.add('hidden');
            }

            if (phone && !/^[0-9]{10}$/.test(phone)) {
                phoneError.classList.remove('hidden');
                phoneError.textContent = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว';
                hasError = true;
            } else {
                phoneError.classList.add('hidden');
            }

            if (!memberTypeId) {
                memberTypeError.classList.remove('hidden');
                memberTypeError.textContent = 'กรุณาเลือกประเภทสมาชิก';
                hasError = true;
            } else {
                memberTypeError.classList.add('hidden');
            }

            if (!personnelId) {
                personnelError.classList.remove('hidden');
                personnelError.textContent = 'กรุณาเลือกพนักงาน';
                hasError = true;
            } else {
                personnelError.classList.add('hidden');
            }

            if (!hasError) {
                this.submit();
            }
        });

        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('editMemberName').value.trim();
            const address = document.getElementById('editMemberAddress').value.trim();
            const phone = document.getElementById('editMemberPhone').value.trim();
            const memberTypeId = document.getElementById('editMemberTypeId').value;
            const personnelId = document.getElementById('editPersonnelId').value;
            const nameError = document.getElementById('editNameError');
            const addressError = document.getElementById('editAddressError');
            const phoneError = document.getElementById('editPhoneError');
            const memberTypeError = document.getElementById('editMemberTypeError');
            const personnelError = document.getElementById('editPersonnelError');

            let hasError = false;
            if (!name) {
                nameError.classList.remove('hidden');
                nameError.textContent = 'กรุณากรอกชื่อสมาชิก';
                hasError = true;
            } else if (name.length > 255) {
                nameError.classList.remove('hidden');
                nameError.textContent = 'ชื่อสมาชิกยาวเกิน 255 ตัวอักษร';
                hasError = true;
            } else {
                nameError.classList.add('hidden');
            }

            if (!address) {
                addressError.classList.remove('hidden');
                addressError.textContent = 'กรุณากรอกที่อยู่';
                hasError = true;
            } else if (address.length > 500) {
                addressError.classList.remove('hidden');
                addressError.textContent = 'ที่อยู่ยาวเกิน 500 ตัวอักษร';
                hasError = true;
            } else {
                addressError.classList.add('hidden');
            }

            if (phone && !/^[0-9]{10}$/.test(phone)) {
                phoneError.classList.remove('hidden');
                phoneError.textContent = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว';
                hasError = true;
            } else {
                phoneError.classList.add('hidden');
            }

            if (!memberTypeId) {
                memberTypeError.classList.remove('hidden');
                memberTypeError.textContent = 'กรุณาเลือกประเภทสมาชิก';
                hasError = true;
            } else {
                memberTypeError.classList.add('hidden');
            }

            if (!personnelId) {
                personnelError.classList.remove('hidden');
                personnelError.textContent = 'กรุณาเลือกพนักงาน';
                hasError = true;
            } else {
                personnelError.classList.add('hidden');
            }

            if (!hasError) {
                this.submit();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closeDeleteModal();
            }
        });

        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        document.getElementById('start_date').addEventListener('change', updateDateFilter);
        document.getElementById('end_date').addEventListener('change', updateDateFilter);

        function updateDateFilter() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', 'member_list');

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

            if (urlParams.has('add_success')) {
                showNotification('เพิ่มข้อมูลสำเร็จ', 'bg-green-500');
            } else if (urlParams.has('edit_success')) {
                showNotification('แก้ไขข้อมูลสำเร็จ', 'bg-green-500');
            } else if (urlParams.has('delete_success')) {
                showNotification('ลบข้อมูลสำเร็จ', 'bg-green-500');
            } else if (urlParams.has('add_error')) {
                showNotification(decodeURIComponent(urlParams.get('add_error')), 'bg-red-500');
            } else if (urlParams.has('edit_error')) {
                showNotification(decodeURIComponent(urlParams.get('edit_error')), 'bg-red-500');
            } else if (urlParams.has('delete_error')) {
                showNotification(decodeURIComponent(urlParams.get('delete_error')), 'bg-red-500');
            } else if (urlParams.has('error')) {
                showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
            }
        });
    </script>
</body>

</html>