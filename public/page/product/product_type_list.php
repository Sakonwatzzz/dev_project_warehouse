<?php
require_once __DIR__ . '../../../../config/database_server.php';

// การตรวจสอบว่าเป็นการ POST request หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typename = trim($_POST['typename'] ?? ''); // กำจัดช่องว่างที่ไม่จำเป็น

    // ตรวจสอบว่า typename ไม่ว่าง
    if (!empty($typename)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO product_type (product_type_name) VALUES (?)');
            $stmt->execute([$typename]);

            // รีเฟรชหน้าเดิมเมื่อบันทึกเสร็จ
            header("Location: /index.php?page=product_type_list"); // เปลี่ยนไปยังหน้าหลังจากบันทึกข้อมูล
            exit; // หยุดการทำงานหลัง header
        } catch (PDOException $e) {
            // แสดงข้อความผิดพลาดหากมีปัญหาในการเพิ่มข้อมูล
            echo 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        echo 'Error: Type name is required.'; // หากไม่กรอกข้อมูล typename
    }
}

// ตรวจสอบว่า `id` มีค่าใน URL หรือไม่ก่อนที่จะทำการลบ
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // ตรวจสอบว่า id เป็นตัวเลข (ป้องกัน SQL Injection)
    if (is_numeric($id)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM product_type WHERE product_type_id = ?");
            $stmt->execute([$id]);

            // กลับไปหน้าแสดงผลหลังจากลบข้อมูล
            header('Location: /index.php?page=product_type');
            exit; // หยุดการทำงานหลัง header
        } catch (PDOException $e) {
            echo 'Database error: ' . htmlspecialchars($e->getMessage()); // แสดงข้อผิดพลาดหากมีปัญหา
        }
    } else {
        echo 'Invalid ID.'; // หาก `id` ไม่ถูกต้อง
    }
}

// ดึงข้อมูลประเภทสินค้าทั้งหมดมาแสดงผล
$query = "SELECT product_type_id, product_type_name FROM product_type";
$stmt = $pdo->prepare($query);
$stmt->execute();

try {
    // ใช้ fetch() ในลูป while เพื่อดึงข้อมูลทีละแถว
    $productTypes = $stmt->fetch(PDO::FETCH_ASSOC); // ดึงแถวแรก
} catch (PDOException $e) {
    echo 'Error: ' . htmlspecialchars($e->getMessage()); // แสดงข้อผิดพลาดหากไม่สามารถดึงข้อมูลได้
}
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
                <button class="submit-button" type="submit">บันทึก</button>
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
            <?php while ($product_type_list = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                <tr>
                    <td><?php echo ($product_type_list['product_type_id']) ?></td>
                    <td><?php echo ($product_type_list['product_type_name']) ?></td>
                    <td>
                        <a href="product_type_update.php?id=<?php echo ($product_type_list['product_type_id']) ?>">แก้ไข</a>
                        <a href="?id=<?php echo ($product_type_list['product_type_id']) ?>"
                           onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>

</html>
