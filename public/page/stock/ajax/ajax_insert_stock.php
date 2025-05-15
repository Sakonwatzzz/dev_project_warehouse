<?php
require_once __DIR__ . '../../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    ob_clean();
    try {
        if ($_POST['action'] === 'insert_check_product') {
            $lot_id = $_POST['lot_id'] ?? null;
            $lot_product_id = $_POST['lot_product_id'] ?? null;
            $product_id = $_POST['product_id'] ?? null;
            $warehouse_id = $_POST['warehouse_id'] ?? null;
            $system_quantity = $_POST['system_quantity'] ?? null;
            $actual_quantity = $_POST['actual_quantity'] ?? null;
            $stock_check_date = $_POST['stock_check_date'] ?? null;
            $stock_check_remark = $_POST['stock_check_remark'] ?? '';
            $personnel_id = $_POST['personnel_id'] ?? null;
            $check_status_id = 1; // Default to 'pending'

            // Validation
            if (!$lot_id || !$lot_product_id || !$product_id || !$warehouse_id || $system_quantity === null || $actual_quantity === null || !$stock_check_date || !$personnel_id) {
                echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
                exit;
            }

            if (!is_numeric($system_quantity) || !is_numeric($actual_quantity) || $actual_quantity < 0) {
                echo json_encode(['status' => 'error', 'message' => 'จำนวนต้องเป็นตัวเลขและไม่ติดลบ']);
                exit;
            }

            // Verify product and lot
            $stmt = $pdo->prepare("
                SELECT 1 FROM lot_product lp
                JOIN lot l ON lp.lot_id = l.lot_id
                WHERE lp.lot_product_id = ? AND lp.product_id = ? AND lp.lot_id = ? AND l.warehouse_id = ?
            ");
            $stmt->execute([$lot_product_id, $product_id, $lot_id, $warehouse_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'ข้อมูลสินค้าหรือล็อตไม่ถูกต้อง']);
                exit;
            }

            // Insert into stock_check
            $stmt = $pdo->prepare("
                INSERT INTO stock_check (lot_id, lot_product_id, product_id, warehouse_id, system_quantity, actual_quantity, stock_check_date, stock_check_remark, personnel_id, check_status_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$lot_id, $lot_product_id, $product_id, $warehouse_id, $system_quantity, $actual_quantity, $stock_check_date, $stock_check_remark, $personnel_id, $check_status_id]);

            echo json_encode(['status' => 'success', 'message' => 'บันทึกสำเร็จ']);
        } elseif ($_POST['action'] === 'insert_check_products') {
            $warehouse_id = $_POST['warehouse_id'] ?? null;
            $stock_check_date = $_POST['stock_check_date'] ?? null;
            $stock_check_remark = $_POST['stock_check_remark'] ?? '';
            $personnel_id = $_POST['personnel_id'] ?? null;
            $products = json_decode($_POST['products'] ?? '[]', true);
            $check_status_id = 1; // Default to 'pending'

            // Validation
            if (!$warehouse_id || !$stock_check_date || !$personnel_id || empty($products)) {
                echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                foreach ($products as $product) {
                    $lot_id = $product['lot_id'] ?? null;
                    $lot_product_id = $product['lot_product_id'] ?? null;
                    $product_id = $product['product_id'] ?? null;
                    $system_quantity = $product['system_quantity'] ?? null;
                    $actual_quantity = $product['actual_quantity'] ?? null;

                    if (!$lot_id || !$lot_product_id || !$product_id || $system_quantity === null || $actual_quantity === null) {
                        throw new Exception('ข้อมูลสินค้าไม่ครบถ้วน');
                    }

                    if (!is_numeric($system_quantity) || !is_numeric($actual_quantity) || $actual_quantity < 0) {
                        throw new Exception('จำนวนต้องเป็นตัวเลขและไม่ติดลบ');
                    }

                    // Verify product and lot
                    $stmt = $pdo->prepare("
                        SELECT 1 FROM lot_product lp
                        JOIN lot l ON lp.lot_id = l.lot_id
                        WHERE lp.lot_product_id = ? AND lp.product_id = ? AND lp.lot_id = ? AND l.warehouse_id = ?
                    ");
                    $stmt->execute([$lot_product_id, $product_id, $lot_id, $warehouse_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception("ข้อมูลสินค้าหรือล็อตไม่ถูกต้อง: $product_id");
                    }

                    // Insert into stock_check
                    $stmt = $pdo->prepare("
                        INSERT INTO stock_check (lot_id, lot_product_id, product_id, warehouse_id, system_quantity, actual_quantity, stock_check_date, stock_check_remark, personnel_id, check_status_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$lot_id, $lot_product_id, $product_id, $warehouse_id, $system_quantity, $actual_quantity, $stock_check_date, $stock_check_remark, $personnel_id, $check_status_id]);
                }
                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'บันทึกสำเร็จ']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>