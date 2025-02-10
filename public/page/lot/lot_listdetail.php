<?php
require_once __DIR__ . '../../../../config/database_server.php';

try {
    // รับค่า lot_id จาก URL
    $lot_id = isset($_GET['id']) ? $_GET['id'] : null;

    if ($lot_id) {
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
            LEFT JOIN store ON lot.store_id = store.store_id
        WHERE lot.lot_id = :lot_id";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
        $stmt->execute();
        $result_lot_list = $stmt;
    } 

    else {
        echo "ไม่พบข้อมูล Lot ID";
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
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
        <?php
        if ($result_lot_list->rowCount() > 0) {
            while ($lot_list = $result_lot_list->fetch(PDO::FETCH_ASSOC)): ?>
                <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <p class="font-semibold mb-2">รหัส Lot สินค้า:</p>
                            <span><?php echo htmlspecialchars($lot_list['lot_id']); ?></span>
                        </div>
                        <div>
                            <p class="font-semibold mb-2">วันที่รับสินค้า:</p>
                            <span><?php echo htmlspecialchars($lot_list['lot_date']); ?></span>
                        </div>
                        <div>
                            <p class="font-semibold mb-2">คลังสินค้า:</p>
                            <span><?php echo htmlspecialchars($lot_list['warehouse_name']); ?></span>
                        </div>
                        <div>
                            <p class="font-semibold mb-2">ผู้ส่งมอบ:</p>
                            <span><?php echo htmlspecialchars($lot_list['supplier_name']); ?></span>
                        </div>
                        <div>
                            <p class="font-semibold mb-2">ร้านค้า:</p>
                            <span><?php echo htmlspecialchars($lot_list['store_name']); ?></span>
                        </div>
                        <div>
                            <p class="font-semibold mb-2">สถานะ:</p>
                            <span><?php echo htmlspecialchars($lot_list['lot_status_name']); ?></span>
                        </div>
                    </div>
                </div>
        <?php endwhile;
        } else {
            echo "<p class='text-red-500'>ไม่พบข้อมูลล็อตสินค้า</p>";
        } ?>



        <!-- ส่วนสแกนสินค้า -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-semibold mb-4">สแกนสินค้าเข้าล็อต</h2>

            <div class="mb-4">
                <label class="block mb-2">สแกนบาร์โค้ดสินค้า:</label>
                <input type="number" name="product_id"
                    class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                    autofocus>
            </div>

            <!-- ฟอร์มสำหรับกรอกข้อมูลเพิ่มเติมหลังสแกน -->
            <div id="product-form" class="hidden">
                <div class="mb-4">
                    <p class="font-semibold">ชื่อสินค้า: <span id="product-name"></span></p>
                    <p class="font-semibold">บาร์โค้ด: <span id="product-barcode"></span></p>
                </div>
                <div class="mb-4">
                    <label class="block mb-2">จำนวน:</label>
                    <input type="number" id="qty-input"
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                </div>
                <div class="mb-4">
                    <label class="block mb-2">ราคาทุน:</label>
                    <input type="number" id="capital-input" step="0.01"
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                </div>
                <button id="add-product"
                    class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                    เพิ่มสินค้า
                </button>
            </div>
        </div>

        <!-- ตารางแสดงรายการสินค้า -->
        <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold mb-4">รายการสินค้าที่สแกน</h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 border">บาร์โค้ด</th>
                            <th class="px-4 py-2 border">ชื่อสินค้า</th>
                            <th class="px-4 py-2 border">จำนวน</th>
                            <th class="px-4 py-2 border">ราคาทุน</th>
                            <th class="px-4 py-2 border">มูลค่ารวม</th>
                            <th class="px-4 py-2 border">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="scanned-products">
                        <!-- จะถูกเติมด้วย JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- คำนวนรายการ -->
            <div class="mt-8 flex space-x-2 justify-end">
                <div class=" flex flex-col">
                    <label for="">จำนวนรายการ</label>
                    <label for="" class=" w-[230px] border border-gray-400">88</label>
                </div>
                <div class=" flex flex-col">
                    <label for="">ยอดรวม</label>
                    <label for="" class=" w-[230px] border border-gray-400">12000</label>
                </div>

            </div>
            <div class="mt-4 flex justify-end">
                <button id="save-all" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">
                    บันทึกทั้งหมด
                </button>
            </div>
        </div>

    </div>

</body>

</html>