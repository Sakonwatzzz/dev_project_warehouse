<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once __DIR__ . '../../../db/warehouse_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brandname = $_POST['brandname'] ?? '';

    // if (!empty($brandname)) {
    //     try {
    $stmt = $pdo->prepare('INSERT INTO product_brand (product_brand_name) VALUES (?)');
    $stmt->execute([$brandname]);

    header('Location: /index.php?page=product_brand');
    exit;
    //     } catch (PDOException $e) {
    //         echo 'Database error: ' . $e->getMessage();
    //     }
    // } else {
    //     echo 'Error: brand name is required.';
    // }
}

$query = "SELECT product_brand_id, 
                product_brand_name 
            FROM product_brand";
$stmt = $pdo->prepare($query);
$stmt->execute();

$productbrands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/product_type.css">
    <title>Document</title>
</head>

<body>

    <div class="add-box">
        <h2 class="sub-title">เพิ่มประแบรนด์สินค้า</h2>
        <form class="form-container" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
            <div class="box">
                <label for="brandname">ชื่อประแบรนด์สินค้า:</label>
                <input type="text" id="brandname" name="brandname" required>
            </div>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
                <!-- แก้คำว่า "lass" เป็น "class" -->
            </div>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>รหัสประแบรนต์สินค้า</th>
                <th>ชื่อประแบรนต์สินค้า</th>
                <th>การจัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productbrands as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_brand_id']) ?></td>

                    <td><?= htmlspecialchars($row['product_brand_name']) ?></td>

                    <td>    
                        <a
                            href="functions_product/product_brand_edit.php?id=<?= htmlspecialchars($row['product_brand_id']) ?>">แก้ไข</a>
                        <a href="functions_product/product_brand_delete.php?id=<?= htmlspecialchars($row['product_brand_id']) ?>"
                            onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>