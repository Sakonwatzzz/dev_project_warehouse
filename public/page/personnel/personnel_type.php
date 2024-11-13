<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare('INSERT INTO personnel_type (personnel_type_name) 
                           VALUES (?)');
    $stmt->execute([$typename]);

    header('Location: /index.php?page=personnel_type');
    exit;
}

// Fetch personnel types for dropdown
$query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$stmt = $pdo->prepare($query);
$stmt->execute();

$personnelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM personnel_type WHERE personnel_type_id = ?");
    $stmt->execute([$id]);

    header("Location: /index.php?page=personnel_type");
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="../../style/personnel_add.css">
    <title>Document</title>
</head>

<body>
    <table class="data-table">
        <div class="add-box">
            <h2 class="sub-title">เพิ่มประเภทบุคลากร</h2>
            <tr>
                <form class="form-container" action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
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
            </tr>
        </div>

        <thead>
            <tr>
                <th>รหัสประเภทบุคลากร</th>
                <th>ชื่อประเภทบุคลากร</th>
                <th>การจัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($personnelTypes as $personnel_type_list): ?>
                <tr>
                    <td><?php echo ($personnel_type_list['personnel_type_id']) ?></td>
                    <td><?php echo ($personnel_type_list['personnel_type_name']) ?></td>
                    <td>
                        <a href="page/personnel/personnel_type_update.php?id=<?php echo ($personnel_type_list['personnel_type_id']) ?>">แก้ไข</a> |
                        <a href="?id=<?php echo ($personnel_type_list['personnel_type_id']) ?>"
                            onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>