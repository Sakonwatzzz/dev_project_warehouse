<?php
try {
    require_once __DIR__ . '../../../../config/database_server.php';
    error_log('Database connected including successfuly database_server.php');
} catch (Exception $e) {
    error_log("Database failed included database_server.php : " . $e->getMessage());
}


// if (empty($_SESSION['csrf_token'])) {
//     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
//     error_log("CSRF token generated: " . $_SESSION['csrf_token']);
// }
// คำนวณ VAT สำหรับการแสดงผลเริ่มต้น
// $sale_net_amount = $sale_total_amount;
// $sale_vat_amount = $sale_net_amount * 0.07;
// $sale_total_amount = $sale_net_amount + $sale_vat_amount;

// เพิ่มการแสดงผลข้อผิดพลาดสำหรับการดีบักชั่วคราวตั้งค่า display_errors เป็น 1 ในไฟล์เพื่อดูข้อผิดพลาดโดยตรง
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/logs/php_errors.log');

ob_start();

// Set timezone
date_default_timezone_set('Asia/Bangkok');
$currentDateTime = date("Y-m-d H:i:s");
$displayDateTime = date("d/m/Y H:i:s");

// Initialize active sales if not set
if (!isset($_SESSION['active_sales'])) {
    $_SESSION['active_sales'] = [];
}

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

// กรณีที่ต้องการให้สร้างบิลอัตโนมัติ ให้ใช้โค้ด หรือ logic ดังนี้ 
// 1.เช็คและสร้าง sale_id ใหม่หากยังไม่มี (ถ้า $sale_id ยังไม่มีค่า (เช่น null หรือ false) 
// 1.1 และยังไม่ได้อยู่ในหน้า sale_list ($_GET['page'] !== 'sale_list') จึงเข้าสู่ขั้นตอนการสร้าง sale ใหม่)
// 1.2 เริ่มต้น transaction เพื่อให้การบันทึกลงฐานข้อมูลสามารถยกเลิก (rollback) ได้หากมีข้อผิดพลาด
// 1.3 คำสั่ง SQL สำหรับเพิ่มแถวใหม่ในตาราง sale ใช้ค่าคงที่: sale_status_id = 2 คือ กำลังขาย
// 1.4 ทำการ execute คำสั่ง insert
// 1.5 ดึง sale_id ที่เพิ่ง insert สำเร็จ เก็บค่า sale_id ที่เพิ่งถูกสร้างล่าสุดไว้ในตัวแปร $sale_id
// 1.6 บันทึก sale_id ลง session เก็บ sale_id นี้ใน session ทั้งในรูปแบบ: รายการที่ active อยู่ (active_sales) /กำหนดให้เป็น current_sale_id ที่ใช้งานอยู่ตอนนี้
// 1.7 ยืนยันการ transaction บันทึกการเปลี่ยนแปลงทั้งหมดลงฐานข้อมูลจริง
/* หากมี error เกิดขึ้น */
// ทำการ rollback ยกเลิกธุรกรรม -> บันทึก log -> แสดงข้อความผิดพลาดและหยุดทำงาน
/* หากยังไม่มี $sale_id หลังจากจบบล็อก try-catch */
// หากยังไม่มี $sale_id จริง ๆ แสดงว่าเกิดข้อผิดพลาดบางอย่าง -> หยุดการทำงานและแจ้งว่า sale_id จำเป็น

// 2. รับ sale_id จาก POST, GET, หรือ SESSION
// 2.1 รับค่าตามลำดับ จาก POST GET SESSION(current_sale_id) ถ้าไม่มี -> null
/* หากยังไม่มี sale_id หลังจากพยายามรับจากทุกช่องทาง */
// ถ้าไม่พบ sale_id จริง ๆ : บันทึก error ลง log -> ส่ง HTTP response code 400 (Bad Request) = ส่งข้อความ error เป็น JSON

// Get sale_id from POST, GET, or session
$sale_id = $_POST['sale_id'] ?? $_GET['sale_id'] ?? $_SESSION['current_sale_id'] ?? null;

// Create new sale if none exists
if (!$sale_id && (!isset($_GET['page']) || $_GET['page'] !== 'sale_list')) {
    try {
        $pdo->beginTransaction();
        $insertSale = $pdo->prepare("INSERT INTO sale (sale_date, sale_status_id, member_id, personnel_id, sale_net_amount, sale_vat_amount, sale_paid_amount, sale_change_amount, sale_total_amount) VALUES (?, 2, ?, ?, ?, ?, ?, ?, ?)");
        $insertSale->execute([
            $currentDateTime, // sale_date
            null,             // member_id
            null,             // personnel_id
            0,                // sale_net_amount
            0.00,             // sale_vat_amount
            0.00,             // sale_paid_amount
            0.00,             // sale_change_amount
            0.00              // sale_total_amount
        ]);
        $sale_id = $pdo->lastInsertId();
        $_SESSION['active_sales'][] = $sale_id;
        $_SESSION['current_sale_id'] = $sale_id;
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error initializing sale: " . $e->getMessage());
        die("Error initializing sale: " . $e->getMessage());
    }
}
if (!$sale_id) {
    die("Error: sale_id is required.");
}



if (!$sale_id) {
    error_log("No sale_id provided in request or session.");
    http_response_code(400);
    // บนหน้าจอแสดงผลจะแสดงว่า {"status":"error","message":"\u0e01\u0e23\u0e38\u0e13\u0e32\u0e23\u0e30\u0e1a\u0e38 sale_id"}
    die(json_encode(['status' => 'error', 'message' => 'กรุณาระบุ sale_id']));
}




/**
 * Helper function to adjust lot_product remain
 * @param PDO $pdo
 * @param string $productId
 * @param int $qty
 * @param bool $addMode
 */
function adjustLotRemain(PDO $pdo, string $productId, int $qty, bool $addMode = false)
{
    $remainingToProcess = $qty;
    $lot_ids = [];
    try {
        while ($remainingToProcess > 0) {
            $query = "
                SELECT lot_product_id, lot_product_remain 
                FROM lot_product 
                WHERE product_id = ? AND lot_product_remain > 0
                ORDER BY lot_product_date ASC, lot_product_id ASC LIMIT 1
            ";
            $stmtLot = $pdo->prepare($query);
            $stmtLot->execute([$productId]);
            $lot = $stmtLot->fetch(PDO::FETCH_ASSOC);

            if (!$lot) {
                throw new Exception("สต็อกล็อตไม่เพียงพอสำหรับสินค้า $productId");
            }

            $current = (int) $lot['lot_product_remain'];
            if ($addMode) {
                $newRemain = $current + $remainingToProcess;
                $remainingToProcess = 0;
            } else {
                if ($current >= $remainingToProcess) {
                    $newRemain = $current - $remainingToProcess;
                    $lot_ids[] = ['lot_product_id' => $lot['lot_product_id'], 'qty' => $remainingToProcess];
                    $remainingToProcess = 0;
                } else {
                    $newRemain = 0;
                    $lot_ids[] = ['lot_product_id' => $lot['lot_product_id'], 'qty' => $current];
                    $remainingToProcess -= $current;
                }
            }

            $updateLot = $pdo->prepare("
                UPDATE lot_product 
                SET lot_product_remain = ? 
                WHERE lot_product_id = ?
            ");
            $updateLot->execute([$newRemain, $lot['lot_product_id']]);
        }
    } catch (Exception $e) {
        error_log("Error in adjustLotRemain for product $productId: " . $e->getMessage());
        throw $e;
    }
    return $lot_ids;
}
//เพิ่มการตรวจสอบ X-Requested-With: XMLHttpRequest.
function isAjaxRequest()
{
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    error_log("isAjaxRequest: " . ($isAjax ? 'Yes' : 'No') . ", Headers: " . json_encode(getallheaders()));
    return $isAjax;
}



// Handle print receipt via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'print_receipt') {
    header('Content-Type: application/json');
    ob_clean();
    try {
        $sale_id = $_POST['sale_id'] ?? null;
        if (!$sale_id) {
            throw new Exception("ไม่พบ sale_id ในคำขอ กรุณาเปิดบิลใหม่และเพิ่มข้อมูลการขาย");
        }

        // Query sale details
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

        if (!$sale_data) {
            throw new Exception("ไม่พบข้อมูลบิล #$sale_id");
        }

        // Query sale products
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

        $sale_net_amount = 0;
        $total_qty = 0;
        foreach ($sale_products as $product) {
            $sale_net_amount += $product['sale_product_qty'] * $product['sale_product_price'];
            $total_qty += $product['sale_product_qty'];
        }
        $vat_rate = 7.00;
        $sale_vat_amount = $sale_net_amount * ($vat_rate / 100);
        $sale_total_amount = $sale_net_amount + $sale_vat_amount;

        // Prepare receipt data
        $receipt_data = [
            'sale_id' => $sale_data['sale_id'],
            'sale_date' => date("d/m/Y H:i:s", strtotime($sale_data['sale_date'])),
            'member_name' => $sale_data['member_name'] ?? 'ไม่ระบุ',
            'personnel_name' => $sale_data['personnel_name'] ?? 'ไม่ระบุ',
            'products' => $sale_products,
            'total_qty' => $total_qty,
            'sale_net_amount' => $sale_net_amount,
            'sale_vat_amount' => $sale_vat_amount,
            'sale_total_amount' => $sale_total_amount,
            'sale_paid_amount' => $_POST['sale_paid_amount'] ?? 0,
            'sale_change_amount' => $_POST['sale_change_amount'] ?? 0
        ];

        echo json_encode(['status' => 'success', 'receipt' => $receipt_data]);
    } catch (Exception $e) {
        error_log("Error generating receipt: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถสร้างใบเสร็จได้: ' . $e->getMessage()]);
    }
    exit();
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

    $sale_net_amount = 0;
    $total_items = 0;
    $total_qty = 0;
    if (!empty($sale_products)) {
        foreach ($sale_products as $product) {
            if (isset($product['sale_product_qty'], $product['sale_product_price'])) {
                $qty = (float)$product['sale_product_qty'];
                $price = (float)$product['sale_product_price'];
                if ($qty < 0 || $price < 0) {
                    error_log("Invalid product data: qty=$qty, price=$price for product_id={$product['product_id']}");
                    continue;
                }
                $sale_net_amount += $qty * $price;
                $total_items++;
                $total_qty += $qty;
            } else {
                error_log("Missing qty or price for product_id={$product['product_id']}");
            }
        }
    } else {
        error_log("No sale_products found for sale_id=$sale_id");
    }
    $vat_rate = 7.00;
    $sale_vat_amount = $sale_net_amount * ($vat_rate / 100);
    $sale_total_amount = $sale_net_amount + $sale_vat_amount;
} catch (PDOException $e) {
    error_log("Database query error: " . $e->getMessage());
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}
// Fetch members, personnel, and sale status
$members = $pdo->query("SELECT member_id, member_name FROM member ORDER BY member_name");

$default_id = 3;
$stmt = $pdo->prepare("SELECT * FROM personnel WHERE personnel_id = ?");
$stmt->execute([$default_id]);
$personnels = $stmt->fetch(PDO::FETCH_ASSOC);

$sale_status_result = $pdo->query("SELECT sale_status_id, sale_status_name FROM sale_status");

// Validate active sales against database
$valid_active_sale = [];
if (!empty($_SESSION['active_sales'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['active_sales']), '?'));
    try {
        // เปลี่ยนจาก sale_status_id = 2 เป็น sale_status_id IN (1, 2) เพื่อให้บิลที่ยืนยันแล้วยังคงอยู่ใน active_sales ชั่วคราว
        $stmt = $pdo->prepare("SELECT sale_id FROM sale WHERE sale_id IN ($placeholders) AND sale_status_id IN (1, 2)");
        $stmt->execute(array_values($_SESSION['active_sales']));
        $valid_sales = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $valid_active_sale = array_intersect($_SESSION['active_sales'], $valid_sales);
        $_SESSION['active_sales'] = $valid_active_sale;
        if (!in_array($_SESSION['current_sale_id'], $valid_active_sale)) {
            $_SESSION['current_sale_id'] = !empty($valid_active_sale) ? reset($valid_active_sale) : null;
            $sale_id = $_SESSION['current_sale_id'];
        }
        error_log("Validated active_sales: " . json_encode($valid_active_sale));
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

$sale_status_id = $sale_data['sale_status_id'] ?? 2;
$sale_status_name = $sale_data['sale_status_name'] ?? 'กำลังขาย';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการการขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Custom gradient backgrounds */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Animated elements */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Modern card styles */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Button hover effects */
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #047857);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(16, 185, 129, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(239, 68, 68, 0.4);
        }

        /* Input focus effects */
        .form-input {
            transition: all 0.3s ease;
            border-radius: 12px;
        }

        .form-input:focus {
            transform: scale(1.02);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        /* Tab styles */
        .tab-active {
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            color: #1e293b;
            border-bottom: 3px solid #3b82f6;
        }

        .tab-inactive {
            background: rgba(255, 255, 255, 0.7);
            color: #64748b;
        }

        /* Table styles */
        .modern-table {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .table-row:hover {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.05), rgba(147, 197, 253, 0.05));
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        /* Scrollbar styles */
        #billTabs::-webkit-scrollbar {
            height: 8px;
        }

        #billTabs::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 20px;
        }

        #billTabs::-webkit-scrollbar-thumb {
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            border-radius: 20px;
        }

        /* Status indicator */
        .status-indicator {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .status-indicator::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        /* Modal improvements */
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Floating action button */
        .fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            border: none;
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.4);
            transition: all 0.3s ease;
            z-index: 50;
        }

        .fab:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 12px 48px rgba(59, 130, 246, 0.6);
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
    <div class="main-body">
        <!-- Header with gradient and glass effect -->
        <header class="gradient-bg text-white shadow-2xl relative overflow-hidden">
            <div class="absolute inset-0 bg-white/10"></div>
            <div class=" mx-auto py-6 px-6 flex justify-between items-center relative z-10">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-store text-2xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold tracking-wider">Your Store POS</h1>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="status-indicator">
                        <span class="text-yellow-300 font-bold">ระบบพร้อมใช้งาน</span>
                    </div>
                    <div class="glass-effect rounded-xl px-4 py-2">
                        <label class="text-sm opacity-80">พนักงานขาย:</label>
                        <div class="font-bold">จอห์น โด</div>
                    </div>
                    <div class="glass-effect rounded-xl px-4 py-2">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <span class="text-sm">22 ก.ค. 2568</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="container mx-auto p-6 space-y-6">
            <!-- Bill tabs and new sale button -->
            <div class="card p-4 fade-in">
                <div class="flex items-center space-x-4">
                    <button id="new_sale" class="btn-primary text-white px-6 py-3 rounded-xl font-medium flex items-center space-x-2 transition-all duration-300">
                        <i class="fas fa-plus-circle"></i>
                        <span>เปิดบิลใหม่</span>
                    </button>
                    
                    <div id="billTabs" class="flex space-x-2 overflow-x-auto flex-nowrap max-w-full pb-2">
                        <div class="px-6 py-3 rounded-xl tab-active cursor-pointer whitespace-nowrap transition-all duration-300 hover:shadow-lg">
                            <i class="fas fa-receipt mr-2"></i>บิล #001
                        </div>
                        <div class="px-6 py-3 rounded-xl tab-inactive cursor-pointer whitespace-nowrap transition-all duration-300 hover:shadow-lg">
                            <i class="fas fa-receipt mr-2"></i>บิล #002
                        </div>
                        <div class="px-6 py-3 rounded-xl tab-inactive cursor-pointer whitespace-nowrap transition-all duration-300 hover:shadow-lg">
                            <i class="fas fa-receipt mr-2"></i>บิล #003
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left side - Product entry and list -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Product search section -->
                    <div class="card p-6 fade-in">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-barcode text-blue-600 mr-3"></i>
                            เพิ่มสินค้า
                        </h2>
                        <div class="flex space-x-4 items-end">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">รหัสสินค้า</label>
                                <input type="text" id="barcode_input" 
                                       class="form-input w-full p-4 border-2 border-gray-200 focus:border-blue-500 focus:ring-0" 
                                       placeholder="ป้อนหรือสแกนรหัสสินค้า" autocomplete="off">
                                <div id="barcode_suggestions" class="absolute z-20 w-full bg-white border border-gray-200 rounded-lg shadow-xl hidden mt-1"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">จำนวน</label>
                                <input type="number" id="qty_input" value="1" min="1" 
                                       class="form-input w-20 p-4 border-2 border-gray-200 focus:border-blue-500 focus:ring-0 text-center">
                            </div>
                            <button class="btn-primary px-6 py-4 rounded-xl text-white font-medium">
                                <i class="fas fa-plus mr-2"></i>เพิ่ม
                            </button>
                        </div>
                    </div>

                    <!-- Product list -->
                    <div class="card p-6 fade-in">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                <i class="fas fa-shopping-cart text-blue-600 mr-3"></i>
                                รายการสินค้า
                            </h2>
                            <div class="text-sm text-gray-500">
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full">3 รายการ</span>
                            </div>
                        </div>
                        
                        <div class="modern-table">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700">
                                        <th class="py-4 px-4 text-left font-semibold">รหัส</th>
                                        <th class="py-4 px-4 text-left font-semibold">ชื่อสินค้า</th>
                                        <th class="py-4 px-4 text-center font-semibold">จำนวน</th>
                                        <th class="py-4 px-4 text-right font-semibold">ราคา</th>
                                        <th class="py-4 px-4 text-right font-semibold">รวม</th>
                                        <th class="py-4 px-4 text-center font-semibold">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-row border-t border-gray-100">
                                        <td class="py-4 px-4 text-sm font-medium">P001</td>
                                        <td class="py-4 px-4 text-sm">น้ำดื่มตราเพชร 600ml</td>
                                        <td class="py-4 px-4 text-center text-sm">
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full">2</span>
                                        </td>
                                        <td class="py-4 px-4 text-right text-sm">15.00</td>
                                        <td class="py-4 px-4 text-right font-bold text-blue-600">30.00</td>
                                        <td class="py-4 px-4 text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button class="p-2 bg-yellow-100 text-yellow-600 rounded-lg hover:bg-yellow-200 transition-colors">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="table-row border-t border-gray-100">
                                        <td class="py-4 px-4 text-sm font-medium">P002</td>
                                        <td class="py-4 px-4 text-sm">ขนมปังโฮลวีท</td>
                                        <td class="py-4 px-4 text-center text-sm">
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full">1</span>
                                        </td>
                                        <td class="py-4 px-4 text-right text-sm">45.00</td>
                                        <td class="py-4 px-4 text-right font-bold text-blue-600">45.00</td>
                                        <td class="py-4 px-4 text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button class="p-2 bg-yellow-100 text-yellow-600 rounded-lg hover:bg-yellow-200 transition-colors">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right side - Payment and summary -->
                <div class="space-y-6">
                    <!-- Customer section -->
                    <div class="card p-6 fade-in">
                        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-user text-blue-600 mr-3"></i>
                            ข้อมูลลูกค้า
                        </h2>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">สมาชิก</label>
                            <input type="text" class="form-input w-full p-3 border-2 border-gray-200 focus:border-blue-500 focus:ring-0" 
                                   placeholder="ค้นหาสมาชิก">
                        </div>
                    </div>

                    <!-- Summary section -->
                    <div class="card p-6 fade-in">
                        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-calculator text-blue-600 mr-3"></i>
                            สรุปการขาย
                        </h2>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">จำนวนรายการ:</span>
                                <span class="font-medium">3 รายการ</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">จำนวนชิ้น:</span>
                                <span class="font-medium">3 ชิ้น</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">ยอดสุทธิ:</span>
                                <span class="font-medium">75.00 บาท</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">VAT (7%):</span>
                                <span class="font-medium">5.25 บาท</span>
                            </div>
                            <hr class="border-gray-200">
                            <div class="flex justify-between text-lg font-bold">
                                <span>ยอดรวม:</span>
                                <span class="text-blue-600">80.25 บาท</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment section -->
                    <div class="card p-6 fade-in">
                        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-credit-card text-blue-600 mr-3"></i>
                            การชำระเงิน
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">เงินที่รับ</label>
                                <input type="number" class="form-input w-full p-3 border-2 border-gray-200 focus:border-blue-500 focus:ring-0" 
                                       placeholder="0.00" step="0.01">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">เงินทอน</label>
                                <input type="text" class="form-input w-full p-3 border-2 border-gray-200 bg-gray-50" 
                                       placeholder="0.00" readonly>
                            </div>
                            <button class="btn-primary w-full py-3 rounded-xl text-white font-medium">
                                <i class="fas fa-calculator mr-2"></i>คำนวณเงินทอน
                            </button>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="space-y-3 fade-in">
                        <button class="btn-success w-full py-4 rounded-xl text-white font-bold text-lg transition-all duration-300">
                            <i class="fas fa-check-circle mr-2"></i>ยืนยันการขาย
                        </button>
                        <button class="btn-danger w-full py-3 rounded-xl text-white font-medium transition-all duration-300">
                            <i class="fas fa-times-circle mr-2"></i>ยกเลิกการขาย
                        </button>
                        <button class="w-full py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl font-medium hover:shadow-lg transition-all duration-300">
                            <i class="fas fa-print mr-2"></i>พิมพ์ใบเสร็จ
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating Action Button -->
        <button class="fab">
            <i class="fas fa-question text-xl"></i>
        </button>
    </div>

    <!-- Scripts -->
    <script>
        $(document).ready(function() {
            // Add fade-in animation on load
            $('.fade-in').each(function(i) {
                $(this).delay(i * 100).queue(function(next) {
                    $(this).addClass('opacity-100').dequeue();
                });
            });

            // Auto-focus on barcode input
            $('#barcode_input').focus();

            // Add smooth scroll behavior
            $('html').css('scroll-behavior', 'smooth');

            // Add ripple effect to buttons
            $('.btn-primary, .btn-success, .btn-danger').on('click', function(e) {
                let ripple = $('<span class="ripple"></span>');
                $(this).append(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });

            // Tab switching animation
            $('#billTabs > div').on('click', function() {
                $('#billTabs > div').removeClass('tab-active').addClass('tab-inactive');
                $(this).removeClass('tab-inactive').addClass('tab-active');
            });

            // Quantity input enhancement
            $('#qty_input').on('wheel', function(e) {
                e.preventDefault();
                let currentVal = parseInt($(this).val());
                if (e.originalEvent.deltaY < 0) {
                    $(this).val(currentVal + 1);
                } else if (currentVal > 1) {
                    $(this).val(currentVal - 1);
                }
            });

            // Add loading state to buttons
            $('.btn-success').on('click', function() {
                let $btn = $(this);
                let originalText = $btn.html();
                $btn.html('<span class="loading-spinner mr-2"></span>กำลังดำเนินการ...');
                $btn.prop('disabled', true);
                
                setTimeout(() => {
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }, 2000);
            });

            // Enhanced barcode input with suggestion dropdown
            $('#barcode_input').on('input', function() {
                let value = $(this).val();
                if (value.length > 2) {
                    // Simulate product suggestions
                    let suggestions = [
                        { code: 'P001', name: 'น้ำดื่มตราเพชร 600ml', price: 15.00 },
                        { code: 'P002', name: 'ขนมปังโฮลวีท', price: 45.00 },
                        { code: 'P003', name: 'นมสดตราดาว 250ml', price: 12.50 }
                    ];
                    
                    let filteredSuggestions = suggestions.filter(item => 
                        item.code.toLowerCase().includes(value.toLowerCase()) || 
                        item.name.toLowerCase().includes(value.toLowerCase())
                    );
                    
                    if (filteredSuggestions.length > 0) {
                        let html = '';
                        filteredSuggestions.forEach(item => {
                            html += `<div class="p-3 hover:bg-blue-50 cursor-pointer border-b last:border-b-0" data-code="${item.code}">
                                <div class="font-medium">${item.code} - ${item.name}</div>
                                <div class="text-sm text-gray-500">฿${item.price.toFixed(2)}</div>
                            </div>`;
                        });
                        $('#barcode_suggestions').html(html).removeClass('hidden');
                    } else {
                        $('#barcode_suggestions').addClass('hidden');
                    }
                } else {
                    $('#barcode_suggestions').addClass('hidden');
                }
            });

            // Handle suggestion selection
            $(document).on('click', '#barcode_suggestions > div', function() {
                let code = $(this).data('code');
                $('#barcode_input').val(code);
                $('#barcode_suggestions').addClass('hidden');
                $('#qty_input').focus();
            });

            // Hide suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#barcode_input, #barcode_suggestions').length) {
                    $('#barcode_suggestions').addClass('hidden');
                }
            });
        });
    </script>
</body>

</html>
<?php

ob_end_flush();
?>