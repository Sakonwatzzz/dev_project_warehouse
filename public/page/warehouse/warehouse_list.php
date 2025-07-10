<?php
// [Your existing PHP backend logic remains unchanged]
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection();

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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'warehouse_list'] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

function validateWarehouseData($name, $status, $pdo, $exclude_id = null)
{
    $errors = [];
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อคลังสินค้าต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (empty($status) || !in_array($status, ['active', 'inactive'])) {
        $errors[] = 'กรุณาเลือกสถานะคลังสินค้า';
    }
    return $errors;
}

function checkWarehouseDependencies($pdo, $warehouse_id)
{
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lot WHERE warehouse_id = ? OR warehouse_from_id = ?");
    $stmt->execute([$warehouse_id, $warehouse_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "Lot ($count รายการ)";
    }
    return $dependencies;
}
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle Requests
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=warehouse_list&error=Invalid CSRF token');
        exit;
    }

    if ($_POST['action'] === 'add') {
        $name = trim($_POST['warehouse_name']);
        $status = trim($_POST['warehouse_status']);
        $errors = validateWarehouseData($name, $status, $pdo);
        if (!empty($errors)) {
            header('Location: index.php?page=warehouse_list&error=' . urlencode(implode(', ', $errors)));
            exit;
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO warehouse (warehouse_name, warehouse_status, warehouse_create_date) VALUES (?, ?, NOW())");
            $stmt->execute([$name, $status]);
            $pdo->commit();
            header('Location: index.php?page=warehouse_list&success=เพิ่มข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Add warehouse error: " . $e->getMessage());
            header('Location: index.php?page=warehouse_list&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
            exit;
        }
    }

    if ($_POST['action'] === 'update') {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        $name = trim($_POST['update_warehouse_name']);
        $status = trim($_POST['update_warehouse_status']);
        $errors = validateWarehouseData($name, $status, $pdo, $id);
        if (!$id) {
            $errors[] = 'รหัสคลังสินค้าไม่ถูกต้อง';
        }
        if (!empty($errors)) {
            header('Location: index.php?page=warehouse_list&error=' . urlencode(implode(', ', $errors)));
            exit;
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE warehouse SET warehouse_name = ?, warehouse_status = ? WHERE warehouse_id = ?");
            $stmt->execute([$name, $status, $id]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                header('Location: index.php?page=warehouse_list&error=ไม่พบข้อมูลคลังสินค้าที่ต้องการแก้ไข');
                exit;
            }
            $pdo->commit();
            header('Location: index.php?page=warehouse_list&success=แก้ไขข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update warehouse error: " . $e->getMessage());
            header('Location: index.php?page=warehouse_list&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
            exit;
        }
    }

    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            header('Location: index.php?page=warehouse_list&error=รหัสคลังสินค้าไม่ถูกต้อง');
            exit;
        }
        try {
            $pdo->beginTransaction();
            $dependencies = checkWarehouseDependencies($pdo, $id);
            if (!empty($dependencies)) {
                $pdo->rollBack();
                $dependency_list = implode(', ', $dependencies);
                header('Location: index.php?page=warehouse_list&error=' . urlencode("ไม่สามารถลบคลังสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM warehouse WHERE warehouse_id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                header('Location: index.php?page=warehouse_list&error=ไม่พบข้อมูลคลังสินค้าที่ต้องการลบ');
                exit;
            }
            $pdo->commit();
            header("Location: index.php?page=warehouse_list&success=ลบข้อมูลสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Delete warehouse error: Warehouse ID=$id, Error=" . $e->getMessage());
            $error_message = strpos($e->getMessage(), '1451') !== false
                ? 'ไม่สามารถลบคลังสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
                : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
            header("Location: index.php?page=warehouse_list&error=" . urlencode($error_message));
            exit;
        }
    }
}

// Pagination
try {
    $count_query = "SELECT COUNT(*) as total_count FROM warehouse";
    $params = [];
    if (!empty($search)) {
        $count_query .= " WHERE warehouse_id LIKE :search OR warehouse_name LIKE :search";
        $params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count warehouse error: " . $e->getMessage());
    header('Location: index.php?page=warehouse_list&error=' . urlencode('เกิดข้อผิดพลาดในการนับข้อมูล: ' . htmlspecialchars($e->getMessage())));
    exit;
}

$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch Warehouse Data
try {
    $query = "
        SELECT 
            w.warehouse_id,
            w.warehouse_name,
            w.warehouse_status,
            COALESCE(COUNT(l.lot_id), 0) as lot_count,
            w.warehouse_create_date
        FROM warehouse w
        LEFT JOIN lot l ON w.warehouse_id = l.warehouse_id";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE w.warehouse_id LIKE :search OR w.warehouse_name LIKE :search";
        $params[':search'] = "%$search%";
    }
    $query .= " GROUP BY w.warehouse_id, w.warehouse_name, w.warehouse_status, w.warehouse_create_date";
    $query .= " ORDER BY w.warehouse_id DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=warehouse_list&error=' . urlencode($e->getMessage()));
    exit;
}

// Additional Queries for Stats Cards
$active_count = $pdo->query("SELECT COUNT(*) FROM warehouse WHERE warehouse_status = 'active'")->fetchColumn();
$inactive_count = $pdo->query("SELECT COUNT(*) FROM warehouse WHERE warehouse_status = 'inactive'")->fetchColumn();
$total_lots = $pdo->query("SELECT COUNT(*) FROM lot")->fetchColumn();

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคลังสินค้า</title>
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="../../style/warehouse_list.css">
    <link href="../../style/page_list.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
</head>

<body class="bg-gradient-to-br from-slate-50 to-blue-50">
    <div class="container mx-auto px-6 pb-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 mt-8">
            <div class="stats-card rounded-2xl p-6 shadow-lg card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">คลังทั้งหมด</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $total_records; ?></p>
                        <p class="text-sm text-green-600 flex items-center mt-1">
                            <i class="fas fa-warehouse mr-1"></i>
                            ทั้งหมด
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-warehouse text-white text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="stats-card rounded-2xl p-6 shadow-lg card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">คลังที่พร้อมใช้งาน</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $active_count; ?></p>
                        <p class="text-sm text-green-600 flex items-center mt-1">
                            <i class="fas fa-check-circle mr-1"></i>
                            <?php echo number_format(($active_count / max($total_records, 1)) * 100, 1); ?>%
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="stats-card rounded-2xl p-6 shadow-lg card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">จำนวน Lot ทั้งหมด</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $total_lots; ?></p>
                        <p class="text-sm text-blue-600 flex items-center mt-1">
                            <i class="fas fa-box mr-1"></i>
                            รวม
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-boxes text-white text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="stats-card rounded-2xl p-6 shadow-lg card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">คลังไม่พร้อมใช้งาน</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $inactive_count; ?></p>
                        <p class="text-sm text-red-600 flex items-center mt-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <?php echo number_format(($inactive_count / max($total_records, 1)) * 100, 1); ?>%
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 ">
            <div class="flex flex-col lg:flex-row items-center justify-between space-y-4 lg:space-y-0">
                <!-- Search -->
                <div class="search-container w-full lg:w-96">
                    <form action="" method="GET" class="relative">
                        <input type="hidden" name="page" value="warehouse_list">
                        <input type="hidden" name="page_num" value="1">
                        <input type="text" name="search" id="searchInput" placeholder="ค้นหาคลังสินค้า ID หรือชื่อ..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="search-glow w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-blue-500  transition-all duration-300">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </form>
                </div>
                <!-- Action Buttons -->
                <div class="flex items-center space-x-3">
                    <button onclick="exportPDF()" class="action-btn px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-300 flex items-center space-x-2 shadow-lg">
                        <i class="fas fa-file-pdf"></i>
                        <span>PDF</span>
                    </button>
                    <button onclick="exportExcel()" class="action-btn px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl hover:from-emerald-600 hover:to-emerald-700 transition-all duration-300 flex items-center space-x-2 shadow-lg">
                        <i class="fas fa-file-excel"></i>
                        <span>Excel</span>
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

        <!-- Warehouse Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

            <!-- Add New Warehouse Card -->
            <div class="warehouse-card rounded-2xl shadow-lg card-hover cursor-pointer opacity-70 hover:opacity-100 transition-opacity flex items-center justify-center">
                <button
                    onclick="showAddModal()"
                    class="p-6 flex flex-col items-center justify-center h-full min-h-[200px] focus:outline-none focus:ring-0">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center mb-4 shadow-md transition-transform transform hover:scale-105">
                        <i class="fas fa-plus text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">เพิ่มคลังใหม่</h3>
                    <p class="text-gray-600 text-center">คลิกเพื่อเพิ่มคลังสินค้าใหม่</p>
                </button>
            </div>


            <?php if (count($warehouses) > 0): ?>
                <?php foreach ($warehouses as $warehouse): ?>
                    <div class="warehouse-card rounded-2xl shadow-lg card-hover cursor-pointer hover:ring-2 hover:ring-blue-400 transition-all duration-300">
                        <a href="?page=warehouse_lot&warehouse_id=<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>" class="block focus:outline-none focus:ring-0">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-xl font-bold text-gray-800">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                    </h3>
                                    <div class="status-badge <?php echo $warehouse['warehouse_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> px-3 py-1 rounded-full text-sm font-medium">
                                        <i class="<?php echo $warehouse['warehouse_status'] === 'active' ? 'fas fa-check-circle' : 'fas fa-times-circle'; ?> mr-1"></i>
                                        <?php echo htmlspecialchars($warehouse['warehouse_status'] === 'active' ? 'เปิด' : 'ปิด'); ?>
                                    </div>
                                </div>

                                <div class="space-y-3 mb-6">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">จำนวน Lot</span>
                                        <span class="text-2xl font-bold text-blue-600"><?php echo htmlspecialchars($warehouse['lot_count']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">วันที่สร้าง</span>
                                        <span class="text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($warehouse['warehouse_create_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                        <div class="flex items-center justify-between p-4">
                            <div class="flex space-x-2">
                                <button onclick="event.stopPropagation(); showEditModal('<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>', '<?php echo htmlspecialchars(addslashes($warehouse['warehouse_name'])); ?>', '<?php echo htmlspecialchars($warehouse['warehouse_status']); ?>')"
                                    class="action-btn px-4 py-2 text-white bg-yellow-500 hover:bg-yellow-600 focus:outline-none focus:ring-0 transition-all duration-300">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <form action="" method="POST" class="delete-form inline-flex">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="button" onclick="event.stopPropagation(); confirmDelete(this.parentElement)"
                                        class="action-btn px-4 py-2 text-white bg-red-500 hover:bg-red-700 transition-all duration-300">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center text-gray-500">
                    <div class="flex flex-col items-center justify-center py-6">
                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                        <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                        <?php if (!empty($search)): ?>
                            <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                <a href="?page=warehouse_list" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                            </p>
                        <?php else: ?>
                            <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลคลังสินค้าในระบบ</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center space-x-2 mt-12">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=warehouse_list&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-white text-gray-600 rounded-lg hover:bg-gray-50 transition-colors shadow-md">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=warehouse_list&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-white text-gray-600 rounded-lg hover:bg-gray-50 transition-colors shadow-md">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=warehouse_list&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 rounded-lg shadow-md <?php echo $i == $page ? 'bg-gradient-to-r from-blue-500 to-purple-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'; ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=warehouse_list&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-white text-gray-600 rounded-lg hover:bg-gray-50 transition-colors shadow-md"><?= $total_pages ?></a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=warehouse_list&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-4 py-2 bg-white text-gray-600 rounded-lg hover:bg-gray-50 transition-colors shadow-md">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="modal hidden">
        <div class="modal-content">
            <div class="mb-5">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-plus-circle text-blue-600 mr-2"></i>เพิ่มคลังสินค้าใหม่
                </h3>
                <p class="text-sm text-gray-500">กรุณากรอกข้อมูลคลังสินค้า</p>
            </div>
            <form id="addForm" method="POST" class="space-y-4" autocomplete="off">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่อคลังสินค้า</label>
                    <input type="text" id="addWarehouseName" name="warehouse_name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required aria-describedby="addNameError" autocomplete="off">
                    <span id="addNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">สถานะ</label>
                    <select id="addWarehouseStatus" name="warehouse_status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required aria-describedby="addStatusError">
                        <option value="">เลือกสถานะ</option>
                        <option value="active">เปิด</option>
                        <option value="inactive">ปิด</option>
                    </select>
                    <span id="addStatusError" class="text-red-500 text-xs mt-1 hidden"></span>
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
                <h3 class="text-xl font-bold text-gray-800">แก้ไขข้อมูลคลังสินค้า</h3>
            </div>
            <form id="editForm" method="POST" class="space-y-4">
                <input type="hidden" id="editWarehouseId" name="id">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่อคลังสินค้า</label>
                    <input type="text" id="editWarehouseName" name="update_warehouse_name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required aria-describedby="editNameError">
                    <span id="editNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">สถานะ</label>
                    <select id="editWarehouseStatus" name="update_warehouse_status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required aria-describedby="editStatusError">
                        <option value="active">เปิด</option>
                        <option value="inactive">ปิด</option>
                    </select>
                    <span id="editStatusError" class="text-red-500 text-xs mt-1 hidden"></span>
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

    <script src="/page/warehouse/function/warehouse_list.js"></script>
</body>

</html>