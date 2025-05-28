<?php
session_start();
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
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check duplicate product_id
function checkProductIdExists($pdo, $product_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE product_id = ?");
    $stmt->execute([$product_id]);
    return $stmt->fetchColumn() > 0;
}

// Handle adding product type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'addtype') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: ?page=product_insert&error=' . urlencode('CSRF Token ไม่ถูกต้อง'));
        exit;
    }

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
        header('Location: ?page=product_insert&add_error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO product_type (product_type_name, product_type_date, personnel_id) VALUES (?, ?, ?)');
        $stmt->execute([$product_type, $current_timestamp, $personnel_id]);
        $new_type_id = $pdo->lastInsertId();
        header('Location: ?page=product_insert&new_type_id=' . urlencode($new_type_id));
        exit;
    } catch (PDOException $e) {
        error_log("Add product type error: " . $e->getMessage());
        header('Location: ?page=product_insert&add_error=' . urlencode('เกิดข้อผิดพลาดทางฐานข้อมูล'));
        exit;
    }
}

// Handle adding product brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'addbrand') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: ?page=product_insert&error=' . urlencode('CSRF Token ไม่ถูกต้อง'));
        exit;
    }

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
        header('Location: ?page=product_insert&add_error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO product_brand (product_brand_name, product_brand_date, personnel_id) VALUES (?, ?, ?)');
        $stmt->execute([$product_brand, $current_timestamp, $personnel_id]);
        $new_brand_id = $pdo->lastInsertId();
        header('Location: ?page=product_insert&new_brand_id=' . urlencode($new_brand_id));
        exit;
    } catch (PDOException $e) {
        error_log("Add product brand error: " . $e->getMessage());
        header('Location: ?page=product_insert&add_error=' . urlencode('เกิดข้อผิดพลาดทางฐานข้อมูล'));
        exit;
    }
}

// Handle product submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: ?page=product_insert&error=' . urlencode('CSRF Token ไม่ถูกต้อง'));
        exit;
    }

    $product_id = trim($_POST['product_id']);
    $name = trim($_POST['name']);
    $price_1 = floatval($_POST['price_1']);
    $price_2 = floatval($_POST['price_2']);
    $price_3 = floatval($_POST['price_3']);
    $price_4 = floatval($_POST['price_4']);
    $capital = floatval($_POST['capital']);
    $typename = filter_var($_POST['typename'], FILTER_VALIDATE_INT);
    $typebrandname = filter_var($_POST['typebrandname'], FILTER_VALIDATE_INT);

    $errors = [];
    if (empty($product_id) || strlen($product_id) > 50) {
        $errors[] = 'รหัสสินค้าต้องไม่ว่างและไม่เกิน 50 ตัวอักษร';
    }
    if (checkProductIdExists($pdo, $product_id)) {
        $errors[] = 'รหัสสินค้านี้มีอยู่แล้วในระบบ';
    }
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
        header('Location: ?page=product_insert&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO product (
            product_id, product_name, product_price1, product_price2,
            product_price3, product_price4, product_capital,
            product_type_id, product_brand_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        $stmt->execute([
            $product_id,
            $name,
            $price_1,
            $price_2,
            $price_3,
            $price_4,
            $capital,
            $typename,
            $typebrandname
        ]);

        header("Location: ?page=product_detail&tabel=product&id=" . urlencode($product_id));
        exit;
    } catch (PDOException $e) {
        error_log("Add product error: " . $e->getMessage());
        header("Location: ?page=product_insert&error=" . urlencode('เกิดข้อผิดพลาดขณะเพิ่มสินค้า'));
        exit;
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
    <link href="/output.css" rel="stylesheet">
    <title>เพิ่มสินค้า</title>
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

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4 md:pt-8">
        <div class="flex flex-col lg:flex-row gap-6">
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#d7e1ec] to-[#e6effb] p-4 border-b border-[#cfd5dc] text-gray-800 text-xl font-bold rounded-t-lg">
                        เพิ่มข้อมูลสินค้า
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST" id="productForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">รหัสสินค้า:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="text" name="product_id" id="product_id" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required autocomplete="off" autofocus>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ชื่อสินค้า:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="text" name="name" id="name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาที่ 1:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" name="price_1" id="price_1" step="0.01" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาที่ 2:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" name="price_2" id="price_2" step="0.01" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาที่ 3:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" name="price_3" id="price_3" step="0.01" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาที่ 4:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" name="price_4" id="price_4" step="0.01" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาทุน:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" name="capital" id="capital" step="0.01" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=product_type" class="text-blue-600 hover:underline">ประเภทสินค้า:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <div class="searchable-select w-full">
                                    <input type="text" 
                                           id="typeSearch" 
                                           class="searchable-input" 
                                           placeholder="พิมพ์เพื่อค้นหาประเภทสินค้า..." 
                                           autocomplete="off">
                                    <div class="dropdown-arrow">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                    <div id="typeDropdown" class="dropdown-menu hidden"></div>
                                    <input type="hidden" name="typename" id="typename" required>
                                </div>
                                <button type="button" onclick="showAddTypeModal()" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                                    เพิ่ม
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=product_brand" class="text-blue-600 hover:underline">แบรนด์สินค้า:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <div class="searchable-select w-full">
                                    <input type="text" 
                                           id="brandSearch" 
                                           class="searchable-input" 
                                           placeholder="พิมพ์เพื่อค้นหาแบรนด์สินค้า..." 
                                           autocomplete="off">
                                    <div class="dropdown-arrow">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                    <div id="brandDropdown" class="dropdown-menu hidden"></div>
                                    <input type="hidden" name="typebrandname" id="typebrandname" required>
                                </div>
                                <button type="button" onclick="showAddBrandModal()" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                                    เพิ่ม
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-4 mt-6">
                            <a href="/?page=product_list" class="mt-4 bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 transition duration-200">
                                ยกเลิก
                            </a>
                            <button type="submit" class="mt-4 bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 transition duration-200">
                                บันทึก
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="modalContainer" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md mx-auto">
            <div class="text-lg font-bold mb-4" id="modalTitle">แจ้งเตือน</div>
            <div class="mb-6" id="modalMessage">ข้อความแจ้งเตือน</div>
            <div class="flex justify-end">
                <button id="modalButton" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition duration-200">
                    ตกลง
                </button>
            </div>
        </div>
    </div>

    <div id="addTypeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md mx-auto w-full transform transition-all scale-95">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">เพิ่มประเภทสินค้า</h3>
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
                    <input type="text" id="addTypeName" name="product_type_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                </div>
                <div>
                    <label for="personnel_id_type" class="block text-sm font-medium text-gray-700 mb-1">พนักงาน</label>
                    <div class="searchable-select w-full">
                        <input type="text" 
                               id="personnelSearchType" 
                               class="searchable-input w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                               placeholder="พิมพ์เพื่อค้นหาพนักงาน..." 
                               autocomplete="off">
                        <div class="dropdown-arrow">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                        <div id="personnelDropdownType" class="dropdown-menu hidden"></div>
                        <input type="hidden" name="personnel_id" id="personnel_id_type" required>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeAddTypeModal()" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 transition duration-200">
                        ยกเลิก
                    </button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="addBrandModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md mx-auto w-full transform transition-all scale-95">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">เพิ่มแบรนด์สินค้า</h3>
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
                    <input type="text" id="addBrandName" name="product_brand_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                </div>
                <div>
                    <label for="personnel_id_brand" class="block text-sm font-medium text-gray-700 mb-1">พนักงาน</label>
                    <div class="searchable-select w-full">
                        <input type="text" 
                               id="personnelSearchBrand" 
                               class="searchable-input w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                               placeholder="พิมพ์เพื่อค้นหาพนักงาน..." 
                               autocomplete="off">
                        <div class="dropdown-arrow">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                        <div id="personnelDropdownBrand" class="dropdown-menu hidden"></div>
                        <input type="hidden" name="personnel_id" id="personnel_id_brand" required>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeAddBrandModal()" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 transition duration-200">
                        ยกเลิก
                    </button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                        บันทึก
                    </button>
                </div>
            </form>
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
                        item.scrollIntoView({ block: 'nearest' });
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

        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = new SearchableSelect('typeSearch', 'typeDropdown', 'typename', productTypes, 'พิมพ์เพื่อค้นหาประเภทสินค้า...');
            const brandSelect = new SearchableSelect('brandSearch', 'brandDropdown', 'typebrandname', productBrands, 'พิมพ์เพื่อค้นหาแบรนด์สินค้า...');
            const personnelSelectType = new SearchableSelect('personnelSearchType', 'personnelDropdownType', 'personnel_id_type', personnel, 'พิมพ์เพื่อค้นหาพนักงาน...');
            const personnelSelectBrand = new SearchableSelect('personnelSearchBrand', 'personnelDropdownBrand', 'personnel_id_brand', personnel, 'พิมพ์เพื่อค้นหาพนักงาน...');

            <?php if ($new_type_id): ?>
                typeSelect.setValue(<?php echo $new_type_id; ?>);
            <?php endif; ?>
            
            <?php if ($new_brand_id): ?>
                brandSelect.setValue(<?php echo $new_brand_id; ?>);
            <?php endif; ?>

            const productForm = document.getElementById('productForm');
            const addTypeForm = document.getElementById('addTypeForm');
            const addBrandForm = document.getElementById('addBrandForm');

            productForm.addEventListener('submit', function(e) {
                let isValid = true;
                const inputs = {
                    product_id: 'รหัสสินค้า',
                    name: 'ชื่อสินค้า',
                    price_1: 'ราคาที่ 1',
                    price_2: 'ราคาที่ 2',
                    price_3: 'ราคาที่ 3',
                    price_4: 'ราคาที่ 4',
                    capital: 'ราคาทุน',
                    typename: 'ประเภทสินค้า',
                    typebrandname: 'แบรนด์สินค้า'
                };

                for (const [inputId, label] of Object.entries(inputs)) {
                    const input = document.getElementById(inputId);
                    if (!input.value) {
                        input.classList.add('border-red-500');
                        isValid = false;
                    } else {
                        input.classList.remove('border-red-500');
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                    showModal('แจ้งเตือน', 'กรุณากรอกข้อมูลให้ครบถ้วน');
                }
            });

            addTypeForm.addEventListener('submit', function(e) {
                if (!document.getElementById('addTypeName').value || !document.getElementById('personnel_id_type').value) {
                    e.preventDefault();
                    showModal('แจ้งเตือน', 'กรุณากรอกชื่อประเภทสินค้าและเลือกพนักงาน');
                }
            });

            addBrandForm.addEventListener('submit', function(e) {
                if (!document.getElementById('addBrandName').value || !document.getElementById('personnel_id_brand').value) {
                    e.preventDefault();
                    showModal('แจ้งเตือน', 'กรุณากรอกชื่อแบรนด์สินค้าและเลือกพนักงาน');
                }
            });

            window.showModal = function(title, message) {
                document.getElementById('modalTitle').textContent = title;
                document.getElementById('modalMessage').textContent = message;
                const modalContainer = document.getElementById('modalContainer');
                modalContainer.classList.remove('hidden');
                modalContainer.classList.add('flex');
                document.getElementById('modalButton').focus();
            };

            window.closeModal = function() {
                const modalContainer = document.getElementById('modalContainer');
                modalContainer.classList.add('hidden');
                modalContainer.classList.remove('flex');
            };

            document.getElementById('modalButton').addEventListener('click', closeModal);

            window.showAddTypeModal = function() {
                document.getElementById('addTypeName').value = '';
                personnelSelectType.reset();
                const modal = document.getElementById('addTypeModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.getElementById('addTypeName').focus();
            };

            window.closeAddTypeModal = function() {
                const modal = document.getElementById('addTypeModal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            window.showAddBrandModal = function() {
                document.getElementById('addBrandName').value = '';
                personnelSelectBrand.reset();
                const modal = document.getElementById('addBrandModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.getElementById('addBrandName').focus();
            };

            window.closeAddBrandModal = function() {
                const modal = document.getElementById('addBrandModal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            <?php if ($errorMessage): ?>
                showModal('แจ้งเตือน', '<?php echo addslashes($errorMessage); ?>');
            <?php endif; ?>
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