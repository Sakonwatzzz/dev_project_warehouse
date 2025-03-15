<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();
try {
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
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result_lot_list = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// จัดการการส่งข้อมูลแบบ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_id = $_POST['lot_id'] ?? '';  // รับค่าจาก POST

    // กรณีเพิ่มคลังสินค้าใหม่
    if (isset($_POST[''])) {
    }

    // กรณีเพิ่มข้อมูล lot
   // กรณีเพิ่มข้อมูล lot
else {
    // รับค่า lot_id จาก POST (แก้ไข)
    $lot_id = $_POST['lot_id'] ?? '';
    $lot_name = $_POST['lot_name'] ?? '';
    $lot_detail = $_POST['lot_detail'] ?? '';
    $lot_date = $_POST['lot_date'] ?? '';
    $lot_status_id = $_POST['lot_status_id'] ?? '';
    $warehouse_id = $_POST['warehouse_id'] ?? '';
    $warehouse_from_id = $_POST['warehouse_from_id'] ?? '';
    
    // กำหนดค่า warehouse_inout เป็น 1 หากไม่มีการกรอกค่า
    $warehouse_inout = $_POST['warehouse_inout'] ?? '1'; // ถ้าไม่มีค่าให้เป็น 1
    
    $supplier_id = $_POST['supplier_id'] ?? '';
    $lot_product_qty = $_POST['lot_product_qty'] ?? '';
    $lot_product_remain = $_POST['lot_product_remain'] ?? '';
    $lot_product_capital = $_POST['lot_product_capital'] ?? '';
    $product_id = $_POST['product_id'] ?? '';

    // ตรวจสอบว่าค่าต่างๆ จากฟอร์มถูกส่งมาเรียบร้อยหรือไม่
    if (empty($lot_name) ) {
        $error = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    } else {
        try {
            $pdo->beginTransaction();

            // ใช้ lot_id จาก POST ในการ INSERT
            $stmt = $pdo->prepare('INSERT INTO lot (lot_id, lot_name, lot_detail, lot_date, lot_status_id, warehouse_id, supplier_id, warehouse_from_id, warehouse_inout) 
            VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$lot_id, $lot_name, $lot_detail, $lot_date, $lot_status_id, $warehouse_id, $supplier_id, $warehouse_from_id, $warehouse_inout]);

            $pdo->commit();

            // ใช้ lot_id จาก POST ในการส่งไปกับ URL
            หา last_id_insert
            $lot_id = ????;
            header("Location: /?page=lot_listdetail&tabel=lot&id=" . $lot_id . "&success=1");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            echo $error;
        }
    }
}

}
// ดึงข้อมูล lot_id สำหรับ dropdown
$query = "SELECT lot_id FROM lot";
$stmt = $pdo->prepare($query);
$stmt->execute();
$lot_id_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// ดึงข้อมูลรายการรับสินค้า
$query = "SELECT lot_product_id, lot_product_qty, lot_product_remain ,lot_product_capital FROM lot_product";
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
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>
    <h1 class="mx-11 my-8 text-4xl font-bold">รอบของสินค้า</h1>
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mx-11 mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <div class="flex">
        <form method="POST" action="" class="w-full mx-11">
            <div
                class="w-2/2 flex flex-wrap p-3 bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg border border-[#8f959b] shadow rounded-lg gap-24">
                <?php
                date_default_timezone_set('Asia/Bangkok'); // ตั้งโซนเวลาเป็นไทย
                $currentDateTime = date("Y-m-d H:i:s"); // วันที่และเวลาปัจจุบันในรูปแบบฐานข้อมูล
                $displayDateTime = date("d/m/Y H:i:s"); // แสดงวันที่ในรูปแบบที่อ่านง่าย
                ?>

                <!-- แถวแสดงวันที่ด้านขวาบน -->
                <span class="absolute pt-56 top-20 right-12 text-gray-900 text-xl">
                    <?= $displayDateTime ?>
                </span>
                <!-- ฟอร์มรับจากภายนอก -->
                <div class="w-[45%] flex flex-wrap space-y-3">
                    <h1>รับจากภายนอก (suplier)</h1>
                    <div class="w-full flex pt-10">
    <label for="lot_id" class="w-[15%] mb-2" hidden>รหัส Lot สินค้า:</label>
    <input type="hidden" name="lot_id" id="lot_id" class="w-[85%] border rounded px-2 py-1"
           value="<?php echo isset($lot_id) ? $lot_id : ''; ?>" placeholder="กรอกเลขที่ lot">
</div>

                    <div class="w-full flex pt-10">
                        <label for="lot_name" class="w-[15%] mb-2">ชื่อ Lot สินค้า:</label>
                        <input type="text" name="lot_name" id="lot_name" class="w-[85%] border rounded px-2 py-1"
                            placeholder="กรอกชื่อหรือรหัส lot รายการสินค้า" required>
                    </div>
                    <div class="w-full flex pt-10">
                        <label for="lot_detail" class="w-[15%] mb-1">รายละเอียดการรับ:</label>
                        <input type="text" name="lot_detail" id="lot_detail" class="w-[85%] border rounded px-2 py-1"
                            placeholder="กรอกรายะละเอียดการรับ">
                    </div>
                    <!-- คลังสินค้า -->
                    <div class="w-full flex">
                        <label for="warehouse_id" class="w-[15%] mb-2">ลงคลัง:</label>
                        <select name="warehouse_id" id="warehouse_id" class="w-[85%] border rounded px-2 py-1">
                            <?php foreach ($warehouse_lot as $warehouse): ?>
                                <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                    <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- ฟอร์มรับมาจากคลัง (ย้ายคลัง) -->
                <div class="w-[45%] flex flex-wrap space-y-3">
                    <h1>รับมาจากคลัง (ย้ายคลัง/กิจการตัวเอง)</h1>
                    <!-- ผู้ส่งมอบ -->
                    <div class="w-full flex flex pt-6">
                        <label for="supplier_id" class="w-[20%] ">รับมาจากผู้ส่งมอบ:</label>
                        <select name="supplier_id" id="supplier_id" class="w-[85%] border rounded px-2 py-1">
                            <?php foreach ($supplier_lot as $supplier): ?>
                                <option value="<?= htmlspecialchars($supplier['supplier_id']) ?>">
                                    <?= htmlspecialchars($supplier['supplier_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- นำมาจากคลัง -->
                    <div class="w-full flex">
                        <label for="warehouse_from_id" class="w-[15%] mb-2 mr-11">นำมาจากคลัง:</label>
                        <select name="warehouse_from_id" id="warehouse_from_id"
                            class="w-[85%] border rounded px-2 py-1">
                            <?php foreach ($warehouse_lot as $warehouse): ?>
                                <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                    <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full flex">
    <label for="lot_status_name" class="w-[15%] mr-10 mb-2">สถานะ:</label>
    <select name="lot_status_id" id="lot_status_name" class="w-[85%] border rounded px-2 py-1" style="pointer-events: none;">
        <?php foreach ($status_lot as $status): ?>
            <option value="<?= htmlspecialchars($status['lot_status_id']) ?>" 
                <?php if ($status['lot_status_id'] == $selected_status_id) echo 'selected'; ?>>
                <?= htmlspecialchars($status['lot_status_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="w-full flex pt-10">
    <label for="warehouse_inout" class="w-[15%] mb-1">1:in | 2:out</label>
    <input type="text" name="warehouse_inout" id="warehouse_inout"
        class="w-[85%] border rounded px-2 py-1" placeholder="กรอกชื่อหรือรหัส lot รายการสินค้า">
</div>

                    <!-- วันที่รับสินค้า -->
                    <div class="w-full flex">
                        <label for="lot_date" class="w-[15%] mb-2"></label>
                        <input type="hidden" name="lot_date" value="<?= $currentDateTime ?>">

                    </div>
                </div>
                <!-- ปุ่มบันทึก อยู่ตรงกลาง -->
                <div class="w-full flex justify-center">
                    <button type="submit"
                        class="w-[5%] h-[50px] bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg px-4 py-1 rounded shadow-md">
                        บันทึก
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="flex">
        <table class=" w-full  mx-11 mt-11 border-2 border-[#A0A0A0] shadow-lg" id="dataTable">
            <thead>
                <!-- ส่วนหัวของตาราง -->
                <tr class="">

                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        ชื่อ Lot สินค้า</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        วันที่และเวลาที่ล็อต</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        นำมาจากคลัง</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        ลงคลัง</th>

                </tr>
            </thead>
            <tbody class="">
                <?php
                while ($lot_list = $result_lot_list->fetch(PDO::FETCH_ASSOC)): ?>
                    <!-- ใช้ while เพื่อลูปข้อมูลที่ได้จากฐานข้อมูล -->
                    <tr class=" bg-white hover:bg-[#aec5e366] cursor-pointer "
                        onclick="window.location.href='?page=lot_listdetail&tabel=lot&id=<?php echo ($lot_list['lot_id']) ?>'">
                        <!-- เมื่อคลิกแถวนี้จะพาไปยังหน้ารายละเอียดของสมาชิก -->
                        <td class="text-start pl-4 border border-gray-500"
                            onclick="window.location.href='?page=lot_listdetail&tabel=lot&id=<?php echo ($lot_list['lot_id']) ?>'">
                            <?php echo ($lot_list['lot_name']) ?>
                        </td>
                        <td class="text-start pl-4 border border-gray-500"><?php echo ($lot_list['lot_date']) ?></td>
                        <td class="text-start pl-4 border border-gray-500">
                            <?php echo !empty($lot_list['warehouse_from_id']) ? htmlspecialchars($lot_list['warehouse_from_id']) : "ยังไม่ระบุ"; ?>
                        </td>
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
        // document.addEventListener('DOMContentLoaded', function () {
        //     document.addEventListener('submit', function (event) {
        //         if (event.target.querySelector('[name="update_warehouse"]')) {
        //             const row = event.target.closest('tr');
        //             const inputs = row.querySelectorAll('input[type="text"]');
        //             let isValid = true;

        //             inputs.forEach(input => {
        //                 if (!input.value.trim()) {
        //                     isValid = false;
        //                     input.classList.add('border-red-500');
        //                 } else {
        //                     input.classList.remove('border-red-500');
        //                 }
        //             });

        //             if (!isValid) {
        //                 event.preventDefault();
        //                 alert('กรุณากรอกข้อมูลให้ครบทุกช่อง');
        //             }
        //         }
        //     });
        // });
    </script>

</body>

</html>