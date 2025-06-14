<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once __DIR__ . '../../../fpdf186/fpdf.php';
date_default_timezone_set('Asia/Bangkok');

function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }
}

try {
    $query = "SELECT COUNT(*) as total_count FROM product";
    $stmt = $pdo->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];

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

            header("Location: /?page=product_detail&tabel=product&id=$id&edit_success=1");
            exit;
        }
    }

    if (isset($_GET['id'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->query("SELECT COUNT(*) FROM product");
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                $pdo->query("ALTER TABLE product AUTO_INCREMENT = 1");
            }

            header("Location: /?page=product_list&delete_success=1" . ($incomplete_mode ? '&mode=incomplete' : ''));
            exit;
        }
    }

    $records_per_page = 10;
    $page = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;
    $offset = ($page - 1) * $records_per_page;

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
        $bind_params[] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $where_conditions[] = "lot_product.lot_product_date <= ?";
        $bind_params[] = $end_date . ' 23:59:59';
    }

    $where_clause = !empty($where_conditions)
        ? 'WHERE ' . implode(' AND ', $where_conditions)
        : '';

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
    GROUP BY product.product_id, product.product_name, product.product_price1, product.product_price2, 
            product.product_price3, product.product_price4, product.product_capital, 
            product.product_type_id, product.product_brand_id,
            product_brand.product_brand_name, product_type.product_type_name
    LIMIT ?, ?";

    $stmt = $pdo->prepare($query);
    foreach ($bind_params as $i => $param) {
        $stmt->bindValue($i + 1, $param, is_string($param) ? PDO::PARAM_STR : PDO::PARAM_INT);
    }
    $stmt->bindValue(count($bind_params) + 1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(count($bind_params) + 2, $records_per_page, PDO::PARAM_INT);

    $stmt->execute();
    $resultsproduct = $stmt;

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

    $typeLimit = isset($_POST['type_limit']) ? intval($_POST['type_limit']) : 5;
    $brandLimit = isset($_POST['brand_limit']) ? intval($_POST['brand_limit']) : 5;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    $totalTypeCount = $pdo->query("SELECT COUNT(*) FROM product_type")->fetchColumn();
    $totalBrandCount = $pdo->query("SELECT COUNT(*) FROM product_brand")->fetchColumn();

    $type_stmt = $pdo->prepare("SELECT product_type_id, product_type_name FROM product_type LIMIT ?");
    $type_stmt->bindValue(1, $typeLimit, PDO::PARAM_INT);
    $type_stmt->execute();
    $typeResults = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

    $brand_stmt = $pdo->prepare("SELECT product_brand_id, product_brand_name FROM product_brand LIMIT ?");
    $brand_stmt->bindValue(1, $brandLimit, PDO::PARAM_INT);
    $brand_stmt->execute();
    $brandResults = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("ข้อผิดพลาดฐานข้อมูล: " . $e->getMessage());
    die("เกิดข้อผิดพลาดขณะประมวลผลคำขอของคุณ: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการสินค้า</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style/product_list.css">
</head>

<body>
    <div class="px-4 py-6 sm:px-6 sm:py-8 animate-fade-in">
        <!-- Header Section -->
        <div class=" glass-effect rounded-xl p-5 mb-6 card-shadow">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 gradient-blue rounded-lg flex items-center justify-center">
                        <i class="fas fa-boxes text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">รายการสินค้า</h1>
                        <p class="text-blue-600 text-sm">จัดการข้อมูลสินค้าทั้งหมด</p>
                    </div>
                </div>
                <div class="flex flex-col md:flex md:flex-row md:items-center gap-3 w-full sm:w-auto">
                    <div class="bg-blue-100 px-3 py-1.5 rounded border border-blue-200 text-sm">
                        <i class="fas fa-list mr-1 text-blue-600"></i>
                        <span class="font-semibold text-blue-800"><?php echo $total_records; ?></span>
                        <span class="text-blue-600">รายการ</span>
                    </div>
                    <a href="/?page=product_insert" class="btn-primary text-white standard-button rounded-lg font-medium hover-lift">
                        <i class="fas fa-plus mr-1"></i>เพิ่มสินค้า
                    </a>
                    <div class="relative flex-1 sm:w-64">
                        <form action="" method="GET" id="searchForm" class="relative">
                            <input type="hidden" name="page" value="product_list">
                            <input type="hidden" name="page_num" value="1">

                            <input
                                type="text"
                                name="search"
                                id="searchInput"
                                placeholder="ค้นหาสินค้า..."
                                value="<?php echo htmlspecialchars($search); ?>"
                                class="w-full pl-10 pr-10 py-2 text-sm border border-blue-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white/90 placeholder-gray-400">

                            <!-- ไอคอนค้นหา -->
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-sm pointer-events-none"></i>

                            <!-- ปุ่มล้างการค้นหา -->
                            <button
                                type="button"
                                id="clearSearch"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-500 hover:text-red-500 transition duration-200"
                                style="display: <?php echo $search ? 'block' : 'none'; ?>;"
                                aria-label="ล้างการค้นหา">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>


        <!-- Controls Section -->
        <div class="glass-effect rounded-xl p-3 sm:p-4 mb-6 card-shadow">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <!-- Date Filter -->
                <div class="flex flex-col gap-3 w-full sm:w-auto sm:flex-row sm:items-center">
                    <h3 class="text-sm sm:text-base font-semibold text-gray-700 flex items-center gap-2 mb-2 sm:mb-0">
                        <i class="fas fa-calendar-alt text-blue-500 text-sm"></i>
                        กรองตามวันที่
                    </h3>
                    <div class="flex flex-col xs:flex-row gap-2 w-full sm:w-auto">
                        <input type="date" id="start_date" name="start_date"
                            class="standard-input-mobile sm:standard-input-sm border border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white/80"
                            value="<?php echo htmlspecialchars($start_date); ?>">
                        <input type="date" id="end_date" name="end_date"
                            class="standard-input-mobile sm:standard-input-sm border border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white/80"
                            value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                </div>

                <!-- Export Buttons -->
                <div class="flex flex-col xs:flex-row gap-2 w-full sm:w-auto border-t border-gray-200/50 pt-3 sm:border-t-0 sm:pt-0">
                    <button onclick="exportPDF()" class="standard-button-mobile sm:standard-button-sm bg-red-500 text-white rounded-lg hover:bg-red-600 transition hover-lift flex items-center justify-center gap-2">
                        <i class="fas fa-file-pdf"></i>
                        <span class="block sm:hidden">ส่งออก PDF</span>
                        <span class="hidden sm:block">PDF</span>
                    </button>
                    <button onclick="exportExcel()" class="standard-button-mobile sm:standard-button-sm bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition hover-lift flex items-center justify-center gap-2">
                        <i class="fas fa-file-excel"></i>
                        <span class="block sm:hidden">ส่งออก Excel</span>
                        <span class="hidden sm:block">Excel</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Filters Sidebar -->
            <div class="w-full lg:w-1/4">
                <div class="filter-card rounded-xl p-5 card-shadow">
                    <button id="toggleFilters" class="lg:hidden w-full bg-blue-50 text-blue-700 standard-button rounded-lg mb-3 flex items-center justify-between hover:bg-blue-100 transition text-sm">
                        <span class="font-medium">ตัวกรอง</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="filters" class="hidden lg:block">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                            <i class="fas fa-filter text-blue-500 text-sm"></i>
                            ค้นหาแบบละเอียด
                        </h2>
                        <div class="mb-6">
                            <h3 class="text-base font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                <i class="fas fa-tags text-blue-500 text-sm"></i>
                                หมวดหมู่สินค้า
                            </h3>
                            <div class="space-y-2">
                                <?php foreach ($typeResults as $typeRow): ?>
                                    <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-blue-50 transition cursor-pointer text-sm">
                                        <input type="checkbox" name="product_type[]" value="<?php echo $typeRow['product_type_id']; ?>"
                                            class="w-4 h-4 text-blue-600 border-blue-300 rounded focus:ring-blue-500"
                                            <?php echo in_array($typeRow['product_type_id'], $selected_types) ? 'checked' : ''; ?>
                                            onchange="updateFilters()">
                                        <span class="text-gray-700"><?php echo htmlspecialchars($typeRow['product_type_name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <div class="flex gap-3 mt-3">
                                    <?php if ($typeLimit < $totalTypeCount): ?>
                                        <form action="" method="POST" class="w-1/2">
                                            <input type="hidden" name="type_limit" value="<?php echo $typeLimit + 5; ?>">
                                            <input type="hidden" name="brand_limit" value="<?php echo $brandLimit; ?>">
                                            <input type="hidden" name="action" value="show_more_types">
                                            <button type="submit" class="w-full standard-button text-blue-600 hover:text-blue-800 text-sm hover:bg-blue-50 rounded-lg">แสดงเพิ่ม</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($typeLimit > 5): ?>
                                        <form action="" method="POST" class="w-1/2">
                                            <input type="hidden" name="type_limit" value="5">
                                            <input type="hidden" name="brand_limit" value="<?php echo $brandLimit; ?>">
                                            <input type="hidden" name="action" value="show_less_types">
                                            <button type="submit" class="w-full standard-button text-blue-600 hover:text-blue-800 text-sm hover:bg-blue-50 rounded-lg">แสดงน้อย</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mb-6">
                            <h3 class="text-base font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                <i class="fas fa-star text-blue-500 text-sm"></i>
                                แบรนด์สินค้า
                            </h3>
                            <div class="space-y-2">
                                <?php foreach ($brandResults as $brandRow): ?>
                                    <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-blue-50 transition cursor-pointer text-sm">
                                        <input type="checkbox" name="product_brand[]" value="<?php echo $brandRow['product_brand_id']; ?>"
                                            class="w-4 h-4 text-blue-600 border-blue-300 rounded focus:ring-blue-500"
                                            <?php echo in_array($brandRow['product_brand_id'], $selected_brands) ? 'checked' : ''; ?>
                                            onchange="updateFilters()">
                                        <span class="text-gray-700"><?php echo htmlspecialchars($brandRow['product_brand_name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <div class="flex gap-3 mt-3">
                                    <?php if ($brandLimit < $totalBrandCount): ?>
                                        <form action="" method="POST" class="w-1/2">
                                            <input type="hidden" name="brand_limit" value="<?php echo $brandLimit + 5; ?>">
                                            <input type="hidden" name="type_limit" value="<?php echo $typeLimit; ?>">
                                            <input type="hidden" name="action" value="show_more_brands">
                                            <button type="submit" class="w-full standard-button text-blue-600 hover:text-blue-800 text-sm hover:bg-blue-50 rounded-lg">แสดงเพิ่ม</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($brandLimit > 5): ?>
                                        <form action="" method="POST" class="w-1/2">
                                            <input type="hidden" name="brand_limit" value="5">
                                            <input type="hidden" name="type_limit" value="<?php echo $typeLimit; ?>">
                                            <input type="hidden" name="action" value="show_less_brands">
                                            <button type="submit" class="w-full standard-button text-blue-600 hover:text-blue-800 text-sm hover:bg-blue-50 rounded-lg">แสดงน้อย</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <button onclick="clearFilters()" class="w-full standard-button bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm">
                            <i class="fas fa-times mr-1"></i>ล้างตัวกรอง
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="w-full lg:w-3/4">
                <div class="glass-effect rounded-xl overflow-hidden card-shadow">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4 text-white">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <h2 class="text-lg font-bold flex items-center gap-2 ">
                                <i class="fas fa-table text-sm"></i>
                                ตารางสินค้า
                            </h2>
                            <div class="flex gap-2">
                                <a href="?page=product_list" class="standard-button bg-white/20 backdrop-blur-sm rounded-lg hover:bg-white/30 transition text-sm">แสดงทั้งหมด</a>
                                <a href="?page=product_list&mode=incomplete" class="standard-button bg-red-500/80 backdrop-blur-sm rounded-lg hover:bg-red-500 transition text-sm">ข้อมูลไม่ครบ</a>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
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
                                                        <a href="?page=product_list&id=<?php echo htmlspecialchars($product_list['product_id']); ?><?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>"
                                                            onclick="return confirm('ยืนยันการลบ?')"
                                                            class="bg-red-500 text-white px-3 py-1.5 rounded-lg hover:bg-red-600 transition text-sm hover-lift">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
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
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div id="notification" class="fixed top-5 right-5 w-72 bg-white border-l-4 border-blue-500 rounded-lg shadow-lg z-50 overflow-hidden hidden animate-fade-in">
            <div class="bg-blue-500 text-white px-4 py-2 font-semibold text-sm">การแจ้งเตือน</div>
            <div class="px-4 py-3 text-gray-700 text-sm" id="notificationMessage"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit_success')) {
                showNotification('แก้ไขสินค้าสำเร็จ!', 'success');
            } else if (urlParams.has('delete_success')) {
                showNotification('ลบสินค้าสำเร็จ!', 'success');
            }

            const searchInput = document.getElementById('searchInput');
            const clearSearch = document.getElementById('clearSearch');

            searchInput.addEventListener('input', function() {
                clearSearch.style.display = this.value.length > 0 ? 'block' : 'none';
            });

            clearSearch.addEventListener('click', function() {
                searchInput.value = '';
                clearSearch.style.display = 'none';
                searchInput.focus();
                updateFilters();
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    updateFilters();
                }
                if (e.key === 'Enter') {
                    updateFilters();
                }
            });

            const toggleFilters = document.getElementById('toggleFilters');
            const filters = document.getElementById('filters');

            toggleFilters.addEventListener('click', function() {
                filters.classList.toggle('hidden');
                const chevron = this.querySelector('i');
                chevron.classList.toggle('fa-chevron-down');
                chevron.classList.toggle('fa-chevron-up');
            });

            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateFilters);
            });

            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', updateFilters);
            });
        });

        function updateFilters() {
            const urlParams = new URLSearchParams();
            urlParams.set('page', 'product_list');
            urlParams.set('page_num', '1');
            <?php if ($incomplete_mode): ?>
                urlParams.set('mode', 'incomplete');
            <?php endif; ?>
            const productTypes = Array.from(document.querySelectorAll('input[name="product_type[]"]:checked')).map(cb => cb.value);
            if (productTypes.length > 0) {
                urlParams.set('product_type', productTypes.join(','));
            }
            const productBrands = Array.from(document.querySelectorAll('input[name="product_brand[]"]:checked')).map(cb => cb.value);
            if (productBrands.length > 0) {
                urlParams.set('product_brand', productBrands.join(','));
            }
            const searchInput = document.getElementById('searchInput').value.trim();
            if (searchInput) {
                urlParams.set('search', searchInput);
            }
            const startDate = document.getElementById('start_date').value;
            if (startDate) {
                urlParams.set('start_date', startDate);
            }
            const endDate = document.getElementById('end_date').value;
            if (endDate) {
                urlParams.set('end_date', endDate);
            }
            window.location.search = urlParams.toString();
        }

        function clearFilters() {
            const urlParams = new URLSearchParams();
            urlParams.set('page', 'product_list');
            urlParams.set('page_num', '1');
            <?php if ($incomplete_mode): ?>
                urlParams.set('mode', 'incomplete');
            <?php endif; ?>
            window.location.search = urlParams.toString();
        }

        function goToPage(pageNum) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', 'product_list');
            urlParams.set('page_num', pageNum);
            <?php if ($incomplete_mode): ?>
                urlParams.set('mode', 'incomplete');
            <?php endif; ?>
            const productTypes = Array.from(document.querySelectorAll('input[name="product_type[]"]:checked')).map(cb => cb.value);
            if (productTypes.length > 0) {
                urlParams.set('product_type', productTypes.join(','));
            }
            const productBrands = Array.from(document.querySelectorAll('input[name="product_brand[]"]:checked')).map(cb => cb.value);
            if (productBrands.length > 0) {
                urlParams.set('product_brand', productBrands.join(','));
            }
            const searchInput = document.getElementById('searchInput').value.trim();
            if (searchInput) {
                urlParams.set('search', searchInput);
            }
            const startDate = document.getElementById('start_date').value;
            if (startDate) {
                urlParams.set('start_date', startDate);
            }
            const endDate = document.getElementById('end_date').value;
            if (endDate) {
                urlParams.set('end_date', endDate);
            }
            window.location.search = urlParams.toString();
        }

        function exportPDF() {
            showNotification('กำลังส่งออก PDF...', 'info');
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const mode = '<?php echo $incomplete_mode ? 'incomplete' : 'complete'; ?>';
            let url = `/page/product/report/export_product_pdf.php?mode=${mode}`;
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            showNotification('กำลังส่งออก Excel...', 'info');
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const mode = '<?php echo $incomplete_mode ? 'incomplete' : 'complete'; ?>';
            let url = `/page/product/report/export_product_excel.php?mode=${mode}`;
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const messageElement = document.getElementById('notificationMessage');
            messageElement.textContent = message;
            notification.classList.remove('hidden');
            notification.querySelector('div:first-child').className = `bg-${type === 'success' ? 'blue-500' : 'yellow-500'} text-white px-4 py-2 font-semibold text-sm`;
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 3000);
        }
    </script>
</body>

</html>