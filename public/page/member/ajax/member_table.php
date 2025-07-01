<?php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    $records_per_page = 5;
    $page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
    $offset = ($page - 1) * $records_per_page;

    $where_conditions = [];
    $bind_params = [];

    if (!empty($search)) {
        $where_conditions[] = "(m.member_id LIKE ? OR m.member_name LIKE ? OR m.member_phone LIKE ?)";
        $bind_params = array_merge($bind_params, ["%$search%", "%$search%", "%$search%"]);
    }

    if ($start_date) {
        $where_conditions[] = "m.member_register_date >= ?";
        $bind_params[] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $where_conditions[] = "m.member_register_date <= ?";
        $bind_params[] = $end_date . ' 23:59:59';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    $query = "
        SELECT 
            m.member_id,
            m.member_name,
            m.member_address,
            m.member_phone,
            m.member_register_date,
            m.member_type_id,
            mt.member_type_name,
            m.personnel_id,
            p.personnel_name
        FROM member m
        INNER JOIN member_type mt ON m.member_type_id = mt.member_type_id
        INNER JOIN personnel p ON m.personnel_id = p.personnel_id
        $where_clause
        ORDER BY m.member_id DESC
        LIMIT ?, ?";

    $stmt = $pdo->prepare($query);
    foreach ($bind_params as $i => $param) {
        $stmt->bindValue($i + 1, $param, is_string($param) ? PDO::PARAM_STR : PDO::PARAM_INT);
    }
    $stmt->bindValue(count($bind_params) + 1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(count($bind_params) + 2, $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count_query = "SELECT COUNT(*) FROM member m $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    foreach ($bind_params as $i => $param) {
        $count_stmt->bindValue($i + 1, $param, is_string($param) ? PDO::PARAM_STR : PDO::PARAM_INT);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_records / $records_per_page));

    ob_start();
?>
    <div class="table-responsive">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-blue-50 border-b border-blue-100">
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">รหัสสมาชิก</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">ชื่อ-นามสกุล</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">ที่อยู่</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">เบอร์โทรศัพท์</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">ประเภทสมาชิก</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">วันที่และเวลา</th>
                    <th class="px-3 py-3 text-left font-semibold text-blue-900">พนักงาน</th>
                    <th class="px-3 py-3 text-center font-semibold text-blue-900">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($members) > 0): ?>
                    <?php foreach ($members as $member): ?>
                        <tr class="border-b border-gray-100 hover:bg-blue-50/50 transition">
                            <td class="px-3 py-3">
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm font-medium"><?php echo htmlspecialchars($member['member_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td class="px-3 py-3">
                                <a href="?page=member_detail&tabel=member&id=<?php echo htmlspecialchars($member['member_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                    class="text-gray-900 font-medium hover:underline decoration-blue-200 hover:decoration-blue-400 hover:text-blue-500">
                                    <?php echo htmlspecialchars($member['member_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td class="px-3 py-3 text-gray-600">
                                <?php
                                $address = mb_substr($member['member_address'], 0, 20) . '...';
                                echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
                                ?>
                            </td>
                            <td class="px-3 py-3 text-gray-600"><?php echo htmlspecialchars($member['member_phone'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-3 py-3">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                                    <?php echo htmlspecialchars($member['member_type_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="px-3 py-3 text-gray-600">
                                <?php echo htmlspecialchars(date('Y-m-d', strtotime($member['member_register_date'])), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="px-3 py-3 text-gray-600"><?php echo htmlspecialchars($member['personnel_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-3 py-3 text-center">
                                <div class="flex justify-center gap-2">
                                    <button onclick="showEditModal(
                                        '<?php echo htmlspecialchars($member['member_id'], ENT_QUOTES, 'UTF-8'); ?>',
                                        '<?php echo htmlspecialchars(addslashes($member['member_name']), ENT_QUOTES, 'UTF-8'); ?>',
                                        '<?php echo htmlspecialchars(addslashes($member['member_address']), ENT_QUOTES, 'UTF-8'); ?>',
                                        '<?php echo htmlspecialchars($member['member_phone'], ENT_QUOTES, 'UTF-8'); ?>',
                                        '<?php echo htmlspecialchars($member['member_type_id'], ENT_QUOTES, 'UTF-8'); ?>',
                                        '<?php echo htmlspecialchars($member['personnel_id'], ENT_QUOTES, 'UTF-8'); ?>'
                                    )" class="bg-yellow-500 text-white px-3 py-1.5 rounded-lg hover:bg-yellow-600 transition text-sm hover-lift">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" onclick="confirmDelete('<?php echo htmlspecialchars($member['member_id'], ENT_QUOTES, 'UTF-8'); ?>')"
                                        class="bg-red-500 text-white px-3 py-1.5 rounded-lg hover:bg-red-600 transition text-sm hover-lift">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-3 py-3 text-center text-gray-600">
                            <div class="flex flex-col items-center justify-center py-6">
                                <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                <p class="text-lg font-medium">ไม่พบข้อมูลที่ค้นหา</p>
                                <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                                    <p class="text-gray-500 mt-1">ลองค้นหาด้วยคำค้นหาอื่น หรือ
                                        <a href="?page=member_list" class="text-blue-600 hover:underline">ดูข้อมูลทั้งหมด</a>
                                    </p>
                                <?php else: ?>
                                    <p class="text-gray-500 mt-1">ยังไม่มีข้อมูลสมาชิกในระบบ</p>
                                <?php endif; ?>
                            </div>
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
    <script>
        document.getElementById('recordCount').textContent = '<?php echo $total_records; ?>';
    </script>
<?php
    echo ob_get_clean();
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ข้อผิดพลาดฐานข้อมูล: ' . htmlspecialchars($e->getMessage())]);
    exit;
}
?>