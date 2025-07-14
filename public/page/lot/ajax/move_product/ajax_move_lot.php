<?php
require_once __DIR__ . '../../../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    $pdo->beginTransaction();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'scan_product') {
        // คงโค้ดเดิมไว้ ไม่มีการเปลี่ยนแปลง
        $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';
        $lot_id = isset($_POST['lot_id']) ? (int)$_POST['lot_id'] : 0;

        if (empty($barcode) || $lot_id <= 0) {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }

        // ตรวจสอบว่า lot ยังไม่ยืนยัน
        $stmt = $pdo->prepare("SELECT lot_status_id FROM lot WHERE lot_id = ?");
        $stmt->execute([$lot_id]);
        $lot = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lot['lot_status_id'] == 2) {
            throw new Exception('ไม่สามารถแก้ไข Lot ที่ยืนยันแล้วได้');
        }

        // แยกข้อมูลบาร์โค้ด
        $parts = explode('*', $barcode);
        $is_delete = strpos($barcode, '-') === 0;
        $barcode_value = $is_delete ? ltrim(end($parts), '-') : end($parts);
        $quantity = 1;

        if (count($parts) >= 2) {
            $quantity = (int)$parts[0];
        }

        if ($is_delete) {
            $quantity = -abs($quantity);
        }

        if ($quantity == 0) {
            throw new Exception('จำนวนต้องมากกว่า 0');
        }

        // ค้นหาสินค้าและราคาทุน
        $stmt = $pdo->prepare("SELECT product_id, product_name, product_capital FROM product WHERE product_id = ?");
        $stmt->execute([$barcode_value]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception('ไม่พบสินค้าด้วยบาร์โค้ดนี้');
        }

        // ใช้ product_capital จากตาราง product
        $capital = $product['product_capital'];

        // ตรวจสอบสต็อกต้นทาง
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(lp.lot_product_remain), 0) as source_qty
            FROM lot_product lp
            JOIN lot l ON lp.lot_id = l.lot_id
            WHERE lp.product_id = ? AND l.warehouse_id = (
                SELECT warehouse_from_id FROM lot WHERE lot_id = ?
            ) AND l.lot_status_id = 2
        ");
        $stmt->execute([$product['product_id'], $lot_id]);
        $source_qty = $stmt->fetchColumn();

        // ตรวจสอบ lot_product ปัจจุบัน
        $stmt = $pdo->prepare("
            SELECT lot_product_id, lot_product_qty, lot_product_remain, lot_product_capital 
            FROM lot_product 
            WHERE lot_id = ? AND product_id = ?
        ");
        $stmt->execute([$lot_id, $product['product_id']]);
        $lot_product = $stmt->fetch(PDO::FETCH_ASSOC);

        $current_qty = $lot_product ? $lot_product['lot_product_qty'] : 0;
        $new_qty = $current_qty + $quantity;

        // ตรวจสอบการลบโดยเทียบกับจำนวนที่ต้องการย้ายใน lot
        if ($is_delete && abs($quantity) > $current_qty) {
            throw new Exception("ไม่สามารถลบได้ เนื่องจากจำนวนที่ต้องการลบ (" . abs($quantity) . ") เกินจำนวนที่ต้องการย้ายใน Lot (" . $current_qty . ")");
        }

        // ตรวจสอบการเพิ่มโดยเทียบกับสต็อกต้นทาง
        if ($quantity > 0 && $quantity > $source_qty - $current_qty) {
            throw new Exception("จำนวนที่ต้องการย้าย ($quantity) เกินจำนวนสินค้าคงเหลือในคลังต้นทาง (" . $source_qty . ")");
        }

        $current_datetime = date('Y-m-d H:i:s');
        if ($new_qty <= 0) {
            // ลบสินค้าถ้าจำนวนน้อยกว่าหรือเท่ากับ 0
            $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_id = ? AND product_id = ?");
            $stmt->execute([$lot_id, $product['product_id']]);
        } elseif ($lot_product) {
            $stmt = $pdo->prepare("
                UPDATE lot_product 
                SET lot_product_qty = ?, lot_product_remain = ?, lot_product_capital = ? 
                WHERE lot_product_id = ?
            ");
            $stmt->execute([$new_qty, $new_qty, $capital, $lot_product['lot_product_id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO lot_product (lot_id, product_id, lot_product_qty, lot_product_remain, lot_product_capital, lot_product_date, lot_product_status) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$lot_id, $product['product_id'], $quantity, $quantity, $capital, $current_datetime]);
        }

        $response['success'] = true;
    } elseif ($action === 'delete_product') {
        // คงโค้ดเดิมไว้
        $lot_product_id = isset($_POST['lot_product_id']) ? (int)$_POST['lot_product_id'] : 0;

        if ($lot_product_id <= 0) {
            throw new Exception('ไม่พบข้อมูลสินค้า');
        }

        // ตรวจสอบสถานะ lot
        $stmt = $pdo->prepare("
            SELECT l.lot_status_id 
            FROM lot l
            JOIN lot_product lp ON l.lot_id = lp.lot_id
            WHERE lp.lot_product_id = ?
        ");
        $stmt->execute([$lot_product_id]);
        if ($stmt->fetchColumn() == 2) {
            throw new Exception('ไม่สามารถลบสินค้าจาก Lot ที่ยืนยันแล้วได้');
        }

        $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_product_id = ?");
        $stmt->execute([$lot_product_id]);

        $response['success'] = true;
    } elseif ($action === 'edit_product') {
        // คงโค้ดเดิมไว้
        $lot_product_id = isset($_POST['lot_product_id']) ? (int)$_POST['lot_product_id'] : 0;
        $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 0;

        if ($lot_product_id <= 0) {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }

        // ตรวจสอบสถานะ lot และดึง product_id
        $stmt = $pdo->prepare("
            SELECT l.lot_status_id, lp.product_id, lp.lot_id 
            FROM lot l
            JOIN lot_product lp ON l.lot_id = lp.lot_id
            WHERE lp.lot_product_id = ?
        ");
        $stmt->execute([$lot_product_id]);
        $lot_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lot_data['lot_status_id'] == 2) {
            throw new Exception('ไม่สามารถแก้ไข Lot ที่ยืนยันแล้วได้');
        }

        // ดึง product_capital จากตาราง product
        $stmt = $pdo->prepare("SELECT product_capital FROM product WHERE product_id = ?");
        $stmt->execute([$lot_data['product_id']]);
        $capital = $stmt->fetchColumn();

        // ตรวจสอบสต็อกต้นทาง
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(lp.lot_product_remain), 0) as source_qty
            FROM lot_product lp
            JOIN lot l ON lp.lot_id = l.lot_id
            WHERE lp.product_id = ? AND l.warehouse_id = (
                SELECT warehouse_from_id FROM lot WHERE lot_id = ?
            ) AND l.lot_status_id = 2
        ");
        $stmt->execute([$lot_data['product_id'], $lot_data['lot_id']]);
        $source_qty = $stmt->fetchColumn();

        if ($qty > $source_qty) {
            throw new Exception("จำนวนที่ต้องการย้าย ($qty) เกินจำนวนสินค้าคงเหลือในคลังต้นทาง (" . $source_qty . ")");
        }

        if ($qty <= 0) {
            // ลบสินค้าถ้าจำนวนน้อยกว่าหรือเท่ากับ 0
            $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_product_id = ?");
            $stmt->execute([$lot_product_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE lot_product 
                SET lot_product_qty = ?, lot_product_remain = ?, lot_product_capital = ? 
                WHERE lot_product_id = ?
            ");
            $stmt->execute([$qty, $qty, $capital, $lot_product_id]);
        }

        $response['success'] = true;
    } elseif ($action === 'confirm_transfer') {
        $lot_id = isset($_POST['lot_id']) ? (int)$_POST['lot_id'] : 0;

        if ($lot_id <= 0) {
            throw new Exception('ไม่พบข้อมูล Lot');
        }

        // ตรวจสอบสถานะ lot และ warehouse_inout
        $stmt = $pdo->prepare("
            SELECT warehouse_from_id, warehouse_id, lot_status_id, warehouse_inout
            FROM lot 
            WHERE lot_id = ?
        ");
        $stmt->execute([$lot_id]);
        $lot = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lot['lot_status_id'] == 2) {
            throw new Exception('Lot นี้ยืนยันแล้ว');
        }

        // ตรวจสอบว่ามีสินค้าอย่างน้อย 1 ชิ้น
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM lot_product lp 
            WHERE lp.lot_id = ? AND lp.lot_product_qty > 0
        ");
        $stmt->execute([$lot_id]);
        $product_count = $stmt->fetchColumn();

        if ($product_count == 0) {
            throw new Exception('ต้องมีสินค้าอย่างน้อย 1 ชิ้นเพื่อยืนยันการย้าย');
        }

        // ดึงรายการสินค้าใน lot
        $stmt = $pdo->prepare("
            SELECT lp.product_id, lp.lot_product_qty 
            FROM lot_product lp 
            WHERE lp.lot_id = ? AND lp.lot_product_qty > 0
        ");
        $stmt->execute([$lot_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ถ้าเป็นการย้ายออก (warehouse_inout = 2) ให้ตรวจสอบและลดสต็อกต้นทาง
        if ($lot['warehouse_inout'] == 2) {
            foreach ($products as $product) {
                // ตรวจสอบสต็อกต้นทาง
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(lp.lot_product_remain), 0) as source_qty
                    FROM lot_product lp
                    JOIN lot l ON lp.lot_id = l.lot_id
                    WHERE lp.product_id = ? AND l.warehouse_id = ?
                    AND l.lot_status_id = 2
                ");
                $stmt->execute([$product['product_id'], $lot['warehouse_from_id']]);
                $source_qty = $stmt->fetchColumn();

                if ($product['lot_product_qty'] > $source_qty) {
                    throw new Exception("สินค้า {$product['product_id']} มีจำนวนไม่เพียงพอในคลังต้นทาง");
                }

                // อัปเดตสต็อกต้นทางโดยไม่ใช้ LIMIT
                $stmt = $pdo->prepare("
                    UPDATE lot_product lp
                    JOIN lot l ON lp.lot_id = l.lot_id
                    SET lp.lot_product_remain = lp.lot_product_remain - ?
                    WHERE lp.product_id = ? AND l.warehouse_id = ? AND l.lot_status_id = 2
                ");
                $stmt->execute([$product['lot_product_qty'], $product['product_id'], $lot['warehouse_from_id']]);
            }
        }

        // อัปเดตสถานะ lot
        $stmt = $pdo->prepare("
            UPDATE lot 
            SET lot_status_id = (SELECT lot_status_id FROM lot_status WHERE lot_status_name = 'ยืนยันแล้ว') 
            WHERE lot_id = ?
        ");
        $stmt->execute([$lot_id]);

        $response['success'] = true;
    } else {
        throw new Exception('ไม่พบคำสั่งที่ระบุ');
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>