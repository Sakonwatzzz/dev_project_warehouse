<?php
require_once __DIR__ . '../../../../config/database_server.php';

session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Session timeout handling
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query([
        'timeout' => 1,
        'page' => 'product_brandgroup',
        'id' => $_GET['id'] ?? '',
        'search' => $_GET['search'] ?? '',
        'product_brand_name' => $_GET['product_brand_name'] ?? ''
    ]);
    header("Location: /page/login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// รับค่าจาก URL เพื่อหา brand ของผลิตภัณฑ์
$productBrand = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : '';

// เพิ่มตัวแปรสำหรับการค้นหา
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// คำสั่ง SQL เพื่อนับจำนวนข้อมูลทั้งหมดในแบรนด์ที่เลือก
try {
    $count_query = "SELECT COUNT(*) as total_count FROM product WHERE product_brand_id = :product_brand_id";
    $params = [':product_brand_id' => $productBrand];
    
    if (!empty($search)) {
        $count_query .= " AND (product_name LIKE :search OR product_id LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $count = $stmt->fetchColumn();
} catch (PDOException $e) {
    echo "Count error: " . htmlspecialchars($e->getMessage());
    exit();
}

// ดึงชื่อแบรนด์สินค้า
try {
    $stmtBrand = $pdo->prepare("SELECT product_brand_name FROM product_brand WHERE product_brand_id = :product_brand_id");
    $stmtBrand->bindParam(':product_brand_id', $productBrand, PDO::PARAM_INT);
    $stmtBrand->execute();
    $brandResult = $stmtBrand->fetch(PDO::FETCH_ASSOC);
    $productBrandName = $brandResult['product_brand_name'] ?? 'ไม่ทราบแบรนด์';
} catch (PDOException $e) {
    echo "Brand name error: " . htmlspecialchars($e->getMessage());
    exit();
}

// Pagination
$records_per_page = 6;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $count_query = "SELECT COUNT(*) FROM product WHERE product_brand_id = :product_brand_id";
    $params = [':product_brand_id' => $productBrand];
    
    if (!empty($search)) {
        $count_query .= " AND (product_name LIKE :search OR product_id LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}

// ดึงข้อมูลสินค้า
try {
    $query = "
        SELECT 
            product.product_id, 
            product.product_name, 
            product.product_price1, 
            product.product_price2, 
            product.product_price3, 
            product.product_price4, 
            product.product_capital, 
            product_brand.product_brand_name
        FROM 
            product
        INNER JOIN
            product_brand ON product.product_brand_id = product_brand.product_brand_id
        WHERE 
            product.product_brand_id = :product_brand_id";
    
    $params = [':product_brand_id' => $productBrand];
    if (!empty($search)) {
        $query .= " AND (product.product_name LIKE :search OR product.product_id LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY product.product_name LIMIT :offset, :records_per_page";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $productData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $productData = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="../../output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>กลุ่มสินค้า</title>
    <style>
        @media (max-width: 768px) {
            .table-responsive { display: none; }
            .card-responsive { display: block; }
        }
        @media (min-width: 769px) {
            .table-responsive { display: block; }
            .card-responsive { display: none; }
        }
    </style>
</head>
<body class="font-sans bg-gray-100">
    <div class="container mx-auto px-4 py-8 md:px-8 lg:px-16">
        <!-- Header and Search Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-6">
            <div class="flex flex-col gap-2 md:flex-row md:space-x-2 md:items-center">
                <div class="flex space-x-2">
                    <div class="w-auto px-4 py-2 bg-blue-200 border rounded font-bold text-blue-800">
                        <i class="fas fa-users mr-2"></i>
                        <?php echo $total_records; ?>
                        <span>รายการ</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center">
                <form action="" method="GET" id="searchForm" class="w-full text-end">
                    <input type="hidden" name="page" value="product_brandgroup">
                    <input type="hidden" name="tabel" value="product_brand">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($productBrand); ?>">
                    <input type="hidden" name="product_brand_name" value="<?php echo htmlspecialchars($productBrandName); ?>">
                    <input type="hidden" name="page_num" value="1">
                    <div class="relative">
                        <input type="text" name="search" id="searchInput"
                            placeholder="ค้นหาสินค้า..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-full md:w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="bg-white shadow-md rounded-xl p-4 space-y-4 border border-gray-200 md:flex md:gap-4 md:bg-transparent md:shadow-none md:border-none md:p-0 md:w-auto">
                <!-- Export Buttons -->
                <div class="grid grid-cols-2 gap-4 pt-2 md:grid md:grid-cols-2 md:gap-4">
                    <button onclick="exportPDF()" class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-red-500 text-white text-sm sm:text-base hover:bg-red-600 transition duration-200 shadow-md md:auto">
                        <i class="fas fa-file-pdf"></i> ส่งออก PDF
                    </button>
                    <button onclick="exportExcel()" class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-emerald-500 text-white text-sm sm:text-base hover:bg-emerald-600 transition duration-200 shadow-md md:auto">
                        <i class="fas fa-file-excel"></i> ส่งออก Excel
                    </button>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#d7e1ec] to-[#e6effb] p-4 border-b border-[#cfd5dc]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-list mr-2"></i>แบรนด์: <?php echo htmlspecialchars($productBrandName); ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="p-4 md:p-6">
                <!-- Table View (Desktop/iPad) -->
                <div class="table-responsive overflow-x-auto">
                    <table class="w-full border-collapse" id="dataTable">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 border text-left text-gray-700 font-semibold text-sm md:text-base">รหัสสินค้า</th>
                                <th class="px-4 py-3 border text-left text-gray-700 font-semibold text-sm md:text-base">ชื่อสินค้า</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($productData) > 0): ?>
                                <?php foreach ($productData as $product): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-4 py-1 border text-sm md:text-base"><?php echo htmlspecialchars($product['product_id']); ?></td>
                                        <td class="px-4 py-1 border text-sm md:text-base"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td class="px-4 py-1 border text-center">
                                            <div class="flex justify-center space-x-2">
                                                <a href="?page=product_update&id=<?php echo htmlspecialchars($product['product_id']); ?>"
                                                    class="bg-yellow-500 text-white px-4 py-1 rounded-lg hover:bg-yellow-600 transition flex items-center text-sm md:text-base">
                                                    <i class="fas fa-edit mr-1"></i>แก้ไข
                                                </a>
                                                <a href="?page=product_delete&id=<?php echo htmlspecialchars($product['product_id']); ?>"
                                                    onclick="return confirm('ต้องการลบสินค้า?')"
                                                    class="bg-red-500 text-white px-4 py-1 rounded-lg hover:bg-red-600 transition flex items-center text-sm md:text-base">
                                                    <i class="fas fa-trash-alt mr-1"></i>ลบ
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-gray-600 text-sm md:text-base">ไม่มีข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Card View (Mobile) -->
                <div class="card-responsive">
                    <?php if (count($productData) > 0): ?>
                        <?php foreach ($productData as $product): ?>
                            <div class="bg-white p-4 mb-4 rounded-lg shadow">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">รหัสสินค้า:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($product['product_id']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">ชื่อสินค้า:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($product['product_name']); ?></span>
                                </div>
                                <div class="flex justify-center space-x-2 mt-4">
                                    <a href="?page=product_update&id=<?php echo htmlspecialchars($product['product_id']); ?>"
                                        class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition flex items-center text-sm">
                                        <i class="fas fa-edit mr-1"></i>แก้ไข
                                    </a>
                                    <a href="?page=product_delete&id=<?php echo htmlspecialchars($product['product_id']); ?>"
                                        onclick="return confirm('ต้องการลบสินค้า?')"
                                        class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition flex items-center text-sm">
                                        <i class="fas fa-trash-alt mr-1"></i>ลบ
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-600 text-sm">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Pagination -->
        <div class="pagination mt-5 flex flex-wrap justify-center items-center gap-2">
            <?php if ($total_pages > 1): ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=product_brandgroup&tabel=product_brand&id=<?= htmlspecialchars($productBrand) ?>&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_brand_name=<?= urlencode($productBrandName) ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=product_brandgroup&tabel=product_brand&id=<?= htmlspecialchars($productBrand) ?>&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_brand_name=<?= urlencode($productBrandName) ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=product_brandgroup&tabel=product_brand&id=<?= htmlspecialchars($productBrand) ?>&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_brand_name=<?= urlencode($productBrandName) ?>"
                        class="px-3 py-2 rounded-md transition-colors text-sm md:text-base <?= $i == $page ? 'bg-gray-800 text-white' : 'bg-blue-100 text-black hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                    <a href="?page=product_brandgroup&tabel=product_brand&id=<?= htmlspecialchars($productBrand) ?>&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_brand_name=<?= urlencode($productBrandName) ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=product_brandgroup&tabel=product_brand&id=<?= htmlspecialchars($productBrand) ?>&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_brand_name=<?= urlencode($productBrandName) ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <!-- Back Button -->
        <div class="mt-4">
            <a href="?page=product_brand"
                class="inline-flex items-center px-6 py-2 bg-gray-700 text-white border rounded-lg hover:bg-gray-800 text-sm md:text-base">
                <i class="fas fa-arrow-left mr-2"></i>ย้อนกลับ
            </a>
        </div>
    </div>
    <!-- Notification -->
    <div id="notification" class="hidden fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white border-2 border-gray-400 rounded-md shadow-lg w-80 md:w-96 h-32 z-50">
        <div id="notification-header" class="p-2 rounded-t-md font-bold text-sm md:text-base"></div>
        <div id="notification-body" class="p-5 text-center text-base md:text-lg"></div>
    </div>
    <script>
        function checkEmptySearch(input) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', 'product_brandgroup');
            urlParams.set('tabel', 'product_brand');
            urlParams.set('id', '<?php echo htmlspecialchars($productBrand); ?>');
            urlParams.set('product_brand_name', '<?php echo urlencode($productBrandName); ?>');
            if (input.value.trim() === '') {
                urlParams.delete('search');
            } else {
                urlParams.set('search', input.value.trim());
            }
            window.location.search = urlParams.toString();
        }

        function exportPDF() {
            let url = '/page/product/report/export_product_brandgroup_pdf.php?id=<?php echo htmlspecialchars($productBrand); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            window.open(url, '_blank');
        }

        function exportExcel() {
            let url = '/page/product/report/export_product_brandgroup_excel.php?id=<?php echo htmlspecialchars($productBrand); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>';
            window.open(url, '_blank');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;

            searchInput.focus();

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    this.form.submit();
                }
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

            searchInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    this.form.submit();
                }
            });
        });

        function showNotification(message, headerClass) {
            const notification = document.getElementById('notification');
            const header = document.getElementById('notification-header');
            const body = document.getElementById('notification-body');
            header.className = `p-2 rounded-t-md font-bold ${headerClass} text-sm md:text-base`;
            header.textContent = 'แจ้งเตือน';
            body.textContent = message;
            notification.classList.remove('hidden');
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 1000);
        }
    </script>
</body>
</html>