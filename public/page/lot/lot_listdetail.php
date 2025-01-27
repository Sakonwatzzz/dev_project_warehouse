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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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
            break;
        case 'lot_product':
            $stmt = $pdo->prepare("
                SELECT 
                    lot_product.*,
                    product.product_name
                FROM lot_product
                INNER JOIN lot ON lot_product.lot_id = lot.lot_id
                INNER JOIN product ON lot_product.product_id = product.product_id
                WHERE lot_product.lot_product_id = ?
            ");
            break;
    }

    $stmt->execute([$id]);
    $lot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot) {
        throw new Exception("No data found");
    }

    // Fetch dropdown data
    $dropdowns = [
        'status_lot' => fetchDropdownData($pdo, "SELECT lot_status_id, lot_status_name FROM lot_status"),
        'warehouse_lot' => fetchDropdownData($pdo, "SELECT warehouse_id, warehouse_name FROM warehouse"),
        'supplier_lot' => fetchDropdownData($pdo, "SELECT supplier_id, supplier_name FROM supplier"),
        'store_lot' => fetchDropdownData($pdo, "SELECT store_id, store_name FROM store")
    ];
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

        <div class="flex space-x-4 mb-6">
            <button id="edit-button" onclick="toggleEditMode()"
                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition">
                แก้ไข
            </button>
            <button id="save-button" type="submit" form="lot-form"
                class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded hidden transition">
                บันทึก
            </button>
        </div>

        <div class="w-[100%] flex space-x-11">
            <form id="lot-form" method="POST" class="w-[60%] bg-white shadow-md rounded px-8 pt-6 pb-8  ">
                <input type="hidden" name="lot_id" value="<?= sanitizeInput($lot['lot_id']) ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 ">
                    <!-- Lot Number -->
                    <div class=" grid grid-cols-5 ">
                        <label class=" text-gray-700 text-lg font-bold ">เลขล็อต</label>
                        <div class="">
                            <span class="view-mode"><?= sanitizeInput($lot['lot_number']) ?></span>
                            <input type="text" name="lot_number"
                                value="<?= sanitizeInput($lot['lot_number']) ?>"
                                class="edit-mode hidden w-full border rounded px-3 ">
                        </div>
                    </div>

                    <!-- Lot Detail -->
                    <div class=" grid grid-cols-5">
                        <label class="text-gray-700 text-lg font-bold ">รายละเอียด</label>
                        <div>
                            <span class="view-mode"><?= sanitizeInput($lot['lot_detail']) ?></span>
                            <input type="text" name="lot_detail"
                                value="<?= sanitizeInput($lot['lot_detail']) ?>"
                                class="edit-mode hidden w-full border rounded px-3 ">
                        </div>
                    </div>

                    <!-- Lot Date -->
                    <div class=" grid grid-cols-5">
                        <label class="text-gray-700 text-lg font-bold ">วันที่ล็อต</label>
                        <div>
                            <span class="view-mode"><?= sanitizeInput($lot['lot_date']) ?></span>
                            <input type="date" name="lot_date"
                                value="<?= sanitizeInput($lot['lot_date']) ?>"
                                class="edit-mode hidden w-full border rounded px-3 ">
                        </div>
                    </div>

                    <!-- Lot Status -->
                    <div class=" grid grid-cols-5">
                        <label class="text-gray-700 text-lg font-bold ">สถานะล็อต</label>
                        <div>
                            <span class="view-mode"><?= sanitizeInput($lot['lot_status_name']) ?></span>
                            <select name="lot_status_id" class="edit-mode hidden w-full border rounded px-3 ">
                                <?php foreach ($dropdowns['status_lot'] as $status): ?>
                                    <option value="<?= $status['lot_status_id'] ?>"
                                        <?= $status['lot_status_id'] == $lot['lot_status_id'] ? 'selected' : '' ?>>
                                        <?= sanitizeInput($status['lot_status_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Warehouse -->
                    <div class=" grid grid-cols-5">
                        <label class="text-gray-700 text-lg font-bold ">คลังสินค้า</label>
                        <div>
                            <span class="view-mode"><?= sanitizeInput($lot['warehouse_name']) ?></span>
                            <select name="warehouse_id" class="edit-mode hidden w-full border rounded px-3 ">
                                <?php foreach ($dropdowns['warehouse_lot'] as $warehouse): ?>
                                    <option value="<?= $warehouse['warehouse_id'] ?>"
                                        <?= $warehouse['warehouse_id'] == $lot['warehouse_id'] ? 'selected' : '' ?>>
                                        <?= sanitizeInput($warehouse['warehouse_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Store -->
                    <div class=" grid grid-cols-5">
                        <label class="text-gray-700 text-lg font-bold ">ร้านค้า</label>
                        <div>
                            <span class="view-mode"><?= sanitizeInput($lot['store_name']) ?></span>
                            <select name="store_id" class="edit-mode hidden w-full border rounded px-3 ">
                                <?php foreach ($dropdowns['store_lot'] as $store): ?>
                                    <option value="<?= $store['store_id'] ?>"
                                        <?= $store['store_id'] == $lot['store_id'] ? 'selected' : '' ?>>
                                        <?= sanitizeInput($store['store_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Supplier -->
                    <div class=" grid grid-cols-5">
                        <label class="text-gray-700 text-lg font-bold ">ผู้ส่งมอบ</label>
                        <div>
                            <span class="view-mode"><?= sanitizeInput($lot['supplier_name']) ?></span>
                            <select name="supplier_id" class="edit-mode hidden w-full border rounded px-3 ">
                                <?php foreach ($dropdowns['supplier_lot'] as $supplier): ?>
                                    <option value="<?= $supplier['supplier_id'] ?>"
                                        <?= $supplier['supplier_id'] == $lot['supplier_id'] ? 'selected' : '' ?>>
                                        <?= sanitizeInput($supplier['supplier_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>

            <div class="bg-white shadow-md rounded px-8 pt-6 pb-8  w-[40%] ">
                <!-- Lot Number -->
                <div class=" grid grid-cols-3 ">
                    <label class=" text-gray-700 text-lg font-bold ">รหัสล็อตสินค้า</label>
                    <div class="">
                        <span class="view-mode"><?= sanitizeInput($lot['lot_number']) ?></span>
                        <input type="text" name="lot_number"
                            value="<?= sanitizeInput($lot['lot_number']) ?>"
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
                    <tr class="hover:bg-blue-50">
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                        <td class="px-4 py-2 border text-center border-gray-400">-</td>
                    </tr>
                </tbody>
            </table>
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