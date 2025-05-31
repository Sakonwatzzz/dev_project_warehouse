<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once __DIR__ . '../../../fpdf186/fpdf.php';
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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'product_type'] + ($_GET ?? []));
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

// Validate product type data
function validateProductTypeData($typename, $personnel_id, $pdo, $exclude_id = null)
{
    $errors = [];
    if (empty($typename) || strlen($typename) > 255) {
        $errors[] = 'ชื่อประเภทสินค้าต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    } else {
        // Check typename uniqueness
        $query = "SELECT COUNT(*) FROM product_type WHERE product_type_name = :typename";
        if ($exclude_id) {
            $query .= " AND product_type_id != :exclude_id";
        }
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':typename', $typename);
        if ($exclude_id) {
            $stmt->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'ชื่อประเภทสินค้านี้มีอยู่ในระบบแล้ว';
        }
    }
    if (!$personnel_id) {
        $errors[] = 'กรุณาเลือกพนักงาน';
    }
    return $errors;
}

// Check product type dependencies
function checkProductTypeDependencies($pdo, $product_type_id)
{
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE product_type_id = ?");
    $stmt->execute([$product_type_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "สินค้า ({$count} รายการ)";
    }
    return $dependencies;
}

// Handle search and date parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Handle POST requests for adding product type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=product_type&error=Invalid CSRF token');
        exit;
    }

    $typename = trim($_POST['typename']);
    $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);

    $errors = validateProductTypeData($typename, $personnel_id, $pdo);

    if (!empty($errors)) {
        header('Location: index.php?page=product_type&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO product_type (product_type_name, product_type_date, personnel_id) VALUES (?, NOW(), ?)");
        $stmt->execute([$typename, $personnel_id]);
        $pdo->commit();
        header('Location: index.php?page=product_type&success=เพิ่มข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add product type error: " . $e->getMessage());
        header('Location: index.php?page=product_type&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle POST requests for updating product type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=product_type&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $typename = trim($_POST['update_typename']);
    $personnel_id = filter_var($_POST['update_personnel_id'], FILTER_VALIDATE_INT);

    $errors = validateProductTypeData($typename, $personnel_id, $pdo, $id);
    if (!$id) {
        $errors[] = 'รหัสประเภทสินค้าไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=product_type&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE product_type SET product_type_name = ?, product_type_date = NOW(), personnel_id = ? WHERE product_type_id = ?");
        $stmt->execute([$typename, $personnel_id, $id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=product_type&error=ไม่พบข้อมูลประเภทสินค้าที่ต้องการแก้ไข');
            exit;
        }
        $pdo->commit();
        header('Location: index.php?page=product_type&success=แก้ไขข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update product type error: " . $e->getMessage());
        header('Location: index.php?page=product_type&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=product_type&error=Invalid CSRF token');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: index.php?page=product_type&error=Invalid product type ID');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Check dependencies
        $dependencies = checkProductTypeDependencies($pdo, $id);

        if (!empty($dependencies)) {
            $pdo->rollBack();
            $dependency_list = implode(', ', $dependencies);
            header('Location: index.php?page=product_type&error=' . urlencode("ไม่สามารถลบประเภทสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
            exit;
        }

        // Proceed with deletion
        $stmt = $pdo->prepare("DELETE FROM product_type WHERE product_type_id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=product_type&error=ไม่พบข้อมูลประเภทสินค้าที่ต้องการลบ');
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=product_type&success=ลบข้อมูลสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete product type error: Product Type ID=$id, Error=" . $e->getMessage());

        if (strpos($e->getMessage(), '1451') !== false) {
            $error_message = 'ไม่สามารถลบประเภทสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ';
        } else {
            $error_message = 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
        }

        header("Location: index.php?page=product_type&error=" . urlencode($error_message));
        exit;
    }
}

// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total_count FROM product_type";
    $params = [];
    if (!empty($search) || $start_date || $end_date) {
        $count_query .= " WHERE 1=1";
        if (!empty($search)) {
            $count_query .= " AND (product_type_id LIKE :search OR product_type_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $count_query .= " AND product_type_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $count_query .= " AND product_type_date <= :end_date";
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
    error_log("Count product type error: " . $e->getMessage());
    header('Location: index.php?page=product_type&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch personnel data for dropdown
try {
    $personnel_query = "SELECT personnel_id, personnel_name FROM personnel";
    $personnels = fetchData($pdo, $personnel_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=product_type&error=' . urlencode($e->getMessage()));
    exit;
}

// Fetch product type data
try {
    $query = "
        SELECT 
            pt.product_type_id,
            pt.product_type_name,
            DATE_FORMAT(pt.product_type_date, '%Y-%m-%d %H:%i:%s') as product_type_date,
            pt.personnel_id,
            per.personnel_name,
            COALESCE(COUNT(p.product_id), 0) as product_count
        FROM product_type pt
        LEFT JOIN product p ON pt.product_type_id = p.product_type_id
        LEFT JOIN personnel per ON pt.personnel_id = per.personnel_id";
    $params = [];
    if (!empty($search) || $start_date || $end_date) {
        $query .= " WHERE 1=1";
        if (!empty($search)) {
            $query .= " AND (pt.product_type_id LIKE :search OR pt.product_type_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $query .= " AND pt.product_type_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND pt.product_type_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }
    $query .= " GROUP BY pt.product_type_id, pt.product_type_name 
                ORDER BY pt.product_type_id DESC 
                LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $producttypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=product_type&error=' . urlencode($e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการประเภทสินค้า</title>
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
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

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-6">
            <div class="flex flex-col gap-2 md:flex-row md:space-x-2 md:items-center">
                <div class="flex space-x-2">
                    <div class="w-auto px-4 py-2 bg-blue-200 border rounded font-bold text-blue-800">
                        <i class="fas fa-list mr-2"></i>
                        <?php echo $total_records; ?>
                        <span>รายการ</span>
                    </div>
                </div>
                <button onclick="showAddModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                    <i class="fas fa-plus mr-2"></i>เพิ่มประเภทสินค้า
                </button>
            </div>
            <div class="flex items-center">
                <form action="" method="GET" id="searchForm" class="w-full text-end">
                    <input type="hidden" name="page" value="product_type">
                    <input type="hidden" name="page_num" value="1">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput" placeholder="ค้นหาประเภทสินค้า..." value="<?php echo htmlspecialchars($search); ?>" class="w-full md:w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="bg-white shadow-md rounded-xl p-4 space-y-4 border border-gray-200 md:flex md:gap-4 md:bg-transparent md:shadow-none md:border-none md:p-0 md:w-auto">
                <!-- Mobile Date Filter -->
                <div class="grid grid-cols-2 gap-4 md:w-[100%]">
                    <div>
                        <label for="start_date" class="text-sm font-medium text-gray-700">เริ่มจาก</label>
                        <input type="date" id="start_date" name="start_date" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div>
                        <label for="end_date" class="text-sm font-medium text-gray-700">ถึงวันที่</label>
                        <input type="date" id="end_date" name="end_date" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                </div>
                <!-- Export Buttons -->
                <div class="grid grid-cols-2 gap-4 pt-2 md:w-[100%] md:grid md:grid-cols-2 md:gap-4">
                    <button onclick="exportPDF()" class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-red-500 text-white text-sm sm:text-base hover:bg-red-600 transition duration-200 shadow-md md:auto">
                        <i class="fas fa-file-pdf"></i> ส่งออก PDF
                    </button>
                    <button onclick="exportExcel()" class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-emerald-500 text-white text-sm sm:text-base hover:bg-emerald-600 transition duration-200 shadow-md md:auto">
                        <i class="fas fa-file-excel"></i> ส่งออก Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div id="notification" class="fixed top-5 right-5 w-80 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden hidden">
            <div id="notification-header" class="px-4 py-2 font-bold text-white"></div>
            <div class="px-4 py-3" id="notification-message"></div>
        </div>

        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#d7e1ec] to-[#e6effb] p-4 border-b border-[#cfd5dc]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-list mr-2"></i> รายการประเภทสินค้า
                    </h2>
                </div>
            </div>
            <div class="p-6">
                <!-- Table View (Desktop/iPad) -->
                <div class="table-responsive overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัสประเภท</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อประเภทสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">พนักงาน</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่และเวลา</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">จำนวนสินค้า</th>
                                <th class="px-6 py-3 border text-center text-gray-700 font-semibold">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($producttypes) > 0): ?>
                                <?php foreach ($producttypes as $type): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($type['product_type_id']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($type['product_type_name']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($type['personnel_name']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($type['product_type_date']); ?></td>
                                        <td class="px-6 py-2 border">
                                            <a href="?page=product_typegroup&tabel=product_type&id=<?php echo htmlspecialchars($type['product_type_id']); ?>&product_type_name=<?php echo urlencode($type['product_type_name']); ?>" class="text-orange-600 hover:underline cursor-pointer">
                                                <?php echo number_format($type['product_count']); ?> รายการ
                                            </a>
                                        </td>
                                        
                                        <td class="px-6 py-2 border text-center">
                                            <div class="flex flex-wrap justify-center gap-2">
                                                <button onclick="showEditModal('<?php echo htmlspecialchars($type['product_type_id']); ?>', '<?php echo htmlspecialchars(addslashes($type['product_type_name'])); ?>', '<?php echo htmlspecialchars($type['personnel_id']); ?>')" class="bg-yellow-500 text-white text-sm px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center whitespace-nowrap">
                                                    <i class="fas fa-edit mr-1"></i> แก้ไข
                                                </button>
                                                <form action="" method="POST" style="display:inline;" class="delete-form">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($type['product_type_id']); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <button type="button" onclick="confirmDelete(this.parentElement)" class="bg-red-500 text-white text-sm px-3 py-1 rounded hover:bg-red-600 transition flex items-center whitespace-nowrap">
                                                        <i class="fas fa-trash-alt mr-1"></i> ลบ
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-gray-600">ไม่มีข้อมูลประเภทสินค้า</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Card View (Mobile) -->
                <div class="card-responsive">
                    <?php if (count($producttypes) > 0): ?>
                        <?php foreach ($producttypes as $type): ?>
                            <div class="bg-white p-4 mb-4 rounded-lg shadow" data-id="<?php echo htmlspecialchars($type['product_type_id']); ?>">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">รหัสประเภท:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($type['product_type_id']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">ชื่อประเภท:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($type['product_type_name']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">พนักงาน:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($type['personnel_name']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">วันที่:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($type['product_type_date']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">จำนวนสินค้า:</span>
                                    <a href="?page=product_typegroup&tabel=product_type&id=<?php echo htmlspecialchars($type['product_type_id']); ?>&product_type_name=<?php echo urlencode($type['product_type_name']); ?>" class="text-orange-600 hover:underline text-sm">
                                        <?php echo number_format($type['product_count']); ?> รายการ
                                    </a>
                                </div>
                                <div class="flex justify-center space-x-2 mt-4">
                                    <button onclick="showEditModal('<?php echo htmlspecialchars($type['product_type_id']); ?>', '<?php echo htmlspecialchars(addslashes($type['product_type_name'])); ?>', '<?php echo htmlspecialchars($type['personnel_id']); ?>')" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition flex items-center text-sm">
                                        <i class="fas fa-edit mr-1"></i>แก้ไข
                                    </button>
                                    <form action="" method="POST" style="display:inline;" class="delete-form">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($type['product_type_id']); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="button" onclick="confirmDelete(this.parentElement)" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition flex items-center text-sm">
                                            <i class="fas fa-trash-alt mr-1"></i>ลบ
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-600 text-sm">ไม่มีข้อมูลประเภทสินค้า</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=product_type&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=product_type&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=product_type&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>" class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=product_type&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=product_type&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">เพิ่มประเภทสินค้าใหม่</h3>
                <form id="addForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อประเภทสินค้า</label>
                        <input type="text" id="addTypeName" name="typename" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <span id="addNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">พนักงาน</label>
                        <select id="addPersonnelId" name="personnel_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">เลือกพนักงาน</option>
                            <?php foreach ($personnels as $personnel): ?>
                                <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                                    <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="addPersonnelError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
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
                <h3 class="text-xl font-bold text-gray-800 mb-4">แก้ไขข้อมูลประเภทสินค้า</h3>
                <form id="editForm" method="POST" class="space-y-4">
                    <input type="hidden" id="editTypeId" name="id">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อประเภทสินค้า</label>
                        <input type="text" id="editTypeName" name="update_typename" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <span id="editNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">พนักงาน</label>
                        <select id="editPersonnelId" name="update_personnel_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">เลือกพนักงาน</option>
                            <?php foreach ($personnels as $personnel): ?>
                                <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                                    <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="editPersonnelError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
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
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        ยกเลิก
                    </button>
                    <button type="button" id="confirmDeleteBtn" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
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
                urlParams.set('page', 'product_type');
                urlParams.delete('search');
                window.location.search = urlParams.toString();
            }
        }

        function showAddModal() {
            document.getElementById('addTypeName').value = '';
            document.getElementById('addPersonnelId').value = '';
            document.getElementById('addNameError').classList.add('hidden');
            document.getElementById('addPersonnelError').classList.add('hidden');
            document.getElementById('addModal').classList.remove('invisible', 'opacity-0');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('invisible', 'opacity-0');
        }

        function showEditModal(id, name, personnelId) {
            document.getElementById('editTypeId').value = id;
            document.getElementById('editTypeName').value = name;
            document.getElementById('editPersonnelId').value = personnelId;
            document.getElementById('editNameError').classList.add('hidden');
            document.getElementById('editPersonnelError').classList.add('hidden');
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
            let url = '/page/product/report/export_product_type_pdf.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/product/report/export_product_type_excel.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
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
                const fields = [
                    {
                        id: 'addTypeName',
                        errorId: 'addNameError',
                        label: 'ชื่อประเภทสินค้า',
                        maxLength: 255
                    },
                    {
                        id: 'addPersonnelId',
                        errorId: 'addPersonnelError',
                        label: 'พนักงาน',
                        maxLength: Infinity
                    }
                ];
                if (validateForm('addForm', fields)) {
                    this.submit();
                }
            });

            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fields = [
                    {
                        id: 'editTypeName',
                        errorId: 'editNameError',
                        label: 'ชื่อประเภทสินค้า',
                        maxLength: 255
                    },
                    {
                        id: 'editPersonnelId',
                        errorId: 'editPersonnelError',
                        label: 'พนักงาน',
                        maxLength: Infinity
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
                urlParams.set('page', 'product_type');

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