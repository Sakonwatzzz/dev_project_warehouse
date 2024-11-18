<?php
// เรียกไฟล์ที่ใช้ในการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '../../../../config/database_server.php';

// ตรวจสอบว่ามีการระบุประเภท (tabel) และ ID (id) มาหรือไม่ ถ้าไม่เจอให้หยุดการทำงาน
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

// ตรวจสอบว่าเป็นการเรียกด้วย POST (เมื่อมีการส่งข้อมูลจากฟอร์ม)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    // ทำการรีไดเรกต์ไปที่หน้ารายละเอียดสินค้า
    header("Location: product_detail.php?tabel=product&id=" . $product_id);
    exit();
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
           INNER JOIN product_type ON product.product_type_id = product_type.product_type_id 
           INNER JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id 
            WHERE product.product_id = ?
        ");
        break;
    default:
        die("ระบุประเภทไม่ถูกต้อง");
}


// ดำเนินการ query ด้วย product_id ที่ระบุ
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC); // เก็บผลลัพธ์เป็นแถวข้อมูลในรูปแบบ array

// ตรวจสอบว่าพบข้อมูลสินค้าหรือไม่ ถ้าไม่พบให้หยุดการทำงาน
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
        SET product_barcode = ?, product_name = ?, product_price1 = ?, product_price2 = ?, 
            product_price3 = ?, product_price4 = ?, product_capital = ?, product_type_id = ?, 
            product_brand_id = ? 
        WHERE product_id = ?
    ");
    $stmt->execute([$barcode, $name, $price1, $price2, $price3, $price4, $capital, $type, $brand, $id]);

    // รีไดเรกต์ไปที่หน้ารายการหลังอัปเดตสำเร็จ
    header("Location: ?page=product_detail&tabel=product&id=25&success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/detail.css">
    <title>รายละเอียด</title>
</head>

<body>
    <div class="detail-body">
        <h2 class="detail-title">รายละเอียดสินค้า</h2>
        <form class="detail-form" action="../../page/product/product_update.php" method="POST">
            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">


            <table class="detail-table">
                <?php if ($tabel === 'product'): ?>

                    <tr>
                        <th>รหัสสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="id" value="<?php echo ($product['product_id']); ?>" readonly>
                            <?php else: ?>
                                <?php echo ($product['product_id']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>บาร์โค้ด:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="barcode" value="<?php echo ($product['product_barcode']); ?>">
                            <?php else: ?>
                                <?php echo ($product['product_barcode']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ชื่อสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="name" value="<?php echo ($product['product_name']); ?>">
                            <?php else: ?>
                                <?php echo ($product['product_name']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- ส่วนสำหรับแสดงราคาต่างๆ และทุน -->
                    <tr>
                        <th>ราคาที่ 1:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="price_1" value="<?php echo ($product['product_price1']); ?>">
                            <?php else: ?>
                                <?php echo ($product['product_price1']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่ 2:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="price_2" value="<?php echo ($product['product_price2']); ?>">
                            <?php else: ?>
                                <?php echo ($product['product_price2']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่ 3:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="price_3" value="<?php echo ($product['product_price3']); ?>">
                            <?php else: ?>
                                <?php echo ($product['product_price3']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาที่ 4:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="price_4" value="<?php echo ($product['product_price4']); ?>">
                            <?php else: ?>
                                <?php echo ($product['product_price4']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาทุน:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="capital" value="<?php echo ($product['product_capital']); ?>">
                            <?php else: ?>
                                <?php echo ($product['product_capital']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- เลือกประเภทสินค้า -->
                    <tr>
                        <th>ประเภทสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <select name="typename">
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

                    <!-- เลือกแบรนด์สินค้า -->
                    <tr>
                        <th>แบรนด์สินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <select name="typebrandname">
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

            <!-- แสดงปุ่ม "บันทึก" เฉพาะในโหมดแก้ไขเท่านั้น -->
            <?php if ($is_update): ?>
                <button class="submit-button" type="submit"
                    style=" border-radius: 6px;
                        width: 120px;
                        height: 40px;
                        padding: 10px;
                        margin-top:10px;
                        /*border: 1px solid rgb(29, 188, 55);*/
                        background-color: #333;
                        text-decoration: none;
                        color: #fff; ">
                    บันทึก</button>
            <?php endif; ?>
        </form>

        <div class="detail-action">
            <a href="/?page=bar_product">ย้อนกลับหน้า List_<?php echo ($tabel); ?></a>

            <?php if ($is_update): ?>
                <a href="?page=product_detail&tabel=<?php echo $tabel; ?>&id=<?php echo $product['product_id']; ?>" style="margin-right: 6px;">ย้อนกลับไปที่ Detail
                </a>
            <?php endif; ?>

            <a href="?page=product_detail&tabel=product&id=<?php echo $product['product_id']; ?>&is_update=1">
                ไปหน้าแก้ไข <?php echo $tabel; ?>
            </a>
        </div>



    </div>
</body>

</html>