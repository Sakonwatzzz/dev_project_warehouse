<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

try {
    $query = "SELECT
        lot_status.lot_status_id, 
        lot_status.lot_status_name, 
        supplier.supplier_id, 
        supplier.supplier_name, 
        warehouse.warehouse_name, 
        warehouse.warehouse_id,
        store.store_id, 
        store.store_name, 
        lot.lot_id, 
        lot.lot_detail, 
        lot.lot_date, 
        lot.lot_status_id, 
        lot.warehouse_id, 
        lot.store_id
    FROM
        lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
        LEFT JOIN store ON lot.store_id = store.store_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result_lot_list = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}


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

    // กรณีเพิ่มข้อมูล lot
    else {
        $lot_id = $_POST['lot_id'] ?? ''; // รับค่า lot_id จาก POST
        $lot_detail = $_POST['lot_detail'] ?? '';
        $lot_date = $_POST['lot_date'] ?? '';
        $lot_status_id = $_POST['lot_status_id'] ?? '';
        $warehouse_id = $_POST['warehouse_id'] ?? '';
        $store_id = $_POST['store_id'] ?? '';
        $supplier_id = $_POST['supplier_id'] ?? '';
        $lot_product_qty = $_POST['lot_product_qty'] ?? '';
        $lot_product_remain = $_POST['lot_product_remain'] ?? '';
        $lot_product_capital = $_POST['lot_product_capital'] ?? '';
        $product_id = $_POST['product_id'] ?? '';

        try {
            $pdo->beginTransaction();

            // ใช้ lot_id จาก POST ในการ INSERT
            $stmt = $pdo->prepare('INSERT INTO lot (lot_id, lot_detail, lot_date, lot_status_id, warehouse_id, store_id, supplier_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$lot_id, $lot_detail, $lot_date, $lot_status_id, $warehouse_id, $store_id, $supplier_id]);

            $pdo->commit();

            // ใช้ lot_id จาก POST ในการส่งไปกับ URL
            header("Location: /?page=lot_listdetail&tabel=lot&id=" . $lot_id . "&success=1");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            echo $error;
        }
    }
}


// ดึงข้อมูลสถานะ lot
$query = "SELECT lot_status_id, lot_status_name  FROM lot_status";
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

// ดึงข้อมูลรายการรับสินค้า
$query = "SELECT lot_product_id,  lot_product_qty, lot_product_remain ,lot_product_capital FROM lot_product";
$stmt = $pdo->prepare($query);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    <div class="flex">
        <div class="relative w-full  mx-11 flex gap-11">
            <!-- ส่วนซ้าย (การจัดการคลังสินค้า) -->
            <div class="w-[40%] border border-[#7a7a7a] rounded-lg bg-[#cbdbe4]">
                <div class="flex justify-between items-center px-4 py-2">
                    <h2 class="text-2xl">คลังสินค้า</h2>
                    <button onclick="showNewWarehouseForm()"
                        class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-1 rounded shadow-md">
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
                            <button type="button" onclick="hideNewWarehouseForm()"
                                class="bg-gray-500 text-white px-3 py-1 rounded">
                                ยกเลิก
                            </button>
                            <button type="submit" name="new_warehouse"
                                class="bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white px-3 py-1 rounded">
                                บันทึก
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ตารางแสดงรายการคลังสินค้า -->
                <table class="w-full">
                    <thead>
                        <tr>
                            <th
                                class="w-[40%]  bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                ชื่อคลังสินค้า
                            </th>
                            <th
                                class="w-[30%] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                สถานะ
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warehouse_lot as $warehouse): ?>
                            <tr data-id="<?= ($warehouse['warehouse_id']) ?>" class="hover:bg-gray-100">
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300 warehouse-name">
                                    <?= ($warehouse['warehouse_name']) ?>
                                </td>
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300 warehouse-status">
                                    <?= ($warehouse['warehouse_status']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ส่วนขวา (ฟอร์มเพิ่ม lot) -->
            <form method="POST" action="" class="w-[60%]">
                <div class="w-full flex justify-center items-center">
                    <div
                        class="w-full flex flex-wrap space-y-3 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg p-4 border border-[#8f959b] shadow rounded-lg">


                        <!-- รหัส Lot สินค้า -->
                        <div class="w-full flex">
                            <label for="lot_id" class="w-[15%] mb-2">รหัส Lot สินค้า:</label>
                            <input type="text" name="lot_id" id="lot_id" required
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


                        <!-- ร้าน -->
                        <div class="w-full flex">
                            <label for="store_name" class="w-[15%] mb-2">ร้าน:</label>
                            <select name="store_id" id="store_name" required class="w-[85%] border rounded px-2 py-1">
                                <?php foreach ($store_lot as $store): ?>
                                    <option value="<?= htmlspecialchars($store['store_id']) ?>">
                                        <?= htmlspecialchars($store['store_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- รายละเอียด -->
                        <div class="w-full flex">
                            <label for="lot_detail" class="w-[15%] mb-2">รายละเอียด:</label>
                            <input type="text" name="lot_detail" id="lot_detail" required
                                class="w-[85%] border rounded px-2 py-1" placeholder="รายละเอียด">
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


                        <!-- สถาณะ -->
                        <div class="w-full flex">
                            <label for="lot_status_name" class="w-[15%] mb-2">สถาณะ:</label>
                            <select name="lot_status_id" id="lot_status_name" required
                                class="w-[85%] border rounded px-2 py-1">
                                <?php foreach ($status_lot as $status): ?>
                                    <option value="<?= htmlspecialchars($status['lot_status_id']) ?>">
                                        <?= htmlspecialchars($status['lot_status_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>


                        <!-- วันที่รับสินค้า -->
                        <div class="w-full flex">
                            <label for="lot_date" class="w-[15%] mb-2">วันที่รับสินค้า:</label>
                            <input type="date" id="lot_date" name="lot_date" value="<?= date('Y-m-d') ?>" readonly
                                class="w-[85%] border rounded px-2 py-1">
                        </div>

                        <!-- ปุ่มบันทึก -->
                        <div class="w-full flex justify-end mt-5">
                            <button type="submit"
                                class="bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg px-4 py-1 rounded shadow-md">
                                บันทึก
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="flex">
        <table class=" w-full  mx-11 mt-11 border-2 border-[#A0A0A0] shadow-lg" id="dataTable">
            <thead>
                <!-- ส่วนหัวของตาราง -->
                <tr class="">

                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        รหัส Lot สินค้า</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        วันที่ล็อต</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        คลังสินค้า</th>

                </tr>
            </thead>
            <tbody class="">
                <?php
                while ($lot_list = $result_lot_list->fetch(PDO::FETCH_ASSOC)): ?>
                    <!-- ใช้ while เพื่อลูปข้อมูลที่ได้จากฐานข้อมูล -->
                    <tr class=" bg-white hover:bg-[#aec5e366] cursor-pointer ">
                        <!-- เมื่อคลิกแถวนี้จะพาไปยังหน้ารายละเอียดของสมาชิก -->
                        <td class="text-start pl-4 border border-gray-500"
                            onclick="window.location.href='?page=lot_listdetail&tabel=lot&id=<?php echo ($lot_list['lot_id']) ?>'">
                            <?php echo($lot_list['lot_id']) ?>
                        </td>
                        <td class="text-start pl-4 border border-gray-500"><?php echo ($lot_list['lot_date']) ?></td>
                        <td class="text-start pl-4 border border-gray-500"
                            onclick="window.location.href='?page=lot_listdetail&tabel=lot&id=<?php echo ($lot_list['lot_id']) ?>'">
                            <?php echo ($lot_list['warehouse_name']) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- เพิ่ม Script สำหรับจัดการฟอร์มและการแก้ไข -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // หาข้อความแจ้งเตือน
            const alert = document.getElementById('success-alert');

            if (alert) {
                // ซ่อนข้อความหลัง 2 วินาที
                setTimeout(function () {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';

                    // ลบ element ออกหลังจาก transition เสร็จสิ้น
                    setTimeout(function () {
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

        function enableEdit(button) {
            const row = button.closest('tr'); // หา row ของปุ่มที่กด
            const managementCell = button.closest('td'); // หาเซลล์ที่อยู่ในคอลัมน์ของปุ่ม

            const visibleInputs = row.querySelectorAll('input[type="text"]'); // ค้นหา input ที่มองเห็นได้
            const hiddenInputs = managementCell.querySelectorAll('input[type="hidden"]'); // ค้นหา input แบบซ่อน

            // เพิ่ม event listener สำหรับการอัปเดตค่า
            visibleInputs.forEach((input, index) => {
                input.addEventListener('input', function () {
                    if (hiddenInputs[index + 1]) { // ตรวจสอบว่ามี hidden input อยู่ก่อนตั้งค่า
                        hiddenInputs[index + 1].value = this.value;
                    }
                });
            });
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
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.edit-button').forEach(button => {
                button.addEventListener('click', function (event) {
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
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('submit', function (event) {
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