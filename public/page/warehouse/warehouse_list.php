<?php
require_once __DIR__ . '../../../../config/database_server.php';

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
    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $warehouses = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
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
    <title>Warehouse List</title>
</head>

<body>
    <div class="personnel-list">
        <div class="title">
            <div class="count">
                <h2 style="font-weight: bold;">ข้อมูลคลังสินค้า</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาคลังสินค้า...">
            </div>
        </div>

        <table class="data-table" id="dataTable">
            <tr>
                <form action="" method="POST">
                    <td></td>
                    <td>
                        <input type="text" id="warehousename" name="warehousename" placeholder="ชื่อคลังสินค้า" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />
                    </td>
                    <td>
                        <input type="text" id="warehousestatus" name="warehousestatus" placeholder="สถานะ" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />
                    </td>
                    <td>
                        <button type="submit"
                            class="px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                            เพิ่ม
                        </button>
                    </td>
                </form>
            </tr>
            <thead>
                <tr>
                    <th>รหัสคลังสินค้า</th>
                    <th>ชื่อคลังสินค้า</th>
                    <th>สถานะ</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach ($warehouses as $warehouse): ?>
                    <tr data-id="<?php echo $warehouse['warehouse_id']; ?>">
                        <td><?php echo $warehouse['warehouse_id']; ?></td>
                        <td class="warehouse-name"><?php echo $warehouse['warehouse_name']; ?></td>
                        <td class="warehouse-status"><?php echo $warehouse['warehouse_status']; ?></td>
                        <td>
                            <button onclick="enableEdit(this)" class="edit-button text-blue-500 hover:text-blue-700">แก้ไข</button>
                            <a href="?page=warehouse_list&id=<?php echo $warehouse['warehouse_id']; ?>" onclick="return confirm('ยืนยันการลบข้อมูล?')" class="delete-button text-red-500 hover:text-red-700">ลบ</a>
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
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            let foundAny = false;

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName("td");
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none";
                if (found) {
                    foundAny = true;
                }
            }

            const noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    const message = document.createElement("p");
                    message.id = "noDataMessage";
                    message.textContent = "ไม่พบข้อมูล";
                    message.style.color = "red";
                    message.style.fontWeight = "bold";
                    message.style.marginTop = "10px";
                    table.parentNode.insertBefore(message, table);
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
                saveButton.classList.add('save-button', 'text-green-500', 'hover:text-green-700');
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
</body>

</html>