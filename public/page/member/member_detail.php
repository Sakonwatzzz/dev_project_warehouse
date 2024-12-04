<?php
require_once __DIR__ . '../../../../config/database_server.php';

if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $member_id = $_POST['member_id'];
    header("Location: member_detail.php?tabel=member&id=" . $member_id);
    exit();
}

$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false;

switch ($tabel) {
    case 'member':
        $stmt = $pdo->prepare("
    SELECT member.*, 
    member_type.member_type_name
    FROM member
    LEFT JOIN member_type ON member.member_type_id = member_type.member_type_id
    WHERE member.member_id = ?
");

        break;
    default:
        die("ระบุประเภทไม่ถูกต้อง");
}
$stmt->execute([$id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$member) {
    echo "ไม่พบข้อมูล";
    exit();
}

// ดึงข้อมูลประเภทสมาชิก
$queryTypes = "SELECT member_type_id, member_type_name FROM member_type";
$member_type_results = $pdo->query($queryTypes);

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/detail.css">
    <link href="../../output.css" rel="stylesheet">
    <title>รายละเอียด</title>
</head>

<body>
    <div class="detail-body  xl:mx-[20rem]">

        <div class="bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
            <!-- เปลี่ยนสีหัวข้อเมื่ออยู่ในโหมดแก้ไข -->
            <h2 class="detail-title bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700<?php echo $is_update ? '' : ''; ?> text-white text-lg px-3 py-2 border border-blue-700 shadow">
                <?php echo $is_update ? 'กำลังแก้ไขสมาชิก' : 'รายละเอียดสมาชิก'; ?>
            </h2>

            <form class="detail-form" action="../../page/member/member_update.php" method="POST">

                <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">

                <!-- ตารางแสดงรายละเอียดสมาชิก -->
                <table class="detail-table">
                    <?php if ($tabel === 'member'): ?>
                        <!-- ตรวจสอบว่าประเภทข้อมูลเป็น 'member' เพื่อแสดงรายละเอียดสมาชิกเท่านั้น -->

                        <tr>
                            <th>รหัสสมาชิก:</th>
                            <!-- แสดงรหัสสมาชิก -->
                            <td>
                                <?php if ($is_update): ?>
                                    <input type="hidden" name="id" value="<?php echo $member['member_id']; ?>">
                                <?php else: ?>
                                    <?php echo $member['member_id']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ชื่อสกุลสมาชิก:</th>
                            <td>
                                <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                    <input type="text" name="name" id="member-name" class="w-full border rounded px-2 focus:outline-yellow-500" value="<?php echo $member['member_name']; ?>">
                                <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                    <?php echo $member['member_name']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ประเภทสมาชิก:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <select name="typename">
                                        <?php while ($typeRow = $member_type_results->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $typeRow['member_type_id']; ?>"
                                                <?php echo ($typeRow['member_type_id'] == $member['member_type_id']) ? 'selected' : ''; ?>>
                                                <?php echo $typeRow['member_type_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <?php else: ?>
                                    <?php echo ($member['member_type_name']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ที่อยู่สมาชิก:</th>
                            <td>
                                <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                    <input type="text" name="address" class="w-full border rounded px-2 focus:outline-yellow-500" value="<?php echo $member['member_address']; ?>">
                                <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                    <?php echo $member['member_address']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th>เบอร์สมาชิก:</th>
                            <td>
                                <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                    <input type="text" name="phone" value="<?php echo $member['member_phone']; ?>">
                                <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                    <?php echo $member['member_phone']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th>วันที่สมัครเป็นสมาชิก:</th>
                            <td>
                                <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                    <input type="date" name="registerdate" value="<?php echo date('Y-m-d'); ?>" readonly>
                                <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                    <?php echo $member['member_register_date']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endif; ?>
                </table>

                <!-- ปุ่มต่างๆ -->
                <div class="detail-action flex gap-2  justify-end<?php echo $is_update ? '' : ''; ?> mx-3 mb-4">
                    <?php if ($is_update): ?>
                        <div class="flex gap-2 mt-4 justify-end">
                            <a href="/?page=member_detail&tabel=member&id=<?php echo $member['member_id']; ?>"
                                class="w-full p-2 px-5 text-center bg-red-500 text-white border rounded-lg shadow-lg hover:bg-red-600">
                                ยกเลิก
                            </a>
                            <button type="submit"
                                class="w-full p-2 px-5 bg-green-500 text-white border rounded-lg shadow-lg hover:bg-green-600">
                                บันทึก
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="/?page=member_list"
                            class="mt-4 p-2 px-6 bg-gray-700 text-white border rounded-lg shadow-lg mr-2 hover:bg-gray-800">
                            ย้อนกลับ
                        </a>
                        <a href="?page=member_detail&tabel=member&id=<?php echo $member['member_id']; ?>&is_update=1"
                            class="mt-4 p-2 px-6 bg-blue-700 text-white border rounded-lg shadow-lg hover:bg-blue-800">
                            ต้องการแก้ไข
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php if ($is_update): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('member-name').focus();
            });
        </script>
    <?php endif; ?>
</body>

</html>