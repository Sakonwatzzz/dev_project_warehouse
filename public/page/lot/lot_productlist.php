<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();
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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'lot_productlist', 'lot_id' => $_GET['lot_id'] ?? 0]);
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

// Validate lot_product data
function validateLotProductData($lot_id, $product_id, $lot_product_qty, $lot_product_capital, $lot_product_date, $pdo)
{
    $errors = [];
    if (!$lot_id) {
        $errors[] = 'กรุณาเลือก Lot';
    }
    if (!$product_id) {
        $errors[] = 'กรุณาเลือกสินค้า';
    }
    if (!is_numeric($lot_product_qty) || $lot_product_qty <= 0) {
        $errors[] = 'จำนวนสินค้าต้องมากกว่า 0';
    }
    if (!is_numeric($lot_product_capital) || $lot_product_capital < 0) {
        $errors[] = 'ราคาต้นทุนต้องไม่ติดลบ';
    }
    if (empty($lot_product_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lot_product_date)) {
        $errors[] = 'วันที่รับสินค้าต้องอยู่ในรูปแบบ YYYY-MM-DD';
    }
    return $errors;
}

// Check lot_product dependencies
function checkLotProductDependencies($pdo, $lot_product_id)
{
    $dependencies = [];
    // Example: Check if lot_product is referenced elsewhere (e.g., in transactions)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM some_transaction_table WHERE lot_product_id = ?");
    $stmt->execute([$lot_product_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "ธุรกรรมที่เกี่ยวข้อง ({$count} รายการ)";
    }
    return $dependencies;
}

// Get lot_id from URL
$lot_id = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : 0;
$lot_name = "";
if ($lot_id) {
    try {
        $lot_query = "SELECT lot_name FROM lot WHERE lot_id = ?";
        $lot_stmt = $pdo->prepare($lot_query);
        $lot_stmt->execute([$lot_id]);
        $lot = $lot_stmt->fetch(PDO::FETCH_ASSOC);
        $lot_name = $lot ? $lot['lot_name'] : "ไม่พบข้อมูล Lot";
    } catch (PDOException $e) {
        header("Location: index.php?page=lot_productlist&error=" . urlencode("Error fetching lot: " . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle search and date parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Handle POST requests for adding lot_product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=Invalid CSRF token');
        exit;
    }

    $lot_id = filter_var($_POST['lot_id'], FILTER_VALIDATE_INT);
    $product_id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
    $lot_product_qty = filter_var($_POST['lot_product_qty'], FILTER_VALIDATE_INT);
    $lot_product_capital = filter_var($_POST['lot_product_capital'], FILTER_VALIDATE_FLOAT);
    $lot_product_date = trim($_POST['lot_product_date']);

    $errors = validateLotProductData($lot_id, $product_id, $lot_product_qty, $lot_product_capital, $lot_product_date, $pdo);

    if (!empty($errors)) {
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO lot_product (lot_id, product_id, lot_product_qty, lot_product_remain, lot_product_capital, lot_product_date, lot_product_status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$lot_id, $product_id, $lot_product_qty, $lot_product_qty, $lot_product_capital, $lot_product_date]);
        $pdo->commit();
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&success=เพิ่มรายการรับสินค้าสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add lot_product error: " . $e->getMessage());
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle POST requests for updating lot_product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=Invalid CSRF token');
        exit;
    }

    $lot_product_id = filter_var($_POST['lot_product_id'], FILTER_VALIDATE_INT);
    $lot_id = filter_var($_POST['lot_id'], FILTER_VALIDATE_INT);
    $product_id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
    $lot_product_qty = filter_var($_POST['lot_product_qty'], FILTER_VALIDATE_INT);
    $lot_product_capital = filter_var($_POST['lot_product_capital'], FILTER_VALIDATE_FLOAT);
    $lot_product_date = trim($_POST['lot_product_date']);

    $errors = validateLotProductData($lot_id, $product_id, $lot_product_qty, $lot_product_capital, $lot_product_date, $pdo);
    if (!$lot_product_id) {
        $errors[] = 'รหัสสินค้าไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE lot_product
            SET lot_id = ?, product_id = ?, lot_product_qty = ?, lot_product_remain = ?, lot_product_capital = ?, lot_product_date = ?
            WHERE lot_product_id = ?
        ");
        $stmt->execute([$lot_id, $product_id, $lot_product_qty, $lot_product_qty, $lot_product_capital, $lot_product_date, $lot_product_id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=ไม่พบข้อมูลรายการรับสินค้าที่ต้องการแก้ไข');
            exit;
        }
        $pdo->commit();
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&success=แก้ไขรายการรับสินค้าสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update lot_product error: " . $e->getMessage());
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['lot_product_id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=Invalid CSRF token');
        exit;
    }

    $lot_product_id = filter_var($_POST['lot_product_id'], FILTER_VALIDATE_INT);
    if (!$lot_product_id) {
        header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=Invalid lot product ID');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $dependencies = checkLotProductDependencies($pdo, $lot_product_id);

        if (!empty($dependencies)) {
            $pdo->rollBack();
            $dependency_list = implode(', ', $dependencies);
            header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=' . urlencode("ไม่สามารถลบรายการรับสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_product_id = ?");
        $stmt->execute([$lot_product_id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=ไม่พบข้อมูลรายการรับสินค้าที่ต้องการลบ');
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=lot_productlist&lot_id=" . $lot_id . "&success=ลบรายการรับสินค้าสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete lot_product error: Lot Product ID=$lot_product_id, Error=" . $e->getMessage());
        $error_message = strpos($e->getMessage(), '1451') !== false
            ? 'ไม่สามารถลบรายการรับสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
            : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
        header("Location: index.php?page=lot_productlist&lot_id=" . $lot_id . "&error=" . urlencode($error_message));
        exit;
    }
}

// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total_count FROM lot_product";
    $params = [];
    if ($lot_id) {
        $count_query .= " WHERE lot_id = :lot_id";
        $params[':lot_id'] = $lot_id;
    }
    if (!empty($search)) {
        $count_query .= $lot_id ? " AND" : " WHERE";
        $count_query .= " (lot_product_id LIKE :search OR product_id LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date) {
        $count_query .= $lot_id || !empty($search) ? " AND" : " WHERE";
        $count_query .= " lot_product_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $count_query .= $lot_id || !empty($search) || $start_date ? " AND" : " WHERE";
        $count_query .= " lot_product_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }

    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count lot_product error: " . $e->getMessage());
    header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=' . urlencode('Count error: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch lot, product, and supplier options
try {
    $lot_query = "SELECT lot_id, lot_name FROM lot";
    $lots = fetchData($pdo, $lot_query)->fetchAll(PDO::FETCH_ASSOC);
    $product_query = "SELECT product_id, product_name FROM product";
    $products = fetchData($pdo, $product_query)->fetchAll(PDO::FETCH_ASSOC);
    $supplier_query = "SELECT supplier_id, supplier_name FROM supplier";
    $suppliers = fetchData($pdo, $supplier_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=' . urlencode($e->getMessage()));
    exit;
}

// Fetch lot_product data
try {
    $query = "
        SELECT
            lp.lot_product_id,
            lp.lot_id,
            l.lot_name,
            lp.product_id,
            p.product_name,
            lp.lot_product_qty,
            lp.lot_product_remain,
            lp.lot_product_capital,
            lp.lot_product_date,
            lp.lot_product_status,
            s.supplier_name
        FROM lot_product lp
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN product p ON lp.product_id = p.product_id
        LEFT JOIN supplier s ON l.supplier_id = s.supplier_id";
    $params = [];
    if ($lot_id || !empty($search) || $start_date || $end_date) {
        $query .= " WHERE";
        $conditions = [];
        if ($lot_id) {
            $conditions[] = " lp.lot_id = :lot_id";
            $params[':lot_id'] = $lot_id;
        }
        if (!empty($search)) {
            $conditions[] = " (lp.lot_product_id LIKE :search OR p.product_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $conditions[] = " lp.lot_product_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $conditions[] = " lp.lot_product_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        $query .= " " . implode(" AND ", $conditions);
    }
    $query .= " ORDER BY lp.lot_product_date DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $lot_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=lot_productlist&lot_id=' . $lot_id . '&error=' . urlencode($e->getMessage()));
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการรับสินค้า<?php echo $lot_id ? " - Lot: " . htmlspecialchars($lot_name) : ""; ?></title>
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .zoom-in {
            cursor: zoom-in;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class=" mx-auto px-4 py-8">
        <!-- Header and Search Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-6">
            <div class="flex flex-col gap-2 md:flex-row md:space-x-2 md:items-center">
                <div class="flex space-x-2">
                    <div class="w-auto px-4 py-2 bg-blue-200 border rounded font-bold text-blue-800">
                        <i class="fas fa-boxes mr-2"></i>
                        <?php echo $total_records; ?>
                        <span>รายการ</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center">
                <form action="" method="GET" id="searchForm" class="w-full text-end">
                    <input type="hidden" name="page" value="lot_productlist">
                    <input type="hidden" name="lot_id" value="<?php echo htmlspecialchars($lot_id); ?>">
                    <input type="hidden" name="page_num" value="1">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหารายการรับสินค้า..."
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
                    <h2 class="text-[18px] flex items-center md:text-[22px]">
                        <div class="hidden md:flex items-center mr-2">
                            <i class="fas fa-list mr-2"></i>
                        </div>
                        รายการรับสินค้า<?php echo $lot_id ? " - Lot: " . htmlspecialchars($lot_name) : ""; ?>
                    </h2>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัสสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อล็อตสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ราคาต้นทุน</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">จำนวนสินค้าเข้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">สินค้าคงเหลือ</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">สถานะ</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ผู้ส่งมอบ</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่รับสินค้า</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lot_products) > 0): ?>
                                <?php foreach ($lot_products as $lot_product): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($lot_product['product_id']); ?></td>
                                        <td class="px-6 py-2 border zoom-in"
                                            onclick="window.location.href='?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot_product['lot_id']); ?>'">
                                            <?php echo htmlspecialchars($lot_product['lot_name']); ?>
                                        </td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($lot_product['product_name']); ?></td>
                                        <td class="px-6 py-2 border text-end"><?php echo htmlspecialchars($lot_product['lot_product_capital']); ?></td>
                                        <td class="px-6 py-2 border text-end"><?php echo htmlspecialchars($lot_product['lot_product_qty']); ?></td>
                                        <td class="px-6 py-2 border text-end"><?php echo htmlspecialchars($lot_product['lot_product_remain']); ?></td>
                                        <td class="px-6 py-2 border text-center"><?php echo htmlspecialchars($lot_product['lot_product_status']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($lot_product['supplier_name']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($lot_product['lot_product_date']); ?></td>
                                       
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-gray-600">ไม่มีข้อมูล</td>
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
                    <a href="?page=lot_productlist&lot_id=<?= $lot_id ?>&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=lot_productlist&lot_id=<?= $lot_id ?>&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=lot_productlist&lot_id=<?= $lot_id ?>&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=lot_productlist&lot_id=<?= $lot_id ?>&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_productlist&lot_id=<?= $lot_id ?>&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Navigation Links -->
        <div class="flex space-x-2 mt-5 justify-end">
            <div class="detail-action">
                <a href="/?page=lot_list" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">
                    <i class="fas fa-arrow-left mr-2"></i>ย้อนกลับ
                </a>
            </div>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
            <div class="bg-white rounded-lg w-full max-w-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">เพิ่มรายการรับสินค้า</h3>
                <form id="addForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Lot</label>
                        <select id="addLotId" name="lot_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือก Lot</option>
                            <?php foreach ($lots as $lot): ?>
                                <option value="<?php echo htmlspecialchars($lot['lot_id']); ?>" <?php echo $lot_id == $lot['lot_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lot['lot_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="addLotError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">สินค้า</label>
                        <select id="addProductId" name="product_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกสินค้า</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="addProductError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">จำนวนสินค้าเข้า</label>
                        <input type="number" id="addLotProductQty" name="lot_product_qty"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required min="1">
                        <span id="addQtyError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ราคาต้นทุน</label>
                        <input type="number" id="addLotProductCapital" name="lot_product_capital"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required min="0" step="0.01">
                        <span id="addCapitalError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">วันที่รับสินค้า</label>
                        <input type="date" id="addLotProductDate" name="lot_product_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="addDateError" class="text-red-500 text-xs mt-1 hidden"></span>
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
                <h3 class="text-xl font-bold text-gray-800 mb-4">แก้ไขรายการรับสินค้า</h3>
                <form id="editForm" method="POST" class="space-y-4">
                    <input type="hidden" id="editLotProductId" name="lot_product_id">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Lot</label>
                        <select id="editLotId" name="lot_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือก Lot</option>
                            <?php foreach ($lots as $lot): ?>
                                <option value="<?php echo htmlspecialchars($lot['lot_id']); ?>">
                                    <?php echo htmlspecialchars($lot['lot_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="editLotError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">สินค้า</label>
                        <select id="editProductId" name="product_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">เลือกสินค้า</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="editProductError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">จำนวนสินค้าเข้า</label>
                        <input type="number" id="editLotProductQty" name="lot_product_qty"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required min="1">
                        <span id="editQtyError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ราคาต้นทุน</label>
                        <input type="number" id="editLotProductCapital" name="lot_product_capital"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required min="0" step="0.01">
                        <span id="editCapitalError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">วันที่รับสินค้า</label>
                        <input type="date" id="editLotProductDate" name="lot_product_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <span id="editDateError" class="text-red-500 text-xs mt-1 hidden"></span>
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
                <p class="text-gray-600 mb-6">คุณแน่ใจหรือไม่ว่าต้องการลบรายการรับสินค้านี้?</p>
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
                urlParams.set('page', 'lot_productlist');
                urlParams.set('lot_id', '<?php echo $lot_id; ?>');
                urlParams.delete('search');
                window.location.search = urlParams.toString();
            }
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
            let url = '/page/lot/ajax/report/lot_product_pdf.php?lot_id=<?php echo urlencode($lot_id); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/lot_product_excel.php?lot_id=<?php echo urlencode($lot_id); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
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
                    if (!input.value && field.required) {
                        error.textContent = `กรุณากรอก${field.label}`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (field.id.includes('Qty') && (input.value <= 0 || isNaN(input.value))) {
                        error.textContent = `${field.label}ต้องมากกว่า 0`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (field.id.includes('Capital') && (input.value < 0 || isNaN(input.value))) {
                        error.textContent = `${field.label}ต้องไม่ติดลบ`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (field.id.includes('Date') && input.value && !/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
                        error.textContent = `${field.label}ต้องอยู่ในรูปแบบ YYYY-MM-DD`;
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
                        id: 'addLotId',
                        errorId: 'addLotError',
                        label: 'Lot',
                        required: true
                    },
                    {
                        id: 'addProductId',
                        errorId: 'addProductError',
                        label: 'สินค้า',
                        required: true
                    },
                    {
                        id: 'addLotProductQty',
                        errorId: 'addQtyError',
                        label: 'จำนวนสินค้าเข้า',
                        required: true
                    },
                    {
                        id: 'addLotProductCapital',
                        errorId: 'addCapitalError',
                        label: 'ราคาต้นทุน',
                        required: true
                    },
                    {
                        id: 'addLotProductDate',
                        errorId: 'addDateError',
                        label: 'วันที่รับสินค้า',
                        required: true
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
                        id: 'editLotId',
                        errorId: 'editLotError',
                        label: 'Lot',
                        required: true
                    },
                    {
                        id: 'editProductId',
                        errorId: 'editProductError',
                        label: 'สินค้า',
                        required: true
                    },
                    {
                        id: 'editLotProductQty',
                        errorId: 'editQtyError',
                        label: 'จำนวนสินค้าเข้า',
                        required: true
                    },
                    {
                        id: 'editLotProductCapital',
                        errorId: 'editCapitalError',
                        label: 'ราคาต้นทุน',
                        required: true
                    },
                    {
                        id: 'editLotProductDate',
                        errorId: 'editDateError',
                        label: 'วันที่รับสินค้า',
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
                urlParams.set('page', 'lot_productlist');
                urlParams.set('lot_id', '<?php echo $lot_id; ?>');

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