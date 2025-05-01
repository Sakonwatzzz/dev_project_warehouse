<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Database queries for dropdowns
function fetchDataFromTable($pdo, $query)
{
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch dropdown data
$status_lot = fetchDataFromTable($pdo, "SELECT lot_status_id, lot_status_name FROM lot_status");
$warehouse_lot = fetchDataFromTable($pdo, "SELECT warehouse_id, warehouse_name FROM warehouse");
$supplier_lot = fetchDataFromTable($pdo, "SELECT supplier_id, supplier_name FROM supplier");

// Handle delete operation
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false) {
        echo "Invalid lot ID";
        exit;
    }

    try {
        // Start a transaction
        $pdo->beginTransaction();

        // Delete related records in lot_product
        $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_id = ?");
        $stmt->execute([$id]);

        // Delete the lot record
        $stmt = $pdo->prepare("DELETE FROM lot WHERE lot_id = ?");
        $stmt->execute([$id]);

        // Commit the transaction
        $pdo->commit();

        header("Location: http://localhost:8000/index.php?page=lot_warehouseinout");
        exit;
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $pdo->rollBack();
        echo "Error: " . $e->getMessage();
        exit;
    }
}

// Pagination setup
try {
    $records_per_page = 5;
    $page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
    $offset = ($page - 1) * $records_per_page;

    // Get total count
    $query = "SELECT COUNT(*) as total_count FROM lot WHERE lot.warehouse_inout = 2";
    $stmt = $pdo->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];
    $total_pages = ceil($count / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Fetch paginated data
try {
    $query = "
    SELECT
        lot_status.lot_status_id, 
        lot_status.lot_status_name, 
        supplier.supplier_id, 
        supplier.supplier_name, 
        warehouse.warehouse_name, 
        warehouse.warehouse_id,
        lot.lot_id, 
        lot.lot_detail, 
        lot.lot_date, 
        lot.lot_status_id, 
        lot.warehouse_id
    FROM
        lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
    WHERE lot.warehouse_inout = 2
    ORDER BY lot.lot_date DESC
    LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $result_lot_list = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>ระบบจัดการล็อตสินค้า</title>
    <style>
        .zoom-in {
            cursor: zoom-in;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="font-[Tahoma] mx-auto max-w-[80rem] px-4 py-8 mt-8 sm:px-6 lg:px-8 ">
        <div class="grid grid-cols-1 md:grid-cols-2 items-center justify-between gap-4 mb-6">
            <div class=" flex items-center gap-6">
                <h2 class="text-2xl font-bold text-gray-800">จำนวนรายการย้ายสินค้ามี:</h2>
                <div class="px-4 py-1 bg-white border border-gray-300 rounded shadow text-center min-w-[50px]">
                    <?php echo $count; ?>
                </div>
                <a href="/?page=lot_listinsert"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow transition-colors duration-200">
                    <i class="fas fa-plus mr-2"></i>เพิ่มการย้ายสินค้า
                </a>
            </div>

            <div class="text-end">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1"
                    placeholder="ค้นหารายการรับสินค้า..."
                    class="w-[350px] px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

         <!-- Table -->
         <div class="overflow-x-auto">
            <table class="mt-1 w-[100%] bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg" id="dataTable">
                <thead>
                    <tr>
                        <th
                            class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            วันและเวลาที่ล็อต</th>
                        <th
                            class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            คลังสินค้า</th>
                        <th
                            class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            ผู้ส่งมอบ</th>
                        <th
                            class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            สถานะล็อต</th>
                        <th
                            class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_lot_list->rowCount() > 0): ?>
                        <?php while ($lot_list_move = $result_lot_list->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr class="bg-white hover:bg-gray-100 cursor-pointer">
                                <td hidden><?php echo htmlspecialchars($lot_list_move['lot_id']); ?></td>
                                <td hidden><?php echo htmlspecialchars($lot_list_move['lot_detail']); ?></td>
                                <td class="px-4 py-3 border border-gray-200 hover:bg-gray-200 cursor-pointer">
                                    <a href="?page=warehouse_move&lot_id=<?php echo htmlspecialchars($lot_list_move['lot_id']); ?>"
                                        class="text-blue-600 hover:text-blue-800 hover:underline">
                                        <?php echo htmlspecialchars($lot_list_move['lot_date']); ?>
                                    </a>
                                </td>
                                <td class="zoom-in px-4 py-3 border border-gray-200 hover:bg-gray-200 cursor-pointer"
                                    onclick="window.location.href='?page=warehouse_lot&warehouse_id=<?php echo htmlspecialchars($lot_list_move['warehouse_id']); ?>'">
                                    <span><?php echo htmlspecialchars($lot_list_move['warehouse_name']); ?></span>
                                </td>
                                <td class="zoom-in px-4 py-3 border border-gray-200 hover:bg-gray-200 cursor-pointer"
                                    onclick="window.location.href='?page=supplier_movelot&supplier_id=<?php echo htmlspecialchars($lot_list_move['supplier_id']); ?>'">
                                    <span><?php echo htmlspecialchars($lot_list_move['supplier_name']); ?></span>
                                </td>
                                <td class="px-4 py-3 border border-gray-200 hover:bg-blue-50 cursor-pointer"
                                    onclick="window.location.href='?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot_list_move['lot_id']); ?>'">
                                    <?php echo htmlspecialchars($lot_list_move['lot_status_name']); ?>
                                </td>
                                <td class="px-4 py-3 border border-gray-200">
                                    <?php if ($lot_list_move['lot_status_name'] !== 'ยืนยันแล้ว' && $lot_list_move['lot_status_id'] != 2): ?>
                                        <div class="flex space-x-2">
                                            <a href="?page=lot_insertdetailout&tabel=lot&id=<?php echo htmlspecialchars($lot_list_move['lot_id']); ?>"
                                                class="inline-block px-3 py-1 bg-yellow-500 hover:bg-yellow-600 text-white rounded shadow transition-colors duration-150">
                                                แก้ไข
                                            </a>
                                            <a href="?page=lot_warehouseinout&id=<?php echo htmlspecialchars($lot_list_move['lot_id']); ?>"
                                                onclick="return confirm('กรุณาตรวจสอบข้อมูลก่อนลบ ถ้าคุณแน่ใจแล้วให้กดลบ')"
                                                class="inline-block px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded shadow transition-colors duration-150">
                                                ลบ
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-center border border-gray-200">ไม่มีข้อมูล</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>


        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=lot_warehouseinout&page_num=<?php echo ($page - 1); ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition-colors duration-150">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="px-4 py-2 bg-gray-500 text-white rounded">
                            <?php echo $i; ?>
                        </a>
                    <?php else: ?>
                        <a href="?page=lot_warehouseinout&page_num=<?php echo $i; ?>"
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition-colors duration-150">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_warehouseinout&page_num=<?php echo ($page + 1); ?>"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded transition-colors duration-150">
                        ถัดไป »
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
                    if (txtValue.toLower.indexOf(filter) > -1) {
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
        });
    </script>
</body>

</html>