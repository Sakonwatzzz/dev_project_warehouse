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
    $selected_types = isset($_GET['product_type']) && !empty($_GET['product_type'])
        ? array_filter(array_map('intval', explode(',', $_GET['product_type'])))
        : [];
    $selected_brands = isset($_GET['product_brand']) && !empty($_GET['product_brand'])
        ? array_filter(array_map('intval', explode(',', $_GET['product_brand'])))
        : [];

    // Handle POST request for updating product
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
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
                SET product_code = ?, 
                    product_name = ?, 
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
    $records_per_page = 20;
    $page = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;
    $offset = ($page - 1) * $records_per_page;

    // Build WHERE condition for filtering
    $where_conditions = [];
    $bind_params = [];

    if ($incomplete_mode) {
        $where_conditions[] = "(
            product.product_code = '' OR product.product_code IS NULL OR 
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

    $where_clause = !empty($where_conditions)
        ? 'WHERE ' . implode(' AND ', $where_conditions)
        : '';

    // Main query to fetch products
    $query = "SELECT 
        product.product_id, 
        product.product_code, 
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
    $bind_params[] = $offset;
    $bind_params[] = $records_per_page;

    foreach ($bind_params as $i => $param) {
        $stmt->bindValue($i + 1, $param, PDO::PARAM_INT);
    }

    $stmt->execute();
    $resultsproduct = $stmt;

    // คำนวณจำนวนสินค้าที่ไม่สมบูรณ์เมื่ออยู่ใน incomplete_mode
    if ($incomplete_mode) {
        $incomplete_query = "SELECT COUNT(*) FROM product WHERE 
            product_code IS NULL OR TRIM(product_code) = '' OR 
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
    $count_query = "SELECT COUNT(*) FROM product $where_clause";
    $count_stmt = $pdo->prepare($count_query);

    foreach ($bind_params as $i => $param) {
        if ($i < count($bind_params) - 2) { // Exclude offset and limit
            $count_stmt->bindValue($i + 1, $param, PDO::PARAM_INT);
        }
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
    die("An error occurred while processing your request.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Product list</title>
</head>

<body class="font-sans mx-20 mt-11">
    <div class="my-[4rem] mx-[5rem]">

        <!-- Mode Toggle and Add Product Button -->
        <div class="mt-6 pl-[20%]">
            <div class="flex items-center gap-6 pb-8">
                <?php if ($incomplete_mode): ?>
                    <h2 class="text-2xl font-bold">จำนวนสินค้าที่ไม่สมบูรณ์</h2>
                    <div class="w-[120px] p-2 bg-red-200 font-bold text-end border border-red-500 rounded-md ">
                        <?php echo isset($incomplete_count) ? $incomplete_count : '0'; ?>
                    </div>
                <?php else: ?>
                    <h2 class="text-2xl font-bold">จำนวนสินค้า</h2>
                    <div class="w-[120px] p-2 bg-blue-200 font-bold text-end border border-blue-500 rounded-md ">
                        <?php echo $count; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex items-center space-x-4">
                <div class="w-[80%] flex items-center space-x-4">
                    <div class="flex space-x-4">
                        <a href="?page=product_list"
                            class="px-4 py-2 rounded-md font-bold transition-all <?php echo !$incomplete_mode ? 'bg-gray-700 text-white' : 'bg-gray-200 text-gray-700 border border-gray-300'; ?>">
                            แสดงทั้งหมด
                        </a>
                        <a href="?page=product_list&mode=incomplete"
                            class="px-4 py-2 rounded-md font-bold transition-all <?php echo $incomplete_mode ? 'bg-red-600 text-white' : 'bg-red-100 text-red-600 border border-red-300'; ?>">
                            แสดงข้อมูลไม่ครบ
                        </a>
                    </div>
                    <div class="flex justify-center">
                        <a href="?page=product_insert"
                            class="flex items-center gap-2 bg-gradient-to-b from-green-500 to-green-700 text-white text-lg px-5 py-2 rounded-lg shadow-lg border border-green-700 transition duration-300 hover:bg-green-600 hover:shadow-xl">
                            <i class="fas fa-plus-circle"></i>
                            เพิ่มสินค้าใหม่
                        </a>
                    </div>
                </div>

                <div class=" w-full">
                    <div class="text-end">
                        <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1"
                            placeholder="ค้นหาสินค้า..."
                            class="w-[500px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div id="notification-edit" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white text-black border-2 border-gray-400 rounded-md w-96 h-32 shadow-lg z-50 hidden">
            <div class="bg-yellow-200 text-black font-bold p-2 rounded-t-md">การแจ้งเตือน</div>
            <div class="p-5 text-center text-lg">แก้ไขบุคลากรสำเร็จ!</div>
        </div>
        <div id="notification-delete" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white text-black border-2 border-gray-400 rounded-md w-96 h-32 shadow-lg z-50 hidden">
            <div class="bg-yellow-200 text-black font-bold p-2 rounded-t-md">การแจ้งเตือน</div>
            <div class="p-5 text-center text-lg">ลบสำเร็จ!</div>
        </div>

        <!-- Main Content -->
        <div class="flex">
            <!-- Filters -->
            <div class="w-1/5 px-6">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-4">
                    <i class="fa-solid fa-filter text-blue-500"></i>
                    <span>ค้นหาแบบละเอียด</span>
                </h1>

                <!-- Product Type Filter -->
                <div class="pt-2 pb-4 border-b border-gray-400">
                    <h2 class="text-lg font-semibold text-gray-700 mb-3">ค้นหาตามหมวดหมู่</h2>
                    <div class="flex flex-col gap-3">
                        <?php foreach ($typeResults as $typeRow): ?>
                            <div class="flex items-center gap-2 text-gray-600">
                                <input type="checkbox"
                                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    name="product_type[]" value="<?php echo $typeRow['product_type_id']; ?>"
                                    <?php echo in_array($typeRow['product_type_id'], $selected_types) ? 'checked' : ''; ?>>
                                <span><?php echo $typeRow['product_type_name']; ?></span>
                            </div>
                        <?php endforeach; ?>

                        <div class="flex justify-between mt-2 gap-2">
                            <?php if ($typeLimit < $totalTypeCount): ?>
                                <form action="" method="POST" class="w-1/2">
                                    <input type="hidden" name="type_limit" value="<?php echo $typeLimit + 5; ?>">
                                    <input type="hidden" name="brand_limit" value="<?php echo $brandLimit; ?>">
                                    <input type="hidden" name="action" value="show_more_types">
                                    <button type="submit"
                                        class="w-full py-2 px-3 bg-white border border-gray-300 rounded-md text-gray-700 text-sm hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors">แสดงเพิ่มเติม</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($typeLimit > 5): ?>
                                <form action="" method="POST" class="w-1/2">
                                    <input type="hidden" name="type_limit" value="5">
                                    <input type="hidden" name="brand_limit" value="<?php echo $brandLimit; ?>">
                                    <input type="hidden" name="action" value="show_less_types">
                                    <button type="submit"
                                        class="w-full py-2 px-3 bg-white border border-gray-300 rounded-md text-gray-700 text-sm hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors">แสดงน้อยลง</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Product Brand Filter -->
                <div class="pt-2 pb-4">
                    <h2 class="text-lg font-semibold text-gray-700 mb-3">ค้นหาตามแบรนด์</h2>
                    <div class="flex flex-col gap-3">
                        <?php foreach ($brandResults as $brandRow): ?>
                            <div class="flex items-center gap-2 text-gray-600">
                                <input type="checkbox"
                                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    name="product_brand[]" value="<?php echo $brandRow['product_brand_id']; ?>"
                                    <?php echo in_array($brandRow['product_brand_id'], $selected_brands) ? 'checked' : ''; ?>>
                                <span><?php echo $brandRow['product_brand_name']; ?></span>
                            </div>
                        <?php endforeach; ?>

                        <div class="flex justify-between mt-2 gap-2">
                            <?php if ($brandLimit < $totalBrandCount): ?>
                                <form action="" method="POST" class="w-1/2">
                                    <input type="hidden" name="brand_limit" value="<?php echo $brandLimit + 5; ?>">
                                    <input type="hidden" name="type_limit" value="<?php echo $typeLimit; ?>">
                                    <input type="hidden" name="action" value="show_more_brands">
                                    <button type="submit"
                                        class="w-full py-2 px-3 bg-white border border-gray-300 rounded-md text-gray-700 text-sm hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors">แสดงเพิ่มเติม</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($brandLimit > 5): ?>
                                <form action="" method="POST" class="w-1/2">
                                    <input type="hidden" name="brand_limit" value="5">
                                    <input type="hidden" name="type_limit" value="<?php echo $typeLimit; ?>">
                                    <input type="hidden" name="action" value="show_less_brands">
                                    <button type="submit"
                                        class="w-full py-2 px-3 bg-white border border-gray-300 rounded-md text-gray-700 text-sm hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors">แสดงน้อยลง</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Table -->
            <div class="w-4/5 flex flex-col gap-4">
                <div id="product-list-container"></div>
                <table class="mb-8 w-full border-2 border-gray-300 shadow-lg" id="dataTable">
                    <thead>
                        <tr class="">
                            <th class="px-3 py-2 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 border border-[#8f959b] shadow">รหัสบาร์โค้ดสินค้า</th>
                            <th class="px-3 py-2 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 border border-[#8f959b] shadow">ชื่อสินค้า</th>
                            <th class="px-3 py-2 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 border border-[#8f959b] shadow">ราคาทุน</th>
                            <th class="px-3 py-2 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 border border-[#8f959b] shadow">ราคาขาย</th>
                            <th class="px-3 py-2 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 border border-[#8f959b] shadow">ประเภทสินค้า</th>
                            <th class="px-3 py-2 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 border border-[#8f959b] shadow">แบรนด์สินค้า</th>
                            <th class="px-3 py-2 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 border border-[#8f959b] shadow">สถานะข้อมูล</th>
                            <th class="px-3 py-2 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 border border-[#8f959b] shadow">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultsproduct as $product_list): ?>
                            <?php
                            // ปรับเงื่อนไขให้รวมค่า 0 เป็นข้อมูลไม่ครบด้วย
                            $is_incomplete = empty(trim($product_list['product_name'])) || // ชื่อสินค้าต้องไม่ว่าง
                                ($product_list['product_price1'] === null || $product_list['product_price1'] === '' || $product_list['product_price1'] == 0) ||
                                ($product_list['product_price2'] === null || $product_list['product_price2'] === '' || $product_list['product_price2'] == 0) ||
                                ($product_list['product_price3'] === null || $product_list['product_price3'] === '' || $product_list['product_price3'] == 0) ||
                                ($product_list['product_price4'] === null || $product_list['product_price4'] === '' || $product_list['product_price4'] == 0) ||
                                ($product_list['product_capital'] === null || $product_list['product_capital'] === '' || $product_list['product_capital'] == 0) ||
                                empty($product_list['product_type_id']) || // product_type_id ถ้า 0 ไม่นับว่าครบ
                                empty($product_list['product_brand_id']); // product_brand_id ถ้า 0 ไม่นับว่าครบ
                            ?>
                            <tr class="hover:bg-gray-200 cursor-pointer <?php echo $is_incomplete ? 'bg-red-100' : 'bg-white'; ?>">
                                <td class="text-start px-2 border border-gray-300 bg-amber-200" hidden><?php echo ($product_list['product_id']); ?></td>
                                <td class="text-start px-2 border border-gray-300 bg-amber-200"><?php echo ($product_list['product_code']); ?></td>
                                <td class="text-start px-2 text-blue-600  border border-gray-300 hover:bg-blue-100 cursor-pointer  hover:text-blue-800 hover:underline  <?php echo empty($product_list['product_name']) ? 'text-red-600 font-bold' : ''; ?>"
                                    onclick="window.location.href='?page=product_detail&tabel=product&id=<?php echo ($product_list['product_id']) ?>'">
                                    <?php echo empty($product_list['product_name']) ? 'ไม่มีข้อมูล' : $product_list['product_name']; ?>
                                </td>
                                <td class="text-end px-2 border border-gray-300 <?php echo empty($product_list['product_capital']) ? 'text-red-600 font-bold' : ''; ?>">
                                    <?php echo empty($product_list['product_capital']) ? 'ไม่มีข้อมูล' : $product_list['product_capital']; ?>
                                </td>
                                <td class="text-end px-2 border border-gray-300 <?php echo empty($product_list['product_price4']) ? 'text-red-600 font-bold' : ''; ?>">
                                    <?php echo empty($product_list['product_price4']) ? 'ไม่มีข้อมูล' : $product_list['product_price4']; ?>
                                </td>
                                <td class="text-start px-2 border border-gray-300 <?php echo empty($product_list['product_type_id']) ? 'text-red-600 font-bold' : ''; ?>">
                                    <?php echo !empty($product_list['product_type_name']) ? $product_list['product_type_name'] : 'ไม่มีข้อมูล'; ?>
                                </td>
                                <td class="text-start px-2 border border-gray-300 <?php echo empty($product_list['product_brand_id']) ? 'text-red-600 font-bold' : ''; ?>">
                                    <?php echo !empty($product_list['product_brand_name']) ? $product_list['product_brand_name'] : 'ไม่มีข้อมูล'; ?>
                                </td>
                                <td class="text-center px-2 border border-gray-300">
                                    <?php if ($is_incomplete): ?>
                                        <span class="text-red-600 font-bold">ข้อมูลไม่ครบ</span>
                                    <?php else: ?>
                                        <span class="text-green-600 font-bold">ข้อมูลครบถ้วน</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center space-x-2 border border-gray-300">
                                    <a href="?page=product_update&id=<?php echo ($product_list['product_id']) ?>"
                                        class="inline-flex items-center px-3 py-1 bg-yellow-500 text-white text-sm font-medium rounded-md hover:bg-yellow-600 transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        แก้ไข
                                    </a>
                                    <a href="?page=product_list&id=<?php echo $product_list['product_id']; ?><?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>"
                                        onclick="return confirm('กรุณาตรวจสอบข้อมูลก่อนลบข้อมูล')"
                                        class="inline-flex items-center px-3 py-1 bg-red-500 text-white text-sm font-medium rounded-md hover:bg-red-600 transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a2 2 0 00-2 2h8a2 2 0 00-2-2m-4 0V3m-3 4h10"></path>
                                        </svg>
                                        ลบ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- No Data Message -->
        <?php if ($total_records == 0): ?>
            <div class="text-center py-4 text-red-600 font-bold">
                <?php if ($incomplete_mode): ?>
                    ไม่พบสินค้าที่มีข้อมูลไม่ครบ
                <?php else: ?>
                    ไม่พบข้อมูลสินค้า
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <div class="mt-5 text-center">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=product_list<?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>&page_num=<?php echo ($page - 1); ?>"
                        class="inline-block px-4 py-2 bg-blue-100 text-black rounded-md mx-1 hover:bg-gray-200">« ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="inline-block px-4 py-2 bg-gray-700 text-white rounded-md mx-1"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=product_list<?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>&page_num=<?php echo $i; ?>"
                            class="inline-block px-4 py-2 bg-blue-100 text-black rounded-md mx-1 hover:bg-gray-200"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=product_list<?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>&page_num=<?php echo ($page + 1); ?>"
                        class="inline-block px-4 py-2 bg-blue-100 text-black rounded-md mx-1 hover:bg-gray-200">ถัดไป »</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="product_type[]"], input[name="product_brand[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateFilters);
            });

            const searchInput = document.getElementById("searchInput");
            if (searchInput) searchInput.focus();

            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit_success')) showNotification('notification-edit');
            else if (urlParams.has('delete_success')) showNotification('notification-delete');
        });

        function updateFilters() {
            const selectedTypes = Array.from(document.querySelectorAll('input[name="product_type[]"]:checked')).map(cb => cb.value);
            const selectedBrands = Array.from(document.querySelectorAll('input[name="product_brand[]"]:checked')).map(cb => cb.value);

            let url = new URL(window.location.href);
            let params = new URLSearchParams(url.search);

            if (params.has('mode')) url.searchParams.set('mode', params.get('mode'));
            if (params.has('page_num')) url.searchParams.set('page_num', params.get('page_num'));

            if (selectedTypes.length > 0) url.searchParams.set('product_type', selectedTypes.join(','));
            else url.searchParams.delete('product_type');

            if (selectedBrands.length > 0) url.searchParams.set('product_brand', selectedBrands.join(','));
            else url.searchParams.delete('product_brand');

            window.location.href = url.toString();
        }

        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            let foundAny = false;

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName("td");
                let rowMatchesSearch = false;

                for (let j = 0; j < td.length; j++) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        rowMatchesSearch = true;
                        break;
                    }
                }
                tr[i].style.display = rowMatchesSearch ? "" : "none";
                if (rowMatchesSearch) foundAny = true;
            }

            let noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    noDataMessage = document.createElement("p");
                    noDataMessage.id = "noDataMessage";
                    noDataMessage.textContent = "ไม่พบข้อมูล";
                    noDataMessage.className = "text-center text-red-600 font-bold mt-2";
                    table.parentNode.insertBefore(noDataMessage, table.nextSibling);
                }
            } else if (noDataMessage) {
                noDataMessage.remove();
            }
        }

        function showNotification(id) {
            const notification = document.getElementById(id);
            notification.classList.remove('hidden');
            setTimeout(() => notification.classList.add('hidden'), 2000);
        }
    </script>
</body>

</html>