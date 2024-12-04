<?php
require_once __DIR__ . '../../../../config/database_server.php';

$queryTypes = "SELECT product_type_id, product_type_name FROM product_type";
$typeResults = $pdo->query($queryTypes);

$querybrandTypes = "SELECT product_brand_id, product_brand_name FROM product_brand";
$brandResults = $pdo->query($querybrandTypes);

$product_update = fetchAll($pdo, table: 'supplier');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['product_id'];
    $barcode = $_POST['barcode'];
    $name = $_POST['name'];
    $price1 = $_POST['price_1'];
    $price2 = $_POST['price_2'];
    $price3 = $_POST['price_3'];
    $price4 = $_POST['price_4'];
    $capital = $_POST['capital'];
    $type = $_POST['typename'];
    $brand = $_POST['typebrandname'];

    $stmt = $pdo->prepare("
        UPDATE product 
        SET product_barcode = ?, product_name = ?, product_price1 = ?, product_price2 = ?, 
            product_price3 = ?, product_price4 = ?, product_capital = ?, product_type_id = ?, 
            product_brand_id = ? 
        WHERE product_id = ?
    ");
    $stmt->execute([$barcode, $name, $price1, $price2, $price3, $price4, $capital, $type, $brand, $id]);

    header("Location: /?page=product_detail&tabel=product&id=$id");
    exit;
}


$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM product WHERE product_id = ?");
$stmt->execute([$id]);
$product_update = $stmt->fetch();



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/update.css">
    <link href="../../output.css" rel="stylesheet">
    <style>
        .title {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            align-items: center;
            justify-content: center;
            gap: 5px;

        }

        .count {
            display: flex;
            gap: 1.5rem;
            align-items: center;

        }

        .listcount {
            font-size: 16px;
            text-align: right;
            color: #000;
            padding: 4px 10px 4px 10px;
            border: 1px solid gray;
            border-radius: 5px;
            background-color: #fff;
            display: inline-block;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            width: 70px;
        }

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
            background-color: #ffdfa0;
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

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 16px;
            text-decoration: none;
            background-color: #f1f1f1;
            color: black;
            border-radius: 5px;
            margin: 0 4px;
        }

        .pagination a.active {
            background-color: #5b97b2;
            ;
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;

        }
    </style>
    <title>product_update</title>
</head>

<body>
    <div class="bg-[#F0F0F0] font-[Tahoma]">
        <div class="mx-[40rem] mt-8 bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
            <h2 class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-2 border border-blue-700 shadow  font-bold p-5">แก้ไขข้อมูลสินค้า</h2>

            <form class="p-5  space-y-2" action="../../page/product/product_update.php" method="POST">
                <input type="hidden" name="product_id" value="<?php echo ($product_update['product_id']) ?>">


                <div class="flex items-center">
                    <label for="barcode" class="w-[150px] text-base mr-4">บาร์โค้ดสินค้า:</label>
                    <input type="text" name="barcode" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($product_update['product_barcode']) ?>">
                </div>

                <div class="flex items-center">
                    <label for="name" class="w-[150px] text-base mr-4">ชื่อสินค้า:</label>
                    <input type="text" name="name"  class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($product_update['product_name']) ?>">

                </div>
                <div class="flex items-center">
                    <label for="price_1" class="w-[150px] text-base mr-4">ราคาที่1:</label>
                    <input type="text" name="price_1" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($product_update['product_price1']) ?>">
                </div>
                <div class="flex items-center">
                    <label for="price_2" class="w-[150px] text-base mr-4">ราคาที่2:</label>
                    <input type="text" name="price_2" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($product_update['product_price2']) ?>">

                </div>
                <div class="flex items-center">
                    <label for="price_3" class="w-[150px] text-base mr-4">ราคาที่3:</label>
                    <input type="text" name="price_3" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($product_update['product_price3']) ?>">

                </div>
                <div class="flex items-center">
                    <label for="price_4" class="w-[150px] text-base mr-4">ราคาที่4:</label>
                    <input type="text" name="price_4" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($product_update['product_price4']) ?>">

                </div>
                <div class="flex items-center">
                    <label for="capital" class="w-[150px] text-base mr-4">ราคาทุน:</label>
                    <input type="text" name="capital" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($product_update['product_capital']) ?>">
                </div>

                <?php if ($typeResults->rowCount() > 0) { ?>
                    <div class="flex items-center">
                        <label for="typename" class="w-[150px] text-base mr-4">ประเภทสินค้า:</label>
                        <select name="typename" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                            <?php while ($product_update = $typeResults->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo ($product_update['product_type_id']); ?>" <?= ($product_update['product_type_id'] == $product_update['product_type_id']) ? 'selected' : ''; ?>>
                                    <?php echo ($product_update['product_type_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <p>ไม่พบข้อมูลประเภทสินค้า</p>
                <?php } ?>


                <?php if ($brandResults->rowCount() > 0) { ?>
                    <div class="flex items-center">
                        <label for="typebrandname" class="w-[150px] text-base mr-4">ประเภทแบรนด์สินค้า:</label>
                        <select name="typebrandname" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                            <?php while ($product_update = $brandResults->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo ($product_update['product_brand_id']); ?>" <?= ($product_update['product_brand_id'] == $product_update['product_brand_id']) ? 'selected' : ''; ?>>
                                    <?php echo ($product_update['product_brand_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>

                <div class="flex gap-1 justify-end">
                    <button class=" bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 
                            border-t-white 
                            border-l-white 
                            px-4
                            py-1
                            rounded 
                                            shadow-md 
                            hover:bg-[#FFEBEE] 
                            active:border-[#EF9A9A] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner">
                        <a href="/?page=bar_product">ยกเลิก</a>
                    </button>

                    <button class="
                            bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg border border-green-700 
                            border-t-white 
                            border-l-white 
            
                            px-4
                            py-1
                            rounded 
                            shadow-md 
                            hover:bg-[#E8F5E9] 
                            active:border-[#A5D6A7] 
                            active:border-b-white 
                            active:border-r-white 
                                active:shadow-inner
                            " type="submit" value="เพิ่ม">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
   

</body>

</html>