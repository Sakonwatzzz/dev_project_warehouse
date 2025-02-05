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

// echo "<pre>";
// print_r($_GET);
// echo "</pre>";

if ($lot_id === 0 && $lot_product_id === 0) {
    echo "Invalid lot_id or lot_product_id.";
    exit;
}

$is_update = isset($_GET['is_update']) ? (bool)$_GET['is_update'] : false;

// Validate table type
$allowedTables = ['lot', 'lot_product'];
if (!in_array($tabel, $allowedTables)) {
    die("Invalid table specified");
}
// Assuming $conn is the database connection
if ($lot_id) {
    // Get lot data for the specific lot_id
    $lot_sql = "SELECT * FROM lot WHERE lot_id = :lot_id";
    $stmt = $pdo->prepare($lot_sql);
    $stmt->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
    $stmt->execute();
    $lot_result = $stmt->fetch(PDO::FETCH_ASSOC);


    // Get lot product data related to the specific lot_id
    $lot_product_sql = "SELECT * FROM lot_product WHERE lot_id = :lot_id";
    $stmt = $pdo->prepare($lot_product_sql);
    $stmt->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
    $stmt->execute();
    $lot_product_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$querylot = "SELECT lot_number, lot_date, lot_detail FROM lot";
$stmt = $pdo->prepare($querylot);
$stmt->execute();
$lot_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// คำสั่ง SQL เพื่อดึงข้อมูลสินค้าของ lot
$queryproduct = "SELECT * FROM lot_product WHERE lot_id = :lot_id";
$stmt = $pdo->prepare($queryproduct);
$stmt->execute(['lot_id' => $lot_id]);
$lot_product_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ค่าที่ได้จากการ execute query
try {
    // กำหนดเงื่อนไขและ SQL query ในรูปแบบ array
    $queries = [
        'lot' => [
            'query' => "SELECT * FROM lot WHERE lot_id = :lot_id",
            'param' => $lot_id,
            'result_var' => 'lot_result'
        ],
        'lot_product' => [
            'query' => "
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
                WHERE lot_product.lot_product_id = ?",
            'param' => $lot_product_id,
            'result_var' => 'lot_product_result'
        ]
    ];

    // ตรวจสอบว่า table ที่รับมาอยู่ใน queries หรือไม่
    if (isset($queries['lot']) && isset($queries['lot']['param']) && $queries['lot']['param'] > 0) {
        $stmt = $pdo->prepare($queries['lot']['query']);
        $stmt->bindParam(':lot_id', $queries['lot']['param'], PDO::PARAM_INT);
        $stmt->execute();
        $queries['lot']['result_var'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch dropdown data
    $dropdowns = [
        'status_lot' => fetchDropdownData($pdo, "SELECT lot_status_id, lot_status_name FROM lot_status"),
        'warehouse_lot' => fetchDropdownData($pdo, "SELECT warehouse_id, warehouse_name FROM warehouse"),
        'supplier_lot' => fetchDropdownData($pdo, "SELECT supplier_id, supplier_name FROM supplier"),
        'store_lot' => fetchDropdownData($pdo, "SELECT store_id, store_name FROM store")
    ];

    // Fetch lot_product if applicable
    if ($tabel === 'lot_product' && isset($lot_product_id) && $lot_product_id > 0) {
        $stmt = $pdo->prepare($queries['lot_product']['query']);
        $stmt->execute([$lot_product_id]);
        $lot_product_result = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
} catch (Exception $e) {
    error_log($e->getMessage());
    die("Error retrieving data: " . $e->getMessage());
}



// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updateData = [
            'lot_number' => isset($_POST['lot_number']) ? trim($_POST['lot_number']) : '',
            'lot_detail' => isset($_POST['lot_detail']) ? trim($_POST['lot_detail']) : '',
            'lot_date' => isset($_POST['lot_date']) ? $_POST['lot_date'] : null,
            'lot_status_id' => isset($_POST['lot_status_id']) ? (int)$_POST['lot_status_id'] : null,
            'warehouse_id' => isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : null,
            'store_id' => isset($_POST['store_id']) ? (int)$_POST['store_id'] : null,
            'supplier_id' => isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null,
            'lot_id' => isset($_POST['lot_id']) ? (int)$_POST['lot_id'] : null
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
            <form id="lot-form" method="POST" class="w-[60%] bg-white shadow-md rounded px-8 pt-6 pb-8">
                <input type="hidden" name="lot_id" value="<?php echo is_array($lot_result) && isset($lot_result['lot_id']) ? $lot_result['lot_id'] : ''; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Lot Number -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">รหัส Lot สินค้า</label>
                        <div class="">
                            <?php if (isset($lot_result['lot_number'])): ?>
                                <span class="view-mode"><?php echo htmlspecialchars($lot_result['lot_number']); ?></span>
                                <input type="text" name="lot_number" value="<?php echo htmlspecialchars($lot_result['lot_number']); ?>" class="edit-mode hidden w-full border rounded px-3">
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Lot Detail -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">รายละเอียด</label>
                        <div>
                            <span class="view-mode"><?php echo htmlspecialchars($lot_result['lot_detail']); ?></span>
                            <input type="text" name="lot_detail" value="<?php echo htmlspecialchars($lot_result['lot_detail']); ?>" class="edit-mode hidden w-full border rounded px-3">
                        </div>
                    </div>

                    <!-- Lot Date -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">วันที่ล็อต</label>
                        <div>
                            <span class="view-mode"><?php echo htmlspecialchars($lot_result['lot_date']); ?></span>
                            <input type="date" name="lot_date" value="<?php echo htmlspecialchars($lot_result['lot_date']); ?>" class="edit-mode hidden w-full border rounded px-3">
                        </div>
                    </div>

                    <!-- Lot Status -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">สถานะล็อต</label>
                        <div>
                            <span class="view-mode"><?php echo htmlspecialchars($lot_result['lot_status_name']); ?></span>
                            <select name="lot_status_id" class="edit-mode hidden w-full border rounded px-3">
                                <?php foreach ($dropdowns['status_lot'] as $status): ?>
                                    <option value="<?= $status['lot_status_id'] ?>" <?= $status['lot_status_id'] == $lot_result['lot_status_id'] ? 'selected' : '' ?>>
                                        <?php echo htmlspecialchars($status['lot_status_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Warehouse -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">คลังสินค้า</label>
                        <div>
                            <span class="view-mode"><?php echo htmlspecialchars($lot_result['warehouse_name']); ?></span>
                            <select name="warehouse_id" class="edit-mode hidden w-full border rounded px-3">
                                <?php foreach ($dropdowns['warehouse_lot'] as $warehouse): ?>
                                    <option value="<?= $warehouse['warehouse_id'] ?>" <?= $warehouse['warehouse_id'] == $lot_result['warehouse_id'] ? 'selected' : '' ?>>
                                        <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Store -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">ร้านค้า</label>
                        <div>
                            <span class="view-mode"><?php echo htmlspecialchars($lot_result['store_name']); ?></span>
                            <select name="store_id" class="edit-mode hidden w-full border rounded px-3">
                                <?php foreach ($dropdowns['store_lot'] as $store): ?>
                                    <option value="<?= $store['store_id'] ?>" <?= $store['store_id'] == $lot_result['store_id'] ? 'selected' : '' ?>>
                                        <?php echo htmlspecialchars($store['store_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Supplier -->
                    <div class="grid grid-cols-2">
                        <label class="text-gray-700 text-lg font-bold">ผู้ส่งมอบ</label>
                        <div>
                            <span class="view-mode"><?php echo htmlspecialchars($lot_result['supplier_name']); ?></span>
                            <select name="supplier_id" class="edit-mode hidden w-full border rounded px-3">
                                <?php foreach ($dropdowns['supplier_lot'] as $supplier): ?>
                                    <option value="<?= $supplier['supplier_id'] ?>" <?= $supplier['supplier_id'] == $lot_result['supplier_id'] ? 'selected' : '' ?>>
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Display Lot Product Data -->
            <div class="w-[40%]">
                <?php foreach ($lot_product_result as $lot_product): ?>
                    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 w-full">
                        <div class="grid grid-cols-2">
                            <label class="text-gray-700 text-lg font-bold">รหัสรายการล็อตสินค้า</label>
                            <div class="view-mode">
                                <?php echo htmlspecialchars($lot_product['lot_product_id']); ?>
                            </div>
                            <input type="hidden" name="lot_product_id" value="<?php echo htmlspecialchars($lot_product['lot_product_id']); ?>">
                        </div>
                        <div class="grid grid-cols-2">
                            <label class="text-gray-700 text-lg font-bold">จำนวนสินค้า</label>
                            <div class="view-mode">
                                <?php echo htmlspecialchars($lot_product['lot_product_qty']); ?>
                            </div>
                        </div>
                        <div class="grid grid-cols-2">
                            <label class="text-gray-700 text-lg font-bold">ราคาทุน</label>
                            <div class="view-mode">
                                <?php echo htmlspecialchars($lot_product['lot_product_capital']); ?>
                            </div>
                        </div>
                        <div class="grid grid-cols-2">
                            <label class="text-gray-700 text-lg font-bold">จำนวนคงเหลือ</label>
                            <div class="view-mode">
                                <?php echo htmlspecialchars($lot_product['lot_product_remain']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                    <?php if (!empty($lot_product_result)): ?>
                        <?php foreach ($lot_product_result as $lot_product): ?>
                            <tr>
                                <td class="px-4 py-2 border text-center"><?php echo htmlspecialchars($lot_product['product_name']); ?></td>
                                <td class="px-4 py-2 border text-center"><?php echo htmlspecialchars($lot_product['lot_product_qty']); ?></td>
                                <td class="px-4 py-2 border text-center"><?php echo htmlspecialchars($lot_product['lot_product_capital']); ?></td>
                                <td class="px-4 py-2 border text-center"><?php echo htmlspecialchars($lot_product['lot_product_remain']); ?></td>
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