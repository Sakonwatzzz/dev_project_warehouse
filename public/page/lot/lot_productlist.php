<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();
date_default_timezone_set('Asia/Bangkok');

$pdo = Database::getInstance()->getConnection();

$accessToken = getToken('access_token');
if (!$accessToken || !validateToken($accessToken)) {
    $refreshToken = getToken('refresh_token');
    if ($refreshToken && validateToken($refreshToken)) {
        $accessToken = refreshToken($refreshToken);
    } else {
        header('Location: login.php');
        exit();
    }
}

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
    // ตัวอย่าง: ตรวจสอบตารางที่เชื่อมโยง (ปรับตามโครงสร้างฐานข้อมูลจริง)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM some_transaction_table WHERE lot_product_id = ?");
    $stmt->execute([$lot_product_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "ธุรกรรมที่เกี่ยวข้อง ({$count} รายการ)";
    }
    return $dependencies;
}

// Handle GET parameters
$lot_id = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$lot_name = "LOT-0003"; // คงที่ตามที่ระบุในโจทย์

// Fetch lot details
if ($lot_id) {
    try {
        $lot_query = "SELECT lot_name FROM lot WHERE lot_id = ?";
        $lot_stmt = $pdo->prepare($lot_query);
        $lot_stmt->execute([$lot_id]);
        $lot = $lot_stmt->fetch(PDO::FETCH_ASSOC);
        $lot_name = $lot ? $lot['lot_name'] : "ไม่พบข้อมูล Lot";
    } catch (PDOException $e) {
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode("เกิดข้อผิดพลาดในการดึงข้อมูล Lot: " . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle POST requests for adding lot_product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=โทเค็น CSRF ไม่ถูกต้อง");
        exit;
    }

    $lot_id = filter_var($_POST['lot_id'], FILTER_VALIDATE_INT);
    $product_id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
    $lot_product_qty = filter_var($_POST['lot_product_qty'], FILTER_VALIDATE_INT);
    $lot_product_capital = filter_var($_POST['lot_product_capital'], FILTER_VALIDATE_FLOAT);
    $lot_product_date = trim($_POST['lot_product_date']);

    $errors = validateLotProductData($lot_id, $product_id, $lot_product_qty, $lot_product_capital, $lot_product_date, $pdo);

    if (!empty($errors)) {
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode(implode(', ', $errors)));
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
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&success=เพิ่มรายการรับสินค้าสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add lot_product error: " . $e->getMessage());
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['lot_product_id'], $_POST['csrf_token']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=โทเค็น CSRF ไม่ถูกต้อง");
        exit;
    }

    $lot_product_id = filter_var($_POST['lot_product_id'], FILTER_VALIDATE_INT);
    if (!$lot_product_id) {
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=รหัสสินค้าไม่ถูกต้อง");
        exit;
    }

    try {
        $pdo->beginTransaction();
        $dependencies = checkLotProductDependencies($pdo, $lot_product_id);

        if (!empty($dependencies)) {
            $pdo->rollBack();
            $dependency_list = implode(', ', $dependencies);
            header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode("ไม่สามารถลบรายการรับสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_product_id = ?");
        $stmt->execute([$lot_product_id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=ไม่พบข้อมูลรายการรับสินค้าที่ต้องการลบ");
            exit;
        }

        $pdo->commit();
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&success=ลบรายการรับสินค้าสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete lot_product error: Lot Product ID=$lot_product_id, Error=" . $e->getMessage());
        $error_message = strpos($e->getMessage(), '1451') !== false
            ? 'ไม่สามารถลบรายการรับสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
            : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode($error_message));
        exit;
    }
}

// Count total records for pagination
try {
    $count_query = "
        SELECT COUNT(*) as total_count 
        FROM lot_product lp
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN product p ON lp.product_id = p.product_id";
    $params = [];
    if ($lot_id || !empty($search)) {
        $count_query .= " WHERE";
        $conditions = [];
        if ($lot_id) {
            $conditions[] = " lp.lot_id = :lot_id";
            $params[':lot_id'] = $lot_id;
        }
        if (!empty($search)) {
            $conditions[] = " (lp.lot_product_id LIKE :search OR p.product_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        $count_query .= " " . implode(" AND ", $conditions);
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count lot_product error: " . $e->getMessage());
    header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode('เกิดข้อผิดพลาดในการนับข้อมูล: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch lot, product, and supplier options
try {
    $lot_query = "SELECT lot_id, lot_name FROM lot ORDER BY lot_name";
    $lots = fetchData($pdo, $lot_query)->fetchAll(PDO::FETCH_ASSOC);
    $product_query = "SELECT product_id, product_name FROM product ORDER BY product_name";
    $products = fetchData($pdo, $product_query)->fetchAll(PDO::FETCH_ASSOC);
    $supplier_query = "SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name";
    $suppliers = fetchData($pdo, $supplier_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode($e->getMessage()));
    exit;
}

// Calculate total price for displayed records
try {
    $total_price_query = "
        SELECT SUM(lp.lot_product_qty * lp.lot_product_capital) as total_price
        FROM lot_product lp
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN product p ON lp.product_id = p.product_id";
    $total_price_params = [];
    if ($lot_id || !empty($search)) {
        $total_price_query .= " WHERE";
        $conditions = [];
        if ($lot_id) {
            $conditions[] = " lp.lot_id = :lot_id";
            $total_price_params[':lot_id'] = $lot_id;
        }
        if (!empty($search)) {
            $conditions[] = " (lp.lot_product_id LIKE :search OR p.product_name LIKE :search)";
            $total_price_params[':search'] = "%$search%";
        }
        $total_price_query .= " " . implode(" AND ", $conditions);
    }
    $total_price_stmt = $pdo->prepare($total_price_query);
    foreach ($total_price_params as $key => $value) {
        $total_price_stmt->bindValue($key, $value);
    }
    $total_price_stmt->execute();
    $total_price_result = $total_price_stmt->fetch(PDO::FETCH_ASSOC);
    $total_price = $total_price_result['total_price'] ?? 0;
} catch (Exception $e) {
    error_log("ข้อผิดพลาดการคำนวณราคารวม: " . $e->getMessage());
    header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode('เกิดข้อผิดพลาดในการคำนวณราคารวม: ' . htmlspecialchars($e->getMessage())));
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการรับสินค้า: <?php echo htmlspecialchars($lot_name); ?></title>
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
            <h1 class="text-3xl font-bold text-indigo-900">รายการรับสินค้า: <?php echo htmlspecialchars($lot_name); ?></h1>
            <p class="text-gray-600">ข้อมูลการรับสินค้าทั้งหมดใน Lot <?php echo htmlspecialchars($lot_name); ?></p>
        </div>

        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span id="totalRecords" class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fas fa-boxes mr-2"></i> <?php echo number_format($total_records); ?> รายการ
                </span>
                <span class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fa-solid fa-money-check-dollar mr-2"></i> มูลค่ารวม: <?php echo number_format($total_price, 2); ?> บาท
                </span>

            </div>
            <form id="searchForm" action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="lot_productlist">
                <input type="hidden" name="lot_id" value="<?php echo htmlspecialchars($lot_id); ?>">
                <input type="hidden" name="page_num" value="1">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหารายการรับสินค้า..." value="<?php echo htmlspecialchars($search); ?>"
                        class="w-full md:w-80 px-4 py-2 border border-gray-300 rounded-lg pl-12 pr-12 bg-white/70 backdrop-blur-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition-all duration-200">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-base md:text-lg"></i>
                    <button type="button" id="clearSearch" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-red-500 transition <?php echo $search ? '' : 'hidden'; ?>">
                        <i class="fas fa-times-circle text-base md:text-lg"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Export Buttons -->
        <div class="glass-effect rounded-xl p-4 mb-6 card-shadow">
            <div class="flex flex-col xs:flex-row gap-2">
                <button onclick="viewAllData()" class="bg-gray-500 text-white py-2 px-4 rounded-lg font-medium hover:bg-gray-600 transition hover-lift w-full md:w-auto">
                    <i class="fas fa-eye mr-2"></i> ดูข้อมูลทั้งหมด
                </button>
                <button id="export-buttons" onclick="exportPDF()"
                    class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition hover-lift flex items-center gap-2 standard-button-mobile"
                    data-lot-id="<?php echo htmlspecialchars($lot_id); ?>"
                    data-token="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <i class="fas fa-file-pdf"></i><span>PDF</span>
                </button>
                <button id="export-excel-button" onclick="exportExcel()"
                    class="bg-emerald-500 text-white py-2 px-4 rounded-lg hover:bg-emerald-600 transition hover-lift flex items-center gap-2 standard-button-mobile"
                    data-lot-id="<?php echo htmlspecialchars($lot_id); ?>"
                    data-token="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <i class="fas fa-file-excel"></i><span>Excel</span>
                </button>
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
        <div id="lotProductTable" class="glass-effect rounded-xl p-6 card-shadow animate-fade-in">
            <!-- ตารางจะถูกโหลดผ่าน AJAX -->
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

    <script src="/page/lot/function/lot_productlist.js"></script>
</body>

</html>