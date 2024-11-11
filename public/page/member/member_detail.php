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
        $stmt = $pdo->prepare("SELECT member.*,
        member_type.member_type_name
        FROM member 
        LEFT JOIN member_type on member.member_type_id = member.member_type_id
        WHERE member.member_id = ?");
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
    <link rel="stylesheet" href="../../style/detail.css"> <!-- ลิงก์ไปยังไฟล์ CSS เพื่อจัดการสไตล์ของหน้าเว็บ -->
</head>

<body>
    <div class="main-body">
        <!-- แสดงหัวเรื่องของหน้าเว็บโดยนำประเภทข้อมูลที่เรียกจาก URL มาทำเป็นหัวเรื่อง -->
        <h1 class="title">รายละเอียด <?php echo $tabel; ?></h1>

        <!-- ฟอร์มที่ใช้ในการส่งข้อมูลสำหรับอัพเดตรายการสมาชิก โดยจะส่งไปยังไฟล์ `member_update.php` -->
        <form action="member_update.php" method="POST">
            <!-- กำหนดค่า member_id เป็น input ซ่อน (hidden) เพื่อใช้ส่ง ID ของสมาชิกนี้ในฟอร์ม -->
            <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">

            <!-- ตารางแสดงรายละเอียดสมาชิก -->
            <table class="details-table">
                <?php if ($tabel === 'member'): ?>
                    <!-- ตรวจสอบว่าประเภทข้อมูลเป็น 'member' เพื่อแสดงรายละเอียดสมาชิกเท่านั้น -->

                    <tr>
                        <th>รหัสสมาชิก:</th>
                        <!-- แสดงรหัสสมาชิก -->
                        <td>
                            <?php if($is_update):?>
                            <input type="hidden" name="id" value="<?php echo $member['member_id']; ?>">
                            <?php else: ?>
                                <?php echo $member['member_id'];?>
                                <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ชื่อสมาชิก:</th>
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
                                    <option value="<?php echo ($member['member_type_id']); ?>" selected>
                                        <?php echo ($member['member_type_name']); ?>
                                    </option>
                                    <?php while ($typeRow = $member_type_results->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo ($typeRow['member_type_id']); ?>">
                                            <?php echo ($typeRow['member_type_name']); ?>
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
                        <th>วันที่:</th>
                        <td>
                            <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                <input type="date" name="registerdate" value="<?php echo $member['member_register_date']; ?>">
                            <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                <?php echo $member['member_register_date']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endif; ?>
            </table>

            <!-- แสดงปุ่ม "บันทึก" เฉพาะในโหมดแก้ไขเท่านั้น -->
            <?php if ($is_update): ?>
                <button class="submit-button" type="submit">บันทึก </button>

            <?php endif; ?>
        </form>

        <!-- ลิงก์กลับไปยังหน้ารายการสมาชิก, หน้ารายละเอียด และหน้าสำหรับแก้ไข -->
        <a href="/?page=bar_<?php echo $tabel; ?>">กลับไปหน้ารายการสมาชิก <?php echo $tabel; ?></a>
        <a href="member_detail.php?tabel=<?php echo $tabel; ?>&id=<?php echo $member['member_id']; ?>">กลับไปหน้ารายละเอียด
            <?php echo $tabel; ?></a>
        <a
            href="member_detail.php?tabel=<?php echo $tabel; ?>&id=<?php echo $member['member_id']; ?>&is_update=1">ไปหน้าแก้ไข
            <?php echo $tabel; ?></a>
    </div>
</body>

</html>