<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare('INSERT INTO member_type (member_type_name) VALUES (?)');
    $stmt->execute([$typename]);

    header('Location: /index.php?page=member_type');
    exit;
}

// Fetch member types for dropdown
$query = "SELECT member_type_id, member_type_name FROM member_type";
$stmt = $pdo->prepare($query);
$stmt->execute();
// try {
//     $member_Types = $pdo->query($query);
// } catch (PDOException $e) {
//     echo 'Error: ' . $e->getMessage();
// }

// if (isset($_GET['id'])) {
//     $id = $_GET['id'];
//     $stmt = $pdo->prepare("DELETE FROM member_type WHERE member_type_id = ?");
//     $stmt->execute([$id]);
//     header("Location: /index.php?page=member_type");
//     exit;
// }

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM member_type WHERE member_type_id = ?");
    $stmt->execute([$id]);
    header("Location: /index.php?page=member_type.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <script src="https://cdn.tailwindcss.com"></script>


    <title>Document</title>
</head>

<div?>
    <div class="content-container">
        <div class="form-container">
            <h2 class="sub-title" style="  font-weight: bold">เพิ่มประเภทสมาชิก</h2>
            <table class="data-table" style="background: #fff;">

                <form class="" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
                    <td></td>
                    <td>
                        <input type="text" id="typename" name="typename" placeholder="ชื่อประเภทบุคลากร"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />

                    </td>
                    <td>
                        <button type="submit" class="px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                            เพิ่ม
                        </button>

                    </td>
                </form>

                <thead>
                    <tr>
                        <th>รหัสประเภทสมาชิก</th>
                        <th>ชื่อประเภทสมาชิก</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($member_type_list = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo ($member_type_list['member_type_id']) ?></td>
                            <td><?php echo ($member_type_list['member_type_name']) ?></td>
                            <td>
                                <a href="page/member/member_type_update.php?id=<?php echo ($member_type_list['member_type_id']) ?>">แก้ไข</a>
                                |
                                <a href="?id=<?php echo ($member_type_list['member_type_id']) ?>"
                                    onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </div>
    </body>

</html>