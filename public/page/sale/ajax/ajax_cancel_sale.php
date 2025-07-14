<?php
ob_start();
session_start();

try {
    require_once __DIR__ . '/../../../../config/database_server.php';
    error_log("Database configuration included successfully");
} catch (Exception $e) {
    error_log("Error including database_server.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage()
    ]);
    exit();
}
$pdo = Database::getInstance()->getConnection(); 
try {
    require_once __DIR__ . '/../../../../functions/adjustLotRemain.php';
    error_log("adjustLotRemain function included successfully");
} catch (Exception $e) {
    error_log("Error including adjustLotRemain.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'การเข้าถึงฟังก์ชัน adjustLotRemain ล้มเหลว: ' . $e->getMessage()
    ]);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'cancel_sale') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}

$cancel_sale_id = $_POST['sale_id'] ?? null;
if (!$cancel_sale_id) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ sale_id ในคำขอ']);
    exit();
}

// ตรวจสอบว่า sale_id มีอยู่ในฐานข้อมูลและอยู่ในสถานะที่สามารถยกเลิกได้
try {
    $check_sale = $pdo->prepare("SELECT sale_status_id FROM sale WHERE sale_id = ?");
    $check_sale->execute([$cancel_sale_id]);
    $sale = $check_sale->fetch(PDO::FETCH_ASSOC);
    if (!$sale) {
        echo json_encode(['status' => 'error', 'message' => 'บิล #' . $cancel_sale_id . ' ไม่พบในระบบ']);
        exit();
    }
    if (!in_array($sale['sale_status_id'], [1, 2])) {
        echo json_encode(['status' => 'error', 'message' => 'บิล #' . $cancel_sale_id . ' ไม่สามารถยกเลิกได้ เนื่องจากไม่ได้อยู่ในสถานะรอดำเนินการ']);
        exit();
    }
} catch (Exception $e) {
    error_log("Error checking sale $cancel_sale_id: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถตรวจสอบสถานะบิลได้: ' . $e->getMessage()]);
    exit();
}

// ตรวจสอบ session active_sales
if (!in_array($cancel_sale_id, $_SESSION['active_sales'] ?? [])) {
    echo json_encode(['status' => 'error', 'message' => 'บิล #' . $cancel_sale_id . ' ไม่พบในรายการบิลที่ใช้งานอยู่']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Restore stock in lot_product
    $stmt = $pdo->prepare("SELECT product_id, sale_product_qty FROM sale_product WHERE sale_id = ?");
    $stmt->execute([$cancel_sale_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        if (!isset($product['product_id']) || !isset($product['sale_product_qty']) || $product['sale_product_qty'] <= 0) {
            throw new Exception("ข้อมูลสินค้าไม่ถูกต้องสำหรับบิล #$cancel_sale_id");
        }
        adjustLotRemain($pdo, $product['product_id'], $product['sale_product_qty'], true);
    }

    // Update sale status to cancelled (sale_status_id = 3)
    $updateSaleStatusid = $pdo->prepare("UPDATE sale SET sale_status_id = 3 WHERE sale_id = ?");
    $updateSaleStatusid->execute([$cancel_sale_id]);

    // Update session
    $_SESSION['active_sales'] = array_diff($_SESSION['active_sales'], [$cancel_sale_id]);
    if ($_SESSION['current_sale_id'] == $cancel_sale_id) {
        $_SESSION['current_sale_id'] = !empty($_SESSION['active_sales']) ? reset($_SESSION['active_sales']) : null;
    }

    $pdo->commit();
    $redirect_url = $_SESSION['current_sale_id'] ? "?page=sale_add&sale_id={$_SESSION['current_sale_id']}" : "?page=sale_add";
    echo json_encode(['status' => 'success', 'message' => 'บิล #' . $cancel_sale_id . ' ถูกยกเลิกเรียบร้อย', 'redirect' => $redirect_url]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error cancelling sale $cancel_sale_id: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกบิลได้: ' . $e->getMessage()]);
}

ob_end_flush();
exit();
