<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once __DIR__ .'../../../db/warehouse_db.php';
$productResults = fetchAll($pdo,'product');

$query = "SELECT
    product.product_id, 
    product.product_barcode, 
    product.product_name, 
    product.product_price1, 
    product.product_price2, 
    product.product_price3, 
    product.product_price4, 
    product.product_capital, 
    product.product_type_id, 
    product.product_brand_id,
    product_brand.product_brand_name, 
    product_type.product_type_name
FROM
    product
INNER JOIN
    product_brand ON product.product_brand_id = product_brand.product_brand_id
INNER JOIN
    product_type ON product.product_type_id = product_type.product_type_id;
";

$productResults = $conn->query($query); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

// Query สำหรับประเภทสินค้า
$queryTypes = "SELECT
    product_type.product_type_id, 
    product_type.product_type_name
FROM product_type";
$typeResults = $conn->query($queryTypes); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

if (!$typeResults) {
    die("Query failed: " . $conn->error);
}

// // Query สำหรับประเภทแบรนด์สินค้า
// $querybrandTypes = "SELECT
//     product_brand.product_brand_id, 
//     product_brand.product_brand_name
// FROM product_brand";
// $brandResults = $conn->query($querybrandTypes); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

// if (!$brandResults) {
//     die("Query failed: " . $conn->error);
    
// }
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
        <table class="data-table">
            <thead>
                <tr>
                    <th>รหัสสินค้า</th>
                    <th>บาร์โค้ด</th>
                    <th>ชื่อสินค้า</th>
                    <th>ราคา 1</th>
                    <th>ราคา 2</th>
                    <th>ราคา 3</th>
                    <th>ราคา 4</th>
                    <th>เงินทุน</th>
                    <th>ประเภทสินค้า</th>
                    <th>แบรนด์สินค้า</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($productResults as $row ): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['product_id']) ?></td>
                        <td><?= htmlspecialchars($row['product_barcode']) ?></td>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td><?= htmlspecialchars($row['product_price1']) ?></td>
                        <td><?= htmlspecialchars($row['product_price2']) ?></td>
                        <td><?= htmlspecialchars($row['product_price3']) ?></td>
                        <td><?= htmlspecialchars($row['product_price4']) ?></td>
                        <td><?= htmlspecialchars($row['product_capital']) ?></td>
                        <td><?= htmlspecialchars($row['product_type_name']) ?></td>
                        <td><?= htmlspecialchars($row['product_brand_name']) ?></td>
                        <td>
                            <a
                                href="functions_product/product_edit.php?id=<?= htmlspecialchars($row['product_id']) ?>">แก้ไข</a>
                            <a href="functions_product/product_delete.php?id=<?= htmlspecialchars($row['product_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>

    </div>
</body>

</html>