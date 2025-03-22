<?php
// เรียกไฟล์ที่ใช้ในการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '../../../../config/database_server.php';

// ตรวจสอบว่ามีการระบุประเภท (tabel) และ ID (id) มาหรือไม่ ถ้าไม่เจอให้หยุดการทำงาน
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

// ดึงค่าตัวแปรจาก URL เพื่อระบุประเภทและ ID
$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false; // เช็คว่าอยู่ในโหมดแก้ไขหรือไม่

// ตรวจสอบประเภทที่กำหนดมาและทำการ query เพื่อดึงข้อมูลสินค้า
switch ($tabel) {
    case 'product':
        // เตรียม query เพื่อดึงข้อมูลสินค้าพร้อมข้อมูลประเภทและแบรนด์
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
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    echo "ไม่พบข้อมูล";
    exit();
}

// ดึงข้อมูลประเภทสินค้า
$queryTypes = "SELECT product_type_id, product_type_name FROM product_type";
$typeResults = $pdo->query($queryTypes);

// ดึงข้อมูลแบรนด์สินค้า
$querybrandTypes = "SELECT product_brand_id, product_brand_name FROM product_brand";
$brandResults = $pdo->query($querybrandTypes);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['product_id'];
    $barcode = $_POST['barcode'];
    $name = $_POST['name'];
    $price1 = $_POST['price_1'];
    $price2 = $_POST['price_2'];
    $price3 = $_POST['price_3'];
    $price4 = $_POST['price_4'];
    $capital = $_POST['capital'];
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
    <title>รายละเอียด</title>
</head>

<body>
    <div class="detail-body  xl:mx-[20rem]">
        <div class="bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md ">
            <!-- เปลี่ยนสีหัวข้อเมื่ออยู่ในโหมดแก้ไข -->
            <h2 class=" bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed]<?php echo $is_update ? '' : ''; ?> text-gray-800 text-2xl font-bold px-3 py-2 border border-[#c8d8ed] shadow">
                <?php echo $is_update ? 'กำลังแก้ไขสินค้า' : 'รายละเอียดสินค้า'; ?>
            </h2>


            <form class="detail-form " action="../../page/product/product_update.php" method="POST">
                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">

                <table class="detail-table  <?php echo $is_update ? '' : ''; ?>">
                    <?php if ($tabel === 'product'): ?>
                        <tr>
                            <th>รหัสสินค้า:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <input type="hidden" name="id" value="<?php echo ($product['product_id']); ?>" readonly>
                                    <span class="text-gray-600 px-2"><?php echo ($product['product_id']); ?></span>
                                <?php else: ?>
                                    <?php echo ($product['product_id']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Similar modifications for other input fields -->
                        <tr>
                            <th>ชื่อสินค้า:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <input type="text" name="name" id="product-name" class="w-full border rounded  px-2 focus:outline-yellow-500 autofocus"
                                        value="<?php echo ($product['product_name']); ?>">
                                <?php else: ?>
                                    <?php echo ($product['product_name']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Repeat similar pattern for price and other fields -->
                        <tr>
                            <th>ราคาขายที่ 1:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <input type="text" name="price_1" class="w-full border rounded px-2 focus:outline-yellow-500" value="<?php echo ($product['product_price1']); ?>">
                                <?php else: ?>
                                    <?php echo ($product['product_price1']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ราคาขายที่ 2:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <input type="text" name="price_2" class="w-full border rounded px-2 focus:outline-yellow-500" value="<?php echo ($product['product_price2']); ?>">
                                <?php else: ?>
                                    <?php echo ($product['product_price2']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ราคาขายที่ 3:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <input type="text" name="price_3" class="w-full border rounded px-2 focus:outline-yellow-500" value="<?php echo ($product['product_price3']); ?>">
                                <?php else: ?>
                                    <?php echo ($product['product_price3']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ราคาขายที่ 4:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <input type="text" name="price_4" class="w-full border rounded px-2 focus:outline-yellow-500" value="<?php echo ($product['product_price4']); ?>">
                                <?php else: ?>
                                    <?php echo ($product['product_price4']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Dropdown for product type and brand -->
                        <tr>
                            <th onclick="window.location.href='?page=product_type&tabel=product_type&id=1'">ประเภทสินค้า:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <select name="typename" class="w-full border rounded px-2 py-1">
                                        <option value="<?php echo ($product['product_type_id']); ?>" selected>
                                            <?php echo ($product['product_type_name']); ?>
                                        </option>
                                        <?php while ($typeRow = $typeResults->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo ($typeRow['product_type_id']); ?>">
                                                <?php echo ($typeRow['product_type_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <?php else: ?>
                                    <?php echo ($product['product_type_name']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th onclick="window.location.href='?page=product_brand&tabel=product_brand&id=1'">แบรนด์สินค้า:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <select name="typebrandname" class="w-full border rounded px-2 py-1">
                                        <option value="<?php echo ($product['product_brand_id']); ?>" selected>
                                            <?php echo ($product['product_brand_name']); ?>
                                        </option>
                                        <?php while ($brandRow = $brandResults->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo ($brandRow['product_brand_id']); ?>">
                                                <?php echo ($brandRow['product_brand_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <?php else: ?>
                                    <?php echo ($product['product_brand_name']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>

                <!-- ปุ่มต่างๆ -->
                <div class="detail-action flex gap-2  justify-end<?php echo $is_update ? '' : ''; ?> mx-3 mb-4">
                    <?php if ($is_update): ?>
                        <div class="flex gap-2 mt-4 justify-end">
                            <a href="/?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>"
                                class="w-full p-2 px-5 text-center bg-red-500 text-white border rounded-lg shadow-lg hover:bg-red-600">
                                ยกเลิก
                            </a>
                            <button type="submit"
                                class="w-full p-2 px-5 bg-green-500 text-white border rounded-lg shadow-lg hover:bg-green-600">
                                บันทึก
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="/?page=product_list"
                            class="mt-4 p-2 px-6 bg-gray-700 text-white border rounded-lg shadow-lg mr-2 hover:bg-gray-800">
                            ย้อนกลับ
                        </a>
                        <a href="?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>&is_update=1"
                            class="mt-4 p-2 px-6 bg-blue-700 text-white border rounded-lg shadow-lg hover:bg-blue-800">
                            ต้องการแก้ไข
                        </a>
                    <?php endif; ?>
                </div>
            </form>
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