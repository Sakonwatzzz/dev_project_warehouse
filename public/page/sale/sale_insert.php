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

// Get sale_id from POST, GET, or session
$sale_id = $_POST['sale_id'] ?? $_GET['sale_id'] ?? $_SESSION['current_sale_id'] ?? null;

// Handle new sale creation via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_sale') {
    header('Content-Type: application/json');
    try {
        $pdo->beginTransaction();
        $insertSale = $pdo->prepare("INSERT INTO sale (sale_date, sale_status_id, member_id, personnel_id) VALUES (?, 1, ?, ?)");
        $insertSale->execute([$currentDateTime, null, null]);
        $new_sale_id = $pdo->lastInsertId();
        $_SESSION['active_sales'][] = $new_sale_id;
        $_SESSION['current_sale_id'] = $new_sale_id;
        $pdo->commit();
        echo json_encode(['status' => 'success', 'sale_id' => $new_sale_id, 'message' => 'บิลใหม่ #' . $new_sale_id . ' ถูกสร้างเรียบร้อย']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error creating new sale: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถสร้างบิลใหม่ได้: ' . $e->getMessage()]);
    }
    exit();
}

// Handle sale cancellation via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_sale') {
    header('Content-Type: application/json');
    $cancel_sale_id = $_POST['sale_id'] ?? null;
    if (!$cancel_sale_id || !in_array($cancel_sale_id, $_SESSION['active_sales'])) {
        echo json_encode(['status' => 'error', 'message' => 'บิลไม่ถูกต้องหรือไม่พบ']);
        exit();
    }
    try {
        $pdo->beginTransaction();
        // Restore stock in lot_product
        $stmt = $pdo->prepare("SELECT product_id, sale_product_qty FROM sale_product WHERE sale_id = ?");
        $stmt->execute([$cancel_sale_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $product) {
            adjustLotRemain($pdo, $product['product_id'], $product['sale_product_qty'], true);
        }

        // Delete sale_product records
        $pdo->prepare("DELETE FROM sale_product WHERE sale_id = ?")->execute([$cancel_sale_id]);

        // Delete sale record
        $pdo->prepare("DELETE FROM sale WHERE sale_id = ?")->execute([$cancel_sale_id]);

        // Update session
        $_SESSION['active_sales'] = array_diff($_SESSION['active_sales'], [$cancel_sale_id]);
        if ($_SESSION['current_sale_id'] == $cancel_sale_id) {
            $_SESSION['current_sale_id'] = !empty($_SESSION['active_sales']) ? reset($_SESSION['active_sales']) : null;
        }

        $pdo->commit();
        $redirect_url = $_SESSION['current_sale_id'] ? "?page=sale_insert&sale_id={$_SESSION['current_sale_id']}" : "?page=sale_insert";
        echo json_encode(['status' => 'success', 'message' => 'บิล #' . $cancel_sale_id . ' ถูกลบเรียบร้อย', 'redirect' => $redirect_url]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error cancelling sale: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกบิลได้: ' . $e->getMessage()]);
    }
    exit();
}

// Handle switch sale via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'switch_sale') {
    header('Content-Type: application/json');
    $new_sale_id = $_POST['sale_id'] ?? null;
    if ($new_sale_id && in_array($new_sale_id, $_SESSION['active_sales'])) {
        $_SESSION['current_sale_id'] = $new_sale_id;
        echo json_encode(['status' => 'success', 'sale_id' => $new_sale_id, 'message' => 'เปลี่ยนไปใช้บิล #' . $new_sale_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'บิลไม่ถูกต้องหรือไม่พบ']);
    }
    exit();
}

// Create new sale if none exists
if (!$sale_id && (!isset($_GET['page']) || $_GET['page'] !== 'sale_list')) {
    try {
        $pdo->beginTransaction();
        $insertSale = $pdo->prepare("INSERT INTO sale (sale_date, sale_status_id, member_id, personnel_id) VALUES (?, 1, ?, ?)");
        $insertSale->execute([$currentDateTime, null, null]);
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

/**
 * Helper function to adjust lot_product remain
 * @param PDO $pdo
 * @param string $productId
 * @param int $qty
 * @param bool $addMode
 */
function adjustLotRemain(PDO $pdo, string $productId, int $qty, bool $addMode = false, $warehouse_id = null)
{
    $remainingToProcess = $qty;
    $lot_ids = [];
    try {
        while ($remainingToProcess > 0) {
            $query = "
                SELECT lot_product_id, lot_product_remain 
                FROM lot_product 
                WHERE product_id = ? AND lot_product_remain > 0
            ";
            if ($warehouse_id) {
                $query .= " AND warehouse_id = ?";
            }
            $query .= " ORDER BY lot_product_date ASC, lot_product_id ASC LIMIT 1";
            $stmtLot = $pdo->prepare($query);
            $params = [$productId];
            if ($warehouse_id) {
                $params[] = $warehouse_id;
            }
            $stmtLot->execute($params);
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
        // แม้เกิดข้อผิดพลาด ให้คืนค่า array ว่างเพื่อป้องกัน null
        error_log("Error in adjustLotRemain: " . $e->getMessage());
        throw $e; // โยน Exception ต่อเพื่อให้ caller จัดการ
    }
    return $lot_ids;
}

// Handle product add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode']) && !isset($_POST['action'])) {
    try {
        $barcode = trim($_POST['barcode']);
        $qty = is_numeric($_POST['qty'] ?? null) ? (int) $_POST['qty'] : 1;
        $price = is_numeric($_POST['price'] ?? null) ? (float) $_POST['price'] : 0;
        $warehouse_id = $_POST['warehouse_id'] ?? null;
        $price_type = $_POST['price_type'] ?? 'price4';
        $sale_reference = $_POST['sale_reference'] ?? null;

        if (empty($barcode)) {
            throw new Exception("กรุณาป้อนรหัสสินค้า");
        }
        if ($qty <= 0) {
            throw new Exception("จำนวนต้องมากกว่า 0");
        }

        $pdo->beginTransaction();

        // ตรวจสอบสินค้า
        $stmtP = $pdo->prepare("
            SELECT product_name, product_price1, product_price2, product_price3, product_price4, product_capital 
            FROM product 
            WHERE product_id = ?
        ");
        $stmtP->execute([$barcode]);
        $prod = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new Exception("รหัสสินค้า $barcode ไม่พบในระบบ");
        }

        // เลือกราคา
        $unitPrice = $price > 0 ? $price : $prod['product_' . $price_type];

        // ดึงราคาทุนจากล็อต
        $stmtLot = $pdo->prepare("
            SELECT lot_product_capital 
            FROM lot_product 
            WHERE product_id = ? AND lot_product_remain > 0" . ($warehouse_id ? " AND warehouse_id = ?" : "") . "
            ORDER BY lot_product_date ASC LIMIT 1
        ");
        $params = [$barcode];
        if ($warehouse_id) {
            $params[] = $warehouse_id;
        }
        $stmtLot->execute($params);
        $lot = $stmtLot->fetch(PDO::FETCH_ASSOC);
        $unitCapital = $lot ? $lot['lot_product_capital'] : $prod['product_capital'];

        // ตรวจสอบว่ามีสินค้านี้ในบิลแล้วหรือไม่
        $stmt = $pdo->prepare("
            SELECT sale_product_qty, sale_product_price, sale_product_capital 
            FROM sale_product 
            WHERE sale_id = ? AND product_id = ?
        ");
        $stmt->execute([$sale_id, $barcode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = $existing['sale_product_qty'] + $qty;
            $newPrice = $price > 0 ? $price : $existing['sale_product_price'];
            $newCapital = $lot ? $lot['lot_product_capital'] : $existing['sale_product_capital'];

            $pdo->prepare("
                UPDATE sale_product 
                SET sale_product_qty = ?, sale_product_price = ?, sale_product_capital = ?, warehouse_id = ?
                WHERE sale_id = ? AND product_id = ?
            ")->execute([$newQty, $newPrice, $newCapital,  $warehouse_id, $sale_id, $barcode]);
        } else {
            $pdo->prepare("
                INSERT INTO sale_product 
                (sale_id, product_id, sale_product_qty, sale_product_price, sale_product_capital, , warehouse_id )
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$sale_id, $barcode, $qty, $unitPrice, $unitCapital,  $warehouse_id]);
        }

        // ตัดสต็อก
        try {
            $lot_ids = adjustLotRemain($pdo, $barcode, $qty, false, $warehouse_id);
            if (is_array($lot_ids) && !empty($lot_ids)) {
                foreach ($lot_ids as $lot) {
                    $pdo->prepare("
                        UPDATE sale_product 
                        SET lot_product_id = ? 
                        WHERE sale_id = ? AND product_id = ?
                    ")->execute([$lot['lot_product_id'], $sale_id, $barcode]);
                }
            } else {
                // ไม่มีล็อตที่ตัด อาจตั้ง lot_product_id เป็น null
                $pdo->prepare("
                    UPDATE sale_product 
                    SET lot_product_id = NULL 
                    WHERE sale_id = ? AND product_id = ?
                ")->execute([$sale_id, $barcode]);
            }
        } catch (Exception $e) {
            throw new Exception("ไม่สามารถตัดสต็อกได้: " . $e->getMessage());
        }

        $pdo->commit();
        $_SESSION['success'] = "เพิ่มสินค้าสำเร็จ";
        header("Location: ?page=sale_insert&sale_id=$sale_id");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error adding product: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header("Location: ?page=sale_insert&sale_id=$sale_id");
    }
    exit();
}

// Handle product delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $barcode = trim($_POST['barcode']);
        $qty = (int) ($_POST['qty'] ?? 1);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT sale_product_qty 
            FROM sale_product 
            WHERE sale_id = ? AND product_id = ?
        ");
        $stmt->execute([$sale_id, $barcode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = $existing['sale_product_qty'] - $qty;
            if ($newQty <= 0) {
                $pdo->prepare("
                    DELETE FROM sale_product 
                    WHERE sale_id = ? AND product_id = ?
                ")->execute([$sale_id, $barcode]);
            } else {
                $pdo->prepare("
                    UPDATE sale_product 
                    SET sale_product_qty = ? 
                    WHERE sale_id = ? AND product_id = ?
                ")->execute([$newQty, $sale_id, $barcode]);
            }
            adjustLotRemain($pdo, $barcode, $qty, true);
            $pdo->commit();
            $_SESSION['success'] = "ลบสินค้าสำเร็จ";
        } else {
            throw new Exception("ไม่พบสินค้าในรายการ");
        }

        header("Location: ?page=sale_insert&sale_id=$sale_id");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error deleting product: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header("Location: ?page=sale_insert&sale_id=$sale_id");
    }
    exit();
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $barcode = trim($_POST['barcode']);
        $qty = (int) ($_POST['qty'] ?? 1);
        $price = (float) ($_POST['price'] ?? 0);
        $capital = (float) ($_POST['capital'] ?? 0);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT sale_product_qty, sale_product_price, sale_product_capital 
            FROM sale_product 
            WHERE sale_id = ? AND product_id = ?
        ");
        $stmt->execute([$sale_id, $barcode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $oldQty = $existing['sale_product_qty'];
            $qtyDiff = $qty - $oldQty;

            $pdo->prepare("
                UPDATE sale_product 
                SET sale_product_qty = ?, sale_product_price = ?, sale_product_capital = ? 
                WHERE sale_id = ? AND product_id = ?
            ")->execute([$qty, $price, $capital, $sale_id, $barcode]);

            if ($qtyDiff > 0) {
                adjustLotRemain($pdo, $barcode, $qtyDiff, false);
            } elseif ($qtyDiff < 0) {
                adjustLotRemain($pdo, $barcode, abs($qtyDiff), true);
            }

            $pdo->commit();
            $_SESSION['success'] = "แก้ไขสินค้าสำเร็จ";
        } else {
            throw new Exception("ไม่พบสินค้าในรายการ");
        }

        header("Location: ?page=sale_insert&sale_id=$sale_id");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating product: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header("Location: ?page=sale_insert&sale_id=$sale_id");
    }
    exit();
}

// Handle sale confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_sale') {
    try {
        $pdo->beginTransaction();

        $check_sale = $pdo->prepare("SELECT * FROM sale WHERE sale_id = ?");
        $check_sale->execute([$sale_id]);
        if (!$check_sale->fetch()) {
            throw new Exception("ไม่พบข้อมูลการขาย");
        }

        $check_products = $pdo->prepare("SELECT COUNT(*) FROM sale_product WHERE sale_id = ?");
        $check_products->execute([$sale_id]);
        if ($check_products->fetchColumn() == 0) {
            throw new Exception("ไม่พบสินค้าในรายการขาย กรุณาเพิ่มสินค้าก่อนยืนยันการขาย");
        }

        $check_query = "SELECT sp.product_id, COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name, 
                       sp.sale_product_price, sp.sale_product_capital 
                       FROM sale_product sp
                       LEFT JOIN product p ON p.product_id = sp.product_id
                       WHERE sp.sale_id = ? 
                       AND (p.product_name IS NULL OR p.product_name = 'ยังไม่ระบุ' OR sp.sale_product_price = 0 OR sp.sale_product_capital = 0)";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$sale_id]);
        $invalid_products = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($invalid_products) > 0) {
            $invalid_list = '';
            foreach ($invalid_products as $product) {
                $invalid_list .= "- รหัส: {$product['product_id']} ชื่อ: {$product['product_name']}\n";
            }
            throw new Exception("พบสินค้าที่ข้อมูลไม่ครบถ้วน:\n" . $invalid_list);
        }

        $updateSale = $pdo->prepare("UPDATE sale SET sale_status_id = 2 WHERE sale_id = ?");
        $updateSale->execute([$sale_id]);

        if (isset($_POST['member_id']) && !empty($_POST['member_id'])) {
            $updateMember = $pdo->prepare("UPDATE sale SET member_id = ? WHERE sale_id = ?");
            $updateMember->execute([$_POST['member_id'], $sale_id]);
        }

        if (isset($_POST['personnel_id']) && !empty($_POST['personnel_id'])) {
            $updatePersonnel = $pdo->prepare("UPDATE sale SET personnel_id = ? WHERE sale_id = ?");
            $updatePersonnel->execute([$_POST['personnel_id'], $sale_id]);
        }

        // Remove from active sales
        $_SESSION['active_sales'] = array_diff($_SESSION['active_sales'], [$sale_id]);
        $_SESSION['current_sale_id'] = !empty($_SESSION['active_sales']) ? reset($_SESSION['active_sales']) : null;

        $pdo->commit();
        $_SESSION['success'] = "ยืนยันการขายสำเร็จ";
        header('Location: ?page=sale_list');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error confirming sale: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header("Location: ?page=sale_insert&sale_id=$sale_id");
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

    $total_amount = 0;
    foreach ($sale_products as $product) {
        $total_amount += $product['sale_product_qty'] * $product['sale_product_price'];
    }
} catch (PDOException $e) {
    error_log("Database query error: " . $e->getMessage());
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}

// Fetch members, personnel, and sale status
$members = $pdo->query("SELECT member_id, member_name FROM member ORDER BY member_name");
$personnels = $pdo->query("SELECT personnel_id, personnel_name FROM personnel ORDER BY personnel_name");
$sale_status_result = $pdo->query("SELECT sale_status_id, sale_status_name FROM sale_status");

// Validate active sales against database
$valid_active_sales = [];
if (!empty($_SESSION['active_sales'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['active_sales']), '?'));
    $stmt = $pdo->prepare("SELECT sale_id FROM sale WHERE sale_id IN ($placeholders) AND sale_status_id = 1");
    $stmt->execute($_SESSION['active_sales']);
    $valid_sales = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $valid_active_sales = array_intersect($_SESSION['active_sales'], $valid_sales);
    $_SESSION['active_sales'] = $valid_active_sales;
    if (!in_array($_SESSION['current_sale_id'], $valid_active_sales)) {
        $_SESSION['current_sale_id'] = !empty($valid_active_sales) ? reset($valid_active_sales) : null;
        $sale_id = $_SESSION['current_sale_id'];
    }
}

$sale_status_id = $sale_data['sale_status_id'] ?? 1;
$sale_status_name = $sale_data['sale_status_name'] ?? 'รอดำเนินการ';
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
    <style>
        #edit-product-modal {
            z-index: 1000;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <header class="bg-gradient-to-r from-blue-600 to-indigo-800 text-white shadow-lg">
        <div class="container mx-auto py-4 px-6 flex justify-between items-center">
            <h1 class="text-2xl font-bold">POS System</h1>
            <div class="flex items-center space-x-4">
                <span class="text-sm">พนักงาน:
                    <?php echo htmlspecialchars($sale_data['personnel_name'] ?? 'N/A'); ?></span>
                <span class="text-sm">วันที่: <?php echo $displayDateTime; ?></span>
            </div>
        </div>
    </header>

    <div class="container mx-auto p-6">
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
                <form action="" method="POST" id="form_add_product" class="space-y-4">
                    <div class="flex space-x-4 mb-4">
                        <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                        <input type="text" name="barcode" id="barcode_input"
                            class="p-3 w-full h-8 border border-gray-300 rounded-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                            placeholder="ป้อนรหัสสินค้า" autofocus>
                        <input type="number" name="qty" value="1" min="1"
                            class="p-2 w-24 border border-gray-300 rounded-sm">
                        <button type="submit"
                            class="p-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                </form>

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
                                    <th class="py-3 px-4 text-center font-medium text-sm">จำนวนขาย</th>
                                    <th class="py-3 px-4 text-right font-medium text-sm">ราคาขาย/หน่วย</th>
                                    <th class="py-3 px-4 text-right font-medium text-sm">ยอดรวม</th>
                                    <th class="py-3 px-4 text-right font-medium text-sm">คลัง</th>
                                    <th class="py-3 px-4 text-right font-medium text-sm">จำนวนสินค้าคงเหลือ</th>
                                    <th class="py-3 px-4 text-center font-medium text-sm">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                        <td class="py-3 px4 text-center text-sm">
                                            <?php
                                            $stmt = $pdo->prepare("SELECT warehouse_name FROM warehouse WHERE warehouse_id = ?");
                                            $stmt->execute([$product['warehouse_id']]);
                                            echo htmlspecialchars($stmt->fetchColumn() ?: '-');
                                            ?>
                                        </td>
                                        <td class="py-3 px-4 text-center text-sm">
                                            <?php echo htmlspecialchars($product['lot_product_remain'] ?: '-'); ?></td>
                                        <td class="py-3 px-4 text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button
                                                    class="edit-product p-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors"
                                                    data-id="<?php echo htmlspecialchars($product['sale_product_id']); ?>"
                                                    data-barcode="<?php echo htmlspecialchars($product['product_id']); ?>"
                                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                    data-qty="<?php echo htmlspecialchars($product['sale_product_qty']); ?>"
                                                    data-price="<?php echo htmlspecialchars($product['sale_product_price']); ?>"
                                                    data-capital="<?php echo htmlspecialchars($product['sale_product_capital']); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </button>
                                                <form method="POST" action="" onsubmit="return confirmDelete();">
                                                    <input type="hidden" name="sale_id"
                                                        value="<?php echo htmlspecialchars($sale_id); ?>">
                                                    <input type="hidden" name="barcode"
                                                        value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                                    <input type="hidden" name="qty" value="1">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit"
                                                        class="p-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
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
                            <p class="text-sm">กรุณาเพิ่มสินค้า</p>
                        </div>
                    <?php endif; ?>
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
                    <form method="POST" action="" id="confirm-sale-form">
                        <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                        <input type="hidden" name="action" value="confirm_sale">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">บิลที่ใช้งาน:</label>
                                <select id="switch_sale" name="switch_sale_id"
                                    class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <?php foreach ($valid_active_sales as $active_sale_id): ?>
                                        <option value="<?php echo $active_sale_id; ?>" <?php echo $active_sale_id == $sale_id ? 'selected' : ''; ?>>
                                            บิล #<?php echo $active_sale_id; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($valid_active_sales)): ?>
                                        <option value="" disabled>ไม่มีบิลที่ใช้งาน</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">วันที่ทำการขาย:</label>
                                <input name="sale_date" value="<?php echo $displayDateTime; ?>" readonly
                                    class="p-2 bg-gray-50 border border-gray-300 rounded text-gray-500 text-sm">
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">สถานะการขาย:</label>
                                <select name="sale_status_id"
                                    class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    disabled>
                                    <?php while ($sale_status = $sale_status_result->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $sale_status['sale_status_id']; ?>" <?php echo ($sale_status_id == $sale_status['sale_status_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sale_status['sale_status_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">สมาชิก:</label>
                                <select name="member_id"
                                    class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">ไม่เลือกสมาชิก</option>
                                    <?php while ($member = $members->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $member['member_id']; ?>" <?php echo ($sale_data['member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['member_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">พนักงานขาย:</label>
                                <select name="personnel_id"
                                    class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">ไม่เลือกพนักงาน</option>
                                    <?php while ($personnel = $personnels->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $personnel['personnel_id']; ?>" <?php echo ($sale_data['personnel_id'] == $personnel['personnel_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="space-y-3">
                                <button id="new_sale" type="button"
                                    class="w-full py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    เปิดบิลใหม่
                                </button>
                                <button id="cancel_sale" type="button"
                                    class="w-full py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    ยกเลิกบิล
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

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
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                            คำนวณเงินทอน
                        </button>
                    </div>
                </div>

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
                    <button type="submit" form="confirm-sale-form"
                        class="w-full py-3 bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition-colors flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        ยืนยันการขาย
                    </button>
                </div>
            </div>
        </div>

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
                <form method="POST" action="">
                    <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit-barcode" name="barcode">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสสินค้า</label>
                            <input type="text" id="edit-barcode-display"
                                class="w-full p-2 border border-gray-300 rounded" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสินค้า</label>
                            <input type="text" id="edit-product-name" class="w-full p-2 border border-gray-300 rounded"
                                readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                            <input type="number" id="edit-qty" name="qty"
                                class="w-full p-2 border border-gray-300 rounded" min="1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ราคาขาย</label>
                            <input type="number" id="edit-price" name="price"
                                class="w-full p-2 border border-gray-300 rounded" step="0.01">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ต้นทุน</label>
                            <input type="number" id="edit-capital" name="capital"
                                class="w-full p-2 border border-gray-300 rounded" step="0.01">
                        </div>
                    </div>
                    <div class="mt-6 flex space-x-3">
                        <button type="submit"
                            class="flex-1 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">บันทึกการแก้ไข</button>
                        <button type="button" id="cancel-edit"
                            class="flex-1 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById("barcode_input").addEventListener("keypress", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                document.getElementById("form_add_product").submit();
            }
        });

        $(document).ready(function () {
            const Swal = window.Swal;
            const totalAmount = <?php echo $total_amount; ?>;
            const currentSaleId = <?php echo json_encode($sale_id); ?>;

            // Toggle delete mode
            $('#delete_mode').click(function () {
                $(this).toggleClass('bg-red-500 bg-red-700');
                if ($(this).hasClass('bg-red-700')) {
                    $(this).html('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg> โหมดลบ (เปิดใช้งาน)');
                } else {
                    $(this).html('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg> โหมดลบ');
                }
            });

            // Confirm delete
            window.confirmDelete = function () {
                return confirm('คุณต้องการลบสินค้านี้ใช่หรือไม่?');
            };

            // Open edit modal
            $('.edit-product').click(function () {
                $('#edit-barcode').val($(this).data('barcode'));
                $('#edit-barcode-display').val($(this).data('barcode'));
                $('#edit-product-name').val($(this).data('name'));
                $('#edit-qty').val($(this).data('qty'));
                $('#edit-price').val($(this).data('price'));
                $('#edit-capital').val($(this).data('capital'));
                $('#edit-product-modal').removeClass('hidden');
            });

            // Close modal
            $('#close-modal, #cancel-edit').click(function () {
                $('#edit-product-modal').addClass('hidden');
            });

            // Calculate change
            $('#calculate_change').click(function () {
                let paidAmount = parseFloat($('#paid_amount').val()) || 0;
                if (paidAmount < totalAmount) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'เงินไม่เพียงพอ',
                        text: 'เงินที่ชำระไม่เพียงพอกับยอดรวม',
                        confirmButtonText: 'ตกลง'
                    });
                    $('#change_amount').val('');
                    $('#print_receipt').prop('disabled', true);
                    return;
                }
                let change = paidAmount - totalAmount;
                $('#change_amount').val(change.toFixed(2));
                $('#print_receipt').prop('disabled', false);
            });

            // Calculate change on Enter
            $('#paid_amount').on('keypress', function (e) {
                if (e.which == 13) {
                    $('#calculate_change').click();
                }
            });

            // Update switch sale dropdown
            function updateSwitchSaleDropdown(newSaleId, saleIds) {
                let $select = $('#switch_sale');
                $select.empty();
                if (saleIds.length === 0) {
                    $select.append('<option value="" disabled>ไม่มีบิลที่ใช้งาน</option>');
                } else {
                    saleIds.forEach(function (saleId) {
                        let selected = saleId == newSaleId ? 'selected' : '';
                        $select.append(`<option value="${saleId}" ${selected}>บิล #${saleId}</option>`);
                    });
                }
            }

            // New sale
            $('#new_sale').click(function () {
                Swal.fire({
                    title: 'เปิดบิลใหม่',
                    text: 'คุณต้องการเปิดบิลใหม่หรือไม่?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'เปิดบิล',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'page/sale/sale_insert.php',
                            type: 'POST',
                            data: { action: 'new_sale' },
                            dataType: 'json',
                            success: function (response) {
                                if (response.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'เปิดบิลสำเร็จ',
                                        text: response.message,
                                        timer: 1500
                                    });
                                    // Update dropdown
                                    let saleIds = <?php echo json_encode($valid_active_sales); ?>;
                                    saleIds.push(response.sale_id);
                                    updateSwitchSaleDropdown(response.sale_id, saleIds);
                                    // Redirect to new sale
                                    window.location = '?page=sale_insert&sale_id=' + response.sale_id;
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ข้อผิดพลาด',
                                        text: response.message
                                    });
                                }
                            },
                            error: function (xhr, status, error) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: 'เกิดข้อผิดพลาดในการสื่อสารกับเซิร์ฟเวอร์: ' + error
                                });
                            }
                        });
                    }
                });
            });

            // Cancel sale
            $('#cancel_sale').click(function () {
                Swal.fire({
                    title: 'ยกเลิกบิล',
                    text: 'คุณต้องการยกเลิกบิล #' + currentSaleId + ' หรือไม่? การดำเนินการนี้จะลบข้อมูลทั้งหมดของบิลนี้',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ยกเลิกบิล',
                    cancelButtonText: 'ไม่ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'page/sale/sale_insert.php',
                            type: 'POST',
                            data: {
                                action: 'cancel_sale',
                                sale_id: currentSaleId
                            },
                            dataType: 'json',
                            success: function (response) {
                                if (response.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'ยกเลิกบิลสำเร็จ',
                                        text: response.message,
                                        timer: 1500
                                    });
                                    // Update dropdown
                                    let saleIds = <?php echo json_encode($valid_active_sales); ?>;
                                    saleIds = saleIds.filter(id => id != currentSaleId);
                                    updateSwitchSaleDropdown(saleIds.length > 0 ? saleIds[0] : null, saleIds);
                                    // Redirect
                                    window.location = response.redirect;
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ข้อผิดพลาด',
                                        text: response.message
                                    });
                                }
                            },
                            error: function (xhr, status, error) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: 'เกิดข้อผิดพลาดในการสื่อสารกับเซิร์ฟเวอร์: ' + error
                                });
                            }
                        });
                    }
                });
            });

            // Switch sale
            $('#switch_sale').change(function () {
                let newSaleId = $(this).val();
                if (!newSaleId) return;
                $.ajax({
                    url: 'page/sale/sale_insert.php',
                    type: 'POST',
                    data: {
                        action: 'switch_sale',
                        sale_id: newSaleId
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'เปลี่ยนบิลสำเร็จ',
                                text: response.message,
                                timer: 1000
                            });
                            window.location = '?page=sale_insert&sale_id=' + response.sale_id;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'ข้อผิดพลาด',
                                text: response.message
                            });
                            $('#switch_sale').val(currentSaleId); // Revert selection
                        }
                    },
                    error: function (xhr, status, error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'ข้อผิดพลาด',
                            text: 'เกิดข้อผิดพลาดในการสื่อสารกับเซิร์ฟเวอร์: ' + error
                        });
                        $('#switch_sale').val(currentSaleId); // Revert selection
                    }
                });
            });

            // Print receipt
            $('#print_receipt').click(function () {
                let paidAmount = parseFloat($('#paid_amount').val()) || 0;
                let changeAmount = parseFloat($('#change_amount').val()) || 0;
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
                                <span>วันที่: <?php echo htmlspecialchars($sale_data['sale_date'] ?? $displayDateTime); ?></span>
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
                                    <?php foreach ($sale_products as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td class="text-right"><?php echo htmlspecialchars($product['sale_product_qty']); ?></td>
                                            <td class="text-right"><?php echo htmlspecialchars(number_format($product['sale_product_price'], 2)); ?></td>
                                            <td class="text-right"><?php echo htmlspecialchars(number_format($product['sale_product_qty'] * $product['sale_product_price'], 2)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
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
                                    <span>${paidAmount.toFixed(2)} บาท</span>
                                </div>
                                <div class="totals">
                                    <span>เงินทอน:</span>
                                    <span>${changeAmount.toFixed(2)} บาท</span>
                                </div>
                            </div>
                            <div class="footer">
                                <div>ขอบคุณที่ใช้บริการ</div>
                                <div>THANK YOU FOR YOUR BUSINESS</div>
                            </div>
                        </div>
                    </body>
                    </html>
                `;
                receiptWindow.document.write(html);
                receiptWindow.document.close();
            });

            console.log('JavaScript loaded successfully');
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>