<?php
require_once __DIR__ . '/../config/database_server.php';

include_once("./db/warehouse_db.php");

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

// Query สำหรับประเภทแบรนด์สินค้า
$querybrandTypes = "SELECT
    product_brand.product_brand_id, 
    product_brand.product_brand_name
FROM product_brand";
$brandResults = $conn->query($querybrandTypes); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

if (!$brandResults) {
    die("Query failed: " . $conn->error); 
}
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

        <!-- Form for adding products -->
        <form class="form-container" action="functions_product/product_create.php" method="POST">
            <label for="barcode">บาร์โค้ดสินค้า:</label>
            <input type="text" name="barcode" id="barcode" >

            <label for="name">ชื่อสินค้า:</label>
            <input type="text" name="name" id="name" >

            <label for="price_1">ราคาที่1:</label>
            <input type="text" name="price_1" id="price_1" >

            <label for="price_2">ราคาที่2:</label>
            <input type="text" name="price_2" id="price_2" >

            <label for="price_3">ราคาที่3:</label>
            <input type="text" name="price_3" id="price_3" >

            <label for="price_4">ราคาที่4:</label>
            <input type="text" name="price_4" id="price_4" >

            <label for="capital">เมือง:</label>
            <input type="text" name="capital" id="capital" >

            <?php if ($typeResults->num_rows > 0) { ?>
                <label for="typename">ประเภทสินค้า:</label>
                <select name="typename" id="typename" >
                    <option value="">-- เลือกประเภทสินค้า --</option>
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
                <select name="typebrandname" id="typebrandname" required>
                    <option value="">-- ประเภทแบรนด์สินค้า --</option>
                    <?php while ($row = $brandResults->fetch_assoc()) { ?>
                        <option value="<?= htmlspecialchars($row['product_brand_id']); ?>">
                            <?= htmlspecialchars($row['product_brand_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทแบรนด์สินค้า</p>
            <?php } ?>

            <input class="submit-button" type="submit" value="เพิ่ม">
        </form>
    </div>

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
                <th>เมือง/ทุน</th>
                <th>ประเภทสินค้า</th>
                <th>แบรนด์สินค้า</th>
                <th>การจัดการ</th>
            </tr>
        </thead>

        <tbody>
            <?php while ($row = $productResults->fetch_assoc()): ?>
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
                        |
                        <a href="functions_product/product_delete.php?id=<?= htmlspecialchars($row['product_id']) ?>"
                            onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>

    </table>
</body>

</html>