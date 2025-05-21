<?php
require_once __DIR__ . '../../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    $pdo->beginTransaction();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'scan_product') {
        $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';
        $lot_id = isset($_POST['lot_id']) ? (int)$_POST['lot_id'] : 0;

        if (empty($barcode) || $lot_id <= 0) {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }

        // ตรวจสอบว่า lot ยังไม่ยืนยัน
        $stmt = $pdo->prepare("SELECT lot_status_id, warehouse_inout, warehouse_from_id FROM lot WHERE lot_id = ?");
        $stmt->execute([$lot_id]);
        $lot = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lot['lot_status_id'] == 2) {
            throw new Exception('ไม่สามารถแก้ไข Lot ที่ยืนยันแล้วได้');
        }

        // แยกข้อมูลบาร์โค้ด
        $parts = explode('*', $barcode);
        $is_delete = strpos($barcode, '-') === 0;

        // กำหนดค่าเริ่มต้น
        $quantity = 1;
        $capital = null;
        $barcode_value = $is_delete ? ltrim($barcode, '-') : $barcode;

        if (count($parts) >= 1) {
            if (count($parts) === 1) {
                // รูปแบบ: รหัส หรือ -รหัส
                $barcode_value = $is_delete ? ltrim($parts[0], '-') : $parts[0];
            } elseif (count($parts) === 2) {
                // รูปแบบ: จำนวน*รหัส หรือ -จำนวน*รหัส
                $quantity = (int)$parts[0];
                if ($is_delete) {
                    $quantity = -abs($quantity);
                }
                $barcode_value = $parts[1];
            } elseif (count($parts) >= 3) {
                // รูปแบบ: จำนวน*รหัส*ราคาทุน
                $quantity = (int)$parts[0];
                $barcode_value = $parts[1];
                $capital = (float)$parts[2];
            }
        }

        if ($quantity == 0) {
            throw new Exception('จำนวนต้องมากกว่า 0');
        }

        if ($is_delete && $capital !== null) {
            throw new Exception('รูปแบบการลบไม่รองรับการระบุราคาทุน');
        }

        // ค้นหาสินค้า
        $stmt = $pdo->prepare("SELECT product_id, product_name, product_capital FROM product WHERE product_id = ?");
        $stmt->execute([$barcode_value]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            // ถ้าไม่มีสินค้า สร้างใหม่
            $insert_product = $pdo->prepare("INSERT INTO product (
                product_id, product_name, product_price1, product_price2, product_price3, 
                product_price4, product_capital, product_type_id, product_brand_id
            ) VALUES (?, ?, 0, 0, 0, 0, ?, NULL, NULL)");
            $insert_product->execute([$barcode_value, 'ยังไม่ระบุ', $capital ?? 0]);
            $product = ['product_id' => $barcode_value, 'product_name' => 'ยังไม่ระบุ', 'product_capital' => $capital ?? 0];
        } elseif ($capital !== null) {
            // อัปเดตราคาทุน
            $update_product = $pdo->prepare("UPDATE product SET product_capital = ? WHERE product_id = ?");
            $update_product->execute([$capital, $barcode_value]);
        }

        $final_capital = $capital ?? $product['product_capital'];

        // ตรวจสอบ lot_product ปัจจุบัน
        $stmt = $pdo->prepare("SELECT lot_product_id, lot_product_qty FROM lot_product WHERE lot_id = ? AND product_id = ?");
        $stmt->execute([$lot_id, $barcode_value]);
        $lot_product = $stmt->fetch(PDO::FETCH_ASSOC);

        $current_qty = $lot_product ? $lot_product['lot_product_qty'] : 0;

        // ตรวจสอบสต็อกต้นทางถ้าเป็นการนำออก (warehouse_inout = 2)
        if ($quantity > 0 && $lot['warehouse_inout'] == 2 && $lot['warehouse_from_id']) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(lp.lot_product_remain), 0) as source_qty
                FROM lot_product lp
                JOIN lot l ON lp.lot_id = l.lot_id
                WHERE lp.product_id = ? AND l.warehouse_id = ? AND l.lot_status_id = 2
            ");
            $stmt->execute([$barcode_value, $lot['warehouse_from_id']]);
            $source_qty = $stmt->fetchColumn();

            if ($quantity > $source_qty) {
                throw new Exception("จำนวนที่ต้องการนำเข้า ($quantity) เกินจำนวนสินค้าคงเหลือในคลังต้นทาง (" . $source_qty . ")");
            }
        }

        $new_qty = $current_qty + $quantity;

        if ($is_delete && abs($quantity) > $current_qty) {
            throw new Exception("ไม่สามารถลบได้ เนื่องจากจำนวนที่ต้องการลบ (" . abs($quantity) . ") เกินจำนวนใน Lot (" . $current_qty . ")");
        }

        $current_datetime = date('Y-m-d H:i:s');
        if ($new_qty <= 0) {
            $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_id = ? AND product_id = ?");
            $stmt->execute([$lot_id, $barcode_value]);
        } elseif ($lot_product) {
            $stmt = $pdo->prepare("
                UPDATE lot_product 
                SET lot_product_qty = ?, lot_product_remain = ?, lot_product_capital = ? 
                WHERE lot_product_id = ?
            ");
            $stmt->execute([$new_qty, $new_qty, $final_capital, $lot_product['lot_product_id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO lot_product (lot_id, product_id, lot_product_qty, lot_product_remain, lot_product_capital, lot_product_date, lot_product_status) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$lot_id, $barcode_value, $quantity, $quantity, $final_capital, $current_datetime]);
        }

        $response['success'] = true;
    } elseif ($action === 'delete_product') {
        $lot_product_id = isset($_POST['lot_product_id']) ? (int)$_POST['lot_product_id'] : 0;

        if ($lot_product_id <= 0) {
            throw new Exception('ไม่พบข้อมูลสินค้า');
        }

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
        $lot_product_id = isset($_POST['lot_product_id']) ? (int)$_POST['lot_product_id'] : 0;
        $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 0;
        $capital = isset($_POST['capital']) ? (float)$_POST['capital'] : 0;
        $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
        $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';

        if ($lot_product_id <= 0 || !$product_name || $qty <= 0 || $capital < 0) {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }

        $stmt = $pdo->prepare("
            SELECT l.lot_status_id, lp.product_id 
            FROM lot l
            JOIN lot_product lp ON l.lot_id = lp.lot_id
            WHERE lp.lot_product_id = ?
        ");
        $stmt->execute([$lot_product_id]);
        $lot_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lot_data['lot_status_id'] == 2) {
            throw new Exception('ไม่สามารถแก้ไข Lot ที่ยืนยันแล้วได้');
        }

        $stmt = $pdo->prepare("UPDATE product SET product_name = ?, product_capital = ? WHERE product_id = ?");
        $stmt->execute([$product_name, $capital, $barcode]);

        $stmt = $pdo->prepare("
            UPDATE lot_product 
            SET lot_product_qty = ?, lot_product_remain = ?, lot_product_capital = ? 
            WHERE lot_product_id = ?
        ");
        $stmt->execute([$qty, $qty, $capital, $lot_product_id]);

        $response['success'] = true;
    } elseif ($action === 'confirm_lot') {
        $lot_id = isset($_POST['lot_id']) ? (int)$_POST['lot_id'] : 0;

        if ($lot_id <= 0) {
            throw new Exception('ไม่พบข้อมูล Lot');
        }

        $stmt = $pdo->prepare("SELECT lot_status_id FROM lot WHERE lot_id = ?");
        $stmt->execute([$lot_id]);
        if ($stmt->fetchColumn() == 2) {
            throw new Exception('Lot นี้ยืนยันแล้ว');
        }

        $stmt = $pdo->prepare("
            SELECT lp.product_id, COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name, lp.lot_product_capital 
            FROM lot_product lp
            LEFT JOIN product p ON lp.product_id = p.product_id
            WHERE lp.lot_id = ? AND lp.lot_product_qty > 0
        ");
        $stmt->execute([$lot_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($products) == 0) {
            throw new Exception('ต้องมีสินค้าอย่างน้อย 1 ชิ้นเพื่อยืนยันการรับเข้า');
        }

        $invalid_products = [];
        foreach ($products as $product) {
            if ($product['product_name'] === 'ไม่มีสินค้าในระบบ' || $product['product_name'] === 'ยังไม่ระบุ' || $product['lot_product_capital'] == 0) {
                $invalid_products[] = $product['product_id'];
            }
        }

        if (!empty($invalid_products)) {
            throw new Exception('กรุณากรอกข้อมูลสินค้าให้ครบถ้วน (ชื่อสินค้าและราคาทุน) สำหรับบาร์โค้ด: ' . implode(', ', $invalid_products));
        }

        $stmt = $pdo->prepare("UPDATE lot SET lot_status_id = 2 WHERE lot_id = ?");
        $stmt->execute([$lot_id]);

        $response['success'] = true;
        $response['message'] = 'การยืนยันล็อตสำเร็จ';
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