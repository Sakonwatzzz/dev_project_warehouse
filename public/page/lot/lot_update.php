<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_id = $_POST['lot_id'];
    $lot_detail = $_POST['lot_detail'];
    $lot_date = $_POST['lot_date'];
    $lot_status_id = $_POST['lot_status_id'];
    $warehouse_id = $_POST['warehouse_id'];
    $store_id = $_POST['store_id'];
    $supplier_id = $_POST['supplier_id'];


    $stmt = $pdo->prepare("
        UPDATE lot 
        SET lot_id = ?, lot_detail = ?, lot_date = ?, lot_status_id = ?, 
            warehouse_id = ?, store_id = ?, supplier_id 
        WHERE lot_id = ?
    ");
    $stmt->execute([$lot_id, $lot_detail, $lot_date, $lot_status_id, $warehouse_id, $store_id, $supplier_id]);

    // รีไดเรกต์ไปที่หน้ารายการหลังอัปเดตสำเร็จ
    header("Location: /?page=lot_detail&tabel=lot&id=$lot_id");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM lot WHERE lot_id = ?");
$stmt->execute([$id]);
$lot = $stmt->fetch();

// ดึงชื่อคลังมาแสดงผล
$query = "SELECT lot_status_id, lot_status_name FROM lot_status";
$stmt = $pdo->prepare($query);
$stmt->execute();
$status_lot = $stmt;
// ดึงชื่อคลังมาแสดงผล
$query = "SELECT warehouse_id, warehouse_name FROM warehouse";
$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouse_lot = $stmt;
// ดึงชื่อผู่ส่งมอบมาแสดงผล
$query = "SELECT supplier_id, supplier_name FROM supplier";
$stmt = $pdo->prepare($query);
$stmt->execute();
$supplier_lot = $stmt;

$query = "SELECT store_id, store_name FROM store";
$stmt = $pdo->prepare($query);
$stmt->execute();
$store_lot = $stmt;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../../style/personnel_insert.css">

    <style>
        .edit-box {
            margin: 3rem 10rem 4rem 8rem;
        }

        h1 {
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="edit-box">
        <h1 class="title">แก้ไขข้อมูลการรับรายการสินค้า</h1>

        <form class="form-container" action="../../page/lot/lot_update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo ($lot['lot_id']) ?>">
            <div class="box">
                <label for="lot_detail">รายละเอียดล็อต:</label>
                <input type="text" name="lot_detail" value="<?php echo ($lot['lot_detail']) ?>">

            </div>
            <div class="box">
                <label for="lot_date">วันที่รับสินค้า</label>
                <input type="date" id="lot_date" name="lot_date" value="<?= date('Y-m-d') ?>" readonly>
            </div>
            <?php
            if ($status_lot->rowCount() > 0) {
                ?>
                <div class="box">
                    <label for="lot_status_id">สถานะล็อต:</label>
                    <select name="lot_status_id" id="lot_status_id">
                        <?php while ($status_lot_update = $status_lot->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($status_lot_update['lot_status_id']); ?>" selected>
                                <?php echo ($status_lot_update['lot_status_name']); ?>
                            </option>
                        <?php } ?>
                    </select><br>
                </div>
                <?php
            } else {
                echo "No result_lot_status available.";
            }
            ?>
            <?php
            if ($warehouse_lot->rowCount() > 0) {
                ?>
                <div class="box">
                    <label for="warehouse_id">คลังสินค้า:</label>
                    <select name="warehouse_id" id="warehouse_id">
                        <?php while ($warehouse_lot_update = $warehouse_lot->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($warehouse_lot_update['warehouse_id']); ?>" selected>
                                <?php echo ($warehouse_lot_update['warehouse_name']); ?>
                            </option>
                        <?php } ?>
                    </select><br>
                </div>
                <?php
            } else {
                echo "No warehouse_lot available.";
            }
            ?>
            <?php
            if ($store_lot->rowCount() > 0) {
                ?>
                <div class="box">
                    <label for="store_id">ร้านค้า:</label>
                    <select name="store_id" id="store_id">
                        <?php while ($store_lot_update = $store_lot->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($store_lot_update['store_id']); ?>" selected>
                                <?php echo ($store_lot_update['store_name']); ?>
                            </option>
                        <?php } ?>

                    </select><br>
                </div>
                <?php
            } else {
                echo "No store_lot available.";
            }
            ?>
            <?php
            if ($supplier_lot->rowCount() > 0) {
                ?>
                <div class="box">
                    <label for="supplier_id">ผู้ส่งมอบ:</label>
                    <select name="supplier_id" id="supplier_id">
                        <?php while ($supplier_lot_update = $supplier_lot->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($supplier_lot_update['supplier_id']); ?>" selected>
                                <?php echo ($supplier_lot_update['supplier_name']); ?>
                            </option>
                        </select><br>
                    <?php } ?>
                </div>
                <?php
            } else {
                echo "No supplier_lot available.";
            }
            ?>

            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
    </div>


</body>

</html>