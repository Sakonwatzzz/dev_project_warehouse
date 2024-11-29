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

    <div class="add-box mt-[2.5rem] mx-[10rem]  bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">การรับสินค้าสำเร็จ!</div>
        </div>


        <h2 class="sub-title p-[10px] pl-[3rem] mb-[1rem] bg-[#0046aa] text-white text-2xl">การรับสินค้า</h2>
        <form class="form-container p-5" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->

            <div class="box flex  items-center py-[5px] pl-[30px]  ">
                <label class="w-[200px]  text-[16px] font-bold "   for="lot_id">รหัสล็อตสินค้า:</label>
                <p class="flex text-center items-center h-11 w-[100%] border border-[#878787]" type="text" id="lot_detail" type="id" id="lot_id" name="lot_id"  value="<?php echo $lot['lot_id']; ?>" readonly>
            
            </div>
            <div class="box  flex  py-[5px] pl-[30px] ">
                <label class="flex text-center items-center h-11 w-[200px]  text-[16px] font-bold" for="lot_detail" >รายละเอียด:</label>
                <input class="flex text-center items-center w-[100%] border border-[#878787]" type="text" id="lot_detail" name="lot_detail">
            </div>
            <div class="box  flex py-[5px] pl-[30px] ">
                <label class="flex text-center items-center w-[200px]  text-[16px] h-11 font-bold" for="lot_date">วันที่รับเข้า:</label>
                <input class="flex  items-center w-[100%]  border border-[#878787]" type="date" id="lot_date" name="lot_date" value="<?= date('Y-m-d') ?>" readonly>
            </div>
            <!-- loop -->
            <?php
            if (count($status_lot) > 0) {
                ?>
                <div class="box flex py-[5px] pl-[30px] ">
                    <label class="flex text-center items-center w-[200px]  text-[16px] font-bold" for="lot_stutas_name" class=" ">สถานะ:</label>
                    <select class="flex items-center h-11 w-[100%]  border border-[#878787]" name="lot_status_id" id="lot_status_id">
                        <?php foreach ($status_lot as $status_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
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
                <div class="box flex  py-[5px] pl-[30px] ">
                    <label class="flex text-center items-center w-[200px]  text-[16px] font-bold" for="warehouse_name">คลังสินค้า:</label>
                    <select class="flex h-11 items-center w-[100%]  border border-[#878787]" name="warehouse_id" id="warehouse_id">
                        <?php foreach ($warehouse_lot as $warehouse_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
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
                <div class="box  flex py-[5px] pl-[30px] ">
                    <label class="flex text-center items-center w-[200px]  text-[16px] font-bold" for="store_name">ร้าน:</label>
                    <select class="flex h-11 items-center w-[100%]  border border-[#878787]" name="store_id" id="store_id">
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
                <div class="box flex py-[5px] pl-[30px] ">
                    <label class="flex text-center items-center w-[200px]  text-[16px] font-bold" for="supplier_name">ผู้ส่งมอบ:</label>
                    <select class="flex h-11 items-center w-[100%]  border border-[#878787]" name="supplier_id" id="supplier_id">
                        <?php foreach ($supplier_lot as $supplier_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
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
            <div class="flex justify-end gap-4 mt-2 mb-5 mx-3">
                <label for=""></label>
                <button class="
                        w-[120px]
                        h-11
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
                    " type="submit" value="เพิ่ม">บันทึก</button>
                <!-- แก้คำว่า "lass" เป็น "class" -->
            </div>
        </form>
    </div>


    <script>
        document.addEventListener("DOMContentLoaded", function () {
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