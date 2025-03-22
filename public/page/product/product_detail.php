<?php
// ไฟล์: product_detail.php
// จุดประสงค์: แสดงและแก้ไขรายละเอียดสินค้า

// ส่วนที่ 1: การเชื่อมต่อและเตรียมข้อมูล
// ===================================

// เรียกไฟล์ที่ใช้ในการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '../../../../config/database_server.php';

// ตรวจสอบพารามิเตอร์ที่จำเป็น
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

// ดึงค่าตัวแปรจาก URL
$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false; // เช็คว่าอยู่ในโหมดแก้ไขหรือไม่

// ส่วนที่ 2: การดึงข้อมูลตามประเภท
// ===========================

// ตรวจสอบประเภทและดึงข้อมูลสินค้า
$product = fetchProductData($pdo, $tabel, $id);
if (!$product) {
    echo "ไม่พบข้อมูล";
    exit();
}

// ดึงข้อมูลคลังสินค้าและจำนวนสินค้าทั้งหมด
$warehouses = fetchProductWarehouses($pdo, $id);
$totalProductQty = calculateTotalProductQuantity($pdo, $id);

// ดึงข้อมูลประเภทและแบรนด์สินค้า
$typeResults = $pdo->query("SELECT product_type_id, product_type_name FROM product_type");
$brandResults = $pdo->query("SELECT product_brand_id, product_brand_name FROM product_brand");

// ส่วนที่ 3: การจัดการการอัปเดตข้อมูล
// =============================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    updateProductData($pdo);
}

// ส่วนที่ 4: ฟังก์ชันต่างๆ
// ====================

/**
 * ดึงข้อมูลสินค้าตามประเภทและ ID
 * @param PDO $pdo การเชื่อมต่อฐานข้อมูล
 * @param string $tabel ประเภทของข้อมูล
 * @param int $id ID ของข้อมูล
 * @return array|false ข้อมูลสินค้าหรือ false หากไม่พบ
 */
function fetchProductData($pdo, $tabel, $id)
{
    switch ($tabel) {
        case 'product':
            $stmt = $pdo->prepare("
            SELECT product.*, 
            product_type.product_type_name, 
            product_brand.product_brand_name 
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

/**
 * ดึงข้อมูลคลังสินค้าที่มีสินค้านี้อยู่
 * @param PDO $pdo การเชื่อมต่อฐานข้อมูล
 * @param int $id ID ของสินค้า
 * @return array ข้อมูลคลังสินค้า
 */
function fetchProductWarehouses($pdo, $id)
{
    $warehouseQuery = $pdo->prepare("
        SELECT w.warehouse_id, w.warehouse_name, SUM(lp.lot_product_remain) as total_qty 
        FROM warehouse w
        JOIN lot l ON w.warehouse_id = l.warehouse_id
        JOIN lot_product lp ON l.lot_id = lp.lot_id
        WHERE lp.product_id = ?
        GROUP BY w.warehouse_id, w.warehouse_name
        ORDER BY w.warehouse_name
    ");
    $warehouseQuery->execute([$id]);
    return $warehouseQuery->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * คำนวณจำนวนสินค้าทั้งหมดจากทุกคลัง
 * @param PDO $pdo การเชื่อมต่อฐานข้อมูล
 * @param int $id ID ของสินค้า
 * @return int จำนวนสินค้าทั้งหมด
 */
function calculateTotalProductQuantity($pdo, $id)
{
    $totalQuery = $pdo->prepare("
        SELECT SUM(lp.lot_product_remain) as total_product_qty
        FROM lot_product lp
        JOIN lot l ON lp.lot_id = l.lot_id
        WHERE lp.product_id = ?
    ");
    $totalQuery->execute([$id]);
    $totalProduct = $totalQuery->fetch(PDO::FETCH_ASSOC);
    return $totalProduct['total_product_qty'] ?? 0;
}

/**
 * อัปเดตข้อมูลสินค้า
 * @param PDO $pdo การเชื่อมต่อฐานข้อมูล
 */
function updateProductData($pdo)
{
    $id = $_POST['product_id'];
    $name = $_POST['name'];
    $price1 = $_POST['price_1'];
    $price2 = $_POST['price_2'];
    $price3 = $_POST['price_3'];
    $price4 = $_POST['price_4'];
    $capital = $_POST['capital'] ?? 0;
    $type = $_POST['typename'];
    $brand = $_POST['typebrandname'];

    $stmt = $pdo->prepare("
        UPDATE product 
        SET product_name = ?, product_price1 = ?, product_price2 = ?, 
            product_price3 = ?, product_price4 = ?, product_capital = ?, product_type_id = ?, 
            product_brand_id = ? 
        WHERE product_id = ?
    ");
    $stmt->execute([$name, $price1, $price2, $price3, $price4, $capital, $type, $brand, $id]);

    // รีไดเรกต์ไปที่หน้ารายการหลังอัปเดตสำเร็จ
    header("Location: ?page=product_detail&tabel=product&id={$id}&success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/detail.css">
    <link href="../../output.css" rel="stylesheet">
    <title><?php echo $is_update ? 'แก้ไขสินค้า' : 'รายละเอียดสินค้า'; ?></title>
</head>

<body>
    <div class="detail-body xl:mx-[20rem]">
        <!-- หัวข้อหลัก -->
        <h2 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-2xl font-bold px-3 py-2 border border-[#c8d8ed] shadow mb-4 rounded-md">
            <?php echo $is_update ? 'กำลังแก้ไขสินค้า' : 'รายละเอียดสินค้า'; ?>
        </h2>

        <!-- เนื้อหาหลัก - แบ่งเป็น 2 คอลัมน์ -->
        <div class="flex flex-col md:flex-row gap-4">
            <!-- คอลัมน์ซ้าย: ข้อมูลคลังสินค้า -->
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

            <!-- คอลัมน์ขวา: รายละเอียดสินค้า -->
            <div class="w-full <?php echo (!$is_update) ? 'md:w-2/3' : ''; ?>">
                <div class="bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold px-3 py-2 border border-[#c8d8ed] shadow">
                        ข้อมูลสินค้า
                    </h3>

                    <form class="detail-form" action="../../page/product/product_update.php" method="POST">
                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">

                        <!-- ตารางแสดงรายละเอียดสินค้า -->
                        <table class="detail-table w-full <?php echo $is_update ? '' : ''; ?>">
                            <?php if ($tabel === 'product'): ?>
                                <!-- รหัสสินค้า -->
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

                                <!-- ชื่อสินค้า -->
                                <tr>
                                    <th class="p-2 border bg-gray-50">ชื่อสินค้า:</th>
                                    <td class="p-2 border">
                                        <?php if ($is_update): ?>
                                            <input type="text" name="name" id="product-name" class="w-full border rounded px-2 focus:outline-yellow-500 autofocus"
                                                value="<?php echo htmlspecialchars($product['product_name']); ?>">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- ราคาขาย -->
                                <?php
                                $priceFields = [
                                    ['field' => 'price_1', 'display' => 'ราคาขายที่ 1', 'value' => 'product_price1'],
                                    ['field' => 'price_2', 'display' => 'ราคาขายที่ 2', 'value' => 'product_price2'],
                                    ['field' => 'price_3', 'display' => 'ราคาขายที่ 3', 'value' => 'product_price3'],
                                    ['field' => 'price_4', 'display' => 'ราคาขายที่ 4', 'value' => 'product_price4']
                                ];

                                foreach ($priceFields as $price): ?>
                                    <tr>
                                        <th class="p-2 border bg-gray-50"><?php echo $price['display']; ?>:</th>
                                        <td class="p-2 border">
                                            <?php if ($is_update): ?>
                                                <input type="text" name="<?php echo $price['field']; ?>" class="w-full border rounded px-2 focus:outline-yellow-500"
                                                    value="<?php echo htmlspecialchars($product[$price['value']]); ?>">
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($product[$price['value']]); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- จำนวนสินค้าทั้งหมด -->
                                <tr>
                                    <th class="p-2 border bg-gray-50">สินค้าทั้งหมด:</th>
                                    <td>
                                        <?php if ($is_update): ?>
                                            <input type="text" name="total_qty" class="w-full border rounded px-2 focus:outline-yellow-500"
                                                value="<?php echo $totalProductQty; ?>" readonly>
                                        <?php else: ?>
                                            <?php echo $totalProductQty; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                               

                                <!-- ประเภทสินค้า -->
                                <tr>
                                    <th class="p-2 border bg-gray-50" onclick="window.location.href='?page=product_type&tabel=product_type&id=1'">ประเภทสินค้า:</th>
                                    <td class="p-2 border">
                                        <?php if ($is_update): ?>
                                            <select name="typename" class="w-full border rounded px-2 py-1">
                                                <option value="<?php echo htmlspecialchars($product['product_type_id']); ?>" selected>
                                                    <?php echo htmlspecialchars($product['product_type_name']); ?>
                                                </option>
                                                <?php while ($typeRow = $typeResults->fetch(PDO::FETCH_ASSOC)): ?>
                                                    <?php if ($typeRow['product_type_id'] != $product['product_type_id']): ?>
                                                        <option value="<?php echo htmlspecialchars($typeRow['product_type_id']); ?>">
                                                            <?php echo htmlspecialchars($typeRow['product_type_name']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endwhile; ?>
                                            </select>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($product['product_type_name']); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- แบรนด์สินค้า -->
                                <tr>
                                    <th class="p-2 border bg-gray-50" onclick="window.location.href='?page=product_brand&tabel=product_brand&id=1'">แบรนด์สินค้า:</th>
                                    <td class="p-2 border">
                                        <?php if ($is_update): ?>
                                            <select name="typebrandname" class="w-full border rounded px-2 py-1">
                                                <option value="<?php echo htmlspecialchars($product['product_brand_id']); ?>" selected>
                                                    <?php echo htmlspecialchars($product['product_brand_name']); ?>
                                                </option>
                                                <?php while ($brandRow = $brandResults->fetch(PDO::FETCH_ASSOC)): ?>
                                                    <?php if ($brandRow['product_brand_id'] != $product['product_brand_id']): ?>
                                                        <option value="<?php echo htmlspecialchars($brandRow['product_brand_id']); ?>">
                                                            <?php echo htmlspecialchars($brandRow['product_brand_name']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endwhile; ?>
                                            </select>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($product['product_brand_name']); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>

                        <!-- ปุ่มต่างๆ -->
                        <div class="detail-action flex gap-2 justify-end mx-3 my-4">
                            <?php if ($is_update): ?>
                                <a href="/?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>"
                                    class="p-2 px-5 text-center bg-red-500 text-white border rounded-lg shadow-lg hover:bg-red-600">
                                    ยกเลิก
                                </a>
                                <button type="submit"
                                    class="p-2 px-5 bg-green-500 text-white border rounded-lg shadow-lg hover:bg-green-600">
                                    บันทึก
                                </button>
                            <?php else: ?>
                                <a href="/?page=product_list"
                                    class="p-2 px-6 bg-gray-700 text-white border rounded-lg shadow-lg mr-2 hover:bg-gray-800">
                                    ย้อนกลับ
                                </a>
                                <a href="?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>&is_update=1"
                                    class="p-2 px-6 bg-blue-700 text-white border rounded-lg shadow-lg hover:bg-blue-800">
                                    ต้องการแก้ไข
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
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
</body>

</html>