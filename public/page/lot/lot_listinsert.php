<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['warehousename'])) {
        $warehousename = $_POST['warehousename'] ?? '';
        $warehousestatus = $_POST['warehousestatus'] ?? '';
        try {
            $stmt = $pdo->prepare('INSERT INTO warehouse (warehouse_name, warehouse_status) VALUES (?, ?)');
            $stmt->execute([$warehousename, $warehousestatus]);
            header('Location: /index.php?page=lot_listinsert');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_warehousename'])) {
        $id = $_POST['id'];
        $newWarehouseName = $_POST['update_warehousename'];
        $newWarehouseStatus = $_POST['update_warehousestatus'];
        try {
            $stmt = $pdo->prepare("UPDATE warehouse SET warehouse_name = ?, warehouse_status = ? WHERE warehouse_id = ?");
            $stmt->execute([$newWarehouseName, $newWarehouseStatus, $id]);
            header('Location: /index.php?page=lot_listinsert');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    } else {
        $lot_detail = $_POST['lot_detail'] ?? '';
        $lot_number = $_POST['lot_number'] ?? '';
        $lot_date = $_POST['lot_date'] ?? '';
        $lot_status_id = $_POST['lot_status_id'] ?? '';
        $warehouse_id = $_POST['warehouse_id'] ?? '';
        $store_id = $_POST['store_id'] ?? '';
        $supplier_id = $_POST['supplier_id'] ?? '';
        try {
            $stmt = $pdo->prepare('INSERT INTO lot (lot_number, lot_date, lot_status_id, warehouse_id, store_id, supplier_id) 
            VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$lot_number, $lot_date, $lot_status_id, $warehouse_id, $store_id, $supplier_id]);
            $lot_id = $pdo->lastInsertId();
            header("Location: index.php?page=lot_listdetail&tabel=lot&id=$lot_id&success=1");
            exit;
        } catch (PDOException $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
}
// ดึงชื่อคลังมาแสดงผล
$query = "SELECT lot_status_id, lot_status_name FROM lot_status";
$stmt = $pdo->prepare($query);
$stmt->execute();
$status_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ดึงชื่อคลังมาแสดงผล
$query = "SELECT warehouse_id, warehouse_name, warehouse_status  FROM warehouse";
$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ดึงชื่อผู่ส่งมอบมาแสดงผล
$query = "SELECT supplier_id, supplier_name FROM supplier";
$stmt = $pdo->prepare($query);
$stmt->execute();
$supplier_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT store_id, store_name FROM store";
$stmt = $pdo->prepare($query);
$stmt->execute();
$store_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
ob_end_flush();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lot_list_insert</title>
</head>

<body>
    <h1 class="mx-11 my-8 text-4xl font-bold">รอบของสินค้า</h1>
    <div class="flex">
        <div class="relative w-full h-[500px] mx-11 flex gap-8">
            <!-- ส่วนแรก (ด้านซ้าย) -->
            <div class="w-[30%] border border-[#7a7a7a] rounded-lg bg-[#cbdbe4]">
                <h2 class="text-2xl px-4 py-2">คลังสินค้า</h2>
                <table class="w-full">
                    <thead class="">
                        <tr>
                            <th
                                class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">

                                ชื่อคลังสินค้า
                            </th>
                            <th
                                class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                สถานะ
                            </th>
                            <th
                                class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <form action="" method="POST">

                        <?php foreach ($warehouse_lot as $warehouse_lot_list): ?>
                            <tr data-id="<?= ($warehouse_lot_list['warehouse_id']); ?>"
                                class="hover:bg-gray-100 transition">
                                <!-- ชื่อคลังสินค้า -->
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300 warehouse-name">
                                    <?= ($warehouse_lot_list['warehouse_name']); ?>
                                </td>
                                <!-- สถานะคลัง -->
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300 warehouse-status">
                                    <?= ($warehouse_lot_list['warehouse_status']); ?>
                                </td>
                                <!-- การจัดการ -->
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300 text-center">
                                    <button onclick="enableEdit(this)" class="edit-button 
                    bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 
                    text-white text-lg border border-yellow-700 px-2 py-1 rounded shadow-md">
                                        แก้ไข
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </form>
                    </tbody>
                </table>
            </div>

            <!-- ส่วนที่สอง (ด้านขวา) -->
            <form method="POST" action="">
                <div class="w-[70%] flex justify-center items-center">
                    <div
                        class="flex flex-wrap space-y-3 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg p-- border border-[#8f959b] shadow rounded-lg">
                        <!-- Input สำหรับเลขที่ Lot -->
                        <div>
                            <label for="lot_number">รหัส Lot สินค้า:</label>
                            <input type="text" name="lot_number" id="lot_number" placeholder="กรอกเลขที่ lot">
                        </div>
                        <!-- Dropdown สำหรับคลังสินค้า -->
                        <div>
                            <label for="warehouse_id">คลังสินค้า:</label>
                            <select name="warehouse_id" id="warehouse_id">
                                <?php foreach ($warehouse_lot as $warehouse_lot_list): ?>
                                    <option value="<?= $warehouse_lot_list['warehouse_id'] ?>">
                                        <?= $warehouse_lot_list['warehouse_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="supplier_id">ผู้ส่งมอบ:</label>
                            <select name="supplier_id" id="supplier_id">
                                <?php foreach ($supplier_lot as $supplier): ?>
                                    <option value="<?= $supplier['supplier_id'] ?>"><?= $supplier['supplier_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- วันที่รับสินค้า -->
                        <div class="w-full px-2">
                            <label for="lot_product_date" class="text-[20px] pb-2 block">วันที่รับสินค้า:</label>
                            <input type="date" id="lot_product_date" name="lot_date" value="<?= date('Y-m-d') ?>"
                                class="border px-2 py-1 w-full rounded" readonly>
                        </div>
                        <div class="flex justify-end mt-5 gap-2">
                            <button class="
                bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg border border-green-700 
                border-t-white 
                border-l-white 
                px-4 
                py-1 
                rounded 
                shadow-md 
                hover:bg-[#E8F5E9] 
                active:border-[#A5D6A7] 
                active:border-b-white 
                active:border-r-white 
                active:shadow-inner
                " type="submit">บันทึก</button>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
    <script>

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
                saveButton.onclick = function () {
                    // Save changes
                    const newWarehouseName = warehouseNameCell.querySelector('.edit-input').value;
                    const newWarehouseStatus = warehouseStatusCell.querySelector('.edit-input').value;

                    // Update the warehouse in the database
                    fetch('warehouse_update.php', {
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
            button.addEventListener('click', function () {
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