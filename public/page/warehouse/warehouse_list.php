<?php
ob_start();
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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'warehouse_list'] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Database Functions
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

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

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

// Handle Requests
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

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
    if ($start_date || $end_date) {
        $query .= (!empty($search) ? " AND" : " WHERE") . " 1=1";
        if ($start_date) {
            $query .= " AND w.warehouse_create_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND w.warehouse_create_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
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
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการคลังสินค้า</title>
    <link href="../../output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style/page_list.css">
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fas fa-warehouse mr-2"></i> <?php echo $total_records; ?> รายการ
                </span>
                <button onclick="showAddModal()" class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition justify-center md:justify-start">
                    <i class="fas fa-plus mr-2"></i> เพิ่มคลังสินค้า
                </button>
            </div>
            <form action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="warehouse_list">
                <input type="hidden" name="page_num" value="1">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหาคลังสินค้า..." value="<?php echo htmlspecialchars($search); ?>"
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
                            <th class="px-6 py-3 text-left font-semibold">รหัสคลัง</th>
                            <th class="px-6 py-3 text-left font-semibold">ชื่อคลังสินค้า</th>
                            <th class="px-6 py-3 text-left font-semibold">สถานะ</th>
                            <th class="px-6 py-3 text-left font-semibold">จำนวน Lot</th>
                            <th class="px-6 py-3 text-left font-semibold">วันที่และเวลา</th>
                            <th class="px-6 py-3 text-center font-semibold">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($warehouses) > 0): ?>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($warehouse['warehouse_id']); ?></td>
                                    <td class="px-6 py-3 border-t">
                                        <a href="?page=warehouse_lot&warehouse_id=<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>"
                                            class="text-blue-600"><?php echo htmlspecialchars($warehouse['warehouse_name']); ?></a>
                                    </td>

                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($warehouse['warehouse_status'] === 'active' ? 'พร้อมใช้งาน' : 'ไม่พร้อมใช้งาน'); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($warehouse['lot_count']); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($warehouse['warehouse_create_date']); ?></td>
                                    <td class="px-6 py-3 border-t text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="showEditModal('<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>', '<?php echo htmlspecialchars(addslashes($warehouse['warehouse_name'])); ?>', '<?php echo htmlspecialchars($warehouse['warehouse_status']); ?>')"
                                                class="flex items-center justify-center px-3 py-1 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition">
                                                <div class="hidden md:flex">
                                                    <i class="fas fa-edit mr-1"></i>
                                                </div>
                                                <p>แก้ไข</p>
                                            </button>
                                            <form action="" method="POST" class="delete-form inline-flex">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>">
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
                                                <a href="?page=warehouse_list" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลคลังสินค้าในระบบ</p>
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
                    <a href="?page=warehouse_list&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">« ก่อนหน้า</a>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=warehouse_list&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=warehouse_list&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded-md transition <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=warehouse_list&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition"><?= $total_pages ?></a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=warehouse_list&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">ถัดไป »</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Warehouse Cards Section -->
        <h2 class="text-2xl font-bold text-gray-800 mb-6 mt-8">คลังสินค้า</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
            <?php foreach ($warehouses as $warehouse): ?>
                <div class="bg-white rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-300 cursor-pointer shadow-md"
                    onclick="window.location.href='?page=warehouse_lot&warehouse_id=<?= htmlspecialchars($warehouse['warehouse_id']) ?>'">
                    <div class="bg-blue-100 p-4 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($warehouse['warehouse_name']) ?></h3>
                    </div>
                    <div class="p-6 flex justify-between items-center">
                        <div>
                            <p class="text-gray-600 mb-2"><i class="fas fa-box mr-2"></i>จำนวน Lot ทั้งหมด</p>
                            <p class="text-3xl font-bold text-blue-600"><?= htmlspecialchars($warehouse['lot_count']) ?></p>
                        </div>
                        <div class="text-blue-500">
                            <i class="fas fa-chevron-right text-2xl"></i>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 border-t">
                        <p class="text-sm text-gray-500"><i class="fas fa-info-circle mr-1"></i> คลิกเพื่อดู Lot ทั้งหมดในคลังนี้</p>
                    </div>
                </div>
            <?php endforeach; ?>
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
                            <option value="active">พร้อมใช้งาน</option>
                            <option value="inactive">ไม่พร้อมใช้งาน</option>
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
                            <option value="active">พร้อมใช้งาน</option>
                            <option value="inactive">ไม่พร้อมใช้งาน</option>
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
    </div>

    <script>
        const showAddModal = () => {
            document.getElementById('addWarehouseName').value = '';
            document.getElementById('addWarehouseStatus').value = '';
            ['addNameError', 'addStatusError'].forEach(id => document.getElementById(id).classList.add('hidden'));
            document.getElementById('addModal').classList.remove('hidden');
        };

        const closeAddModal = () => {
            document.getElementById('addModal').classList.add('hidden');
        };

        const showEditModal = (id, name, status) => {
            document.getElementById('editWarehouseId').value = id;
            document.getElementById('editWarehouseName').value = name;
            document.getElementById('editWarehouseStatus').value = status;
            ['editNameError', 'editStatusError'].forEach(id => document.getElementById(id).classList.add('hidden'));
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
            let url = '/page/warehouse/report/export_warehouse_pdf.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        };

        const exportExcel = () => {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/warehouse/report/export_warehouse_excel.php';
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
                fields.forEach(({ id, errorId, label, maxLength, regex, isOptional }) => {
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
                        error.textContent = `${label}ไม่ถูกต้อง`;
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
                const fields = [
                    {
                        id: 'addWarehouseName',
                        errorId: 'addNameError',
                        label: 'ชื่อคลังสินค้า',
                        maxLength: 255
                    },
                    {
                        id: 'addWarehouseStatus',
                        errorId: 'addStatusError',
                        label: 'สถานะ',
                        maxLength: Infinity
                    }
                ];
                if (validateForm('addForm', fields)) {
                    e.target.submit();
                }
            });

            document.getElementById('editForm').addEventListener('submit', (e) => {
                e.preventDefault();
                const fields = [
                    {
                        id: 'editWarehouseName',
                        errorId: 'editNameError',
                        label: 'ชื่อคลังสินค้า',
                        maxLength: 255
                    },
                    {
                        id: 'editWarehouseStatus',
                        errorId: 'editStatusError',
                        label: 'สถานะ',
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
                urlParams.set('page', 'warehouse_list');
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