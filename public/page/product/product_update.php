<?php
require_once __DIR__ . '../../../../config/database_server.php';

if (!isset($_GET['id'])) {
    die("ระบุ ID ไม่ถูกต้อง");
}

$id = $_GET['id'];

// Fetch product types and brands
$typeResults = $pdo->query("SELECT product_type_id, product_type_name FROM product_type");
$brandResults = $pdo->query("SELECT product_brand_id, product_brand_name FROM product_brand");

// Fetch product data
function fetchProductData($pdo, $id)
{
    $stmt = $pdo->prepare("
        SELECT product.*, product_type.product_type_name, product_brand.product_brand_name 
        FROM product 
        LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id 
        LEFT JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id 
        WHERE product.product_id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$product = fetchProductData($pdo, $id);
if (!$product) {
    echo "ไม่พบข้อมูลสินค้า";
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_type') {
        $newTypeName = trim($_POST['product_type_name'] ?? '');
        if (!empty($newTypeName)) {
            $stmt = $pdo->prepare("INSERT INTO product_type (product_type_name) VALUES (?)");
            $stmt->execute([$newTypeName]);
            $newTypeId = $pdo->lastInsertId();
            header("Location: ?page=product_update&tabel=product&id={$id}&new_type_id={$newTypeId}");
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_brand') {
        $newBrandName = trim($_POST['product_brand_name'] ?? '');
        if (!empty($newBrandName)) {
            $stmt = $pdo->prepare("INSERT INTO product_brand (product_brand_name) VALUES (?)");
            $stmt->execute([$newBrandName]);
            $newBrandId = $pdo->lastInsertId();
            header("Location: ?page=product_update&tabel=product&id={$id}&new_brand_id={$newBrandId}");
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

$newTypeId = isset($_GET['new_type_id']) ? $_GET['new_type_id'] : null;
$newBrandId = isset($_GET['new_brand_id']) ? $_GET['new_brand_id'] : null;

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>แก้ไขข้อมูลสินค้า</title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <div class="bg-gray-50 border-2 border-gray-400 shadow-lg rounded-md">
            <h2 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-2xl font-bold p-4 border-b border-gray-300 rounded-t-md">
                แก้ไขข้อมูลสินค้า
            </h2>
            <form class="p-4 space-y-2" action="" method="POST">
                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">

                <div class="flex items-center">
                    <label class="w-1/3 pl-11 text-base font-medium text-gray-700">รหัสสินค้า:</label>
                    <input type="text" name="product_id" class="w-2/3 p-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600" value="<?php echo htmlspecialchars($product['product_id']); ?>" readonly>
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 pl-11 text-base font-medium text-gray-700">ชื่อสินค้า:</label>
                    <input type="text" name="name" class="w-2/3 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 pl-11 text-base font-medium text-gray-700">ราคาที่ 1:</label>
                    <input type="text" name="price_1" class="w-2/3 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" value="<?php echo htmlspecialchars($product['product_price1']); ?>">
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 pl-11 text-base font-medium text-gray-700">ราคาที่ 2:</label>
                    <input type="text" name="price_2" class="w-2/3 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" value="<?php echo htmlspecialchars($product['product_price2']); ?>">
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 pl-11 text-base font-medium text-gray-700">ราคาที่ 3:</label>
                    <input type="text" name="price_3" class="w-2/3 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" value="<?php echo htmlspecialchars($product['product_price3']); ?>">
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 pl-11 text-base font-medium text-gray-700">ราคาที่ 4:</label>
                    <input type="text" name="price_4" class="w-2/3 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" value="<?php echo htmlspecialchars($product['product_price4']); ?>">
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 pl-11 text-base font-medium text-gray-700">ราคาทุน:</label>
                    <input type="text" name="capital" class="w-2/3 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" value="<?php echo htmlspecialchars($product['product_capital']); ?>">
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 pl-11 text-base font-medium text-gray-700">ประเภทสินค้า:</label>
                    <div class="w-2/3 flex space-x-2">
                        <select name="typename" class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <?php while ($type = $typeResults->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $type['product_type_id']; ?>" <?php echo ($newTypeId && $type['product_type_id'] == $newTypeId) ? 'selected' : ($type['product_type_id'] == $product['product_type_id'] && !$newTypeId ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($type['product_type_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="button" class="w-[120px] bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md transition duration-200" onclick="document.getElementById('typeModal').style.display='flex'">เพิ่มใหม่</button>
                    </div>
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 pl-11 text-base font-medium text-gray-700">แบรนด์สินค้า:</label>
                    <div class="w-2/3 flex space-x-2">
                        <select name="typebrandname" class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <?php while ($brand = $brandResults->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $brand['product_brand_id']; ?>" <?php echo ($newBrandId && $brand['product_brand_id'] == $newBrandId) ? 'selected' : ($brand['product_brand_id'] == $product['product_brand_id'] && !$newBrandId ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($brand['product_brand_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="button" class=" w-[120px] bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md transition duration-200" onclick="document.getElementById('brandModal').style.display='flex'">เพิ่มใหม่</button>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <a href="/?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md transition duration-200">ยกเลิก</a>
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md transition duration-200">บันทึก</button>
                </div>
            </form>
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