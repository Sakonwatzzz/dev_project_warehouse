<?php
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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'product_detail', 'tabel' => $_GET['tabel'] ?? '', 'id' => $_GET['id'] ?? ''] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Validate required GET parameters
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    header("Location: /?page=product_list&error=" . urlencode("ระบุประเภทหรือ ID ไม่ถูกต้อง"));
    exit;
}

$tabel = $_GET['tabel'];
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$is_update = isset($_GET['is_update']) ? (bool)$_GET['is_update'] : false;
$newTypeId = isset($_GET['new_type_id']) ? filter_var($_GET['new_type_id'], FILTER_VALIDATE_INT) : null;
$newBrandId = isset($_GET['new_brand_id']) ? filter_var($_GET['new_brand_id'], FILTER_VALIDATE_INT) : null;

// Function to validate form data
function validateProductData($name, $price1, $price2, $price3, $price4, $capital, $type, $brand)
{
    $errors = [];
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อสินค้าต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (!is_numeric($price1) || $price1 < 0) {
        $errors[] = 'ราคาขายที่ 1 ต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!is_numeric($price2) || $price2 < 0) {
        $errors[] = 'ราคาขายที่ 2 ต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!is_numeric($price3) || $price3 < 0) {
        $errors[] = 'ราคาขายที่ 3 ต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!is_numeric($price4) || $price4 < 0) {
        $errors[] = 'ราคาขายที่ 4 ต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (!is_numeric($capital) || $capital < 0) {
        $errors[] = 'ราคาทุนต้องเป็นตัวเลขที่ไม่ติดลบ';
    }
    if (empty($type) || !is_numeric($type)) {
        $errors[] = 'ต้องเลือกประเภทสินค้า';
    }
    if (empty($brand) || !is_numeric($brand)) {
        $errors[] = 'ต้องเลือกแบรนด์สินค้า';
    }
    return $errors;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add_type') {
        $newTypeName = trim($_POST['product_type_name'] ?? '');
        if (empty($newTypeName)) {
            header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&is_update=1&error=" . urlencode("ชื่อประเภทสินค้าต้องไม่ว่าง"));
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO product_type (product_type_name) VALUES (?)");
            $stmt->execute([$newTypeName]);
            $newTypeId = $pdo->lastInsertId();
            header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&is_update=1&new_type_id={$newTypeId}&success=" . urlencode("เพิ่มประเภทสินค้าสำเร็จ"));
            exit;
        } catch (PDOException $e) {
            error_log("Add product type error: " . $e->getMessage());
            header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&is_update=1&error=" . urlencode("เกิดข้อผิดพลาดในการเพิ่มประเภทสินค้า"));
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_brand') {
        $newBrandName = trim($_POST['product_brand_name'] ?? '');
        if (empty($newBrandName)) {
            header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&is_update=1&error=" . urlencode("ชื่อแบรนด์สินค้าต้องไม่ว่าง"));
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO product_brand (product_brand_name) VALUES (?)");
            $stmt->execute([$newBrandName]);
            $newBrandId = $pdo->lastInsertId();
            header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&is_update=1&new_brand_id={$newBrandId}&success=" . urlencode("เพิ่มแบรนด์สินค้าสำเร็จ"));
            exit;
        } catch (PDOException $e) {
            error_log("Add product brand error: " . $e->getMessage());
            header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&is_update=1&error=" . urlencode("เกิดข้อผิดพลาดในการเพิ่มแบรนด์สินค้า"));
            exit;
        }
    } elseif (isset($_POST['product_id'])) {
        $product_id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        $price1 = filter_var($_POST['price_1'] ?? 0, FILTER_VALIDATE_FLOAT);
        $price2 = filter_var($_POST['price_2'] ?? 0, FILTER_VALIDATE_FLOAT);
        $price3 = filter_var($_POST['price_3'] ?? 0, FILTER_VALIDATE_FLOAT);
        $price4 = filter_var($_POST['price_4'] ?? 0, FILTER_VALIDATE_FLOAT);
        $capital = filter_var($_POST['capital'] ?? 0, FILTER_VALIDATE_FLOAT);
        $type = filter_var($_POST['typename'] ?? '', FILTER_VALIDATE_INT);
        $brand = filter_var($_POST['typebrandname'] ?? '', FILTER_VALIDATE_INT);

        $errors = validateProductData($name, $price1, $price2, $price3, $price4, $capital, $type, $brand);
        if (!empty($errors)) {
            header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&is_update=1&error=" . urlencode(implode(', ', $errors)));
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE product 
                SET product_name = ?, product_price1 = ?, product_price2 = ?, 
                    product_price3 = ?, product_price4 = ?, product_capital = ?, 
                    product_type_id = ?, product_brand_id = ? 
                WHERE product_id = ?
            ");
            $stmt->execute([$name, $price1, $price2, $price3, $price4, $capital, $type, $brand, $product_id]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&error=" . urlencode("ไม่พบข้อมูลสินค้าที่ต้องการแก้ไข"));
                exit;
            }
            $pdo->commit();
            header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&success=" . urlencode("แก้ไขข้อมูลสินค้าสำเร็จ"));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update product error: " . $e->getMessage());
            header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&error=" . urlencode("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage())));
            exit;
        }
    }
}

// Fetch product data
function fetchProductData($pdo, $tabel, $id)
{
    if ($tabel !== 'product') {
        header("Location: /?page=product_list&error=" . urlencode("ระบุประเภทไม่ถูกต้อง"));
        exit;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, pt.product_type_name, pb.product_brand_name 
            FROM product p
            LEFT JOIN product_type pt ON p.product_type_id = pt.product_type_id 
            LEFT JOIN product_brand pb ON p.product_brand_id = pb.product_brand_id 
            WHERE p.product_id = ?
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            header("Location: /?page=product_list&error=" . urlencode("ไม่พบข้อมูลสินค้า"));
            exit;
        }
        return $product;
    } catch (PDOException $e) {
        error_log("Fetch product error: " . $e->getMessage());
        header("Location: /?page=product_list&error=" . urlencode("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Fetch warehouse data
function fetchProductWarehouses($pdo, $id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT w.warehouse_id, w.warehouse_name, SUM(lp.lot_product_remain) as total_qty 
            FROM warehouse w 
            JOIN lot l ON w.warehouse_id = l.warehouse_id 
            JOIN lot_product lp ON l.lot_id = lp.lot_id 
            WHERE lp.product_id = ? 
            GROUP BY w.warehouse_id, w.warehouse_name 
            ORDER BY w.warehouse_name
        ");
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fetch warehouses error: " . $e->getMessage());
        header("Location: /?page=product_detail&tabel=product&id={$id}&error=" . urlencode("เกิดข้อผิดพลาดในการดึงข้อมูลคลังสินค้า"));
        exit;
    }
}

// Calculate total product quantity
function calculateTotalProductQuantity($pdo, $id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT SUM(lp.lot_product_remain) as total_product_qty 
            FROM lot_product lp 
            JOIN lot l ON lp.lot_id = l.lot_id 
            WHERE lp.product_id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_product_qty'] ?? 0;
    } catch (PDOException $e) {
        error_log("Calculate total quantity error: " . $e->getMessage());
        return 0;
    }
}

$product = fetchProductData($pdo, $tabel, $id);
$warehouses = fetchProductWarehouses($pdo, $id);
$totalProductQty = calculateTotalProductQuantity($pdo, $id);

// Fetch types and brands
try {
    $typeResults = $pdo->query("SELECT product_type_id, product_type_name FROM product_type ORDER BY product_type_name");
    $brandResults = $pdo->query("SELECT product_brand_id, product_brand_name FROM product_brand ORDER BY product_brand_name");
} catch (PDOException $e) {
    error_log("Fetch types or brands error: " . $e->getMessage());
    header("Location: /?page=product_detail&tabel={$tabel}&id={$id}&error=" . urlencode("เกิดข้อผิดพลาดในการดึงข้อมูลประเภทหรือแบรนด์สินค้า"));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_update ? 'แก้ไขข้อมูลสินค้า' : 'รายละเอียดสินค้า'; ?></title>
    <link href="/css/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-field {
            transition: border-color 0.2s ease-in-out;
        }

        .form-field:focus {
            border-color: #2563eb;
            outline: none;
            right: 2px;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8 xl:max-w-4xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between mb-6">
            <h2 class="text-2xl font-bold text-blue-800 mb-4 sm:mb-0">
                <i class="fas fa-box mr-2"></i>
                <?php echo $is_update ? 'แก้ไขข้อมูลสินค้า' : 'รายละเอียดสินค้า'; ?>
            </h2>
            <!-- <a href="/?page=product_list" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-xl shadow-md hover:from-blue-600 hover:to-blue-800 transition duration-300 ease-in-out">
                <i class="fas fa-arrow-left"></i>
                <span>ย้อนกลับ</span>
            </a> -->
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

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Warehouse Section (Visible only in view mode) -->
            <?php if (!$is_update): ?>
                <div class="w-full lg:w-5/12">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 border-b-2 border-gray-200 pb-2">
                            คลังสินค้าที่มีสินค้านี้
                        </h3>
                        <div class="p-4">
                            <?php if (count($warehouses) > 0): ?>
                                <table class="w-full border-collapse border border-gray-300 text-sm">
                                    <thead>
                                        <tr class="bg-blue-50">
                                            <th class="border border-gray-300 p-2 text-gray-700 text-left">ชื่อคลังสินค้า</th>
                                            <th class="border border-gray-300 p-2 text-gray-700 text-center">จำนวน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($warehouses as $warehouse): ?>
                                            <tr class="hover:bg-gray-100">
                                                <td class="border border-gray-300 p-2">
                                                    <a href="?page=warehouse_lot&warehouse_id=<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>" class="text-blue-600 hover:underline">
                                                        <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                                    </a>
                                                </td>
                                                <td class="border border-gray-300 p-2 text-center"><?php echo htmlspecialchars($warehouse['total_qty']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-100 font-semibold">
                                            <td class="border border-gray-300 p-2 text-right text-gray-700">รวมทั้งหมด:</td>
                                            <td class="border border-gray-300 p-2 text-center text-gray-700"><?php echo htmlspecialchars($totalProductQty); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-600 italic text-center py-4">ไม่พบสินค้านี้ในคลังสินค้าใดๆ</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Product Details Section -->
            <div class="w-full <?php echo !$is_update ? 'lg:w-7/12' : ''; ?>">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 border-b-2 border-gray-200 pb-2">
                        ข้อมูลสินค้า
                    </h3>
                    <form id="productForm" action="" method="POST" class="space-y-4">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <?php
                        $fields = [
                            ['label' => 'รหัสสินค้า', 'name' => 'product_id', 'value' => $product['product_id'], 'readonly' => true, 'id' => 'product-id'],
                            ['label' => 'ชื่อสินค้า', 'name' => 'name', 'value' => $product['product_name'], 'id' => 'product-name', 'required' => true, 'errorId' => 'nameError'],
                            ['label' => 'ราคาขายที่ 1', 'name' => 'price_1', 'value' => $product['product_price1'], 'id' => 'product-price1', 'type' => 'number', 'step' => '0.01', 'errorId' => 'price1Error'],
                            ['label' => 'ราคาขายที่ 2', 'name' => 'price_2', 'value' => $product['product_price2'], 'id' => 'product-price2', 'type' => 'number', 'step' => '0.01', 'errorId' => 'price2Error'],
                            ['label' => 'ราคาขายที่ 3', 'name' => 'price_3', 'value' => $product['product_price3'], 'id' => 'product-price3', 'type' => 'number', 'step' => '0.01', 'errorId' => 'price3Error'],
                            ['label' => 'ราคาขายที่ 4', 'name' => 'price_4', 'value' => $product['product_price4'], 'id' => 'product-price4', 'type' => 'number', 'step' => '0.01', 'errorId' => 'price4Error'],
                            ['label' => 'ราคาทุน', 'name' => 'capital', 'value' => $product['product_capital'], 'id' => 'product-capital', 'type' => 'number', 'step' => '0.01', 'errorId' => 'capitalError'],
                            ['label' => 'จำนวนสินค้าทั้งหมด', 'name' => 'total_qty', 'value' => $totalProductQty, 'readonly' => true, 'id' => 'product-total-qty'],
                        ];

                        foreach ($fields as $field): ?>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                                <div class="w-full sm:w-8/12">
                                    <?php if ($is_update && !($field['readonly'] ?? false)): ?>
                                        <input type="<?php echo $field['type'] ?? 'text'; ?>"
                                            id="<?php echo $field['id']; ?>"
                                            name="<?php echo $field['name']; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                            <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>
                                            <?php echo isset($field['step']) ? 'step="' . $field['step'] . '"' : ''; ?>
                                            aria-describedby="<?php echo $field['errorId'] ?? ''; ?>">
                                        <span id="<?php echo $field['errorId'] ?? ''; ?>" class="text-red-500 text-xs mt-1 hidden"></span>
                                    <?php elseif ($is_update && ($field['readonly'] ?? false)): ?>
                                        <input type="<?php echo $field['type'] ?? 'text'; ?>"
                                            id="<?php echo $field['id']; ?>"
                                            name="<?php echo $field['name']; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"
                                            readonly>
                                    <?php else: ?>
                                        <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($field['value']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Product Type -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700">
                                <a href="?page=product_type" class="text-blue-600 hover:underline">ประเภทสินค้า</a>:
                            </label>
                            <div class="w-full sm:w-8/12 flex items-center gap-2">
                                <?php if ($is_update): ?>
                                    <select id="product-type" name="typename" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="typeError">
                                        <option value="">เลือกประเภทสินค้า</option>
                                        <?php while ($type = $typeResults->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo htmlspecialchars($type['product_type_id']); ?>"
                                                <?php echo ($newTypeId && $type['product_type_id'] == $newTypeId) ? 'selected' : ($type['product_type_id'] == $product['product_type_id'] && !$newTypeId ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($type['product_type_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button type="button" onclick="document.getElementById('typeModal').style.display='flex'"
                                        class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 whitespace-nowrap">
                                        <i class="fas fa-plus mr-2"></i> เพิ่มใหม่
                                    </button>
                                    <span id="typeError" class="text-red-500 text-xs mt-1 hidden"></span>
                                <?php else: ?>
                                    <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($product['product_type_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Product Brand -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700">
                                <a href="?page=product_brand" class="text-blue-600 hover:underline">แบรนด์สินค้า</a>:
                            </label>
                            <div class="w-full sm:w-8/12 flex items-center gap-2">
                                <?php if ($is_update): ?>
                                    <select id="product-brand" name="typebrandname" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="brandError">
                                        <option value="">เลือกแบรนด์สินค้า</option>
                                        <?php while ($brand = $brandResults->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo htmlspecialchars($brand['product_brand_id']); ?>"
                                                <?php echo ($newBrandId && $brand['product_brand_id'] == $newBrandId) ? 'selected' : ($brand['product_brand_id'] == $product['product_brand_id'] && !$newBrandId ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($brand['product_brand_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button type="button" onclick="document.getElementById('brandModal').style.display='flex'"
                                        class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 whitespace-nowrap">
                                        <i class="fas fa-plus mr-2"></i> เพิ่มใหม่
                                    </button>
                                    <span id="brandError" class="text-red-500 text-xs mt-1 hidden"></span>
                                <?php else: ?>
                                    <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($product['product_brand_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4 mt-6">
                            <?php if ($is_update): ?>
                                <a href="/?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product['product_id']); ?>"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                                    <i class="fas fa-times mr-2"></i> ยกเลิก
                                </a>
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition">
                                    <i class="fas fa-save mr-2"></i> บันทึก
                                </button>
                            <?php else: ?>
                                <a href="/?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product['product_id']); ?>&is_update=1"
                                    class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition">
                                    <i class="fas fa-edit mr-2"></i> แก้ไขข้อมูล
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Adding New Product Type -->
    <div id="typeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="relative p-6">
                <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl font-bold" onclick="document.getElementById('typeModal').style.display='none'">×</button>
                <h3 class="text-xl font-bold mb-4 text-gray-800">เพิ่มประเภทสินค้าใหม่</h3>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_type">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="space-y-4">
                        <div>
                            <label for="product_type_name" class="block mb-2 font-medium text-gray-700">ชื่อประเภทสินค้า:</label>
                            <input type="text" id="product_type_name" name="product_type_name" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="typeModalError">
                            <span id="typeModalError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition" onclick="document.getElementById('typeModal').style.display='none'">
                                <i class="fas fa-times mr-2"></i> ยกเลิก
                            </button>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition">
                                <i class="fas fa-save mr-2"></i> เพิ่ม
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Adding New Product Brand -->
    <div id="brandModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="relative p-6">
                <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl font-bold" onclick="document.getElementById('brandModal').style.display='none'">×</button>
                <h3 class="text-xl font-bold mb-4 text-gray-800">เพิ่มแบรนด์สินค้าใหม่</h3>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_brand">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="space-y-4">
                        <div>
                            <label for="product_brand_name" class="block mb-2 font-medium text-gray-700">ชื่อแบรนด์สินค้า:</label>
                            <input type="text" id="product_brand_name" name="product_brand_name" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="brandModalError">
                            <span id="brandModalError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition" onclick="document.getElementById('brandModal').style.display='none'">
                                <i class="fas fa-times mr-2"></i> ยกเลิก
                            </button>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition">
                                <i class="fas fa-save mr-2"></i> เพิ่ม
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
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

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                const successMessage = urlParams.get('success') === '1' ? 'แก้ไขสำเร็จ' : decodeURIComponent(urlParams.get('success'));
                showNotification(successMessage, 'bg-green-500');
            } else if (urlParams.has('error')) {
                showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-600');
            }

            <?php if ($is_update): ?>
                document.getElementById('product-name').focus();
            <?php endif; ?>

            const validateForm = () => {
                let hasError = false;
                const fields = [{
                        id: 'product-name',
                        errorId: 'nameError',
                        label: 'ชื่อสินค้า',
                        maxLength: 255,
                        required: true
                    },
                    {
                        id: 'product-price1',
                        errorId: 'price1Error',
                        label: 'ราคาขายที่ 1',
                        validate: (value) => !isNaN(value) && value >= 0,
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ'
                    },
                    {
                        id: 'product-price2',
                        errorId: 'price2Error',
                        label: 'ราคาขายที่ 2',
                        validate: (value) => !isNaN(value) && value >= 0,
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ'
                    },
                    {
                        id: 'product-price3',
                        errorId: 'price3Error',
                        label: 'ราคาขายที่ 3',
                        validate: (value) => !isNaN(value) && value >= 0,
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ'
                    },
                    {
                        id: 'product-price4',
                        errorId: 'price4Error',
                        label: 'ราคาขายที่ 4',
                        validate: (value) => !isNaN(value) && value >= 0,
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ'
                    },
                    {
                        id: 'product-capital',
                        errorId: 'capitalError',
                        label: 'ราคาทุน',
                        validate: (value) => !isNaN(value) && value >= 0,
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ'
                    },
                    {
                        id: 'product-type',
                        errorId: 'typeError',
                        label: 'ประเภทสินค้า',
                        required: true
                    },
                    {
                        id: 'product-brand',
                        errorId: 'brandError',
                        label: 'แบรนด์สินค้า',
                        required: true
                    }
                ];

                fields.forEach(({
                    id,
                    errorId,
                    label,
                    maxLength,
                    required,
                    validate,
                    errorMessage
                }) => {
                    const input = document.getElementById(id);
                    const error = document.getElementById(errorId);
                    if (required && !input.value.trim()) {
                        error.textContent = `กรุณากรอก${label}`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (maxLength && input.value.length > maxLength) {
                        error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (validate && !validate(input.value)) {
                        error.textContent = errorMessage;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else {
                        error.classList.add('hidden');
                    }
                });

                // Validate modal inputs
                const typeModalInput = document.getElementById('product_type_name');
                const typeModalError = document.getElementById('typeModalError');
                if (typeModalInput && typeModalInput.value.length > 255) {
                    typeModalError.textContent = 'ชื่อประเภทสินค้ายาวเกิน 255 ตัวอักษร';
                    typeModalError.classList.remove('hidden');
                    hasError = true;
                } else if (typeModalError) {
                    typeModalError.classList.add('hidden');
                }

                const brandModalInput = document.getElementById('product_brand_name');
                const brandModalError = document.getElementById('brandModalError');
                if (brandModalInput && brandModalInput.value.length > 255) {
                    brandModalError.textContent = 'ชื่อแบรนด์สินค้ายาวเกิน 255 ตัวอักษร';
                    brandModalError.classList.remove('hidden');
                    hasError = true;
                } else if (brandModalError) {
                    brandModalError.classList.add('hidden');
                }

                return !hasError;
            };

            document.getElementById('productForm').addEventListener('submit', (e) => {
                if (!validateForm()) {
                    e.preventDefault();
                }
            });

            // Validate modal forms
            const typeModalForm = document.querySelector('#typeModal form');
            if (typeModalForm) {
                typeModalForm.addEventListener('submit', (e) => {
                    const typeInput = document.getElementById('product_type_name');
                    const typeError = document.getElementById('typeModalError');
                    if (!typeInput.value.trim()) {
                        typeError.textContent = 'กรุณากรอกชื่อประเภทสินค้า';
                        typeError.classList.remove('hidden');
                        e.preventDefault();
                    } else if (typeInput.value.length > 255) {
                        typeError.textContent = 'ชื่อประเภทสินค้ายาวเกิน 255 ตัวอักษร';
                        typeError.classList.remove('hidden');
                        e.preventDefault();
                    } else {
                        typeError.classList.add('hidden');
                    }
                });
            }

            const brandModalForm = document.querySelector('#brandModal form');
            if (brandModalForm) {
                brandModalForm.addEventListener('submit', (e) => {
                    const brandInput = document.getElementById('product_brand_name');
                    const brandError = document.getElementById('brandModalError');
                    if (!brandInput.value.trim()) {
                        brandError.textContent = 'กรุณากรอกชื่อแบรนด์สินค้า';
                        brandError.classList.remove('hidden');
                        e.preventDefault();
                    } else if (brandInput.value.length > 255) {
                        brandError.textContent = 'ชื่อแบรนด์สินค้ายาวเกิน 255 ตัวอักษร';
                        brandError.classList.remove('hidden');
                        e.preventDefault();
                    } else {
                        brandError.classList.add('hidden');
                    }
                });
            }
        });

        window.onclick = (event) => {
            if (event.target === document.getElementById('typeModal')) {
                document.getElementById('typeModal').style.display = 'none';
            }
            if (event.target === document.getElementById('brandModal')) {
                document.getElementById('brandModal').style.display = 'none';
            }
        };
    </script>
</body>

</html>