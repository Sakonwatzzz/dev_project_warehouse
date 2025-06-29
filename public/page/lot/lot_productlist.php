<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();
date_default_timezone_set('Asia/Bangkok');

// Session Management
if (!isset($_SESSION)) {
    session_start();
}
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
        header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode("เกิดข้อผิดพลาดในการดึงข้อมูล Lot: " . htmlspecialchars($e->getMessage())));
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
    header("Location: index.php?page=lot_productlist&lot_id=$lot_id&error=" . urlencode($e->getMessage()));
    exit;
}

// คำนวณราคารวมสำหรับเรคคอร์ดที่แสดง
try {
    $total_price_query = "
        SELECT SUM(lp.lot_product_qty * lp.lot_product_capital) as total_price
        FROM lot_product lp
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN product p ON lp.product_id = p.product_id
        LEFT JOIN supplier s ON l.supplier_id = s.supplier_id";
    $total_price_params = [];
    if ($lot_id || !empty($search) || $start_date || $end_date) {
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
        if ($start_date) {
            $conditions[] = " lp.lot_product_date >= :start_date";
            $total_price_params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $conditions[] = " lp.lot_product_date <= :end_date";
            $total_price_params[':end_date'] = $end_date . ' 23:59:59';
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
    <title>รายการรับสินค้า<?php echo $lot_id ? " - Lot: " . htmlspecialchars($lot_name) : ""; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }

        .modal-content {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            width: 100%;
            max-width: 32rem;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">รายการรับสินค้า<?php echo $lot_id ? " : " . htmlspecialchars($lot_name) : ""; ?></h1>
            <p class="text-gray-600">ข้อมูลการรับสินค้าทั้งหมด</p>
        </div>

        <!-- Header and Search Section -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-col w-full md:flex-row md:w-auto items-center space-y-2 md:space-y-0 md:space-x-4">
                <span class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold  md:justify-start">
                    <i class="fas fa-boxes mr-2"></i> <?php echo number_format($total_records); ?> รายการ
                </span>
                <span class="inline-flex items-center w-full md:w-auto px-4 py-2 bg-blue-100 text-blue-800 rounded-md font-semibold  md:justify-start">
                    <i class="fa-solid fa-money-check-dollar mr-2"></i> ราคารวม: <?php echo number_format($total_price, 2); ?> บาท
                </span>
            </div>
            <form action="" method="GET" class="w-full md:w-auto">
                <input type="hidden" name="page" value="lot_productlist">
                <input type="hidden" name="lot_id" value="<?php echo htmlspecialchars($lot_id); ?>">
                <input type="hidden" name="page_num" value="1">
                <div class="relative">
                    <input type="text" name="search" id="searchInput" placeholder="ค้นหารายการรับสินค้า..." value="<?php echo htmlspecialchars($search); ?>"
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


        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto table-responsive">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 text-sm">
                            <th class="px-6 py-3 text-left font-semibold">รหัสสินค้า</th>
                            <th class="px-6 py-3 text-left font-semibold">ชื่อสินค้า</th>
                            <th class="px-6 py-3 text-right font-semibold">ราคาต้นทุน</th>
                            <th class="px-6 py-3 text-right font-semibold">จำนวนสินค้าเข้า</th>
                            <th class="px-6 py-3 text-right font-semibold">สินค้าคงเหลือ</th>
                            <th class="px-6 py-3 text-left font-semibold">ผู้ส่งมอบ</th>
                            <th class="px-6 py-3 text-left font-semibold">วันที่รับสินค้า</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($lot_products) > 0): ?>
                            <?php foreach ($lot_products as $lot_product): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($lot_product['product_id']); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($lot_product['product_name']); ?></td>
                                    <td class="px-6 py-3 border-t text-right"><?php echo htmlspecialchars($lot_product['lot_product_capital']); ?></td>
                                    <td class="px-6 py-3 border-t text-right"><?php echo htmlspecialchars($lot_product['lot_product_qty']); ?></td>
                                    <td class="px-6 py-3 border-t text-right"><?php echo htmlspecialchars($lot_product['lot_product_remain']); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($lot_product['supplier_name'] ?? 'ไม่มีข้อมูล'); ?></td>
                                    <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($lot_product['lot_product_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center py-6">
                                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                        <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                                        <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                                            <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                                <a href="?page=lot_productlist&lot_id=<?php echo urlencode($lot_id); ?>" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลการรับสินค้าใน Lot นี้</p>
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
                <?php if (count($lot_products) > 0): ?>
                    <?php foreach ($lot_products as $lot_product): ?>
                        <div class="bg-white p-4 mb-4 rounded-lg shadow" data-id="<?php echo htmlspecialchars($lot_product['lot_product_id']); ?>">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">รหัสสินค้า:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot_product['product_id']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ชื่อล็อตสินค้า:</span>
                                <a href="?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot_product['lot_id']); ?>"
                                    class="text-blue-600 hover:underline text-sm">
                                    <?php echo htmlspecialchars($lot_product['lot_name']); ?>
                                </a>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ชื่อสินค้า:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot_product['product_name']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ราคาต้นทุน:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot_product['lot_product_capital']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">จำนวนสินค้าเข้า:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot_product['lot_product_qty']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">สินค้าคงเหลือ:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot_product['lot_product_remain']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">สถานะ:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot_product['lot_product_status']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">ผู้ส่งมอบ:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot_product['supplier_name'] ?? 'ไม่มีข้อมูล'); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-semibold text-sm">วันที่รับสินค้า:</span>
                                <span class="text-sm"><?php echo htmlspecialchars($lot_product['lot_product_date']); ?></span>
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
                                    <a href="?page=lot_productlist&lot_id=<?php echo urlencode($lot_id); ?>" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลการรับสินค้าใน Lot นี้</p>
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
                    <a href="?page=lot_productlist&lot_id=<?= urlencode($lot_id) ?>&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">« ก่อนหน้า</a>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=lot_productlist&lot_id=<?= urlencode($lot_id) ?>&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=lot_productlist&lot_id=<?= urlencode($lot_id) ?>&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded-md transition <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=lot_productlist&lot_id=<?= urlencode($lot_id) ?>&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition"><?= $total_pages ?></a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_productlist&lot_id=<?= urlencode($lot_id) ?>&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">ถัดไป »</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Navigation Links -->
        <!-- <div class="flex justify-center space-x-4 mt-6">
            <a href="/?page=lot_list" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i> กลับไปหน้ารายการ Lot
            </a>
        </div> -->



    </div>

    <script>
        // const showNotification = (message, headerClass) => {
        //     const notification = document.getElementById('notification');
        //     const header = document.getElementById('notification-header');
        //     const title = document.getElementById('notificationTitle');
        //     const body = document.getElementById('notificationMessage');
        //     const icon = document.getElementById('notificationIcon');

        //     header.className = `flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg ${headerClass}`;
        //     title.textContent = 'แจ้งเตือน';
        //     body.textContent = message;
        //     icon.className = `fas ${headerClass.includes('bg-green') ? 'fa-check-circle' : 'fa-exclamation-circle'} text-2xl`;
        //     notification.classList.remove('translate-x-full');

        //     setTimeout(() => {
        //         hideNotification();
        //     }, 5000);
        // };

        // const hideNotification = () => {
        //     document.getElementById('notification').classList.add('translate-x-full');
        // };

        const exportPDF = () => {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/lot_product_pdf.php?lot_id=<?php echo urlencode($lot_id); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        };

        const exportExcel = () => {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/lot_product_excel.php?lot_id=<?php echo urlencode($lot_id); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
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
                        required: true,
                        validate: (value) => !isNaN(value) && value > 0,
                        errorMessage: 'จำนวนสินค้าต้องมากกว่า 0'
                    },
                    {
                        id: 'addLotProductCapital',
                        errorId: 'addCapitalError',
                        label: 'ราคาต้นทุน',
                        required: true,
                        validate: (value) => !isNaN(value) && value >= 0,
                        errorMessage: 'ราคาต้นทุนต้องไม่ติดลบ'
                    },
                    {
                        id: 'addLotProductDate',
                        errorId: 'addDateError',
                        label: 'วันที่รับสินค้า',
                        required: true,
                        validate: (value) => /^\d{4}-\d{2}-\d{2}$/.test(value),
                        errorMessage: 'วันที่ต้องอยู่ในรูปแบบ YYYY-MM-DD'
                    }
                ];
                if (validateForm('addForm', fields)) {
                    e.target.submit();
                }
            });

            document.getElementById('editForm').addEventListener('submit', (e) => {
                e.preventDefault();
                const fields = [{
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
                        required: true,
                        validate: (value) => !isNaN(value) && value > 0,
                        errorMessage: 'จำนวนสินค้าต้องมากกว่า 0'
                    },
                    {
                        id: 'editLotProductCapital',
                        errorId: 'editCapitalError',
                        label: 'ราคาต้นทุน',
                        required: true,
                        validate: (value) => !isNaN(value) && value >= 0,
                        errorMessage: 'ราคาต้นทุนต้องไม่ติดลบ'
                    },
                    {
                        id: 'editLotProductDate',
                        errorId: 'editDateError',
                        label: 'วันที่รับสินค้า',
                        required: true,
                        validate: (value) => /^\d{4}-\d{2}-\d{2}$/.test(value),
                        errorMessage: 'วันที่ต้องอยู่ในรูปแบบ YYYY-MM-DD'
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
                urlParams.set('page', 'lot_productlist');
                urlParams.set('lot_id', '<?php echo urlencode($lot_id); ?>');
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