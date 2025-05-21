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
            $stock_check_system = $_POST['stock_check_system'] ?? null;
            $stock_check_actual = $_POST['stock_check_actual'] ?? null;
            $stock_check_date = $_POST['stock_check_date'] ?? null;
            $stock_check_remark = $_POST['stock_check_remark'] ?? '';
            $personnel_id = $_POST['personnel_id'] ?? null;
            $check_status_id = 1;

            // ตรวจสอบ
            if (!$product_id || !$warehouse_id || $stock_check_system === null || $stock_check_actual === null || !$stock_check_date || !$personnel_id || !$lot_id || !$lot_product_id) {
                error_log("Missing required fields: " . json_encode($_POST));
                echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
                exit;
            }
            if (!is_numeric($stock_check_system) || !is_numeric($stock_check_actual)) {
                error_log("Invalid numeric values: system=$stock_check_system, actual=$stock_check_actual");
                echo json_encode(['status' => 'error', 'message' => 'จำนวนต้องเป็นตัวเลข']);
                exit;
            }
            // ตรวจสอบวันที่
            try {
                $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $stock_check_date, new DateTimeZone('Asia/Bangkok'));
                if ($dateTime === false) {
                    throw new Exception('Invalid date format');
                }
                $stock_check_date = $dateTime->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                error_log("Date parsing error: " . $e->getMessage() . " Input: " . $stock_check_date);
                echo json_encode(['status' => 'error', 'message' => 'รูปแบบวันที่ไม่ถูกต้อง']);
                exit;
            }

            if (!$lot_id || !$lot_product_id) {
                $stmt = $pdo->prepare("
                    SELECT lp.lot_id, lp.lot_product_id
                    FROM lot_product lp
                    JOIN lot l ON lp.lot_id = l.lot_id
                    WHERE lp.product_id = ? AND l.warehouse_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$product_id, $warehouse_id]);
                $lot_data = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($lot_data) {
                    $lot_id = $lot_id ?? $lot_data['lot_id'];
                    $lot_product_id = $lot_product_id ?? $lot_data['lot_product_id'];
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลล็อตสำหรับสินค้านี้']);
                    exit;
                }
            }

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

            $stmt = $pdo->prepare("
                INSERT INTO stock_check (lot_id, lot_product_id, product_id, warehouse_id, stock_check_system, stock_check_actual, stock_check_date, stock_check_remark, personnel_id, check_status_id )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$lot_id, $lot_product_id, $product_id, $warehouse_id, $stock_check_system, $stock_check_actual, $stock_check_date, $stock_check_remark, $personnel_id, $check_status_id]);

            echo json_encode(['status' => 'success', 'message' => 'บันทึกสำเร็จ']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Insert error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>