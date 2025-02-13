<?php
require_once __DIR__ . '../../../../config/database_server.php';

// ตรวจสอบค่า ID ที่ได้รับจาก URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ค่า ID ไม่ถูกต้อง");
}

$lot_id = (int) $_GET['id'];
$table = $_GET['table'] ?? 'lot';

try {
    // ดึงข้อมูลล็อต
    $query_lotlist = "SELECT
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
    FROM lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
        LEFT JOIN store ON lot.store_id = store.store_id
    WHERE lot.lot_id = :lot_id";

    $stmt_lot = $pdo->prepare($query_lotlist);
    $stmt_lot->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
    $stmt_lot->execute();
    $lot_data = $stmt_lot->fetchAll(PDO::FETCH_ASSOC);
    if (!$lot_data) {
        die("ไม่พบข้อมูลสำหรับล็อตนี้");
    }
} catch (PDOException $e) {
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
        FROM lot_product
            LEFT JOIN lot ON lot_product.lot_id = lot.lot_id
            LEFT JOIN product ON lot_product.product_id = product.product_id
        WHERE lot_product.lot_id = :lot_id";

    $stmt_product = $pdo->prepare($query_product);
    $stmt_product->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
    $stmt_product->execute();
    $lot_product_data = $stmt_product->fetch(PDO::FETCH_ASSOC);
    if (!$lot_product_data) {
        die("ไม่พบข้อมูลสำหรับล็อตสินค้าในตารางนี้");
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูลสินค้าจากฐานข้อมูล: " . $e->getMessage());
}



// ตรวจสอบการเพิ่มสินค้าลงในล็อตผ่าน POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['product_id'])) {
    try {
        $product_id = $_POST['product_id'];

        // ตรวจสอบค่าว่าง
        if (empty($product_id)) {
            throw new Exception("กรุณากรอกหรือสแกนบาร์โค้ดสินค้า");
        }

        // ค้นหาข้อมูลสินค้า
        $stmt = $pdo->prepare('SELECT product_id, product_name, product_price FROM product WHERE product_id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("ไม่พบข้อมูลสินค้าที่มีบาร์โค้ดนี้");
        }

        // เพิ่มสินค้าเข้า lot_product
        $stmt = $pdo->prepare('INSERT INTO lot_product (lot_id, lot_product_qty, lot_product_remain, lot_product_capital, lot_product_date, lot_product_status, product_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt_product->execute([$lot_id, $lot_product_qty, $lot_product_remain, $lot_product_date, $lot_product_capital, $product_id]);


        // ตรวจสอบว่าข้อมูลถูกเพิ่มหรือไม่
        if ($stmt->rowCount() > 0) {
            // รีเฟรชรายการสินค้า
            $stmt_product = $pdo->prepare($query_product);
            $stmt_product->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
            $stmt_product->execute();
            $lot_product = $stmt_product->fetchAll(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("เกิดข้อผิดพลาดในการเพิ่มสินค้า");
        }

        // รีเฟรชหน้าเว็บเพื่อให้ข้อมูลใหม่แสดงทันที
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $lot_id);
        exit();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
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
                        <span><?php echo ($lot_data['lot_id']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">วันที่รับสินค้า:</p>
                        <span><?php echo ($lot_data['lot_date']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">คลังสินค้า:</p>
                        <span><?php echo ($lot_data['warehouse_name']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">ผู้ส่งมอบ:</p>
                        <span><?php echo ($lot_data['supplier_name']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">ร้านค้า:</p>
                        <span><?php echo ($lot_data['store_name']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">สถานะ:</p>
                        <span><?php echo ($lot_data['lot_status_name']); ?></span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p class="text-red-500">ไม่พบข้อมูลล็อตสินค้า</p>
        <?php endif; ?>
        <h2 class="text-2xl font-semibold mb-4">สแกนสินค้าเข้าล็อต</h2>
        <form action="" method="POST">
            <div class="mb-4">
                <label class="block mb-2">สแกนบาร์โค้ดสินค้า:</label>
                <input type="number" name="product_id"
                    class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400" autofocus>
            </div>

            <!-- Display message -->
            <?php if (!empty($error_message)): ?>
                <div class="text-red-500 mb-4">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Table display scanned products -->
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
                            <?php foreach ($lot_product as $lot_product_result): ?>
                                <tr class="bg-white hover:bg-[#aec5e366] cursor-pointer">
                                    <td class="pl-6 py-2 border border-gray-500">
                                        <?php echo $lot_product_result['product_id']; ?>
                                    </td>
                                    <td class="pl-6 py-2 border border-gray-500">
                                        <?php echo $lot_product_result['product_name']; ?>
                                    </td>
                                    <td class="text-center py-2 border border-gray-500">
                                        <?php echo $lot_product_result['lot_product_qty']; ?>
                                    </td>
                                    <td class="text-right py-2 border border-gray-500">
                                        <?php echo number_format($lot_product_result['lot_product_capital'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
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
</body>
<script>
    $(document).ready(function () {
        $('input[name="product_id"]').on('keypress', function (e) {
            if (e.which == 13) {
                e.preventDefault();
                let product_id = $(this).val();
                if (product_id) {
                    $.post(window.location.href, {
                        barcode: product_id
                    }, function (data) {
                        location.reload();
                    });
                }
            }
        });
    });
</script>
<script>
    // เมื่อกดปุ่ม "แก้ไข" จะให้แสดงฟอร์ม
    document.getElementById("editBtn").onclick = function () {
        document.getElementById("editForm").style.display = "block";
    };

    // เมื่อกดปุ่ม "ยกเลิก" จะซ่อนฟอร์ม
    document.getElementById("cancelBtn").onclick = function () {
        document.getElementById("editForm").style.display = "none";
    };
</script>

</html>