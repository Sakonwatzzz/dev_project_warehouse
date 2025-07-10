<?php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection(); 

try {
    $productTypeId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : '';
    $search = trim($_GET['search'] ?? '');
    $start_date = trim($_GET['start_date'] ?? '');
    $end_date = trim($_GET['end_date'] ?? '');
    $page = max(1, (int)($_GET['page_num'] ?? 1));
    $records_per_page = 5;

    if (empty($productTypeId)) {
        echo json_encode(['error' => 'กรุณาระบุรหัสประเภทสินค้า']);
        exit;
    }

    // ตรวจสอบรูปแบบวันที่
    if ($start_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        echo json_encode(['error' => 'รูปแบบวันที่เริ่มต้นไม่ถูกต้อง']);
        exit;
    }
    if ($end_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        echo json_encode(['error' => 'รูปแบบวันที่สิ้นสุดไม่ถูกต้อง']);
        exit;
    }
    if ($start_date && $end_date && strtotime($end_date) < strtotime($start_date)) {
        echo json_encode(['error' => 'วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น']);
        exit;
    }

    // สร้างคำสั่ง SQL สำหรับนับจำนวนทั้งหมด
    $count_query = "
        SELECT COUNT(DISTINCT product.product_id) 
        FROM product 
        LEFT JOIN lot_product ON product.product_id = lot_product.product_id
        WHERE product.product_type_id = :product_type_id";
    $count_params = [':product_type_id' => $productTypeId];

    // สร้างคำสั่ง SQL สำหรับดึงข้อมูล
    $query = "
        SELECT 
            product.product_id,
            product.product_name,
            product.product_price1,
            product.product_price2,
            product.product_price3,
            product.product_price4,
            product.product_capital,
            product.product_type_id, 
            product_type.product_type_name,
            (SELECT lot_product_date FROM lot_product 
             WHERE lot_product.product_id = product.product_id 
             ORDER BY lot_product_date DESC LIMIT 1) AS lot_product_date
        FROM product
        INNER JOIN product_type ON product.product_type_id = product_type.product_type_id
        LEFT JOIN lot_product ON product.product_id = lot_product.product_id
        WHERE product.product_type_id = :product_type_id";
    $params = [':product_type_id' => $productTypeId];

    if (!empty($search) || $start_date || $end_date) {
        if (!empty($search)) {
            $query .= " AND (product.product_id LIKE :search OR product.product_name LIKE :search)";
            $count_query .= " AND (product.product_id LIKE :search OR product.product_name LIKE :search)";
            $params[':search'] = "%$search%";
            $count_params[':search'] = "%$search%";
        }
        if ($start_date) {
            $query .= " AND lot_product.lot_product_date >= :start_date";
            $count_query .= " AND lot_product.lot_product_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
            $count_params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND lot_product.lot_product_date <= :end_date";
            $count_query .= " AND lot_product.lot_product_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
            $count_params[':end_date'] = $end_date . ' 23:59:59';
        }
    }

    // นับจำนวนทั้งหมด
    $stmt = $pdo->prepare($count_query);
    foreach ($count_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // ดึงข้อมูลสินค้า
    $query .= " GROUP BY product.product_id ORDER BY product.product_id DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', ($page - 1) * $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // บันทึก Query และ Params สำหรับ Debug
    error_log("Query: $query, Params: " . json_encode($params) . ", Total Records: $total_records");

    ob_start();
?>
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-blue-50 border-b border-blue-100">
                <th class="px-3 py-3 text-left font-semibold text-blue-900">รหัสสินค้า</th>
                <th class="px-3 py-3 text-left font-semibold text-blue-900">ชื่อสินค้า</th>
                <th class="px-3 py-3 text-left font-semibold text-blue-900">ประเภท</th>
                <th class="px-3 py-3 text-right font-semibold text-blue-900">ราคาขาย 1</th>
                <th class="px-3 py-3 text-right font-semibold text-blue-900">ราคาขาย 2</th>
                <th class="px-3 py-3 text-right font-semibold text-blue-900">ราคาขาย 3</th>
                <th class="px-3 py-3 text-right font-semibold text-blue-900">ราคาขาย 4</th>
                <th class="px-3 py-3 text-right font-semibold text-blue-900">ราคาทุน</th>
                <th class="px-3 py-3 text-center font-semibold text-blue-900">วันที่นำเข้า</th>
                <th class="px-3 py-3 text-center font-semibold text-blue-900">การจัดการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td class="px-3 py-4 whitespace-nowrap"><?php echo htmlspecialchars($product['product_id']); ?></td>
                        <td class="px-3 py-4 whitespace-nowrap">
                            <a href="?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product['product_id']); ?>"
                               class="text-blue-600 hover:underline cursor-pointer">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </a>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap"><?php echo htmlspecialchars($product['product_type_name']); ?></td>
                        <td class="px-3 py-4 text-right whitespace-nowrap"><?php echo number_format($product['product_price1'], 2); ?></td>
                        <td class="px-3 py-4 text-right whitespace-nowrap"><?php echo number_format($product['product_price2'], 2); ?></td>
                        <td class="px-3 py-4 text-right whitespace-nowrap"><?php echo number_format($product['product_price3'], 2); ?></td>
                        <td class="px-3 py-4 text-right whitespace-nowrap"><?php echo number_format($product['product_price4'], 2); ?></td>
                        <td class="px-3 py-4 text-right whitespace-nowrap"><?php echo number_format($product['product_capital'], 2); ?></td>
                        <td class="px-3 py-4 text-center whitespace-nowrap"><?php echo !empty($product['lot_product_date']) ? htmlspecialchars(date('Y-m-d', strtotime($product['lot_product_date']))) : '-'; ?></td>
                        <td class="px-3 py-3 text-center">
                            <div class="flex justify-center gap-2">
                                <button onclick="showEditModal(
                                    '<?php echo htmlspecialchars($product['product_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars(addslashes($product['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars($product['product_price1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars($product['product_price2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars($product['product_price3'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars($product['product_price4'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars($product['product_capital'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars($product['product_type_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'
                                )" class="bg-yellow-500 text-white px-3 py-1.5 rounded-lg hover:bg-yellow-600 transition text-sm hover-lift">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" onclick="confirmDelete('<?php echo htmlspecialchars($product['product_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')"
                                    class="bg-red-500 text-white px-3 py-1.5 rounded-lg hover:bg-red-600 transition text-sm hover-lift">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" class="px-6 py-4 text-center text-gray-500">ไม่พบข้อมูลสินค้า</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

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
    error_log("Error in product_typegroup_table.php: " . $e->getMessage());
    echo json_encode(['error' => 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage())]);
}