<?php
require_once __DIR__ . '../../../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

date_default_timezone_set('Asia/Bangkok');

try {
    $lot_id = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : 0;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $records_per_page = 5;
    $page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
    $offset = ($page - 1) * $records_per_page;

    $where_conditions = [];
    $bind_params = [];
    if ($lot_id) {
        $where_conditions[] = "lp.lot_id = ?";
        $bind_params[] = $lot_id;
    }
    if (!empty($search)) {
        $where_conditions[] = "(lp.lot_product_id LIKE ? OR p.product_name LIKE ?)";
        $bind_params = array_merge($bind_params, ["%$search%", "%$search%"]);
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

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
            per.personnel_name
        FROM lot_product lp
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN product p ON lp.product_id = p.product_id
        LEFT JOIN personnel per ON l.personnel_id = per.personnel_id
        $where_clause
        ORDER BY lp.lot_product_date DESC
        LIMIT ?, ?";

    $stmt = $pdo->prepare($query);
    foreach ($bind_params as $i => $param) {
        $stmt->bindValue($i + 1, $param, is_string($param) ? PDO::PARAM_STR : PDO::PARAM_INT);
    }
    $stmt->bindValue(count($bind_params) + 1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(count($bind_params) + 2, $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $lot_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count_query = "SELECT COUNT(*) FROM lot_product lp LEFT JOIN product p ON lp.product_id = p.product_id $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    foreach ($bind_params as $i => $param) {
        $count_stmt->bindValue($i + 1, $param, is_string($param) ? PDO::PARAM_STR : PDO::PARAM_INT);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_records / $records_per_page));

    ob_start();
?>
    <div class="overflow-x-auto table-responsive">
        <table class="w-full table-auto">
            <thead>
                <tr class="bg-gray-50 text-gray-700 text-sm">
                    <th class="px-6 py-3 text-left font-semibold">รหัสสินค้า</th>
                    <th class="px-6 py-3 text-left font-semibold">ชื่อสินค้า</th>
                    <th class="px-6 py-3 text-right font-semibold">ราคาต้นทุน</th>
                    <th class="px-6 py-3 text-right font-semibold">จำนวนสินค้าเข้า</th>
                    <th class="px-6 py-3 text-right font-semibold">สินค้าคงเหลือ</th>
                    <th class="px-6 py-3 text-left font-semibold">พนักงาน</th>
                    <th class="px-6 py-3 text-left font-semibold">วันที่รับสินค้า</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (count($lot_products) > 0): ?>
                    <?php foreach ($lot_products as $lot_product): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($lot_product['product_id']); ?></td>
                            <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($lot_product['product_name']); ?></td>
                            <td class="px-6 py-3 border-t text-right"><?php echo htmlspecialchars($lot_product['lot_product_capital']); ?></td>
                            <td class="px-6 py-3 border-t text-right"><?php echo htmlspecialchars($lot_product['lot_product_qty']); ?></td>
                            <td class="px-6 py-3 border-t text-right"><?php echo htmlspecialchars($lot_product['lot_product_remain']); ?></td>
                            <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($lot_product['personnel_name'] ?? 'ไม่มีข้อมูล'); ?></td>
                            <td class="px-6 py-3 border-t"><?php echo htmlspecialchars($lot_product['lot_product_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            <div class="flex flex-col items-center justify-center py-6">
                                <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                                <?php if (!empty($search)): ?>
                                    <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                        <a href="?page=lot_productlist&lot_id=<?php echo urlencode($lot_id); ?>" class="text-indigo-600 hover:underline">ดูข้อมูลทั้งหมด</a>
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
    <div class="flex justify-center mt-6 space-x-2">
        <?php if ($total_pages > 1): ?>
            <?php if ($page > 1): ?>
                <button onclick="goToPage(<?php echo $page - 1; ?>)"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">« ก่อนหน้า</button>
            <?php endif; ?>
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            if ($start_page > 1): ?>
                <button onclick="goToPage(1)" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">1</button>
                <?php if ($start_page > 2): ?>
                    <span class="px-4 py-2 text-gray-500">...</span>
                <?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <button onclick="goToPage(<?php echo $i; ?>)"
                    class="px-4 py-2 rounded-md transition <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
                    <?php echo $i; ?>
                </button>
            <?php endfor; ?>
            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span class="px-4 py-2 text-gray-500">...</span>
                <?php endif; ?>
                <button onclick="goToPage(<?php echo $total_pages; ?>)"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition"><?php echo $total_pages; ?></button>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
                <button onclick="goToPage(<?php echo $page + 1; ?>)"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md transition">ถัดไป »</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function goToPage(pageNum) {
            const urlParams = new URLSearchParams();
            urlParams.set('page', 'lot_productlist');
            urlParams.set('lot_id', '<?php echo urlencode($lot_id); ?>');
            urlParams.set('page_num', pageNum);
            const searchValue = document.getElementById('searchInput').value.trim();
            if (searchValue) {
                urlParams.set('search', searchValue);
            }
            fetch(`/page/lot/ajax/lot_product_table.php?${urlParams.toString()}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.text();
                })
                .then(data => {
                    try {
                        const json = JSON.parse(data);
                        showNotification(json.error, 'bg-red-500');
                    } catch (e) {
                        document.querySelector('.bg-white.rounded-lg.shadow-md.overflow-hidden').innerHTML = data;
                        history.pushState({}, '', `?${urlParams.toString()}`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('เกิดข้อผิดพลาดในการโหลดข้อมูล', 'bg-red-500');
                });
        }
    </script>
<?php
    echo ob_get_clean();
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ข้อผิดพลาดฐานข้อมูล: ' . htmlspecialchars($e->getMessage())]);
    exit;
}
?>