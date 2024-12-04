<?php
require_once __DIR__ . '/../../../config/database_server.php';

$supplier_update = fetchAll($pdo, table: 'supplier');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare("
    UPDATE supplier 
    SET supplier_name = ?, supplier_address = ?, supplier_phone = ?, supplier_detail = ?
    WHERE supplier_id = ?
");
    $stmt->execute([$name, $address, $phone, $detail, $id]);

    header("Location: /?page=bar_supplier&success=1");
    exit;
}
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM supplier WHERE supplier_id = ?");
$stmt->execute([$id]);
$supplier_update  = $stmt->fetch();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/update.css">
    <link href="../../output.css" rel="stylesheet">
    <title>suppliser update</title>

</head>

<body>
    <div class="bg-[#F0F0F0] font-[Tahoma]">
        <div class="mx-[40rem] mt-8 bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
            <h2 class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-2 border border-blue-700 shadow  font-bold p-5">แก้ไข ข้อมูลผู้ส่งมอบ</h2>

            <form class="p-5  space-y-2" action="../../page/supplier/supplier_update.php" method="POST">
                <input type="hidden" name="id" value="<?php echo ($supplier_update['supplier_id']) ?>">

                <div class="flex items-center">
                    <label for="name" for="barcode" class="w-[150px] text-base mr-4">ชื่อผู้ส่งมอบ</label>
                    <input type="text" name="name" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($supplier_update['supplier_name']) ?>" required>
                </div>


                <div class="flex items-center">
                    <label for="address" for="barcode" class="w-[150px] text-base mr-4">ที่อยู่ผู้ส่งมอบ</label>
                    <input type="text" name="address" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($supplier_update['supplier_address']) ?>" required>
                </div>

                <div class="flex items-center">
                    <label for="phone" for="barcode" class="w-[150px] text-base mr-4">เบอร์โทรศัพท์ผู้ส่งมอบ</label>
                    <input type="text" name="phone" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($supplier_update['supplier_phone']) ?>" required>
                </div>


                <div class="flex items-center">
                    <label for="detail" for="barcode" class="w-[150px] text-base mr-4">รายละเอียดผู้ส่งมอบ</label>
                    <input type="text" name="detail" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($supplier_update['supplier_detail']) ?>" required>
                </div>

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
                        <a href="/?page=bar_supplier">ยกเลิก</a>
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