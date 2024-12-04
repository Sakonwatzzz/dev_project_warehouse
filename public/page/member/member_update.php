<?php
require_once __DIR__ . '../../../../config/database_server.php';

$query = "SELECT member_type_id, member_type_name FROM member_type";
$results = $pdo->query($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $registerdate = $_POST['registerdate'];
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare("
        UPDATE member 
        SET member_name = ?, member_address = ?, member_phone = ?, member_register_date = ?, member_type_id = ? 
        WHERE member_id = ?
    ");
    $stmt->execute([$name, $address, $phone, $registerdate, $typename, $id]);

    header("Location: /?page=member_detail&tabel=member&id=$id");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM member WHERE member_id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/update.css">
    <link href="../../output.css" rel="stylesheet">
    <title>Document</title>
</head>

<body>
    <div class="bg-[#F0F0F0] font-[Tahoma]">
        <div class="mx-[40rem] mt-8 bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
            <h2 class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-2 border border-blue-700 shadow  font-bold p-5">แก้ไขสมาชิก</h2>

            <form class="p-5  space-y-2" action="../../page/member/member_update.php" method="POST">
                <input type="hidden" name="id" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($member['member_id']) ?>">

                <div class="flex items-center">
                    <label for="name" class="w-[150px] text-base mr-4">ชื่อกุลสมาชิก:</label>
                    <input type="text" name="name" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($member['member_name']) ?>">
                </div>

                <div class="flex items-center">
                    <label for="address" class="w-[150px] text-base mr-4">ที่อยู่สมาชิก:</label>
                    <input type="text" name="address" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($member['member_address']) ?>">
                </div>

                <div class="flex items-center">
                    <label for="phone" class="w-[150px] text-base mr-4">เบอร์โทรศัพท์สมาชิก:</label>
                    <input type="text" name="phone" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo ($member['member_phone']) ?>">
                </div>

                <div class="flex items-center">
                    <label for="registerdate" class="w-[150px] text-base mr-4">วันที่สมัครเป็นสมาชิก:</label>
                    <input type="date" name="registerdate" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" value="<?php echo date('Y-m-d'); ?>" readonly>
                </div>


                <?php if ($results->rowCount() > 0) { ?>
                    <div class="flex items-center">
                        <label for="typename" class="w-[150px] text-base mr-4">ประเภทสมาชิก:</label>
                        <select name="typename" id="typename" class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner">
                            <?php while ($member_update = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo ($member_update['member_type_id']); ?>"
                                    <?php if ($member_update['member_type_id'] == $member['member_type_id']) echo 'selected'; ?>>
                                    <?php echo ($member_update['member_type_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <p>ไม่พบข้อมูลประเภทสมาชิก</p>
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
                        <a href="/?page=bar_member">ยกเลิก</a>
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