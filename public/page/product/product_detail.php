<?php
require_once __DIR__ . '../../../../config/database_server.php';

if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false;
$newTypeId = isset($_GET['new_type_id']) ? $_GET['new_type_id'] : null;
$newBrandId = isset($_GET['new_brand_id']) ? $_GET['new_brand_id'] : null;

// Fetch product data
function fetchProductData($pdo, $tabel, $id)
{
    if ($tabel !== 'product') {
        die("ระบุประเภทไม่ถูกต้อง");
    }
    $stmt = $pdo->prepare("
        SELECT p.*, pt.product_type_name, pb.product_brand_name 
        FROM product p
        LEFT JOIN product_type pt ON p.product_type_id = pt.product_type_id 
        LEFT JOIN product_brand pb ON p.product_brand_id = pb.product_brand_id 
        WHERE p.product_id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch warehouse data
function fetchProductWarehouses($pdo, $id)
{
    $stmt = $pdo->prepare("
        SELECT w.warehouse_id, w.warehouse_name, SUM(lp.lot_product_remain) as total_qty 
        FROM warehouse w 
        JOIN lot l ON w.warehouse_id = l.warehouse_id 
        JOIN lot_product lp ON l.lot_id = lp.lot_id 
        WHERE lp.product_id = ? 
        GROUP BY w.warehouse_id, w.warehouse_name 
        ORDER BY w.warehouse_name
    ");
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate total product quantity
function calculateTotalProductQuantity($pdo, $id)
{
    $stmt = $pdo->prepare("
        SELECT SUM(lp.lot_product_remain) as total_product_qty 
        FROM lot_product lp 
        JOIN lot l ON lp.lot_id = l.lot_id 
        WHERE lp.product_id = ?
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total_product_qty'] ?? 0;
}

$product = fetchProductData($pdo, $tabel, $id);
if (!$product) {
    echo "ไม่พบข้อมูล";
    exit;
}

$warehouses = fetchProductWarehouses($pdo, $id);
$totalProductQty = calculateTotalProductQuantity($pdo, $id);

// Fetch types and brands
$typeResults = $pdo->query("SELECT product_type_id, product_type_name FROM product_type");
$brandResults = $pdo->query("SELECT product_brand_id, product_brand_name FROM product_brand");

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_type') {
        $newTypeName = trim($_POST['product_type_name'] ?? '');
        if (!empty($newTypeName)) {
            $stmt = $pdo->prepare("INSERT INTO product_type (product_type_name) VALUES (?)");
            $stmt->execute([$newTypeName]);
            $newTypeId = $pdo->lastInsertId();
            header("Location: ?page=product_detail&tabel=product&id={$id}&is_update=1&new_type_id={$newTypeId}");
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_brand') {
        $newBrandName = trim($_POST['product_brand_name'] ?? '');
        if (!empty($newBrandName)) {
            $stmt = $pdo->prepare("INSERT INTO product_brand (product_brand_name) VALUES (?)");
            $stmt->execute([$newBrandName]);
            $newBrandId = $pdo->lastInsertId();
            header("Location: ?page=product_detail&tabel=product&id={$id}&is_update=1&new_brand_id={$newBrandId}");
            exit;
        }
    } elseif (isset($_POST['product_id'])) {
        $id = $_POST['product_id'];
        $name = $_POST['name'] ?? '';
        $price1 = $_POST['price_1'] ?? 0;
        $price2 = $_POST['price_2'] ?? 0;
        $price3 = $_POST['price_3'] ?? 0;
        $price4 = $_POST['price_4'] ?? 0;
        $capital = $_POST['capital'] ?? 0;
        $type = $_POST['typename'] ?? '';
        $brand = $_POST['typebrandname'] ?? '';

        $stmt = $pdo->prepare("
            UPDATE product 
            SET product_name = ?, product_price1 = ?, product_price2 = ?, 
                product_price3 = ?, product_price4 = ?, product_capital = ?, 
                product_type_id = ?, product_brand_id = ? 
            WHERE product_id = ?
        ");
        $stmt->execute([$name, $price1, $price2, $price3, $price4, $capital, $type, $brand, $id]);

        header("Location: ?page=product_detail&tabel=product&id={$id}&success=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title><?php echo $is_update ? 'แก้ไขสินค้า' : 'รายละเอียดสินค้า'; ?></title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-5xl p-4">
        <h2 class=" text-gray-800 text-2xl font-bold mt-8 mb-4">
            <?php echo $is_update ? 'กำลังแก้ไขสินค้า' : 'รายละเอียดสินค้า'; ?>
        </h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Warehouse Section (Visible only in view mode) -->
            <?php if (!$is_update): ?>
                <div class="w-full lg:w-5/12">
                    <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg h-full">
                        <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800  text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                            คลังสินค้าที่มีสินค้านี้
                        </h3>
                        <div class="p-4">
                            <?php if (count($warehouses) > 0): ?>
                                <table class="w-full border-collapse border border-gray-300 text-sm">
                                    <thead>
                                        <tr class="bg-blue-50">
                                            <th class="border border-gray-300 p-2 text-gray-700 text-left">ชื่อคลังสินค้า</th>
                                            <th class="border border-gray-300 p-2 text-gray-700 text-center">จำนวน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($warehouses as $warehouse): ?>
                                            <tr class="hover:bg-gray-100">
                                                <td class="border border-gray-300 p-2">
                                                    <a href="?page=warehouse_lot&warehouse_id=<?php echo $warehouse['warehouse_id']; ?>" class="text-blue-600 hover:underline">
                                                        <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                                    </a>
                                                </td>
                                                <td class="border border-gray-300 p-2 text-center"><?php echo $warehouse['total_qty']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-100 font-semibold">
                                            <td class="border border-gray-300 p-2 text-right text-gray-700">รวมทั้งหมด:</td>
                                            <td class="border border-gray-300 p-2 text-center text-gray-700"><?php echo $totalProductQty; ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-600 italic text-center py-4">ไม่พบสินค้านี้ในคลังสินค้าใดๆ</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Product Details Section -->
            <div class="w-full <?php echo !$is_update ? 'lg:w-7/12' : ''; ?>">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800  text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        ข้อมูลสินค้า
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">

                        <!-- Product Fields -->
                        <?php
                        $fields = [
                            ['label' => 'รหัสสินค้า', 'name' => 'product_id', 'value' => $product['product_id'], 'readonly' => true],
                            ['label' => 'ชื่อสินค้า', 'name' => 'name', 'value' => $product['product_name'], 'id' => 'product-name', 'required' => true],
                            ['label' => 'ราคาขายที่ 1', 'name' => 'price_1', 'value' => $product['product_price1']],
                            ['label' => 'ราคาขายที่ 2', 'name' => 'price_2', 'value' => $product['product_price2']],
                            ['label' => 'ราคาขายที่ 3', 'name' => 'price_3', 'value' => $product['product_price3']],
                            ['label' => 'ราคาขายที่ 4', 'name' => 'price_4', 'value' => $product['product_price4']],
                            ['label' => 'ราคาทุน', 'name' => 'capital', 'value' => $product['product_capital']],
                            ['label' => 'จำนวนสินค้าทั้งหมด', 'name' => 'total_qty', 'value' => $totalProductQty, 'readonly' => true],
                        ];

                        foreach ($fields as $field): ?>
                            <div class="flex items-center gap-4">
                                <label class="w-5/12 lg:w-4/12 text-base font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                                <div class="w-7/12 lg:w-8/12">
                                    <?php if ($is_update && !($field['readonly'] ?? false)): ?>
                                        <input type="text" name="<?php echo $field['name']; ?>" id="<?php echo $field['id'] ?? ''; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                            <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>>
                                    <?php elseif ($is_update && ($field['readonly'] ?? false)): ?>
                                        <input type="text" name="<?php echo $field['name']; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="w-full p-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600" readonly>
                                    <?php else: ?>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($field['value']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Product Type -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=product_type" class="text-blue-600 hover:underline">ประเภทสินค้า:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <?php if ($is_update): ?>
                                    <select name="typename" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                        <?php while ($type = $typeResults->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $type['product_type_id']; ?>"
                                                <?php echo ($newTypeId && $type['product_type_id'] == $newTypeId) ? 'selected' : ($type['product_type_id'] == $product['product_type_id'] && !$newTypeId ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($type['product_type_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button type="button" onclick="document.getElementById('typeModal').style.display='flex'"
                                        class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 whitespace-nowrap">
                                        เพิ่มใหม่
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-600"><?php echo htmlspecialchars($product['product_type_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Product Brand -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=product_brand" class="text-blue-600 hover:underline">แบรนด์สินค้า:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <?php if ($is_update): ?>
                                    <select name="typebrandname" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                        <?php while ($brand = $brandResults->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $brand['product_brand_id']; ?>"
                                                <?php echo ($newBrandId && $brand['product_brand_id'] == $newBrandId) ? 'selected' : ($brand['product_brand_id'] == $product['product_brand_id'] && !$newBrandId ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($brand['product_brand_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button type="button" onclick="document.getElementById('brandModal').style.display='flex'"
                                        class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 whitespace-nowrap">
                                        เพิ่มใหม่
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-600"><?php echo htmlspecialchars($product['product_brand_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4 mt-6">
                            <?php if ($is_update): ?>
                                <a href="/?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>"
                                    class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 transition duration-200">
                                    ยกเลิก
                                </a>
                                <button type="submit"
                                    class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 transition duration-200">
                                    บันทึก
                                </button>
                            <?php else: ?>
                                <a href="/?page=product_list"
                                    class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                                    ย้อนกลับ
                                </a>
                                <a href="?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>&is_update=1"
                                    class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                                    ต้องการแก้ไข
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Adding New Product Type -->
    <div id="typeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden  items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="relative p-6">
                <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl font-bold" onclick="document.getElementById('typeModal').style.display='none'">×</button>
                <h3 class="text-xl font-bold mb-4 text-gray-800">เพิ่มประเภทสินค้าใหม่</h3>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_type">
                    <div class="space-y-4">
                        <div>
                            <label for="product_type_name" class="block mb-2 font-medium text-gray-700">ชื่อประเภทสินค้า:</label>
                            <input type="text" id="product_type_name" name="product_type_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md transition duration-200" onclick="document.getElementById('typeModal').style.display='none'">ยกเลิก</button>
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md transition duration-200">เพิ่ม</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Adding New Product Brand -->
    <div id="brandModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden  items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="relative p-6">
                <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl font-bold" onclick="document.getElementById('brandModal').style.display='none'">×</button>
                <h3 class="text-xl font-bold mb-4 text-gray-800">เพิ่มแบรนด์สินค้าใหม่</h3>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_brand">
                    <div class="space-y-4">
                        <div>
                            <label for="product_brand_name" class="block mb-2 font-medium text-gray-700">ชื่อแบรนด์สินค้า:</label>
                            <input type="text" id="product_brand_name" name="product_brand_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md transition duration-200" onclick="document.getElementById('brandModal').style.display='none'">ยกเลิก</button>
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md transition duration-200">เพิ่ม</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($is_update): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('product-name').focus();
            });
        </script>
    <?php endif; ?>

    <script>
        window.onclick = (event) => {
            if (event.target === document.getElementById('typeModal')) {
                document.getElementById('typeModal').style.display = 'none';
            }
            if (event.target === document.getElementById('brandModal')) {
                document.getElementById('brandModal').style.display = 'none';
            }
        };
    </script>
</body>

</html>