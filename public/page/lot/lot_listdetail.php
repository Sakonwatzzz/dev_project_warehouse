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
$lot_id = isset($_GET['lot_id']) ? (int) $_GET['lot_id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
$lot_product_id = isset($_GET['lot_product_id']) ? (int) $_GET['lot_product_id'] : 0;
// Validation function
function validateProductData($product_id, $capital, $qty, $remain)
{
    if (!is_numeric($product_id) || $product_id <= 0)
        return false;
    if (!is_numeric($capital) || $capital < 0)
        return false;
    if (!is_numeric($qty) || $qty < 0)
        return false;
    if (!is_numeric($remain) || $remain < 0)
        return false;
    return true;
}

if ($lot_id === 0 && $lot_product_id === 0) {
    echo "Invalid lot_id or lot_product_id.";
    exit;
}

$is_update = isset($_GET['is_update']) ? (bool) $_GET['is_update'] : false;

// Validate table type
$allowedTables = ['lot', 'lot_product'];
if (!in_array($tabel, $allowedTables)) {
    die("Invalid table specified");
}


// ค่าที่ได้จากการ execute query
try {
    // กำหนดเงื่อนไขและ SQL query ในรูปแบบ array
    // Query สำหรับดึงข้อมูลของ lot
    $lot_sql = "
SELECT
    lot.*, 
    lot_status.lot_status_id, 
    lot_status.lot_status_name, 
    store.store_id, 
    store.store_name, 
    supplier.supplier_name, 
    supplier.supplier_id, 
    lot.lot_id, 
    lot.lot_number, 
    lot.lot_detail, 
    lot.lot_date, 
    lot.lot_status_id, 
    lot.warehouse_id, 
    lot.supplier_id, 
    lot.store_id, 
    warehouse.warehouse_id, 
    warehouse.warehouse_name
FROM
    lot
    INNER JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
    INNER JOIN store ON lot.store_id = store.store_id
    INNER JOIN supplier ON lot.supplier_id = supplier.supplier_id
    INNER JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
WHERE lot.lot_id = :lot_id
";

    // เตรียมการ statement
    $stmt = $pdo->prepare($lot_sql);
    // Bind :lot_id ให้ตรงกับพารามิเตอร์
    $stmt->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
    // execute คำสั่ง
    $stmt->execute();
    // ดึงข้อมูลผลลัพธ์
    $lot_result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query สำหรับดึงข้อมูลของ lot_product
    $lot_product_sql = "
SELECT
    lot_product.lot_product_id, 
    lot_product.lot_id, 
    lot_product.lot_product_qty, 
    lot_product.lot_product_remain,  
    lot_product.lot_product_capital, 
    lot_product.lot_product_date, 
    lot_product.lot_product_status,  
    lot_product.product_id, 
    product.product_id,
    product.product_name
FROM
    lot_product
INNER JOIN lot ON lot_product.lot_id = lot.lot_id
INNER JOIN product ON lot_product.product_id = product.product_id
WHERE lot_product.lot_product_id = :lot_product_id
";

    // เตรียมการ statement
    $stmt2 = $pdo->prepare($lot_product_sql);
    // Bind :lot_product_id ให้ตรงกับพารามิเตอร์
    $stmt2->bindParam(':lot_product_id', $lot_product_id, PDO::PARAM_INT);
    // execute คำสั่ง
    $stmt2->execute();
    // ดึงข้อมูลผลลัพธ์
    $lot_product_result = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // ตรวจสอบค่า lot_id และ lot_product_id ก่อน
    if (!$lot_id && !$lot_product_id) {
        echo "Invalid lot_id or lot_product_id.";
        exit;
    }

    // Fetch ข้อมูล dropdown
    $dropdowns = [
        'status_lot' => fetchDropdownData($pdo, "SELECT lot_status_id, lot_status_name FROM lot_status"),
        'warehouse_lot' => fetchDropdownData($pdo, "SELECT warehouse_id, warehouse_name FROM warehouse"),
        'supplier_lot' => fetchDropdownData($pdo, "SELECT supplier_id, supplier_name FROM supplier"),
        'store_lot' => fetchDropdownData($pdo, "SELECT store_id, store_name FROM store"),
        'product_lot' => fetchDropdownData($pdo, "SELECT product_id, product_name FROM product")
    ];


} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}


$sql = "SELECT * FROM lot WHERE lot_id = :lot_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
$stmt->execute();



// ดึงข้อมูลสินค้าทั้งหมดจากตาราง product
$query_product = "SELECT product_id, product_name FROM product";
$stmt = $pdo->prepare($query_product);
$stmt->execute();
$product_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ดึงข้อมูลสินค้าของ Lot ตาม warehouse_id ที่กำหนด
$query_lot_product = "SELECT * FROM lot_product WHERE lot_id = :lot_id";
$stmt2 = $pdo->prepare($query_lot_product); // ใช้ตัวแปรใหม่เพื่อป้องกันปัญหาทับค่า
$stmt2->execute(['lot_id' => $lot_id]);
$lot_product_result = $stmt2->fetchAll(PDO::FETCH_ASSOC);


// ตรวจสอบก่อนว่า $lot_id มีค่าหรือไม่
if (!isset($lot_id) || empty($lot_id)) {
    die("Error: lot_id is not set or is empty.");
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

// ใช้ isset() เพื่อตรวจสอบก่อนว่าคีย์มีอยู่ในอาร์เรย์หรือไม่
$lotStatusName = isset($status_lot['lot_status_name']) ? $status_lot['lot_status_name'] : 'N/A';
$warehouseName = isset($warehouse_lot['warehouse_name']) ? $warehouse_lot['warehouse_name'] : 'N/A';
$storeName = isset($store_lot['store_name']) ? $store_lot['store_name'] : 'N/A';
$supplierName = isset($supplier_lot['supplier_name']) ? $supplier_lot['supplier_name'] : 'N/A';



 // ตรวจสอบว่าตัวแปร $lot_result เป็นอาร์เรย์ก่อน
 if (is_array($lot_result) && isset($lot_result['lot_number'])) {
    $lot_number = htmlspecialchars($lot_result['lot_number']);
} else {
    $lot_number = 'ไม่พบข้อมูลล็อต'; // ค่าเริ่มต้นหากไม่มีข้อมูล
}

// ตรวจสอบว่า $lot_product_result เป็น array ก่อนเข้าถึงข้อมูล
if (is_array($lot_product_result) && !empty($lot_product_result)) {
    foreach ($lot_product_result as $lot_product) {
        // ทำการแสดงผลลัพธ์ที่ได้
        // echo ($lot_product['lot_product_qty']);
    }
} else {
    // หากไม่มีข้อมูลหรือเกิดข้อผิดพลาด
    echo "ไม่พบข้อมูลสินค้าสำหรับล็อตนี้";
}



// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ตรวจสอบค่าที่ส่งมาว่ามีข้อมูลหรือไม่
        if (empty($_POST['lot_id']) || !isset($_POST['lot_product_id'])) {
            die("Error: Lot ID or Product ID is missing.");
        }

        $updateData = [
            'lot_number' => $_POST['lot_number'] ?? '',
            'lot_detail' => $_POST['lot_detail'] ?? '',
            'lot_date' => $_POST['lot_date'] ?? null,
            'lot_status_id' => isset($_POST['lot_status_id']) ? (int) $_POST['lot_status_id'] : null,
            'warehouse_id' => isset($_POST['warehouse_id']) ? (int) $_POST['warehouse_id'] : null,
            'store_id' => isset($_POST['store_id']) ? (int) $_POST['store_id'] : null,
            'supplier_id' => isset($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : null,
            'lot_id' => isset($_POST['lot_id']) ? (int) $_POST['lot_id'] : null,
        ];

        // อัพเดตข้อมูลในตาราง lot
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

        // อัพเดทข้อมูลในตาราง lot_product
        if (isset($_POST['lot_product_id']) && is_array($_POST['lot_product_id'])) {
            foreach ($_POST['lot_product_id'] as $key => $lot_product_id) {
                // รับค่าจากฟอร์ม
                $new_product_id = isset($_POST['product_id'][$key]) ? (int) $_POST['product_id'][$key] : null;
                $lot_product_capital = isset($_POST['lot_product_capital'][$key]) ? (float) $_POST['lot_product_capital'][$key] : null;
                $lot_product_qty = isset($_POST['lot_product_qty'][$key]) ? (int) $_POST['lot_product_qty'][$key] : null;
                $lot_product_remain = isset($_POST['lot_product_remain'][$key]) ? (int) $_POST['lot_product_remain'][$key] : null;

                // ตรวจสอบค่าก่อนอัพเดต
                if ($new_product_id === null || $lot_product_id === null) {
                    error_log("Error: product_id or lot_product_id is missing.");
                    continue;
                }

                error_log("Updating lot_product_id: $lot_product_id with product_id: $new_product_id");

                // SQL UPDATE
                $productStmt = $pdo->prepare("
                    UPDATE lot_product 
                    SET 
                        product_id = ?,
                        lot_product_capital = ?, 
                        lot_product_qty = ?, 
                        lot_product_remain = ? 
                    WHERE lot_product_id = ? AND lot_id = ?
                ");
                $productStmt->execute([
                    $new_product_id,
                    $lot_product_capital,
                    $lot_product_qty,
                    $lot_product_remain,
                    $lot_product_id,
                    $updateData['lot_id']
                ]);
            }
        }

        // Redirect หลังจากอัพเดทเสร็จสิ้น
        header("Location: /?page=lot_listdetail&tabel=lot&id={$updateData['lot_id']}");
        exit;
    } catch (PDOException $e) {
        error_log("Update Error: " . $e->getMessage());
        die("Error updating record: " . $e->getMessage());
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
        <!-- Form หลักที่รวมข้อมูลทั้ง lot และ lot_product -->
        <form id="lot-form" method="POST">
            <!-- Hidden input สำหรับ lot_id -->
            <input type="hidden" name="lot_id"
                value="<?= isset($lot_result['lot_id']) ? $lot_result['lot_id'] : ''; ?>">

            <!-- Lot Details Section -->
            <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Lot Number -->
<div class="grid grid-cols-2">
    <label class="text-gray-700 text-lg font-bold">
        รหัส Lot สินค้า
    </label>
    <div>
        <span class="view-mode"><?= $lot_number; ?></span>
        <input type="text" name="lot_number"
            value="<?= $lot_number; ?>"
            class="edit-mode hidden w-full border rounded px-3">
    </div>
</div>
                    <!-- Lot Detail -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">รายละเอียด</label>
                        <div>
                            <span class="view-mode"><?= htmlspecialchars($lot_result['lot_detail'] ?? ''); ?></span>
                            <input type="text" name="lot_detail"
                                value="<?= htmlspecialchars($lot_result['lot_detail'] ?? ''); ?>"
                                class="edit-mode hidden w-full border rounded px-3">
                        </div>
                    </div>
                    <!-- Lot Date -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">วันที่ล็อต</label>
                        <div>
                            <span class="view-mode"><?= htmlspecialchars(date('Y-m-d')); ?></span>
                            <input type="date" name="lot_date" value="<?= date('Y-m-d'); ?>"
                                class="edit-mode hidden w-full border rounded px-3" readonly>
                        </div>
                    </div>
                    <!-- Lot Status -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">สถานะล็อต</label>
                        <div>
                            <span
                                class="view-mode"><?= htmlspecialchars($lot_result['lot_status_name'] ?? 'ยังไม่ได้กรอกข้อมูล'); ?></span>
                            <select name="lot_status_id" class="edit-mode hidden w-full border rounded px-3">
                                <?php foreach ($dropdowns['status_lot'] as $status): ?>
                                    <option value="<?= $status['lot_status_id'] ?>"
                                        <?= ($status['lot_status_id'] == $lot_result['lot_status_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status['lot_status_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Warehouse -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">คลังสินค้า</label>
                        <div>
                            <span
                                class="view-mode"><?= htmlspecialchars($lot_result['warehouse_name'] ?? 'ยังไม่ได้กรอกข้อมูล'); ?></span>
                            <select name="warehouse_id" class="edit-mode hidden w-full border rounded px-3">
                                <?php foreach ($dropdowns['warehouse_lot'] as $warehouse): ?>
                                    <option value="<?= $warehouse['warehouse_id'] ?>"
                                        <?= ($warehouse['warehouse_id'] == $lot_result['warehouse_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($warehouse['warehouse_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Store -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">ร้านค้า</label>
                        <div>
                            <span
                                class="view-mode"><?= htmlspecialchars($lot_result['store_name'] ?? 'ยังไม่ได้กรอกข้อมูล'); ?></span>
                            <select name="store_id" class="edit-mode hidden w-full border rounded px-3">
                                <?php foreach ($dropdowns['store_lot'] as $store): ?>
                                    <option value="<?= $store['store_id'] ?>"
                                        <?= ($store['store_id'] == $lot_result['store_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($store['store_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Supplier -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">ผู้ส่งมอบ</label>
                        <div>
                            <span
                                class="view-mode"><?= htmlspecialchars($lot_result['supplier_name'] ?? 'ยังไม่ได้กรอกข้อมูล'); ?></span>
                            <select name="supplier_id" class="edit-mode hidden w-full border rounded px-3">
                                <?php foreach ($dropdowns['supplier_lot'] as $supplier): ?>
                                    <option value="<?= $supplier['supplier_id'] ?>"
                                        <?= ($supplier['supplier_id'] == $lot_result['supplier_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lot Product Details Section -->
            <!-- ส่วนนี้อยู่ภายใน <form id="lot-form" method="POST"> -->
            <!-- Lot Product Details Section (ตาราง) -->
            <div class="mt-4">
                <table class="w-full border-2 border-gray-300 shadow-lg">
                    <thead>
                        <tr class="bg-blue-100">
                            <th class="px-4 py-2 border border-gray-500">สินค้านำเข้า</th>
                            <th class="px-4 py-2 border border-gray-500">ราคาต้นทุน</th>
                            <th class="px-4 py-2 border border-gray-500">จำนวนสินค้านำเข้า</th>
                            <!-- <th class="px-4 py-2 border border-gray-500">จำนวนคงเหลือ</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($lot_product_result)): ?>
                            <?php foreach ($lot_product_result as $index => $product_result): ?>
                                <tr>
                                    <!-- รหัสสินค้า -->
                                    <td class="px-4 py-2 border text-center">
                                        <span class="view-mode">
                                            <?= isset($product_result['product_name']) ? htmlspecialchars($product_result['product_name']) : 'ยังไม่ได้กรอกข้อมูล'; ?>
                                        </span>
                                        <!-- ส่งค่า lot_product_id -->
                                        <input type="hidden" name="lot_product_id[]"
                                            value="<?= htmlspecialchars($product_result['lot_product_id']); ?>">

                                        <!-- Dropdown เลือกสินค้า -->
                                        <select name="product_id[]" class="edit-mode hidden w-full border rounded px-3">
                                            <?php foreach ($dropdowns['product_lot'] as $product): ?>
                                                <option value="<?= $product['product_id'] ?>"
                                                    <?= ($product['product_id'] == $product_result['product_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($product['product_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <!-- ราคาทุน -->
                                    <td class="px-4 py-2 border text-center">
                                        <span
                                            class="view-mode"><?= htmlspecialchars($product_result['lot_product_capital']); ?></span>
                                        <input type="number" name="lot_product_capital[]"
                                            value="<?= htmlspecialchars($product_result['lot_product_capital']); ?>"
                                            class="edit-mode hidden w-full border rounded px-3">
                                    </td>

                                    <!-- จำนวนเข้า -->
                                    <td class="px-4 py-2 border text-center">
                                        <span
                                            class="view-mode"><?= htmlspecialchars($product_result['lot_product_qty']); ?></span>
                                        <input type="number" name="lot_product_qty[]"
                                            value="<?= htmlspecialchars($product_result['lot_product_qty']); ?>"
                                            class="edit-mode hidden w-full border rounded px-3">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-center">ไม่มีข้อมูลสินค้า</td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>
            <div class=" ">
                <div class="flex flex-col md:flex-row gap-8 mt-8 ">
                    <div class="flex flex-col gap-2 bg-gray-100 p-6 rounded-lg shadow-md w-full md:w-1/2">
                        <label class="font-bold text-3xl text-gray-700">จำนวนสินค้าที่รับเข้า</label>
                        <input type="number" name="lot_product_remain[]"
                            value="<? echo ($product_result['lot_product_remain']); ?>"
                            class="p-4 h-[70px] border border-gray-400 text-4xl flex justify-center items-center rounded-lg bg-white shadow"
                            readonly>
                        </input>
                    </div>
                    <div class="flex flex-col gap-2 bg-gray-100 p-6 rounded-lg shadow-md w-full md:w-1/2 ">
                        <label class="font-bold text-3xl text-gray-700">ยอดรวม</label>
                        <div
                            class="p-4 h-[70px] border border-gray-400 text-4xl flex justify-center items-center rounded-lg bg-white shadow">

                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="w-full flex justify-center">
                    <button id="save-button" type="submit" form="lot-form"
                        class="w-full px-6 py-3 bg-blue-600 text-white text-xl font-semibold rounded-lg shadow-md hover:bg-blue-700 transition-all">
                        ยืนยัน
                    </button>
                </div>
            </div>
        </form>
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
            editButton.textContent = editButton.textContent === 'ยกเลิก' ? 'แก้ไข' : 'ยกเลิก';
        }
    </script>
</body>

</html>