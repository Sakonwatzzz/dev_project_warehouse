<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../config/database_server.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

//ใช้ $base_url ที่กำหนดไว้
// $base_url = '/page/sale';

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

// Handle new sale creation via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_sale') {
    header('Content-Type: application/json');
    ob_clean();
    try {
        $pdo->beginTransaction();
        $insertSale = $pdo->prepare("INSERT INTO sale (sale_date, sale_status_id, member_id, personnel_id) VALUES (?, 1, ?, ?)");
        $insertSale->execute([$currentDateTime, null, null]);
        $new_sale_id = $pdo->lastInsertId();
        $_SESSION['active_sales'][] = $new_sale_id;
        $_SESSION['current_sale_id'] = $new_sale_id;
        $pdo->commit();
        echo json_encode(['status' => 'success', 'sale_id' => $new_sale_id, 'message' => 'บิลใหม่ #' . $new_sale_id . ' ถูกสร้างเรียบร้อย', 'redirect' => "?page=sale_add&sale_id=$new_sale_id"]);
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
    ob_clean();
    $cancel_sale_id = $_POST['sale_id'] ?? null;
    if (!$cancel_sale_id || !in_array($cancel_sale_id, $_SESSION['active_sales'])) {
        echo json_encode(['status' => 'error', 'message' => 'บิลไม่ถูกต้องหรือไม่พบ']);
        exit();
    }
    error_log("Attempting to cancel sale with sale_id: " . $cancel_sale_id);
    try {
        $pdo->beginTransaction();

        // Restore stock in lot_product
        $stmt = $pdo->prepare("SELECT product_id, sale_product_qty FROM sale_product WHERE sale_id = ?");
        $stmt->execute([$cancel_sale_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $product) {
            if (!isset($product['product_id']) || !isset($product['sale_product_qty']) || $product['sale_product_qty'] <= 0) {
                error_log("Invalid product data for sale_id $cancel_sale_id: " . json_encode($product));
                throw new Exception("ข้อมูลสินค้าไม่ถูกต้องสำหรับบิล #$cancel_sale_id");
            }
            adjustLotRemain($pdo, $product['product_id'], $product['sale_product_qty'], true);
        }

        // Delete sale_product records
        $deleteProductStmt = $pdo->prepare("DELETE FROM sale_product WHERE sale_id = ?");
        $deleteProductStmt->execute([$cancel_sale_id]);

        // Delete sale record
        if (empty($cancel_sale_id)) {
            throw new Exception("ไม่สามารถยกเลิกบิลได้: sale_id ไม่ถูกต้อง");
        }
        $deleteSaleStmt = $pdo->prepare("DELETE FROM sale WHERE sale_id = ?");
        $deleteSaleStmt->execute([$cancel_sale_id]);

        // Update session
        $_SESSION['active_sales'] = array_diff($_SESSION['active_sales'], [$cancel_sale_id]);
        if ($_SESSION['current_sale_id'] == $cancel_sale_id) {
            $_SESSION['current_sale_id'] = !empty($_SESSION['active_sales']) ? reset($_SESSION['active_sales']) : null;
        }

        $pdo->commit();
        $redirect_url = $_SESSION['current_sale_id'] ? "?page=sale_add&sale_id={$_SESSION['current_sale_id']}" : "?page=sale_add";
        echo json_encode(['status' => 'success', 'message' => 'บิล #' . $cancel_sale_id . ' ถูกลบเรียบร้อย', 'redirect' => $redirect_url]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error cancelling sale $cancel_sale_id: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกบิลได้: ' . $e->getMessage()]);
    }
    exit();
}

// Handle switch sale via Ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'switch_sale') {
    header('Content-Type: application/json');
    ob_clean();
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
// Handle product add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {

    if (!isAjaxRequest()) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'Access denied: AJAX request required']);
        exit();
    }
    header('Content-Type: application/json');
    ob_clean();
    try {
        $barcode = trim($_POST['barcode']);
        $qty = is_numeric($_POST['qty'] ?? null) ? (int) $_POST['qty'] : 1;
        $price = is_numeric($_POST['price'] ?? null) ? (float) $_POST['price'] : 0;
        $price_type = $_POST['price_type'] ?? 'price4';

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
        $unitCapital = $prod['product_capital'];

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
            $newCapital = $prod['product_capital'];

            $stmt = $pdo->prepare("
                UPDATE sale_product 
                SET sale_product_qty = ?, sale_product_price = ?, sale_product_capital = ?
                WHERE sale_id = ? AND product_id = ?
            ");
            $stmt->execute([$newQty, $newPrice, $newCapital, $sale_id, $barcode]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO sale_product 
                (sale_id, product_id, sale_product_qty, sale_product_price, sale_product_capital)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$sale_id, $barcode, $qty, $unitPrice, $unitCapital]);
        }

        // ตัดสต็อก
        try {
            $lot_ids = adjustLotRemain($pdo, $barcode, $qty, false);
        } catch (Exception $e) {
            throw new Exception("ไม่สามารถตัดสต็อกได้: " . $e->getMessage());
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มสินค้าสำเร็จ']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error adding product: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle product delete 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'Access denied: AJAX request required']);
        exit();
    }
    //ถ้าไม่ใช่ AJAX, ส่ง 403 Forbidden และ JSON error
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();
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
            echo json_encode(['status' => 'success', 'message' => 'ลบสินค้าสำเร็จ']);
        } else {
            throw new Exception("ไม่พบสินค้าในรายการ");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error deleting product: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    header('Content-Type: application/json');
    ob_clean();
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
            echo json_encode(['status' => 'success', 'message' => 'แก้ไขสินค้าสำเร็จ']);
        } else {
            throw new Exception("ไม่พบสินค้าในรายการ");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating product: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle sale confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_sale') {
    header('Content-Type: application/json');
    ob_clean();
    try {
        $pdo->beginTransaction();

        // ตรวจสอบ sale_id
        $sale_id = $_POST['sale_id'] ?? null;
        error_log("Confirm sale: sale_id = " . ($sale_id ?? 'null')); // บันทึก sale_id
        if (empty($sale_id)) {
            throw new Exception("ไม่พบ sale_id ในคำขอ");
        }

        // ตรวจสอบว่าบิลมีอยู่ในฐานข้อมูลและอยู่ในสถานะรอดำเนินการ
        $check_sale = $pdo->prepare("SELECT sale_status_id FROM sale WHERE sale_id = ?");
        $check_sale->execute([$sale_id]);
        $sale = $check_sale->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            throw new Exception("ไม่พบข้อมูลบิล #$sale_id ในระบบ");
        }
        if ($sale['sale_status_id'] != 1) {
            throw new Exception("บิล #$sale_id ไม่สามารถยืนยันได้ เนื่องจากไม่ได้อยู่ในสถานะรอดำเนินการ");
        }

        // ตรวจสอบว่ามีสินค้าในบิล
        $check_products = $pdo->prepare("SELECT COUNT(*) FROM sale_product WHERE sale_id = ?");
        $check_products->execute([$sale_id]);
        if ($check_products->fetchColumn() == 0) {
            throw new Exception("ไม่พบสินค้าในรายการขาย กรุณาเพิ่มสินค้าก่อนยืนยันการขาย");
        }

        // ตรวจสอบความสมบูรณ์ของข้อมูลสินค้า
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

        // อัปเดตสถานะบิลเป็นยืนยันแล้ว (sale_status_id = 2)
        $updateSale = $pdo->prepare("UPDATE sale SET sale_status_id = 2 WHERE sale_id = ?");
        $updateSale->execute([$sale_id]);

        // อัปเดต member_id หากระบุ
        if (isset($_POST['member_id']) && !empty($_POST['member_id'])) {
            $updateMember = $pdo->prepare("UPDATE sale SET member_id = ? WHERE sale_id = ?");
            $updateMember->execute([$_POST['member_id'], $sale_id]);
        }

        // อัปเดต personnel_id หากระบุ
        if (isset($_POST['personnel_id']) && !empty($_POST['personnel_id'])) {
            $updatePersonnel = $pdo->prepare("UPDATE sale SET personnel_id = ? WHERE sale_id = ?");
            $updatePersonnel->execute([$_POST['personnel_id'], $sale_id]);
        }

        // อัปเดต sale_deposit หากระบุ
        // if (isset($_POST['sale_deposit']) && is_numeric($_POST['sale_deposit'])) {
        //     $updateDeposit = $pdo->prepare("UPDATE sale SET sale_deposit = ? WHERE sale_id = ?");
        //     $updateDeposit->execute([(float)$_POST['sale_deposit'], $sale_id]);
        // }

        // Remove from active sales
        $_SESSION['active_sales'] = array_diff($_SESSION['active_sales'], [$sale_id]);
        $_SESSION['current_sale_id'] = !empty($_SESSION['active_sales']) ? reset($_SESSION['active_sales']) : null;

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'ยืนยันการขายสำเร็จ', 'redirect' => '?page=sale_add']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error confirming sale: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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


    // sp.sale_product_referenc ยังไม่ต้องการคอลัมน์นี้
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
    <title>ระบบบริหารจัดการการขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            background-color: #3b82f6;
            color: white;
        }

        .tab-inactive {
            background-color: #e5e7eb;
            color: black;
        }

        #billTabs::-webkit-scrollbar {
            height: 8px;
        }

        #billTabs::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        #billTabs::-webkit-scrollbar-thumb {
            background: #3b82f6;
            border-radius: 4px;
        }

        #billTabs::-webkit-scrollbar-thumb:hover {
            background: #2563eb;
        }
    </style>

</head>

<body class="bg-gray-100 font-sans">
    <div class="main-body">
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
            <div class="flex items-center space-x-2 mb-6">
                <button id="new_sale"
                    class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    เปิดบิลใหม่
                </button>
                <div id="billTabs" class="flex space-x-2 overflow-x-auto flex-nowrap max-w-full">
                    <?php foreach ($valid_active_sales as $active_sale_id): ?>
                        <div id="tab_<?php echo $active_sale_id; ?>"
                            class="px-4 py-2 rounded <?php echo $active_sale_id == $sale_id ? 'tab-active' : 'tab-inactive'; ?> cursor-pointer whitespace-nowrap"
                            onclick="switchBill(<?php echo $active_sale_id; ?>)">
                            บิล #<?php echo $active_sale_id; ?> <span
                                onclick="cancelSale(<?php echo $active_sale_id; ?>)">×</span>
                        </div>
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
                                    placeholder="ป้อนหรือสแกนรหัสสินค้า" autocomplete="off" autofocus>
                                <div id="barcode_suggestions"
                                    class="absolute z-10 w-full bg-white border border-gray-200 rounded-b-lg shadow-lg hidden">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                                <input type="number" name="qty" value="1" min="1"
                                    class="p-2 w-24 border border-gray-300 rounded-sm">
                            </div>
                            <div>
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
                    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                        <h2 class="text-lg font-semibold mb-3 text-gray-800 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            การชำระเงิน
                        </h2>
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                            <div class="flex justify-between text-gray-800">
                                <span class="font-medium text-sm">ยอดรวมสินค้า:</span>
                                <span class="total-amount text-sm"><?php echo number_format($total_amount, 2); ?>
                                    บาท</span>
                            </div>
                            <div
                                class="border-t border-gray-200 mt-2 pt-2 flex justify-between font-semibold text-base">
                                <span>ยอดรวมสุทธิ:</span>
                                <span class="text-blue-700 total-amount"><?php echo number_format($total_amount, 2); ?>
                                    บาท</span>
                            </div>
                        </div>
                        <div class="flex flex-row space-x-4 items-end">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">เงินที่ชำระ (บาท):</label>
                                <input type="number" id="paid_amount"
                                    class="p-2 w-full text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    step="0.01" placeholder="ป้อนจำนวนเงิน">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">เงินทอน (บาท):</label>
                                <input type="text" id="change_amount"
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
                                                    <button
                                                        class="delete-product p-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                                                        data-barcode="<?php echo htmlspecialchars($product['product_id']); ?>"
                                                        data-qty="1">
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
                        <form method="POST" action="" id="confirm-sale-form" novalidate onsubmit="return false;">
                            <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                            <input type="hidden" name="action" value="confirm_sale">
                            <div class="space-y-4">
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
                                <!-- <div class="grid grid-cols-1 gap-2">
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
                                </div> -->
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
                                <div class="grid grid-cols-1 gap-2" hidden>
                                    <label class="text-base font-medium text-gray-700">สมาชิก:</label>
                                    <input type="text" id="member_search"
                                        class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="ค้นหาสมาชิก">
                                    <input type="hidden" name="member_id" id="member_id">
                                    <div id="member_suggestions"
                                        class="absolute z-10 w-full bg-white border border-gray-200 rounded-b-lg shadow-lg hidden">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 gap-2" hidden>
                                    <label class="text-base font-medium text-gray-700" hidden>พนักงานขาย:</label>
                                    <select name="personnel_id"
                                        class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        hidden>
                                        <option value="" hidden>ไม่เลือกพนักงาน</option>
                                        <?php while ($personnel = $personnels->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $personnel['personnel_id']; ?>" <?php echo ($sale_data['personnel_id'] == $personnel['personnel_id']) ? 'selected' : ''; ?> hidden>
                                                <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <button id="print_receipt"
                                    class="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center"
                                    disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    พิมพ์ใบเสร็จ
                                </button>
                                <button type="submit" form="confirm-sale-form" id="confirm-sale-btn"
                                    class="w-full py-3 bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition-colors flex items-center justify-center">
                                    <span class="loading-spinner hidden">กำลังประมวลผล...</span>
                                    <span class="btn-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                        ยืนยันการขาย
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
                                    class="w-full p-2 border border-gray-300 rounded modal-active" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสินค้า</label>
                                <input type="text" id="edit-product-name"
                                    class="w-full p-2 border border-gray-300 rounded modal-active" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                                <input type="number" id="edit-qty" name="qty"
                                    class="w-full p-2 border border-gray-300 rounded modal-active" min="1">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ราคาขาย</label>
                                <input type="number" id="edit-price" name="price"
                                    class="w-full p-2 border border-gray-300 rounded modal-active" step="0.01">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ต้นทุน</label>
                                <input type="number" id="edit-capital" name="capital"
                                    class="w-full p-2 border border-gray-300 rounded modal-active" step="0.01">
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
                $(document).ready(function () {

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

                    // ตัวแปรควบคุมการประมวลผลเพื่อป้องกันการ submit ซ้ำ
                    let isProcessing = false;

                    $('#form_add_product input').on('keypress', function (e) {
                        if (e.which === 13 && !isProcessing) {
                            e.preventDefault();
                            e.stopPropagation(); // หยุด event bubbling
                            const barcode = $('#barcode_input').val();
                            const qty = parseInt($('input[name="qty"]').val()) || 0;
                            if (!barcode) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: 'กรุณาป้อนรหัสสินค้า'
                                });
                                return;
                            }
                            if (qty <= 0) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: 'จำนวนต้องมากกว่า 0'
                                });
                                return;
                            }
                            console.log('Submitting #form_add_product:', $('#form_add_product').serialize());
                            $.ajax({
                                url: 'page/sale/sale_add.php',
                                method: 'POST',
                                data: $('#form_add_product').serialize(),
                                dataType: 'json',
                                beforeSend: function () {
                                    isProcessing = true;
                                    $('#barcode_input').prop('disabled', true);
                                    $('input[name="qty"]').prop('disabled', true);
                                },
                                success: function (response) {
                                    if (response.status === 'success') {
                                        Toast.fire({
                                            icon: 'success',
                                            title: response.message
                                        });
                                        updateProductTable();
                                        $('#form_add_product')[0].reset();
                                        $('#product_info').addClass('hidden').empty();
                                    } else {
                                        Swal.fire({
                                            icon: 'warning',
                                            title: '',
                                            text: response.message,
                                            didOpen: () => {
                                                $('.main-body').removeAttr('aria-hidden');
                                            }
                                        });
                                    }
                                },
                                complete: function () {
                                    isProcessing = false;
                                    $('#barcode_input').prop('disabled', false);
                                    $('input[name="qty"]').prop('disabled', false);
                                    $('.main-body').removeAttr('aria-hidden');
                                    if (!$('#edit-product-modal').is(':visible')) {
                                        $('#barcode_input').focus();
                                    }
                                },
                                error: function (xhr) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ข้อผิดพลาด',
                                        text: xhr.responseText || 'ไม่สามารถเพิ่มสินค้าได้',
                                        didOpen: () => {
                                            $('.main-body').removeAttr('aria-hidden');
                                        }
                                    });
                                    isProcessing = false;
                                }
                            });
                        }
                    });

                    // ป้องกันการ submit ไม่คาดคิดของ #form_add_product
                    $('#form_add_product').off('submit').on('submit', function (e) {
                        e.preventDefault();
                        console.log('Unexpected submit event on #form_add_product');
                    });
                    // จัดการ submit ของ #confirm-sale-form เฉพาะเมื่อกดปุ่ม
                    $('#confirm-sale-btn').on('click', function (e) {
                        e.preventDefault();
                        console.log('Submitting #confirm-sale-form:', $('#confirm-sale-form').serialize());
                        $('#confirm-sale-btn .btn-text').addClass('hidden');
                        $('#confirm-sale-btn .loading-spinner').removeClass('hidden');
                        $.ajax({
                            url: 'page/sale/sale_add.php',
                            method: 'POST',
                            data: $('#confirm-sale-form').serialize(),
                            dataType: 'json',
                            success: function (response) {
                                $('#confirm-sale-btn .btn-text').removeClass('hidden');
                                $('#confirm-sale-btn .loading-spinner').addClass('hidden');
                                if (response.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'สำเร็จ',
                                        text: response.message,
                                        timer: 1500,
                                        didOpen: () => {
                                            $('.main-body').removeAttr('aria-hidden');
                                        }
                                    }).then(() => {
                                        window.location.href = response.redirect;
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ข้อผิดพลาด',
                                        text: response.message,
                                        didOpen: () => {
                                            $('.main-body').removeAttr('aria-hidden');
                                        }
                                    });
                                }
                            },
                            error: function (xhr) {
                                $('#confirm-sale-btn .btn-text').removeClass('hidden');
                                $('#confirm-sale-btn .loading-spinner').addClass('hidden');
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: xhr.responseText || 'ไม่สามารถยืนยันการขายได้',
                                    didOpen: () => {
                                        $('.main-body').removeAttr('aria-hidden');
                                    }
                                });
                            }
                        });
                    });
                    // ป้องกัน Enter key ใน #confirm-sale-form
                    $('#confirm-sale-form input').on('keypress', function (e) {
                        if (e.which === 13) {
                            e.preventDefault();
                            console.log('Blocked Enter key in #confirm-sale-form');
                        }
                    });

                    // Autocomplete สำหรับค้นหาสินค้า
                    $('#barcode_input').on('input', function () {
                        const query = $(this).val();
                        if (query.length < 2) {
                            $('#barcode_suggestions').addClass('hidden').empty();
                            $('#product_info').addClass('hidden').empty();
                            return;
                        }
                        $.ajax({
                            url: 'page/sale/ajax/ajax_search_product.php',
                            method: 'GET',
                            data: {
                                query: query
                            },
                            dataType: 'json',
                            success: function (response) {
                                const products = response;
                                const suggestions = $('#barcode_suggestions').removeClass('hidden').empty();
                                if (products.length === 0) {
                                    suggestions.append('<div class="p-2 text-gray-500">ไม่พบสินค้า</div>');
                                    return;
                                }
                                products.forEach(product => {
                                    suggestions.append(`
                                <div class="p-2 hover:bg-gray-100 cursor-pointer" data-barcode="${product.product_id}">
                                    ${product.product_id} - ${product.product_name} (สต็อก: ${product.total_stock})
                                </div>
                            `);
                                });
                                suggestions.find('div').on('click', function () {
                                    const barcode = $(this).data('barcode');
                                    $('#barcode_input').val(barcode);
                                    suggestions.addClass('hidden').empty();
                                    fetchProductInfo(barcode);
                                });
                            },
                            error: function (xhr) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: xhr.responseText || 'ไม่สามารถค้นหาสินค้าได้',
                                    didOpen: () => {
                                        $('.main-body').removeAttr('aria-hidden');
                                    }
                                });
                            }
                        });
                    });

                    // แสดงข้อมูลสินค้า
                    function fetchProductInfo(barcode) {
                        $.ajax({
                            url: 'page/sale/ajax/ajax_get_product.php',
                            method: 'GET',
                            data: {
                                barcode: barcode
                            },
                            dataType: 'json',
                            success: function (response) {
                                const product = response;
                                const info = $('#product_info').removeClass('hidden');
                                if (!product) {
                                    info.html('<span class="text-red-500">ไม่พบสินค้าหรือสต็อกไม่เพียงพอ</span>');
                                    return;
                                }
                                info.html(`
                            <p><strong>ชื่อสินค้า:</strong> ${product.product_name}</p>
                            <p><strong>ราคาขาย:</strong> ${product.product_price4} บาท</p>
                            <p><strong>สต็อกคงเหลือ:</strong> ${product.total_stock} ชิ้น</p>
                        `);
                            },
                            error: function (xhr) {
                                $('#product_info').addClass('hidden').empty();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: xhr.responseText || 'ไม่สามารถดึงข้อมูลสินค้าได้',
                                    didOpen: () => {
                                        $('.main-body').removeAttr('aria-hidden');
                                    }
                                });
                            }
                        });
                    }
                    // อัปเดตตารางสินค้าแบบเรียลไทม์
                    function updateProductTable() {
                        $.ajax({
                            url: 'page/sale/ajax/ajax_get_sale_products.php',
                            method: 'GET',
                            data: {
                                sale_id: '<?php echo htmlspecialchars($sale_id); ?>'
                            },
                            dataType: 'json',
                            success: function (response) {
                                if (response.status === 'success') {
                                    $('#product_table_body').html(response.html);
                                    $('.total-amount').text(response.total_amount + ' บาท');
                                    const hasProducts = !response.html.includes('ไม่พบสินค้า');
                                    $('#confirm-sale-btn')
                                        .prop('disabled', !hasProducts)
                                        .toggleClass('opacity-50 cursor-not-allowed', !hasProducts);
                                    $('#calculate_change').click();
                                    bindEditProductEvents();
                                    $('.main-body').removeAttr('aria-hidden');
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ข้อผิดพลาด',
                                        text: response.message,
                                        didOpen: () => {
                                            $('.main-body').removeAttr('aria-hidden');
                                        }
                                    });
                                }
                            },
                            error: function (xhr) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: xhr.responseText || 'ไม่สามารถดึงข้อมูลตารางได้',
                                    didOpen: () => {
                                        $('.main-body').removeAttr('aria-hidden');
                                    }
                                });
                            }
                        });
                    }
                    // Autocomplete สำหรับค้นหาสมาชิก
                    $('#member_search').on('input', function () {
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
                            success: function (response) {
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
                                suggestions.find('div').on('click', function () {
                                    const memberId = $(this).data('id');
                                    const memberName = $(this).text();
                                    $('#member_search').val(memberName);
                                    $('#member_id').val(memberId);
                                    suggestions.addClass('hidden').empty();
                                });
                            },
                            error: function (xhr) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: xhr.responseText || 'ไม่สามารถค้นหาสมาชิกได้'
                                });
                            }
                        });
                    });

                    // คำนวณเงินทอน
                    $('#calculate_change').on('click', function () {
                        const paid = parseFloat($('#paid_amount').val()) || 0;
                        const total = parseFloat(<?php echo $total_amount; ?>) || 0;
                        const change = paid - total;
                        $('#change_amount').val(change >= 0 ? change.toFixed(2) : 'เงินไม่พอ');
                        $('#print_receipt').prop('disabled', change < 0);
                    });

                    // อัปเดตเงินทอนเมื่อกรอกเงินที่ชำระ
                    $('#paid_amount').on('input', function () {
                        $('#calculate_change').click();
                    });

                    // สลับบิล
                    // $('#switch_sale').on('change', function () {
                    //     const saleId = $(this).val();
                    //     $.ajax({
                    //         url: '/page/sale/sale_add.php',
                    //         method: 'POST',
                    //         data: {
                    //             action: 'switch_sale',
                    //             sale_id: saleId
                    //         },
                    //         dataType: 'json',
                    //         success: function (response) {
                    //             if (response.status === 'success') {
                    //                 window.location.href = '/?page=sale_add&sale_id=' + saleId;
                    //             } else {
                    //                 Swal.fire({
                    //                     icon: 'error',
                    //                     title: 'ข้อผิดพลาด',
                    //                     text: response.message
                    //                 });
                    //             }
                    //         },
                    //         error: function (xhr) {
                    //             Swal.fire({
                    //                 icon: 'error',
                    //                 title: 'ข้อผิดพลาด',
                    //                 text: xhr.responseText || 'ไม่สามารถสลับบิลได้'
                    //             });
                    //         }
                    //     });
                    // });
                    // เปิดบิลใหม่
                    $('#new_sale').on('click', function () {
                        $.ajax({
                            url: 'page/sale/sale_add.php',
                            method: 'POST',
                            data: {
                                action: 'new_sale'
                            },
                            dataType: 'json',
                            success: function (response) {
                                if (response.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'สำเร็จ',
                                        text: response.message,
                                        timer: 1500
                                    }).then(() => {
                                        window.location.href = response.redirect;
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ข้อผิดพลาด',
                                        text: response.message
                                    });
                                }
                            },
                            error: function (xhr) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: xhr.responseText || 'ไม่สามารถสร้างบิลใหม่ได้'
                                });
                            }
                        });
                    });

                    // ยกเลิกบิล
                    $('#cancel_sale').on('click', function () {
                        Swal.fire({
                            title: 'ยืนยันการยกเลิกบิล?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'ยกเลิกบิล',
                            cancelButtonText: 'ยกเลิก'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $.ajax({
                                    url: 'page/sale/sale_add.php',
                                    method: 'POST',
                                    data: {
                                        action: 'cancel_sale',
                                        sale_id: '<?php echo $sale_id; ?>'
                                    },
                                    dataType: 'json',
                                    success: function (response) {
                                        if (response.status === 'success') {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'สำเร็จ',
                                                text: response.message,
                                                timer: 1500
                                            }).then(() => {
                                                window.location.href = response.redirect;
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'ข้อผิดพลาด',
                                                text: response.message
                                            });
                                        }
                                    },
                                    error: function (xhr) {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'ข้อผิดพลาด',
                                            text: xhr.responseText || 'ไม่สามารถยกเลิกบิลได้'
                                        });
                                    }
                                });
                            }
                        });
                    });


                    // ลบสินค้า
                    $(document).on('click', '.delete-product', function (e) {
                        e.preventDefault();
                        console.log('Delete button clicked:', $(this).data('barcode')); // Log การคลิก
                        if (isProcessing) {
                            console.warn('Processing in progress, ignoring click');
                            return;
                        }
                        const barcode = $(this).data('barcode');
                        const qty = parseInt($(this).data('qty')) || 1;
                        Swal.fire({
                            title: 'ยืนยันการลบสินค้า?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'ลบ',
                            cancelButtonText: 'ยกเลิก'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                console.log('Sending AJAX request to delete:', {
                                    barcode,
                                    qty
                                }); // Log ก่อนส่ง
                                $.ajax({
                                    url: 'page/sale/sale_add.php',
                                    method: 'POST',
                                    data: {
                                        action: 'delete',
                                        sale_id: '<?php echo htmlspecialchars($sale_id); ?>',
                                        barcode: barcode,
                                        qty: qty
                                    },
                                    dataType: 'json',
                                    timeout: 2000,
                                    beforeSend: function (xhr) {
                                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); // ยืนยัน header
                                        isProcessing = true;
                                        console.log('AJAX request sent:', {
                                            action: 'delete',
                                            sale_id: '<?php echo htmlspecialchars($sale_id); ?>',
                                            barcode,
                                            qty
                                        });
                                    },
                                    success: function (response) {
                                        console.log('AJAX response:', response); // Log response
                                        if (response.status === 'success') {
                                            Toast.fire({
                                                icon: 'success',
                                                title: response.message
                                            });
                                            updateProductTable();
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'ข้อผิดพลาด',
                                                text: response.message,
                                                didOpen: () => {
                                                    $('.main-body').removeAttr('aria-hidden');
                                                }
                                            });
                                        }
                                    },
                                    complete: function () {
                                        isProcessing = false;
                                        $('.main-body').removeAttr('aria-hidden');
                                        if (!$('#edit-product-modal').is(':visible')) {
                                            $('#barcode_input').focus();
                                        }
                                    },
                                    error: function (xhr, status, error) {
                                        console.error('AJAX error:', status, error, xhr.responseText); // Log error
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'ข้อผิดพลาด',
                                            text: xhr.responseText || 'ไม่สามารถลบสินค้าได้: ' + error,
                                            didOpen: () => {
                                                $('.main-body').removeAttr('aria-hidden');
                                            }
                                        });
                                        isProcessing = false;
                                    }
                                });
                            }
                        });
                    });

                    // เปิด Modal และกรอกข้อมูลแก้ไข
                    $(document).on('click', '.edit-product', function (e) {
                        e.preventDefault();
                        const barcode = $(this).data('barcode');
                        const name = $(this).data('name');
                        const qty = $(this).data('qty');
                        const price = $(this).data('price');
                        const capital = $(this).data('capital');
                        const reference = $(this).data('reference') || '';
                        $('#edit-barcode').val(barcode);
                        $('#edit-barcode-display').val(barcode);
                        $('#edit-product-name').val(name);
                        $('#edit-qty').val(qty);
                        $('#edit-price').val(parseFloat(price).toFixed(2));
                        $('#edit-capital').val(parseFloat(capital).toFixed(2));
                        $('#edit-reference').val(reference);
                        $('.main-body').attr('aria-hidden', 'true');
                        $('#edit-product-modal').removeClass('hidden');
                    });
                    // Submit ฟอร์มแก้ไขสินค้า
                    $('#edit-product-form').on('submit', function (e) {
                        e.preventDefault();
                        if (isProcessing) return;
                        const qty = parseInt($('#edit-qty').val()) || 0;
                        if (qty <= 0) {
                            Swal.fire({
                                icon: 'error',
                                title: 'ข้อผิดพลาด',
                                text: 'จำนวนต้องมากกว่า 0',
                                didOpen: () => {
                                    $('.main-body').removeAttr('aria-hidden');
                                }
                            });
                            return;
                        }
                        $.ajax({
                            url: 'page/sale/sale_add.php',
                            method: 'POST',
                            data: $(this).serialize(),
                            dataType: 'json',
                            beforeSend: function () {
                                isProcessing = true;
                            },
                            success: function (response) {
                                if (response.status === 'success') {
                                    Toast.fire({
                                        icon: 'success',
                                        title: response.message
                                    });
                                    $('#edit-product-modal').addClass('hidden');
                                    updateProductTable();
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ข้อผิดพลาด',
                                        text: response.message,
                                        didOpen: () => {
                                            $('.main-body').removeAttr('aria-hidden');
                                        }
                                    });
                                }
                            },
                            complete: function () {
                                isProcessing = false;
                                $('.main-body').removeAttr('aria-hidden');
                                if (!$('#edit-product-modal').is(':visible')) {
                                    $('#barcode_input').focus();
                                }
                            },
                            error: function (xhr) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: xhr.responseText || 'ไม่สามารถแก้ไขสินค้าได้',
                                    didOpen: () => {
                                        $('.main-body').removeAttr('aria-hidden');
                                    }
                                });
                                isProcessing = false;
                            }
                        });
                    });
                    $('#close-modal, #cancel-edit').on('click', function () {
                        $('#edit-product-modal').addClass('hidden');
                        $('.main-body').removeAttr('aria-hidden');
                        $('#barcode_input').focus();
                    });

                    // ยืนยันการลบสินค้า
                    // function confirmDelete() {
                    //     return confirm('ยืนยันการลบสินค้านี้?');
                    // }


                    // Bind event สำหรับปุ่มแก้ไขและลบ
                    function bindEditProductEvents() {
                        $('.edit-product').off('click').on('click', function (e) {
                            e.preventDefault();
                            const barcode = $(this).data('barcode');
                            const name = $(this).data('name');
                            const qty = $(this).data('qty');
                            const price = $(this).data('price');
                            const capital = $(this).data('capital');
                            const reference = $(this).data('reference') || '';
                            $('#edit-barcode').val(barcode);
                            $('#edit-barcode-display').val(barcode);
                            $('#edit-product-name').val(name);
                            $('#edit-qty').val(qty);
                            $('#edit-price').val(parseFloat(price).toFixed(2));
                            $('#edit-capital').val(parseFloat(capital).toFixed(2));
                            $('#edit-reference').val(reference);
                            $('.main-body').attr('aria-hidden', 'true');
                            $('#edit-product-modal').removeClass('hidden');
                        });
                    }
                });
            </script>
            <script>
                // Array to track bills in client-side
                let bills = <?php echo json_encode($valid_active_sales); ?>;
                let currentSaleId = <?php echo json_encode($sale_id); ?>;

                // Function to create a new sale
                function createNewSale() {
                    $.ajax({
                        url: window.location.pathname,
                        type: 'POST',
                        data: {
                            action: 'new_sale'
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                bills.push(response.sale_id);
                                currentSaleId = response.sale_id;
                                addBillTab(response.sale_id);
                                switchBill(response.sale_id);
                                Swal.fire({
                                    toast: true,
                                    position: 'center',
                                    icon: 'success',
                                    title: response.message,
                                    showConfirmButton: false,
                                    timer: 1500,
                                    timerProgressBar: true
                                });
                                window.location.href = response.redirect;
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: response.message,
                                    confirmButtonText: 'ตกลง'
                                });
                            }
                        },
                        error: function (xhr, status, error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'ข้อผิดพลาด',
                                text: 'เกิดข้อผิดพลาดในการสร้างบิลใหม่: ' + error,
                                confirmButtonText: 'ตกลง'
                            });
                        }
                    });
                }

                // Function to add a bill tab to the UI
                function addBillTab(saleId) {
                    const tab = `<div id="tab_${saleId}" class="px-4 py-2 rounded tab-active cursor-pointer whitespace-nowrap" onclick="switchBill(${saleId})">
            บิล #${saleId} <span onclick="cancelSale(${saleId})">×</span>
        </div>`;
                    $('#billTabs').append(tab);
                    updateTabStyles();
                }

                // Function to switch to a different bill
                function switchBill(saleId) {
                    if (saleId === currentSaleId) return;
                    $.ajax({
                        url: '/page/sale/sale_add.php',
                        type: 'POST',
                        data: {
                            action: 'switch_sale',
                            sale_id: saleId
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                currentSaleId = saleId;
                                updateTabStyles();
                                window.location.href = `?page=sale_add&sale_id=${saleId}`;
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ข้อผิดพลาด',
                                    text: response.message,
                                    confirmButtonText: 'ตกลง'
                                });
                            }
                        },
                        error: function (xhr, status, error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'ข้อผิดพลาด',
                                text: 'เกิดข้อผิดพลาดในการสลับบิล: ' + error,
                                confirmButtonText: 'ตกลง'
                            });
                        }
                    });
                }

                // Function to cancel a sale
                function cancelSale(saleId) {
                    event.stopPropagation(); // Prevent triggering switchBill
                    Swal.fire({
                        title: 'ยืนยันการยกเลิก?',
                        text: `คุณต้องการยกเลิกบิล #${saleId} หรือไม่?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'ยืนยัน',
                        cancelButtonText: 'ยกเลิก'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: window.location.pathname,
                                type: 'POST',
                                data: {
                                    action: 'cancel_sale',
                                    sale_id: saleId
                                },
                                dataType: 'json',
                                success: function (response) {
                                    if (response.status === 'success') {
                                        bills = bills.filter(id => id != saleId);
                                        $(`#tab_${saleId}`).remove();
                                        if (currentSaleId == saleId) {
                                            currentSaleId = bills.length > 0 ? bills[0] : null;
                                        }
                                        updateTabStyles();
                                        Swal.fire({
                                            toast: true,
                                            position: 'center',
                                            icon: 'success',
                                            title: response.message,
                                            showConfirmButton: false,
                                            timer: 1500,
                                            timerProgressBar: true
                                        });
                                        if (response.redirect) {
                                            window.location.href = response.redirect;
                                        }
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'ข้อผิดพลาด',
                                            text: response.message,
                                            confirmButtonText: 'ตกลง'
                                        });
                                    }
                                },
                                error: function (xhr, status, error) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ข้อผิดพลาด',
                                        text: 'เกิดข้อผิดพลาดในการยกเลิกบิล: ' + error,
                                        confirmButtonText: 'ตกลง'
                                    });
                                }
                            });
                        }
                    });
                }

                // Function to update tab styles based on currentSaleId
                function updateTabStyles() {
                    $('#billTabs > div').each(function () {
                        const tabId = $(this).attr('id').replace('tab_', '');
                        if (tabId == currentSaleId) {
                            $(this).removeClass('tab-inactive').addClass('tab-active');
                        } else {
                            $(this).removeClass('tab-active').addClass('tab-inactive');
                        }
                    });
                }

                // Event listener for new sale button
                $('#new_sale').on('click', createNewSale);
            </script>
        </div>
    </div>
</body>

</html>
<?php ob_end_flush(); ?>