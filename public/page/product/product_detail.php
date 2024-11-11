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
        // ถ้าไม่ใช่ประเภทที่ระบุไว้ ให้หยุดการทำงาน
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
        <h1 class="title">รายละเอียด <?php echo ($tabel); ?></h1>

        <!-- ฟอร์มที่ใช้ในการส่งข้อมูลสำหรับอัพเดตรายการสินค้า -->
        <form action="product_update.php" method="POST">
            
            <!-- กำหนดค่า product_id เป็น input ซ่อน (hidden) เพื่อใช้ส่ง ID ของสินค้านี้ในฟอร์ม -->
            <input type="hidden" name="product_id" value="<?php echo ($product['product_id']); ?>">

            <!-- ตารางแสดงรายละเอียดสินค้า -->
            <table class="details-table">
                <?php if ($tabel === 'product'): ?>
                    <!-- แสดงรายละเอียดต่างๆ ของสินค้า เช่น รหัสสินค้า บาร์โค้ด และชื่อสินค้า -->
                    
                    <tr>
                        <th>รหัสสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                            <input type="text" name="id" value="<?php echo ($product['product_id']); ?>" >
                            <?php else: ?>
                                <?php echo ($product['product_id']); ?>
                            <?php endif;?> 
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
                <button class="submit-button" type="submit">บันทึก</button>
            <?php endif; ?>
        </form>

        <!-- ลิงก์กลับไปยังหน้ารายการและรายละเอียดสินค้า -->
        <a href="/?page=bar_<?php echo ($tabel); ?>">กลับไปหน้ารายการสินค้า <?php echo ($tabel); ?></a>
        <a href="product_detail.php?tabel=<?php echo ($tabel); ?>&id=<?php echo ($product['product_id']); ?>">กลับไปหน้ารายละเอียด <?php echo ($tabel); ?></a>
        <a href="product_detail.php?tabel=<?php echo ($tabel); ?>&id=<?php echo ($product['product_id']); ?>&is_update=1">ไปหน้าแก้ไข <?php echo ($tabel); ?></a>
    </div>
</body>

</html>
