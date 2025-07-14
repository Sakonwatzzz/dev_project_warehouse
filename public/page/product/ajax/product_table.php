<?php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

$pdo = Database::getInstance()->getConnection(); 

try {
    $incomplete_mode = isset($_GET['mode']) && $_GET['mode'] === 'incomplete';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    $selected_types = isset($_GET['product_type']) && !empty($_GET['product_type'])
        ? array_filter(array_map('intval', explode(',', $_GET['product_type'])))
        : [];
    $selected_brands = isset($_GET['product_brand']) && !empty($_GET['product_brand'])
        ? array_filter(array_map('intval', explode(',', $_GET['product_brand'])))
        : [];

    $records_per_page = 10;
    $page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
    $offset = ($page - 1) * $records_per_page;

    $where_conditions = [];
    $bind_params = [];

    if ($incomplete_mode) {
        $where_conditions[] = "(
            product.product_name = '' OR product.product_name IS NULL OR 
            product.product_price1 IS NULL OR product.product_price1 = 0 OR 
            product.product_price2 IS NULL OR product.product_price2 = 0 OR 
            product.product_price3 IS NULL OR product.product_price3 = 0 OR 
            product.product_price4 IS NULL OR product.product_price4 = 0 OR 
            product.product_capital IS NULL OR product.product_capital = 0 OR 
            product.product_type_id IS NULL OR 
            product.product_brand_id IS NULL
        )";
    }

    if ($search) {
        $where_conditions[] = "(product.product_name LIKE ? OR product.product_id LIKE ?)";
        $bind_params[] = "%$search%";
        $bind_params[] = "%$search%";
    }

    if (!empty($selected_types)) {
        $type_placeholders = implode(',', array_fill(0, count($selected_types), '?'));
        $where_conditions[] = "product.product_type_id IN ($type_placeholders)";
        $bind_params = array_merge($bind_params, $selected_types);
    }

    if (!empty($selected_brands)) {
        $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
        $where_conditions[] = "product.product_brand_id IN ($brand_placeholders)";
        $bind_params = array_merge($bind_params, $selected_brands);
    }

    if ($start_date) {
        $where_conditions[] = "lot_product.lot_product_date >= ?";
        $bind_params[] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $where_conditions[] = "lot_product.lot_product_date <= ?";
        $bind_params[] = $end_date . ' 23:59:59';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    $query = "SELECT 
        product.product_id, 
        product.product_name, 
        product.product_price1, 
        product.product_price2, 
        product.product_price3, 
        product.product_price4, 
        product.product_capital, 
        product.product_type_id, 
        product.product_brand_id,
        COALESCE(product_brand.product_brand_name, 'ไม่มีข้อมูล') AS product_brand_name, 
        COALESCE(product_type.product_type_name, 'ไม่มีข้อมูล') AS product_type_name,
        (SELECT lot_product_date FROM lot_product 
         WHERE lot_product.product_id = product.product_id 
         ORDER BY lot_product_date DESC LIMIT 1) AS lot_product_date,
        MAX(COALESCE(personnel.personnel_name, 'ไม่มีข้อมูล')) AS personnel_name
    FROM product
    LEFT JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id
    LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id
    LEFT JOIN lot_product ON lot_product.product_id = product.product_id
    LEFT JOIN lot ON lot.lot_id = lot_product.lot_id
    LEFT JOIN personnel ON lot.personnel_id = personnel.personnel_id
    $where_clause
    GROUP BY product.product_id
    LIMIT ?, ?";

    $stmt = $pdo->prepare($query);
    foreach ($bind_params as $i => $param) {
        $stmt->bindValue($i + 1, $param, is_string($param) ? PDO::PARAM_STR : PDO::PARAM_INT);
    }
    $stmt->bindValue(count($bind_params) + 1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(count($bind_params) + 2, $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $resultsproduct = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count_query = "SELECT COUNT(DISTINCT product.product_id) FROM product 
                    LEFT JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id
                    LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id
                    LEFT JOIN lot_product ON lot_product.product_id = product.product_id
                    LEFT JOIN lot ON lot.lot_id = lot_product.lot_id
                    LEFT JOIN personnel ON lot.personnel_id = personnel.personnel_id
                    $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    foreach ($bind_params as $i => $param) {
        $count_stmt->bindValue($i + 1, $param, is_string($param) ? PDO::PARAM_STR : PDO::PARAM_INT);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    ob_start();
?>
    <div class="table-responsive">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-blue-50 border-b border-blue-100">
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">รหัสสินค้า</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">ชื่อสินค้า</th>
                    <th class="px-3 py-3 text-right font-semibold text-blue-900">ราคาทุน</th>
                    <th class="px-3 py-3 text-right font-semibold text-blue-900">ราคาขาย</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">ประเภท</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">แบรนด์</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">วันที่นำเข้า</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">พนักงาน</th>
                    <th class="px-3 py-3 text-center font-semibold text-blue-900">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($resultsproduct) > 0): ?>
                    <?php foreach ($resultsproduct as $product_list): ?>
                        <?php
                        $is_incomplete = empty($product_list['product_name']) ||
                            ($product_list['product_price1'] === null || $product_list['product_price1'] == 0) ||
                            ($product_list['product_price2'] === null || $product_list['product_price2'] == 0) ||
                            ($product_list['product_price3'] === null || $product_list['product_price3'] == 0) ||
                            ($product_list['product_price4'] === null || $product_list['product_price4'] == 0) ||
                            ($product_list['product_capital'] === null || $product_list['product_capital'] == 0) ||
                            empty($product_list['product_type_id']) ||
                            empty($product_list['product_brand_id']);
                        ?>
                        <tr class="border-b border-gray-100 hover:bg-blue-50/50 transition <?php echo $is_incomplete ? 'bg-red-50' : ''; ?>">
                            <td class="px-3 py-3">
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm font-medium"><?php echo htmlspecialchars($product_list['product_id']); ?></span>
                            </td>
                            <td class="px-3 py-3">
                                <a href="?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product_list['product_id']); ?>"
                                    class="font-medium <?php echo empty($product_list['product_name']) ? 'text-red-600' : 'text-gray-900'; ?> hover:underline decoration-blue-200 hover:decoration-blue-400 hover:text-blue-500">
                                    <?php echo empty($product_list['product_name']) ? 'ไม่มีข้อมูล' : htmlspecialchars($product_list['product_name']); ?>
                                </a>
                            </td>
                            <td class="px-3 py-3 text-right <?php echo empty($product_list['product_capital']) ? 'text-red-600' : 'text-gray-900'; ?> font-medium">
                                <?php echo empty($product_list['product_capital']) ? '-' : number_format($product_list['product_capital'], 2); ?>
                            </td>
                            <td class="px-3 py-3 text-right <?php echo empty($product_list['product_price4']) ? 'text-red-600' : 'text-green-600'; ?> font-semibold">
                                <?php echo empty($product_list['product_price4']) ? '-' : number_format($product_list['product_price4'], 2); ?>
                            </td>
                            <td class="px-3 py-3">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs <?php echo empty($product_list['product_type_id']) ? 'bg-red-100 text-red-800' : ''; ?>">
                                    <?php echo !empty($product_list['product_type_name']) ? htmlspecialchars($product_list['product_type_name']) : '-'; ?>
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs <?php echo empty($product_list['product_brand_id']) ? 'bg-red-100 text-red-800' : ''; ?>">
                                    <?php echo !empty($product_list['product_brand_name']) ? htmlspecialchars($product_list['product_brand_name']) : '-'; ?>
                                </span>
                            </td>
                            <td class="px-3 py-3 text-gray-600">
                                <?php echo !empty($product_list['lot_product_date']) ? htmlspecialchars(date('Y-m-d', strtotime($product_list['lot_product_date']))) : '-'; ?>
                            </td>
                            <td class="px-3 py-3 text-gray-600">
                                <?php echo !empty($product_list['personnel_name']) ? htmlspecialchars($product_list['personnel_name']) : '-'; ?>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <div class="flex justify-center gap-2">
                                    <a href="?page=product_update&id=<?php echo htmlspecialchars($product_list['product_id']); ?>"
                                        class="bg-yellow-500 text-white px-3 py-1.5 rounded-lg hover:bg-yellow-600 transition text-sm hover-lift">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteProduct('<?php echo htmlspecialchars($product_list['product_id']); ?>')"
                                        class="bg-red-500 text-white px-3 py-1.5 rounded-lg hover:bg-red-600 transition text-sm hover-lift">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-3 py-3 text-center text-gray-600">
                            <?php echo $incomplete_mode ? 'ไม่พบสินค้าที่มีข้อมูลไม่ครบ' : 'ไม่พบข้อมูลสินค้า'; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="flex flex-col sm:flex-row items-center justify-between mt-6 gap-3 text-sm">
        <div class="text-gray-600">
            แสดง <?php echo ($offset + 1); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> จาก <?php echo $total_records; ?> รายการ
        </div>
        <div class="flex items-center gap-2">
            <button class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                <?php echo $page <= 1 ? 'disabled' : ''; ?>
                onclick="goToPage(<?php echo $page - 1; ?>)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            if ($start_page > 1): ?>
                <button class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition" onclick="goToPage(1)">1</button>
                <?php if ($start_page > 2): ?>
                    <span class="px-2 text-gray-500">...</span>
                <?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <button class="px-3 py-1.5 rounded-lg <?php echo $i == $page ? 'bg-blue-500 text-white' : 'bg-blue-100 text-blue-700 hover:bg-blue-200'; ?> transition"
                    onclick="goToPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
            <?php endfor; ?>
            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span class="px-2 text-gray-500">...</span>
                <?php endif; ?>
                <button class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition" onclick="goToPage(<?php echo $total_pages; ?>)"><?php echo $total_pages; ?></button>
            <?php endif; ?>
            <button class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition <?php echo $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                <?php echo $page >= $total_pages ? 'disabled' : ''; ?>
                onclick="goToPage(<?php echo $page + 1; ?>)">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
<?php
    echo ob_get_clean();
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ข้อผิดพลาดฐานข้อมูล: ' . htmlspecialchars($e->getMessage())]);
    exit;
}
?>