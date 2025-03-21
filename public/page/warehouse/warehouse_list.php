<?php
require_once __DIR__ . '../../../../config/database_server.php';
function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die('Database error: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['warehousename'])) {
        $warehousename = $_POST['warehousename'] ?? '';
        $warehousestatus = $_POST['warehousestatus'] ?? '';
        try {
            $stmt = $pdo->prepare('INSERT INTO warehouse (warehouse_name , warehouse_status) VALUES (?,?)');
            $stmt->execute([$warehousename, $warehousestatus]);
            header('Location: /index.php?page=warehouse_list');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_warehousename'])) {
        $id = $_POST['id'];
        $newWarehouseName = $_POST['update_warehousename'];
        $newwarehousestatus = $_POST['update_warehousestatus'];
        try {
            $stmt = $pdo->prepare("UPDATE warehouse SET warehouse_name = ? , warehouse_status = ? WHERE warehouse_id = ?");
            $stmt->execute([$newWarehouseName, $newwarehousestatus, $id]);
            header('Location: /index.php?page=warehouse_list');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    }
}

// Deleting data based on 'id' parameter in $_GET
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM warehouse WHERE warehouse_id = ?");
        $stmt->execute([$id]);
        header("Location: ?page=warehouse_list");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Count total records in the warehouse table
$query = "SELECT COUNT(*) as total_count FROM warehouse";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $total_query = "SELECT COUNT(*) FROM warehouse"; // Change to warehouse
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Fetch data for current page
try {
    // Fetch lot list
    $query = "SELECT
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
    FROM
        lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id";
    $result_lot_list = fetchData($pdo, $query);

    // Store result for later use in warehouse cards
    $lots = $result_lot_list->fetchAll(PDO::FETCH_ASSOC);

    // Fetch lot_id options
    $query = "SELECT lot_id FROM lot";
    $lot_id_options = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch lot status data
    $query = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $status_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch warehouse data
    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse ORDER BY warehouse_id";
    $warehouse_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch supplier data
    $query = "SELECT supplier_id, supplier_name FROM supplier";
    $supplier_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch lot product data
    $query = "SELECT lot_product_id, lot_product_qty, lot_product_remain, lot_product_capital FROM lot_product";
    $result = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Set default status ID if not selected
    $selected_status_id = $_POST['lot_status_id'] ?? '';
    if ($selected_status_id === '') {
        $selected_status_id = $status_lot[0]['lot_status_id'] ?? '';
    }

    // Count lots per warehouse
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
    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <link href="../../output.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
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
            background-color: #aec5e366;
            color: black;
            border-radius: 5px;
            margin: 0 4px;
        }

        .pagination a.active {
            background-color: #333;
            ;
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
    </style>
    <title>Warehouse List</title>
</head>

<body>
    <div class="font-[Tahoma] mx-[15rem] mt-11">
        <div class="title">
            <div class="count">
                <h2 class="text-2xl font-bold">ข้อมูลคลังสินค้า</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาคลังสินค้า...">
            </div>
        </div>

        <table class=" mt-5 mb-8 w-[100%]  border-2 border-[#A0A0A0] shadow-lg " id="dataTable">
            <tr>
                <form action="" method="POST">
                    <td class=" bg-white ">
                        <input type="text" id="warehousename" name="warehousename" placeholder="ชื่อคลังสินค้า"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none hover:border-gray-600 " />
                    </td>
                    <td class=" bg-white ">
                        <input type="text" id="warehousestatus" name="warehousestatus" placeholder="สถานะ"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none hover:border-gray-600" />
                    </td>
                    <td class=" bg-white ">
                        <button type="submit"
                            class="px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                            เพิ่ม
                        </button>
                    </td>
                </form>
            </tr>
            <thead class="">
                <tr>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ชื่อคลังสินค้า</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">สถานะ</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">การจัดการ</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach ($warehouse_lot as $warehouse): ?>
                    <tr class=" bg-white hover:bg-[#b7babe66] cursor-pointer" data-id=" <?php echo $warehouse['warehouse_id']; ?>">
                        <td class="warehouse-name"><?php echo $warehouse['warehouse_name']; ?></td>
                        <td class="warehouse-status"><?php echo $warehouse['warehouse_status']; ?></td>
                        <td>
                            <button onclick="enableEdit(this)" class="edit-button 
                            bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 
                            text-white 
                            text-lg 
                            border border-yellow-700 
                            border-t-white 
                            border-l-white 
                            px-2 
                            py-1 
                            rounded 
                            shadow-md 
                            hover:bg-[#FFF9C4] 
                            active:border-[#FFF176] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner
                            ">แก้ไข</button>

                            <button class=" bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 
                            border-t-white 
                            border-l-white 
                            px-4 
                            py-1 
                            rounded 
                                            shadow-md 
                            hover:bg-[#FFEBEE] 
                            active:border-[#EF9A9A] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner">
                                <a href="?page=warehouse_list&id=<?php echo $warehouse['warehouse_id']; ?>" onclick="return confirm('ยืนยันการลบข้อมูล?')" class="delete-button ">ลบ</a>
                            </button>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <!-- Pagination for table -->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=warehouse_list&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=warehouse_list&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=warehouse_list&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <!-- Warehouse Cards Section -->
        <h2 class="text-2xl font-bold text-gray-800 mb-6">คลังสินค้า</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
            <?php foreach ($warehouse_lot as $warehouse): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300"
                    onclick="window.location.href='?page=warehouse_lot&warehouse_id=<?= htmlspecialchars($warehouse['warehouse_id']) ?>'">
                    <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                        <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($warehouse['warehouse_name']) ?></h3>
                    </div>
                    <div class="p-6 flex justify-between items-center">
                        <div>
                            <p class="text-gray-600 mb-2"><i class="fas fa-box mr-2"></i>จำนวน Lot ทั้งหมด</p>
                            <p class="text-3xl font-bold text-blue-600"><?= $warehouse_lot_counts[$warehouse['warehouse_id']] ?? 0 ?></p>
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

            // Always keep the header visible
            thead.style.display = "";

            // Always keep the input row visible 
            tr[0].style.display = "";

            // Search through data rows (start from index 2 to skip header and input row)
            for (let i = 2; i < tr.length; i++) {
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
                    noDataMessage.style.color = "red";
                    noDataMessage.style.fontWeight = "bold";
                    noDataMessage.style.textAlign = "center";
                    noDataMessage.style.marginTop = "10px";
                    table.parentNode.insertBefore(noDataMessage, table.nextSibling);
                }
            } else {
                if (noDataMessage) {
                    noDataMessage.remove();
                }
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
                // Switch to edit mode
                warehouseNameCell.innerHTML = `<input type="text" class="edit-input" style="border: 1px solid #333;" value="${originalWarehouseName}" />`;
                warehouseStatusCell.innerHTML = `<input type="text" class="edit-input" style="border: 1px solid #333;" value="${originalWarehouseStatus}" />`;

                button.innerText = 'บันทึก';
                const deleteButton = row.querySelector('.delete-button');
                const editButton = row.querySelector('.edit-button');
                deleteButton.style.display = 'none';
                editButton.style.display = 'none';

                // Create and display a save button
                const saveButton = document.createElement('button');
                saveButton.innerText = 'บันทึก';
                saveButton.classList.add('save-button', 'bg-gradient-to-b',
                    'from-green-300',
                    'via-green-500',
                    'to-green-700',
                    'text-white',
                    'text-lg',
                    'border',
                    'border-green-700',
                    'border-t-white',
                    'border-l-white',
                    'px-2',
                    'py-1',
                    'rounded',
                    'shadow-md',
                    'hover:bg-[#E8F5E9]',
                    'active:border-[#A5D6A7]',
                    'active:border-b-white',
                    'active:border-r-white',
                    'active:shadow-inner');
                saveButton.onclick = function() {
                    // Save changes
                    const newWarehouseName = warehouseNameCell.querySelector('.edit-input').value;
                    const newWarehouseStatus = warehouseStatusCell.querySelector('.edit-input').value;

                    // Update the warehouse in the database
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `id=${warehouseId}&update_warehousename=${encodeURIComponent(newWarehouseName)}&update_warehousestatus=${encodeURIComponent(newWarehouseStatus)}`
                    }).then(() => {
                        // Update the table with new values after saving
                        warehouseNameCell.innerHTML = newWarehouseName;
                        warehouseStatusCell.innerHTML = newWarehouseStatus;
                        // Reset to edit mode
                        button.innerText = 'แก้ไข';
                        deleteButton.style.display = 'inline-block';
                        editButton.style.display = 'inline-block';
                        saveButton.remove(); // Remove the save button
                    }).catch(error => {
                        console.error('Error:', error);
                        alert('ไม่สามารถบันทึกการเปลี่ยนแปลงได้');
                    });
                };
                row.querySelector('td:last-child').appendChild(saveButton);
            }
        }
    </script>

    <script>
        // เมื่อคลิกปุ่มแก้ไข ให้ซ่อนปุ่มแก้ไขและลบ และแสดงปุ่มบันทึก
        document.querySelectorAll('.edit-button').forEach(button => {
            button.addEventListener('click', function() {
                // หยุดการกระทำของลิงก์
                event.preventDefault();
                // ซ่อนปุ่มแก้ไขและลบ
                this.parentElement.querySelector('.edit-delete-buttons').style.display = 'none';
                // แสดงปุ่มบันทึก
                this.parentElement.querySelector('.save-button').style.display = 'block';
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById("searchInput");
            searchInput.focus();
            if (urlParams.has('edit_success')) {
                const notificationEdit = document.getElementById('notification-edit');
                notificationEdit.style.display = 'block';
                setTimeout(() => {
                    notificationEdit.style.display = 'none';
                }, 2000);
            } else if (urlParams.has('delete_success')) {
                const notificationDelete = document.getElementById('notification-delete');
                notificationDelete.style.display = 'block';
                setTimeout(() => {
                    notificationDelete.style.display = 'none';
                }, 2000);
            }
        });
    </script>
</body>

</html>