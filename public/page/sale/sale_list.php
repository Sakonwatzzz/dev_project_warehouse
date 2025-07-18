<?php
require_once __DIR__ . '../../../../config/database_server.php';

date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection();

// อ่านค่าจาก URL สำหรับการกรอง
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '';
$status = isset($_GET['status']) ? (int)$_GET['status'] : 1; // ค่าเริ่มต้นสถานะ
$date_range = isset($_GET['date_range']) ? htmlspecialchars($_GET['date_range']) : 'this_year'; // ค่าเริ่มต้นเป็น this_year

// ตั้งค่าวันที่ปัจจุบันเมื่อ date_range เป็น custom และไม่มีค่าใน URL
if ($date_range === 'custom' && empty($start_date)) {
    $start_date = date('Y-m-d');
}
if ($date_range === 'custom' && empty($end_date)) {
    $end_date = date('Y-m-d');
}

// สร้างเงื่อนไข WHERE และพารามิเตอร์
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(sale.sale_id LIKE ? OR member.member_name LIKE ? OR personnel.personnel_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($date_range === 'today') {
    $where_conditions[] = "DATE(sale.sale_date) = CURDATE()";
} elseif ($date_range === 'this_month') {
    $where_conditions[] = "YEAR(sale.sale_date) = YEAR(CURDATE()) AND MONTH(sale.sale_date) = MONTH(CURDATE())";
} elseif ($date_range === 'this_year') {
    $where_conditions[] = "YEAR(sale.sale_date) = YEAR(CURDATE())";
} elseif ($date_range === 'custom' && $start_date) {
    $where_conditions[] = "DATE(sale.sale_date) >= ?";
    $params[] = $start_date;
}
if ($date_range === 'custom' && $end_date) {
    $where_conditions[] = "DATE(sale.sale_date) <= ?";
    $params[] = $end_date;
}

if ($status) {
    $where_conditions[] = "sale.sale_status_id = ?";
    $params[] = $status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// นับจำนวนข้อมูลทั้งหมด (รวมเงื่อนไขการกรอง)
$total_query = "SELECT COUNT(DISTINCT sale.sale_id) FROM sale
                LEFT JOIN member ON sale.member_id = member.member_id
                LEFT JOIN personnel ON sale.personnel_id = personnel.personnel_id
                $where_clause";
$stmt_count = $pdo->prepare($total_query);
if (!empty($params)) {
    foreach ($params as $index => $value) {
        $stmt_count->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}
$stmt_count->execute();
$count = $stmt_count->fetchColumn();

// Pagination
$records_per_page = 10;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;
$total_page = ceil($count / $records_per_page);

try {
    $query = "SELECT 
                sale.sale_id, 
                sale.sale_date, 
                sale.sale_status_id, 
                sale.member_id, 
                sale.personnel_id, 
                sale_product.sale_product_id, 
                sale_product.sale_product_qty, 
                sale_product.sale_product_price, 
                sale_product.sale_product_capital, 
                sale_product.product_id, 
                product.product_name, 
                product.product_price1, 
                product.product_price2, 
                product.product_price3, 
                product.product_price4, 
                product.product_capital, 
                member.member_name, 
                member.member_phone, 
                member.member_register_date, 
                member.member_type_id, 
                member.member_address, 
                personnel.personnel_name, 
                personnel.personnel_phone, 
                personnel.personnel_username, 
                sale_status.sale_status_name
            FROM
                sale
                LEFT JOIN sale_product ON sale.sale_id = sale_product.sale_id
                LEFT JOIN product ON product.product_id = sale_product.product_id
                LEFT JOIN member ON sale.member_id = member.member_id
                LEFT JOIN personnel ON sale.personnel_id = personnel.personnel_id
                LEFT JOIN sale_status ON sale.sale_status_id = sale_status.sale_status_id
                $where_clause
                ORDER BY sale.sale_id ASC
                LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);

    // ผูกพารามิเตอร์ทั้งหมด (รวม LIMIT และ OFFSET)
    $param_index = 1;
    foreach ($params as $value) {
        $stmt->bindValue($param_index++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue($param_index++, (int)$records_per_page, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sale_products)) {
        $error_message = "ไม่พบรายการขายที่ค้นหา";
    } else {
        $total_price_by_sale = [];
        foreach ($sale_products as $sale_product) {
            $sale_id = $sale_product['sale_id'];
            if (!isset($total_price_by_sale[$sale_id])) {
                $total_price_by_sale[$sale_id] = 0;
            }
            $total_price_by_sale[$sale_id] += $sale_product['sale_product_qty'] * $sale_product['sale_product_price'];
        }
    }
} catch (PDOException $e) {
    error_log("Database query error: " . $e->getMessage());
    $error_message = "การเชื่อมต่อฐานข้อมูลเกิดข้อผิดพลาด: " . $e->getMessage();
}

// ดึงข้อมูลสถานะการขาย
$sale_statusQuery = $pdo->query("SELECT sale_status_id, sale_status_name FROM sale_status");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
    <style>
        .custom-select {
            appearance: none;
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5em;
            cursor: pointer;
        }
        .flatpickr-input {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' /%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5em;
        }
        .flatpickr-calendar {
            @apply bg-white shadow-lg rounded-lg border border-gray-200;
        }
        .flatpickr-day.selected {
            @apply bg-blue-500 border-blue-500 text-white;
        }
        .flatpickr-day.today {
            @apply border-blue-400;
        }
        .flatpickr-day:hover {
            @apply bg-blue-100;
        }
        .flatpickr-monthDropdown-month,
        .flatpickr-current-month .numInput {
            @apply text-gray-700;
        }
        .flatpickr-prev-month,
        .flatpickr-next-month {
            @apply text-blue-500 hover:text-blue-700;
        }
        .standard-button-mobile {
            @apply text-sm px-3 py-1;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4">
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 flex-wrap">
                <div class="flex items-center gap-4">
                    <p class="text-gray-700 font-medium">จำนวนรายการขายทั้งหมด</p>
                    <div class="border border-gray-300 rounded-md px-4 py-2 text-lg font-bold text-blue-600 bg-blue-50">
                        <?php echo $count; ?>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <div class="flex items-center">
                        <input type="text" id="searchInput" autofocus placeholder="ค้นหารายการขาย..."
                            class="border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 w-full sm:w-96 transition duration-200 hover:bg-gray-50"
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-gray-700 font-medium">ช่วงวันที่</h2>
                        <select id="dateRangeSelect" name="date_range"
                            class="custom-select border border-gray-200 rounded-md px-4 py-2 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition duration-200 hover:bg-blue-50 w-40">
                            <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>วันนี้</option>
                            <option value="this_month" <?php echo $date_range === 'this_month' ? 'selected' : ''; ?>>เดือนนี้</option>
                            <option value="this_year" <?php echo $date_range === 'this_year' ? 'selected' : ''; ?>>ปีนี้</option>
                            <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>กำหนดเอง</option>
                        </select>
                    </div>
                    <div id="customDateRange" class="flex items-center space-x-2 transition-all duration-300 transform <?php echo $date_range !== 'custom' ? 'opacity-0 scale-95 hidden' : 'opacity-100 scale-100'; ?>">
                        <input type="text" id="startDate"
                            class="flatpickr-input border border-gray-200 rounded-md px-4 py-2 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition duration-200 hover:bg-gray-50 w-36"
                            value="<?php echo htmlspecialchars($start_date ? date('d/m/Y', strtotime($start_date)) : date('d/m/Y')); ?>">
                        <span class="text-gray-600 bg-gray-100 px-3 py-1 rounded-full font-medium">ถึง</span>
                        <input type="text" id="endDate"
                            class="flatpickr-input border border-gray-200 rounded-md px-4 py-2 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition duration-200 hover:bg-gray-50 w-36"
                            value="<?php echo htmlspecialchars($end_date ? date('d/m/Y', strtotime($end_date)) : date('d/m/Y')); ?>">
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-gray-700 font-medium">สถานะการขาย</h2>
                        <select id="saleStatusSelect" name="sale_status_id"
                            class="custom-select border border-gray-200 rounded-md px-4 py-2 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition duration-200 hover:bg-blue-50 w-40">
                            <?php while ($status_list = $sale_statusQuery->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo htmlspecialchars($status_list['sale_status_id']); ?>"
                                    <?php echo $status_list['sale_status_id'] == $status ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status_list['sale_status_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="flex flex-row xs:flex-col gap-2">
                    <!-- <button id="resetFilter" class="py-2 px-4 rounded-lg bg-purple-500 hover:bg-purple-600 text-white shadow-sm transition duration-200 standard-button-mobile">
                        <i class="fas fa-times mr-1"></i>รีเซ็ตการกรอง
                    </button> -->
                    <button onclick="exportPDF()" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition flex items-center gap-2 standard-button-mobile">
                        <i class="fas fa-file-pdf"></i><span>PDF</span>
                    </button>
                    <button onclick="exportExcel()" class="bg-emerald-500 text-white py-2 px-4 rounded-lg hover:bg-emerald-600 transition flex items-center gap-2 standard-button-mobile">
                        <i class="fas fa-file-excel"></i><span>Excel</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <?php if (isset($error_message)): ?>
                <p class="text-center text-red-600 py-4"><?php echo $error_message; ?></p>
            <?php else: ?>
                <table id="dataTable" class="min-w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-200 text-gray-700">
                            <th class="py-3 px-4 text-left border-b">รหัสการขาย</th>
                            <th class="py-3 px-4 text-left border-b">วันที่ขาย</th>
                            <th class="py-3 px-4 text-left border-b">สถานะการขาย</th>
                            <th class="py-3 px-4 text-left border-b">ชื่อสมาชิก</th>
                            <th class="py-3 px-4 text-left border-b">เบอร์โทรศัพท์สมาชิก</th>
                            <th class="py-3 px-4 text-left border-b">พนักงาน</th>
                            <th class="py-3 px-4 text-left border-b">ยอดรวม</th>
                            <th class="py-3 px-4 text-left border-b">ดูรายละเอียด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $current_sale_id = null;
                        foreach ($sale_products as $sale_product):
                            if ($current_sale_id !== $sale_product['sale_id']):
                                $current_sale_id = $sale_product['sale_id'];
                        ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['sale_id']); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['sale_date']); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['sale_status_name']); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['member_name'] ?: 'ไม่มีข้อมูล'); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['member_phone'] ?: 'ไม่มีข้อมูล'); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['personnel_name'] ?: 'ไม่มีข้อมูล'); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600">
                                        ฿<?php echo number_format($total_price_by_sale[$sale_product['sale_id']], 2); ?>
                                    </td>
                                    <td class="py-3 px-4 border-b">
                                        <a href="http://localhost:8000/?page=sale_detail&sale_id=<?php echo urlencode($sale_product['sale_id']); ?>"
                                            class="text-blue-500 hover:text-blue-700">ดูรายละเอียด</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($sale_products)): ?>
                        <tfoot>
                            <tr class="bg-gray-200 text-gray-700">
                                <td colspan="6" class="py-3 px-4 text-right border-t font-semibold">รวมทั้งหมด</td>
                                <td class="py-3 px-4 border-t font-semibold">
                                    ฿<?php echo number_format(array_sum($total_price_by_sale), 2); ?>
                                </td>
                                <td class="py-3 px-4 border-t"></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            <?php endif; ?>

            <div class="flex justify-center mt-6 pb-6">
                <?php if ($total_page > 1): ?>
                    <nav class="inline-flex items-center space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=sale_list&page_num=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&status=<?php echo urlencode($status); ?>&date_range=<?php echo urlencode($date_range); ?>"
                                class="px-3 py-1 bg-white border border-gray-300 rounded transition duration-200 hover:bg-gray-200">
                                « ก่อนหน้า
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="px-3 py-1 bg-blue-500 text-white border rounded">
                                    <?php echo $i; ?>
                                </span>
                            <?php else: ?>
                                <a href="?page=sale_list&page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&status=<?php echo urlencode($status); ?>&date_range=<?php echo urlencode($date_range); ?>"
                                    class="px-3 py-1 bg-white border border-gray-300 rounded transition duration-200 hover:bg-gray-200">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_page): ?>
                            <a href="?page=sale_list&page_num=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&status=<?php echo urlencode($status); ?>&date_range=<?php echo urlencode($date_range); ?>"
                                class="px-3 py-1 bg-white border border-gray-300 rounded transition duration-200 hover:bg-gray-200">
                                ถัดไป »
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Global functions for exporting
        function exportPDF() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            window.open(`/page/sale/report/export_sale_pdf.php?${params.toString()}`);
        }

        function exportExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.open(`/page/sale/report/export_sale_excel.php?${params.toString()}`);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const dateRangeSelect = document.getElementById('dateRangeSelect');
            const customDateRange = document.getElementById('customDateRange');
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            const saleStatusSelect = document.getElementById('saleStatusSelect');
            // const resetFilter = document.getElementById('resetFilter');

            // Initialize Flatpickr
            flatpickr('#startDate, #endDate', {
                locale: 'th',
                dateFormat: 'd/m/Y',
                enableTime: false,
                allowInput: true
            });

            // Toggle custom date range visibility
            dateRangeSelect.addEventListener('change', () => {
                if (dateRangeSelect.value === 'custom') {
                    customDateRange.classList.remove('opacity-0', 'scale-95', 'hidden');
                    customDateRange.classList.add('opacity-100', 'scale-100');
                } else {
                    customDateRange.classList.add('opacity-0', 'scale-95', 'hidden');
                    customDateRange.classList.remove('opacity-100', 'scale-100');
                }
                updateTable();
            });

            // Debounce function
            let debounceTimer;
            function debounceUpdateTable() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(updateTable, 300);
            }

            // Update table function
            function updateTable() {
                const params = new URLSearchParams();
                params.set('page', 'sale_list');
                params.set('page_num', '1');
                if (searchInput.value.trim()) {
                    params.set('search', searchInput.value.trim());
                }
                if (dateRangeSelect.value) {
                    params.set('date_range', dateRangeSelect.value);
                }
                if (dateRangeSelect.value === 'custom' && startDateInput.value) {
                    params.set('start_date', formatDateForQuery(startDateInput.value));
                }
                if (dateRangeSelect.value === 'custom' && endDateInput.value) {
                    params.set('end_date', formatDateForQuery(endDateInput.value));
                }
                if (saleStatusSelect.value) {
                    params.set('status', saleStatusSelect.value);
                }

                window.location.href = `?${params.toString()}`;
            }

            // Format date for query (from dd/mm/yyyy to yyyy-mm-dd)
            function formatDateForQuery(dateStr) {
                if (!dateStr) return '';
                const [day, month, year] = dateStr.split('/');
                return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
            }

            // Notification function
            function showNotification(message, type) {
                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} animate-fade-in`;
                notification.textContent = message;
                document.body.appendChild(notification);
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }

            // Event listeners
            searchInput.addEventListener('input', debounceUpdateTable);
            startDateInput.addEventListener('change', updateTable);
            endDateInput.addEventListener('change', updateTable);
            saleStatusSelect.addEventListener('change', updateTable);

            // resetFilter.addEventListener('click', () => {
            //     searchInput.value = '';
            //     dateRangeSelect.value = 'this_year';
            //     saleStatusSelect.value = '1';
            //     startDateInput.value = '';
            //     endDateInput.value = '';
            //     customDateRange.classList.add('opacity-0', 'scale-95', 'hidden');
            //     customDateRange.classList.remove('opacity-100', 'scale-100');
            //     updateTable();
            // });

            // Show notification if any
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                showNotification('การดำเนินการสำเร็จ!', 'success');
            } else if (urlParams.has('error')) {
                showNotification('เกิดข้อผิดพลาด!', 'error');
            }
        });
    </script>
</body>

</html>