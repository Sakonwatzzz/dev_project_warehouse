<?php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

try {
    $type = trim($_GET['type'] ?? '');
    $search = trim($_GET['search'] ?? '');
    $start_date = trim($_GET['start_date'] ?? '');
    $end_date = trim($_GET['end_date'] ?? '');
    $page = max(1, (int)($_GET['page_num'] ?? 1));
    $records_per_page = 5;

    if (empty($type)) {
        echo json_encode(['error' => 'กรุณาระบุประเภทบุคลากร']);
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

    // ตรวจสอบวันที่สิ้นสุดไม่มาก่อนวันที่เริ่มต้น
    if ($start_date && $end_date && strtotime($end_date) < strtotime($start_date)) {
        echo json_encode(['error' => 'วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น']);
        exit;
    }

    // สร้างคำสั่ง SQL สำหรับนับจำนวนทั้งหมด
    $count_query = "
        SELECT COUNT(*) 
        FROM personnel p 
        INNER JOIN personnel_type pt ON p.personnel_type_id = pt.personnel_type_id 
        WHERE pt.personnel_type_name = :type";
    $count_params = [':type' => $type];

    // สร้างคำสั่ง SQL สำหรับดึงข้อมูล
    $query = "
        SELECT 
            p.personnel_id,
            p.personnel_name,
            p.personnel_username,
            p.personnel_address,
            p.personnel_phone,
            p.personnel_create_date,
            p.personnel_type_id, 
            pt.personnel_type_name
        FROM personnel p
        INNER JOIN personnel_type pt ON p.personnel_type_id = pt.personnel_type_id
        WHERE pt.personnel_type_name = :type";
    $params = [':type' => $type];

    if (!empty($search) || $start_date || $end_date) {
        if (!empty($search)) {
            $query .= " AND (p.personnel_id LIKE :search OR p.personnel_name LIKE :search OR p.personnel_username LIKE :search OR p.personnel_phone LIKE :search)";
            $count_query .= " AND (p.personnel_id LIKE :search OR p.personnel_name LIKE :search OR p.personnel_username LIKE :search OR p.personnel_phone LIKE :search)";
            $params[':search'] = "%$search%";
            $count_params[':search'] = "%$search%";
        }
        if ($start_date) {
            $query .= " AND DATE(p.personnel_create_date) >= :start_date";
            $count_query .= " AND DATE(p.personnel_create_date) >= :start_date";
            $params[':start_date'] = $start_date;
            $count_params[':start_date'] = $start_date;
        }
        if ($end_date) {
            $query .= " AND DATE(p.personnel_create_date) <= :end_date";
            $count_query .= " AND DATE(p.personnel_create_date) <= :end_date";
            $params[':end_date'] = $end_date;
            $count_params[':end_date'] = $end_date;
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

    // ดึงข้อมูลบุคลากร
    $query .= " ORDER BY p.personnel_id LIMIT :offset, :limit";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', ($page - 1) * $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // บันทึก Query และ Params สำหรับ Debug
    error_log("Query: $query, Params: " . json_encode($params) . ", Total Records: $total_records");

    ob_start();
?>
    <table class="min-w-full bg-white">
        <thead>
            <tr class="bg-gray-100">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รหัสบุคลากร</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อ-นามสกุล</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อผู้ใช้</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ที่อยู่</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">เบอร์โทรศัพท์</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันที่สมัคร</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">การจัดการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (count($personnel) > 0): ?>
                <?php foreach ($personnel as $person): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($person['personnel_id']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($person['personnel_name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($person['personnel_username']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars(mb_substr($person['personnel_address'], 0, 20) . '...'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($person['personnel_phone']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars(date('Y-m-d', strtotime($person['personnel_create_date']))); ?></td>
                        <td class="px-3 py-3 text-center">
                            <div class="flex justify-center gap-2">
                                <button onclick="showEditModal(
                                    '<?php echo htmlspecialchars($person['personnel_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars(addslashes($person['personnel_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars($person['personnel_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars($person['personnel_type_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars(addslashes($person['personnel_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>',
                                    '<?php echo htmlspecialchars($person['personnel_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'
                                )" class="bg-yellow-500 text-white px-3 py-1.5 rounded-lg hover:bg-yellow-600 transition text-sm hover-lift">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" onclick="confirmDelete('<?php echo htmlspecialchars($person['personnel_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')"
                                    class="bg-red-500 text-white px-3 py-1.5 rounded-lg hover:bg-red-600 transition text-sm hover-lift">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">ไม่พบข้อมูลบุคลากร</td>
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
    error_log("Error in personnel_group_table.php: " . $e->getMessage());
    echo json_encode(['error' => 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage())]);
}