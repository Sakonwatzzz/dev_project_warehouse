<?php
session_start();
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Set timezone for consistent datetime handling
date_default_timezone_set('Asia/Bangkok'); // Adjust to your desired timezone

// Generate CSRF token if not set
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

// CSRF Verification
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check for duplicate product_id
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
    $current_timestamp = date('Y-m-d H:i:s'); // Capture current datetime in YYYY-MM-DD HH:MM:SS format

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
    $current_timestamp = date('Y-m-d H:i:s'); // Capture current datetime in YYYY-MM-DD HH:MM:SS format

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

    if (checkProductIdExists($pdo, $product_id)) {
        echo "<script>
            alert('รหัสสินค้านี้มีอยู่แล้วในระบบ');
            window.history.back();
        </script>";
        exit;
    }

    $name = trim($_POST['name']);
    $price_1 = floatval($_POST['price_1']);
    $price_2 = floatval($_POST['price_2']);
    $price_3 = floatval($_POST['price_3']);
    $price_4 = floatval($_POST['price_4']);
    $capital = floatval($_POST['capital']);
    $typename = intval($_POST['typename']);
    $typebrandname = intval($_POST['typebrandname']);

    try {
        $stmt = $pdo->prepare('INSERT INTO product (
            product_id, product_name, product_price1, product_price2,
            product_price3, product_price4, product_capital,
            product_type_id, product_brand_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $stmt->execute([
            $product_id, $name, $price_1, $price_2, $price_3,
            $price_4, $capital, $typename, $typebrandname
        ]);

        header("Location: ?page=product_detail&tabel=product&id=" . urlencode($product_id));
        exit;
    } catch (PDOException $e) {
        error_log("Add product error: " . $e->getMessage());
        header("Location: ?page=product_insert&error=" . urlencode('เกิดข้อผิดพลาดขณะเพิ่มสินค้า'));
        exit;
    }
}

// Fetch dropdown data (include datetime for optional display)
try {
    $typeResults = $pdo->query("SELECT product_type_id, product_type_name, product_type_date FROM product_type ORDER BY product_type_name");
    $brandResults = $pdo->query("SELECT product_brand_id, product_brand_name, product_brand_date FROM product_brand ORDER BY product_brand_name");
    $personnelResults = $pdo->query("SELECT personnel_id, personnel_name FROM personnel ORDER BY personnel_name");
} catch (PDOException $e) {
    error_log("Database fetch error: " . $e->getMessage());
    $typeResults = $brandResults = $personnelResults = [];
}

// Get new type/brand IDs from URL for pre-selection
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
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <h2 class="text-gray-800 text-2xl font-bold mt-8 mb-4">เพิ่มสินค้า</h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        กรอกข้อมูลสินค้า
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST" id="productForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <!-- Product Fields -->
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
                                <select name="typename" id="typename" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <?php while ($typeRow = $typeResults->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($typeRow['product_type_id']); ?>" <?php echo ($new_type_id && $typeRow['product_type_id'] == $new_type_id) ? 'selected' : ''; ?>>
                                            <?php 
                                            // Optionally display datetime in dropdown (comment out if not needed)
                                            echo htmlspecialchars($typeRow['product_type_name']); 
                                            // echo htmlspecialchars($typeRow['product_type_name'] . ' (' . $typeRow['product_type_date'] . ')'); 
                                            ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
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
                                <select name="typebrandname" id="typebrandname" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <?php while ($brandRow = $brandResults->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($brandRow['product_brand_id']); ?>" <?php echo ($new_brand_id && $brandRow['product_brand_id'] == $new_brand_id) ? 'selected' : ''; ?>>
                                            <?php 
                                            // Optionally display datetime in dropdown (comment out if not needed)
                                            echo htmlspecialchars($brandRow['product_brand_name']); 
                                            // echo htmlspecialchars($brandRow['product_brand_name'] . ' (' . $brandRow['product_brand_date'] . ')'); 
                                            ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
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

    <!-- Modal for validation messages -->
    <div id="modalContainer" class="hidden fixed inset-0 bg-black bg-opacity-50  items-center justify-center z-50 ">
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

    <!-- Add Product Type Modal -->
    <div id="addTypeModal" class="hidden fixed inset-0 bg-black bg-opacity-50  items-center justify-center z-50 ">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md mx-auto w-full transform transition-all scale-95">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">เพิ่มประเภทสินค้า</h3>
                <button type="button" onclick="closeAddTypeModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form id="addTypeForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="addtype">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div>
                    <label for="addTypeName" class="block text-sm font-medium text-gray-700 mb-1">ชื่อประเภทสินค้า</label>
                    <input type="text" id="addTypeName" name="product_type_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                    <span id="addtypeNameError" class="text-rose-500 text-xs mt-1 hidden">กรุณากรอกชื่อประเภทสินค้า</span>
                </div>
                <div>
                    <label for="personnel_id_type" class="block text-sm font-medium text-gray-700 mb-1">พนักงาน</label>
                    <select name="personnel_id" id="personnel_id_type" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required>
                        <?php 
                        $personnelResults->execute();
                        while ($personnelRow = $personnelResults->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo htmlspecialchars($personnelRow['personnel_id']); ?>">
                                <?php echo htmlspecialchars($personnelRow['personnel_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <span id="personnelIdTypeError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกพนักงาน</span>
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

    <!-- Add Product Brand Modal -->
    <div id="addBrandModal" class="hidden fixed inset-0 bg-black bg-opacity-50  items-center justify-center z-50 ">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md mx-auto w-full transform transition-all scale-95">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">เพิ่มแบรนด์สินค้า</h3>
                <button type="button" onclick="closeAddBrandModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form id="addBrandForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="addbrand">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div>
                    <label for="addBrandName" class="block text-sm font-medium text-gray-700 mb-1">ชื่อแบรนด์สินค้า</label>
                    <input type="text" id="addBrandName" name="product_brand_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                    <span id="addBrandNameError" class="text-rose-500 text-xs mt-1 hidden">กรุณากรอกชื่อแบรนด์สินค้า</span>
                </div>
                <div>
                    <label for="personnel_id_brand" class="block text-sm font-medium text-gray-700 mb-1">พนักงาน</label>
                    <select name="personnel_id" id="personnel_id_brand" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required>
                        <?php 
                        $personnelResults->execute();
                        while ($personnelRow = $personnelResults->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo htmlspecialchars($personnelRow['personnel_id']); ?>">
                                <?php echo htmlspecialchars($personnelRow['personnel_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <span id="personnelIdBrandError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกพนักงาน</span>
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
        function showAddTypeModal() {
            document.getElementById('addTypeName').value = '';
            document.getElementById('personnel_id_type').value = '';
            document.getElementById('addtypeNameError').classList.add('hidden');
            document.getElementById('personnelIdTypeError').classList.add('hidden');
            document.getElementById('addTypeModal').classList.remove('hidden');
            document.getElementById('addTypeModal').querySelector('.transform').classList.remove('scale-95');
            document.getElementById('addTypeModal').querySelector('.transform').classList.add('scale-100');
        }

        function closeAddTypeModal() {
            document.getElementById('addTypeModal').classList.add('hidden');
            document.getElementById('addTypeModal').querySelector('.transform').classList.remove('scale-100');
            document.getElementById('addTypeModal').querySelector('.transform').classList.add('scale-95');
        }

        function showAddBrandModal() {
            document.getElementById('addBrandName').value = '';
            document.getElementById('personnel_id_brand').value = '';
            document.getElementById('addBrandNameError').classList.add('hidden');
            document.getElementById('personnelIdBrandError').classList.add('hidden');
            document.getElementById('addBrandModal').classList.remove('hidden');
            document.getElementById('addBrandModal').querySelector('.transform').classList.remove('scale-95');
            document.getElementById('addBrandModal').querySelector('.transform').classList.add('scale-100');
        }

        function closeAddBrandModal() {
            document.getElementById('addBrandModal').classList.add('hidden');
            document.getElementById('addBrandModal').querySelector('.transform').classList.remove('scale-100');
            document.getElementById('addBrandModal').querySelector('.transform').classList.add('scale-95');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('productForm');
            const productIdInput = document.getElementById('product_id');
            const modalContainer = document.getElementById('modalContainer');
            const modalButton = document.getElementById('modalButton');
            const addTypeForm = document.getElementById('addTypeForm');
            const addBrandForm = document.getElementById('addBrandForm');

            function showModal(title, message) {
                const modalTitle = document.getElementById('modalTitle');
                const modalMessage = document.getElementById('modalMessage');
                modalTitle.textContent = title;
                modalMessage.textContent = message;
                modalContainer.classList.remove('hidden');
            }

            function closeModal() {
                modalContainer.classList.add('hidden');
                productIdInput.focus();
            }

            modalButton.addEventListener('click', closeModal);

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !modalContainer.classList.contains('hidden')) {
                    closeModal();
                }
            });

            async function checkProductId(productId) {
                try {
                    const response = await fetch(`?action=check_product_id&product_id=${encodeURIComponent(productId)}`);
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    const data = await response.json();
                    return data.exists;
                } catch (error) {
                    console.error('Error checking product ID:', error);
                    return false;
                }
            }

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const productId = productIdInput.value.trim();

                if (!productId) {
                    showModal('แจ้งเตือน', 'กรุณากรอกรหัสสินค้า');
                    productIdInput.classList.add('border-red-500', 'bg-red-50');
                    return;
                }

                const exists = await checkProductId(productId);

                if (exists) {
                    showModal('แจ้งเตือน', 'รหัสสินค้านี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบและแก้ไขใหม่');
                    productIdInput.classList.add('border-red-500', 'bg-red-50');
                    productIdInput.select();
                } else {
                    form.submit();
                }
            });

            addTypeForm.addEventListener('submit', function(e) {
                const name = document.getElementById('addTypeName').value.trim();
                const personnelId = document.getElementById('personnel_id_type').value;
                const nameError = document.getElementById('addtypeNameError');
                const personnelError = document.getElementById('personnelIdTypeError');

                let hasError = false;
                if (!name) {
                    nameError.classList.remove('hidden');
                    hasError = true;
                } else {
                    nameError.classList.add('hidden');
                }
                if (!personnelId) {
                    personnelError.classList.remove('hidden');
                    hasError = true;
                } else {
                    personnelError.classList.add('hidden');
                }

                if (hasError) {
                    e.preventDefault();
                }
            });

            addBrandForm.addEventListener('submit', function(e) {
                const name = document.getElementById('addBrandName').value.trim();
                const personnelId = document.getElementById('personnel_id_brand').value;
                const nameError = document.getElementById('addBrandNameError');
                const personnelError = document.getElementById('personnelIdBrandError');

                let hasError = false;
                if (!name) {
                    nameError.classList.remove('hidden');
                    hasError = true;
                } else {
                    nameError.classList.add('hidden');
                }
                if (!personnelId) {
                    personnelError.classList.remove('hidden');
                    hasError = true;
                } else {
                    personnelError.classList.add('hidden');
                }

                if (hasError) {
                    e.preventDefault();
                }
            });

            productIdInput.addEventListener('input', function() {
                this.classList.remove('border-red-500', 'bg-red-50');
            });

            // Show error modal if present
            <?php if ($errorMessage): ?>
                showModal('แจ้งเตือน', '<?php echo addslashes($errorMessage); ?>');
            <?php endif; ?>

            productIdInput.focus();
        });
    </script>
</body>
</html>