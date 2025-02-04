<?php
require_once __DIR__ . '../../../../config/database_server.php';
session_start();

// Input Validation and Sanitization Function
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Function to fetch dropdown data
function fetchDropdownData($pdo, $query)
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return [];
    }
}
// Validate and sanitize GET parameters
$tabel = isset($_GET['tabel']) ? sanitizeInput($_GET['tabel']) : '';
if (isset($_GET['lot_id'])) {
    if (isset($_GET['lot_id']) && is_numeric($_GET['lot_id'])) {
        $lot_id = $_GET['lot_id'];
    } else {
        // ทำการแจ้งข้อผิดพลาดหรือทำการรีไดเรกต์
        echo "Invalid lot_id.";
        exit;
    }

    if (isset($_GET['lot_product_id']) && is_numeric($_GET['lot_product_id'])) {
        $lot_product_id = $_GET['lot_product_id'];
    } else {
        // ทำการแจ้งข้อผิดพลาดหรือทำการรีไดเรกต์
        echo "Invalid lot_product_id.";
        exit;
    }
}
$is_update = isset($_GET['is_update']) ? (bool)$_GET['is_update'] : false;

// Validate table type
$allowedTables = ['lot', 'lot_product'];
if (!in_array($tabel, $allowedTables)) {
    die("Invalid table specified");
}
try {
    // Prepare main query based on table type
    switch ($tabel) {
        case 'lot':
            $stmt = $pdo->prepare(" 
                SELECT 
                    lot.*,
                    lot_status.lot_status_name, 
                    supplier.supplier_name, 
                    warehouse.warehouse_name, 
                    store.store_name
                FROM lot
                LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
                LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
                LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
                LEFT JOIN store ON lot.store_id = store.store_id
                WHERE lot.lot_id = ?
            ");
            $stmt->execute([$lot_id]);
            $lot_list = $stmt->fetch(PDO::FETCH_ASSOC);
            break;

        case 'lot_product':
            if (isset($_GET['lot_product_id'])) {
                $lot_product_id = $_GET['lot_product_id'];
            } else {
                echo "Lot Product ID is missing.";
                exit;
            }

            $stmt = $pdo->prepare(" 
                SELECT 
                    lot_product.*,
                    product.product_name
                FROM lot_product
                INNER JOIN lot ON lot_product.lot_id = lot.lot_id
                INNER JOIN product ON lot_product.product_id = product.product_id
                WHERE lot_product.lot_product_id = ?
            ");
            $stmt->execute([$lot_product_id]);
            $lot_product = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
    }

    // Handle error if no data found
    if (!$lot_list || ($tabel == 'lot_product' && !$lot_product)) {
        echo "No data found.";
        exit;
    }

    // Fetch dropdown data
    $dropdowns = [
        'status_lot' => fetchDropdownData($pdo, "SELECT lot_status_id, lot_status_name FROM lot_status"),
        'warehouse_lot' => fetchDropdownData($pdo, "SELECT warehouse_id, warehouse_name FROM warehouse"),
        'supplier_lot' => fetchDropdownData($pdo, "SELECT supplier_id, supplier_name FROM supplier"),
        'store_lot' => fetchDropdownData($pdo, "SELECT store_id, store_name FROM store")
    ];
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit;
} catch (Exception $e) {
    error_log($e->getMessage());
    die("Error retrieving data: " . $e->getMessage());
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updateData = [
            'lot_number' => $_POST['lot_number'] ?? '',
            'lot_detail' => $_POST['lot_detail'] ?? '',
            'lot_date' => $_POST['lot_date'] ?? '',
            'lot_status_id' => $_POST['lot_status_id'] ?? null,
            'warehouse_id' => $_POST['warehouse_id'] ?? null,
            'store_id' => $_POST['store_id'] ?? null,
            'supplier_id' => $_POST['supplier_id'] ?? null,
            'lot_id' => $_POST['lot_id'] ?? null
        ];

        $stmt = $pdo->prepare("
            UPDATE lot 
            SET 
                lot_number = ?, 
                lot_detail = ?, 
                lot_date = ?, 
                lot_status_id = ?, 
                warehouse_id = ?, 
                store_id = ?, 
                supplier_id = ?
            WHERE lot_id = ?
        ");

        $stmt->execute([
            $updateData['lot_number'],
            $updateData['lot_detail'],
            $updateData['lot_date'],
            $updateData['lot_status_id'],
            $updateData['warehouse_id'],
            $updateData['store_id'],
            $updateData['supplier_id'],
            $updateData['lot_id']
        ]);

        header("Location: /?page=lot_listdetail&tabel=lot&id={$updateData['lot_id']}");
        exit;
    } catch (PDOException $e) {
        error_log("Update Error: " . $e->getMessage());
        die("Error updating record");
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการรับสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <h1 class="text-4xl font-bold text-center mb-6">รายการรับสินค้า</h1>

        <div class="flex gap-4 h-[70px]">
            <button class="h-11 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition">
                <a href="/?page=lot_listinsert">
                    ย้อนกลับ</a>
            </button>

            <div class="flex space-x-4  mb-6">
                <button id="edit-button" onclick="toggleEditMode()"
                    class="h-11 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition">
                    แก้ไข
                </button>
                <button id="save-button" type="submit" form="lot-form"
                    class="h-11 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded hidden transition">
                    บันทึก
                </button>
            </div>

        </div>

        <div class="w-[100%] flex space-x-11">
            <form id="lot-form" method="POST" class="w-[60%] bg-white shadow-md rounded px-8 pt-6 pb-8  ">
                <input type="hidden" name="lot_id" value="<?php echo ($lot['lot_id']) ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 ">
                    <!-- Lot Number -->
                    <div class=" grid grid-cols-2 ">
                        <label class=" text-gray-700 text-lg font-bold ">รหัส Lot สินค้า</label>
                        <div class="">
                            <span class="view-mode"><?php echo ($lot['lot_number']) ?></span>
                            <input type="text" name="lot_number"
                                value="<?php echo ($lot['lot_number']) ?>"
                                class="edit-mode hidden w-full border rounded px-3 ">
                        </div>
                    </div>

                    <!-- Lot Detail -->
                    <div class=" grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold ">รายละเอียด</label>
                        <div>
                            <span class="view-mode"><?php echo ($lot['lot_detail']) ?></span>
                            <input type="text" name="lot_detail"
                                value="<?php echo ($lot['lot_detail']) ?>"
                                class="edit-mode hidden w-full border rounded px-3 ">
                        </div>
                    </div>

                    <!-- Lot Date -->
                    <div class=" grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold ">วันที่ล็อต</label>
                        <div>
                            <span class="view-mode"><?php echo ($lot['lot_date']) ?></span>
                            <input type="date" name="lot_date"
                                value="<?php echo ($lot['lot_date']) ?>"
                                class="edit-mode hidden w-full border rounded px-3 ">
                        </div>
                    </div>

                    <!-- Lot Status -->
                    <div class=" grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold ">สถานะล็อต</label>
                        <div>
                            <span class="view-mode"><?php echo ($lot['lot_status_name']) ?></span>
                            <select name="lot_status_id" class="edit-mode hidden w-full border rounded px-3 ">
                                <?php foreach ($dropdowns['status_lot'] as $status): ?>
                                    <option value="<?= $status['lot_status_id'] ?>"
                                        <?= $status['lot_status_id'] == $lot['lot_status_id'] ? 'selected' : '' ?>>
                                        <?php echo ($status['lot_status_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Warehouse -->
                    <div class=" grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold ">คลังสินค้า</label>
                        <div>
                            <span class="view-mode"><?php echo ($lot['warehouse_name']) ?></span>
                            <select name="warehouse_id" class="edit-mode hidden w-full border rounded px-3 ">
                                <?php foreach ($dropdowns['warehouse_lot'] as $warehouse): ?>
                                    <option value="<?= $warehouse['warehouse_id'] ?>"
                                        <?= $warehouse['warehouse_id'] == $lot['warehouse_id'] ? 'selected' : '' ?>>
                                        <?php echo ($warehouse['warehouse_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Store -->
                    <div class=" grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold ">ร้านค้า</label>
                        <div>
                            <span class="view-mode"><?php echo ($lot['store_name']) ?></span>
                            <select name="store_id" class="edit-mode hidden w-full border rounded px-3 ">
                                <?php foreach ($dropdowns['store_lot'] as $store): ?>
                                    <option value="<?= $store['store_id'] ?>"
                                        <?= $store['store_id'] == $lot['store_id'] ? 'selected' : '' ?>>
                                        <?php echo ($store['store_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Supplier -->
                    <div class=" grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold ">ผู้ส่งมอบ</label>
                        <div>
                            <span class="view-mode"><?php echo ($lot['supplier_name']) ?></span>
                            <select name="supplier_id" class="edit-mode hidden w-full border rounded px-3 ">
                                <?php foreach ($dropdowns['supplier_lot'] as $supplier): ?>
                                    <option value="<?= $supplier['supplier_id'] ?>"
                                        <?= $supplier['supplier_id'] == $lot['supplier_id'] ? 'selected' : '' ?>>
                                        <?php echo ($supplier['supplier_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>

            <div class="bg-white shadow-md rounded px-8 pt-6 pb-8  w-[40%] ">
                <div class=" grid grid-cols-2 ">
                    <label class=" text-gray-700 text-lg font-bold ">รหัสรายการล็อตสินค้า</label>
                    <div class="">
                        <span class="view-mode"><?php echo ($lot_product['lot_product_id']) ?></span>
                        <input type="text" name="lot_product_id"
                            value="<?php echo ($lot_product['lot_product_id']) ?>"
                            class="edit-mode hidden w-full border rounded px-3 ">
                    </div>
                </div>

            </div>
        </div>

        <!-- Product Table -->
        <div class="mt-4">
            <table class="w-full border-2 border-gray-300 shadow-lg">
                <thead>
                    <tr class="bg-blue-100">
                        <th class="px-4 py-2 border border-gray-500">รหัสสินค้า</th>
                        <th class="px-4 py-2 border border-gray-500">รายละเอียด</th>
                        <th class="px-4 py-2 border border-gray-500">ราคาทุน</th>
                        <th class="px-4 py-2 border border-gray-500">จำนวนเข้า</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="hover:bg-blue-50 bg-[#fff]">
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                    </tr>
                    <tr class="hover:bg-blue-50 bg-[#fff]">
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                    </tr>
                    <tr class="hover:bg-blue-50 bg-[#fff]">
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                    </tr>
                    <tr class="hover:bg-blue-50 bg-[#fff]">
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                    </tr>
                </tbody>
            </table>
        </div>


        <div class=" ">
            <div class="flex flex-col md:flex-row gap-8 mt-8 ">
                <div class="flex flex-col gap-2 bg-gray-100 p-6 rounded-lg shadow-md w-full md:w-1/2">
                    <label class="font-bold text-3xl text-gray-700">จำนวนสินค้า</label>
                    <div class="p-4 h-[70px] border border-gray-400 text-4xl flex justify-center items-center rounded-lg bg-white shadow">
                        14 ชิ้น
                    </div>
                </div>
                <div class="flex flex-col gap-2 bg-gray-100 p-6 rounded-lg shadow-md w-full md:w-1/2 ">
                    <label class="font-bold text-3xl text-gray-700">ยอดรวม</label>
                    <div class="p-4 h-[70px] border border-gray-400 text-4xl flex justify-center items-center rounded-lg bg-white shadow">
                        980000000000000 บาท
                    </div>
                </div>
            </div>

            <div class="w-full mt-6 flex justify-center">
                <button class="w-full px-6 py-3 bg-blue-600 text-white text-xl font-semibold rounded-lg shadow-md hover:bg-blue-700 transition-all">
                    ยืนยัน
                </button>
            </div>
        </div>

    </div>

    <script>
        function toggleEditMode() {
            const viewElements = document.querySelectorAll('.view-mode');
            const editElements = document.querySelectorAll('.edit-mode');
            const editButton = document.getElementById('edit-button');
            const saveButton = document.getElementById('save-button');

            viewElements.forEach(el => el.classList.toggle('hidden'));
            editElements.forEach(el => el.classList.toggle('hidden'));
            saveButton.classList.toggle('hidden');

            editButton.textContent = editButton.textContent === 'แก้ไข' ? 'ยกเลิก' : 'แก้ไข';
        }
    </script>
</body>

</html>