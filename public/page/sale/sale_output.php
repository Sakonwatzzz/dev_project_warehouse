<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../config/database_server.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Set timezone
date_default_timezone_set('Asia/Bangkok');
$currentDateTime = date("Y-m-d H:i:s");
$displayDateTime = date("d/m/Y H:i:s");

// Initialize active sales if not set
if (!isset($_SESSION['active_sales'])) {
    $_SESSION['active_sales'] = [];
}

// Get sale_id from GET or session
$sale_id = $_GET['sale_id'] ?? $_SESSION['current_sale_id'] ?? null;

if (!$sale_id) {
    die("Error: sale_id is required.");
}

// Query sale details
try {
    $query = "SELECT
        s.sale_id, s.sale_date, s.member_id, m.member_name, m.member_phone,
        s.personnel_id, p.personnel_name, s.sale_status_id, ss.sale_status_name
    FROM sale s
    LEFT JOIN member m ON s.member_id = m.member_id
    LEFT JOIN personnel p ON s.personnel_id = p.personnel_id
    LEFT JOIN sale_status ss ON s.sale_status_id = ss.sale_status_id
    WHERE s.sale_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sale_id]);
    $sale_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $product_query = "SELECT
        sp.sale_product_id, sp.sale_id, sp.product_id,
        COALESCE(pr.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
        sp.sale_product_qty, sp.sale_product_price, sp.sale_product_capital
    FROM sale_product sp
    LEFT JOIN product pr ON pr.product_id = sp.product_id
    WHERE sp.sale_id = ?";
    $stmt = $pdo->prepare($product_query);
    $stmt->execute([$sale_id]);
    $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_amount = 0;
    foreach ($sale_products as $product) {
        $total_amount += $product['sale_product_qty'] * $product['sale_product_price'];
    }
} catch (PDOException $e) {
    error_log("Database query error: " . $e->getMessage());
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}

// Validate active sales against database
$valid_active_sales = [];
if (!empty($_SESSION['active_sales'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['active_sales']), '?'));
    try {
        $stmt = $pdo->prepare("SELECT sale_id FROM sale WHERE sale_id IN ($placeholders) AND sale_status_id = 1");
        $stmt->execute(array_values($_SESSION['active_sales']));
        $valid_sales = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $valid_active_sales = array_intersect($_SESSION['active_sales'], $valid_sales);
        $_SESSION['active_sales'] = $valid_active_sales;
        if (!in_array($_SESSION['current_sale_id'], $valid_active_sales)) {
            $_SESSION['current_sale_id'] = !empty($valid_active_sales) ? reset($valid_active_sales) : null;
            $sale_id = $_SESSION['current_sale_id'];
        }
    } catch (PDOException $e) {
        error_log("Error validating active sales: " . $e->getMessage());
        $_SESSION['active_sales'] = [];
        $_SESSION['current_sale_id'] = null;
        $sale_id = null;
    }
} else {
    $_SESSION['active_sales'] = [];
    $_SESSION['current_sale_id'] = null;
    $sale_id = null;
}

$sale_status_id = $sale_data['sale_status_id'] ?? 1;
$sale_status_name = $sale_data['sale_status_name'] ?? 'รอดำเนินการ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเสร็จการขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'TH Sarabun New', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-600 to-indigo-800 text-white shadow-lg rounded-lg mb-6">
            <div class="py-4 px-6 flex justify-between items-center">
                <h1 class="text-2xl font-bold">ใบเสร็จการขาย</h1>
                <div class="flex items-center space-x-4">
                    <span class="text-sm">บิล #: <?php echo htmlspecialchars($sale_id); ?></span>
                    <span class="text-sm">วันที่: <?php echo $displayDateTime; ?></span>
                </div>
            </div>
        </header>

        <!-- Sale Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                ข้อมูลการขาย
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600"><strong>พนักงาน:</strong> <?php echo htmlspecialchars($sale_data['personnel_name'] ?? 'N/A'); ?></p>
                    <p class="text-sm text-gray-600"><strong>สถานะ:</strong> <?php echo htmlspecialchars($sale_status_name); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600"><strong>สมาชิก:</strong> <?php echo htmlspecialchars($sale_data['member_name'] ?? 'ไม่มี'); ?></p>
                    <p class="text-sm text-gray-600"><strong>เบอร์โทร:</strong> <?php echo htmlspecialchars($sale_data['member_phone'] ?? '-'); ?></p>
                </div>
            </div>
        </div>

        <!-- Product List -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                รายการสินค้า
            </h2>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700">
                            <th class="py-3 px-4 text-left font-medium text-sm">รหัสสินค้า</th>
                            <th class="py-3 px-4 text-left font-medium text-sm">ชื่อสินค้า</th>
                            <th class="py-3 px-4 text-center font-medium text-sm">จำนวน</th>
                            <th class="py-3 px-4 text-right font-medium text-sm">ราคาขาย</th>
                            <th class="py-3 px-4 text-right font-medium text-sm">ยอดรวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale_products as $product): ?>
                            <tr class="border-t border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-4 text-sm"><?php echo htmlspecialchars($product['product_id']); ?></td>
                                <td class="py-3 px-4 text-sm"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td class="py-3 px-4 text-center text-sm"><?php echo htmlspecialchars($product['sale_product_qty']); ?></td>
                                <td class="py-3 px-4 text-right text-sm"><?php echo number_format($product['sale_product_price'], 2); ?></td>
                                <td class="py-3 px-4 text-right font-medium text-sm"><?php echo number_format($product['sale_product_qty'] * $product['sale_product_price'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (empty($sale_products)): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <p class="mt-2">ไม่พบสินค้าในการขายนี้</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Summary -->
        <div class="bg-white rounded-lg shadow-md p-4">
            <h2 class="text-lg font-semibold mb-3 text-gray-800 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                สรุปการชำระเงิน
            </h2>
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="flex justify-between text-gray-800 mb-2">
                    <span class="font-medium text-sm">ยอดรวมสินค้า:</span>
                    <span class="text-sm"><?php echo number_format($total_amount, 2); ?> บาท</span>
                </div>
                <div class="border-t border-gray-200 pt-2 flex justify-between font-semibold text-base">
                    <span>ยอดรวมสุทธิ:</span>
                    <span class="text-blue-700"><?php echo number_format($total_amount, 2); ?> บาท</span>
                </div>
                <div class="flex justify-between text-gray-800 mt-2">
                    <span class="font-medium text-sm">เงินที่ชำระ:</span>
                    <span class="text-sm" id="paid_amount_display">0.00 บาท</span>
                </div>
                <div class="flex justify-between text-gray-800 mt-2">
                    <span class="font-medium text-sm">เงินทอน:</span>
                    <span class="text-sm text-blue-700 font-semibold" id="change_amount_display">0.00 บาท</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: '<?php echo $_SESSION['error']; ?>',
                confirmButtonText: 'ตกลง'
            });
            <?php unset($_SESSION['error']); ?>
        </script>
    <?php endif; ?>
</body>
</html>