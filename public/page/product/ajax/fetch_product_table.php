<?php
require_once __DIR__ . '../../../../config/database_server.php';

header('Content-Type: application/json');

try {
    // Sanitize and validate input filters
    $search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
    $selected_types = isset($_GET['product_type']) && !empty($_GET['product_type'])
        ? array_filter(array_map('intval', explode(',', $_GET['product_type'])))
        : [];
    $selected_brands = isset($_GET['product_brand']) && !empty($_GET['product_brand'])
        ? array_filter(array_map('intval', explode(',', $_GET['product_brand'])))
        : [];
    $incomplete_mode = isset($_GET['mode']) && $_GET['mode'] === 'incomplete';
    $page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
    $records_per_page = 10;
    $offset = ($page - 1) * $records_per_page;

    // Build WHERE condition
    $where_conditions = [];
    $bind_params = [];

    if ($incomplete_mode) {
        $where_conditions[] = "(
            product.product_name = '' OR product.product_name IS NULL OR 
            product.product_price1 = '' OR product.product_price1 IS NULL OR 
            product.product_price2 = '' OR product.product_price2 IS NULL OR 
            product.product_price3 = '' OR product.product_price3 IS NULL OR 
            product.product_price4 = '' OR product.product_price4 IS NULL OR 
            product.product_capital = '' OR product.product_capital IS NULL OR 
            product.product_type_id = '' OR product.product_type_id IS NULL OR 
            product.product_brand_id = '' OR product.product_brand_id IS NULL
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

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Main query
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
        COALESCE(product_type.product_type_name, 'ไม่มีข้อมูล') AS product_type_name
    FROM product
    LEFT JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id
    LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id
    $where_clause
    LIMIT ?, ?";

    $stmt = $pdo->prepare($query);
    foreach ($bind_params as $i => $param) {
        $stmt->bindValue($i + 1, $param, is_string($param) ? PDO::PARAM_STR : PDO::PARAM_INT);
    }
    $stmt->bindValue(count($bind_params) + 1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(count($bind_params) + 2, $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $resultsproduct = $stmt;

    // Count total records for pagination
    $count_query = "SELECT COUNT(*) FROM product $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    foreach ($bind_params as $i => $param) {
        $count_stmt->bindValue($i + 1, $param, is_string($param) ? PDO::PARAM_STR : PDO::PARAM_INT);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // Start output buffering
    ob_start();
    ?>
    <div class="table-responsive">
        <table class="w-full border-collapse" id="dataTable">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-3 bg-blue-400 sm:px-6 py-2 sm:py-3 border text-left text-gray-700 font-semibold text-xs sm:text-sm">รหัสสินค้า</th>
                    <th class="px-3 sm:px-6 py-2 sm:py-3 border text-left text-gray-700 font-semibold text-xs sm:text-sm">ชื่อสินค้า</th>
                    <th class="px-3 sm:px-6 py-2 sm:py-3 border text-left text-gray-700 font-semibold text-xs sm:text-sm">ราคาทุน</th>
                    <th class="px-3 sm:px-6 py-2 sm:py-3 border text-left text-gray-700 font-semibold text-xs sm:text-sm">ราคาขาย</th>
                    <th class="px-3 sm:px-6 py-2 sm:py-3 border text-left text-gray-700 font-semibold text-xs sm:text-sm">ประเภทสินค้า</th>
                    <th class="px-3 sm:px-6 py-2 sm:py-3 border text-left text-gray-700 font-semibold text-xs sm:text-sm">แบรนด์สินค้า</th>
                    <th class="px-3 sm:px-6 py-2 sm:py-3 border text-left text-gray-700 font-semibold text-xs sm:text-sm">สถานะข้อมูล</th>
                    <th class="px-3 sm:px-6 py-2 sm:py-3 border text-center text-gray-700 font-semibold text-xs sm:text-sm">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultsproduct->rowCount() > 0): ?>
                    <?php foreach ($resultsproduct as $product_list): ?>
                        <?php
                        $is_incomplete = empty($product_list['product_name']) ||
                            ($product_list['product_price1'] === null || $product_list['product_price1'] === '' || $product_list['product_price1'] == 0) ||
                            ($product_list['product_price2'] === null || $product_list['product_price2'] === '' || $product_list['product_price2'] == 0) ||
                            ($product_list['product_price3'] === null || $product_list['product_price3'] === '' || $product_list['product_price3'] == 0) ||
                            ($product_list['product_price4'] === null || $product_list['product_price4'] === '' || $product_list['product_price4'] == 0) ||
                            ($product_list['product_capital'] === null || $product_list['product_capital'] === '' || $product_list['product_capital'] == 0) ||
                            empty($product_list['product_type_id']) ||
                            empty($product_list['product_brand_id']);
                        ?>
                        <tr class="hover:bg-gray-100 <?php echo $is_incomplete ? 'bg-red-50' : ''; ?>">
                            <td class="px-3 bg-yellow-400 bg-opacity-50 sm:px-6 py-2 border border-gray-300 text-xs sm:text-sm"><?php echo htmlspecialchars($product_list['product_id']); ?></td>
                            <td class="px-3 sm:px-6 py-2 border text-blue-600 hover:underline cursor-pointer text-xs sm:text-sm <?php echo empty($product_list['product_name']) ? 'text-red-600 font-bold' : ''; ?>"
                                onclick="window.location.href='?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product_list['product_id']); ?>'">
                                <?php echo empty($product_list['product_name']) ? 'ไม่มีข้อมูล' : htmlspecialchars($product_list['product_name']); ?>
                            </td>
                            <td class="px-3 sm:px-6 py-2 border text-right text-xs sm:text-sm <?php echo empty($product_list['product_capital']) ? 'text-red-600 font-bold' : ''; ?>">
                                <?php echo empty($product_list['product_capital']) ? 'ไม่มีข้อมูล' : htmlspecialchars($product_list['product_capital']); ?>
                            </td>
                            <td class="px-3 sm:px-6 py-2 border text-right text-xs sm:text-sm <?php echo empty($product_list['product_price4']) ? 'text-red-600 font-bold' : ''; ?>">
                                <?php echo empty($product_list['product_price4']) ? 'ไม่มีข้อมูล' : htmlspecialchars($product_list['product_price4']); ?>
                            </td>
                            <td class="px-3 sm:px-6 py-2 border text-xs sm:text-sm <?php echo empty($product_list['product_type_id']) ? 'text-red-600 font-bold' : ''; ?>">
                                <?php echo !empty($product_list['product_type_name']) ? htmlspecialchars($product_list['product_type_name']) : 'ไม่มีข้อมูล'; ?>
                            </td>
                            <td class="px-3 sm:px-6 py-2 border text-xs sm:text-sm <?php echo empty($product_list['product_brand_id']) ? 'text-red-600 font-bold' : ''; ?>">
                                <?php echo !empty($product_list['product_brand_name']) ? htmlspecialchars($product_list['product_brand_name']) : 'ไม่มีข้อมูล'; ?>
                            </td>
                            <td class="px-3 sm:px-6 py-2 border text-center text-xs sm:text-sm">
                                <?php if ($is_incomplete): ?>
                                    <span class="text-red-600 font-bold">N</span>
                                <?php else: ?>
                                    <span class="text-green-600 font-bold">OK</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 sm:px-6 py-2 border text-center">
                                <div class="flex flex-col sm:flex-row justify-center space-y-2 sm:space-y-0 sm:space-x-2">
                                    <a href="?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product_list['product_id']); ?>"
                                        class="bg-blue-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-blue-600 transition flex items-center text-xs sm:text-sm">
                                        <i class="fas fa-eye mr-1"></i>ดู
                                    </a>
                                    <a href="?page=product_update&id=<?php echo htmlspecialchars($product_list['product_id']); ?>"
                                        class="bg-yellow-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-xs sm:text-sm">
                                        <i class="fas fa-edit mr-1"></i>แก้ไข
                                    </a>
                                    <a href="?page=product_list&id=<?php echo htmlspecialchars($product_list['product_id']); ?><?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>"
                                        onclick="return confirm('กรุณาตรวจสอบข้อมูลก่อนลบ ถ้าคุณแน่ใจแล้วให้กดลบ')"
                                        class="bg-red-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-red-600 transition flex items-center text-xs sm:text-sm">
                                        <i class="fas fa-trash-alt mr-1"></i>ลบ
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-gray-600 text-sm sm:text-base">
                            <?php echo $incomplete_mode ? 'ไม่พบสินค้าที่มีข้อมูลไม่ครบ' : 'ไม่พบข้อมูลสินค้า'; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div class="pagination mt-5 flex flex-wrap justify-center items-center gap-2">
        <?php
        $query_params = [];
        $query_params['page'] = 'product_list';
        if ($incomplete_mode) {
            $query_params['mode'] = 'incomplete';
        }
        if (!empty($selected_types)) {
            $query_params['product_type'] = implode(',', $selected_types);
        }
        if (!empty($selected_brands)) {
            $query_params['product_brand'] = implode(',', $selected_brands);
        }
        if ($search) {
            $query_params['search'] = $search;
        }
        ?>
        <?php if ($total_pages > 1): ?>
            <!-- Previous Button -->
            <?php if ($page > 1): ?>
                <?php $query_params['page_num'] = $page - 1; ?>
                <a href="?<?php echo http_build_query($query_params); ?>"
                    class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                    « ก่อนหน้า
                </a>
            <?php endif; ?>
            <!-- Page Numbers -->
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            if ($start_page > 1): ?>
                <?php $query_params['page_num'] = 1; ?>
                <a href="?<?php echo http_build_query($query_params); ?>"
                    class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                    1
                </a>
                <?php if ($start_page > 2): ?>
                    <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                <?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php $query_params['page_num'] = $i; ?>
                <a href="?<?php echo http_build_query($query_params); ?>"
                    class="px-3 py-2 rounded-md transition-colors text-sm md:text-base <?php echo $i == $page ? 'bg-gray-800 text-white' : 'bg-blue-100 text-black hover:bg-gray-300'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                <?php endif; ?>
                <?php $query_params['page_num'] = $total_pages; ?>
                <a href="?<?php echo http_build_query($query_params); ?>"
                    class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                    <?php echo $total_pages; ?>
                </a>
            <?php endif; ?>
            <!-- Next Button -->
            <?php if ($page < $total_pages): ?>
                <?php $query_params['page_num'] = $page + 1; ?>
                <a href="?<?php echo http_build_query($query_params); ?>"
                    class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                    ถัดไป »
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    $table_html = ob_get_clean();
    echo json_encode(['success' => true, 'tableHtml' => $table_html]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการโหลดข้อมูลตาราง']);
}
?>