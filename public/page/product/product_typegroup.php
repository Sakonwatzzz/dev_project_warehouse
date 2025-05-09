<?php
require_once __DIR__ . '../../../../config/database_server.php';

// รับค่าจาก URL เพื่อหา type ของผลิตภัณฑ์
$productTypeId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : '';

// เพิ่มตัวแปรสำหรับการค้นหา
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// คำสั่ง SQL เพื่อนับจำนวนข้อมูลทั้งหมดในประเภทที่เลือก
try {
    $count_query = "SELECT COUNT(*) as total_count FROM product WHERE product_type_id = :product_type_id";
    $params = [':product_type_id' => $productTypeId];
    
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

// ดึงชื่อประเภทสินค้า
try {
    $stmtType = $pdo->prepare("SELECT product_type_name FROM product_type WHERE product_type_id = :product_type_id");
    $stmtType->bindParam(':product_type_id', $productTypeId, PDO::PARAM_INT);
    $stmtType->execute();
    $typeResult = $stmtType->fetch(PDO::FETCH_ASSOC);
    $productTypeName = $typeResult['product_type_name'] ?? 'ไม่ทราบประเภท';
} catch (PDOException $e) {
    echo "Type name error: " . htmlspecialchars($e->getMessage());
    exit();
}

// Pagination
$records_per_page = 6;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $count_query = "SELECT COUNT(*) FROM product WHERE product_type_id = :product_type_id";
    $params = [':product_type_id' => $productTypeId];
    
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
            product.product_type_id,
            product_type.product_type_name
        FROM 
            product
        INNER JOIN
            product_type ON product.product_type_id = product_type.product_type_id
        WHERE 
            product.product_type_id = :product_type_id";
    
    $params = [':product_type_id' => $productTypeId];
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-list mr-2"></i>ประเภท: <?php echo htmlspecialchars($productTypeName); ?>
                        </h2>
                        <span class="px-3 py-1 bg-white font-bold border border-gray-500 rounded-md text-sm md:text-base"><?php echo $count; ?> รายการ</span>
                    </div>
                    <div class="w-full md:w-1/2">
                        <form action="" method="GET" class="w-full" id="searchForm">
                            <input type="hidden" name="page" value="product_typegroup">
                            <input type="hidden" name="tabel" value="product_type">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($productTypeId); ?>">
                            <input type="hidden" name="product_type_name" value="<?php echo htmlspecialchars($productTypeName); ?>">
                            <div class="relative">
                                <input type="text" name="search" id="searchInput"
                                    placeholder="ค้นหาสินค้า..."
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base"
                                    oninput="checkEmptySearch(this)">
                                <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
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
                    <a href="?page=product_typegroup&tabel=product_type&id=<?= htmlspecialchars($productTypeId) ?>&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_type_name=<?= urlencode($productTypeName) ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=product_typegroup&tabel=product_type&id=<?= htmlspecialchars($productTypeId) ?>&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_type_name=<?= urlencode($productTypeName) ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=product_typegroup&tabel=product_type&id=<?= htmlspecialchars($productTypeId) ?>&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_type_name=<?= urlencode($productTypeName) ?>"
                        class="px-3 py-2 rounded-md transition-colors text-sm md:text-base <?= $i == $page ? 'bg-gray-800 text-white' : 'bg-blue-100 text-black hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                    <a href="?page=product_typegroup&tabel=product_type&id=<?= htmlspecialchars($productTypeId) ?>&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_type_name=<?= urlencode($productTypeName) ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=product_typegroup&tabel=product_type&id=<?= htmlspecialchars($productTypeId) ?>&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>&product_type_name=<?= urlencode($productTypeName) ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <!-- Back Button -->
        <div class="mt-4">
            <a href="?page=product_type"
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
            if (input.value.trim() === '') {
                window.location.href = '?page=product_typegroup&tabel=product_type&id=<?php echo htmlspecialchars($productTypeId); ?>&product_type_name=<?php echo urlencode($productTypeName); ?>';
            }
        }
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('search')) {
                document.getElementById('searchInput').focus();
            }
            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = '?page=product_typegroup&tabel=product_type&id=<?php echo htmlspecialchars($productTypeId); ?>&product_type_name=<?php echo urlencode($productTypeName); ?>';
                }
            });
            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    localStorage.setItem('searchPreserve', this.value);
                }
            });
            const preserved = localStorage.getItem('searchPreserve');
            if (preserved) {
                const input = document.getElementById('searchInput');
                input.value = preserved;
                input.focus();
                input.selectionStart = input.selectionEnd = preserved.length;
                localStorage.removeItem('searchPreserve');
            }
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