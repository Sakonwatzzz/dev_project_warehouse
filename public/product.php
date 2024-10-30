<?php
require_once __DIR__ . '/../config/database_server.php';

include_once("./db/warehouse_db.php");


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
            <input type="text" name="barcode" id="barcode" required>

            <label for="name">ชื่อสินค้า:</label>
            <input type="text" name="name" id="name" required>

            <label for="priceone">ราคาที่1:</label>
            <input type="text" name="priceone" id="priceone" required>

            <label for="pricetwo">ราคาที่2:</label>
            <input type="text" name="pricetwo" id="pricetwo" required>

            <label for="pricethree">ราคาที่3:</label>
            <input type="text" name="pricethree" id="pricethree" required>

            <label for="pricefour">ราคาที่4:</label>
            <input type="text" name="pricefour" id="pricefour" required>

            <label for="capital">เมือง:</label>
            <input type="text" name="capital" id="capital" required>

            <?php if ($results->num_rows > 0) { ?>
                <label for="typename">ประเภทสินค้า:</label>
                <select name="typename" id="typename" required>
                    <option value="">-- เลือกประเภทสินค้า --</option>
                    <?php while ($row = $results->fetch_assoc()) { ?>
                        <option value="<?= htmlspecialchars($row['product_type_id']); ?>">
                            <?= htmlspecialchars($row['product_type_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทสินค้า</p>
            <?php } ?>

            <?php if ($results->num_rows > 0) { ?>
                <label for="typebrandname">ประเภทแบรนด์สินค้า:</label>
                <select name="typebrandname" id="typebrandname" required>
                    <option value="">-- ประเภทแบรนด์สินค้า --</option>
                    <?php while ($row = $results->fetch_assoc()) { ?>
                        <option value="<?= htmlspecialchars($row['product_type_id']); ?>">
                            <?= htmlspecialchars($row['product_type_name']); ?>
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
            <?php while ($row = $result->fetch_assoc()): ?>
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