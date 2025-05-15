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
    header('Location: login.php?timeout=1');
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
        die("Database error: " . htmlspecialchars($e->getMessage()));
    }
}

// Verify CSRF token
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle Add Personnel Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_type&add_error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    $personnel_type = trim($_POST['personnel_type']);
    $errors = [];
    if (empty($personnel_type) || strlen($personnel_type) > 255) {
        $errors[] = 'ชื่อประเภทบุคลากรต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=personnel_type&add_error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO personnel_type (personnel_type_name) VALUES (?)');
        $stmt->execute([$personnel_type]);
        header('Location: index.php?page=personnel_type&add_success=1' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        error_log("Add personnel type error: " . $e->getMessage());
        header('Location: index.php?page=personnel_type&add_error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle Update Personnel Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token']) && $_POST['action'] === 'update') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_type&edit_error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $newpersonnel_type = trim($_POST['update_personnel_type']);
    $errors = [];
    if (!$id) {
        $errors[] = 'รหัสประเภทไม่ถูกต้อง';
    }
    if (empty($newpersonnel_type) || strlen($newpersonnel_type) > 255) {
        $errors[] = 'ชื่อประเภทบุคลากรต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=personnel_type&edit_error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE personnel_type SET personnel_type_name = ? WHERE personnel_type_id = ?");
        $stmt->execute([$newpersonnel_type, $id]);
        header('Location: index.php?page=personnel_type&edit_success=1' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        error_log("Update personnel type error: " . $e->getMessage());
        header('Location: index.php?page=personnel_type&edit_error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle Delete Personnel Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=personnel_type&delete_error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: index.php?page=personnel_type&delete_error=' . urlencode('Invalid personnel type ID'));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_type_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: index.php?page=personnel_type&delete_error=' . urlencode('ไม่สามารถลบได้เนื่องจากมีบุคลากรที่เกี่ยวข้อง'));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM personnel_type WHERE personnel_type_id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=personnel_type&delete_error=' . urlencode('ไม่พบข้อมูลประเภทบุคลากรที่ต้องการลบ'));
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=personnel_type&delete_success=1" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete personnel type error: ID=$id, Error=" . $e->getMessage());
        header("Location: index.php?page=personnel_type&delete_error=" . urlencode('เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Search and Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $count_query = "SELECT COUNT(*) FROM personnel_type";
    $params = [];
    if (!empty($search)) {
        $count_query .= " WHERE personnel_type_name LIKE :search OR personnel_type_id LIKE :search";
        $params[':search'] = "%$search%";
    }

    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count personnel type error: " . $e->getMessage());
    die("Count error: " . htmlspecialchars($e->getMessage()));
}

$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

try {
    $query = "
        SELECT 
            pn.personnel_type_id,
            pn.personnel_type_name,
            COALESCE(COUNT(p.personnel_id), 0) as personnel_count
        FROM personnel_type pn
        LEFT JOIN personnel p ON pn.personnel_type_id = p.personnel_type_id";

    $params = [];
    if (!empty($search)) {
        $query .= " WHERE pn.personnel_type_name LIKE :search OR pn.personnel_type_id LIKE :search";
        $params[':search'] = "%$search%";
    }

    $query .= " GROUP BY pn.personnel_type_id, pn.personnel_type_name 
        LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $personnel_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch personnel type error: " . $e->getMessage());
    die("Fetch error: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>จัดการประเภทบุคลากร | ระบบจัดการข้อมูล</title>
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

<body class="font-sans bg-gray-50 text-gray-800">
    <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">จัดการประเภทบุคลากร</h1>
            <p class="text-gray-600">ข้อมูลประเภทบุคลากรทั้งหมดในระบบ</p>
        </div>

        <!-- Search and Count Section -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6 border border-gray-100">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <span class="bg-indigo-100 text-indigo-800 font-medium px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-users mr-2"></i>
                        <span class="font-bold"><?php echo number_format($total_records); ?></span>
                        <span class="ml-1">รายการ</span>
                    </span>
                    <button onclick="showAddModal()"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition flex items-center">
                        <i class="fas fa-plus mr-2"></i>เพิ่มประเภทบุคลากร
                    </button>
                </div>

                <div class="w-full lg:w-1/2 xl:w-1/3">
                    <form action="" method="GET" id="searchForm" class="relative">
                        <input type="hidden" name="page" value="personnel_type">
                        <input type="hidden" name="page_num" value="1">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหารหัส หรือชื่อประเภทบุคลากร..."
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
            <div class="overflow-x-auto table-responsive">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">รหัสประเภท</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">ชื่อประเภท</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b">จำนวนบุคลากร</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($personnel_types) > 0): ?>
                            <?php foreach ($personnel_types as $personneltype): ?>
                                <tr data-id="<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>"
                                    class="hover:bg-indigo-50/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium"><?php echo htmlspecialchars($personneltype['personnel_type_id']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 type-name"><?php echo htmlspecialchars($personneltype['personnel_type_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="?page=personnel_group&type=<?php echo urlencode($personneltype['personnel_type_name']); ?>"
                                            class="text-indigo-700 hover:text-indigo-900 hover:underline">
                                            <?php echo number_format($personneltype['personnel_count']); ?> รายการ
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="flex justify-center items-center space-x-2">
                                            <button onclick="showEditModal('<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>', '<?php echo htmlspecialchars(addslashes($personneltype['personnel_type_name'])); ?>')"
                                                class="bg-amber-500 text-white px-3 py-1.5 rounded-md hover:bg-amber-600 transition flex items-center text-sm">
                                                <i class="fas fa-edit mr-1"></i>แก้ไข
                                            </button>
                                            <form action="" method="POST" style="display:inline;" class="delete-form">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <button type="button" onclick="confirmDelete(this.parentElement)"
                                                    class="bg-rose-500 text-white px-3 py-1.5 rounded-md hover:bg-rose-600 transition flex items-center text-sm">
                                                    <i class="fas fa-trash-alt mr-1"></i>ลบ
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center py-6">
                                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                        <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                                        <?php if (!empty($search)): ?>
                                            <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                                <a href="?page=personnel_type" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลประเภทบุคลากรในระบบ</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Card View (Mobile) -->
            <div class="card-responsive">
                <?php if (count($personnel_types) > 0): ?>
                    <?php foreach ($personnel_types as $personneltype): ?>
                        <div class="bg-white p-4 mb-4 rounded-lg shadow" data-id="<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">รหัสประเภท:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($personneltype['personnel_type_id']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm type-name">ชื่อประเภท:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($personneltype['personnel_type_name']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">จำนวนบุคลากร:</span>
                                <a href="?page=personnel_group&type=<?php echo urlencode($personneltype['personnel_type_name']); ?>"
                                    class="text-indigo-600 hover:underline text-sm">
                                    <?php echo number_format($personneltype['personnel_count']); ?> รายการ
                                </a>
                            </div>
                            <div class="flex justify-center space-x-2 mt-4">
                                <button onclick="showEditModal('<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>', '<?php echo htmlspecialchars(addslashes($personneltype['personnel_type_name'])); ?>')"
                                    class="bg-amber-500 text-white px-4 py-2 rounded-lg hover:bg-amber-600 transition flex items-center text-sm">
                                    <i class="fas fa-edit mr-1"></i>แก้ไข
                                </button>
                                <form action="" method="POST" style="display:inline;" class="delete-form">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="button" onclick="confirmDelete(this.parentElement)"
                                        class="bg-rose-500 text-white px-4 py-2 rounded-lg hover:bg-rose-600 transition flex items-center text-sm">
                                        <i class="fas fa-trash-alt mr-1"></i>ลบ
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-600 text-sm">ไม่มีข้อมูล</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6">
                <nav class="inline-flex rounded-md shadow">
                    <?php if ($page > 1): ?>
                        <a href="?page=personnel_type&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
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
                            <a href="?page=personnel_type&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
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
                            <a href="?page=personnel_type&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
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
                            <a href="?page=personnel_type&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <?= $total_pages ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <span class="relative md:hidden inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        หน้า <?= $page ?>/<?= $total_pages ?>
                    </span>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=personnel_type&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
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
                    <i class="fas fa-plus-circle text-indigo-600 mr-2"></i>เพิ่มประเภทบุคลากรใหม่
                </h3>
                <p class="text-sm text-gray-500">กรุณากรอกข้อมูลประเภทบุคลากร</p>
            </div>

            <form id="addForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div>
                    <label for="addPersonnelType" class="block text-sm font-medium text-gray-700">ชื่อประเภทบุคลากร</label>
                    <input type="text" id="addPersonnelType" name="personnel_type"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    <span id="addNameError" class="text-rose-500 text-xs mt-1 hidden">กรุณากรอกชื่อประเภทบุคลากร</span>
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
                    <i class="fas fa-edit text-indigo-600 mr-2"></i>แก้ไขข้อมูลประเภทบุคลากร
                </h3>
                <p class="text-sm text-gray-500">กรุณากรอกข้อมูลที่ต้องการแก้ไข</p>
            </div>

            <form id="editForm" method="POST" class="space-y-4">
                <input type="hidden" id="editPersonnelTypeId" name="id">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div>
                    <label for="editPersonnelType" class="block text-sm font-medium text-gray-700">ชื่อประเภทบุคลากร</label>
                    <input type="text" id="editPersonnelType" name="update_personnel_type"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    <span id="nameError" class="text-rose-500 text-xs mt-1 hidden">กรุณากรอกชื่อประเภทบุคลากร</span>
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
            document.getElementById('addPersonnelType').value = '';
            document.getElementById('addNameError').classList.add('hidden');
            document.getElementById('addModal').classList.remove('invisible', 'opacity-0');
            document.getElementById('addModal').querySelector('.transform').classList.remove('scale-95');
            document.getElementById('addModal').querySelector('.transform').classList.add('scale-100');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('invisible', 'opacity-0');
            document.getElementById('addModal').querySelector('.transform').classList.remove('scale-100');
            document.getElementById('addModal').querySelector('.transform').classList.add('scale-95');
        }

        function showEditModal(id, name) {
            document.getElementById('editPersonnelTypeId').value = id;
            document.getElementById('editPersonnelType').value = name;
            document.getElementById('nameError').classList.add('hidden');
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
                const formData = new FormData(form);
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else if (response.ok) {
                        closeDeleteModal();
                        showNotification('ลบข้อมูลสำเร็จ', 'bg-green-500');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        return response.text().then(text => {
                            throw new Error(text || 'Failed to delete');
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    closeDeleteModal();
                    showNotification('เกิดข้อผิดพลาดในการลบ: ' + error.message, 'bg-red-500');
                });
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

        document.getElementById('addForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const personnelType = document.getElementById('addPersonnelType').value.trim();
            const nameError = document.getElementById('addNameError');
            let hasError = false;

            if (!personnelType) {
                nameError.classList.remove('hidden');
                nameError.textContent = 'กรุณากรอกชื่อประเภทบุคลากร';
                hasError = true;
            } else if (personnelType.length > 255) {
                nameError.classList.remove('hidden');
                nameError.textContent = 'ชื่อประเภทบุคลากรยาวเกิน 255 ตัวอักษร';
                hasError = true;
            } else {
                nameError.classList.add('hidden');
            }

            if (hasError) return;

            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('personnel_type', personnelType);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                } else if (response.ok) {
                    closeAddModal();
                    showNotification('เพิ่มข้อมูลสำเร็จ', 'bg-green-500');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    throw new Error('Failed to add personnel type');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('เกิดข้อผิดพลาดในการเพิ่มข้อมูล: ' + error.message, 'bg-red-500');
            });
        });

        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const personnelTypeId = document.getElementById('editPersonnelTypeId').value;
            const newName = document.getElementById('editPersonnelType').value.trim();
            const nameError = document.getElementById('nameError');
            let hasError = false;

            if (!newName) {
                nameError.classList.remove('hidden');
                nameError.textContent = 'กรุณากรอกชื่อประเภทบุคลากร';
                hasError = true;
            } else if (newName.length > 255) {
                nameError.classList.remove('hidden');
                nameError.textContent = 'ชื่อประเภทบุคลากรยาวเกิน 255 ตัวอักษร';
                hasError = true;
            } else {
                nameError.classList.add('hidden');
            }

            if (hasError) return;

            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('id', personnelTypeId);
            formData.append('update_personnel_type', newName);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                } else if (response.ok) {
                    const row = document.querySelector(`tr[data-id="${personnelTypeId}"]`);
                    const card = document.querySelector(`div[data-id="${personnelTypeId}"]`);
                    if (row) row.querySelector('.type-name').textContent = newName;
                    if (card) card.querySelector('.type-name + span').textContent = newName;
                    closeEditModal();
                    showNotification('แก้ไขสำเร็จ', 'bg-green-500');
                } else {
                    throw new Error('Failed to save');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('เกิดข้อผิดพลาดในการบันทึก: ' + error.message, 'bg-red-500');
            });
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

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('searchInput').focus();

            if (urlParams.has('search')) {
                searchInput.focus();
            }

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
            }
        });
    </script>
</body>

</html>