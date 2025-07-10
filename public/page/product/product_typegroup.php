<?php
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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'product_typegroup', 'id' => $_GET['id'] ?? ''] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Database Functions
function fetchData($pdo, $query, $params = []) {
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

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validateProductData($product_id, $name, $price1, $price2, $price3, $price4, $capital, $type_id, $pdo, $exclude_id = null) {
    $errors = [];
    if (empty($product_id)) {
        $errors[] = 'รหัสสินค้าต้องไม่ว่าง';
    } else {
        $query = "SELECT COUNT(*) FROM product WHERE product_id = :product_id";
        if ($exclude_id) {
            $query .= " AND product_id != :exclude_id";
        }
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':product_id', $product_id);
        if ($exclude_id) {
            $stmt->bindValue(':exclude_id', $exclude_id);
        }
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'รหัสสินค้านี้มีอยู่ในระบบแล้ว';
        }
    }
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อสินค้าต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    } else {
        $query = "SELECT COUNT(*) FROM product WHERE product_name = :name";
        if ($exclude_id) {
            $query .= " AND product_id != :exclude_id";
        }
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':name', $name);
        if ($exclude_id) {
            $stmt->bindValue(':exclude_id', $exclude_id);
        }
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'ชื่อสินค้านี้มีอยู่ในระบบแล้ว';
        }
    }
    if (!is_numeric($price1) || $price1 < 0) {
        $errors[] = 'ราคาขาย 1 ต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!is_numeric($price2) || $price2 < 0) {
        $errors[] = 'ราคาขาย 2 ต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!is_numeric($price3) || $price3 < 0) {
        $errors[] = 'ราคาขาย 3 ต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!is_numeric($price4) || $price4 < 0) {
        $errors[] = 'ราคาขาย 4 ต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!is_numeric($capital) || $capital < 0) {
        $errors[] = 'ราคาทุนต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!$type_id) {
        $errors[] = 'กรุณาเลือกประเภทสินค้า';
    }
    return $errors;
}

function checkProductDependencies($pdo, $product_id) {
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale_product WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "รายการสั่งซื้อ ({$count} รายการ)";
    }
    return $dependencies;
}

// Handle Requests
$productTypeId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

if (!$productTypeId) {
    header('Location: index.php?page=product_typegroup&error=' . urlencode('กรุณาระบุรหัสประเภทสินค้า'));
    exit;
}

// Get product type name
try {
    $stmtType = $pdo->prepare("SELECT product_type_name FROM product_type WHERE product_type_id = :product_type_id");
    $stmtType->bindParam(':product_type_id', $productTypeId, PDO::PARAM_INT);
    $stmtType->execute();
    $typeResult = $stmtType->fetch(PDO::FETCH_ASSOC);
    $productTypeName = $typeResult['product_type_name'] ?? 'ไม่ทราบประเภท';
} catch (PDOException $e) {
    header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode('เกิดข้อผิดพลาดในการดึงชื่อประเภท: ' . htmlspecialchars($e->getMessage())));
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    if ($_POST['action'] === 'add') {
        $product_id = trim($_POST['add_product_id']);
        $name = trim($_POST['add_product_name']);
        $price1 = filter_var($_POST['add_product_price1'], FILTER_VALIDATE_FLOAT);
        $price2 = filter_var($_POST['add_product_price2'], FILTER_VALIDATE_FLOAT);
        $price3 = filter_var($_POST['add_product_price3'], FILTER_VALIDATE_FLOAT);
        $price4 = filter_var($_POST['add_product_price4'], FILTER_VALIDATE_FLOAT);
        $capital = filter_var($_POST['add_product_capital'], FILTER_VALIDATE_FLOAT);
        $type_id = filter_var($_POST['product_type_id'], FILTER_VALIDATE_INT);

        $errors = validateProductData($product_id, $name, $price1, $price2, $price3, $price4, $capital, $type_id, $pdo);

        if (!empty($errors)) {
            header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode(implode(', ', $errors)));
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO product (product_id, product_name, product_price1, product_price2, product_price3, product_price4, product_capital, product_type_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$product_id, $name, $price1, $price2, $price3, $price4, $capital, $type_id]);
            $pdo->commit();
            header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&success=เพิ่มข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Add product error: " . $e->getMessage());
            header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
            exit;
        }
    }

    if ($_POST['action'] === 'edit') {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        $name = trim($_POST['update_product_name']);
        $price1 = filter_var($_POST['update_product_price1'], FILTER_VALIDATE_FLOAT);
        $price2 = filter_var($_POST['update_product_price2'], FILTER_VALIDATE_FLOAT);
        $price3 = filter_var($_POST['update_product_price3'], FILTER_VALIDATE_FLOAT);
        $price4 = filter_var($_POST['update_product_price4'], FILTER_VALIDATE_FLOAT);
        $capital = filter_var($_POST['update_product_capital'], FILTER_VALIDATE_FLOAT);
        $type_id = filter_var($_POST['update_product_type_id'], FILTER_VALIDATE_INT);

        $errors = validateProductData($id, $name, $price1, $price2, $price3, $price4, $capital, $type_id, $pdo, $id);
        if (!$id) {
            $errors[] = 'รหัสสินค้าไม่ถูกต้อง';
        }

        if (!empty($errors)) {
            header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode(implode(', ', $errors)));
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE product SET product_name = ?, product_price1 = ?, product_price2 = ?, product_price3 = ?, product_price4 = ?, product_capital = ?, product_type_id = ? WHERE product_id = ?");
            $stmt->execute([$name, $price1, $price2, $price3, $price4, $capital, $type_id, $id]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=ไม่พบข้อมูลสินค้าที่ต้องการแก้ไข');
                exit;
            }
            $pdo->commit();
            header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&success=แก้ไขข้อมูลสำเร็จ' . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update product error: " . $e->getMessage());
            header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode('เกิดข้อผิดพลาดในฐานข้อมูล: ' . htmlspecialchars($e->getMessage())));
            exit;
        }
    }

    if ($_POST['action'] === 'delete') {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=รหัสสินค้าไม่ถูกต้อง');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $dependencies = checkProductDependencies($pdo, $id);
            if (!empty($dependencies)) {
                $pdo->rollBack();
                $dependency_list = implode(', ', $dependencies);
                header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode("ไม่สามารถลบสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่: {$dependency_list}"));
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=ไม่พบข้อมูลสินค้าที่ต้องการลบ');
                exit;
            }
            $pdo->commit();
            header("Location: index.php?page=product_typegroup&id=" . urlencode($productTypeId) . "&success=ลบข้อมูลสำเร็จ" . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Delete product error: ID=$id, Error=" . $e->getMessage());
            $error_message = strpos($e->getMessage(), '1451') !== false
                ? 'ไม่สามารถลบสินค้าได้ เนื่องจากมีข้อมูลที่เชื่อมโยงอยู่ในระบบ'
                : 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage());
            header("Location: index.php?page=product_typegroup&id=" . urlencode($productTypeId) . "&error=" . urlencode($error_message));
            exit;
        }
    }
}

// Fetch product types for dropdown
try {
    $product_types = fetchData($pdo, "SELECT product_type_id, product_type_name FROM product_type ORDER BY product_type_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode($e->getMessage()));
    exit;
}

// Count total records for pagination
try {
    $count_query = "
        SELECT COUNT(DISTINCT product.product_id) as total_count
        FROM product
        LEFT JOIN lot_product ON product.product_id = lot_product.product_id
        WHERE product.product_type_id = :product_type_id";
    $params = [':product_type_id' => $productTypeId];
    if (!empty($search)) {
        $count_query .= " AND (product.product_name LIKE :search OR product.product_id LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date) {
        $count_query .= " AND lot_product.lot_product_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $count_query .= " AND lot_product.lot_product_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }

    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count product error: " . $e->getMessage());
    header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode('เกิดข้อผิดพลาดในการนับข้อมูล: ' . htmlspecialchars($e->getMessage())));
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
    <title>กลุ่มสินค้า: <?php echo htmlspecialchars($productTypeName); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link href="/output.css" rel="stylesheet">
    <link href="/style/page_list.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-white min-h-screen font-sans text-base">
    <div class="container mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">กลุ่มสินค้า: <?php echo htmlspecialchars($productTypeName); ?></h1>
            <p class="text-gray-600">ข้อมูลสินค้าทั้งหมดในประเภท <?php echo htmlspecialchars($productTypeName); ?></p>
        </div>

        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span id="totalRecords" class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fas fa-boxes mr-2"></i> <?php echo number_format($total_records); ?> รายการ
                </span>
                <button onclick="showAddModal()" class="btn-primary text-white py-2 px-4 rounded-lg font-medium hover-lift w-full md:w-auto">
                    <i class="fas fa-plus mr-2"></i> เพิ่มสินค้า
                </button>
                <button onclick="viewAllData()" class="bg-gray-500 text-white py-2 px-4 rounded-lg font-medium hover:bg-gray-600 transition hover-lift w-full md:w-auto">
                    <i class="fas fa-eye mr-2"></i> ดูข้อมูลทั้งหมด
                </button>
            </div>
            <form id="searchForm" action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="product_typegroup">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($productTypeId); ?>">
                <input type="hidden" name="page_num" value="1">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหาสินค้า..." value="<?php echo htmlspecialchars($search); ?>"
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
                        <i class="fas fa-calendar-alt text-blue-500"></i>กรองตามวันที่นำเข้า
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
                    <button id="export-buttons" onclick="exportPDF()"
                        class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition hover-lift flex items-center gap-2 standard-button-mobile"
                        data-id="<?php echo htmlspecialchars($productTypeId); ?>"
                        data-token="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <i class="fas fa-file-pdf"></i><span>PDF</span>
                    </button>
                    <button id="export-excel-button" onclick="exportExcel()"
                        class="bg-emerald-500 text-white py-2 px-4 rounded-lg hover:bg-emerald-600 transition hover-lift flex items-center gap-2 standard-button-mobile"
                        data-id="<?php echo htmlspecialchars($productTypeId); ?>"
                        data-token="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
        <div id="productTable" class="glass-effect rounded-xl p-6 card-shadow animate-fade-in">
            <!-- ตารางจะถูกโหลดผ่าน AJAX -->
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50 p-4">
            <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md md:max-w-lg lg:max-w-xl max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="mb-5">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center">
                            <i class="fas fa-plus-circle text-blue-600 mr-2"></i>เพิ่มสินค้าใหม่
                        </h3>
                        <p class="text-sm text-gray-500">กรุณากรอกข้อมูลสินค้า</p>
                    </div>
                    <form id="addForm" method="POST" class="space-y-4" autocomplete="off">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="product_type_id" id="addProductTypeId" value="<?php echo htmlspecialchars($productTypeId); ?>">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">รหัสสินค้า</label>
                            <input type="text" id="addProductId" name="add_product_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addIdError" autocomplete="off">
                            <span id="addIdError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ชื่อสินค้า</label>
                            <input type="text" id="addProductName" name="add_product_name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addNameError" autocomplete="off">
                            <span id="addNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 1</label>
                            <input type="number" step="0.01" id="addProductPrice1" name="add_product_price1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addPrice1Error" autocomplete="off">
                            <span id="addPrice1Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 2</label>
                            <input type="number" step="0.01" id="addProductPrice2" name="add_product_price2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addPrice2Error" autocomplete="off">
                            <span id="addPrice2Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 3</label>
                            <input type="number" step="0.01" id="addProductPrice3" name="add_product_price3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addPrice3Error" autocomplete="off">
                            <span id="addPrice3Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 4</label>
                            <input type="number" step="0.01" id="addProductPrice4" name="add_product_price4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addPrice4Error" autocomplete="off">
                            <span id="addPrice4Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาทุน</label>
                            <input type="number" step="0.01" id="addProductCapital" name="add_product_capital"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addCapitalError" autocomplete="off">
                            <span id="addCapitalError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ประเภทสินค้า</label>
                            <p class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700">
                                <?php echo htmlspecialchars($productTypeName); ?>
                            </p>
                        </div>
                        <div class="flex justify-end space-x-2 pt-4">
                            <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                ยกเลิก
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">บันทึก</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50 p-4">
            <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md md:max-w-lg lg:max-w-xl max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center space-x-2 mb-4">
                        <i class="far fa-edit text-blue-600 text-xl"></i>
                        <h3 class="text-xl font-bold text-gray-800">แก้ไขข้อมูลสินค้า</h3>
                    </div>
                    <form id="editForm" method="POST" class="space-y-4">
                        <input type="hidden" id="editProductId" name="id">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">รหัสสินค้า</label>
                            <input type="text" id="editProductIdDisplay" disabled
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700"
                                aria-describedby="editIdError">
                            <span id="editIdError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ชื่อสินค้า</label>
                            <input type="text" id="editProductName" name="update_product_name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="editNameError">
                            <span id="editNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 1</label>
                            <input type="number" step="0.01" id="editProductPrice1" name="update_product_price1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="editPrice1Error">
                            <span id="editPrice1Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 2</label>
                            <input type="number" step="0.01" id="editProductPrice2" name="update_product_price2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="editPrice2Error">
                            <span id="editPrice2Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 3</label>
                            <input type="number" step="0.01" id="editProductPrice3" name="update_product_price3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="editPrice3Error">
                            <span id="editPrice3Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 4</label>
                            <input type="number" step="0.01" id="editProductPrice4" name="update_product_price4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="editPrice4Error">
                            <span id="editPrice4Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาทุน</label>
                            <input type="number" step="0.01" id="editProductCapital" name="update_product_capital"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="editCapitalError">
                            <span id="editCapitalError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ประเภทสินค้า</label>
                            <select id="editProductTypeId" name="update_product_type_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="editTypeError">
                                <option value="">เลือกประเภทสินค้า</option>
                                <?php foreach ($product_types as $ptype): ?>
                                    <option value="<?php echo htmlspecialchars($ptype['product_type_id']); ?>">
                                        <?php echo htmlspecialchars($ptype['product_type_name']); ?>
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
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50 p-4">
            <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md">
                <div class="p-6">
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
    </div>

    <script src="/page/product/function/product_typegroup.js"></script>
</body>
</html>