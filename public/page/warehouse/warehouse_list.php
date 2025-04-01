<?php
require_once __DIR__ . '../../../../config/database_server.php';

function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die('Database error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Handle POST requests (Insert and Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['warehousename'])) {
        $warehousename = $_POST['warehousename'] ?? '';
        $warehousestatus = $_POST['warehousestatus'] ?? '';
        try {
            $stmt = $pdo->prepare('INSERT INTO warehouse (warehouse_name, warehouse_status) VALUES (?, ?)');
            $stmt->execute([$warehousename, $warehousestatus]);
            header('Location: /index.php?page=warehouse_list');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    } elseif (isset($_POST['update_warehousename'])) {
        $id = $_POST['id'];
        $newWarehouseName = $_POST['update_warehousename'];
        $newwarehousestatus = $_POST['update_warehousestatus'];
        try {
            $stmt = $pdo->prepare("UPDATE warehouse SET warehouse_name = ?, warehouse_status = ? WHERE warehouse_id = ?");
            $stmt->execute([$newWarehouseName, $newwarehousestatus, $id]);
            header('Location: /index.php?page=warehouse_list');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle DELETE request
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM warehouse WHERE warehouse_id = ?");
        $stmt->execute([$id]);
        header("Location: ?page=warehouse_list");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}

// Count total records in the warehouse table
$query = "SELECT COUNT(*) as total_count FROM warehouse";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $total_records = $pdo->query("SELECT COUNT(*) FROM warehouse")->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}

// Fetch warehouse data with lot count (for both table and cards)
try {
    $query = "
        SELECT 
            w.warehouse_id,
            w.warehouse_name,
            w.warehouse_status,
            COALESCE(COUNT(l.lot_id), 0) as lot_count
        FROM warehouse w
        LEFT JOIN lot l ON w.warehouse_id = l.warehouse_id
        GROUP BY w.warehouse_id, w.warehouse_name, w.warehouse_status
        LIMIT :offset, :records_per_page
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $warehouses = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}

// Fetch additional data (unchanged)
try {
    $query = "
        SELECT
            lot_status.lot_status_id, 
            lot_status.lot_status_name, 
            supplier.supplier_id, 
            supplier.supplier_name, 
            warehouse.warehouse_name AS warehouse_in_name, 
            warehouse.warehouse_name AS warehouse_name, 
            warehouse.warehouse_id,
            lot.lot_id, 
            lot.lot_name, 
            lot.lot_detail, 
            lot.lot_date, 
            lot.lot_status_id, 
            lot.warehouse_id, 
            lot.warehouse_from_id,
            lot.warehouse_inout
        FROM lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
    ";
    $result_lot_list = fetchData($pdo, $query);
    $lots = $result_lot_list->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT lot_id FROM lot";
    $lot_id_options = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $status_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse ORDER BY warehouse_id";
    $warehouse_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT supplier_id, supplier_name FROM supplier";
    $supplier_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT lot_product_id, lot_product_qty, lot_product_remain, lot_product_capital FROM lot_product";
    $result = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $selected_status_id = $_POST['lot_status_id'] ?? '';
    if ($selected_status_id === '') {
        $selected_status_id = $status_lot[0]['lot_status_id'] ?? '';
    }

    $warehouse_lot_counts = [];
    foreach ($warehouse_lot as $warehouse) {
        $warehouse_id = $warehouse['warehouse_id'];
        $warehouse_lot_counts[$warehouse_id] = 0;
        foreach ($lots as $lot) {
            if ($lot['warehouse_id'] == $warehouse_id) {
                $warehouse_lot_counts[$warehouse_id]++;
            }
        }
    }
} catch (Exception $e) {
    $error = "เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>Warehouse List</title>
</head>
<body class="font-[Tahoma]">
    <div class="mx-[15rem] mt-11">
        <div class="grid grid-cols-2 items-center justify-center gap-[5px]">
            <div class="flex gap-6 items-center">
                <h2 class="text-2xl font-bold">จำนวนคลังสินค้า</h2>
                <div class="text-base text-right text-black py-1 px-[10px] border border-gray-500 rounded-md bg-white inline-block mt-[10px] shadow-sm w-[70px]">
                    <?= $count ?>
                </div>
            </div>
            <div class="mt-4 md:mt-0">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1"
                    placeholder="ค้นหาคลังสินค้า..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <table class="mt-5 mb-8 w-[100%] border-2 border-[#A0A0A0] shadow-lg" id="dataTable">
            <tr>
                <form action="" method="POST">
                    <td class="bg-white">
                        <input type="text" id="warehousename" name="warehousename" placeholder="ชื่อคลังสินค้า"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none hover:border-gray-600" />
                    </td>
                    <td class="bg-white">
                        <input type="text" id="warehousestatus" name="warehousestatus" placeholder="สถานะ"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none hover:border-gray-600" />
                    </td>
                    <td class="bg-white py-2 flex justify-center items-center">
                        <button type="submit"
                            class="w-[130px] px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                            เพิ่ม
                        </button>
                    </td>
                </form>
            </tr>
            <thead>
                <tr>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ชื่อคลังสินค้า</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">สถานะ</th>
                    <th class="w-[250px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">การจัดการ</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach ($warehouses as $warehouse): ?>
                    <tr class="bg-white hover:bg-[#b7babe66] cursor-pointer" data-id="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                        <td class="warehouse-name border p-2"><?= htmlspecialchars($warehouse['warehouse_name']) ?></td>
                        <td class="warehouse-status border p-2 text-center"><?= htmlspecialchars($warehouse['warehouse_status']) ?></td>
                        <td class="border p-2 flex gap-2 justify-center items-center">
                            <button onclick="enableEdit(this)" class="edit-button 
                                bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 
                                text-white text-lg border border-yellow-700 border-t-white border-l-white 
                                px-2 py-1 rounded shadow-md hover:bg-yellow-100 
                                active:border-yellow-300 active:border-b-white active:border-r-white active:shadow-inner">
                                แก้ไข
                            </button>
                            <button class="bg-gradient-to-b from-red-300 via-red-500 to-red-700 
                                text-white text-lg border border-red-700 border-t-white border-l-white 
                                px-4 py-1 rounded shadow-md hover:bg-red-100
                                active:border-red-300 active:border-b-white active:border-r-white active:shadow-inner">
                                <a href="?page=warehouse_list&id=<?= htmlspecialchars($warehouse['warehouse_id']) ?>" 
                                   onclick="return confirm('ยืนยันการลบข้อมูล?')" class="delete-button text-white no-underline">
                                    ลบ
                                </a>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination for table -->
        <div class="pagination mt-5 flex justify-center items-center gap-2">
            <?php if ($total_pages > 1): ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=warehouse_list&page_num=<?= $page - 1 ?>"
                       class="px-3 py-1 bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=warehouse_list&page_num=1"
                       class="px-3 py-1 bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=warehouse_list&page_num=<?= $i ?>"
                       class="px-3 py-1 rounded-md transition-colors <?= $i == $page ? 'bg-gray-800 text-white' : 'bg-[#aec5e366] text-black hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=warehouse_list&page_num=<?= $total_pages ?>"
                       class="px-3 py-1 bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>

                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=warehouse_list&page_num=<?= $page + 1 ?>"
                       class="px-3 py-1 bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Warehouse Cards Section -->
        <h2 class="text-2xl font-bold text-gray-800 mb-6">คลังสินค้า</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
            <?php foreach ($warehouses as $warehouse): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300"
                     onclick="window.location.href='?page=warehouse_lot&warehouse_id=<?= htmlspecialchars($warehouse['warehouse_id']) ?>'">
                    <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                        <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($warehouse['warehouse_name']) ?></h3>
                    </div>
                    <div class="p-6 flex justify-between items-center">
                        <div>
                            <p class="text-gray-600 mb-2"><i class="fas fa-box mr-2"></i>จำนวน Lot ทั้งหมด</p>
                            <p class="text-3xl font-bold text-blue-600"><?= $warehouse['lot_count'] ?></p>
                        </div>
                        <div class="text-blue-500">
                            <i class="fas fa-chevron-right text-2xl"></i>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 border-t">
                        <p class="text-sm text-gray-500"><i class="fas fa-info-circle mr-1"></i> คลิกเพื่อดู Lot ทั้งหมดในคลังนี้</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            const thead = table.querySelector("thead");
            let foundAny = false;

            thead.style.display = "";
            tr[0].style.display = "";

            for (let i = 2; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName("td");
                let rowMatchesSearch = false;

                for (let j = 0; j < td.length; j++) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        rowMatchesSearch = true;
                        break;
                    }
                }

                tr[i].style.display = rowMatchesSearch ? "" : "none";
                if (rowMatchesSearch) foundAny = true;
            }

            let noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    noDataMessage = document.createElement("p");
                    noDataMessage.id = "noDataMessage";
                    noDataMessage.textContent = "ไม่พบข้อมูล";
                    noDataMessage.className = "text-red-600 font-bold text-center mt-3";
                    table.parentNode.insertBefore(noDataMessage, table.nextSibling);
                }
            } else if (noDataMessage) {
                noDataMessage.remove();
            }
        }

        function enableEdit(button) {
            const row = button.closest('tr');
            const warehouseNameCell = row.querySelector('.warehouse-name');
            const warehouseStatusCell = row.querySelector('.warehouse-status');
            const warehouseId = row.getAttribute('data-id');

            const originalWarehouseName = warehouseNameCell.innerText;
            const originalWarehouseStatus = warehouseStatusCell.innerText;

            if (button.innerText === 'แก้ไข') {
                warehouseNameCell.innerHTML = `<input type="text" class="edit-input w-full border border-gray-500" style="border: 1px solid #333;" value="${originalWarehouseName}" />`;
                warehouseStatusCell.innerHTML = `<input type="text" class="edit-input w-full border border-gray-500" style="border: 1px solid #333;" value="${originalWarehouseStatus}" />`;

                button.innerText = 'บันทึก';
                const deleteButton = row.querySelector('.delete-button');
                const editButton = row.querySelector('.edit-button');
                deleteButton.style.display = 'none';
                editButton.style.display = 'none';

                const saveButton = document.createElement('button');
                saveButton.innerText = 'บันทึก';
                saveButton.className = 'save-button bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg border border-green-700 border-t-white border-l-white px-2 py-1 rounded shadow-md hover:bg-green-100 active:border-green-300 active:border-b-white active:border-r-white active:shadow-inner';
                saveButton.onclick = function() {
                    const newWarehouseName = warehouseNameCell.querySelector('.edit-input').value;
                    const newWarehouseStatus = warehouseStatusCell.querySelector('.edit-input').value;

                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `id=${warehouseId}&update_warehousename=${encodeURIComponent(newWarehouseName)}&update_warehousestatus=${encodeURIComponent(newWarehouseStatus)}`
                    }).then(() => {
                        warehouseNameCell.innerHTML = newWarehouseName;
                        warehouseStatusCell.innerHTML = newWarehouseStatus;
                        button.innerText = 'แก้ไข';
                        deleteButton.style.display = 'inline-block';
                        editButton.style.display = 'inline-block';
                        saveButton.remove();
                    }).catch(error => {
                        console.error('Error:', error);
                        alert('ไม่สามารถบันทึกการเปลี่ยนแปลงได้');
                    });
                };
                row.querySelector('td:last-child').appendChild(saveButton);
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById("searchInput");
            searchInput.focus();
            if (urlParams.has('edit_success')) {
                const notificationEdit = document.getElementById('notification-edit');
                if (notificationEdit) {
                    notificationEdit.style.display = 'block';
                    setTimeout(() => notificationEdit.style.display = 'none', 2000);
                }
            } else if (urlParams.has('delete_success')) {
                const notificationDelete = document.getElementById('notification-delete');
                if (notificationDelete) {
                    notificationDelete.style.display = 'block';
                    setTimeout(() => notificationDelete.style.display = 'none', 2000);
                }
            }
        });
    </script>
</body>
</html>