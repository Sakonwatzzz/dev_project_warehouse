<?php
require_once __DIR__ . '../../../../config/database_server.php';

$queryTypes = "SELECT product_type_id, product_type_name FROM product_type";
$typeResults = $pdo->query($queryTypes);

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
    header("Location: /home.php?page=product_list&success=1");
    exit;
}


$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM product WHERE product_id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../../style/personnel_insert.css">

    <style>
        .edit-box {
            margin: 3rem 10rem 4rem 8rem;
        }

        h1 {
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="edit-box">
        <h1 class="title">แก้ไขข้อมูลสินค้า</h1>

        <form class="form-container" action="" method="POST">
            <input type="hidden" name="id" value="<?php echo ($product['product_id']) ?>">

            <div class="box">
                <label for="barcode">บาร์โค้ดสินค้า:</label>
                <input type="text" name="barcode" value="<?php echo ($product['product_barcode']) ?>">
            </div>

            <div class="box">
                <label for="name">ชื่อสินค้า:</label>
                <input type="text" name="name" value="<?php echo ($product['product_name']) ?>">

            </div>
            <div class="box">
                <label for="price_1">ราคาที่1:</label>
                <input type="text" name="price_1" value="<?php echo ($product['product_price1']) ?>">
            </div>
            <div class="box">
                <label for="price_2">ราคาที่2:</label>
                <input type="text" name="price_2" value="<?php echo ($product['product_price2']) ?>">

            </div>
            <div class="box">
                <label for="price_3">ราคาที่3:</label>
                <input type="text" name="price_3" value="<?php echo ($product['product_price3']) ?>">

            </div>
            <div class="box">
                <label for="price_4">ราคาที่4:</label>
                <input type="text" name="price_4" value="<?php echo ($product['product_price4']) ?>">

            </div>
            <div class="box">
                <label for="capital">ราคาทุน:</label>
                <input type="text" name="capital" value="<?php echo ($product['product_capital']) ?>">
            </div>

            <?php if ($typeResults->rowCount() > 0) { ?>
                <div class="box">
                    <label for="typename">ประเภทสินค้า:</label>
                    <select name="typename">
                        <?php while ($product_update = $typeResults->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($product_update['product_type_id']); ?>" <?= ($product_update['product_type_id'] == $product['product_type_id']) ? 'selected' : ''; ?>>
                                <?php echo ($product_update['product_type_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทสินค้า</p>
            <?php } ?>


            <?php if ($brandResults->rowCount() > 0) { ?>
                <div class="box">
                    <label for="typebrandname">ประเภทแบรนด์สินค้า:</label>
                    <select name="typebrandname">
                        <?php while ($product_update = $brandResults->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($product_update['product_brand_id']); ?>" <?= ($product_update['product_brand_id'] == $product['product_brand_id']) ? 'selected' : ''; ?>>
                                <?php echo ($product_update['product_brand_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } ?>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
    </div>


</body>

</html>

http://localhost:8000/product_detail.php?tabel=&id=24&is_update=1
http://localhost:8000/page/personnel/personnel_detail.php?tabel=personnel&id=41%20%20%20%20%20%20%20%20%20%20%20%20&is_update=1