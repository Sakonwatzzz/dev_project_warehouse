<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Set timezone
date_default_timezone_set('Asia/Bangkok');

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Session timeout (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// CSRF Verification
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fetch product data
function fetchProductData($pdo, $id)
{
    $stmt = $pdo->prepare("
        SELECT product.*, product_type.product_type_id, product_type.product_type_name, 
               product_brand.product_brand_id, product_brand.product_brand_name 
        FROM product 
        LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id 
        LEFT JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id 
        WHERE product.product_id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: ?page=product_list&error=" . urlencode('ระบุ ID ไม่ถูกต้อง'));
    exit;
}

$id = trim($_GET['id']);
$product = fetchProductData($pdo, $id);
if (!$product) {
    header("Location: ?page=product_list&error=" . urlencode('ไม่พบข้อมูลสินค้า'));
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: ?page=product_update&tabel=product&id=' . urlencode($id) . '&error=' . urlencode('CSRF Token ไม่ถูกต้อง'));
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'addtype') {
        $product_type = trim($_POST['product_type_name']);
        $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);
        $current_timestamp = date('Y-m-d H:i:s');

        $errors = [];
        if (empty($product_type) || strlen($product_type) > 255) {
            $errors[] = 'ชื่อประเภทสินค้าต้องไม่ว่างและไม่เกิน 255 ตัวอักษร';
        }
        if (!$personnel_id) {
            $errors[] = 'กรุณาเลือกพนักงาน';
        }

        if (!empty($errors)) {
            header('Location: ?page=product_update&tabel=product&id=' . urlencode($id) . '&add_error=' . urlencode(implode(', ', $errors)));
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO product_type (product_type_name, product_type_date, personnel_id) VALUES (?, ?, ?)');
            $stmt->execute([$product_type, $current_timestamp, $personnel_id]);
            $new_type_id = $pdo->lastInsertId();
            header('Location: ?page=product_update&tabel=product&id=' . urlencode($id) . '&new_type_id=' . urlencode($new_type_id));
            exit;
        } catch (PDOException $e) {
            error_log("Add product type error: " . $e->getMessage());
            header('Location: ?page=product_update&tabel=product&id=' . urlencode($id) . '&add_error=' . urlencode('เกิดข้อผิดพลาดทางฐานข้อมูล'));
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'addbrand') {
        $product_brand = trim($_POST['product_brand_name']);
        $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);
        $current_timestamp = date('Y-m-d H:i:s');

        $errors = [];
        if (empty($product_brand) || strlen($product_brand) > 255) {
            $errors[] = 'ชื่อแบรนด์สินค้าต้องไม่ว่างและไม่เกิน 255 ตัวอักษร';
        }
        if (!$personnel_id) {
            $errors[] = 'กรุณาเลือกพนักงาน';
        }

        if (!empty($errors)) {
            header('Location: ?page=product_update&tabel=product&id=' . urlencode($id) . '&add_error=' . urlencode(implode(', ', $errors)));
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO product_brand (product_brand_name, product_brand_date, personnel_id) VALUES (?, ?, ?)');
            $stmt->execute([$product_brand, $current_timestamp, $personnel_id]);
            $new_brand_id = $pdo->lastInsertId();
            header('Location: ?page=product_update&tabel=product&id=' . urlencode($id) . '&new_brand_id=' . urlencode($new_brand_id));
            exit;
        } catch (PDOException $e) {
            error_log("Add product brand error: " . $e->getMessage());
            header('Location: ?page=product_update&tabel=product&id=' . urlencode($id) . '&add_error=' . urlencode('เกิดข้อผิดพลาดทางฐานข้อมูล'));
            exit;
        }
    } elseif (isset($_POST['product_id'])) {
        $name = trim($_POST['name']);
        $price_1 = floatval($_POST['price_1']);
        $price_2 = floatval($_POST['price_2']);
        $price_3 = floatval($_POST['price_3']);
        $price_4 = floatval($_POST['price_4']);
        $capital = floatval($_POST['capital']);
        $typename = filter_var($_POST['typename'], FILTER_VALIDATE_INT);
        $typebrandname = filter_var($_POST['typebrandname'], FILTER_VALIDATE_INT);

        $errors = [];
        if (empty($name) || strlen($name) > 255) {
            $errors[] = 'ชื่อสินค้าต้องไม่ว่างและไม่เกิน 255 ตัวอักษร';
        }
        if ($price_1 < 0 || $price_2 < 0 || $price_3 < 0 || $price_4 < 0 || $capital < 0) {
            $errors[] = 'ราคาและราคาทุนต้องไม่เป็นค่าลบ';
        }
        if (!$typename) {
            $errors[] = 'กรุณาเลือกประเภทสินค้า';
        }
        if (!$typebrandname) {
            $errors[] = 'กรุณาเลือกแบรนด์สินค้า';
        }

        if (!empty($errors)) {
            header('Location: ?page=product_update&tabel=product&id=' . urlencode($id) . '&error=' . urlencode(implode(', ', $errors)));
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE product 
                SET product_name = ?, product_price1 = ?, product_price2 = ?, 
                    product_price3 = ?, product_price4 = ?, product_capital = ?, 
                    product_type_id = ?, product_brand_id = ? 
                WHERE product_id = ?
            ");
            $stmt->execute([$name, $price_1, $price_2, $price_3, $price_4, $capital, $typename, $typebrandname, $id]);

            header("Location: ?page=product_detail&tabel=product&id=" . urlencode($id) . "&success=1");
            exit;
        } catch (PDOException $e) {
            error_log("Update product error: " . $e->getMessage());
            header("Location: ?page=product_update&tabel=product&id=" . urlencode($id) . "&error=" . urlencode('เกิดข้อผิดพลาดขณะอัปเดตสินค้า'));
            exit;
        }
    }
}

// Fetch dropdown data
try {
    $typeResults = $pdo->query("SELECT product_type_id, product_type_name, product_type_date FROM product_type ORDER BY product_type_name");
    $brandResults = $pdo->query("SELECT product_brand_id, product_brand_name, product_brand_date FROM product_brand ORDER BY product_brand_name");
    $personnelResults = $pdo->query("SELECT personnel_id, personnel_name FROM personnel ORDER BY personnel_name");

    $types = $typeResults->fetchAll(PDO::FETCH_ASSOC);
    $brands = $brandResults->fetchAll(PDO::FETCH_ASSOC);
    $personnel = $personnelResults->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database fetch error: " . $e->getMessage());
    $types = $brands = $personnel = [];
}

$new_type_id = isset($_GET['new_type_id']) ? filter_var($_GET['new_type_id'], FILTER_VALIDATE_INT) : null;
$new_brand_id = isset($_GET['new_brand_id']) ? filter_var($_GET['new_brand_id'], FILTER_VALIDATE_INT) : null;
$errorMessage = isset($_GET['add_error']) ? urldecode($_GET['add_error']) : (isset($_GET['error']) ? urldecode($_GET['error']) : null);

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>แก้ไขข้อมูลสินค้า | ระบบจัดการข้อมูล</title>
    <style>
        .searchable-select {
            position: relative;
        }

        .searchable-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background-color: white;
            cursor: text;
        }

        .searchable-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .dropdown-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
        }

        .dropdown-item:hover {
            background-color: #f3f4f6;
        }

        .dropdown-item.selected {
            background-color: #dbeafe;
            color: #1d4ed8;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .no-results {
            padding: 8px 12px;
            color: #6b7280;
            font-style: italic;
        }

        .dropdown-arrow {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            transition: transform 0.2s;
        }

        .dropdown-arrow.open {
            transform: translateY(-50%) rotate(180deg);
        }
    </style>
</head>

<body class="font-sans bg-gray-50 text-gray-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">แก้ไขข้อมูลสินค้า</h1>
            <p class="text-gray-600">แก้ไขข้อมูลสินค้าในระบบ</p>
        </div>

        <!-- Form Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
            <form id="productForm" class="space-y-4" action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">

                <!-- รหัสสินค้า -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="product_id" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">รหัสสินค้า:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="product_id_display" id="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>" disabled
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed">
                    </div>
                </div>

                <!-- ชื่อสินค้า -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="name" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ชื่อสินค้า:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product['product_name']); ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="nameError" class="text-rose-500 text-xs mt-1 hidden">ชื่อสินค้าต้องไม่ว่างและไม่เกิน 255 ตัวอักษร</span>
                    </div>
                </div>

                <!-- ราคาที่ 1 -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="price_1" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ราคาที่ 1:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="number" name="price_1" id="price_1" step="0.01" value="<?php echo htmlspecialchars($product['product_price1']); ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="price1Error" class="text-rose-500 text-xs mt-1 hidden">ราคาที่ 1 ต้องไม่เป็นค่าลบ</span>
                    </div>
                </div>

                <!-- ราคาที่ 2 -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="price_2" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ราคาที่ 2:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="number" name="price_2" id="price_2" step="0.01" value="<?php echo htmlspecialchars($product['product_price2']); ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="price2Error" class="text-rose-500 text-xs mt-1 hidden">ราคาที่ 2 ต้องไม่เป็นค่าลบ</span>
                    </div>
                </div>

                <!-- ราคาที่ 3 -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="price_3" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ราคาที่ 3:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="number" name="price_3" id="price_3" step="0.01" value="<?php echo htmlspecialchars($product['product_price3']); ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="price3Error" class="text-rose-500 text-xs mt-1 hidden">ราคาที่ 3 ต้องไม่เป็นค่าลบ</span>
                    </div>
                </div>

                <!-- ราคาที่ 4 -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="price_4" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ราคาที่ 4:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="number" name="price_4" id="price_4" step="0.01" value="<?php echo htmlspecialchars($product['product_price4']); ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="price4Error" class="text-rose-500 text-xs mt-1 hidden">ราคาที่ 4 ต้องไม่เป็นค่าลบ</span>
                    </div>
                </div>

                <!-- ราคาทุน -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="capital" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ราคาทุน:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="number" name="capital" id="capital" step="0.01" value="<?php echo htmlspecialchars($product['product_capital']); ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="capitalError" class="text-rose-500 text-xs mt-1 hidden">ราคาทุนต้องไม่เป็นค่าลบ</span>
                    </div>
                </div>

                <!-- ประเภทสินค้า -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="typeSearch" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">
                        <a href="?page=product_type" class="text-indigo-600 hover:underline">ประเภทสินค้า:</a>
                    </label>
                    <div class="w-full sm:w-3/4 flex items-center gap-2">
                        <div class="searchable-select w-full">
                            <input type="text" id="typeSearch" class="searchable-input" placeholder="พิมพ์เพื่อค้นหาประเภทสินค้า..." autocomplete="off">
                            <div class="dropdown-arrow">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            <div id="typeDropdown" class="dropdown-menu hidden"></div>
                            <input type="hidden" name="typename" id="typename" value="<?php echo htmlspecialchars($product['product_type_id']); ?>" required>
                        </div>
                        <button type="button" onclick="showAddTypeModal()"
                            class="px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            เพิ่ม
                        </button>
                    </div>
                    <span id="typenameError" class="w-full sm:w-3/4 sm:ml-[25%] text-rose-500 text-xs mt-1 hidden">กรุณาเลือกประเภทสินค้า</span>
                </div>

                <!-- แบรนด์สินค้า -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="brandSearch" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">
                        <a href="?page=product_brand" class="text-indigo-600 hover:underline">แบรนด์สินค้า:</a>
                    </label>
                    <div class="w-full sm:w-3/4 flex items-center gap-2">
                        <div class="searchable-select w-full">
                            <input type="text" id="brandSearch" class="searchable-input" placeholder="พิมพ์เพื่อค้นหาแบรนด์สินค้า..." autocomplete="off">
                            <div class="dropdown-arrow">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            <div id="brandDropdown" class="dropdown-menu hidden"></div>
                            <input type="hidden" name="typebrandname" id="typebrandname" value="<?php echo htmlspecialchars($product['product_brand_id']); ?>" required>
                        </div>
                        <button type="button" onclick="showAddBrandModal()"
                            class="px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            เพิ่ม
                        </button>
                    </div>
                    <span id="typebrandnameError" class="w-full sm:w-3/4 sm:ml-[25%] text-rose-500 text-xs mt-1 hidden">กรุณาเลือกแบรนด์สินค้า</span>
                </div>

                <!-- ปุ่ม -->
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" onclick="showCancelModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        ยกเลิก
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>

        <!-- Cancel Confirmation Modal -->
        <div id="cancelModal"
            class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
            <div class="bg-white rounded-lg w-full max-w-md p-6 relative transform scale-95 transition-all duration-300">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">ยืนยันการยกเลิก</h3>
                    <p class="text-sm text-gray-500 mb-6">คุณแน่ใจหรือไม่ว่าต้องการยกเลิก? การเปลี่ยนแปลงจะไม่ถูกบันทึก</p>

                    <div class="flex justify-center gap-3">
                        <button type="button" onclick="closeCancelModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            อยู่ต่อ
                        </button>
                        <a href="/?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product['product_id']); ?>"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                            ยกเลิก
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Product Type Modal -->
        <div id="addTypeModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300">
            <div class="bg-white rounded-lg w-full max-w-md p-6 relative transform scale-95 transition-all duration-300">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">เพิ่มประเภทสินค้า</h3>
                    <button type="button" onclick="closeAddTypeModal()" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="addTypeForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="addtype">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label for="addTypeName" class="block text-sm font-medium text-gray-700 mb-1">ชื่อประเภทสินค้า</label>
                        <input type="text" id="addTypeName" name="product_type_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none" required>
                        <span id="addTypeNameError" class="text-rose-500 text-xs mt-1 hidden">ชื่อประเภทสินค้าต้องไม่ว่างและไม่เกิน 255 ตัวอักษร</span>
                    </div>
                    <div>
                        <label for="personnelSearchType" class="block text-sm font-medium text-gray-700 mb-1">พนักงาน</label>
                        <div class="searchable-select w-full">
                            <input type="text" id="personnelSearchType" class="searchable-input w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="พิมพ์เพื่อค้นหาพนักงาน..." autocomplete="off">
                            <div class="dropdown-arrow">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            <div id="personnelDropdownType" class="dropdown-menu hidden"></div>
                            <input type="hidden" name="personnel_id" id="personnel_id_type" required>
                        </div>
                        <span id="personnelTypeError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกพนักงาน</span>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" onclick="closeAddTypeModal()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            ยกเลิก
                        </button>
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                            บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Product Brand Modal -->
        <div id="addBrandModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300">
            <div class="bg-white rounded-lg w-full max-w-md p-6 relative transform scale-95 transition-all duration-300">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">เพิ่มแบรนด์สินค้า</h3>
                    <button type="button" onclick="closeAddBrandModal()" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="addBrandForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="addbrand">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label for="addBrandName" class="block text-sm font-medium text-gray-700 mb-1">ชื่อแบรนด์สินค้า</label>
                        <input type="text" id="addBrandName" name="product_brand_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none" required>
                        <span id="addBrandNameError" class="text-rose-500 text-xs mt-1 hidden">ชื่อแบรนด์สินค้าต้องไม่ว่างและไม่เกิน 255 ตัวอักษร</span>
                    </div>
                    <div>
                        <label for="personnelSearchBrand" class="block text-sm font-medium text-gray-700 mb-1">พนักงาน</label>
                        <div class="searchable-select w-full">
                            <input type="text" id="personnelSearchBrand" class="searchable-input w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="พิมพ์เพื่อค้นหาพนักงาน..." autocomplete="off">
                            <div class="dropdown-arrow">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            <div id="personnelDropdownBrand" class="dropdown-menu hidden"></div>
                            <input type="hidden" name="personnel_id" id="personnel_id_brand" required>
                        </div>
                        <span id="personnelBrandError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกพนักงาน</span>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" onclick="closeAddBrandModal()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            ยกเลิก
                        </button>
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                            บันทึก
                        </button>
                    </div>
                </form>
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
    </div>

    <script>
        const productTypes = [
            <?php
            $types_js = [];
            foreach ($types as $type) {
                $types_js[] = "{ id: " . $type['product_type_id'] . ", name: '" . addslashes($type['product_type_name']) . "' }";
            }
            echo implode(',', $types_js);
            ?>
        ];

        const productBrands = [
            <?php
            $brands_js = [];
            foreach ($brands as $brand) {
                $brands_js[] = "{ id: " . $brand['product_brand_id'] . ", name: '" . addslashes($brand['product_brand_name']) . "' }";
            }
            echo implode(',', $brands_js);
            ?>
        ];

        const personnel = [
            <?php
            $personnel_js = [];
            foreach ($personnel as $person) {
                $personnel_js[] = "{ id: " . $person['personnel_id'] . ", name: '" . addslashes($person['personnel_name']) . "' }";
            }
            echo implode(',', $personnel_js);
            ?>
        ];

        class SearchableSelect {
            constructor(searchInputId, dropdownId, hiddenInputId, data, placeholder) {
                this.searchInput = document.getElementById(searchInputId);
                this.dropdown = document.getElementById(dropdownId);
                this.hiddenInput = document.getElementById(hiddenInputId);
                this.data = data;
                this.placeholder = placeholder;
                this.selectedItem = null;
                this.filteredData = [...data];
                this.highlightedIndex = -1;

                if (!this.searchInput || !this.dropdown || !this.hiddenInput) {
                    console.error('SearchableSelect initialization failed: Elements not found');
                    return;
                }

                this.init();
            }

            init() {
                this.setupEventListeners();
                this.renderDropdown();
                this.searchInput.placeholder = this.placeholder;
            }

            setupEventListeners() {
                this.searchInput.addEventListener('input', (e) => {
                    this.handleSearch(e.target.value);
                });

                this.searchInput.addEventListener('focus', () => {
                    this.showDropdown();
                });

                this.searchInput.addEventListener('blur', (e) => {
                    setTimeout(() => {
                        if (!this.dropdown.contains(document.activeElement)) {
                            this.hideDropdown();
                        }
                    }, 150);
                });

                this.searchInput.addEventListener('keydown', (e) => {
                    this.handleKeydown(e);
                });

                const arrow = this.searchInput.parentElement.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.addEventListener('click', () => {
                        if (this.dropdown.classList.contains('hidden')) {
                            this.searchInput.focus();
                            this.showDropdown();
                        } else {
                            this.hideDropdown();
                        }
                    });
                }

                document.addEventListener('click', (e) => {
                    if (!this.searchInput.parentElement.contains(e.target)) {
                        this.hideDropdown();
                    }
                });
            }

            handleSearch(query) {
                this.filteredData = this.data.filter(item =>
                    item.name.toLowerCase().includes(query.toLowerCase())
                );
                this.highlightedIndex = -1;
                this.renderDropdown();
                this.showDropdown();
            }

            handleKeydown(e) {
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.highlightedIndex = Math.min(this.highlightedIndex + 1, this.filteredData.length - 1);
                        this.updateHighlight();
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        this.highlightedIndex = Math.max(this.highlightedIndex - 1, -1);
                        this.updateHighlight();
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (this.highlightedIndex >= 0) {
                            this.selectItem(this.filteredData[this.highlightedIndex]);
                        }
                        break;
                    case 'Escape':
                        this.hideDropdown();
                        break;
                }
            }

            updateHighlight() {
                const items = this.dropdown.querySelectorAll('.dropdown-item');
                items.forEach((item, index) => {
                    if (index === this.highlightedIndex) {
                        item.classList.add('selected');
                        item.scrollIntoView({
                            block: 'nearest'
                        });
                    } else {
                        item.classList.remove('selected');
                    }
                });
            }

            renderDropdown() {
                this.dropdown.innerHTML = '';

                if (this.filteredData.length === 0) {
                    const noResults = document.createElement('div');
                    noResults.className = 'no-results';
                    noResults.textContent = 'ไม่พบข้อมูล';
                    this.dropdown.appendChild(noResults);
                    return;
                }

                this.filteredData.forEach((item, index) => {
                    const option = document.createElement('div');
                    option.className = 'dropdown-item';
                    option.textContent = item.name;
                    option.dataset.value = item.id;
                    option.dataset.index = index;

                    option.addEventListener('click', () => {
                        this.selectItem(item);
                    });

                    option.addEventListener('mouseenter', () => {
                        this.highlightedIndex = index;
                        this.updateHighlight();
                    });

                    this.dropdown.appendChild(option);
                });
            }

            selectItem(item) {
                this.selectedItem = item;
                this.searchInput.value = item.name;
                this.hiddenInput.value = item.id;
                this.hideDropdown();

                this.searchInput.classList.remove('border-red-500');
                this.hiddenInput.classList.remove('border-red-500');
            }

            showDropdown() {
                this.dropdown.classList.remove('hidden');
                const arrow = this.searchInput.parentElement.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.add('open');
                }
            }

            hideDropdown() {
                this.dropdown.classList.add('hidden');
                const arrow = this.searchInput.parentElement.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.remove('open');
                }
            }

            setValue(id) {
                const item = this.data.find(item => item.id == id);
                if (item) {
                    this.selectItem(item);
                }
            }

            reset() {
                this.searchInput.value = '';
                this.hiddenInput.value = '';
                this.selectedItem = null;
                this.filteredData = [...this.data];
                this.hideDropdown();
            }
        }

        function showCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.classList.remove('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
        }

        function closeCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.classList.add('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
        }

        function showAddTypeModal() {
            const modal = document.getElementById('addTypeModal');
            const addTypeName = document.getElementById('addTypeName');
            if (addTypeName) addTypeName.value = '';
            if (personnelSelectType) personnelSelectType.reset();
            modal.classList.remove('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
            setTimeout(() => addTypeName.focus(), 100);
        }

        function closeAddTypeModal() {
            const modal = document.getElementById('addTypeModal');
            modal.classList.add('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
        }

        function showAddBrandModal() {
            const modal = document.getElementById('addBrandModal');
            const addBrandName = document.getElementById('addBrandName');
            if (addBrandName) addBrandName.value = '';
            if (personnelSelectBrand) personnelSelectBrand.reset();
            modal.classList.remove('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
            setTimeout(() => addBrandName.focus(), 100);
        }

        function closeAddBrandModal() {
            const modal = document.getElementById('addBrandModal');
            modal.classList.add('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
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

            setTimeout(() => hideNotification(), 5000);
        }

        function hideNotification() {
            const notification = document.getElementById('notification');
            notification.classList.add('translate-x-full');
        }

        let typeSelect, brandSelect, personnelSelectType, personnelSelectBrand;

        document.addEventListener('DOMContentLoaded', function() {
            typeSelect = new SearchableSelect('typeSearch', 'typeDropdown', 'typename', productTypes, 'พิมพ์เพื่อค้นหาประเภทสินค้า...');
            brandSelect = new SearchableSelect('brandSearch', 'brandDropdown', 'typebrandname', productBrands, 'พิมพ์เพื่อค้นหาแบรนด์สินค้า...');
            personnelSelectType = new SearchableSelect('personnelSearchType', 'personnelDropdownType', 'personnel_id_type', personnel, 'พิมพ์เพื่อค้นหาพนักงาน...');
            personnelSelectBrand = new SearchableSelect('personnelSearchBrand', 'personnelDropdownBrand', 'personnel_id_brand', personnel, 'พิมพ์เพื่อค้นหาพนักงาน...');

            // Set initial values
            typeSelect.setValue(<?php echo json_encode($new_type_id ?: $product['product_type_id']); ?>);
            brandSelect.setValue(<?php echo json_encode($new_brand_id ?: $product['product_brand_id']); ?>);

            const productForm = document.getElementById('productForm');
            const addTypeForm = document.getElementById('addTypeForm');
            const addBrandForm = document.getElementById('addBrandForm');

            productForm.addEventListener('submit', function(e) {
                e.preventDefault();
                let hasError = false;

                const name = document.getElementById('name').value.trim();
                const price_1 = parseFloat(document.getElementById('price_1').value);
                const price_2 = parseFloat(document.getElementById('price_2').value);
                const price_3 = parseFloat(document.getElementById('price_3').value);
                const price_4 = parseFloat(document.getElementById('price_4').value);
                const capital = parseFloat(document.getElementById('capital').value);
                const typename = document.getElementById('typename').value;
                const typebrandname = document.getElementById('typebrandname').value;

                if (!name || name.length > 255) {
                    document.getElementById('nameError').textContent = 'ชื่อสินค้าต้องไม่ว่างและไม่เกิน 255 ตัวอักษร';
                    document.getElementById('nameError').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('nameError').classList.add('hidden');
                }

                if (isNaN(price_1) || price_1 < 0) {
                    document.getElementById('price1Error').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('price1Error').classList.add('hidden');
                }

                if (isNaN(price_2) || price_2 < 0) {
                    document.getElementById('price2Error').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('price2Error').classList.add('hidden');
                }

                if (isNaN(price_3) || price_3 < 0) {
                    document.getElementById('price3Error').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('price3Error').classList.add('hidden');
                }

                if (isNaN(price_4) || price_4 < 0) {
                    document.getElementById('price4Error').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('price4Error').classList.add('hidden');
                }

                if (isNaN(capital) || capital < 0) {
                    document.getElementById('capitalError').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('capitalError').classList.add('hidden');
                }

                if (!typename) {
                    document.getElementById('typenameError').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('typenameError').classList.add('hidden');
                }

                if (!typebrandname) {
                    document.getElementById('typebrandnameError').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('typebrandnameError').classList.add('hidden');
                }

                if (!hasError) {
                    this.submit();
                }
            });

            addTypeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                let hasError = false;

                const typeName = document.getElementById('addTypeName').value.trim();
                const personnel = document.getElementById('personnel_id_type').value;

                if (!typeName || typeName.length > 255) {
                    document.getElementById('addTypeNameError').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('addTypeNameError').classList.add('hidden');
                }

                if (!personnel) {
                    document.getElementById('personnelTypeError').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('personnelTypeError').classList.add('hidden');
                }

                if (!hasError) {
                    this.submit();
                }
            });

            addBrandForm.addEventListener('submit', function(e) {
                e.preventDefault();
                let hasError = false;

                const brandName = document.getElementById('addBrandName').value.trim();
                const personnel = document.getElementById('personnel_id_brand').value;

                if (!brandName || brandName.length > 255) {
                    document.getElementById('addBrandNameError').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('addBrandNameError').classList.add('hidden');
                }

                if (!personnel) {
                    document.getElementById('personnelBrandError').classList.remove('hidden');
                    hasError = true;
                } else {
                    document.getElementById('personnelBrandError').classList.add('hidden');
                }

                if (!hasError) {
                    this.submit();
                }
            });

            document.getElementById('name').focus();

            <?php if ($errorMessage): ?>
                showNotification('<?php echo addslashes($errorMessage); ?>', 'bg-red-500');
            <?php elseif (isset($_GET['success'])): ?>
                showNotification('อัปเดตสินค้าสำเร็จ', 'bg-green-500');
            <?php endif; ?>

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    showCancelModal();
                }
            });

            document.getElementById('cancelModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeCancelModal();
                }
            });
        });

        function updateTypeDropdown(newType) {
            productTypes.push(newType);
            typeSelect.data = productTypes;
            typeSelect.filteredData = [...productTypes];
            typeSelect.renderDropdown();
            typeSelect.setValue(newType.id);
        }

        function updateBrandDropdown(newBrand) {
            productBrands.push(newBrand);
            brandSelect.data = productBrands;
            brandSelect.filteredData = [...productBrands];
            brandSelect.renderDropdown();
            brandSelect.setValue(newBrand.id);
        }
    </script>
</body>

</html>