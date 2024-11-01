<?php
require_once __DIR__ . '../../../config/database_server.php';
require_once __DIR__ . '../../db/warehouse_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $brandname = $_POST['brandname'];
    $stmt = $pdo->prepare("
        UPDATE product_brand
        SET product_brand_name = ?
        WHERE product_brand_id = ?
    ");
    $stmt->execute([$brandname, $id]);

    header('Location: /index.php?page=product_brand');  // กลับไปหน้าแสดงผล
    exit;
}
// ตรวจสอบให้แน่ใจว่า ID ถูกตั้งค่า
if (!isset($_GET['id'])) {
    die("Error: Product ID not provided.");
}
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM product_brand WHERE product_brand_id = ?");
$stmt->execute([$id]);
$productbrands = $stmt->fetch();
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
        <h1 class="title">ข้อมูลประเภทสินค้าสินค้า</h1>
        <h2 class="sub-title">เพิ่มประเภทสินค้า</h2>

        <form class="form-container" action="product_brand_edit.php" method="POST">

            <input type="hidden" name="id" value="<?= htmlspecialchars($productbrands['product_brand_id']) ?>">

            <label for="brandname">ชื่อประเภทสินค้า:</label>
            <input type="text" name="brandname" id="brandname"
                value="<?= htmlspecialchars($productbrands['product_brand_name']) ?>">

            <input class="submit-button" type="submit" value="อัพเดท">
        </form>
    </div>


</body>

</html>