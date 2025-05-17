<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Database queries for dropdowns
function fetchDataFromTable($pdo, $query)
{
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch dropdown data
$status_lot = fetchDataFromTable($pdo, "SELECT lot_status_id, lot_status_name FROM lot_status");
$warehouse_lot = fetchDataFromTable($pdo, "SELECT warehouse_id, warehouse_name FROM warehouse");
$supplier_lot = fetchDataFromTable($pdo, "SELECT supplier_id, supplier_name FROM supplier");

// Add search and date filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Handle delete operation
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM lot WHERE lot_id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?page=lot_list");
    exit;
}

// Pagination setup
try {
    $records_per_page = 5;
    $page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
    $offset = ($page - 1) * $records_per_page;

    // Get total count with search and date filters
    $count_query = "SELECT COUNT(*) as total_count FROM lot WHERE lot.warehouse_inout = 1";
    $params = [];
    if ($start_date || $end_date || !empty($search)) {
        $count_query .= " AND 1=1";
        if ($start_date) {
            $count_query .= " AND lot.lot_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $count_query .= " AND lot.lot_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        if (!empty($search)) {
            $count_query .= " AND (lot.lot_id LIKE :search OR lot.lot_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $count = $stmt->fetchColumn();
    $total_pages = ceil($count / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
    exit();
}

// Fetch paginated data with search and date filters
try {
    $query = "
    SELECT
        lot_status.lot_status_id, 
        lot_status.lot_status_name, 
        supplier.supplier_id, 
        supplier.supplier_name, 
        warehouse.warehouse_name, 
        warehouse.warehouse_id,
        lot.lot_id, 
        lot.lot_name,
        lot.lot_detail, 
        lot.lot_date, 
        lot.lot_status_id, 
        lot.warehouse_id
    FROM
        lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
    WHERE lot.warehouse_inout = 1";

    $params = [];
    if ($start_date || $end_date || !empty($search)) {
        $query .= " AND 1=1";
        if ($start_date) {
            $query .= " AND lot.lot_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND lot.lot_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        if (!empty($search)) {
            $query .= " AND (lot.lot_id LIKE :search OR lot.lot_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
    }

    $query .= " ORDER BY lot.lot_date DESC LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $result_lot_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
    exit();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการล็อตสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-6">
                <h2 class="text-2xl font-bold text-gray-800">จำนวนรายการรับสินค้ามี:</h2>
                <div class="px-4 py-1 bg-white border border-gray-300 rounded shadow text-center min-w-[50px]">
                    <?php echo $count; ?>
                </div>
                <a href="?page=lot_listinsert"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition flex items-center">
                    <i class="fas fa-plus mr-2"></i>เพิ่มการรับสินค้า
                </a>
            </div>
            <div class="text-end">
                <form action="" method="GET" id="searchForm">
                    <input type="hidden" name="page" value="lot_list">
                    <input type="hidden" name="page_num" value="1">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหารายการรับสินค้า..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            oninput="checkEmptySearch(this)">
                        <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notification -->
        <div id="notification"
            class="fixed top-5 right-5 w-80 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden hidden">
            <div class="bg-green-500 text-white px-4 py-2 font-bold">การแจ้งเตือน</div>
            <div class="px-4 py-3">แก้ไขสมาชิกสำเร็จ!</div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <!-- หัวข้อ -->
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-list mr-2"></i> รายการรับสินค้าคลัง
                        </h2>
                    </div>

                    <!-- ปุ่มดาวน์โหลดรายงาน -->
                    <div class="flex flex-col md:flex-row md:items-center md:space-x-4 space-y-2 md:space-y-0">

                        <!-- เลือกช่วงวันที่ -->
                        <div class="flex items-center space-x-2">
                            <label for="start_date" class="text-sm font-medium">วันที่เริ่มต้น</label>
                            <input type="date" id="start_date" name="start_date"
                                class="border rounded px-2 py-1"
                                value="<?php echo htmlspecialchars($start_date); ?>">
                            <label for="end_date" class="text-sm font-medium">ถึง</label>
                            <input type="date" id="end_date" name="end_date"
                                class="border rounded px-2 py-1"
                                value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>

                        <!-- ปุ่มดาวน์โหลด -->
                        <div class="flex space-x-2">
                            <button onclick="exportPDF()"
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition flex items-center text-sm md:text-base">
                                <i class="fas fa-file-pdf mr-2"></i> ดาวน์โหลด PDF
                            </button>
                            <button onclick="exportExcel()"
                                class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition flex items-center text-sm md:text-base">
                                <i class="fas fa-file-excel mr-2"></i> ดาวน์โหลด Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัส Lot</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อ Lot</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">คลังปลายทาง</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">เจ้าหน้าที่</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">สถานะ</th>
                                <th class="px-6 py-3 border text-center text-gray-700 font-semibold">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($result_lot_list) > 0): ?>
                                <?php foreach ($result_lot_list as $lot_list): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($lot_list['lot_id']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($lot_list['lot_name']); ?></td>
                                        <td class="px-6 py-2 border cursor-pointer hover:underline" onclick="window.location.href='?page=warehouse_lot&warehouse_id=<?php echo htmlspecialchars($lot_list['warehouse_id']); ?>'"><?php echo htmlspecialchars($lot_list['warehouse_name']); ?></td>
                                        <td class="px-6 py-2 border cursor-pointer hover:underline" onclick="window.location.href='?page=supplier_lot&supplier_id=<?php echo htmlspecialchars($lot_list['supplier_id']); ?>'"><?php echo htmlspecialchars($lot_list['supplier_name']); ?></td>
                                        <td class="px-6 py-2 border">
                                            <a href="?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot_list['lot_id']); ?>"
                                                class="text-blue-600 hover:underline cursor-pointer block w-full h-full">
                                                <?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($lot_list['lot_date']))); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-2 border cursor-pointer hover:underline text-green-600" onclick="window.location.href='?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot_list['lot_id']); ?>'"><?php echo htmlspecialchars($lot_list['lot_status_name']); ?></td>
                                        <td class="px-6 py-2 border text-center">
                                            <div class="flex flex-wrap justify-center gap-2">
                                                <?php if ($lot_list['lot_status_id'] == 2): ?>
                                                    <a href="?page=lot_productlist&lot_id=<?php echo htmlspecialchars($lot_list['lot_id']); ?>"
                                                        class="bg-blue-500 text-white text-sm px-3 py-1 rounded hover:bg-blue-600 transition flex items-center whitespace-nowrap">
                                                        <i class="fas fa-eye mr-1"></i> ดูรายละเอียด
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($lot_list['lot_status_name'] !== 'ยืนยันแล้ว' && $lot_list['lot_status_id'] != 2): ?>
                                                    <a href="?page=lot_listdetail&tabel=lot&id=<?php echo htmlspecialchars($lot_list['lot_id']); ?>"
                                                        class="bg-yellow-500 text-white text-sm px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center whitespace-nowrap">
                                                        <i class="fas fa-edit mr-1"></i> แก้ไข
                                                    </a>
                                                    <a href="?page=lot_list&id=<?php echo htmlspecialchars($lot_list['lot_id']); ?>"
                                                        onclick="return confirm('กรุณาตรวจสอบข้อมูลก่อนลบ ถ้าคุณแน่ใจแล้วให้กดลบ')"
                                                        class="bg-red-500 text-white text-sm px-3 py-1 rounded hover:bg-red-600 transition flex items-center whitespace-nowrap">
                                                        <i class="fas fa-trash-alt mr-1"></i> ลบ
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-gray-600">ไม่มีรายการรับสินค้าคลัง</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=lot_list&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=lot_list&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=lot_list&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 rounded transition <?= $i == $page ? 'bg-gray-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=lot_list&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_list&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', 'lot_list');
                urlParams.delete('search');
                window.location.search = urlParams.toString();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('searchInput');
            if (urlParams.has('search')) {
                searchInput.focus();
            }
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = '?page=lot_list';
                }
            });
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    localStorage.setItem('searchPreserve', this.value);
                }
            });
            const preserved = localStorage.getItem('searchPreserve');
            if (preserved) {
                searchInput.value = preserved;
                searchInput.focus();
                searchInput.selectionStart = searchInput.selectionEnd = preserved.length;
                localStorage.removeItem('searchPreserve');
            }

            // Notification handler
            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.classList.remove('hidden');
                setTimeout(() => {
                    notification.classList.add('hidden');
                }, 2000);
            }

            // Date filter handler
            document.getElementById('start_date').addEventListener('change', updateDateFilter);
            document.getElementById('end_date').addEventListener('change', updateDateFilter);
        });

        function exportPDF() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/export_lot_list_pdf.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/lot/ajax/report/export_lot_list_excel.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function updateDateFilter() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', 'lot_list');

            if (startDate) {
                urlParams.set('start_date', startDate);
            } else {
                urlParams.delete('start_date');
            }

            if (endDate) {
                urlParams.set('end_date', endDate);
            } else {
                urlParams.delete('end_date');
            }

            window.location.search = urlParams.toString();
        }
    </script>
</body>

</html>