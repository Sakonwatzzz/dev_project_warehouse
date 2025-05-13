<?php
try {
    require_once __DIR__ . '../../../../config/database_server.php';
    error_log('Database connected including successfuly database_server.php');
} catch (Exception $e) {
    error_log("Database failed included database_server.php : " . $e->getMessage());
}
// เพิ่มการแสดงผลข้อผิดพลาดสำหรับการดีบักชั่วคราวตั้งค่า display_errors เป็น 1 ในไฟล์เพื่อดูข้อผิดพลาดโดยตรง
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/logs/php_errors.log');

ob_start();
session_start();

// Set timezone
date_default_timezone_set('Asia/Bangkok');
$currentDateTime = date("Y-m-d H:i:s");
$displayDateTime = date("d/m/Y H:i:s");

// Initialize active sales if not set
if (!isset($_SESSION['active_sales'])) {
    $_SESSION['active_sales'] = [];
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


// Handle close billTab via Ajax



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

        $sale_total_amount = 0;
        $total_qty = 0;
        foreach ($sale_products as $product) {
            $sale_total_amount += $product['sale_product_qty'] * $product['sale_product_price'];
            $total_qty += $product['sale_product_qty'];
        }

        // Prepare receipt data
        $receipt_data = [
            'sale_id' => $sale_data['sale_id'],
            'sale_date' => date("d/m/Y H:i:s", strtotime($sale_data['sale_date'])),
            'member_name' => $sale_data['member_name'] ?? 'ไม่ระบุ',
            'personnel_name' => $sale_data['personnel_name'] ?? 'ไม่ระบุ',
            'products' => $sale_products,
            'total_qty' => $total_qty,
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

    $sale_total_amount = 0;
    $total_items = 0;
    $total_qty = 0;
    foreach ($sale_products as $product) {
        $sale_total_amount += $product['sale_product_qty'] * $product['sale_product_price'];
        $total_items++;
        $total_qty += $product['sale_product_qty'];
    }
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="page/sale/js/sale_print_receipt.js"></script>
    <script src="page/sale/js/sale_new_sale.js"></script>
    <script src="page/sale/js/sale_switch.js"></script>
    <script src="page/sale/js/sale_insert.js"></script>
    <!-- <script src="page/sale/js/sale_modal.js"> </script> -->

    <!-- ตรวจสอบว่า sale_delete.js ถูกโหลดใน sale_add.php อย่างไร. หากเป็นไฟล์ .js แยก, ต้องส่ง 
    sale_id จาก sale_add.php ไปยัง JavaScript ผ่านวิธีอื่น เช่น การกำหนดตัวแปรใน <script> หรือผ่าน attribute ใน HTML. -->
    <script>
        const SALE_ID = '<?php echo htmlspecialchars($sale_id); ?>';
    </script>
    <script src="page/sale/js/sale_delete.js"></script>
    <script src="page/sale/js/sale_bill.js"></script>
    <script src="page/sale/js/sale_confirm_btn.js"></script>
    <script src="page/sale/js/sale_modal_edit.js"></script>
    <script src="page/sale/js/sale_payment.js"></script>

    <style>
        #edit-product-modal {
            z-index: 1000;
        }

        .main-body[aria-hidden="true"] input:not(.modal-active),
        .main-body[aria-hidden="true"] select:not(.modal-active),
        .main-body[aria-hidden="true"] button:not(.modal-active) {
            pointer-events: none;
            visibility: hidden;
        }

        .modal-active {
            pointer-events: auto !important;
            visibility: visible !important;
        }
    </style>
    <!-- CSS for billTabs -->
    <style>
        .tab-active {
            background-color: rgb(255, 255, 255);
            color: black;
        }

        .tab-inactive {
            background-color: #e5e7eb;
            color: black;
        }

        #billTabs::-webkit-scrollbar {
            height: 10px;
        }

        #billTabs::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        #billTabs::-webkit-scrollbar-thumb {
            background: rgb(121, 120, 120);
            border-radius: 4px;
        }

        #billTabs::-webkit-scrollbar-thumb:hover {
            background: rgb(65, 65, 65);
        }
    </style>

</head>

<body class="bg-gray-100 font-sans">
    <div class="main-body">
        <header class="bg-gradient-to-r from-blue-600 to-indigo-800 text-white shadow-lg">
            <div class="container mx-auto py-4 px-6 flex justify-between items-center">
                <h1 class="text-2xl font-bold">Your Store or Company name POS
                </h1>
                <div class="flex items-center space-x-4">
                    <p id="sale-status" class="text-yellow-400 font-bold"> <?php echo htmlspecialchars($sale_status_name); ?></p>
                    <label class="text-base font-medium">พนักงานขาย:</label>
                    <div class="text-xll font-bold">
                        <input class="bg-transparent " type="text" name="personnel_id"
                            value="<?php echo $personnels['personnel_id']; ?>" readonly hidden>
                        <?php echo htmlspecialchars($personnels['personnel_name'] ?? 'ไม่พบชื่อ'); ?>
                    </div>
                    <span class="text-sm">วันที่: <?php echo $displayDateTime; ?></span>
                </div>
            </div>
        </header>
        <div class="container mx-auto p-6">
            <div class="flex items-center space-x-2 mb-6 rounded-lg shadow-md py-2 px-2">
                <button id="new_sale"
                    class="w-40 px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-600 transition-colors flex items-center ">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    เปิดบิลใหม่
                </button>
                <div id="billTabs"
                    class="flex space-x-2 overflow-x-auto flex-nowrap max-w-full pb-2 shadow-md p-2 py-2">
                    <?php foreach ($valid_active_sale as $active_sale_id): ?>
                        <div id="tab_<?php echo $active_sale_id; ?>"
                            class="px-4 py-1 rounded-md hover:bg-gray-300 transition-colors shadow-lg <?php echo $active_sale_id == $sale_id ? 'tab-active' : 'tab-inactive'; ?> cursor-pointer whitespace-nowrap"
                            onclick="switchBill(<?php echo $active_sale_id; ?>)">
                            บิล #<?php echo $active_sale_id; ?>
                        </div>
                        <button id="close_tab_<?php echo $active_sale_id; ?>" type="button"
                            class="text-red-500 text-xl font-bold pl-3">×</button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (isset($_SESSION['success'])): ?>
                <script>
                    Swal.fire({
                        toast: true,
                        position: 'center',
                        icon: 'success',
                        title: '<?php echo $_SESSION['success']; ?>',
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true
                    });
                    <?php unset($_SESSION['success']); ?>
                </script>
            <?php endif; ?>
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
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <form action="" method="POST" id="form_add_product" class="space-y-4" novalidate
                        onsubmit="return false;">
                        <div class="flex flex-wrap space-x-4 mb-4 items-center">
                            <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                            <input type="hidden" name="action" value="add_product">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">รหัสสินค้า</label>
                                <input type="text" name="barcode" id="barcode_input"
                                    class="p-3 w-full h-8 border border-gray-300 rounded-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    placeholder="ป้อนหรือสแกนรหัสสินค้า" autocomplete="off" autofocus
                                    <?php echo ($sale_status_id === 1) ? 'disable' : ''; ?>>
                                <div id="barcode_suggestions"
                                    class="z-10 w-full bg-white border border-gray-200 rounded-b-lg shadow-lg hidden">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                                <input type="number" name="qty" value="1" min="1" id="barcode-qty"
                                    class="p-2 w-12 h-8 border border-gray-300 rounded-sm"
                                    <?php echo ($sale_status_id === 1) ? 'disable' : ''; ?>>
                            </div>
                            <div hidden>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ราคาขาย</label>
                                <select name="price_type" class="p-2 w-32 border border-gray-300 rounded-sm">
                                    <option value="price4">ราคาขายจริง</option>
                                    <option value="price1">ราคา 1</option>
                                    <option value="price2">ราคา 2</option>
                                    <option value="price3">ราคา 3</option>
                                </select>
                            </div>
                        </div>
                        <div id="product_info" class="text-sm text-gray-600 hidden"></div>
                    </form>
                    <!-- Payment Section -->
                    <!-- <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                    </div> -->
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
                                    <?php if (empty($sale_products)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-8 text-gray-500">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                </svg>
                                                <p class="mt-2">ไม่พบสินค้าในการขายนี้</p>
                                                <p class="text-sm">กรุณาเพิ่มสินค้า</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
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
                                                    <?php if ($sale_data['sale_status_id'] != 1): ?>
                                                        <div class="flex justify-center space-x-2">
                                                            <button class="edit-product p-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors shadow-md"
                                                                data-id="<?php echo htmlspecialchars($product['sale_product_id']); ?>"
                                                                data-barcode="<?php echo htmlspecialchars($product['product_id']); ?>"
                                                                data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                                data-qty="<?php echo htmlspecialchars($product['sale_product_qty']); ?>"
                                                                data-price="<?php echo htmlspecialchars($product['sale_product_price']); ?>"
                                                                data-capital="<?php echo htmlspecialchars($product['sale_product_capital']); ?>">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                </svg>
                                                            </button>
                                                            <button type="button" class="delete-product p-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors shadow-md"
                                                                data-barcode="<?php echo htmlspecialchars($product['product_id']); ?>"
                                                                data-qty="1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="space-y-6">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            ข้อมูลการขาย
                        </h2>
                        <form method="POST" action="" id="confirm-sale-form" novalidate onsubmit="return false;">
                            <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                            <input type="hidden" name="action" value="confirm_sale">
                            <div class="space-y-4">
                                <div class="space-y-3">
                                    <button id="cancel_sale" type="button"
                                        class="w-full py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        ยกเลิกการขาย
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 gap-2" hidden>
                                    <label class="text-base font-medium text-gray-700" hidden>วันที่ทำการขาย:</label>
                                    <input name="sale_date" value="<?php echo $displayDateTime; ?>" readonly hidden
                                        class="p-2 bg-gray-50 border border-gray-300 rounded text-gray-500 text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2" hidden>
                                    <label class="text-base font-medium text-gray-700" hidden>สถานะการขาย:</label>
                                    <select name="sale_status_id"
                                        class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        hidden>
                                        <?php while ($sale_status = $sale_status_result->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $sale_status['sale_status_id']; ?>" <?php echo ($sale_status_id == $sale_status['sale_status_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sale_status['sale_status_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 gap-2 pb-4">
                                        <label class="text-base font-medium text-gray-700">สมาชิก:</label>
                                        <input type="text" id="member_search"
                                            class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="ค้นหาสมาชิก"
                                            <?php echo ($sale_data['sale_status_id'] == 1) ? 'disabled' : ''; ?>>
                                        <input type="hidden" name="member_id" id="member_id"
                                            <?php echo ($sale_data['sale_status_id'] == 1) ? 'disabled' : ''; ?>>
                                        <div id="member_suggestions"
                                            class="absolute z-10 w-auto bg-white border border-gray-200 rounded-b-lg shadow-lg hidden">
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg shadow-lg">
                        <h2 class="text-lg font-semibold mb-3 text-gray-800 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            การชำระเงิน
                        </h2>
                        <div class="flex justify-between text-gray-800 pb-2">
                            <span class="font-medium text-sm">จำนวนรายการ:</span>
                            <span class="total-items text-sm"><?php echo $total_items; ?> รายการ</span>
                        </div>
                        <div class="flex justify-between text-gray-800 pb-2">
                            <span class="font-medium text-sm">จำนวนชิ้น:</span>
                            <span class="total-qty text-sm"><?php echo $total_qty; ?> ชิ้น</span>
                        </div>

                        <div class="border-t border-gray-200 mt-2 pt-2 flex justify-between font-semibold text-base">
                            <span>ยอดรวมสุทธิ:</span>
                            <span type="number" id="sale_total_amount" class="text-blue-700 total-amount"><?php echo number_format($sale_total_amount, 2); ?>
                            บาท</span>
                        </div>

                        <div class="flex flex-row space-x-4 items-end pb-6">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">เงินที่ชำระ
                                    (บาท):</label>
                                <input type="number" id="sale_paid_amount" value="" min="1"
                                    class="p-2 w-full text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    step="0.01" placeholder="ป้อนจำนวนเงิน">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">เงินทอน (บาท):</label>
                                <input type="text" id="sale_change_amount"
                                    class="p-2 w-full text-sm bg-gray-50 border border-gray-300 rounded-lg text-blue-700 font-semibold"
                                    readonly>
                            </div>
                            <div>
                                <button id="calculate_change"
                                    class="py-2 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                    </svg>
                                    คำนวณ
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <!-- ปุ่มพิมพ์ใบเสร็จ (เริ่มต้นซ่อน) -->
                        <button id="print_receipt"
                            class="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center <?php echo ($sale_data['sale_status_id'] == 1) ? '' : 'hidden'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            พิมพ์ใบเสร็จ
                        </button>

                        <!-- ปุ่มยืนยันรายการขาย -->
                        <button type="submit" form="confirm-sale-form" id="confirm-sale-btn"
                            class="w-full py-3 bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition-colors flex items-center justify-center">
                            <span class="loading-spinner hidden">กำลังประมวลผล...</span>
                            <span class="btn-text">
                                <!-- <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg> -->
                                ยืนยันรายการขาย
                            </span>
                        </button>
                    </div>
                    </form>

                </div>
            </div>
        </div>

        <!-- Modal -->
        <div id="edit-product-modal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
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
                <form id="edit-product-form" method="POST" action="" onsubmit="return false;">
                    <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit-barcode" name="barcode">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสสินค้า</label>
                            <input type="text" id="edit-barcode-display"
                                class="w-full p-2 border border-gray-300 rounded modal-active bg-gray-200" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสินค้า</label>
                            <input type="text" id="edit-product-name"
                                class="w-full p-2 border border-gray-300 rounded modal-active bg-gray-200" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                            <input type="number" id="edit-qty" name="qty"
                                class="w-full p-2 border border-gray-300 rounded modal-active" min="1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ราคาขาย</label>
                            <input type="number" id="edit-price" name="price"
                                class="w-full p-2 border border-gray-300 rounded modal-active bg-gray-200" step="0.01"
                                readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ต้นทุน</label>
                            <input type="number" id="edit-capital" name="capital"
                                class="w-full p-2 border border-gray-300 rounded modal-active bg-gray-200" step="0.01"
                                readonly>
                        </div>
                        <!-- <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">อ้างอิง</label>
                                <input type="text" id="edit-reference" name="sale_reference" class="w-full p-2 border border-gray-300 rounded modal-active">
                            </div> -->
                    </div>
                    <div class="mt-6 flex space-x-3">
                        <button type="submit"
                            class="flex-1 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors modal-active">บันทึกการแก้ไข</button>
                        <button type="button" id="cancel-edit"
                            class="flex-1 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors modal-active">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $('#print_receipt').addClass('hidden');
                // ตรวจสอบว่า jQuery โหลดสำเร็จ
                if (typeof $ === 'undefined') {
                    console.error('jQuery not loaded');
                    alert('ไม่สามารถโหลด jQuery ได้ กรุณาตรวจสอบการเชื่อมต่อหรือรีเฟรชหน้า');
                    return;
                }

                // ตรวจสอบว่า SweetAlert2 โหลดสำเร็จ
                if (typeof Swal === 'undefined') {
                    console.error('SweetAlert2 not loaded');
                    alert('ไม่สามารถโหลด SweetAlert2 ได้ กรุณาตรวจสอบการเชื่อมต่อหรือรีเฟรชหน้า');
                    return;
                }
                // Toast instance สำหรับ SweetAlert2
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top', // มุมขวาบน
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                        $('.main-body').removeAttr('aria-hidden');
                    },
                    didClose: () => {
                        if (!$('#edit-product-modal').is(':visible')) {
                            $('#barcode_input').focus();
                        }
                    }
                });
                const saleStatusId = <?php echo json_encode($sale_data['sale_status_id'] ?? 2); ?>;
                if (saleStatusId === 1) {
                    $('#print_receipt').removeClass('hidden');
                    $('.edit-product').addClass('hidden');
                    $('.delete-product').addClass('hidden');
                    $('#member_search, #member_id, select[name="personnel_id"]').prop('disabled', true);
                    $('#confirm-sale-btn').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                    $('#barcode_input, #barcode-qty').prop('disabled', true);
                    $('#form_add_product').find('input, select, button').prop('disabled', true);
                } else {
                    $('#print_receipt').addClass('hidden');
                }

                let dynamicTotalAmount = <?php echo $sale_total_amount; ?>; // ค่าเริ่มต้น


                $('#member_search').on('input', function() {
                    const query = $(this).val();
                    if (query.length < 2) {
                        $('#member_suggestions').addClass('hidden').empty();
                        return;
                    }
                    $.ajax({
                        url: 'page/sale/ajax/ajax_search_member.php',
                        method: 'GET',
                        data: {
                            query: query
                        },
                        dataType: 'json',
                        success: function(response) {
                            const members = response;
                            const suggestions = $('#member_suggestions').removeClass('hidden').empty();
                            if (members.length === 0) {
                                suggestions.append('<div class="p-2 text-gray-500">ไม่พบสมาชิก</div>');
                                return;
                            }
                            members.forEach(member => {
                                suggestions.append(`
                                    <div class="p-2 hover:bg-gray-100 cursor-pointer" data-id="${member.member_id}">
                                        ${member.member_name}
                                    </div>
                                `);
                            });
                            suggestions.find('div').on('click', function() {
                                const memberId = $(this).data('id');
                                const memberName = $(this).text();
                                $('#member_search').val(memberName);
                                $('#member_id').val(memberId);
                                suggestions.addClass('hidden').empty();
                            });
                        },
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'ข้อผิดพลาด',
                                text: xhr.responseText || 'ไม่สามารถค้นหาสมาชิกได้'
                            });
                        }
                    });
                });

                // // คำนวณเงินทอน
                // $('#calculate_change').on('click', function() {
                //     const paid = parseFloat($('#sale_paid_amount').val()) || 0;
                //     const total = dynamicTotalAmount;
                //     const change = paid - total;
                //     //console.log เพื่อดีบักตรวจสอบการใช้งานว่าล้มเหลวหรือไม่ ใช้ตอนพัฒนาเท่านั้น!!!
                //     console.log("Calculate change: ", {
                //         paid,
                //         total
                //     });
                //     $('#sale_change_amount').val(change >= 0 ? change.toFixed(2) : 'เงินไม่พอ');
                //     $('#print_receipt').prop('disabled', change < 0);
                // });


                // // อัปเดตเงินทอนเมื่อกรอกเงินที่ชำระ
                // $('#sale_paid_amount').off('input').on('input', function() {
                //     $('#calculate_change').click();
                // });

                // function refreshSummary() {
                //     $('.total-amount').text(dynamicTotalAmount.toFixed(2) + ' บาท');
                //     $('#calculate_change').click();
                // }
            });

            // Array to track bills in client-side
            let bills = <?php echo json_encode($valid_active_sale); ?>;
            let currentSaleId = <?php echo json_encode($sale_id); ?>;

            // Function to update tab styles based on currentSaleId
            function updateTabStyles() {
                $('#billTabs > div').each(function() {
                    const tabId = $(this).attr('id').replace('tab_', '');
                    if (tabId == currentSaleId) {
                        $(this).removeClass('tab-inactive').addClass('tab-active');
                    } else {
                        $(this).removeClass('tab-active').addClass('tab-inactive');
                    }
                });
            }
        </script>
    </div>
    </div>
</body>

</html>
<?php ob_end_flush(); ?>