<?php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection();

try {
    $lot_id = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : 0;
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page_num'] ?? 1));
    $records_per_page = 5;

    if ($lot_id === 0) {
        echo json_encode(['error' => 'กรุณาระบุรหัส Lot']);
        exit;
    }

    // สร้างคำสั่ง SQL สำหรับนับจำนวนทั้งหมด
    $count_query = "
        SELECT COUNT(*) 
        FROM lot_product lp 
        LEFT JOIN lot l ON lp.lot_id = l.lot_id 
        LEFT JOIN product p ON lp.product_id = p.product_id 
        WHERE lp.lot_id = :lot_id";
    $count_params = [':lot_id' => $lot_id];

    // สร้างคำสั่ง SQL สำหรับดึงข้อมูล
    $query = "
        SELECT 
            lp.lot_product_id,
            lp.lot_id,
            l.lot_name,
            lp.product_id,
            p.product_name,
            lp.lot_product_qty,
            lp.lot_product_remain,
            lp.lot_product_capital,
            lp.lot_product_date,
            lp.lot_product_status,
            s.supplier_name
        FROM lot_product lp
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN product p ON lp.product_id = p.product_id
        LEFT JOIN supplier s ON l.supplier_id = s.supplier_id
        WHERE lp.lot_id = :lot_id";
    $params = [':lot_id' => $lot_id];

    if (!empty($search)) {
        $conditions[] = " (p.product_id LIKE :search OR p.product_name LIKE :search)";
        $params[':search'] = "%$search%";
        $count_params[':search'] = "%$search%";
        $query .= " AND " . implode(" AND ", $conditions);
        $count_query .= " AND " . implode(" AND ", $conditions);
    }

    // นับจำนวนทั้งหมด
    $stmt = $pdo->prepare($count_query);
    foreach ($count_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // ดึงข้อมูลรายการรับสินค้า
    $query .= " ORDER BY lp.lot_product_date DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', ($page - 1) * $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $lot_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // บันทึก Query และ Params สำหรับ Debug
    error_log("Query: $query, Params: " . json_encode($params) . ", Total Records: $total_records");

    ob_start();
?>
    <div class="table-responsive">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-blue-50 border-b border-blue-100">
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">รหัสสินค้า</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">ชื่อสินค้า</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">ราคาต้นทุน</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">จำนวนสินค้าเข้า</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">สินค้าคงเหลือ</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">ผู้ส่งมอบ</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">วันที่รับสินค้า</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (count($lot_products) > 0): ?>
                    <?php foreach ($lot_products as $lot_product): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($lot_product['product_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($lot_product['product_name']); ?></td>
                            <td class="px-6 py-4 text-right"><?php echo htmlspecialchars(number_format($lot_product['lot_product_capital'], 2)); ?></td>
                            <td class="px-6 py-4 text-right"><?php echo htmlspecialchars($lot_product['lot_product_qty']); ?></td>
                            <td class="px-6 py-4 text-right"><?php echo htmlspecialchars($lot_product['lot_product_remain']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($lot_product['supplier_name'] ?? 'ไม่มีข้อมูล'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars(date('Y-m-d', strtotime($lot_product['lot_product_date']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                            <div class="flex flex-col items-center justify-center py-6">
                                <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                                <?php if (!empty($search)): ?>
                                    <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                        <a href="?page=lot_productlist&lot_id=<?php echo urlencode($lot_id); ?>" class="text-blue-600 hover:underline focus:outline-none">ดูข้อมูลทั้งหมด</a>
                                    </p>
                                <?php else: ?>
                                    <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลการรับสินค้าใน Lot นี้</p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
 
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex justify-between items-center">
            <div class="text-sm text-gray-700">
                หน้า <?php echo $page; ?> จาก <?php echo $total_pages; ?>
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                    <button onclick="goToPage(<?php echo $page - 1; ?>)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">ก่อนหน้า</button>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <button onclick="goToPage(<?php echo $page + 1; ?>)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">ถัดไป</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php
    echo ob_get_clean();
} catch (Exception $e) {
    error_log("Error in lot_productlist_table.php: " . $e->getMessage());
    echo json_encode(['error' => 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage())]);
}
?>