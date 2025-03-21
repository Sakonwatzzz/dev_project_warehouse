<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// ตรวจสอบว่าได้รับ lot_id จาก URL หรือไม่
$lot_id = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : null;
$where_clause = $lot_id ? " WHERE lot_product.lot_id = :lot_id" : "";

// ดึงข้อมูล lot เพื่อแสดงชื่อ lot (ถ้ามี lot_id)
$lot_name = "";
if ($lot_id) {
    $lot_query = "SELECT lot_name FROM lot WHERE lot_id = :lot_id";
    $lot_stmt = $pdo->prepare($lot_query);
    $lot_stmt->bindValue(':lot_id', $lot_id, PDO::PARAM_INT);
    $lot_stmt->execute();
    $lot = $lot_stmt->fetch(PDO::FETCH_ASSOC);
    $lot_name = $lot ? $lot['lot_name'] : "ไม่พบข้อมูล lot";
}

// การแบ่งหน้า
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// จำนวนข้อมูลทั้งหมดในตาราง (กรองตาม lot_id ถ้ามี)
$query = "SELECT COUNT(*) as total_count FROM lot_product" . $where_clause;
$stmt = $pdo->prepare($query);
if ($lot_id) {
    $stmt->bindValue(':lot_id', $lot_id, PDO::PARAM_INT);
}
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];
$total_pages = ceil($count / $records_per_page);

// ดึงข้อมูล lot_product (กรองตาม lot_id ถ้ามี)
try {
    $query = "
        SELECT
            lot_product.lot_product_id, 
            lot_product.lot_id, 
            lot.lot_name, 
            lot_product.lot_product_qty, 
            lot_product.lot_product_remain,  
            lot_product.lot_product_capital, 
            lot_product.lot_product_date, 
            lot_product.lot_product_status,  
            lot_product.product_id, 
            product.product_name 
        FROM
            lot_product
            LEFT JOIN lot ON lot_product.lot_id = lot.lot_id
            LEFT JOIN product ON lot_product.product_id = product.product_id
        " . $where_clause . "
        LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    if ($lot_id) {
        $stmt->bindValue(':lot_id', $lot_id, PDO::PARAM_INT);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $result_lotproduct_list = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// การลบข้อมูล
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_product_id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?page=lot_productlist" . ($lot_id ? "&lot_id=$lot_id" : ""));
    exit();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <link href="../../output.css" rel="stylesheet">
    <title>รายการรับสินค้า<?php echo $lot_id ? " - Lot: $lot_name" : ""; ?></title>
</head>
<body>
    <div class="font-[Tahoma] mx-[10rem] mt-11 rounded-md">
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-6">
                <h2 class="text-2xl font-bold text-gray-800">ข้อมูลการรับสินค้า<?php echo $lot_id ? " - Lot: $lot_name" : ""; ?></h2>
                <div class="px-4 py-1 bg-white border border-gray-300 rounded shadow text-center min-w-[70px]"><?php echo $count; ?></div>
            </div>
            <div class="mt-4 md:mt-0">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1"
                    placeholder="ค้นหารายการรับสินค้า..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <table class="mt-4 w-[100%] bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg" id="dataTable">
            <thead>
                <tr>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">รหัสสินค้า</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ชื่อล็อตสินค้า</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ราคาต้นทุน</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">จำนวนสินค้าเข้า</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">จำนวนสินค้าคงเหลือ</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">สถานะสินค้า</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">วันที่รับสินค้า</th>
                    <!--<th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">การจัดการ</th>-->
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php if ($result_lotproduct_list->rowCount() > 0): ?>
                    <?php while ($lot_product_list = $result_lotproduct_list->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr class="bg-white hover:bg-[#aec5e366] cursor-pointer">
                            <td><?php echo ($lot_product_list['product_id']); ?></td>
                            <td><?php echo ($lot_product_list['lot_name']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_capital']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_qty']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_remain']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_status']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_date']); ?></td>
                            <!--<td>
                                <a href="?page=lot_productupdate&id=<?php echo ($lot_product_list['lot_product_id']); ?>" class="bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 text-white text-lg border border-yellow-700 border-t-white border-l-white px-2 py-1 rounded shadow-md hover:bg-[#FFF9C4] active:border-[#FFF176] active:border-b-white active:border-r-white active:shadow-inner">แก้ไข</a>
                                <a href="?page=lot_productlist&id=<?php echo ($lot_product_list['lot_product_id']); ?>&lot_id=<?php echo $lot_id; ?>" onclick="return confirm('ลบหรือไม่ลบ สรุป?')" class="bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 border-t-white border-l-white px-4 py-1 rounded shadow-md hover:bg-[#FFEBEE] active:border-[#EF9A9A] active:border-b-white active:border-r-white active:shadow-inner">ลบ</a>
                            </td>
                        </tr>-->
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">ไม่พบข้อมูล</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="box mt-[5rem]">

            <a href="index.php?page=lot_list" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">
                    <i class="fas fa-arrow-left mr-2"></i>กลับ
                </a>
        </div>

        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=lot_productlist&page_num=1<?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition-colors duration-150">«หน้าแรก</a>
                    <a href="?page=lot_productlist&page_num=<?php echo ($page - 1); ?><?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition-colors duration-150">«ก่อนหน้า</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=lot_productlist&page_num=<?php echo $i; ?><?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>" class="px-4 py-2 bg-gray-500 text-white rounded <?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_productlist&page_num=<?php echo ($page + 1); ?><?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition-colors duration-150">ถัดไป»</a>
                    <a href="?page=lot_productlist&page_num=<?php echo $total_pages; ?><?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition-colors duration-150">สุดท้าย»</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput").value.toLowerCase();
            const table = document.getElementById("dataTable");
            const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");
            let foundAny = false;

            for (const row of rows) {
                const cells = row.getElementsByTagName("td");
                let match = false;

                for (const cell of cells) {
                    if (cell.textContent.toLowerCase().includes(input)) {
                        match = true;
                        break;
                    }
                }

                row.style.display = match ? "" : "none";
                if (match) foundAny = true;
            }

            let noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    noDataMessage = document.createElement("p");
                    noDataMessage.id = "noDataMessage";
                    noDataMessage.textContent = "ไม่พบข้อมูล";
                    noDataMessage.className = "text-red-600 font-bold text-center mt-4";
                    table.parentNode.appendChild(noDataMessage);
                }
            } else if (noDataMessage) {
                noDataMessage.remove();
            }
        }
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById("searchInput");
            searchInput.focus();
            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.style.display = 'block';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 2000);
            }
        });
    </script>
</body>
</html>