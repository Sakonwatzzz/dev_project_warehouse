<?php
// ไฟล์: product_detail.php
require_once __DIR__ . '../../../../config/database_server.php';

if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false;

// ตัวแปรสำหรับเก็บ ID ที่เพิ่งเพิ่ม (ถ้ามี)
$newTypeId = isset($_GET['new_type_id']) ? $_GET['new_type_id'] : null;
$newBrandId = isset($_GET['new_brand_id']) ? $_GET['new_brand_id'] : null;

$product = fetchProductData($pdo, $tabel, $id);
if (!$product) {
    echo "ไม่พบข้อมูล";
    exit;
}

$warehouses = fetchProductWarehouses($pdo, $id);
$totalProductQty = calculateTotalProductQuantity($pdo, $id);

$typeResults = $pdo->query("SELECT product_type_id, product_type_name FROM product_type");
$brandResults = $pdo->query("SELECT product_brand_id, product_brand_name FROM product_brand");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_type') {
        $newTypeName = trim($_POST['new_type_name'] ?? '');
        if (!empty($newTypeName)) {
            $stmt = $pdo->prepare("INSERT INTO product_type (product_type_name) VALUES (?)");
            $stmt->execute([$newTypeName]);
            $newTypeId = $pdo->lastInsertId();
            // รีไดเรกต์พร้อมส่ง ID ใหม่กลับไป
            header("Location: ?page=product_detail&tabel=product&id={$id}&is_update=1&new_type_id={$newTypeId}");
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_brand') {
        $newBrandName = trim($_POST['new_brand_name'] ?? '');
        if (!empty($newBrandName)) {
            $stmt = $pdo->prepare("INSERT INTO product_brand (product_brand_name) VALUES (?)");
            $stmt->execute([$newBrandName]);
            $newBrandId = $pdo->lastInsertId();
            // รีไดเรกต์พร้อมส่ง ID ใหม่กลับไป
            header("Location: ?page=product_detail&tabel=product&id={$id}&is_update=1&new_brand_id={$newBrandId}");
            exit;
        }
    } elseif (isset($_POST['product_id'])) {
        updateProductData($pdo);
    }
}

function fetchProductData($pdo, $tabel, $id) {
    switch ($tabel) {
        case 'product':
            $stmt = $pdo->prepare("
                SELECT product.*, product_type.product_type_name, product_brand.product_brand_name 
                FROM product 
                LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id 
                LEFT JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id 
                WHERE product.product_id = ?
            ");
            break;
        default:
            die("ระบุประเภทไม่ถูกต้อง");
    }
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetchProductWarehouses($pdo, $id) {
    $warehouseQuery = $pdo->prepare("
        SELECT w.warehouse_id, w.warehouse_name, SUM(lp.lot_product_remain) as total_qty 
        FROM warehouse w JOIN lot l ON w.warehouse_id = l.warehouse_id 
        JOIN lot_product lp ON l.lot_id = lp.lot_id 
        WHERE lp.product_id = ? 
        GROUP BY w.warehouse_id, w.warehouse_name 
        ORDER BY w.warehouse_name
    ");
    $warehouseQuery->execute([$id]);
    return $warehouseQuery->fetchAll(PDO::FETCH_ASSOC);
}

function calculateTotalProductQuantity($pdo, $id) {
    $totalQuery = $pdo->prepare("
        SELECT SUM(lp.lot_product_remain) as total_product_qty 
        FROM lot_product lp JOIN lot l ON lp.lot_id = l.lot_id 
        WHERE lp.product_id = ?
    ");
    $totalQuery->execute([$id]);
    $totalProduct = $totalQuery->fetch(PDO::FETCH_ASSOC);
    return $totalProduct['total_product_qty'] ?? 0;
}

function updateProductData($pdo) {
    try {
        $id = $_POST['product_id'];
        $name = $_POST['name'] ?? '';
        $price1 = $_POST['price_1'] ?? 0;
        $price2 = $_POST['price_2'] ?? 0;
        $price3 = $_POST['price_3'] ?? 0;
        $price4 = $_POST['price_4'] ?? 0;
        $capital = $_POST['capital'] ?? 0;
        $type = $_POST['typename'] ?? '';
        $brand = $_POST['typebrandname'] ?? '';

        if (empty($id) || empty($name)) {
            throw new Exception("ข้อมูลไม่ครบถ้วน: product_id หรือ name หายไป");
        }

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
    } catch (Exception $e) {
        file_put_contents('debug.log', "Update Error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
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

<body>
    <div class="mx-auto xl:mx-[20rem] p-4">
        <h2 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-2xl font-bold px-3 py-2 border border-[#c8d8ed] shadow mb-4 rounded-md">
            <?php echo $is_update ? 'กำลังแก้ไขสินค้า' : 'รายละเอียดสินค้า'; ?>
        </h2>
        <div class="flex flex-col md:flex-row gap-4">
            <?php if (!$is_update): ?>
                <div class="w-full md:w-1/3">
                    <div class="bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md h-full">
                        <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold px-3 py-2 border border-[#c8d8ed] shadow">
                            คลังสินค้าที่มีสินค้านี้
                        </h3>
                        <div class="p-3">
                            <?php if (count($warehouses) > 0): ?>
                                <table class="w-full border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gradient-to-b from-[#e8f0fa] via-[#e8f0fa] to-[#e8f0fa]">
                                            <th class="border border-gray-300 p-2">ชื่อคลังสินค้า</th>
                                            <th class="border border-gray-300 p-2">จำนวน</th>
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
                                        <tr class="bg-gray-100">
                                            <th class="border border-gray-300 p-2 text-right">รวมทั้งหมด:</th>
                                            <th class="border border-gray-300 p-2 text-center"><?php echo $totalProductQty; ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-600 italic p-4">ไม่พบสินค้านี้ในคลังสินค้าใดๆ</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="w-full <?php echo (!$is_update) ? 'md:w-2/3' : ''; ?>">
                <div class="bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold px-3 py-2 border border-[#c8d8ed] shadow">
                        ข้อมูลสินค้า
                    </h3>
                    <form class="p-4" action="" method="POST">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                        <table class="w-full border-collapse">
                            <?php if ($tabel === 'product'): ?>
                                <tr>
                                    <th class="p-2 border bg-gray-50 w-1/3">รหัสสินค้า:</th>
                                    <td class="p-2 border">
                                        <?php if ($is_update): ?>
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($product['product_id']); ?>" readonly>
                                            <span class="text-gray-600 px-2"><?php echo htmlspecialchars($product['product_id']); ?></span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($product['product_id']); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="p-2 border bg-gray-50">ชื่อสินค้า:</th>
                                    <td class="border">
                                        <?php if ($is_update): ?>
                                            <input type="text" name="name" id="product-name" class="w-full border rounded px-2 py-2 focus:outline-yellow-500 focus:ring-1 focus:ring-yellow-500" value="<?php echo htmlspecialchars($product['product_name']); ?>">
                                        <?php else: ?>
                                            <span class="text-gray-600 px-2"><?php echo htmlspecialchars($product['product_name']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php $priceFields = [['field' => 'price_1', 'display' => 'ราคาขายที่ 1', 'value' => 'product_price1'], ['field' => 'price_2', 'display' => 'ราคาขายที่ 2', 'value' => 'product_price2'], ['field' => 'price_3', 'display' => 'ราคาขายที่ 3', 'value' => 'product_price3'], ['field' => 'price_4', 'display' => 'ราคาขายที่ 4', 'value' => 'product_price4']];
                                foreach ($priceFields as $price): ?>
                                    <tr>
                                        <th class="p-2 border bg-gray-50"><?php echo $price['display']; ?>:</th>
                                        <td class="border">
                                            <?php if ($is_update): ?>
                                                <input type="text" name="<?php echo $price['field']; ?>" class="w-full border rounded px-2 py-2 focus:outline-yellow-500 focus:ring-1 focus:ring-yellow-500" value="<?php echo htmlspecialchars($product[$price['value']]); ?>">
                                            <?php else: ?>
                                                <span class="text-gray-600 px-2"><?php echo htmlspecialchars($product[$price['value']]); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <th class="p-2 border bg-gray-50">จำนวนสินค้าทั้งหมด:</th>
                                    <td class="border">
                                        <?php if ($is_update): ?>
                                            <input type="text" name="total_qty" class="w-full border rounded px-2 py-2 focus:outline-yellow-500 focus:ring-1 focus:ring-yellow-500 bg-gray-100" value="<?php echo $totalProductQty; ?>" readonly>
                                        <?php else: ?>
                                            <span class="text-gray-600 px-2"><?php echo $totalProductQty; ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="p-2 text-blue-700 border bg-gray-50 cursor-pointer hover:bg-blue-200 transition duration-200" onclick="window.location.href='?page=product_type&tabel=product_type&id=1'">ประเภทสินค้า:</th>
                                    <td class="border">
                                        <div class="flex space-x-2">
                                            <?php if ($is_update): ?>
                                                <select name="typename" class="w-full border rounded px-2 py-2 focus:outline-yellow-500 focus:ring-1 focus:ring-yellow-500">
                                                    <?php
                                                    $typeRows = $typeResults->fetchAll(PDO::FETCH_ASSOC);
                                                    foreach ($typeRows as $typeRow):
                                                        $selected = ($newTypeId && $typeRow['product_type_id'] == $newTypeId) ? 'selected' : ($typeRow['product_type_id'] == $product['product_type_id'] && !$newTypeId ? 'selected' : '');
                                                    ?>
                                                        <option value="<?php echo htmlspecialchars($typeRow['product_type_id']); ?>" <?php echo $selected; ?>>
                                                            <?php echo htmlspecialchars($typeRow['product_type_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="w-[100px] bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-3 rounded transition duration-200" onclick="document.getElementById('typeModal').style.display='flex'">เพิ่มใหม่</button>
                                            <?php else: ?>
                                                <span class="text-gray-600 px-2"><?php echo htmlspecialchars($product['product_type_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="p-2 text-blue-700 border bg-gray-50 cursor-pointer hover:bg-blue-200 transition duration-200" onclick="window.location.href='?page=product_brand&tabel=product_brand&id=1'">แบรนด์สินค้า:</th>
                                    <td class="border">
                                        <div class="flex space-x-2">
                                            <?php if ($is_update): ?>
                                                <select name="typebrandname" class="w-full border rounded px-2 py-3 focus:outline-yellow-500 focus:ring-1 focus:ring-yellow-500">
                                                    <?php
                                                    $brandRows = $brandResults->fetchAll(PDO::FETCH_ASSOC);
                                                    foreach ($brandRows as $brandRow):
                                                        $selected = ($newBrandId && $brandRow['product_brand_id'] == $newBrandId) ? 'selected' : ($brandRow['product_brand_id'] == $product['product_brand_id'] && !$newBrandId ? 'selected' : '');
                                                    ?>
                                                        <option value="<?php echo htmlspecialchars($brandRow['product_brand_id']); ?>" <?php echo $selected; ?>>
                                                            <?php echo htmlspecialchars($brandRow['product_brand_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="w-[100px] bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded transition duration-200" onclick="document.getElementById('brandModal').style.display='flex'">เพิ่มใหม่</button>
                                            <?php else: ?>
                                                <span class="text-gray-600 px-2"><?php echo htmlspecialchars($product['product_brand_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                        <div class="flex gap-2 justify-end mt-6 mb-4 mx-3">
                            <?php if ($is_update): ?>
                                <a href="/?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>" class="py-2 px-5 text-center bg-red-500 text-white rounded-lg shadow-lg hover:bg-red-600 transition duration-200">ยกเลิก</a>
                                <button type="submit" class="py-2 px-5 bg-green-500 text-white rounded-lg shadow-lg hover:bg-green-600 transition duration-200">บันทึก</button>
                            <?php else: ?>
                                <a href="/?page=product_list" class="py-2 px-6 bg-gray-700 text-white rounded-lg shadow-lg mr-2 hover:bg-gray-800 transition duration-200">ย้อนกลับ</a>
                                <a href="?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>&is_update=1" class="py-2 px-6 bg-blue-700 text-white rounded-lg shadow-lg hover:bg-blue-800 transition duration-200">ต้องการแก้ไข</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Adding New Product Type -->
    <div id="typeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="relative p-6">
                <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl font-bold" onclick="document.getElementById('typeModal').style.display='none'">×</button>
                <h3 class="text-xl font-bold mb-4 text-gray-800">เพิ่มประเภทสินค้าใหม่</h3>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_type">
                    <div class="space-y-4">
                        <div>
                            <label for="new_type_name" class="block mb-2 font-medium text-gray-700">ชื่อประเภทสินค้า:</label>
                            <input type="text" id="new_type_name" name="new_type_name" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="flex justify-end pt-2">
                            <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2 transition duration-200" onclick="document.getElementById('typeModal').style.display='none'">ยกเลิก</button>
                            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-200">เพิ่ม</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Adding New Product Brand -->
    <div id="brandModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="relative p-6">
                <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl font-bold" onclick="document.getElementById('brandModal').style.display='none'">×</button>
                <h3 class="text-xl font-bold mb-4 text-gray-800">เพิ่มแบรนด์สินค้าใหม่</h3>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_brand">
                    <div class="space-y-4">
                        <div>
                            <label for="new_brand_name" class="block mb-2 font-medium text-gray-700">ชื่อแบรนด์สินค้า:</label>
                            <input type="text" id="new_brand_name" name="new_brand_name" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="flex justify-end pt-2">
                            <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2 transition duration-200" onclick="document.getElementById('brandModal').style.display='none'">ยกเลิก</button>
                            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-200">เพิ่ม</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($is_update): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('product-name').focus();
            });
        </script>
    <?php endif; ?>

    <script>
        window.onclick = function(event) {
            if (event.target == document.getElementById('typeModal')) {
                document.getElementById('typeModal').style.display = 'none';
            }
            if (event.target == document.getElementById('brandModal')) {
                document.getElementById('brandModal').style.display = 'none';
            }
        };
    </script>
</body>

</html>