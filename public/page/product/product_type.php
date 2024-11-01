<?php
require_once __DIR__ . '/../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typename = $_POST['typename'] ?? '';

    // if (!empty($typename)) {
    //     try {
    $stmt = $pdo->prepare('INSERT INTO product_type (product_type_name) VALUES (?)');
    $stmt->execute([$typename]);

    header('Location: /index.php?page=product_type');
    exit;
    //     } catch (PDOException $e) {
    //         echo 'Database error: ' . $e->getMessage();
    //     }
    // } else {
    //     echo 'Error: Type name is required.';
    // }
}

$query = "SELECT product_type_id, 
                product_type_name 
            FROM product_type";
$stmt = $pdo->prepare($query);
$stmt->execute();

$productTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <h2 class="sub-title">เพิ่มประเภทสินค้า</h2>
        <form class="form-container" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
            <div class="box">
                <label for="typename">ชื่อประเภทสินค้า:</label>
                <input type="text" id="typename" name="typename" required>
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
                <th>รหัสประเภทสินค้า</th>
                <th>ชื่อประเภทสินค้า</th>
                <th>การจัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productTypes as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_type_id']) ?></td>

                    <td><?= htmlspecialchars($row['product_type_name']) ?></td>

                    <td>
                        <a
                            href="functions_product/product_type_edit.php?id=<?= htmlspecialchars($row['product_type_id']) ?>">แก้ไข</a>
                        <a href="functions_product/product_type_delete.php?id=<?= htmlspecialchars($row['product_type_id']) ?>"
                            onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>