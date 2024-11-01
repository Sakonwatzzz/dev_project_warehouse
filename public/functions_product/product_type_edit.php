<?php
require_once __DIR__ . '../../../config/database_server.php';
require_once __DIR__ . '../../db/warehouse_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $typename = $_POST['typename'];
    $stmt = $pdo->prepare("
        UPDATE product_type
        SET product_type_name = ?
        WHERE product_type_id = ?
    ");
    $stmt->execute([$typename, $id]);

    header('Location: /index.php?page=product_type');  // กลับไปหน้าแสดงผล
    exit;
}
// ตรวจสอบให้แน่ใจว่า ID ถูกตั้งค่า
if (!isset($_GET['id'])) {
    die("Error: Product ID not provided.");
}
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM product_type WHERE product_type_id = ?");
$stmt->execute([$id]);
$producttypes = $stmt->fetch();
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
        <h2 class="sub-title">แก้ไขประเภทสินค้า</h2>

        <form class="form-container" action="product_type_edit.php" method="POST">

            <input type="hidden" name="id" value="<?= htmlspecialchars($producttypes['product_type_id']) ?>">

            <label for="typename">ชื่อประเภทสินค้า:</label>
            <input type="text" name="typename" id="typename"
                value="<?= htmlspecialchars($producttypes['product_type_name']) ?>">

            <input class="submit-button" type="submit" value="อัพเดท">
        </form>
    </div>


</body>

</html>