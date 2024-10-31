<?php
require_once __DIR__ . '../../../config/database_server.php';
require_once __DIR__ . '../../db/warehouse_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];  
    $barcode = $_POST['barcode'];
    $name = $_POST['name'];
    $price_1 = $_POST['price_1'];
    $price_2 = $_POST['price_2'];
    $price_3 = $_POST['price_3'];
    $price_4 = $_POST['price_4'];
    $capital = $_POST['capital'];
    $typename = $_POST['typename'];
    $typebrandname = $_POST['typebrandname'];
    $stmt = $pdo->prepare("
        UPDATE product 
        SET product_barcode = ?, product_name = ?, product_price1 = ?, product_price2 = ?, product_price3 = ?  ,product_price4 = ?  , product_capital = ?  ,product_type_id = ?  ,product_brand_id = ?  
        WHERE product_id = ?
    ");
    $stmt->execute([$barcode, $name, $price_1,  $price_2,  $price_3, $price_4, $capital, $typename, $typebrandname, $id]);

    header('Location:   /?page=bar_product');  // กลับไปหน้าแสดงผล
    exit;
}
// ตรวจสอบให้แน่ใจว่า ID ถูกตั้งค่า
if (!isset($_GET['id'])) {
    die("Error: Product ID not provided.");
}
$id = $_GET['id'];

// ดึงรายละเอียดผลิตภัณฑ์พร้อมชื่อแบรนด์และประเภท
$stmt = $pdo->prepare("SELECT product.*, product_brand.product_brand_name, product_type.product_type_name 
    FROM product 
    INNER JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id
    INNER JOIN product_type ON product.product_type_id = product_type.product_type_id 
    WHERE product.product_id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    die("Error: Product not found.");
}

// ดึงประเภทและแบรนด์สำหรับ dropdowns
$queryTypes = "SELECT product_type_id, product_type_name FROM product_type";
$typeResults = $conn->query($queryTypes);

$querybrandTypes = "SELECT product_brand_id, product_brand_name FROM product_brand";
$brandResults = $conn->query($querybrandTypes);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="/style/product.css">
</head>

<body>
    <div class="body-main">
        <h1 class="title">ข้อมูลสินค้า</h1>
        <h2 class="sub-title">เพิ่มสินค้า</h2>

        <form class="form-container" action="product_edit.php" method="POST">

            <input type="hidden" name="id" value="<?= htmlspecialchars($product['product_id']) ?>">

            <label for="barcode">บาร์โค้ดสินค้า:</label>
            <input type="text" name="barcode" id="barcode" value="<?= htmlspecialchars(string: $product['product_barcode']) ?>">

            <label for="name">ชื่อสินค้า:</label>
            <input type="text" name="name" id="name" value="<?= htmlspecialchars($product['product_name']) ?>">

            <label for="price_1">ราคาที่1:</label>
            <input type="text" name="price_1" id="price_1" value="<?= htmlspecialchars($product['product_price1']) ?>">

            <label for="price_2">ราคาที่2:</label>
            <input type="text" name="price_2" id="price_2" value="<?= htmlspecialchars($product['product_price2']) ?>">

            <label for="price_3">ราคาที่3:</label>
            <input type="text" name="price_3" id="price_3" value="<?= htmlspecialchars($product['product_price3']) ?>">

            <label for="price_4">ราคาที่4:</label>
            <input type="text" name="price_4" id="price_4" value="<?= htmlspecialchars($product['product_price4']) ?>">

            <label for="capital">เมือง:</label>
            <input type="text" name="capital" id="capital" value="<?= htmlspecialchars($product['product_capital']) ?>">

            <?php if ($typeResults->num_rows > 0) { ?>
                <label for="typename">ประเภทสินค้า:</label>
                <select name="typename" id="typename">
                    <option value="<?= htmlspecialchars(string: $product['product_type_id']) ?>">
                        <?= htmlspecialchars(string: $product['product_type_name']) ?>
                    </option>
                    <?php while ($row = $typeResults->fetch_assoc()) { ?>
                        <option value="<?= htmlspecialchars($row['product_type_id']); ?>">
                            <?= htmlspecialchars($row['product_type_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทสินค้า</p>
            <?php } ?>


            <?php if ($brandResults->num_rows > 0) { ?>
                <label for="typebrandname">ประเภทแบรนด์สินค้า:</label>
                <select name="typebrandname" id="typebrandname">
                    <option value="<?= htmlspecialchars($product['product_brand_id']) ?>" selected>
                        <?= htmlspecialchars($product['product_brand_name']) ?>
                    </option>
                    <?php while ($row = $brandResults->fetch_assoc()) { ?>
                        <option value="<?= htmlspecialchars($row['product_brand_id']); ?>">
                            <?= htmlspecialchars($row['product_brand_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } ?>

            <input class="submit-button" type="submit" value="update">
        </form>
    </div>


</body>

</html>