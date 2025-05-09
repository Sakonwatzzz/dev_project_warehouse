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
            product.product_name,
            supplier.supplier_name
        FROM
            lot_product
            LEFT JOIN lot ON lot_product.lot_id = lot.lot_id
            LEFT JOIN product ON lot_product.product_id = product.product_id
            LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
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
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการรับสินค้า<?php echo $lot_id ? " - Lot: $lot_name" : ""; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .zoom-in {
            cursor: zoom-in;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto  py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-6">
                <h2 class="text-2xl font-bold text-gray-800">ข้อมูลการรับสินค้า<?php echo $lot_id ? " - Lot: $lot_name" : ""; ?></h2>
                <div class="px-4 py-1 bg-white border border-gray-300 rounded shadow text-center min-w-[50px]">
                    <?php echo $count; ?>
                </div>
            </div>

            <div class="text-end">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1"
                    placeholder="ค้นหารายการรับสินค้า..."
                    class="w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <!-- Notification -->
        <div id="notification"
            class="fixed top-5 right-5 w-80 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden hidden">
            <div class="bg-green-500 text-white px-4 py-2 font-bold">การแจ้งเตือน</div>
            <div class="px-4 py-3">แก้ไขสมาชิกสำเร็จ!</div>
        </div>

        <!-- Back Button -->
        <div class="mb-2 w-[100px]">
            <a href="index.php?page=lot_warehouseinout" class="text-gray-700 px-4 py-2 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>กลับ
            </a>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list mr-2"></i>รายการรับสินค้า
                </h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse" id="dataTable">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left border text-gray-700 font-semibold">รหัสสินค้า</th>
                                <th class="px-4 py-3 text-left border text-gray-700 font-semibold">ชื่อล็อตสินค้า</th>
                                <th class="px-4 py-3 text-left border text-gray-700 font-semibold">ราคาต้นทุน</th>
                                <th class="px-4 py-3 text-left border text-gray-700 font-semibold">จำนวนสินค้าเข้า</th>
                                <th class="px-4 py-3 text-left border text-gray-700 font-semibold">จำนวนสินค้าคงเหลือ</th>
                                <th class="px-4 py-3 text-left border text-gray-700 font-semibold">สถานะสินค้า</th>
                                <th class="px-4 py-3 text-left border text-gray-700 font-semibold">ผู้ส่งมอบ</th>
                                <th class="px-4 py-3 text-left border text-gray-700 font-semibold">วันที่รับสินค้า</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_lotproduct_list->rowCount() > 0): ?>
                                <?php while ($lot_product_list = $result_lotproduct_list->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-1 py-2 border cursor-pointer hover:underline zoom-in text-center"><?php echo htmlspecialchars($lot_product_list['product_id']); ?></td>
                                        <td class="px-1 py-2 border cursor-pointer hover:underline zoom-in text-center"><?php echo htmlspecialchars($lot_product_list['lot_name']); ?></td>
                                        <td class="px-1 py-2 border text-end"><?php echo htmlspecialchars($lot_product_list['lot_product_capital']); ?></td>
                                        <td class="px-1 py-2 border text-end"><?php echo htmlspecialchars($lot_product_list['lot_product_qty']); ?></td>
                                        <td class="px-1 py-2 border text-end"><?php echo htmlspecialchars($lot_product_list['lot_product_remain']); ?></td>
                                        <td class="px-1 py-2 border text-center"><?php echo htmlspecialchars($lot_product_list['lot_product_status']); ?></td>
                                        <td class="px-1 py-2 border"><?php echo htmlspecialchars($lot_product_list['supplier_name']); ?></td>
                                        <td class="px-1 py-2 border text-center"><?php echo htmlspecialchars($lot_product_list['lot_product_date']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-gray-600">ไม่มีข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>



        <!-- Pagination -->
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=lot_productlist&page_num=1<?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « หน้าแรก
                    </a>
                    <a href="?page=lot_productlist&page_num=<?php echo ($page - 1); ?><?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="px-4 py-2 bg-gray-500 text-white rounded">
                            <?php echo $i; ?>
                        </a>
                    <?php else: ?>
                        <a href="?page=lot_productlist&page_num=<?php echo $i; ?><?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>"
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_productlist&page_num=<?php echo ($page + 1); ?><?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        ถัดไป »
                    </a>
                    <a href="?page=lot_productlist&page_num=<?php echo $total_pages; ?><?php echo $lot_id ? "&lot_id=$lot_id" : ""; ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition">
                        สุดท้าย »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search function
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            const thead = table.querySelector("thead");
            let foundAny = false;

            // Always keep the header visible
            thead.style.display = "";

            // Search through data rows (start from index 1 to skip header)
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName("td");
                let rowMatchesSearch = false;

                // Check every cell in the row
                for (let j = 0; j < td.length; j++) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        rowMatchesSearch = true;
                        break;
                    }
                }

                tr[i].style.display = rowMatchesSearch ? "" : "none";
                if (rowMatchesSearch) {
                    foundAny = true;
                }
            }

            // Handle "No data" message
            let noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    noDataMessage = document.createElement("p");
                    noDataMessage.id = "noDataMessage";
                    noDataMessage.textContent = "ไม่พบข้อมูล";
                    noDataMessage.className = "text-red-500 font-bold text-center mt-4";
                    table.parentNode.insertBefore(noDataMessage, table.nextSibling);
                }
            } else {
                if (noDataMessage) {
                    noDataMessage.remove();
                }
            }
        }

        // Notification handler
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById("searchInput");
            searchInput.focus();

            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.classList.remove('hidden');

                setTimeout(() => {
                    notification.classList.add('hidden');
                }, 2000);
            }
        });
    </script>
</body>

</html>