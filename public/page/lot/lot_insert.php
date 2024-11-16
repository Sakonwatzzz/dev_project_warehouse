<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_detail = $_POST['lot_detail'];
    $lot_date = $_POST['lot_date'];
    $lot_status_id = $_POST['lot_status_id'];
    $warehouse_id = $_POST['warehouse_id'];
    $store_id = $_POST['store_id'];
    $supplier_id = $_POST['supplier_id'];

    $stmt = $pdo->prepare('INSERT INTO personnel (lot_detail,  lot_date, lot_status_id, warehouse_id, store_id, supplier_id) 
                           VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$lot_detail, $lot_date, $lot_status_id, $personnel_phone, $warehouse_id, $store_id, $supplier_id]);


    $personnel_id = $pdo->lastInsertId();
    header("Location: personnel/personnel_detail.php?tabel=personnel&id=" . $personnel_id);
    exit;
}

// ดึงชื่อคลังมาแสดงผล
$query = "SELECT warehouse_id, warehouse_name FROM warehouse";
$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouseTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ดึงชื่อผู่ส่งมอบมาแสดงผล
$query = "SELECT supplier_id, supplier_name FROM supplier";
$stmt = $pdo->prepare($query);
$stmt->execute();
$supplierTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_end_flush();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_insert.css">
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

    <div class="add-box">

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">นำเข้าสำเร็จ!</div>
        </div>


        <h2 class="sub-title">นำสินค้าเข้า</h2>
        <form class="form-container" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
            <div class="box">
                <label for="lot_detail">รายละเอียด:</label>
                <input type="text" id="lot_detail" name="lot_detail">
            </div>
            <div class="box">
                <label for="lot_date">วันที่รับเข้า:</label>
                <input type="date" id="lot_date" name="lot_date">
            </div>
            <div class="box">
                <label for="lot_stutas_id">รหัสสถานะ:</label>
                <input type="text" id="lot_stutas_id" name="lot_stutas_id">
            </div>

            <!-- loop -->
            <?php
            if (count($warehouseTypes) > 0) {
                ?>
                <div class="box">
                    <label for="warehouse_type_name">คลังสินค้า:</label>
                    <select name="warehouse_type_name" id="warehouse_type_name">
                        <?php foreach ($warehouseTypes as $warehouse_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
                            <option value="<?php echo ($warehouse_insert['warehouse_type_id']); ?>">
                                <?php echo ($warehouse_insert['warehouse_type_name']); ?>
                            </option>
                        <?php } ?>
                    </select><br>
                </div>

                <?php
            } else {
                echo "No warehouse types available.";
            }
            ?>
            <?php
            if (count($storeTypes) > 0) {
                ?>
                <div class="box">
                    <label for="warehouse_type_name">ร้าน:</label>
                    <select name="warehouse_type_name" id="warehouse_type_name">
                        <?php foreach ($storeTypes as $warehouse_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
                            <option value="<?php echo ($warehouse_insert['warehouse_type_id']); ?>">
                                <?php echo ($warehouse_insert['warehouse_type_name']); ?>
                            </option>
                        <?php } ?>
                    </select><br>
                </div>

                <?php
            } else {
                echo "No warehouse types available.";
            }
            ?>
            <?php
            if (count($warehouseTypes) > 0) {
                ?>
                <div class="box">
                    <label for="store_type_name">ผู้ส่งมอบ:</label>
                    <select name="store_type_name" id="store_type_name">
                        <?php foreach ($storeTypes as $store_insert) { ?> <!-- เปลี่ยนเป็น foreach -->
                            <option value="<?php echo ($store_insert['store_type_id']); ?>">
                                <?php echo ($store_insert['store_type_name']); ?>
                            </option>
                        <?php } ?>
                    </select><br>
                </div>

                <?php
            } else {
                echo "No warehouse types available.";
            }
            ?>
            <!--  -->
            <div class="box">
                <label for="warehouse_address">ที่อยู่บุคลากร:</label>
                <input type="text" id="personnel_address" name="personnel_address">
            </div>
            <div class="box">
                <label for="personnel_phone">เบอร์โทรศัพท์:</label>
                <input type="text" id="personnel_phone" name="personnel_phone">
            </div>

            <div class="box">
                <label for="personnel_username">ชื่อผู้ใช้:</label>
                <input type="text" id="personnel_username" name="personnel_username">
            </div>

            <div class="box">
                <label for="personnel_password">รหัสผ่าน:</label>
                <input type="password" id="personnel_password" name="personnel_password">
            </div>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
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