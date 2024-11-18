<?php
require_once __DIR__ . '../../../../config/database_server.php'; // โหลดไฟล์การตั้งค่าการเชื่อมต่อฐานข้อมูล
ob_start(); // เริ่มต้นการเก็บบัฟเฟอร์ output (ใช้สำหรับจัดการการแสดงผล)
// คำสั่ง SQL เพื่อดึงข้อมูล
$query = "SELECT
    lot_product.lot_product_id, 
    lot_product.lot_id, 
    lot_product.lot_product_qty, 
    lot_product.lot_product_remain, 
    lot_product.lot_product_capital, 
    lot_product.lot_product_date, 
    lot_product.lot_product_status,  
    lot_product.product_id, 
    product.product_id
FROM
    lot_product
    INNER JOIN
    lot
    ON 
    lot_product.lot_id = lot.lot_id
    INNER JOIN
    product
    ON 
    lot_product.product_id = product.product_id";
// กำหนดค่าเริ่มต้นสำหรับตัวแปร
$lot_id = [];
$product_id = [];

try {
    // ดึงข้อมูล lot_id
    $stmt = $pdo->query("SELECT lot_id FROM lot");
    $lot_id = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูล product_id
    $stmt = $pdo->query("SELECT product_id FROM product");
    $product_id = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

// คำสั่ง SQL ดึงข้อมูลจำนวนทั้งหมด
$query = "SELECT COUNT(*) as total_count FROM lot_product";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// การลบข้อมูลจาก lot_product
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_product_id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?page=lot_product_list");
}

// การแบ่งหน้า
$records_per_page = 3;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = ceil($count / $records_per_page);

// คำสั่ง SQL ดึงข้อมูลพร้อม LIMIT
try {
    $query = "SELECT
    lot_product.lot_product_id, 
    lot_product.lot_id, 
    lot_product.lot_product_qty, 
    lot_product.lot_product_remain,  
    lot_product.lot_product_capital, 
    lot_product.lot_product_date, 
    lot_product.lot_product_status,  
    lot_product.product_id, 
    product.product_id
FROM
    lot_product
    INNER JOIN
    lot
    ON 
    lot_product.lot_id = lot.lot_id
    INNER JOIN
    product
    ON 
    lot_product.product_id = product.product_id
LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        echo "Failed to prepare the SQL query.";
        exit;
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();

    // ตรวจสอบผลลัพธ์
    if ($stmt->rowCount() > 0) {
        $result_lot_product = $stmt->fetchAll(PDO::FETCH_ASSOC); // ดึงข้อมูลทั้งหมด
    } else {
        $result_lot_product = [];
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <style>
        .title {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .count {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .listcount {
            font-size: 16px;
            text-align: right;
            color: #000;
            padding: 4px 10px 4px 10px;
            border: 1px solid gray;
            border-radius: 5px;
            background-color: #fff;
            display: inline-block;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            width: 70px;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 16px;
            text-decoration: none;
            background-color: #f1f1f1;
            color: black;
            border-radius: 5px;
            margin: 0 4px;
        }

        .pagination a.active {
            background-color: #5b97b2;
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
    </style>
    <title>Document</title>
</head>

<body>
    <div class="personnel-list">
        <div class="title">
            <div class="count">
                <h2>ข้อมูลการรับสินค้า</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>

            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหารายการรับสินค้า...">
            </div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
                <tr>
                    <th>รหัสรายรับสินค้า</th>
                    <th>รหัสล็อตสินค้า</th>
                    <th>จำนวนสินค้าเข้า</th>
                    <th>จำนวนสินค้าคงเหลือ</th>
                    <th>ราคาต้นทุน</th>
                    <th>วันที่รับสินค้า</th>
                    <th>สถานะสินค้า</th>
                    <th>รหัสสินค้า</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($result_lot_product)): ?>
                    <?php foreach ($result_lot_product as $lot_product_list): ?>
                        <tr>
                            <td><?php echo ($lot_product_list['lot_product_id']) ?></td>
                            <td><?php echo ($lot_product_list['lot_id']) ?></td>
                            <td><?php echo ($lot_product_list['lot_product_qty']) ?></td>
                            <td><?php echo ($lot_product_list['lot_product_remain']) ?></td>
                            <td><?php echo ($lot_product_list['lot_product_capital']) ?></td>
                            <td><?php echo ($lot_product_list['lot_product_date']) ?></td>
                            <td><?php echo ($lot_product_list['lot_product_status']) ?></td>
                            <td><?php echo ($lot_product_list['product_id']) ?></td>
                            <td>
                                <a href="page/lot/lot_product_update.php?id=<?php echo ($lot_product_list['lot_id']) ?>">แก้ไข</a> |
                                <a href="?page=lot_product_list&id=<?php echo ($lot_product_list['lot_id']) ?>" onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">ไม่พบข้อมูล</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="box">
            <label for=""></label>
            <a class="submit-button"  href="page/lot/lot_product_insert.php" value="เพิ่ม">เพิ่มรับรายการสินค้า</a class="submit-button">
            <!-- แก้คำว่า "lass" เป็น "class" -->
        </div>
        <!-- การแบ่งหน้า -->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=lot_product_list&page_num=1">First</a>
                    <a href="?page=lot_product_list&page_num=<?php echo ($page - 1); ?>">Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=lot_product_list&page_num=<?php echo $i; ?>" class="<?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_product_list&page_num=<?php echo ($page + 1); ?>">Next</a>
                    <a href="?page=lot_product_list&page_num=<?php echo $total_pages; ?>">Last</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>