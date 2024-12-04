<?php
require_once __DIR__ . '../../../../config/database_server.php';

if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $personnel_id = $_POST['personnel_id'];
    header("Location: personnel_detail.php?tabel=personnel&id=" . $personnel_id);
    exit();
}

$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false;

switch ($tabel) {
    case 'personnel':
        $stmt = $pdo->prepare("
        SELECT personnel.*,
        personnel_type.personnel_type_name
        FROM personnel
        INNER JOIN personnel_type ON personnel.personnel_type_id = personnel_type.personnel_type_id
        WHERE personnel.personnel_id = ?
        ");
        break;
    default:
        die("ระบุประเภทไม่ถูกต้อง");
}

$stmt->execute([$id]);
$personnel = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$personnel) {
    echo "ไม่พบข้อมูล";
    exit();
}

// ดึงข้อมูลประเภทบุคลากร
$queryTypes = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$personnel_type_results = $pdo->query($queryTypes);

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
    <div class="detail-body xl:mx-[20rem]">

        <div class="bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
            <!-- เปลี่ยนสีหัวข้อเมื่ออยู่ในโหมดแก้ไข -->
            <h2 class="text-lg detail-title bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700<?php echo $is_update ? '' : ''; ?> text-white px-3 py-2 border border-blue-700 shadow">
                <?php echo $is_update ? 'กำลังแก้ไขบุคลากร' : 'รายละเอียดบุคลากร'; ?>
            </h2>



            <form class="detail-form" action="../../page/personnel/personnel_update.php" method="POST">

                <input type="hidden" name="personnel_id" value="<?php echo $personnel['personnel_id']; ?>">


                <table class="detail-table">
                    <?php if ($tabel === 'personnel'): ?>


                        <tr>
                            <th>รหัสบุคลากร:</th>

                            <td>
                                <?php if ($is_update): ?>
                                    <input type="hidden" name="id" value="<?php echo $personnel['personnel_id']; ?>">
                                <?php else: ?>
                                    <?php echo $personnel['personnel_id']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ชื่อสกุลบุคลากร:</th>
                            <td>
                                <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                    <input type="text" name="name" value="<?php echo $personnel['personnel_name']; ?>">
                                <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                    <?php echo $personnel['personnel_name']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ประเภทบุคลากร:</th>
                            <td>
                                <?php if ($is_update): ?>
                                    <select name="typename">
                                        <option value="<?php echo ($personnel['personnel_type_id']); ?>" selected>
                                            <?php echo ($personnel['personnel_type_name']); ?>
                                        </option>
                                        <?php while ($typeRow = $personnel_type_results->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo ($typeRow['personnel_type_id']); ?>">
                                                <?php echo ($typeRow['personnel_type_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <?php else: ?>
                                    <?php echo ($personnel['personnel_type_name']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ที่อยู่บุคลากร:</th>
                            <td>
                                <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                    <input type="text" name="address" value="<?php echo $personnel['personnel_address']; ?>">
                                <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                    <?php echo $personnel['personnel_address']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th>เบอร์บุคลากร:</th>
                            <td>
                                <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                    <input type="text" name="phone" value="<?php echo $personnel['personnel_phone']; ?>">
                                <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                    <?php echo $personnel['personnel_phone']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th>ชื่อผู้ใช้บุคลากร:</th>
                            <td>
                                <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                    <input type="text" name="username" value="<?php echo $personnel['personnel_username']; ?>">
                                <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                    <?php echo $personnel['personnel_username']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>รหัสผ่าน:</th>
                            <td>
                                <?php if ($is_update): ?> <!-- ถ้าเป็นโหมดแก้ไข ($is_update = true) ให้แสดง input text -->
                                    <input type="text" name="password" value="<?php echo $personnel['personnel_password']; ?>">
                                <?php else: ?> <!-- ถ้าไม่ใช่โหมดแก้ไข ให้แสดงเป็นข้อความปกติ -->
                                    <?php echo $personnel['personnel_password']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endif; ?>
                </table>

                <!-- ปุ่มต่างๆ -->
                <div class="detail-action flex gap-2  justify-end<?php echo $is_update ? '' : ''; ?> mx-3 mb-4">
                    <?php if ($is_update): ?>
                        <div class="flex gap-2 mt-4 justify-end">
                            <a href="/?page=personnel_detail&tabel=personnel&id=<?php echo $personnel['personnel_id']; ?>"
                                class="w-full p-2 px-5 text-center bg-red-500 text-white border rounded-lg shadow-lg hover:bg-red-600">
                                ยกเลิก
                            </a>
                            <button type="submit"
                                class="w-full p-2 px-5 bg-green-500 text-white border rounded-lg shadow-lg hover:bg-green-600">
                                บันทึก
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="/?page=personnel_list"
                            class="mt-4 p-2 px-6 bg-gray-700 text-white border rounded-lg shadow-lg mr-2 hover:bg-gray-800">
                            ย้อนกลับ
                        </a>
                        <a href="?page=personnel_detail&tabel=personnel&id=<?php echo $personnel['personnel_id']; ?>&is_update=1"
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
                document.getElementById('product-name').focus();
            });
        </script>
    <?php endif; ?>
</body>

</html>