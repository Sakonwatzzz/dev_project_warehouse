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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'product_typegroup', 'id' => $_GET['id'] ?? ''] + ($_GET ?? []));
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

// Validate product data
function validateProductData($product_id, $name, $price1, $price2, $price3, $price4, $capital, $type_id, $pdo, $exclude_id = null)
{
    $errors = [];
    // Validate product_id
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
            $stmt->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
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
            $stmt->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
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

// Check product dependencies
function checkProductDependencies($pdo, $product_id)
{
    $dependencies = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale_product WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $dependencies[] = "รายการสั่งซื้อ ({$count} รายการ)";
    }
    return $dependencies;
}

// Handle search and type parameters
$productTypeId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

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

// Handle POST requests for adding product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=Invalid CSRF token');
        exit;
    }

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
        $stmt = $pdo->prepare("INSERT INTO product (product_id, product_name, product_price1, product_price2, product_price3, product_price4, product_capital, product_type_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
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

// Handle POST requests for updating product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=Invalid CSRF token');
        exit;
    }

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

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=Invalid CSRF token');
        exit;
    }

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

// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total_count FROM product WHERE product_type_id = :product_type_id";
    $params = [':product_type_id' => $productTypeId];
    if (!empty($search)) {
        $count_query .= " AND (product_name LIKE :search OR product_id LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date) {
        $count_query .= " AND created_at >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $count_query .= " AND created_at <= :end_date";
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

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, min((int)$_GET['page_num'], ceil($total_records / $records_per_page))) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch product types for dropdown
try {
    $product_type_query = "SELECT product_type_id, product_type_name FROM product_type ORDER BY product_type_name";
    $product_types = fetchData($pdo, $product_type_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode($e->getMessage()));
    exit;
}

// Fetch product data
try {
    $query = "
        SELECT 
            p.product_id,
            p.product_name,
            p.product_price1,
            p.product_price2,
            p.product_price3,
            p.product_price4,
            p.product_capital,
            p.product_type_id,
            pt.product_type_name
        FROM product p
        INNER JOIN product_type pt ON p.product_type_id = pt.product_type_id
        WHERE p.product_type_id = :product_type_id";
    $params = [':product_type_id' => $productTypeId];
    if (!empty($search)) {
        $query .= " AND (p.product_name LIKE :search OR p.product_id LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date) {
        $query .= " AND p.created_at >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $query .= " AND p.created_at <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }
    $query .= " ORDER BY p.product_id DESC LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $productData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: index.php?page=product_typegroup&id=' . urlencode($productTypeId) . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กลุ่มสินค้า: <?php echo htmlspecialchars($productTypeName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style/page_list.css">
    <style>
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

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">กลุ่มสินค้า: <?php echo htmlspecialchars($productTypeName); ?></h1>
            <p class="text-gray-600">ข้อมูลสินค้าทั้งหมดในประเภท <?php echo htmlspecialchars($productTypeName); ?></p>
        </div>

        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold justify-center md:justify-start">
                    <i class="fas fa-boxes mr-2"></i> <?php echo number_format($total_records); ?> รายการ
                </span>
                <button onclick="showAddModal()" class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition justify-center md:justify-start">
                    <i class="fas fa-plus mr-2"></i> เพิ่มสินค้า
                </button>
            </div>
            <form action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="product_typegroup">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($productTypeId); ?>">
                <input type="hidden" name="page_num" value="1">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหาสินค้า..." value="<?php echo htmlspecialchars($search); ?>"
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
                    <button onclick="exportPDF()" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                        <i class="fas fa-file-pdf mr-2"></i> ส่งออก PDF
                    </button>
                    <button onclick="exportExcel()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
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

        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto table-responsive">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 text-sm">
                            <th class="px-6 py-3 text-left font-semibold">รหัสสินค้า</th>
                            <th class="px-6 py-3 text-left font-semibold">ชื่อสินค้า</th>
                            <th class="px-6 py-3 text-left font-semibold">ประเภท</th>
                            <th class="px-6 py-3 text-left font-semibold">ราคาขาย 1</th>
                            <th class="px-6 py-3 text-left font-semibold">ราคาขาย 2</th>
                            <th class="px-6 py-3 text-left font-semibold">ราคาขาย 3</th>
                            <th class="px-6 py-3 text-left font-semibold">ราคาขาย 4</th>
                            <th class="px-6 py-3 text-left font-semibold">ราคาทุน</th>
                            <th class="px-6 py-3 text-center font-semibold">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($productData) > 0): ?>
                            <?php foreach ($productData as $product): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($product['product_id']); ?></td>
                                    <td class="px-6 py-3 border-t">
                                        <a href="?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product['product_id']); ?>"
                                            class="text-blue-600 hover:underline cursor-pointer">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($product['product_type_name']); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo number_format($product['product_price1'], 2); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo number_format($product['product_price2'], 2); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo number_format($product['product_price3'], 2); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo number_format($product['product_price4'], 2); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo number_format($product['product_capital'], 2); ?></td>
                                    <td class="px-6 py-3 border-t text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="showEditModal('<?php echo htmlspecialchars($product['product_id']); ?>', '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>', '<?php echo htmlspecialchars($product['product_price1']); ?>', '<?php echo htmlspecialchars($product['product_price2']); ?>', '<?php echo htmlspecialchars($product['product_price3']); ?>', '<?php echo htmlspecialchars($product['product_price4']); ?>', '<?php echo htmlspecialchars($product['product_capital']); ?>', '<?php echo htmlspecialchars($product['product_type_id']); ?>')"
                                                class="flex items-center justify-center px-3 py-1 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition">
                                                <div class="hidden md:flex">
                                                    <i class="fas fa-edit mr-1"></i>
                                                </div>
                                                <p>แก้ไข</p>
                                            </button>
                                            <form action="" method="POST" class="delete-form inline-flex">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
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
                                <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center py-6">
                                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                        <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                                        <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                                            <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                                <a href="?page=product_typegroup&id=<?php echo urlencode($productTypeId); ?>" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลสินค้าในกลุ่มนี้</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Card View (Mobile) -->
            <div class="card-responsive p-6">
                <?php if (count($productData) > 0): ?>
                    <?php foreach ($productData as $product): ?>
                        <div class="bg-white p-4 mb-4 rounded-lg shadow" data-id="<?php echo htmlspecialchars($product['product_id']); ?>">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">รหัสสินค้า:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($product['product_id']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ชื่อสินค้า:</span>
                                <a href="?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product['product_id']); ?>"
                                    class="text-blue-600 hover:underline text-sm">
                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                </a>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ประเภท:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($product['product_type_name']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ราคาขาย 1:</span>
                                <span class="text-sm"><?php echo number_format($product['product_price1'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ราคาขาย 2:</span>
                                <span class="text-sm"><?php echo number_format($product['product_price2'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ราคาขาย 3:</span>
                                <span class="text-sm"><?php echo number_format($product['product_price3'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ราคาขาย 4:</span>
                                <span class="text-sm"><?php echo number_format($product['product_price4'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ราคาทุน:</span>
                                <span class="text-sm"><?php echo number_format($product['product_capital'], 2); ?></span>
                            </div>
                            <div class="flex justify-center space-x-2 mt-4">
                                <button onclick="showEditModal('<?php echo htmlspecialchars($product['product_id']); ?>', '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>', '<?php echo htmlspecialchars($product['product_price1']); ?>', '<?php echo htmlspecialchars($product['product_price2']); ?>', '<?php echo htmlspecialchars($product['product_price3']); ?>', '<?php echo htmlspecialchars($product['product_price4']); ?>', '<?php echo htmlspecialchars($product['product_capital']); ?>', '<?php echo htmlspecialchars($product['product_type_id']); ?>')"
                                    class="flex items-center justify-center px-3 py-1 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition">
                                    <i class="fas fa-edit mr-1"></i> แก้ไข
                                </button>
                                <form action="" method="POST" class="delete-form inline-flex">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="button" onclick="confirmDelete(this.parentElement)"
                                        class="flex items-center justify-center px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition">
                                        <i class="fas fa-trash-alt mr-1"></i> ลบ
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-600 text-sm">
                        <div class="flex flex-col items-center justify-center py-6">
                            <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                            <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                            <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                                <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                    <a href="?page=product_typegroup&id=<?php echo urlencode($productTypeId); ?>" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลสินค้าในกลุ่มนี้</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=product_typegroup&id=<?= urlencode($productTypeId) ?>&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">« ก่อนหน้า</a>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=product_typegroup&id=<?= urlencode($productTypeId) ?>&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=product_typegroup&id=<?= urlencode($productTypeId) ?>&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded-md transition <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=product_typegroup&id=<?= urlencode($productTypeId) ?>&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition"><?= $total_pages ?></a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=product_typegroup&id=<?= urlencode($productTypeId) ?>&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">ถัดไป »</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50 p-4">
            <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md md:max-w-lg lg:max-w-xl max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center">
                            <i class="fas fa-plus-circle text-blue-600 mr-2"></i>เพิ่มสินค้าใหม่
                        </h3>
                        <p class="text-sm text-gray-500">กรุณากรอกข้อมูลสินค้า</p>
                    </div>
                    <form id="addForm" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="product_type_id" id="addProductTypeId" value="<?php echo htmlspecialchars($productTypeId); ?>">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">รหัสสินค้า</label>
                            <input type="text" id="addProductId" name="add_product_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addIdError">
                            <span id="addIdError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ชื่อสินค้า</label>
                            <input type="text" id="addProductName" name="add_product_name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addNameError">
                            <span id="addNameError" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 1</label>
                            <input type="number" step="0.01" id="addProductPrice1" name="add_product_price1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addPrice1Error">
                            <span id="addPrice1Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 2</label>
                            <input type="number" step="0.01" id="addProductPrice2" name="add_product_price2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addPrice2Error">
                            <span id="addPrice2Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 3</label>
                            <input type="number" step="0.01" id="addProductPrice3" name="add_product_price3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addPrice3Error">
                            <span id="addPrice3Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาขาย 4</label>
                            <input type="number" step="0.01" id="addProductPrice4" name="add_product_price4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addPrice4Error">
                            <span id="addPrice4Error" class="text-red-500 text-xs mt-1 hidden"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ราคาทุน</label>
                            <input type="number" step="0.01" id="addProductCapital" name="add_product_capital"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required aria-describedby="addCapitalError">
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
            document.getElementById('addProductId').value = '';
            document.getElementById('addProductName').value = '';
            document.getElementById('addProductPrice1').value = '';
            document.getElementById('addProductPrice2').value = '';
            document.getElementById('addProductPrice3').value = '';
            document.getElementById('addProductPrice4').value = '';
            document.getElementById('addProductCapital').value = '';
            document.getElementById('addIdError').classList.add('hidden');
            document.getElementById('addNameError').classList.add('hidden');
            document.getElementById('addPrice1Error').classList.add('hidden');
            document.getElementById('addPrice2Error').classList.add('hidden');
            document.getElementById('addPrice3Error').classList.add('hidden');
            document.getElementById('addPrice4Error').classList.add('hidden');
            document.getElementById('addCapitalError').classList.add('hidden');
            document.getElementById('addModal').classList.remove('hidden');
        };

        const closeAddModal = () => {
            document.getElementById('addModal').classList.add('hidden');
        };

        const showEditModal = (id, name, price1, price2, price3, price4, capital, typeId) => {
            document.getElementById('editProductId').value = id;
            document.getElementById('editProductIdDisplay').value = id;
            document.getElementById('editProductName').value = name;
            document.getElementById('editProductPrice1').value = price1;
            document.getElementById('editProductPrice2').value = price2;
            document.getElementById('editProductPrice3').value = price3;
            document.getElementById('editProductPrice4').value = price4;
            document.getElementById('editProductCapital').value = capital;
            document.getElementById('editProductTypeId').value = typeId;
            document.getElementById('editIdError').classList.add('hidden');
            document.getElementById('editNameError').classList.add('hidden');
            document.getElementById('editPrice1Error').classList.add('hidden');
            document.getElementById('editPrice2Error').classList.add('hidden');
            document.getElementById('editPrice3Error').classList.add('hidden');
            document.getElementById('editPrice4Error').classList.add('hidden');
            document.getElementById('editCapitalError').classList.add('hidden');
            document.getElementById('editTypeError').classList.add('hidden');
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
            let url = '/page/product/report/export_product_typegroup_pdf.php?id=<?php echo urlencode($productTypeId); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        };

        const exportExcel = () => {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/product/report/export_product_typegroup_excel.php?id=<?php echo urlencode($productTypeId); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
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
                return !hasError;
            };

            document.getElementById('addForm').addEventListener('submit', (e) => {
                e.preventDefault();
                const fields = [{
                        id: 'addProductId',
                        errorId: 'addIdError',
                        label: 'รหัสสินค้า',
                        required: true
                    },
                    {
                        id: 'addProductName',
                        errorId: 'addNameError',
                        label: 'ชื่อสินค้า',
                        maxLength: 255,
                        required: true
                    },
                    {
                        id: 'addProductPrice1',
                        errorId: 'addPrice1Error',
                        label: 'ราคาขาย 1',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    },
                    {
                        id: 'addProductPrice2',
                        errorId: 'addPrice2Error',
                        label: 'ราคาขาย 2',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    },
                    {
                        id: 'addProductPrice3',
                        errorId: 'addPrice3Error',
                        label: 'ราคาขาย 3',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    },
                    {
                        id: 'addProductPrice4',
                        errorId: 'addPrice4Error',
                        label: 'ราคาขาย 4',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    },
                    {
                        id: 'addProductCapital',
                        errorId: 'addCapitalError',
                        label: 'ราคาทุน',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    }
                ];
                if (validateForm('addForm', fields)) {
                    e.target.submit();
                }
            });

            document.getElementById('editForm').addEventListener('submit', (e) => {
                e.preventDefault();
                const fields = [{
                        id: 'editProductName',
                        errorId: 'editNameError',
                        label: 'ชื่อสินค้า',
                        maxLength: 255,
                        required: true
                    },
                    {
                        id: 'editProductPrice1',
                        errorId: 'editPrice1Error',
                        label: 'ราคาขาย 1',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    },
                    {
                        id: 'editProductPrice2',
                        errorId: 'editPrice2Error',
                        label: 'ราคาขาย 2',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    },
                    {
                        id: 'editProductPrice3',
                        errorId: 'editPrice3Error',
                        label: 'ราคาขาย 3',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    },
                    {
                        id: 'editProductPrice4',
                        errorId: 'editPrice4Error',
                        label: 'ราคาขาย 4',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    },
                    {
                        id: 'editProductCapital',
                        errorId: 'editCapitalError',
                        label: 'ราคาทุน',
                        validate: (value) => !value || (!isNaN(value) && value >= 0),
                        errorMessage: 'ต้องเป็นตัวเลขที่ไม่ติดลบ',
                        required: true
                    },
                    {
                        id: 'editProductTypeId',
                        errorId: 'editTypeError',
                        label: 'ประเภทสินค้า',
                        required: true
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
                urlParams.set('page', 'product_typegroup');
                urlParams.set('id', '<?php echo urlencode($productTypeId); ?>');
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