<?php
require_once __DIR__ . '../../../../config/database_server.php';

try {
    // Count total records in the product table
    $query = "SELECT COUNT(*) as total_count FROM product";
    $stmt = $pdo->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];

    // Check display mode
    $incomplete_mode = isset($_GET['mode']) && $_GET['mode'] === 'incomplete';

    // Sanitize and validate input filters
    $search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? filter_var($_GET['start_date'], FILTER_SANITIZE_STRING) : null;
    $end_date = isset($_GET['end_date']) ? filter_var($_GET['end_date'], FILTER_SANITIZE_STRING) : null;
    $selected_types = isset($_GET['product_type']) && !empty($_GET['product_type'])
        ? array_filter(array_map('intval', explode(',', $_GET['product_type'])))
        : [];
    $selected_brands = isset($_GET['product_brand']) && !empty($_GET['product_brand'])
        ? array_filter(array_map('intval', explode(',', $_GET['product_brand'])))
        : [];

    // Handle POST request for updating product
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $name = $_POST['name'] ?? '';
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        $prices = [
            'price1' => filter_input(INPUT_POST, 'price_1', FILTER_VALIDATE_FLOAT),
            'price2' => filter_input(INPUT_POST, 'price_2', FILTER_VALIDATE_FLOAT),
            'price3' => filter_input(INPUT_POST, 'price_3', FILTER_VALIDATE_FLOAT),
            'price4' => filter_input(INPUT_POST, 'price_4', FILTER_VALIDATE_FLOAT),
        ];
        $capital = filter_input(INPUT_POST, 'capital', FILTER_VALIDATE_FLOAT);
        $type = filter_input(INPUT_POST, 'typename', FILTER_VALIDATE_INT);
        $brand = filter_input(INPUT_POST, 'typebrandname', FILTER_VALIDATE_INT);

        if ($id && $name && $type && $brand) {
            $stmt = $pdo->prepare("
                UPDATE product 
                SET product_name = ?, 
                    product_price1 = ?, 
                    product_price2 = ?, 
                    product_price3 = ?, 
                    product_price4 = ?, 
                    product_capital = ?, 
                    product_type_id = ?, 
                    product_brand_id = ? 
                WHERE product_id = ?
            ");
            $stmt->execute([
                $name,
                $prices['price1'],
                $prices['price2'],
                $prices['price3'],
                $prices['price4'],
                $capital,
                $type,
                $brand,
                $id
            ]);

            header("Location: /?page=product_detail&tabel=product&id=$id");
            exit;
        }
    }

    // Handle deletion request
    if (isset($_GET['id'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
            $stmt->execute([$id]);

            // Reset AUTO_INCREMENT if table is empty
            $stmt = $pdo->query("SELECT COUNT(*) FROM product");
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                $pdo->query("ALTER TABLE product AUTO_INCREMENT = 1");
            }

            header("Location: /?page=product_list&delete_success");
            exit;
        }
    }

    // Pagination setup
    $records_per_page = 10;
    $page = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;
    $offset = ($page - 1) * $records_per_page;

    // Build WHERE condition for filtering
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

    if ($start_date) {
        $where_conditions[] = "lot_product.lot_product_date >= ?";
        $bind_params[] = $start_date;
    }
    if ($end_date) {
        $where_conditions[] = "lot_product.lot_product_date <= ?";
        $bind_params[] = $end_date;
    }

    $where_clause = !empty($where_conditions)
        ? 'WHERE ' . implode(' AND ', $where_conditions)
        : '';

    // Main query to fetch products
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
        COALESCE(personnel.personnel_name, 'ไม่มีข้อมูล') AS personnel_name
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
    $resultsproduct = $stmt;

    // Calculate incomplete products count when in incomplete_mode
    if ($incomplete_mode) {
        $incomplete_query = "SELECT COUNT(*) FROM product WHERE 
            product_name IS NULL OR TRIM(product_name) = '' OR 
            (product_price1 IS NULL OR product_price1 = '' OR product_price1 = 0) OR 
            (product_price2 IS NULL OR product_price2 = '' OR product_price2 = 0) OR 
            (product_price3 IS NULL OR product_price3 = '' OR product_price3 = 0) OR 
            (product_price4 IS NULL OR product_price4 = '' OR product_price4 = 0) OR 
            (product_capital IS NULL OR product_capital = '' OR product_capital = 0) OR 
            product_type_id IS NULL OR product_type_id = 0 OR 
            product_brand_id IS NULL OR product_brand_id = 0";

        $incomplete_stmt = $pdo->prepare($incomplete_query);
        $incomplete_stmt->execute();
        $incomplete_count = $incomplete_stmt->fetchColumn();
    }

    // Count total records for pagination
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

    // Product Type and Brand filters
    $typeLimit = isset($_POST['type_limit']) ? intval($_POST['type_limit']) : 5;
    $brandLimit = isset($_POST['brand_limit']) ? intval($_POST['brand_limit']) : 5;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    $totalTypeCount = $pdo->query("SELECT COUNT(*) FROM product_type")->fetchColumn();
    $totalBrandCount = $pdo->query("SELECT COUNT(*) FROM product_brand")->fetchColumn();

    // Fetch Product Types
    $type_stmt = $pdo->prepare("SELECT product_type_id, product_type_name FROM product_type LIMIT ?");
    $type_stmt->bindValue(1, $typeLimit, PDO::PARAM_INT);
    $type_stmt->execute();
    $typeResults = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Product Brands
    $brand_stmt = $pdo->prepare("SELECT product_brand_id, product_brand_name FROM product_brand LIMIT ?");
    $brand_stmt->bindValue(1, $brandLimit, PDO::PARAM_INT);
    $brand_stmt->execute();
    $brandResults = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("เกิดข้อผิดพลาดขณะประมวลผลคำขอของคุณ");
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการสินค้า</title>
    <link href="../../output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        @media (max-width: 640px) {
            .table-responsive {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table-responsive table {
                min-width: 800px;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="px-4 py-6 sm:py-8">

        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 sm:mb-8">
            <div class="flex items-center gap-4 flex-wrap">
                <?php if ($incomplete_mode): ?>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800">จำนวนสินค้าที่ไม่สมบูรณ์:</h2>
                    <div class="px-3 py-1 bg-white border border-red-500 rounded shadow text-center min-w-[40px] text-red-600 font-bold">
                        <?php echo isset($incomplete_count) ? $incomplete_count : '0'; ?>
                    </div>
                <?php else: ?>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800">จำนวนสินค้า:</h2>
                    <div class="px-3 py-1 bg-white border border-gray-300 rounded shadow text-center min-w-[40px]">
                        <?php echo $count; ?>
                    </div>
                <?php endif; ?>
                <a href="?page=product_insert"
                    class="bg-blue-500 text-white px-3 py-2 rounded hover:bg-blue-600 transition flex items-center text-sm sm:text-base">
                    <i class="fas fa-plus mr-1 sm:mr-2"></i>เพิ่มสินค้าใหม่
                </a>
            </div>

            <div class="w-full sm:w-auto">
                <input type="text" id="searchInput"
                    placeholder="ค้นหาสินค้า..."
                    class="w-full sm:w-[250px] lg:w-[350px] px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base"
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>

        <!-- Filters and Table -->
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Filters -->
            <div class="w-full lg:w-1/6">
                <button id="toggleFilters" class="lg:hidden w-full bg-gray-200 text-gray-700 px-4 py-2 rounded-md mb-4 flex items-center justify-between">
                    <span>ตัวกรอง</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div id="filters" class="hidden lg:block bg-white p-4 rounded-lg shadow-lg">
                    <h1 class="text-lg sm:text-xl font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <i class="fa-solid fa-filter text-blue-500"></i>
                        <span>ค้นหาแบบละเอียด</span>
                    </h1>

                    <!-- Product Type Filter -->
                    <div class="pt-2 pb-4 border-b border-gray-400">
                        <h2 class="text-base sm:text-lg font-semibold text-gray-700 mb-3">ค้นหาตามหมวดหมู่</h2>
                        <div class="flex flex-col gap-2 sm:gap-3">
                            <?php foreach ($typeResults as $typeRow): ?>
                                <div class="flex items-center gap-2 text-gray-600 text-sm sm:text-base">
                                    <input type="checkbox"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        name="product_type[]" value="<?php echo $typeRow['product_type_id']; ?>"
                                        <?php echo in_array($typeRow['product_type_id'], $selected_types) ? 'checked' : ''; ?>>
                                    <span><?php echo $typeRow['product_type_name']; ?></span>
                                </div>
                            <?php endforeach; ?>

                            <div class="flex flex-col sm:flex-row justify-between mt-2 gap-2">
                                <?php if ($typeLimit < $totalTypeCount): ?>
                                    <form action="" method="POST" class="w-full sm:w-1/2">
                                        <input type="hidden" name="type_limit" value="<?php echo $typeLimit + 5; ?>">
                                        <input type="hidden" name="brand_limit" value="<?php echo $brandLimit; ?>">
                                        <input type="hidden" name="action" value="show_more_types">
                                        <button type="submit"
                                            class="w-full py-1.5 px-2 bg-white border border-gray-300 rounded-md text-gray-700 text-xs sm:text-sm hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors">แสดงเพิ่มเติม</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($typeLimit > 5): ?>
                                    <form action="" method="POST" class="w-full sm:w-1/2">
                                        <input type="hidden" name="type_limit" value="5">
                                        <input type="hidden" name="brand_limit" value="<?php echo $brandLimit; ?>">
                                        <input type="hidden" name="action" value="show_less_types">
                                        <button type="submit"
                                            class="w-full py-1.5 px-2 bg-white border border-gray-300 rounded-md text-gray-700 text-xs sm:text-sm hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors">แสดงน้อยลง</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Product Brand Filter -->
                    <div class="pt-2 pb-4">
                        <h2 class="text-base sm:text-lg font-semibold text-gray-700 mb-3">ค้นหาตามแบรนด์</h2>
                        <div class="flex flex-col gap-2 sm:gap-3">
                            <?php foreach ($brandResults as $brandRow): ?>
                                <div class="flex items-center gap-2 text-gray-600 text-sm sm:text-base">
                                    <input type="checkbox"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        name="product_brand[]" value="<?php echo $brandRow['product_brand_id']; ?>"
                                        <?php echo in_array($brandRow['product_brand_id'], $selected_brands) ? 'checked' : ''; ?>>
                                    <span><?php echo $brandRow['product_brand_name']; ?></span>
                                </div>
                            <?php endforeach; ?>

                            <div class="flex flex-col sm:flex-row justify-between mt-2 gap-2">
                                <?php if ($brandLimit < $totalBrandCount): ?>
                                    <form action="" method="POST" class="w-full sm:w-1/2">
                                        <input type="hidden" name="brand_limit" value="<?php echo $brandLimit + 5; ?>">
                                        <input type="hidden" name="type_limit" value="<?php echo $typeLimit; ?>">
                                        <input type="hidden" name="action" value="show_more_brands">
                                        <button type="submit"
                                            class="w-full py-1.5 px-2 bg-white border border-gray-300 rounded-md text-gray-700 text-xs sm:text-sm hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors">แสดงเพิ่มเติม</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($brandLimit > 5): ?>
                                    <form action="" method="POST" class="w-full sm:w-1/2">
                                        <input type="hidden" name="brand_limit" value="5">
                                        <input type="hidden" name="type_limit" value="<?php echo $typeLimit; ?>">
                                        <input type="hidden" name="action" value="show_less_brands">
                                        <button type="submit"
                                            class="w-full py-1.5 px-2 bg-white border border-gray-300 rounded-md text-gray-700 text-xs sm:text-sm hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors">แสดงน้อยลง</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="w-full lg:w-4/5">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6 sm:mb-8">
                    <div class="flex justify-between items-center bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                        <!-- หัวข้อ -->
                        <h2 class="text-lg sm:text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-list mr-2"></i>รายการสินค้า
                        </h2>

                        <div class="flex space-x-2">
                            <!-- เลือกช่วงวันที่ -->
                            <div class="flex items-center space-x-2">
                                <label for="start_date" class="text-sm font-medium">วันที่นำเข้า</label>
                                <input type="date" id="start_date" name="start_date" class="border rounded px-2 py-1"
                                       value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                                <label for="end_date" class="text-sm font-medium">ถึง</label>
                                <input type="date" id="end_date" name="end_date" class="border rounded px-2 py-1"
                                       value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                            </div>

                            <!-- ปุ่มดาวน์โหลดรายงาน -->
                            <div class="flex space-x-2">
                                <button onclick="exportPDF()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex items-center text-sm md:text-base">
                                    <i class="fas fa-file-pdf mr-2"></i> PDF
                                </button>
                                <button onclick="exportExcel()" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition flex items-center text-sm md:text-base">
                                    <i class="fas fa-file-excel mr-2"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 sm:p-6">
                        <div class="flex items-center space-x-3 sm:space-x-4 mb-4">
                            <a href="?page=product_list"
                                class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-md font-bold transition-all text-sm sm:text-base <?php echo !$incomplete_mode ? 'bg-gray-700 text-white' : 'bg-gray-200 text-gray-700 border border-gray-300'; ?>">
                                แสดงทั้งหมด
                            </a>
                            <a href="?page=product_list&mode=incomplete"
                                class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-md font-bold transition-all text-sm sm:text-base <?php echo $incomplete_mode ? 'bg-red-600 text-white' : 'bg-red-100 text-red-600 border border-red-300'; ?>">
                                แสดงข้อมูลไม่ครบ
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="w-full border-collapse" id="dataTable">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-3 bg-blue-400 text-center py-2 sm:py-3 border text-gray-700 font-semibold text-xs sm:text-sm">รหัสสินค้า</th>
                                        <th class="px-3 text-center py-2 sm:py-3 border text-gray-700 font-semibold text-xs sm:text-sm">ชื่อสินค้า</th>
                                        <th class="px-3 text-center py-2 sm:py-3 border text-gray-700 font-semibold text-xs sm:text-sm">ราคาทุน</th>
                                        <th class="px-3 text-center py-2 sm:py-3 border text-gray-700 font-semibold text-xs sm:text-sm">ราคาขาย</th>
                                        <th class="px-3 text-center py-2 sm:py-3 border text-gray-700 font-semibold text-xs sm:text-sm">ประเภทสินค้า</th>
                                        <th class="px-3 text-center py-2 sm:py-3 border text-gray-700 font-semibold text-xs sm:text-sm">แบรนด์สินค้า</th>
                                        <th class="px-3 text-center py-2 sm:py-3 border text-gray-700 font-semibold text-xs sm:text-sm">วันที่นำเข้า</th>
                                        <th class="px-3 text-center py-2 sm:py-3 border text-gray-700 font-semibold text-xs sm:text-sm">ชื่อพนักงาน</th>
                                        <th class="px-3 text-center py-2 sm:py-3 border text-gray-700 font-semibold text-xs sm:text-sm">การจัดการ</th>
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
                                                <td class="px-3 bg-yellow-400 bg-opacity-50 py-2 border border-gray-300 text-xs sm:text-sm"><?php echo htmlspecialchars($product_list['product_id']); ?></td>
                                                <td class="px-3 py-2 border text-blue-600 hover:underline cursor-pointer text-xs sm:text-sm <?php echo empty($product_list['product_name']) ? 'text-red-600 font-bold' : ''; ?>"
                                                    onclick="window.location.href='?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product_list['product_id']); ?>'">
                                                    <?php echo empty($product_list['product_name']) ? 'ไม่มีข้อมูล' : htmlspecialchars($product_list['product_name']); ?>
                                                </td>
                                                <td class="px-3 py-2 border text-right text-xs sm:text-sm <?php echo empty($product_list['product_capital']) ? 'text-red-600 font-bold' : ''; ?>">
                                                    <?php echo empty($product_list['product_capital']) ? 'ไม่มีข้อมูล' : htmlspecialchars($product_list['product_capital']); ?>
                                                </td>
                                                <td class="px-3 py-2 border text-right text-xs sm:text-sm <?php echo empty($product_list['product_price4']) ? 'text-red-600 font-bold' : ''; ?>">
                                                    <?php echo empty($product_list['product_price4']) ? 'ไม่มีข้อมูล' : htmlspecialchars($product_list['product_price4']); ?>
                                                </td>
                                                <td class="px-3 py-2 border text-xs sm:text-sm <?php echo empty($product_list['product_type_id']) ? 'text-red-600 font-bold' : ''; ?>">
                                                    <?php echo !empty($product_list['product_type_name']) ? htmlspecialchars($product_list['product_type_name']) : 'ไม่มีข้อมูล'; ?>
                                                </td>
                                                <td class="px-3 py-2 border text-xs sm:text-sm <?php echo empty($product_list['product_brand_id']) ? 'text-red-600 font-bold' : ''; ?>">
                                                    <?php echo !empty($product_list['product_brand_name']) ? htmlspecialchars($product_list['product_brand_name']) : 'ไม่มีข้อมูล'; ?>
                                                </td>
                                                <td class="px-3 py-2 border text-xs sm:text-sm <?php echo empty($product_list['lot_product_date']) ? '' : ''; ?>">
                                                    <?php echo !empty($product_list['lot_product_date']) ? htmlspecialchars($product_list['lot_product_date']) : 'ไม่มีข้อมูล'; ?>
                                                </td>
                                                <td class="px-3 py-2 border text-xs sm:text-sm <?php echo empty($product_list['personnel_name']) ? '' : ''; ?>">
                                                    <?php echo !empty($product_list['personnel_name']) ? htmlspecialchars($product_list['personnel_name']) : 'ไม่มีข้อมูล'; ?>
                                                </td>
                                                <td class="px-3 py-2 border text-center">
                                                    <div class="flex flex-col sm:flex-row justify-center space-y-2 sm:space-y-0 sm:space-x-2">
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
                                            <td colspan="9" class="text-center py-4 text-gray-600 text-sm sm:text-base">
                                                <?php echo $incomplete_mode ? 'ไม่พบสินค้าที่มีข้อมูลไม่ครบ' : 'ไม่พบข้อมูลสินค้า'; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="pagination mt-5 flex flex-wrap justify-center items-center gap-2">
                    <?php
                    // Build query string for pagination links to preserve filters
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
                    if ($start_date) {
                        $query_params['start_date'] = $start_date;
                    }
                    if ($end_date) {
                        $query_params['end_date'] = $end_date;
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
            </div>

            <!-- Notifications -->
            <div id="notification-edit"
                class="fixed top-5 right-5 w-64 sm:w-80 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden hidden">
                <div class="bg-yellow-500 text-white px-3 sm:px-4 py-1.5 sm:py-2 font-bold text-sm sm:text-base">การแจ้งเตือน</div>
                <div class="px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base">แก้ไขสินค้าสำเร็จ!</div>
            </div>
            <div id="notification-delete"
                class="fixed top-5 right-5 w-64 sm:w-80 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden hidden">
                <div class="bg-red-500 text-white px-3 sm:px-4 py-1.5 sm:py-2 font-bold text-sm sm:text-base">การแจ้งเตือน</div>
                <div class="px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base">ลบสินค้าสำเร็จ!</div>
            </div>
        </div>

        <script src="page/product/function/search_product.js"></script>

        <script>
            function exportPDF() {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                const mode = '<?php echo $incomplete_mode ? 'incomplete' : 'complete'; ?>';

                let url = `/page/product/report/export_product_pdf.php?mode=${mode}`;
                if (startDate) url += `&start_date=${startDate}`;
                if (endDate) url += `&end_date=${endDate}`;

                window.open(url, '_blank');
            }

            function exportExcel() {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                const mode = '<?php echo $incomplete_mode ? 'incomplete' : 'complete'; ?>';

                let url = `/page/product/report/export_product_excel.php?mode=${mode}`;
                if (startDate) url += `&start_date=${startDate}`;
                if (endDate) url += `&end_date=${endDate}`;

                window.open(url, '_blank');
            }

            // อัปเดต URL เมื่อเปลี่ยนวันที่
            document.getElementById('start_date').addEventListener('change', updateDateFilter);
            document.getElementById('end_date').addEventListener('change', updateDateFilter);

            function updateDateFilter() {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                const urlParams = new URLSearchParams(window.location.search);

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