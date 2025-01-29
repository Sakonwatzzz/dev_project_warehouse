<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// จัดการการส่งข้อมูลแบบ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // กรณีเพิ่มคลังสินค้าใหม่
    if (isset($_POST['new_warehouse'])) {
        $warehousename = trim($_POST['warehousename'] ?? '');
        $warehousestatus = trim($_POST['warehousestatus'] ?? '');

        if (!empty($warehousename) && !empty($warehousestatus)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO warehouse (warehouse_name, warehouse_status) VALUES (?, ?)');
                $stmt->execute([$warehousename, $warehousestatus]);
                header('Location: ' . $_SERVER['PHP_SELF'] . '?success=created');
                exit;
            } catch (PDOException $e) {
                $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
            }
        } else {
            $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
        }
    }

    // กรณีอัปเดตข้อมูลคลังสินค้า
    elseif (isset($_POST['update_warehouse'])) {
        $id = $_POST['warehouse_id'] ?? '';
        $newWarehouseName = trim($_POST['update_warehousename'] ?? '');
        $newWarehouseStatus = trim($_POST['update_warehousestatus'] ?? '');

        if (!empty($id) && !empty($newWarehouseName) && !empty($newWarehouseStatus)) {
            try {
                $stmt = $pdo->prepare("UPDATE warehouse SET warehouse_name = ?, warehouse_status = ? WHERE warehouse_id = ?");
                $stmt->execute([$newWarehouseName, $newWarehouseStatus, $id]);
                header('Location: /index.php?page=lot_listinsert&success=updated');
                exit;
            } catch (PDOException $e) {
                $error = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $e->getMessage();
            }
        } else {
            $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
        }
    }
    // กรณีเพิ่มข้อมูล lot
    else {
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
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// ดึงข้อมูลสถานะ lot
$query = "SELECT lot_status_id, lot_status_name FROM lot_status";
$stmt = $pdo->prepare($query);
$stmt->execute();
$status_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลคลังสินค้า
$query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse ORDER BY warehouse_id";
$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลผู้ส่งมอบ
$query = "SELECT supplier_id, supplier_name FROM supplier";
$stmt = $pdo->prepare($query);
$stmt->execute();
$supplier_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลร้านค้า
$query = "SELECT store_id, store_name FROM store";
$stmt = $pdo->prepare($query);
$stmt->execute();
$store_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการคลังสินค้า</title>
</head>

<body>
    <h1 class="mx-11 my-8 text-4xl font-bold">รอบของสินค้า</h1>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mx-11 mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div id="success-alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mx-11 mb-4">
            <?php
            if ($_GET['success'] === 'created') {
                echo 'เพิ่มคลังสินค้าใหม่เรียบร้อยแล้ว';
            } elseif ($_GET['success'] === 'updated') {
                echo 'อัปเดตข้อมูลคลังสินค้าเรียบร้อยแล้ว';
            }
            ?>
        </div>

    <?php endif; ?>

    <div class="flex">
        <div class="relative w-full  mx-11 flex gap-11">
            <!-- ส่วนซ้าย (การจัดการคลังสินค้า) -->
            <div class="w-[40%] border border-[#7a7a7a] rounded-lg bg-[#cbdbe4]">
                <div class="flex justify-between items-center px-4 py-2">
                    <h2 class="text-2xl">คลังสินค้า</h2>
                    <button onclick="showNewWarehouseForm()" class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-1 rounded shadow-md">
                        เพิ่มคลัง
                    </button>
                </div>

                <!-- แบบฟอร์มเพิ่มคลังใหม่ (ซ่อนไว้) -->
                <div id="newWarehouseForm" class="hidden px-4 py-2 bg-white">
                    <form method="POST" class="space-y-3">
                        <div>
                            <label class="block">ชื่อคลังสินค้า:</label>
                            <input type="text" name="warehousename" required class="w-full border rounded px-2 py-1">
                        </div>
                        <div>
                            <label class="block">สถานะ:</label>
                            <input type="text" name="warehousestatus" required class="w-full border rounded px-2 py-1">
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="hideNewWarehouseForm()" class="bg-gray-500 text-white px-3 py-1 rounded">
                                ยกเลิก
                            </button>
                            <button type="submit" name="new_warehouse" class="bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white px-3 py-1 rounded">
                                บันทึก
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ตารางแสดงรายการคลังสินค้า -->
                <table class="w-full ">
                    <thead>
                        <tr>
                            <th class="w-[40%]  bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                ชื่อคลังสินค้า
                            </th>
                            <th class="w-[30%] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                สถานะ
                            </th>
                            <th class="w-[30%] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                การจัดการ
                            </th>
                        </tr>
                    </thead>
                    <tbody >
                        <?php foreach ($warehouse_lot as $warehouse): ?>
                            <tr data-id="<?= htmlspecialchars($warehouse['warehouse_id']) ?>" class="hover:bg-gray-100">
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300 warehouse-name">
                                    <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                </td>
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300 warehouse-status">
                                    <?= htmlspecialchars($warehouse['warehouse_status']) ?>
                                </td>
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300 text-center">
                                    <button onclick="enableEdit(this)" class="edit-button bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 text-white text-lg px-2 py-1 rounded shadow-md">
                                        แก้ไข
                                    </button>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ส่วนขวา (ฟอร์มเพิ่ม lot) -->
            <form method="POST" action="" class="w-[60%]">
                <div class="w-full flex justify-center items-center">
                    <div class="w-full flex flex-wrap space-y-3 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg p-4 border border-[#8f959b] shadow rounded-lg">
                        <!-- รหัส Lot สินค้า -->
                        <div class="w-full flex">
                            <label for="lot_number" class="w-[15%] mb-2">รหัส Lot สินค้า:</label>
                            <input type="text" name="lot_number" id="lot_number" required
                                class="w-[85%] border rounded px-2 py-1" placeholder="กรอกเลขที่ lot">
                        </div>

                        <!-- คลังสินค้า -->
                        <div class="w-full flex">
                            <label for="warehouse_id" class="w-[15%] mb-2">คลังสินค้า:</label>
                            <select name="warehouse_id" id="warehouse_id" required
                                class="w-[85%] border rounded px-2 py-1">
                                <?php foreach ($warehouse_lot as $warehouse): ?>
                                    <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                        <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- ผู้ส่งมอบ -->
                        <div class="w-full flex">
                            <label for="supplier_id" class="w-[15%] mb-2">ผู้ส่งมอบ:</label>
                            <select name="supplier_id" id="supplier_id" required
                                class="w-[85%] border rounded px-2 py-1">
                                <?php foreach ($supplier_lot as $supplier): ?>
                                    <option value="<?= htmlspecialchars($supplier['supplier_id']) ?>">
                                        <?= htmlspecialchars($supplier['supplier_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- วันที่รับสินค้า -->
                        <div class="w-full flex">
                            <label for="lot_date" class="w-[15%] mb-2">วันที่รับสินค้า:</label>
                            <input type="date" id="lot_date" name="lot_date"
                                value="<?= date('Y-m-d') ?>" readonly
                                class="w-[85%] border rounded px-2 py-1">
                        </div>

                        <!-- ปุ่มบันทึก -->
                        <div class="w-full flex justify-end mt-5">
                            <button type="submit" class="bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg px-4 py-1 rounded shadow-md">
                                บันทึก
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div


        <!-- เพิ่ม Script สำหรับจัดการฟอร์มและการแก้ไข -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // หาข้อความแจ้งเตือน
            const alert = document.getElementById('success-alert');

            if (alert) {
                // ซ่อนข้อความหลัง 2 วินาที
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';

                    // ลบ element ออกหลังจาก transition เสร็จสิ้น
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 2000);
            }
        });
    </script>
    <script>
        // ฟังก์ชันแสดงฟอร์มเพิ่มคลังใหม่
        function showNewWarehouseForm() {
            document.getElementById('newWarehouseForm').classList.remove('hidden');
        }

        // ฟังก์ชันซ่อนฟอร์มเพิ่มคลังใหม่
        function hideNewWarehouseForm() {
            document.getElementById('newWarehouseForm').classList.add('hidden');
        }

        // ฟังก์ชันเปิดโหมดแก้ไข
        function enableEdit(button) {
            const row = button.closest('tr');
            const warehouseNameCell = row.querySelector('.warehouse-name');
            const warehouseStatusCell = row.querySelector('.warehouse-status');
            const warehouseId = row.getAttribute('data-id');
            const managementCell = button.closest('td');

            if (button.innerText === 'แก้ไข') {
                const originalName = warehouseNameCell.innerText.trim();
                const originalStatus = warehouseStatusCell.innerText.trim();

                // Replace name cell content with input
                warehouseNameCell.innerHTML = `
            <input type="text" name="update_warehousename" value="${originalName}" 
                   class="w-full border rounded px-2 py-1" required>
        `;

                // Replace status cell content with input
                warehouseStatusCell.innerHTML = `
            <input type="text" name="update_warehousestatus" value="${originalStatus}" 
                   class="w-full border rounded px-2 py-1" required>
        `;

                // Replace management cell content with save/cancel buttons
                managementCell.innerHTML = `
            <form method="POST" class="flex justify-center space-x-2">
                <input type="hidden" name="warehouse_id" value="${warehouseId}">
                <input type="hidden" name="update_warehousename" value="${originalName}">
                <input type="hidden" name="update_warehousestatus" value="${originalStatus}">
                <button type="button" onclick="cancelEdit(this)" 
                        class="bg-gray-500 text-white text-lg px-2 py-1 rounded shadow-md">
                    ยกเลิก
                </button>               
                <button type="submit" name="update_warehouse" 
                        class="bg-gradient-to-b from-green-300 via-green-500 to-green-700 
                               text-white text-lg px-2 py-1 rounded shadow-md">
                    บันทึก
                </button>
            </form>
        `;

                // Add event listener to update hidden inputs when visible inputs change
                const visibleInputs = row.querySelectorAll('input[type="text"]');
                const hiddenInputs = managementCell.querySelectorAll('input[type="hidden"]');
                visibleInputs.forEach((input, index) => {
                    input.addEventListener('input', function() {
                        hiddenInputs[index + 1].value = this.value;
                    });
                });
            }
        }

        function cancelEdit(button) {
            const row = button.closest('tr');
            const warehouseNameCell = row.querySelector('.warehouse-name');
            const warehouseStatusCell = row.querySelector('.warehouse-status');
            const managementCell = button.closest('td');
            const form = managementCell.querySelector('form');

            // Get original values from hidden inputs
            const originalName = form.elements.update_warehousename.value;
            const originalStatus = form.elements.update_warehousestatus.value;

            // Restore original content
            warehouseNameCell.textContent = originalName;
            warehouseStatusCell.textContent = originalStatus;
            managementCell.innerHTML = `
        <button onclick="enableEdit(this)" class="edit-button bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 text-white text-lg px-2 py-1 rounded shadow-md">
            แก้ไข
        </button>
    `;
        }

        // ฟังก์ชันตรวจสอบการกดปุ่มแก้ไขและลบ
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-button').forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const editButtons = this.parentElement.querySelector('.edit-delete-buttons');
                    const saveButton = this.parentElement.querySelector('.save-button');

                    if (editButtons && saveButton) {
                        editButtons.style.display = 'none';
                        saveButton.style.display = 'block';
                    }
                });
            });
        });

        // Add form validation
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('submit', function(event) {
                if (event.target.querySelector('[name="update_warehouse"]')) {
                    const row = event.target.closest('tr');
                    const inputs = row.querySelectorAll('input[type="text"]');
                    let isValid = true;

                    inputs.forEach(input => {
                        if (!input.value.trim()) {
                            isValid = false;
                            input.classList.add('border-red-500');
                        } else {
                            input.classList.remove('border-red-500');
                        }
                    });

                    if (!isValid) {
                        event.preventDefault();
                        alert('กรุณากรอกข้อมูลให้ครบทุกช่อง');
                    }
                }
            });
        });
    </script>
</body>

</html>