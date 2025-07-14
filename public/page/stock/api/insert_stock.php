<?php
require_once __DIR__ . '../../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 
header('Content-Type: application/json');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $action = $_POST['action'] ?? '';
    if ($action !== 'insert_check_product') {
        throw new Exception('Invalid action');
    }

    $stock_id = $_POST['stock_id'] ?? null;
    $product_id = $_POST['product_id'] ?? null;
    $warehouse_id = $_POST['warehouse_id'] ?? null;
    $lot_id = $_POST['lot_id'] ?? null;
    $lot_product_id = $_POST['lot_product_id'] ?? null;
    $stock_check_actual = $_POST['stock_check_actual'] ?? null;
    $stock_check_system = $_POST['stock_check_system'] ?? null;
    $stock_check_remark_id = $_POST['stock_check_remark_id'] ?? null;
    $stock_check_comment = $_POST['stock_check_comment'] ?? '';
    // $personnel_id = $_POST['personnel_id'] ?? null;
    $is_update = filter_var($_POST['is_update'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!$stock_id || !$product_id || !$warehouse_id || !$lot_id || !$lot_product_id || $stock_check_actual === null || $stock_check_system === null) { //|| !$personnel_id
        throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
    }

    // คำนวณ stock_check_remark_id อัตโนมัติ
    $stock_check_remark_id = null;
    if ($stock_check_actual === $stock_check_system) {
        $stock_check_remark_id = 1; // ครบถ้วน
    } elseif ($stock_check_actual < $stock_check_system) {
        $stock_check_remark_id = 2; // ขาดหาย
    } elseif ($stock_check_actual > $stock_check_system) {
        $stock_check_remark_id = 3; // จำนวนสินค้าเกิน
    }
    $pdo->beginTransaction();

    // ตรวจสอบ stock_id และ personnel_id
    // $query = "SELECT stock_id FROM stock WHERE stock_id = :stock_id AND check_status_id = 1 AND personnel_id = :personnel_id";
    // $stmt = $pdo->prepare($query);
    // $stmt->execute([':stock_id' => $stock_id, ':personnel_id' => $personnel_id]);
    // if (!$stmt->fetch()) {
    //     $pdo->rollBack();
    //     throw new Exception("เซสชันสต๊อก $stock_id ไม่ถูกต้อง, ถูกยืนยันแล้ว, หรือไม่ใช่ของผู้ใช้");
    // }
    if ($is_update) {
        // ตรวจสอบว่ามีรายการใน stock_check หรือไม่
        $query = "SELECT stock_check_id, stock_check_actual FROM stock_check 
                  WHERE stock_id = :stock_id AND product_id = :product_id AND warehouse_id = :warehouse_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':stock_id' => $stock_id,
            ':product_id' => $product_id,
            ':warehouse_id' => $warehouse_id
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // อัปเดต stock_check_actual
            $new_stock_check_actual = $existing['stock_check_actual'] + (int) $stock_check_actual;
            $query = "UPDATE stock_check 
                      SET stock_check_actual = :stock_check_actual, 
                          stock_check_comment = :stock_check_comment,
                          stock_check_remark_id = :stock_check_remark_id
                      WHERE stock_check_id = :stock_check_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':stock_check_actual' => $new_stock_check_actual,
                ':stock_check_comment' => $stock_check_comment,
                ':stock_check_remark_id' => $stock_check_remark_id,
                ':stock_check_id' => $existing['stock_check_id']
            ]);

            $pdo->commit();
            echo json_encode([
                'status' => 'success',
                'message' => 'อัปเดตรายการตรวจสอบสต๊อกเรียบร้อย',
                'stock_id' => $stock_id
            ]);
            exit;
        }
    }

    // ถ้าไม่มีรายการหรือไม่ใช่ update, ทำการ INSERT
    $query = "INSERT INTO stock_check (stock_id, product_id, warehouse_id, lot_id, lot_product_id, stock_check_actual, stock_check_system, stock_check_remark_id, stock_check_comment)
              VALUES (:stock_id, :product_id, :warehouse_id, :lot_id, :lot_product_id, :stock_check_actual, :stock_check_system, :stock_check_remark_id, :stock_check_comment)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':stock_id' => $stock_id,
        ':product_id' => $product_id,
        ':warehouse_id' => $warehouse_id,
        ':lot_id' => $lot_id,
        ':lot_product_id' => $lot_product_id,
        ':stock_check_actual' => $stock_check_actual,
        ':stock_check_system' => $stock_check_system,
        ':stock_check_remark_id' => $stock_check_remark_id,
        ':stock_check_comment' => $stock_check_comment
    ]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'เพิ่มรายการตรวจสอบสต๊อกเรียบร้อย',
        'stock_id' => $stock_id
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // error_log("insert_stock.php error: " . $e->getMessage(), 3, "/var/log/php_errors.log");
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>