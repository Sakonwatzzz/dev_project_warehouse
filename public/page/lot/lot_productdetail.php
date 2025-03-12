<?php
// เรียกไฟล์ที่ใช้ในการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . "../../../../config/database_server.php";
// ตรวจสอบว่ามีการระบุประเภท (tabel) และ ID (id) มาหรือไม่ ถ้าไม่เจอให้หยุดการทำงาน
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

// ตรวจสอบว่าเป็นการเรียกด้วย POST (เมื่อมีการส่งข้อมูลจากฟอร์ม)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lot_id = $_POST['lot_id'];
    // ทำการรีไดเรกต์ไปที่หน้ารายละเอียดสินค้า
    header("Location: lot_product_detail.php?tabel=lot&id=$lot_product_id");
    exit();
}


// ดึงค่าตัวแปรจาก URL เพื่อระบุประเภทและ ID

$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false; // เช็คว่าอยู่ในโหมดแก้ไขหรือไม่


// ตรวจสอบประเภทที่กำหนดมาและทำการ query เพื่อดึงข้อมูลสินค้า
switch ($tabel) {
    case 'lot_product':
        // เตรียม query เพื่อดึงข้อมูลสินค้าพร้อมข้อมูลประเภทและแบรนด์
        $stmt = $pdo->prepare("
    SELECT
    lot_product.lot_product_id, 
    lot_product.lot_id, 
    lot_product.lot_product_qty, 
    lot_product.lot_product_remain,  
    lot_product.lot_product_capital, 
    lot_product.lot_product_date, 
    lot_product.lot_product_status,  
    lot_product.product_id, 
    product.product_id,
    product.product_name
FROM
    lot_product
    INNER JOIN
    lot
    ON 
    lot_product.lot_id = lot.lot_id
    INNER JOIN
    product
    ON 
    lot_product.product_id = product.product_id
        WHERE lot_product.lot_product_id = ?
        ");
        break;
    default:
        die("ระบุประเภทไม่ถูกต้อง");
}


// ดำเนินการ query ด้วย product_id ที่ระบุ
$stmt->execute([$id]);
$lot = $stmt->fetch(PDO::FETCH_ASSOC); // เก็บผลลัพธ์เป็นแถวข้อมูลในรูปแบบ array
if (!$lot) {
    echo "ไม่พบข้อมูล";
    exit();
}
// 
$querylot = "SELECT lot_id FROM lot";
$result_lot = $pdo->query($querylot);

// 
$queryproduct = "SELECT product_id, product_name FROM product";
$result_product = $pdo->query($queryproduct);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_product_id = $_POST['lot_product_id'];
    $lot_id = $_POST['lot_id'];
    $lot_product_qty = $_POST['lot_product_qty'];
    $lot_product_remain = $_POST['lot_product_remain'];
    $lot_product_capital = $_POST['lot_product_capital'];
    $lot_product_date = $_POST['lot_product_date'];
    $lot_product_status = $_POST['lot_product_status'];
    $product_id = $_POST['product_id'];


    $stmt = $pdo->prepare("
        UPDATE lot_product 
        SET product_lot_id = ?, product_lot_product_qty = ?, product_lot_product_remain = ?, lot_product_capital = ?, 
            lot_product_date = ?, lot_product_status = ?, product_id 
        WHERE lot_product_id = ?
    ");
    $stmt->execute([$lot_id, $lot_product_qty, $lot_product_remain, $lot_product_capital, $lot_product_date, $lot_product_status, $lot_product_status, $product_id, $lot_product_id]);
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
        <h2 class="detail-title">รายละเอียดล็อต</h2>
        <form class="detail-form" action="../../page/lot/lot_update.php" method="POST">
            <input type="hidden" name="lot_product_id" value="<?php echo $lot['lot_product_id']; ?>">
            <table class="detail-table">
                <?php if ($tabel === 'lot_product'): ?>
                    <tr>
                        <th>รหัสรายการรับสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="id" name="lot_product_id" value="<?php echo ($lot['lot_product_id']); ?>" readonly>
                            <?php else: ?>
                                <?php echo ($lot['lot_product_id']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>รหัสล็อตสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <select name="lot_id">
                                    <option value="<?php echo ($lot['lot_id']); ?>" selected>
                                        <?php echo ($lot['lot_id']); ?>
                                    </option>
                                    <?php while ($lot_detail = $result_lot->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo ($lot_detail['lot_id']); ?>">
                                            <?php echo ($lot_detail['lot_id']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <?php echo ($lot['lot_id']); ?>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>จำนวนวสินค้าเข้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="lot_product_qty" value="<?php echo ($lot['lot_product_qty']); ?>">
                            <?php else: ?>
                                <?php echo ($lot['lot_product_qty']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>จำนวนสินคงเหลือ:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="lot_product_remain"
                                    value="<?php echo ($lot['lot_product_remain']); ?>">
                            <?php else: ?>
                                <?php echo ($lot['lot_product_remain']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ราคาต้นทุน:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="lot_product_capital"
                                    value="<?php echo ($lot['lot_product_capital']); ?>">
                            <?php else: ?>
                                <?php echo ($lot['lot_product_capital']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>วันที่รับเข้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="date" name="lot_product_date" value="<?php echo ($lot['lot_product_date']); ?>">
                            <?php else: ?>
                                <?php echo ($lot['lot_product_date']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>สถานะสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <input type="text" name="lot_product_status"
                                    value="<?php echo ($lot['lot_product_status']); ?>">
                            <?php else: ?>
                                <?php echo ($lot['lot_product_status']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>รหัสสินค้า:</th>
                        <td>
                            <?php if ($is_update): ?>
                                <select name="product_name">
                                    <option value="<?php echo ($lot['product_id']); ?>" selected>
                                        <?php echo ($lot['product_name']); ?>
                                    </option>
                                    <?php while ($lot_product_detail = $result_product->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo ($lot_product_detail['product_id']); ?>">
                                            <?php echo ($lot_product_detail['product_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <?php echo ($lot['product_name']); ?>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>

            <!-- แสดงปุ่ม "บันทึก" เฉพาะในโหมดแก้ไขเท่านั้น -->
            <?php if ($is_update): ?>
                <button class="submit-button" type="submit" style=" border-radius: 6px;
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
            <a href="/?page=lot_productlist">ย้อนกลับหน้ารายการ<?php echo ($tabel); ?></a>

            <?php if ($is_update): ?>
                <a href="?page=lot_productdetail&tabel=<?php echo $tabel; ?>&id=<?php echo $lot['lot_product_id']; ?>"
                    style="margin-right: 6px;">ย้อนกลับไปที่ Detail
                </a>
            <?php endif; ?>

            <a href="?page=lot_productdetail&tabel=lot_product&id=<?php echo $lot['lot_product_id']; ?>&is_update=1">
                ไปหน้าแก้ไข <?php echo $tabel; ?>
            </a>
        </div>



    </div>
</body>

</html>