<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

try {
    // ดึงชื่อคลังมาแสดงผล
    $query = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $status_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // ดึงชื่อคลังมาแสดงผล
    $query = "SELECT warehouse_id, warehouse_name FROM warehouse";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // ดึงชื่อผู่ส่งมอบมาแสดงผล
    $query = "SELECT supplier_id, supplier_name FROM supplier";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $supplier_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT store_id, store_name FROM store";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $store_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "error" . $e->getMessage();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_detail = $_POST['lot_detail'];
    $lot_date = $_POST['lot_date'];
    $lot_status_id = $_POST['lot_status_id'];
    $warehouse_id = $_POST['warehouse_id'];
    $store_id = $_POST['store_id'];
    $supplier_id = $_POST['supplier_id'];

    $stmt = $pdo->prepare('INSERT INTO lot (lot_detail, lot_date, lot_status_id, warehouse_id, store_id, supplier_id) 
    VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$lot_detail, $lot_date, $lot_status_id, $warehouse_id, $store_id, $supplier_id]);

    $lot_id = $pdo->lastInsertId();
    header("Location: index.php?page=lot_detail&tabel=lot&id=$lot_id");
    exit;
}

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


try {
    // ดึงข้อมูล lot_id
    $stmt = $pdo->query("SELECT lot_id FROM lot");
    $lot_id = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูล product_id
    $stmt = $pdo->query("SELECT product_id FROM product");
    $product_id = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the form data
    $lot_id = $_POST['lot_id']; // ตอนนี้มันจะเป็นค่าหนึ่งเดียว ไม่ใช่อาเรย์
    $lot_product_qty = $_POST['lot_product_qty'];
    $lot_product_remain = $_POST['lot_product_remain'];
    $lot_product_capital = $_POST['lot_product_capital'];
    $lot_product_date = $_POST['lot_product_date'];
    $lot_product_status = $_POST['lot_product_status'];
    $product_id = $_POST['product_id']; // นี่ก็ควรจะเป็นค่าหนึ่งเดียว

    // Prepare the SQL insert statement
    $stmt = $pdo->prepare('INSERT INTO lot_product (lot_id, lot_product_qty, lot_product_remain, lot_product_capital, lot_product_date, lot_product_status, product_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?)');

    // Execute the query with the data from the form
    $stmt->execute([
        $lot_id, // ตอนนี้มันเป็นค่าหนึ่งเดียว
        $lot_product_qty,
        $lot_product_remain,
        $lot_product_capital,
        $lot_product_date,
        $lot_product_status,
        $product_id // นี่ก็คือค่าหนึ่งเดียวเช่นกัน
    ]);

    // Get the last inserted ID and redirect
    $lot_product_id = $pdo->lastInsertId();
    header("Location: lot/lot_product_detail.php?tabel=lot_product&id=$lot_product_id");
    exit;
}

$query = "SELECT
    lot_product.lot_product_id, 
    lot_product.lot_id, 
    lot_product.lot_product_qty, 
    lot_product.lot_product_capital, 
    lot_product.lot_product_date, 
    lot_product.product_id, 
    product.product_id
FROM
    lot_product
    INNER JOIN
    lot
    ON 
    lot_product.lot_id = lot.lot_id
    INNER JOIN
    product
    ON 
    lot_product.product_id = product.product_id";
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>Document</title>

    <style>
        .notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            color: #000;
            border: 2px solid #A0A0A0;
            border-radius: 5px;
            font-family: "Tahoma", "Verdana", sans-serif;
            width: 400px;
            height: 120px;
            box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
        }

        .notification-header {
            background-color: #A0C4FF;
            color: #000;
            padding: 10px;
            font-weight: bold;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }

        .notification-body {
            padding: 20px;
            font-size: 16px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class=" font-[Tahoma] mx-[2rem] mt-1 ">
        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">การรับสินค้าสำเร็จ!</div>
        </div>
        <div class=" ">
            <form class="p-5  space-y-2" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->

                <div class="grid grid-cols-2 gap-4  ">
                    <div class="bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
                        <h2 class="bg-[#0046AA] text-white text-2xl font-bold p-5">การรับสินค้า</h2>

                        <div class="flex items-center px-4 pt-5">
                            <label class="w-[150px] text-base mr-4" for="lot_product_qty" for="lot_id">รหัสล็อตสินค้า:</label>
                            <div class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                                <p type="id" id="lot_id" name="lot_id" value="<?php echo $lot['lot_id']; ?>" readonly>
                            </div>
                        </div>
                        <div class="flex items-center px-4 pt-2">
                            <label class="w-[150px] text-base mr-4" for="lot_product_qty" for="lot_detail">รายละเอียด:</label>
                            <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" id="lot_detail" name="lot_detail">
                        </div>
                        <div class="flex items-center px-4 pt-2">
                            <label class="w-[150px] text-base mr-4" for="lot_date">วันที่รับเข้า:</label>
                            <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="date" id="lot_date" name="lot_date" value="<?= date('Y-m-d') ?>" readonly>
                        </div>

                        <!-- loop -->
                        <?php
                        if (count($status_lot) > 0) {
                        ?>
                            <div class="flex items-center px-4 pt-2">
                                <label class="w-[150px] text-base mr-4" for="lot_stutas_name">สถานะ:</label>
                                <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="lot_status_id" id="lot_status_id">
                                    <?php foreach ($status_lot as $status_insert) { ?>
                                        <option value="<?php echo ($status_insert['lot_status_id']); ?>">
                                            <?php echo ($status_insert['lot_status_name']); ?>
                                        </option>

                                    <?php } ?>
                                </select><br>
                            </div>

                        <?php
                        } else {
                            echo "No lot_stutas_name  available.";
                        }
                        ?>
                        <!-- loop -->
                        <?php
                        if (count($warehouse_lot) > 0) {
                        ?>
                            <div class="flex items-center px-4 pt-2">
                                <label class="w-[150px] text-base mr-4" for="warehouse_name">คลังสินค้า:</label>
                                <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="warehouse_id" id="warehouse_id">
                                    <?php foreach ($warehouse_lot as $warehouse_insert) { ?>
                                        <option value="<?php echo ($warehouse_insert['warehouse_id']); ?>">
                                            <?php echo ($warehouse_insert['warehouse_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select><br>
                            </div>

                        <?php
                        } else {
                            echo "No warehouse available.";
                        }
                        ?>
                        <?php
                        if (count($store_lot) > 0) {
                        ?>
                            <div class="flex items-center px-4 pt-2">
                                <label class="w-[150px] text-base mr-4" for="store_name">ร้าน:</label>
                                <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="store_id" id="store_id">
                                    <?php foreach ($store_lot as $store_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
                                        <option value="<?php echo ($store_insert['store_id']); ?>">
                                            <?php echo ($store_insert['store_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select><br>
                            </div>

                        <?php
                        } else {
                            echo "No store_name available.";
                        }
                        ?>
                        <?php
                        if (count($warehouse_lot) > 0) {
                        ?>
                            <div class="flex items-center px-4 pt-2">
                                <label class="w-[150px] text-base mr-4" for="supplier_name">ผู้ส่งมอบ:</label>
                                <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="supplier_id" id="supplier_id">
                                    <?php foreach ($supplier_lot as $supplier_insert) { ?>
                                        <option value="<?php echo ($supplier_insert['supplier_id']); ?>">
                                            <?php echo ($supplier_insert['supplier_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select><br>
                            </div>

                        <?php
                        } else {
                            echo "No warehouse types available.";
                        }
                        ?>

                    </div>

                    <div c class="bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
                        <h2 class="bg-[#0046AA] text-white text-2xl font-bold p-5 ">การรับรายการสินค้า</h2>

                        <div class="flex items-center px-4 pt-5">
                            <label class="w-[150px] text-base mr-4" for="lot_product_id">รหัสรายการรับสินค้า:</label>
                            <div class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                                <p type="id" id="lot_product_id" name="lot_product_id" value="<?php echo $lot['lot_product_id']; ?>"
                                    readonly>
                            </div>

                        </div>
                        <!-- loop -->
                        <?php
                        if (count(value: $lot_id) > 0) {
                        ?>
                            <div class="flex items-center px-4 pt-2">
                                <label class="w-[150px] text-base mr-4" for="lot_id">รหัสล็อตสินค้า:</label>
                                <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="lot_id" id="lot_id">
                                    <?php foreach ($lot_id as $lot_id_insert) { ?>
                                        <option value="<?php echo ($lot_id_insert['lot_id']); ?>">
                                            <?php echo ($lot_id_insert['lot_id']); ?>
                                        </option>

                                    <?php } ?>
                                </select><br>
                            </div>

                        <?php
                        } else {
                            echo "No lot_id available.";
                        }
                        ?>
                        <div class="flex items-center px-4 pt-2">
                            <label class="w-[150px] text-base mr-4" for="lot_product_qty">จำนวนสินค้าเข้า:</label>
                            <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" id="lot_product_qty" name="lot_product_qty">
                        </div>
                        <div class="flex items-center px-4 pt-2">
                            <label class="w-[150px] text-base mr-4" for="lot_product_remain">จำนวนสินค้าคงเหลือ:</label>
                            <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" id="lot_product_remain" name="lot_product_remain">
                        </div>
                        <div class="flex items-center px-4 pt-2">
                            <label class="w-[150px] text-base mr-4" for="lot_product_capital">ราคาต้นทุน:</label>
                            <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" id="lot_product_capital" name="lot_product_capital">
                        </div>
                        <div class="flex items-center px-4 pt-2">
                            <label class="w-[150px] text-base mr-4" for="lot_product_date">วันที่รับสินค้า:</label>
                            <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="date" id="lot_product_date" name="lot_product_date" value="<?= date('Y-m-d') ?>"
                                readonly>
                        </div>
                        <div class="flex items-center px-4 pt-2">
                            <label class="w-[150px] text-base mr-4" for="lot_product_status">สถานะสินค้า:</label>
                            <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="id" id="lot_product_status" name="lot_product_status">
                        </div>
                        <!-- loop -->
                        <?php
                        if (count($product_id) > 0) {
                        ?>
                            <div class="flex items-center px-4 pt-2 pb-5">
                                <label class="w-[150px] text-base mr-4" for="product_id">รหัสสินค้า:</label>
                                <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="product_id" id="product_id">
                                    <?php foreach ($product_id as $lot_product_insert) { ?>
                                        <option value="<?php echo ($lot_product_insert['product_id']); ?>">
                                            <?php echo ($lot_product_insert['product_id']); ?>
                                        </option>
                                    <?php } ?>
                                </select><br>
                            </div>

                        <?php
                        } else {
                            echo "No product_id available.";
                        }
                        ?>
                    </div>
                </div>

                <div class="flex justify-end mt-5">
                    <button
                        type="submit"
                        class="
                        w-[120px]
                        h-[40px]
                        bg-[#e1e1e1] 
                        text-black 
                        border-2 
                        border-[#A0A0A0] 
                        border-t-white 
                        border-l-white 
                        px-4 
                        py-1 
                        rounded 
                        shadow-md 
                        hover:bg-[#F1F1F1]
                        active:border-[#A0A0A0]
                        active:border-b-white
                        active:border-r-white
                        active:shadow-inner
                    ">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>

    </div>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.style.display = 'block'; // แสดงการแจ้งเตือน

                // ลบการแจ้งเตือนหลัง 2 วินาที
                setTimeout(() => {
                    notification.style.display = 'none'; // ซ่อนการแจ้งเตือน
                }, 2000);
            }
        });
    </script>
</body>

</html>