<?php
require_once __DIR__ . '/../../../config/database_server.php';

// Start output buffering
ob_start();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
  
    // แก้ไข SQL Query เพื่อ JOIN ตาราง product_type และ product_brand
    $stmt = $pdo->prepare("
       SELECT product.*, 
                   product_type.product_type_name, 
                   product_brand.product_brand_name 
            FROM product 
            INNER JOIN product_type ON product.product_type_id = product_type.product_type_id 
            INNER JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id 
            WHERE product.product_id = ?
    ");
    $stmt->execute([$id]);
    $product_update = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product_update) {
        echo "ไม่พบข้อมูลสินค้า"; 
        exit;
    }
    // // ใช้ header() เพื่อรีไดเรกต์ไปยังหน้ารายละเอียดสินค้า
    // $product_id = $_POST['product_id'];
    // header("Location: product_update.php?tabel=product&id=" . $product_id);
    // exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบว่า $_POST['id'] มีค่าหรือไม่
    $id = $_POST['id'] ?? null;
    if (!$id) {
        echo "ไม่พบรหัสสินค้า";
        exit;
    }

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
        SET product_barcode = ?, product_name = ?, product_price1 = ?, product_price2 = ?, product_price3 = ?, product_price4 = ?, product_capital = ?, product_type_id = ?, product_brand_id = ?  
        WHERE product_id = ?
    ");
    $stmt->execute([$barcode, $name, $price_1, $price_2, $price_3, $price_4, $capital, $typename, $typebrandname, $id]);

    // ใช้ header() เพื่อรีไดเรกต์ไปยังหน้ารายละเอียดสินค้า
    header('Location: product_detail.php?tabel=product&id=' . urlencode($id) . '&success=1');
    exit();
}

$queryTypes = "SELECT product_type_id, product_type_name FROM product_type";
$typeResults = $pdo->query($queryTypes);

$querybrandTypes = "SELECT product_brand_id, product_brand_name FROM product_brand";
$brandResults = $pdo->query($querybrandTypes);
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
            <input type="hidden" name="id"  value="<?php echo ($product_update['product_id']) ?>">
            <div class="box">
                <label for="barcode">บาร์โค้ดสินค้า:</label>
                <input type="text" name="barcode" id="barcode" value="<?php echo ($product_update['product_barcode']) ?>">
            </div>

            <div class="box">
                <label for="name">ชื่อสินค้า:</label>
                <input type="text" name="name" id="name" value="<?php echo ($product_update['product_name']) ?>">

            </div>
            <div class="box">
                <label for="price_1">ราคาที่1:</label>
                <input type="text" name="price_1" id="price_1" value="<?php echo ($product_update['product_price1']) ?>">
            </div>
            <div class="box">
                <label for="price_2">ราคาที่2:</label>
                <input type="text" name="price_2" id="price_2" value="<?php echo ($product_update['product_price2']) ?>">

            </div>
            <div class="box">
                <label for="price_3">ราคาที่3:</label>
                <input type="text" name="price_3" id="price_3" value="<?php echo ($product_update['product_price3']) ?>">

            </div>
            <div class="box">
                <label for="price_4">ราคาที่4:</label>
                <input type="text" name="price_4" id="price_4" value="<?php echo ($product_update['product_price4']) ?>">

            </div>
            <div class="box">
                <label for="capital">ราคาทุน:</label>
                <input type="text" name="capital" id="capital" value="<?php echo ($product_update['product_capital']) ?>">
            </div>

            <?php if ($typeResults->rowCount() > 0) { ?>
                <div class="box">
                    <label for="typename">ประเภทสินค้า:</label>
                    <select name="typename" id="typename">
                        <option value="<?php echo ($product_update['product_type_id']) ?>" selected>
                            <?php echo ($product_update['product_type_name']) ?>
                        </option>
                        <?php while ($row = $typeResults->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($row['product_type_id']); ?>">
                                <?php echo ($row['product_type_name']); ?>
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
                    <select name="typebrandname" id="typebrandname">
                        <option value="<?php echo ($product_update['product_brand_id']) ?>" selected>
                            <?php echo ($product_update['product_brand_name']) ?>
                        </option>
                        <?php while ($row = $brandResults->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($row['product_brand_id']); ?>">
                                <?php echo ($row['product_brand_name']); ?>
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