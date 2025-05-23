<?php require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
$is_update = isset($_GET['is_update']) && $_GET['is_update'] == 1;
$update_id = isset($_GET['id']) ? $_GET['id'] : null;
try {
    $query = "SELECT
    stock_check.product_id,
    product.product_name,
    stock_check.stock_check_id,
    stock_check.lot_id,
    warehouse.warehouse_id,
    warehouse.warehouse_name,
    stock_check.stock_check_remark,
    personnel.personnel_name,
    stock_check.lot_product_id,
    lot_product.lot_product_remain,
    stock.stock_id,
    stock.stock_date,
    stock_check.stock_check_system,
    stock_check.stock_check_actual,
    stock.check_status_id,
    stock_checks.check_status_name
FROM
    stock_check stock_check
    LEFT JOIN product product ON stock_check.product_id = product.product_id
    LEFT JOIN warehouse warehouse ON stock_check.warehouse_id = warehouse.warehouse_id
    LEFT JOIN lot lot ON stock_check.lot_id = lot.lot_id
    LEFT JOIN lot_product lot_product ON stock_check.lot_product_id = lot_product.lot_product_id
    LEFT JOIN stock stock ON stock_check.stock_id = stock.stock_id
    LEFT JOIN personnel personnel ON stock.personnel_id = personnel.personnel_id
    LEFT JOIN stock_check_status stock_checks ON stock.check_status_id = stock_checks.check_status_id
    ORDER BY stock.stock_date DESC
        ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $check_stock = $stmt;
} catch (Exception $e) {
    echo ("Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_submit'])) {
    $stock_check_id = $_POST['stock_check_id'];
    $stock_check_actual = $_POST['stock_check_actual'];
    $stock_check_remark = $_POST['stock_check_remark'];

    $stmt = $pdo->prepare("UPDATE stock_check SET  stock_check_actual = ?, stock_check_remark = ? WHERE
    stock_check_id = ?");
    $stmt->execute([$stock_check_actual, $stock_check_remark, $stock_check_id]);
    // รีไดเรกต์ไปที่หน้ารายการหลังอัปเดตสำเร็จ
    header("Location: /?page=stock_list");
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .input-update {
            border-color: black;
            border-radius: 3px;
        }
    </style>
</head>

<body class="main-body">
    <div class="min-h-screen flex flex-col">
        <header class="bg-blue-600 shadow-lg w-full">
            <div class="container max-w-screen-xl mx-auto px-4 py-4 flex justify-between">
                <div class="items-center">
                    <h1 class="font-bold text-2xl text-white">
                        <?= $is_update ? 'กำลังแก้ไขประวัติรายการเช็คสต๊อกสินค้า' : 'ประวัติรายการเช็คสต๊อกสินค้า'; ?>
                    </h1>
                </div>
                <div class="flex justify-center py-2 p-2 gap-4 text-white">
                    <span id="clock-realtime"></span>
                    <button class="flex items-center focus:outline-none">
                        <span class="md:block">ชื่อผู้ใช้งาน</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>
        <div class="overflow-hidden rounded-lg flex justify-center items-center p-2">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border" >
                                รหัสรายการเช็คสต๊อกสินค้า</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border" >
                                รหัสเช็คสต๊อก</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border">
                                วันที่</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border" >
                                คลังสินค้า</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border">
                                รหัสสินค้า</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border">
                                ชื่อสินค้า</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border">
                                จำนวนสินค้าจากระบบ</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border">
                                จำนวนสินค้าที่นับได้จริง</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border">
                                พนักงาน</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border">
                                สถานะ</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border">
                                หมายเหตุ</th>
                            <th class="px-6 py-3 text-left text-base text-gray-900 uppercase border">
                                การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($check_stock->rowCount() > 0): ?>
                            <?php while ($check_stock_list = $check_stock->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <form method="POST"
                                        action="?page=stock_list&is_update=1&id=<?php echo $check_stock_list['stock_check_id']; ?>">
                                        <td class="px-3 py-2 border text-left text-sm" >
                                            <?php echo $check_stock_list['stock_check_id']; ?>
                                            <input type="hidden" name="stock_check_id"
                                                value="<?php echo $check_stock_list['stock_check_id']; ?>">
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm" >
                                            <?php echo $check_stock_list['stock_id']; ?>
                                            <input type="hidden" name="stock_id"
                                                value="<?php echo $check_stock_list['stock_id']; ?>">
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm">
                                            <?php echo date('d/m/Y', strtotime($check_stock_list['stock_date'])); ?>
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm" >
                                            <?php echo $check_stock_list['warehouse_name']; ?>
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm">
                                            <?php echo $check_stock_list['product_id']; ?>
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm">
                                            <?php echo $check_stock_list['product_name']; ?>
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm">
                                            <?php echo $check_stock_list['stock_check_system']; ?>
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm">
                                            <?php if ($is_update && $update_id == $check_stock_list['stock_check_id']): ?>
                                                <input type="number" step="0.01" name="stock_check_actual"
                                                    value="<?php echo $check_stock_list['stock_check_actual']; ?>"
                                                    class="input-update w-full border rounded-sm px-2 py-1">
                                            <?php else: ?>
                                                <?php echo $check_stock_list['stock_check_actual']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm">
                                            <?php echo $check_stock_list['personnel_name']; ?>
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm">
                                            <?php echo $check_stock_list['check_status_name']; ?>
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm">
                                            <?php if ($is_update && $update_id == $check_stock_list['stock_check_id']): ?>
                                                <input type="text" name="stock_check_remark"
                                                    value="<?php echo $check_stock_list['stock_check_remark']; ?>"
                                                    class="input-update w-full border rounded-sm px-2 py-1">
                                            <?php else: ?>
                                                <?php echo $check_stock_list['stock_check_remark']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 border text-left text-sm">
                                            <div class="flex flex-col sm:flex-row gap-1">
                                                <?php if ($is_update && $update_id == $check_stock_list['stock_check_id']): ?>
                                                    <button type="submit" name="update_submit"
                                                        class="bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600">บันทึก</button>
                                                    <a href="?page=stock_list"
                                                        class="bg-yellow-400 text-white px-2 py-1 rounded hover:bg-yellow-600">ยกเลิก</a>
                                                <?php else: ?>
                                                    <a href="?page=stock_list&is_update=1&id=<?php echo $check_stock_list['stock_check_id']; ?>"
                                                        class="bg-yellow-500 text-white px-2 py-1 rounded hover:bg-yellow-600">แก้ไข</a>
                                                    <a href="?page=stock_list&delete_id=<?php echo $check_stock_list['stock_check_id']; ?>"
                                                        onclick="return confirm('แน่ใจว่าต้องการลบ?')"
                                                        class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">ลบ</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>

<script>
    function updateClock() {
        const now = new Date();
        const day = String(now.getDate()).padStart(2, '0');
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const year = now.getFullYear();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');

        const formmattedData = `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;

        document.getElementById('clock-realtime').textContent = formmattedData;
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>

</html>