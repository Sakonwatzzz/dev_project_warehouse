<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure the POST keys exist before using them
    if (isset($_POST['lot_product_id'], $_POST['lot_id'], $_POST['lot_product_qty'], $_POST['lot_product_remain'], $_POST['lot_product_capital'], $_POST['lot_product_date'], $_POST['lot_product_status'], $_POST['product_id'])) {
        $lot_product_id = $_POST['lot_product_id'];
        $lot_id = $_POST['lot_id'];
        $lot_product_qty = $_POST['lot_product_qty'];
        $lot_product_remain = $_POST['lot_product_remain'];
        $lot_product_capital = $_POST['lot_product_capital'];
        $lot_product_date = $_POST['lot_product_date'];
        $lot_product_status = $_POST['lot_product_status'];
        $product_id = $_POST['product_id'];

        // Prepare the SQL statement with the correct parameters
        $stmt = $pdo->prepare("
            UPDATE lot_product 
            SET lot_product_qty = ?, lot_product_remain = ?, lot_product_capital = ?, 
                lot_product_date = ?, lot_product_status = ?, product_id = ? 
            WHERE lot_product_id = ?
        ");
        // Execute the statement with the correct values
        $stmt->execute([$lot_product_qty, $lot_product_remain, $lot_product_capital, $lot_product_date, $lot_product_status, $product_id, $lot_product_id]);

        // Redirect to the list page with a success parameter
        header("Location: /?page=lot_productdetail&tabel=lot_productt&id=$lot_product_id");
        exit;
    } else {
        echo "Missing required POST data.";
    }
}

// Fetch the list of lot IDs
$stmt = $pdo->prepare("SELECT * FROM lot");
$stmt->execute();
$result_lot_id = $stmt;

// Fetch the details of the lot_product based on the ID in the GET parameter
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM lot_product WHERE lot_product_id = ?");
$stmt->execute([$id]);
$lot_product = $stmt->fetch();

// Fetch the product details
$stmt_product = $pdo->prepare("SELECT * FROM product WHERE product_id = ?");
$stmt_product->execute([$lot_product['product_id']]);
$result_product_id = $stmt_product->fetch();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="../../output.css" rel="stylesheet">
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
    <div class="font-[Tahoma] mx-[20rem] mt-11 bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
        <h1 class="bg-[#0046AA] text-white text-2xl font-bold p-5">แก้ไขข้อมูลการรับรายการสินค้า</h1>

        <form class="p-5 space-y-2" action="" method="POST">
            <div class="flex items-center">
                <label class="w-[110px] text-base mr-4" for="lot_product_id">รหัสรับรายการรับสินค้า:</label>
                <input type="id" name="lot_product_id" value="<?php echo ($lot_product['lot_product_id']) ?>" readonly>
            </div>
            <?php if ($result_lot_id->rowCount() > 0) { ?>
                <div class="flex items-center">
                    <label class="w-[110px] text-base mr-4" for="lot_id">รหัสล็อตสินค้า:</label>
                    <select name="lot_id" id="lot_id" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                        <?php while ($lot_id_update = $result_lot_id->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo ($lot_id_update['lot_id']); ?>"
                                <?php echo ($lot_id_update['lot_id'] == $lot_product['lot_id']) ? 'selected' : ''; ?>>
                                <?php echo ($lot_id_update['lot_id']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } else {
                echo "No lot_id available.";
            } ?>

            <div class="flex items-center">
                <label class="w-[110px] text-base mr-4" for="lot_product_qty">จำนวนสินค้าเข้า:</label>
                <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="number" name="lot_product_qty" value="<?php echo ($lot_product['lot_product_qty']) ?>" required>
            </div>
            <div class="flex items-center">
                <label class="w-[110px] text-base mr-4" for="lot_product_remain">จำนวนสินค้าคงเหลือ:</label>
                <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="number" name="lot_product_remain" value="<?php echo ($lot_product['lot_product_remain']) ?>" required>
            </div>

            <div class="flex items-center">
                <label class="w-[110px] text-base mr-4" for="lot_product_capital">ราคาต้นทุน:</label>
                <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="number" name="lot_product_capital" value="<?php echo ($lot_product['lot_product_capital']) ?>" required>
            </div>

            <div class="flex items-center">
                <label class="w-[110px] text-base mr-4" for="lot_product_date">วันที่รับสินค้า</label>
                <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="date" id="lot_product_date" name="lot_product_date" value="<?= date('Y-m-d') ?>" readonly>
            </div>

            <div class="flex items-center">
                <label class="w-[110px] text-base mr-4" for="lot_product_status">สถานะสินค้า:</label>
                <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="lot_product_status" value="<?php echo ($lot_product['lot_product_status']) ?>" required>
            </div>
            <div class="flex items-center">
                <label class="w-[110px] text-base mr-4" for="product_id">รหัสสินค้า:</label>
                <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="number" name="product_id" value="<?php echo ($lot_product['product_id']) ?>" required>
            </div>

            <div class="flex justify-end mt-5 gap-2">
                <a href="/?page=lot_productlist" class="bg-[#e1e1e1] text-black border-2 border-[#A0A0A0] border-t-white border-l-white px-4 py-1 rounded shadow-md hover:bg-[#F1F1F1] active:border-[#A0A0A0] active:border-b-white active:border-r-white active:shadow-inner">ยกเลิก</a>
                <button class="bg-[#e1e1e1] text-black border-2 border-[#A0A0A0] border-t-white border-l-white px-4 py-1 rounded shadow-md hover:bg-[#F1F1F1] active:border-[#A0A0A0] active:border-b-white active:border-r-white active:shadow-inner" type="submit">บันทึก</button>
            </div>
        </form>
    </div>
</body>

</html>