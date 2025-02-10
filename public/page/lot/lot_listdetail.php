<?php
require_once __DIR__ . '../../../../config/database_server.php';

// ตรวจสอบค่า ID ที่ได้รับจาก URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ค่า ID ไม่ถูกต้อง");
}

$lot_id = (int) $_GET['id'];
$table = $_GET['table'] ?? 'lot';

try {
    // คำสั่ง SQL สำหรับดึงข้อมูลล็อต
    $query_product = "SELECT
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
        LEFT JOIN store ON lot.store_id = store.store_id
    WHERE lot.lot_id = :lot_id";

    $stmt_product = $pdo->prepare($query_product);
    $stmt_product->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
    $stmt_product->execute();
    $lot_data = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if (!$lot_data) {
        die("ไม่พบข้อมูลสำหรับล็อตนี้");
    }
} catch (PDOException $e) {
    // แสดงข้อความข้อผิดพลาดจากฐานข้อมูล
    die("เกิดข้อผิดพลาดในฐานข้อมูล: " . $e->getMessage());
}

// ดึงรายการสินค้าภายในล็อต
try {
    $query_product = "SELECT
        lot_product.lot_product_id, 
        lot_product.lot_id, 
        lot_product.lot_product_qty, 
        lot_product.lot_product_remain,  
        lot_product.lot_product_capital, 
        lot_product.lot_product_date, 
        lot_product.lot_product_status,  
        lot_product.product_id, 
        product.product_name
    FROM
        lot_product
        LEFT JOIN lot ON lot_product.lot_id = lot.lot_id
        LEFT JOIN product ON lot_product.product_id = product.product_id
    WHERE lot_product.lot_id = :lot_id";

    $stmt_product = $pdo->prepare($query_product);
    $stmt_product->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
    $stmt_product->execute();
    $lot_product = $stmt_product->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูลสินค้าจากฐานข้อมูล: " . $e->getMessage());
}

// ตรวจสอบการเพิ่มสินค้าลงในล็อตผ่าน POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the form data
        $lot_id = $_POST['lot_id'];
        $lot_product_qty = $_POST['lot_product_qty'];
        $lot_product_remain = $_POST['lot_product_remain'];
        $lot_product_capital = $_POST['lot_product_capital'];
        $lot_product_date = $_POST['lot_product_date'];
        $lot_product_status = $_POST['lot_product_status'];
        $product_id = $_POST['product_id'];

        // Validate inputs
        if (empty($lot_id) || empty($lot_product_qty) || empty($product_id)) {
            throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
        }

        // Prepare the SQL insert statement
        $stmt = $pdo->prepare('INSERT INTO lot_product (lot_id, lot_product_qty, lot_product_remain, lot_product_capital, lot_product_date, lot_product_status, product_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)');

        // Execute the query with the data from the form
        $stmt->execute([
            $lot_id,
            $lot_product_qty,
            $lot_product_remain,
            $lot_product_capital,
            $lot_product_date,
            $lot_product_status,
            $product_id
        ]);

        // Get the last inserted ID and redirect
        $lot_product_id = $pdo->lastInsertId();
        // header("Location: /?page=lot_productlist&success=1");
        exit;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
// ตรวจสอบการแก้ไขข้อมูลสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_product_id = $_POST['lot_product_id'];
    $lot_id = $_POST['lot_id'];
    $lot_product_qty = $_POST['lot_product_qty'];
    $lot_product_remain = $_POST['lot_product_remain'];
    $lot_product_capital = $_POST['lot_product_capital'];
    $lot_product_date = $_POST['lot_product_date'];
    $lot_product_status = $_POST['lot_product_status'];
    $product_id = $_POST['product_id'];


    $stmt = $pdo->prepare("
        UPDATE lot_product 
        SET product_lot_id = ?, product_lot_product_qty = ?, product_lot_product_remain = ?, lot_product_capital = ?, 
            lot_product_date = ?, lot_product_status = ?, product_id 
        WHERE lot_product_id = ?
    ");
    $stmt->execute([$lot_id, $lot_product_qty, $lot_product_remain, $lot_product_capital, $lot_product_date, $lot_product_status, $lot_product_status, $product_id, $lot_product_id]);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดล็อตสินค้า</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <h1 class="text-4xl font-bold mb-8">รายละเอียดล็อตสินค้า</h1>

        <?php if ($lot_data): ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="font-semibold mb-2">รหัส Lot สินค้า:</p>
                        <span><?php echo htmlspecialchars($lot_data['lot_id']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">วันที่รับสินค้า:</p>
                        <span><?php echo htmlspecialchars($lot_data['lot_date']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">คลังสินค้า:</p>
                        <span><?php echo htmlspecialchars($lot_data['warehouse_name']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">ผู้ส่งมอบ:</p>
                        <span><?php echo htmlspecialchars($lot_data['supplier_name']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">ร้านค้า:</p>
                        <span><?php echo htmlspecialchars($lot_data['store_name']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">สถานะ:</p>
                        <span><?php echo htmlspecialchars($lot_data['lot_status_name']); ?></span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p class="text-red-500">ไม่พบข้อมูลล็อตสินค้า</p>
        <?php endif; ?>
        <h2 class="text-2xl font-semibold mb-4">สแกนสินค้าเข้าล็อต</h2>

        <div class="mb-4">
            <label class="block mb-2">สแกนบาร์โค้ดสินค้า:</label>
            <input type="number" name="product_id"
                class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                autofocus>
        </div>
        <!-- ตารางแสดงรายการสินค้า -->
        <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold mb-4">รายการสินค้าที่สแกน</h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 border text-left w-50">บาร์โค้ดสินค้า</th>
                            <th class="px-6 py-3 border text-left w-1/4">ชื่อสินค้า</th>
                            <th class="px-6 py-3 border text-center w-30">จำนวน</th>
                            <th class="px-6 py-3 border text-right w-30">ราคาทุน</th>
                            <th class="px-6 py-3 border text-right w-30">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($lot_product)): ?>
                            <?php foreach ($lot_product as $lot_product_result): ?>
                                <tr class="bg-white hover:bg-[#aec5e366] cursor-pointer">
                                    <td class="pl-6 py-2 border border-gray-500"><?php echo $lot_product_result['product_id']; ?></td>
                                    <td class="pl-6 py-2 border border-gray-500"><?php echo $lot_product_result['product_name']; ?></td>
                                    <td class="text-center py-2 border border-gray-500"><?php echo $lot_product_result['lot_product_qty']; ?></td>
                                    <td class="text-right py-2 border border-gray-500"><?php echo $lot_product_result['lot_product_capital']; ?></td>
                                    <td>
                                        <!-- ปุ่มแก้ไข -->
                                        <button id="editBtn" class="bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 text-white text-lg border border-yellow-700 px-2 py-1 rounded shadow-md">แก้ไข</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">ไม่มีข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                </table>
                <!-- ฟอร์มแก้ไขที่จะแสดงเมื่อกดปุ่มแก้ไข -->
                <div id="editForm" style="display: none;">
                    <form method="POST">
                        <label for="lot_product_qty">จำนวน:</label>
                        <input type="number" id="lot_product_qty" name="lot_product_qty" value="<?php echo $lot_product['lot_product_qty']; ?>" required>

                        <label for="lot_product_capital">ราคาทุน:</label>
                        <input type="text" id="lot_product_capital" name="lot_product_capital" value="<?php echo $lot_product['lot_product_capital']; ?>" required>

                        <label for="lot_product_status">สถานะ:</label>
                        <select id="lot_product_status" name="lot_product_status">
                            <option value="Active" <?php echo ($lot_product['lot_product_status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($lot_product['lot_product_status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>

                        <button type="submit">อัพเดต</button>
                        <button type="button" id="cancelBtn">ยกเลิก</button>
                    </form>
                </div>
            </div>
        </div>


        <!-- คำนวนรายการ -->
        <!-- <div class="mt-8 flex justify-end space-x-6">
                <div class="flex flex-col">
                    <label class="text-gray-700 font-medium">จำนวนรายการ</label>
                    <label class="w-[250px] border border-gray-400 px-4 py-2 rounded-md bg-gray-50 text-center">88</label>
                </div>
                <div class="flex flex-col">
                    <label class="text-gray-700 font-medium">ยอดรวม</label>
                    <label class="w-[250px] border border-gray-400 px-4 py-2 rounded-md bg-gray-50 text-center">12,000</label>
                </div>
            </div> -->

        <div class="mt-6 flex justify-end">
            <button id="save-all" class="bg-green-500 text-white px-8 py-3 rounded-lg shadow-md hover:bg-green-600">
                บันทึกทั้งหมด
            </button>
        </div>
    </div>


    </div>

</body>
<script>
    $(document).ready(function() {
        $('input[name="product_id"]').on('keypress', function(e) {
            if (e.which == 13) {
                e.preventDefault();
                let product_id = $(this).val();
                if (product_id) {
                    $.post(window.location.href, {
                        barcode: product_id
                    }, function(data) {
                        location.reload();
                    });
                }
            }
        });
    });
</script>
<script>
    // เมื่อกดปุ่ม "แก้ไข" จะให้แสดงฟอร์ม
    document.getElementById("editBtn").onclick = function() {
        document.getElementById("editForm").style.display = "block";
    };

    // เมื่อกดปุ่ม "ยกเลิก" จะซ่อนฟอร์ม
    document.getElementById("cancelBtn").onclick = function() {
        document.getElementById("editForm").style.display = "none";
    };
</script>

</html>