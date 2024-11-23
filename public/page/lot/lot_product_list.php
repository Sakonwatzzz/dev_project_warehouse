<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

$query = "SELECT
lot_product.lot_product_id, 
lot_product.lot_id, 
lot_product.lot_product_qty, 
lot_product.lot_product_remain,  
lot_product.lot_product_capital, 
lot_product.lot_product_date, 
lot_product.lot_product_status,  
lot_product.product_id, 
product.product_name
FROM
lot_product
INNER JOIN lot ON lot_product.lot_id = lot.lot_id
INNER JOIN product ON lot_product.product_id = product.product_id";
$result_lotproduct_list = $pdo->query($query);


// การลบข้อมูล
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_product_id = ?");
    $stmt->execute([$id]);
    // หลังจากลบแล้วให้ redirect ไปหน้าหลัก และ exit เพื่อหยุดการทำงาน
    header("Location: index.php?page=lot_product_list");
    exit();
}

try {
    // การแบ่งหน้า
    $records_per_page = 5;
    $page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
    $offset = ($page - 1) * $records_per_page;
    $total_pages = ceil($count / $records_per_page);
    // ดึงข้อมูลจำนวนทั้งหมด
    $query = "SELECT COUNT(*) as total_count FROM lot_product";
    $stmt = $pdo->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];

} catch (PDOException $e) {
    echo "erro" . $e->getMessage();
}

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
    product.product_name
FROM
    lot_product
    INNER JOIN lot ON lot_product.lot_id = lot.lot_id
    INNER JOIN product ON lot_product.product_id = product.product_id
LIMIT :offset, :records_per_page";

    $smtm = $pdo->prepare($query);
    $smtm->bindValue(':offset', $offset, PDO::PARAM_INT);
    $smtm->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $result_lotproduct_list->execute();


} catch (PDOException $e) {
    echo "" . $e->getMessage();
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <title>รายการรับสินค้า</title>
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
                <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="ค้นหารายการรับสินค้า...">
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
                    <th>ชื่อสินค้า</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_lotproduct_list->rowCount() > 0): ?>
                    <?php while ($lot_product_list = $result_lotproduct_list->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td
                                onclick="window.location.href='?page=lot_product_detail&tabel=lot_product&id=<?php echo ($lot_product_list['lot_product_id']) ?>'">
                                <?php echo ($lot_product_list['lot_product_id']); ?>
                            </td>
                            <td><?php echo ($lot_product_list['lot_id']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_qty']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_remain']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_capital']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_date']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_status']); ?></td>
                            <td><?php echo ($lot_product_list['product_name']); ?></td>
                            <td>
                                <a
                                    href="?page=lot_product_update&id=<?php echo ($lot_product_list['lot_product_id']); ?>">แก้ไข</a>
                                <a href="?page=lot_product_list&id=<?php echo ($lot_product_list['lot_product_id']); ?>"
                                    onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">ไม่พบข้อมูล</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=lot_product_list&page_num=1">First</a>
                    <a href="?page=lot_product_list&page_num=<?php echo ($page - 1); ?>">Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=lot_product_list&page_num=<?php echo $i; ?>"
                        class="<?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
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