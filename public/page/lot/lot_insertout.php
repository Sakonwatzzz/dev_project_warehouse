<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Initialize variables
$error = null;
$selected_status_id = '';

// Function to fetch data from database
function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }
}

// Load necessary data
try {
    // Fetch lot status data
    $query = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $status_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch warehouse data
    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse ORDER BY warehouse_id";
    $warehouse_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch personnel data
    $query = "SELECT personnel_id, personnel_name FROM personnel";
    $personnel_lot = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Generate new lot_name
    $query = "SELECT lot_name FROM lot WHERE lot_name LIKE 'WT-%' ORDER BY lot_id DESC LIMIT 1";
    $result = fetchData($pdo, $query)->fetch(PDO::FETCH_ASSOC);
    $newLotNumber = $result ? (int)preg_replace('/WT-(\d+)/', '$1', $result['lot_name']) + 1 : 1;
    $new_lot_name = "WT-" . str_pad($newLotNumber, 3, '0', STR_PAD_LEFT);

    // Fetch lots for table (warehouse-to-warehouse transfers)
    $query = "
        SELECT 
            l.lot_id,
            l.lot_name,
            l.lot_date,
            l.lot_status_id,
            pn.personnel_id,
            pn.personnel_name,
            ls.lot_status_name,
            w1.warehouse_name AS warehouse_from_name,
            w2.warehouse_name AS warehouse_name
        FROM lot l
        LEFT JOIN warehouse w1 ON l.warehouse_from_id = w1.warehouse_id
        LEFT JOIN warehouse w2 ON l.warehouse_id = w2.warehouse_id
        INNER JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
        LEFT JOIN personnel pn ON l.personnel_id = pn.personnel_id
        WHERE l.warehouse_inout = 2
        ORDER BY l.lot_id DESC
        ";
    $lots = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    // Set default status ID
    $selected_status_id = $_POST['lot_status_id'] ?? ($status_lot[0]['lot_status_id'] ?? '');
} catch (Exception $e) {
    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_name = $_POST['lot_name'] ?? '';
    $lot_detail = $_POST['lot_detail'] ?? '';
    $lot_date = $_POST['lot_date'] ?? date("Y-m-d H:i:s");
    $lot_status_id = $_POST['lot_status_id'] ?? $selected_status_id;
    $warehouse_id = $_POST['warehouse_id'] ?? '';
    $warehouse_from_id = $_POST['warehouse_from_id'] ?? '';
    $warehouse_inout = $_POST['warehouse_inout'] ?? '2';
    $personnel_id = $_POST['personnel_id'] ?? null;

    // Validate form data
    if (empty($lot_name) || empty($warehouse_id) || empty($warehouse_from_id)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน: ชื่อ Lot, คลังต้นทาง, และคลังปลายทาง";
    } elseif ($warehouse_id == $warehouse_from_id) {
        $error = "คลังต้นทางและคลังปลายทางต้องไม่เป็นคลังเดียวกัน";
    } else {
        try {
            $pdo->beginTransaction();

            // Insert new lot
            $stmt = $pdo->prepare(
                'INSERT INTO lot (lot_name, lot_detail, lot_date, lot_status_id, warehouse_id, personnel_id, warehouse_from_id, warehouse_inout) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$lot_name, $lot_detail, $lot_date, $lot_status_id, $warehouse_id, $personnel_id, $warehouse_from_id, $warehouse_inout]);

            $lot_id = $pdo->lastInsertId();
            if (!$lot_id) {
                throw new Exception("ไม่สามารถดึงค่า lot_id ได้");
            }

            $pdo->commit();

            // Redirect to lot details page
            header("Location: index.php?page=lot_insertdetailout&tabel=lot&id=" . urlencode($lot_id) . "&success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Set timezone
date_default_timezone_set('Asia/Bangkok');
$displayDateTime = date("d/m/Y H:i:s");
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ย้ายสินค้า - ระบบจัดการคลังสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <header class="flex justify-end items-center mb-8">
            <!-- <h1 class="text-4xl font-bold text-gray-800">ย้ายสินค้าคลัง</h1> -->
            <div class="text-gray-600"><i class="far fa-clock mr-2"></i><?= $displayDateTime ?></div>
        </header>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Form Section -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#d7e1ec] to-[#e6effb] p-4 border-b border-[#cfd5dc]">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-exchange-alt mr-2"></i>ย้ายสินค้าจากคลัง
                </h2>
            </div>
            <form method="POST" action="" class="p-8">
                <div class="space-y-6 bg-gray-50 p-6 rounded-lg border border-gray-200">
                    <h3 class="text-xl font-semibold text-blue-700 mb-4 flex items-center">
                        <i class="fas fa-truck-loading mr-2"></i>ย้ายสินค้าไปยังคลังอื่น
                    </h3>

                    <div class="form-group">
                        <label for="lot_name" class="block text-gray-700 font-medium mb-2">ชื่อ Lot ย้ายสินค้า <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="fas fa-tag text-gray-400"></i>
                            </div>
                            <input type="text" name="lot_name" id="lot_name" class="w-full border rounded-lg px-3 py-3 pl-10" value="<?= htmlspecialchars($new_lot_name) ?>" required readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="lot_detail" class="block text-gray-700 font-medium mb-2">รายละเอียดการย้ายสินค้า</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="fas fa-info-circle text-gray-400"></i>
                            </div>
                            <input type="text" name="lot_detail" id="lot_detail" class="w-full border rounded-lg px-3 py-3 pl-10" placeholder="กรอกรายละเอียดการย้าย">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="personnel_from_id" class="block text-gray-700 font-medium mb-2">พนักงาน <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="fas fa-user-tie text-gray-400"></i>
                            </div>
                            <select name="personnel_id" id="personnel_id"
                                class="w-full border rounded-lg pl-10 pr-10 py-3 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-400">
                                <?php foreach ($personnel_lot as $personnel): ?>
                                    <option value="<?= htmlspecialchars($personnel['personnel_id']) ?>">
                                        <?= htmlspecialchars($personnel['personnel_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="warehouse_from_id" class="block text-gray-700 font-medium mb-2">นำมาจากคลัง <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="fas fa-dolly text-gray-400"></i>
                            </div>
                            <select name="warehouse_from_id" id="warehouse_from_id" class="w-full border rounded-lg pl-10 pr-10 py-3 appearance-none" required>
                                <option value="">เลือกคลังต้นทาง</option>
                                <?php foreach ($warehouse_lot as $warehouse): ?>
                                    <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                        <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="warehouse_id" class="block text-gray-700 font-medium mb-2">ลงคลัง <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="fas fa-warehouse text-gray-400"></i>
                            </div>
                            <select name="warehouse_id" id="warehouse_id" class="w-full border rounded-lg pl-10 pr-10 py-3 appearance-none" required>
                                <option value="">เลือกคลังปลายทาง</option>
                                <?php foreach ($warehouse_lot as $warehouse): ?>
                                    <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                        <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" hidden>
                        <label for="lot_status_id" class="block text-gray-700 font-medium mb-2">สถานะ</label>
                        <input type="hidden" name="lot_status_id" value="<?= $selected_status_id ?>">
                    </div>

                    <div class="form-group" hidden>
                        <label for="warehouse_inout" class="block text-gray-700 font-medium mb-2">สถานะการรับ-จ่าย</label>
                        <input type="hidden" name="warehouse_inout" value="2">
                    </div>

                    <input type="hidden" name="lot_date" value="<?= date("Y-m-d H:i:s") ?>">
                </div>

                <div class="mt-10 flex justify-center">
                    <button type="submit" class="bg-gradient-to-r from-green-500 to-green-700 text-white text-lg px-10 py-3 rounded-lg shadow-lg hover:from-green-600 hover:to-green-800 transition-all flex items-center">
                        <i class="fas fa-save mr-3"></i>บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>

        <!-- Lots Table Section -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#d7e1ec] to-[#e6effb] p-4 border-b border-[#cfd5dc]">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list mr-2"></i>รายการย้ายสินค้าคลัง
                </h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">รหัส Lot</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">ชื่อ Lot</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">คลังต้นทาง</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">คลังปลายทาง</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">เจ้าหน้าที่</th>
                                <th class="px-6 py-3 border text-left text-gray-700 font-semibold">วันที่</th>
                                <th class="px-6 py-3 border text-center text-gray-700 font-semibold">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lots)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-gray-600">ไม่มีรายการย้ายสินค้าคลัง</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lots as $lot): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-6 py-2 border"><?= htmlspecialchars($lot['lot_id']) ?></td>
                                        <td class="px-6 py-2 border"><?= htmlspecialchars($lot['lot_name']) ?></td>
                                        <td class="px-6 py-2 border"><?= htmlspecialchars($lot['warehouse_from_name'] ?? 'ไม่ระบุ') ?></td>
                                        <td class="px-6 py-2 border"><?= htmlspecialchars($lot['warehouse_name'] ?? 'ไม่ระบุ') ?></td>
                                        <td class="px-6 py-2 border"><?= htmlspecialchars($lot['personnel_name'] ?? 'ไม่ระบุ') ?></td>
                                        <td class="px-6 py-2 border"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($lot['lot_date']))) ?></td>
                                        <td class="px-6 py-2 border text-center">
                                            <a href="index.php?page=lot_insertdetailout&tabel=lot&id=<?= urlencode($lot['lot_id']) ?>"
                                                class="bg-blue-500 text-white px-4 py-1 rounded hover:bg-blue-600 transition">
                                                <i class="fas fa-eye mr-1"></i>ดูรายละเอียด
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>