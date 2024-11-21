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
    <title>รายละเอียด</title>
    <link rel="stylesheet" href="../../style/detail.css">
</head>

<body>
    <div class="detail-body">

        <h2 class="detail-title">รายละเอียดสมาชิก</h2>


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
                                <input type="text" name="name" value="<?php echo $member['member_name']; ?>">
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
                                <input type="text" name="address" value="<?php echo $member['member_address']; ?>">
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

            <!-- แสดงปุ่ม "บันทึก" เฉพาะในโหมดแก้ไขเท่านั้น -->
            <?php if ($is_update): ?>
                <button class="submit-button" type="submit"
                    style=" border-radius: 6px;
                        width: 120px;
                        height: 40px;
                        padding: 10px;
                        margin-top:10px;
                        /*border: 1px solid rgb(29, 188, 55);*/
                        background-color: #333;
                        text-decoration: none;
                        color: #fff; ">บันทึก </button>

            <?php endif; ?>
        </form>

        <div class="detail-action">
            <a href="/?page=bar_member">ย้อนกลับหน้า List_<?php echo $tabel; ?></a>

            <?php if ($is_update): ?>
                <a href="?page=member_detail&tabel=<?php echo $tabel; ?>&id=<?php echo $member['member_id']; ?>" style="margin-right: 6px;">ย้อนกลับไปที่ Detail</a>
            <?php endif; ?>

            <a href="?page=member_detail&tabel=member&id=<?php echo $member['member_id']; ?>&is_update=1">
                ไปหน้าแก้ไข <?php echo $tabel; ?>
            </a>
        </div>

    </div>
</body>

</html>