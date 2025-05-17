<?php
require_once __DIR__ . '../../../../../../config/database_server.php';

// รับค่า lot_id จาก request
$lot_id = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : 0;
$warehouse_id = isset($_GET['warehouse_id']) ? $_GET['warehouse_id'] : null;

// ตรวจสอบว่า lot_id มีค่าหรือไม่
if (!$lot_id) {
    die(json_encode(['success' => false, 'error' => 'lot_id is missing']));
}

try {
    // ดึงข้อมูล lot เพื่อตรวจสอบสถานะ
    $query_lot = "SELECT
                    lot.lot_id, 
                    lot.lot_status_id,
                    lot.warehouse_from_id
                FROM lot lot
                WHERE lot.lot_id = :lot_id AND lot.warehouse_inout = 2";
    $stmt = $pdo->prepare($query_lot);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot) {
        die(json_encode(['success' => false, 'error' => 'ไม่พบข้อมูล Lot ที่ระบุ']));
    }

    // ตรวจสอบว่า lot ยืนยันแล้วหรือไม่
    $is_confirmed = $lot['lot_status_id'] == 2;

    // ดึงข้อมูลสินค้าใน lot พร้อมยอดคงเหลือจาก warehouse
    $query_products = "
    SELECT
        lp.lot_product_id, 
        lp.lot_product_qty, 
        lp.lot_product_remain, 
        p.product_id,
        p.product_name,
        p.product_capital,
        COALESCE((
            SELECT SUM(lp2.lot_product_remain)
            FROM lot_product lp2
            JOIN lot l2 ON lp2.lot_id = l2.lot_id
            WHERE lp2.product_id = p.product_id 
            AND l2.warehouse_id = :warehouse_from_id 
            AND l2.lot_status_id = 2
        ), 0) as source_qty_remaining
    FROM lot_product lp
    LEFT JOIN product p ON lp.product_id = p.product_id
    WHERE lp.lot_id = :lot_id AND lp.lot_product_qty > 0
    ORDER BY lp.lot_product_id DESC";    
    $stmt = $pdo->prepare($query_products);
    $stmt->execute(['lot_id' => $lot_id, 'warehouse_from_id' => $lot['warehouse_from_id']]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // สร้าง HTML สำหรับตาราง
    ob_start();
    
    if (count($products) == 0) {
        echo '<tr><td colspan="' . ($is_confirmed ? 7 : 8) . '" class="text-center py-4 text-gray-500">ไม่พบรายการสินค้า</td></tr>';
    } else {
        $total_qty = 0;
        $total_value = 0;
        foreach ($products as $product) {
            $total = $product['lot_product_qty'] * $product['product_capital'];
            $total_qty += $product['lot_product_qty'];
            $total_value += $total;
    ?>
            <tr class="hover:bg-gray-50" data-product-id="<?php echo htmlspecialchars($product['product_id']); ?>">
                <td class="px-4 py-3"><?php echo htmlspecialchars($product['product_id']); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($product['product_name']); ?></td>
                <td class="px-4 py-3 text-center"><?php echo number_format($product['source_qty_remaining'], 0); ?></td>
                <td class="px-4 py-3 text-center"><?php echo number_format($product['lot_product_qty'], 0); ?></td>
                <td class="px-4 py-3 text-right"><?php echo number_format($product['product_capital'], 2); ?></td>
                <td class="px-4 py-3 text-right"><?php echo number_format($total, 2); ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full <?php echo $is_confirmed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                        <i class="fas <?php echo $is_confirmed ? 'fa-check mr-1' : 'fa-clock mr-1'; ?>"></i>
                        <?php echo $is_confirmed ? 'ยืนยันแล้ว' : 'รอดำเนินการ'; ?>
                    </span>
                </td>
                <?php if (!$is_confirmed): ?>
                    <td class="px-4 py-3 text-center">
                        <button class="btn bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded mr-1 edit-product"
                            data-id="<?php echo $product['lot_product_id']; ?>"
                            data-qty="<?php echo $product['lot_product_qty']; ?>"
                            data-source-qty="<?php echo $product['source_qty_remaining']; ?>">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded delete-product"
                            data-id="<?php echo $product['lot_product_id']; ?>">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                <?php endif; ?>
            </tr>
    <?php
        }
    ?>
        <tr class="total-row border-t-2 border-gray-200">
            <td colspan="3" class="px-4 py-3 text-right font-medium">ยอดรวม:</td>
            <td class="px-4 py-3 text-center font-bold"><?php echo number_format($total_qty, 0); ?></td>
            <td class="px-4 py-3"></td>
            <td class="px-4 py-3 text-right font-bold"><?php echo number_format($total_value, 2); ?> บาท</td>
            <td colspan="<?php echo $is_confirmed ? '1' : '2'; ?>" class="px-4 py-3"></td>
        </tr>
    <?php
    }
    
    $table_html = ob_get_clean();
    
    // ส่งข้อมูลกลับในรูปแบบ JSON
    echo json_encode([
        'success' => true,
        'tableHtml' => $table_html,
        'isConfirmed' => $is_confirmed,
        'totalQty' => isset($total_qty) ? $total_qty : 0,
        'totalValue' => isset($total_value) ? $total_value : 0
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
