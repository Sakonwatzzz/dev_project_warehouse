<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once __DIR__ . '../../../db/warehouse_db.php';

if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    header("Location: product_detail.php?tabel=product&id=" . $product_id);
    exit();
}

$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false;

switch ($tabel) {
    case 'product':
        $stmt = $pdo->prepare("
            SELECT product.*, 
                   product_type.product_type_name, 
                   product_brand.product_brand_name 
            FROM product 
            INNER JOIN product_type ON product.product_type_id = product_type.product_type_id 
            INNER JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id 
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

// Query to fetch product types
$queryTypes = "SELECT product_type_id, product_type_name FROM product_type";
$typeResults = $conn->query($queryTypes);

// Query to fetch product brands
$querybrandTypes = "SELECT product_brand_id, product_brand_name FROM product_brand";
$brandResults = $conn->query($querybrandTypes)
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียด</title>
    <link rel="stylesheet" href="../../style/detail.css"> <!-- ลิงก์ไปยังไฟล์ CSS เพื่อจัดการสไตล์ของหน้าเว็บ -->
</head>

<body>
    <div class="main-body">
        <!-- แสดงหัวเรื่องของหน้าเว็บโดยนำประเภทข้อมูลที่เรียกจาก URL มาทำเป็นหัวเรื่อง -->
        <h1 class="title">รายละเอียด <?php echo $tabel; ?></h1>

        <!-- ฟอร์มที่ใช้ในการส่งข้อมูลสำหรับอัพเดตรายการสินค้า โดยจะส่งไปยังไฟล์ `product_update.php` -->
        <form action="product_update.php" method="POST">
            <!-- กำหนดค่า product_id เป็น input ซ่อน (hidden) เพื่อใช้ส่ง ID ของสินค้านี้ในฟอร์ม -->
            <input type="hidden" name="product_id" value="<?php echo $product_detail['product_id']; ?>">

            <!-- ตารางแสดงรายละเอียดสินค้า -->
            <table class="details-table">
                <?php if ($tabel === 'product'): ?>
                    <!-- ตรวจสอบว่าประเภทข้อมูลเป็น 'product' เพื่อแสดงรายละเอียดสินค้าเท่านั้น -->
                    
                    <tr>
                        <th>รหัสสินค้า:</th>
                        <!-- แสดงรหัสสินค้า -->
                        <input type="id" name="id" value="<?php echo $product['product_id']; ?>">
                        </tr>

                    <th>บาร์โค้ด:</th>
                    <td>
                        <?php if ($is_update) { ?>
                            <input type="text" name="barcode" value="<?php echo $product['product_barcode']; ?>">
                        <?php } else { ?>
                            <?php echo $product['product_barcode']; ?>
                        <?php } ?>
                    </td>
                    </tr>
                    <tr>
                        <th>ชื่อสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                <input type="text" name="name" value="<?php echo $product['product_name']; ?>">
                            <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                <?php echo $product['product_name']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                    <tr>
                        <th>ราคาที่ 1:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="price_1" value="<?php echo $product['product_price1']; ?>">
                            <?php else: ?>
                                <?php echo $product['product_price1']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่ 2:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="price_2" value="<?php echo $product['product_price2']; ?>">
                            <?php else: ?>
                                <?php echo $product['product_price2']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่ 3:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="price_3" value="<?php echo $product['product_price3']; ?>">
                            <?php else: ?>
                                <?php echo $product['product_price3']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่ 4:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="price_4" value="<?php echo $product['product_price4']; ?>">
                            <?php else: ?>
                                <?php echo $product['product_price4']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาทุน:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="capital" value="<?php echo $product['product_capital']; ?>">
                            <?php else: ?>
                                <?php echo $product['product_capital']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                    <tr>
                    <th>ประเภทสินค้า:</th>
                    <td>
                        <?php if ($is_update && $typeResults->num_rows > 0): ?>
                            <select name="typename">
                                <option value="<?php echo htmlspecialchars($product['product_type_id']); ?>" selected>
                                    <?php echo htmlspecialchars($product['product_type_name']); ?>
                                </option>
                                <?php while ($typeRow = $typeResults->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($typeRow['product_type_id']); ?>">
                                        <?php echo htmlspecialchars($typeRow['product_type_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        <?php else: ?>
                            <?php echo htmlspecialchars($product['product_type_name']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th>แบรนด์สินค้า:</th>
                    <td>
                        <?php if ($is_update && $brandResults->num_rows > 0): ?>
                            <select name="typebrandname">
                                <option value="<?php echo htmlspecialchars($product['product_brand_id']); ?>" selected>
                                    <?php echo htmlspecialchars($product['product_brand_name']); ?>
                                </option>
                                <?php while ($brandRow = $brandResults->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($brandRow['product_brand_id']); ?>">
                                        <?php echo htmlspecialchars($brandRow['product_brand_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        <?php else: ?>
                            <?php echo htmlspecialchars($product['product_brand_name']); ?>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php endif; ?>
            </table>

            <!-- แสดงปุ่ม "บันทึก" เฉพาะในโหมดแก้ไขเท่านั้น -->
            <?php if ($is_update): ?>
                <button class="submit-button" type="submit">บันทึก </button>

            <?php endif; ?>
        </form>

        <!-- ลิงก์กลับไปยังหน้ารายการสินค้า, หน้ารายละเอียด และหน้าสำหรับแก้ไข -->
        <a href="/?page=bar_<?php echo $tabel; ?>">กลับไปหน้ารายการสินค้า <?php echo $tabel; ?></a>
        <a href="product_detail.php?tabel=<?php echo $tabel; ?>&id=<?php echo $product['product_id']; ?>">กลับไปหน้ารายละเอียด
            <?php echo $tabel; ?></a>
        <a href="product_detail.php?tabel=<?php echo $tabel; ?>&id=<?php echo $product['product_id']; ?>&is_update=1">ไปหน้าแก้ไข
            <?php echo $tabel; ?></a>
    </div>
</body>

</html>