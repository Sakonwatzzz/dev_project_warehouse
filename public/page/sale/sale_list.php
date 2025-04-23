<?php
ob_start();
require_once __DIR__ . '/../../../config/database_server.php';
// ต้องกำหนด $sale_id (สมมติว่าได้จาก GET หรือ POST)
$sale_id = $_POST['sale_id'] ?? $_GET['sale_id'] ?? $_SESSION['sale_id'] ?? null;

if (!isset($_GET['page']) || $_GET['page'] !== 'sale_list') {
    $sale_id = $_POST['sale_id'] ?? $_GET['sale_id'] ?? $_SESSION['sale_id'] ?? null;

    if (!$sale_id) {
        die("Error: sale_id is required.");
    }
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        if (
            !isset($_POST['barcode']) || !isset($_POST['product_name']) || !isset($_POST['qty']) ||
            !isset($_POST['price']) || !isset($_POST['capital']) || !isset($_POST['sale_product_id'])
        ) {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }

        $pdo->beginTransaction();

        // Update product name in product table
        $productStmt = $pdo->prepare("UPDATE product SET product_name = ? WHERE product_id = ?");
        $productStmt->execute([trim($_POST['product_name']), trim($_POST['barcode'])]);

        // Update quantity and price in sale_product table
        $stmt = $pdo->prepare("UPDATE sale_product SET 
            sale_product_qty = ?, sale_product_price = ?, sale_product_capital = ?
            WHERE sale_product_id = ? AND sale_id = ?");
        $stmt->execute([
            (int)$_POST['qty'],
            (float)$_POST['price'],
            (float)$_POST['capital'],
            (int)$_POST['sale_product_id'],
            $sale_id
        ]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// แก้ไขในส่วนของฟังก์ชัน handle sale confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sale_id']) && isset($_POST['action']) && $_POST['action'] === 'confirm_sale') {
    try {
        $pdo->beginTransaction();

        // เพิ่มการตรวจสอบว่า sale_id มีอยู่จริง
        $check_sale = $pdo->prepare("SELECT * FROM sale WHERE sale_id = ?");
        $check_sale->execute([$_POST['sale_id']]);
        if (!$check_sale->fetch()) {
            throw new Exception("ไม่พบข้อมูลการขาย");
        }

        // ตรวจสอบว่ามีสินค้าในรายการขายหรือไม่
        $check_products = $pdo->prepare("SELECT COUNT(*) FROM sale_product WHERE sale_id = ?");
        $check_products->execute([$_POST['sale_id']]);
        if ($check_products->fetchColumn() == 0) {
            throw new Exception("ไม่พบสินค้าในรายการขาย กรุณาเพิ่มสินค้าก่อนยืนยันการขาย");
        }

        // ตรวจสอบสินค้าที่มีข้อมูลไม่ครบถ้วน
        $check_query = "SELECT sp.product_id, COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name, 
                      sp.sale_product_price, sp.sale_product_capital 
                      FROM sale_product sp
                      LEFT JOIN product p ON p.product_id = sp.product_id
                      WHERE sp.sale_id = ? 
                      AND (p.product_name IS NULL OR p.product_name = 'ยังไม่ระบุ' OR sp.sale_product_price = 0 OR sp.sale_product_capital = 0)";

        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$_POST['sale_id']]);
        $invalid_products = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($invalid_products) > 0) {
            $invalid_list = '';
            foreach ($invalid_products as $product) {
                $invalid_list .= "- รหัส: {$product['product_id']} ชื่อ: {$product['product_name']}\n";
            }
            throw new Exception("พบสินค้าที่ข้อมูลไม่ครบถ้วน:\n" . $invalid_list);
        }

        // Update sale status to confirmed (sale_status_id = 2)
        $updateSale = $pdo->prepare("UPDATE sale SET sale_status_id = 2 WHERE sale_id = ?");
        $updateSale->execute([$_POST['sale_id']]);

        // อัพเดทข้อมูลสมาชิกและพนักงาน (ถ้ามีการเลือก)
        if (isset($_POST['member_id'])) {
            $updateMember = $pdo->prepare("UPDATE sale SET member_id = ? WHERE sale_id = ?");
            $updateMember->execute([$_POST['member_id'], $_POST['sale_id']]);
        }

        if (isset($_POST['personnel_id'])) {
            $updatePersonnel = $pdo->prepare("UPDATE sale SET personnel_id = ? WHERE sale_id = ?");
            $updatePersonnel->execute([$_POST['personnel_id'], $_POST['sale_id']]);
        }

        $pdo->commit();

        // Redirect with success message
        header('Location: index.php?page=sale_list&success=1');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // For AJAX requests, return JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }

        // For regular form submissions, redirect with error
        header('Location: index.php?page=sale_view&id=' . $_POST['sale_id'] . '&error=' . urlencode($e->getMessage()));
        exit();
    }
}

// Handle product scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {
    header('Content-Type: application/json');
    try {
        $barcode = trim($_POST['barcode']); // ตัดช่องว่าง
        $isDeleteMode = isset($_POST['deleteMode']) && $_POST['deleteMode'] === 'true';
        $quantity = isset($_POST['qty']) && is_numeric($_POST['qty']) ? (int)$_POST['qty'] : 1;
        $price = isset($_POST['price']) && is_numeric($_POST['price']) ? (float)$_POST['price'] : 0;
        $capital = isset($_POST['capital']) && is_numeric($_POST['capital']) ? (float)$_POST['capital'] : 0;

        if (empty($barcode)) {
            throw new Exception("กรุณาป้อนรหัสสินค้า");
        }

        // เริ่ม transaction เพื่อความสอดคล้องของข้อมูล
        $pdo->beginTransaction();

        // ตรวจสอบว่า product_id มีอยู่ใน sale_product
        $stmt = $pdo->prepare("SELECT sp.*, p.product_name, p.product_price4, p.product_capital 
                              FROM sale_product sp 
                              LEFT JOIN product p ON p.product_id = sp.product_id 
                              WHERE sp.sale_id = ? AND sp.product_id = ?");
        $stmt->execute([$sale_id, $barcode]);
        $existing_product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_product) {
            if ($isDeleteMode) {
                $newQty = $existing_product['sale_product_qty'] - $quantity;
                if ($newQty <= 0) {
                    $delete = $pdo->prepare("DELETE FROM sale_product WHERE sale_id = ? AND product_id = ?");
                    $delete->execute([$sale_id, $barcode]);
                } else {
                    $update = $pdo->prepare("UPDATE sale_product SET 
                        sale_product_qty = ?, 
                        sale_product_price = ?, 
                        sale_product_capital = ? 
                        WHERE sale_id = ? AND product_id = ?");
                    $update->execute([$newQty, $existing_product['sale_product_price'], $existing_product['sale_product_capital'], $sale_id, $barcode]);
                }
            } else {
                $newQty = $existing_product['sale_product_qty'] + $quantity;
                $newPrice = $price > 0 ? $price : ($existing_product['product_price4'] ?? $existing_product['sale_product_price']);
                $newCapital = $capital > 0 ? $capital : ($existing_product['product_capital'] ?? $existing_product['sale_product_capital']);

                $update = $pdo->prepare("UPDATE sale_product SET 
                    sale_product_qty = ?, 
                    sale_product_price = ?, 
                    sale_product_capital = ? 
                    WHERE sale_id = ? AND product_id = ?");
                $update->execute([$newQty, $newPrice, $newCapital, $sale_id, $barcode]);
            }
        } else {
            if (!$isDeleteMode) {
                // ตรวจสอบว่า product_id มีอยู่ในตาราง product
                $stmt_product = $pdo->prepare("SELECT product_name, product_price4, product_capital, stock 
                                              FROM product WHERE product_id = ?");
                $stmt_product->execute([$barcode]);
                $product_exists = $stmt_product->fetch(PDO::FETCH_ASSOC);

                if (!$product_exists) {
                    // เพิ่มสินค้าใหม่ในตาราง product
                    $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : "ยังไม่ระบุ";
                    $insert_product = $pdo->prepare("INSERT INTO product (
                        product_id, product_name, product_price1, product_price2, 
                        product_price3, product_price4, product_capital, 
                        product_type_id, product_brand_id, stock
                    ) VALUES (?, ?, 0, 0, 0, ?, ?, NULL, NULL, 0)");
                    $insert_product->execute([$barcode, $product_name, $price, $capital]);
                } else {
                    $price = $price > 0 ? $price : $product_exists['product_price4'];
                    $capital = $capital > 0 ? $capital : $product_exists['product_capital'];
                }

                // เพิ่มใน sale_product
                $insert = $pdo->prepare("INSERT INTO sale_product (
                    sale_id, product_id, sale_product_qty, sale_product_price, 
                    sale_product_capital, sale_product_booking, sale_product_status
                ) VALUES (?, ?, ?, ?, ?, 0, 1)");
                $insert->execute([$sale_id, $barcode, $quantity, $price, $capital]);
            }
        }

        // อัปเดตสต็อก
        $stmt_product = $pdo->prepare("SELECT stock FROM product WHERE product_id = ?");
        $stmt_product->execute([$barcode]);
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);
        if ($product && isset($product['stock'])) {
            $newStock = $isDeleteMode ? $product['stock'] + $quantity : $product['stock'] - $quantity;
            if ($newStock < 0) {
                throw new Exception("สต็อกไม่เพียงพอสำหรับสินค้า $barcode");
            }
            $updateStock = $pdo->prepare("UPDATE product SET stock = ? WHERE product_id = ?");
            $updateStock->execute([$newStock, $barcode]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
// Handle product search for suggestions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    error_log('Search Request: ' . print_r($_POST, true)); // บันทึก request
    header('Content-Type: application/json');
    try {
        $query = trim($_POST['query'] ?? '');
        error_log('Query: ' . $query); // บันทึก query
        if (empty($query)) {
            echo json_encode([]);
            exit();
        }
        $stmt = $pdo->prepare("SELECT product_id, product_name 
                              FROM product 
                              WHERE product_id LIKE ? OR product_name LIKE ? 
                              LIMIT 10");
        $stmt->execute(["%$query%", "%$query%"]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log('Products: ' . print_r($products, true)); // บันทึกผลลัพธ์
        echo json_encode($products);
    } catch (Exception $e) {
        error_log('Search Error: ' . $e->getMessage()); // บันทึก error
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}
// Query sale details
try {
    $query = "SELECT
        s.sale_id, s.sale_date, s.member_id, m.member_name, m.member_phone,
        m.member_register_date, m.member_type_id, s.personnel_id, p.personnel_name,
        p.personnel_phone, p.personnel_type_id, s.sale_status_id, ss.sale_status_name
    FROM sale s
    LEFT JOIN member m ON s.member_id = m.member_id
    LEFT JOIN personnel p ON s.personnel_id = p.personnel_id
    LEFT JOIN sale_status ss ON s.sale_status_id = ss.sale_status_id
    WHERE s.sale_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sale_id]);
    $sale_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query products in sale
    $product_query = "SELECT
        sp.sale_product_id, sp.sale_id, sp.product_id,
        COALESCE(pr.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
        sp.sale_product_qty, sp.sale_product_price, sp.sale_product_capital,
        sp.sale_product_booking, sp.sale_product_status,
        pr.product_price4, pr.product_capital,
        CASE 
            WHEN pr.product_name IS NULL OR pr.product_name = 'ยังไม่ระบุ' 
            OR sp.sale_product_price = 0 OR sp.sale_product_capital = 0 THEN 1
            ELSE 0
        END as needs_update
    FROM sale_product sp
    LEFT JOIN product pr ON pr.product_id = sp.product_id
    WHERE sp.sale_id = ?
    ORDER BY needs_update DESC, sp.sale_product_id DESC";
    $stmt = $pdo->prepare($product_query);
    $stmt->execute([$sale_id]);
    $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Calculate total amount
    $total_amount = 0;
    foreach ($sale_products as $product) {
        $total_amount += $product['sale_product_qty'] * $product['sale_product_price'];
    }
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}


// ดึงข้อมูลID จากตาราง product
$product_query = $pdo->query("SELECT product_id FROM product");

// ดึงข้อมูลสมาชิกจากฐานข้อมูล (สำหรับเลือกสมาชิก)
$members = $pdo->query("SELECT member_id, member_name FROM member ORDER BY member_name");
// $stmt = $pdo->prepare($query);
// $stmt->execute();

// ดึงข้อมูลพนักงานจากฐานข้อมูล (สำหรับเลือกพนักงาน)
$personnels = $pdo->query("SELECT personnel_id, personnel_name FROM personnel ORDER BY personnel_name");
// $stmt = $pdo->prepare($query);
// $stmt->execute();
// $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sale_status_id = $sale_data['sale_status_id'] ?? null;
$sale_status_name = $sale_data['sale_status_name'] ?? null;

$sale_data['sale_status_id'] = $sale_data['sale_status_id'] ?? 1;


$sale_status_result = $pdo->query("SELECT sale_status_id, sale_status_name FROM sale_status");

// Set timezone and get current date/time
date_default_timezone_set('Asia/Bangkok');
$currentDateTime = date("Y-m-d H:i:s");
$displayDateTime = date("d/m/Y H:i:s");

$sale_id_display = htmlspecialchars($sale_data['sale_id'] ?? 'N/A');
$sale_date = htmlspecialchars($sale_data['sale_date'] ?? 'N/A');
$personnel_name = htmlspecialchars($sale_data['personnel_name'] ?? 'N/A');
$product_rows = '';
foreach ($sale_products as $product) {
    $product_rows .= "<tr>" .
        "<td>" . htmlspecialchars($product['product_name']) . "</td>" .
        "<td class='text-right'>" . htmlspecialchars($product['sale_product_qty']) . "</td>" .
        "<td class='text-right'>" . number_format($product['sale_product_price'], 2) . "</td>" .
        "<td class='text-right'>" . number_format($product['sale_product_qty'] * $product['sale_product_price'], 2) . "</td>" .
        "</tr>";
}
// ประมวลผลข้อมูล PHP ล่วงหน้า
$sale_id = isset($_GET['sale_id']) ? $_GET['sale_id'] : '';
$sale_data = isset($sale_data) ? $sale_data : [];
$sale_products = isset($sale_products) ? $sale_products : [];
$total_amount = isset($total_amount) ? $total_amount : 0;

// ประมวลผลข้อมูล PHP
$sale_id_display = json_encode($sale_data['sale_id'] ?? 'N/A');
$sale_date = json_encode($sale_data['sale_date'] ?? 'N/A');
$personnel_name = json_encode($sale_data['personnel_name'] ?? 'N/A');
$product_rows = '';
foreach ($sale_products as $product) {
    $product_rows .= "<tr>" .
        "<td>" . json_encode($product['product_name']) . "</td>" .
        "<td class='text-right'>" . json_encode($product['sale_product_qty']) . "</td>" .
        "<td class='text-right'>" . json_encode(number_format($product['sale_product_price'], 2)) . "</td>" .
        "<td class='text-right'>" . json_encode(number_format($product['sale_product_qty'] * $product['sale_product_price'], 2)) . "</td>" .
        "</tr>";
}
$product_count = json_encode(count($sale_products));
$total_amount_display = json_encode(number_format($total_amount, 2));
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการการขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        #suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border: 1px solid #ccc;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: block;
        }

        #suggestions div {
            padding: 8px;
            cursor: pointer;
        }

        #suggestions div:hover {
            background-color: #f0f0f0;
        }
    </style>
    ```php
<?php
ob_start();
require_once __DIR__ . '/../../../../config/database_server.php';
$sale_id = isset($_GET['sale_id']) ? $_GET['sale_id'] : '';
$sale_data = isset($sale_data) ? $sale_data : [];
$sale_products = isset($sale_products) ? $sale_products : [];
$total_amount = isset($total_amount) ? $total_amount : 0;

// ดีบักข้อมูล PHP
error_log('Sale Data: ' . print_r($sale_data, true));
error_log('Sale Products: ' . print_r($sale_products, true));
?>
</head>

<body class="bg-gray-100 font-sans">
    <!-- เพิ่ม Header มืออาชีพ -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-800 text-white shadow-lg">
        <div class="container mx-auto py-4 px-6 flex justify-between items-center">
            <h1 class="text-2xl font-bold">POS System</h1>
            <div class="flex items-center space-x-4">
                <span class="text-sm">พนักงาน: ชื่อพนักงาน</span>
                <span class="text-sm">วันที่: <?= $displayDateTime ?></span>
            </div>
        </div>
    </header>

    <div class="container mx-auto p-6">
        <!-- รูปแบบการแบ่งหน้าจอแบบ 2 คอลัมน์ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- คอลัมน์ซ้าย (2/3) สำหรับรายการสินค้า -->
            <div class="lg:col-span-2 space-y-6">
                <!-- ฟอร์มสแกนบาร์โค้ด -->
                <form action="" id="barcode-form" method="POST">
                    <div class="relative flex space-x-4 mb-4">
                        <div class="relative w-full">
                            <input type="text" id="barcode" class="p-3 w-full h-8 border border-gray-300 rounded-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="สแกนหรือป้อนรหัสสินค้า" autofocus>
                            <div id="suggestions" class="absolute bg-white w-full border border-gray-200 z-10 rounded-sm mt-1"></div>
                        </div>
                        <button id="scan_product" type="button" class="p-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>
                    </div>
                </form>

                <!-- <div class="relative">
                    <input type="text" id="barcode"
                        class="p-3 w-full h-8 border border-gray-300 rounded-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                        placeholder="สแกนหรือป้อนรหัสสินค้า" autofocus>
                    <div id="suggestions" class="absolute bg-white w-full border border-gray-200 z-10 rounded-sm mt-1"></div>
                </div> -->

                <!-- รายการสินค้า -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            รายการสินค้า
                        </h2>
                        <button id="delete_mode"
                            class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors flex items-center text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            โหมดลบ
                        </button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-700">
                                    <th class="py-3 px-4 text-left font-medium text-sm">รหัสสินค้า</th>
                                    <th class="py-3 px-4 text-left font-medium text-sm">ชื่อสินค้า</th>
                                    <th class="py-3 px-4 text-center font-medium text-sm">จำนวน</th>
                                    <th class="py-3 px-4 text-right font-medium text-sm">ราคาขาย</th>
                                    <th class="py-3 px-4 text-right font-medium text-sm">ยอดรวม</th>
                                    <th class="py-3 px-4 text-center font-medium text-sm">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="product_table_body">
                                <!-- ตัวอย่างแถวสินค้า -->
                                <?php foreach ($sale_products as $product): ?>
                                    <tr class="border-t border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-4 text-sm">
                                            <?php echo htmlspecialchars($product['product_id']); ?>
                                        </td>
                                        <td class="py-3 px-4 text-sm">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </td>
                                        <td class="py-3 px-4 text-center text-sm">
                                            <?php echo htmlspecialchars($product['sale_product_qty']); ?>
                                        </td>
                                        <td class="py-3 px-4 text-right text-sm">
                                            <?php echo number_format($product['sale_product_price'], 2); ?>
                                        </td>
                                        <td class="py-3 px-4 text-right font-medium text-sm">
                                            <?php echo number_format($product['sale_product_qty'] * $product['sale_product_price'], 2); ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button
                                                    class="edit-product p-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors"
                                                    data-id="<?php echo htmlspecialchars($product['sale_product_id']); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    class="delete-product p-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                                                    data-id="<?php echo htmlspecialchars($product['sale_product_id']); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (empty($sale_products)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <p class="mt-2">ไม่พบสินค้าในการขายนี้</p>
                            <p class="text-sm">กรุณาสแกนหรือเพิ่มสินค้า</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- คอลัมน์ขวา (1/3) สำหรับข้อมูลการขายและการชำระเงิน -->
            <div class="space-y-6">
                <!-- ข้อมูลการขาย -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        ข้อมูลการขาย
                    </h2>
                    <div class="space-y-4">
                        <input type="hidden" name="sale_id"
                            value="<?php echo isset($sale_data['sale_id']) ? htmlspecialchars($sale_data['sale_id']) : ''; ?>">
                        <div class="grid grid-cols-1 gap-2">
                            <label class="text-base font-medium text-gray-700">วันที่ทำการขาย:</label>
                            <input name="sale_date" value="<?= $displayDateTime ?>" readonly
                                class="p-2 bg-gray-50 border border-gray-300 rounded text-gray-500 text-sm">
                        </div>

                        <div class="grid grid-cols-1 gap-2">
                            <label class="text-base font-medium text-gray-700">สถานะการขาย:</label>
                            <select name="sale_status_id" class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php while ($sale_status = $sale_status_result->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $sale_status['sale_status_id']; ?>"
                                        <?php echo ($sale_data['sale_status_id'] == $sale_status['sale_status_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sale_status['sale_status_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>

                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">สมาชิก:</label>
                                <select name="member_id"
                                    class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer hover:bg-gray-200">
                                    <?php while ($member_result = $members->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $member_result['member_id']; ?>">
                                            <?php echo ($member_result['member_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">พนักงานขาย:</label>
                                <select name="personnel_id" id="personnel_id"
                                    class="p-2 border text-sm border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" readonly>
                                    <?php while ($personnel_result = $personnels->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $personnel_result['personnel_id']; ?>">
                                            <?php echo htmlspecialchars($personnel_result['personnel_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- สรุปยอดและการชำระเงิน -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            การชำระเงิน
                        </h2>

                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <div class="flex justify-between text-gray-800">
                                <span class="font-medium">ยอดรวมสินค้า:</span>
                                <span><?php echo number_format($total_amount, 2); ?> บาท</span>
                            </div>
                            <!-- เพิ่มส่วนส่วนลด และภาษีถ้าจำเป็น -->
                            <div class="border-t border-gray-200 mt-3 pt-3 flex justify-between font-semibold text-lg">
                                <span>ยอดรวมสุทธิ:</span>
                                <span class="text-blue-700"><?php echo number_format($total_amount, 2); ?> บาท</span>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-sm font-medium text-gray-700">เงินที่ชำระ (บาท):</label>
                                <input type="number" id="paid_amount"
                                    class="p-3 text-lg border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    step="0.01" placeholder="ป้อนจำนวนเงิน">
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-sm font-medium text-gray-700">เงินทอน (บาท):</label>
                                <input type="text" id="change_amount"
                                    class="p-3 text-lg bg-gray-50 border border-gray-300 rounded-lg text-blue-700 font-semibold"
                                    readonly>
                            </div>

                            <button id="calculate_change"
                                class="w-full py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                </svg>
                                คำนวณเงินทอน
                            </button>
                        </div>
                    </div>

                    <!-- ปุ่มดำเนินการ -->
                    <div class="space-y-3">
                        <button id="print_receipt"
                            class="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center"
                            disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            พิมพ์ใบเสร็จ
                        </button>

                        <form method="POST" action="" id="confirm-sale-form">
                            <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                            <input type="hidden" name="action" value="confirm_sale">
                            <input type="hidden" name="member_id" id="selected-member-id" value="">
                            <input type="hidden" name="personnel_id" id="selected-personnel-id" value="">
                            <button type="submit" class="w-full py-3 bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition-colors flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                ยืนยันการขาย
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- เพิ่ม Modal แก้ไขสินค้า -->
        <div id="edit-product-modal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">แก้ไขสินค้า</h3>
                    <button id="close-modal" class="text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <input type="hidden" id="edit-product-id">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">รหัสสินค้า</label>
                        <input type="text" id="edit-barcode" class="w-full p-2 border border-gray-300 rounded" readonly>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสินค้า</label>
                        <input type="text" id="edit-product-name" class="w-full p-2 border border-gray-300 rounded"
                            readonly>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                        <input type="number" id="edit-qty" class="w-full p-2 border border-gray-300 rounded" min="1">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ราคาขาย</label>
                        <input type="number" id="edit-price" class="w-full p-2 border border-gray-300 rounded"
                            step="0.01">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ต้นทุน</label>
                        <input type="number" id="edit-capital" class="w-full p-2 border border-gray-300 rounded" step="0.01">
                    </div>
                </div>

                <div class="mt-6 flex space-x-3">
                    <button id="update-product"
                        class="flex-1 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                        บันทึกการแก้ไข
                    </button>
                    <button id="cancel-edit"
                        class="flex-1 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors">
                        ยกเลิก
                    </button>
                </div>
            </div>
        </div>

</body>

<script>
    $(document).ready(function() {
                // Suggestion เมื่อพิมพ์
                $('#barcode').on('input', function() {
                    const query = $(this).val().trim();
                    const suggestionBox = $('#suggestions');
                    if (query.length < 1) {
                        suggestionBox.empty().hide();
                        return;
                    }
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'search',
                            query: query
                        },
                        dataType: 'json',
                        success: function(data) {
                            suggestionBox.empty().show();
                            if (data.error) {
                                suggestionBox.html(`<div class="px-2 py-1 text-red-500">${data.error}</div>`);
                                return;
                            }
                            if (!Array.isArray(data) || data.length === 0) {
                                suggestionBox.html('<div class="px-2 py-1 text-gray-500">ไม่พบข้อมูลสินค้าที่ตรงกับการค้นหา</div>');
                                return;
                            }
                            data.forEach(product => {
                                const item = $(`<div class="cursor-pointer hover:bg-gray-100 px-2 py-1">${product.product_id} - ${product.product_name}</div>`);
                                item.click(function() {
                                    $('#barcode').val(product.product_id);
                                    suggestionBox.empty().hide();
                                    scanBarcode();
                                });
                                suggestionBox.append(item);
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error('Suggestion Error:', status, error, xhr.responseText);
                            suggestionBox.html(`<div class="px-2 py-1 text-red-500">เกิดข้อผิดพลาดในการค้นหา: ${status}</div>`).show();
                        }
                    });
                });

                // ซ่อน suggestion เมื่อคลิกที่อื่น
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('#barcode, #suggestions').length) {
                        $('#suggestions').empty().hide();
                    }
                });

                // สแกนบาร์โค้ดเมื่อกด Enter
                $('#barcode').on('keypress', function(e) {
                    if (e.which == 13) {
                        e.preventDefault();
                        scanBarcode();
                    }
                });

                // สแกนบาร์โค้ดเมื่อคลิกปุ่ม
                $('#scan_product').click(function(e) {
                    e.preventDefault();
                    scanBarcode();
                });

                // ฟังก์ชันสแกนบาร์โค้ด
                function scanBarcode() {
                    let barcode = $('#barcode').val().trim();
                    let deleteMode = $('#delete_mode').hasClass('bg-red-700');
                    let sale_id = '<?php echo $sale_id; ?>';
                    if (!barcode) {
                        alert('กรุณาป้อนรหัสสินค้า');
                        return;
                    }
                    if (!sale_id) {
                        alert('ไม่พบรหัสใบขาย');
                        return;
                    }
                    $('#suggestions').empty().hide();
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            sale_id: sale_id,
                            barcode: barcode,
                            qty: 1,
                            deleteMode: deleteMode
                        },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Scan Response:', response);
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('ข้อผิดพลาด: ' + response.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error, xhr.responseText);
                            alert('เกิดข้อผิดพลาดในการสแกน: ' + error);
                        },
                        complete: function() {
                            $('#barcode').val('').focus();
                        }
                    });
                }

                // สลับโหมดลบ
                $('#delete_mode').click(function() {
                    $(this).toggleClass('bg-red-500 bg-red-700');
                    if ($(this).hasClass('bg-red-700')) {
                        $(this).html('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg> โหมดลบ (เปิดใช้งาน)');
                    } else {
                        $(this).html('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg> โหมดลบ');
                    }
                });

                // ลบสินค้า
                $('.delete-product').click(function() {
                    if (confirm('คุณต้องการลบสินค้านี้ใช่หรือไม่?')) {
                        let id = $(this).data('id');
                        let barcode = $(this).closest('tr').find('td:eq(0)').text().trim();
                        $.ajax({
                            url: window.location.href,
                            type: 'POST',
                            data: {
                                sale_id: '<?php echo $sale_id; ?>',
                                barcode: barcode,
                                qty: 1,
                                deleteMode: true
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert('ข้อผิดพลาด: ' + response.error);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', status, error, xhr.responseText);
                                alert('เกิดข้อผิดพลาดในการลบสินค้า');
                            }
                        });
                    }
                });

                // แก้ไขสินค้า
                $('.edit-product').click(function() {
                    let id = $(this).data('id');
                    let row = $(this).closest('tr');
                    let barcode = row.find('td:eq(0)').text().trim();
                    let name = row.find('td:eq(1)').text().trim();
                    let qty = row.find('td:eq(2)').text().trim();
                    let price = row.find('td:eq(3)').text().trim().replace(/,/g, '');
                    $('#edit-product-id').val(id);
                    $('#edit-barcode').val(barcode);
                    $('#edit-product-name').val(name);
                    $('#edit-qty').val(qty);
                    $('#edit-price').val(price);
                    $('#edit-product-modal').removeClass('hidden');
                });

                $('#close-modal, #cancel-edit').click(function() {
                    $('#edit-product-modal').addClass('hidden');
                });

                $('#update-product').click(function() {
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'update',
                            sale_product_id: $('#edit-product-id').val(),
                            barcode: $('#edit-barcode').val(),
                            product_name: $('#edit-product-name').val(),
                            qty: $('#edit-qty').val(),
                            price: $('#edit-price').val(),
                            capital: $('#edit-capital').val() || 0
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('ข้อผิดพลาด: ' + response.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error, xhr.responseText);
                            alert('เกิดข้อผิดพลาดในการอัปเดต');
                        }
                    });
                });

                // รีเซ็ตฟอร์มหลังสแกน
                $('#barcode').on('keypress', function(e) {
                    if (e.which == 13) {
                        $('#scan_product').click();
                        $('#barcode').val('');
                        $('#product_name').val('');
                        $('#qty').val('1');
                        $('#price').val('');
                        $('#capital').val('');
                    }
                });

                // คำนวณเงินทอน
                $('#calculate_change').click(function() {
                    let total_amount = <?php echo $total_amount; ?>;
                    let paid_amount = parseFloat($('#paid_amount').val()) || 0;
                    if (paid_amount < total_amount) {
                        alert('เงินที่ชำระไม่เพียงพอ');
                        $('#change_amount').val('');
                        $('#print_receipt').prop('disabled', true);
                        return;
                    }
                    let change = paid_amount - total_amount;
                    $('#change_amount').val(change.toFixed(2));
                    $('#print_receipt').prop('disabled', false);
                });

                // พิมพ์ใบเสร็จ
                $('#print_receipt').click(function() {
                            let total_amount = parseFloat($('#total_amount').val().replace(/,/g, '')) || 0;
                            let paid_amount = parseFloat($('#paid_amount').val()) || 0;
                            let change_amount = parseFloat($('#change_amount').val()) || 0;
                            let receiptWindow = window.open('', '_blank');
                            let html = `
        <html>
        <head>
            <title>ใบเสร็จการขาย</title>
            <style>
                @page { size: 80mm 200mm; margin: 0; }
                body { font-family: 'Arial', sans-serif; margin: 0; padding: 8mm; font-size: 12px; }
                .receipt { width: 76mm; margin: auto; }
                .header { text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
                .store-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
                .info { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 11px; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 11px; }
                th, td { text-align: left; padding: 3px 0; }
                th { border-bottom: 1px solid #ddd; }
                .total-section { margin-top: 10px; border-top: 1px dashed #000; padding-top: 10px; }
                .totals { display: flex; justify-content: space-between; margin: 5px 0; }
                .grand-total { font-weight: bold; font-size: 14px; margin: 10px 0; display: flex; justify-content: space-between; }
                .payment-info { margin: 10px 0; border-top: 1px dashed #000; padding-top: 10px; }
                .footer { text-align: center; margin-top: 20px; font-size: 11px; border-top: 1px dashed #000; padding-top: 10px; }
                .text-right { text-align: right; }
            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="header">
                    <div class="store-name">POS SYSTEM</div>
                    <div>123 ถนนสุขุมวิท เขตวัฒนา กรุงเทพฯ</div>
                    <div>โทร: 02-123-4567</div>
                </div>
                <div class="info">
                    <span>เลขที่: <?php echo htmlspecialchars($sale_data['sale_id'] ?? 'N/A'); ?></span>
                    <span>วันที่: <?php echo htmlspecialchars($sale_data['sale_date'] ?? 'N/A'); ?></span>
                </div>
                <div class="info">
                    <span>พนักงาน: <?php echo htmlspecialchars($sale_data['personnel_name'] ?? 'N/A'); ?></span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>รายการ</th>
                            <th class="text-right">จำนวน</th>
                            <th class="text-right">ราคา</th>
                            <th class="text-right">รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale_products as $product) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_name'] ?? ''); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($product['sale_product_qty'] ?? 0); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars(number_format($product['sale_product_price'] ?? 0, 2)); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars(number_format(($product['sale_product_qty'] ?? 0) * ($product['sale_product_price'] ?? 0), 2)); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <div class="total-section">
                    <div class="totals">
                        <span>จำนวนสินค้า:</span>
                        <span><?php echo count($sale_products); ?> รายการ</span>
                    </div>
                    <div class="grand-total">
                        <span>ยอดรวมทั้งสิ้น:</span>
                        <span><?php echo number_format($total_amount, 2); ?> บาท</span>
                    </div>
                </div>
                <div class="payment-info">
                    <div class="totals">
                        <span>เงินที่รับ:</span>
                        <span>${paid_amount.toFixed(2)} บาท</span>
                    </div>
                    <div class="totals">
                        <span>เงินทอน:</span>
                        <span>${change_amount.toFixed(2)} บาท</span>
                    </div>
                </div>
                <div class="footer">
                    <div>ขอบคุณที่ใช้บริการ</div>
                    <div>THANK YOU FOR YOUR BUSINESS</div>
                </div>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                }
</script>
</body>

</html>
`;
console.log('Receipt HTML:', html);
try {
receiptWindow.document.write(html);
receiptWindow.document.close();
} catch (e) {
console.error('Write Error:', e);
}
});

// คำนวณเงินทอนเมื่อกด Enter ในช่องเงินที่ชำระ
$('#paid_amount').on('keypress', function(e) {
if (e.which == 13) {
$('#calculate_change').click();
}
});

// เพิ่ม event สำหรับการเลือกสมาชิกและพนักงาน
$('select[name="member_id"]').change(function() {
$('#selected-member-id').val($(this).val());
});
$('select[name="personnel_id"]').change(function() {
$('#selected-personnel-id').val($(this).val());
});

// ตั้งค่าเริ่มต้น
$('#selected-member-id').val($('select[name="member_id"]').val());
$('#selected-personnel-id').val($('select[name="personnel_id"]').val());
});
console.log('JavaScript loaded successfully');
</script>


</html>
<?php ob_end_flush(); ?>