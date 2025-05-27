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
            header('Location: /index.php?page=warehouse_list&add_success=1');
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
            header('Location: /index.php?page=warehouse_list&edit_success=1');
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
        header("Location: ?page=warehouse_list&delete_success=1");
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
    <div class="mx-[8rem] mt-11">
        <!-- Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#d7e1ec] to-[#e6effb] p-4 border-b border-[#cfd5dc]">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list mr-2"></i>รายการคลังสินค้า
                </h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse" id="dataTable">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัสคลัง</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อคลังสินค้า</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">สถานะ</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">จำนวน Lot</th>
                                <th class="px-6 py-3 border text-center text-gray-700 font-semibold">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($warehouses) > 0): ?>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-6 py-2 border cursor-pointer hover:underline zoom-in"><?php echo htmlspecialchars($warehouse['warehouse_id']); ?></td>
                                        <td class="px-6 py-2 border cursor-pointer hover:underline zoom-in warehouse-name"><?php echo htmlspecialchars($warehouse['warehouse_name']); ?></td>
                                        <td class="px-6 py-2 border warehouse-status"><?php echo htmlspecialchars($warehouse['warehouse_status']); ?></td>
                                        <td class="px-6 py-2 border"><?php echo htmlspecialchars($warehouse['lot_count']); ?></td>
                                        <td class="px-6 py-2 border text-center">
                                            <div class="flex justify-center space-x-2">

                                                <button onclick="enableEdit(this)"
                                                    class="edit-btn bg-yellow-500 text-white px-4 py-1 rounded hover:bg-yellow-600 transition flex items-center">
                                                    <i class="fas fa-edit mr-1"></i>แก้ไข
                                                </button>
                                                <a href="?page=warehouse_list&id=<?php echo htmlspecialchars($warehouse['warehouse_id']); ?>"
                                                    onclick="return confirm('ยืนยันการลบข้อมูล?')"
                                                    class="delete-btn bg-red-500 text-white px-4 py-1 rounded hover:bg-red-600 transition flex items-center">
                                                    <i class="fas fa-trash-alt mr-1"></i>ลบ
                                                </a>
                                                <button class="save-btn hidden bg-green-500 text-white px-4 py-1 rounded hover:bg-green-600 transition  items-center"
                                                    onclick="saveEdit(this)">
                                                    <i class="fas fa-save mr-1"></i>บันทึก
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-gray-600">ไม่มีข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

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
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8 ">
            <?php foreach ($warehouses as $warehouse): ?>
                <div class="bg-white rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-300 cursor-pointer shadow-md "
                    onclick="window.location.href='?page=warehouse_lot&warehouse_id=<?= htmlspecialchars($warehouse['warehouse_id']) ?>'">
                    <div class="bg-blue-100 p-4 border-b border-gray-200">
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

    <!-- Notification -->
    <div id="notification" class="hidden fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white border-2 border-gray-400 rounded-md shadow-lg w-96 h-32 z-50">
        <div id="notification-header" class="p-2 rounded-t-md font-bold"></div>
        <div id="notification-body" class="p-5 text-center text-lg"></div>
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
            const warehouseId = row.getAttribute('data-id');

            const originalWarehouseName = warehouseNameCell.textContent.trim();

            // Replace cell content with input field

            warehouseNameCell.innerHTML = `<input type="text" class="edit-input w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="${originalWarehouseName}" />`;


            // Hide edit and delete buttons, show save button
            button.classList.add('hidden');
            row.querySelector('.delete-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
        }

        function saveEdit(button) {
            const row = button.closest('tr');
            const warehouseId = row.getAttribute('data-id');
            const newName = row.querySelector('.edit-input').value.trim();
            const warehouseNameCell = row.querySelector('.warehouse-name');

            if (!newName) {
                alert('กรุณากรอกชื่อประเภทสมาชิก');
                return;
            }

            // Send update request
            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${warehouseId}&update_member_type=${encodeURIComponent(newName)}`
                })
                .then(response => {
                    if (response.ok) {
                        // Update UI
                        warehouseNameCell.textContent = newName;
                        const editBtn = row.querySelector('.edit-btn');
                        const deleteBtn = row.querySelector('.delete-btn');
                        editBtn.classList.remove('hidden');
                        deleteBtn.classList.remove('hidden');
                        button.classList.add('hidden');
                        showNotification('แก้ไขสำเร็จ', 'bg-cyan-200');
                    } else {
                        throw new Error('Failed to save');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('เกิดข้อผิดพลาดในการบันทึก', 'bg-red-200');
                });
        }

        function showNotification(message, headerClass) {
            const notification = document.getElementById('notification');
            const header = document.getElementById('notification-header');
            const body = document.getElementById('notification-body');

            header.className = `p-2 rounded-t-md font-bold ${headerClass}`;
            header.textContent = 'แจ้งเตือน';
            body.textContent = message;
            notification.classList.remove('hidden');

            setTimeout(() => {
                notification.classList.add('hidden');
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('add_success')) {
                showNotification('เพิ่มข้อมูลสำเร็จ', 'bg-cyan-200');
            } else if (urlParams.has('edit_success')) {
                showNotification('แก้ไขข้อมูลสำเร็จ', 'bg-cyan-200');
            } else if (urlParams.has('delete_success')) {
                showNotification('ลบข้อมูลสำเร็จ', 'bg-cyan-200');
            }
            document.getElementById('searchInput').focus();
        });
    </script>
</body>

</html>