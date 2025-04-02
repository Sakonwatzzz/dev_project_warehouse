<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Handle POST request for inserting a new personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $personnel_name = $_POST['personnel_name'];
    $personnel_type_name = $_POST['personnel_type_name']; // This is actually personnel_type_id
    $personnel_address = $_POST['personnel_address'];
    $personnel_phone = $_POST['personnel_phone'];
    $personnel_username = $_POST['personnel_username'];
    $personnel_password = $_POST['personnel_password'];

    $stmt = $pdo->prepare('
        INSERT INTO personnel (personnel_name, personnel_type_id, personnel_address, personnel_phone, personnel_username, personnel_password) 
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$personnel_name, $personnel_type_name, $personnel_address, $personnel_phone, $personnel_username, $personnel_password]);

    $personnel_id = $pdo->lastInsertId();
    header("Location: /?page=personnel_detail&tabel=personnel&id=$personnel_id");
    exit;
}

// Fetch personnel types for dropdown
$query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$stmt = $pdo->prepare($query);
$stmt->execute();
$personnelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>เพิ่มบุคลากร</title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <h2 class="text-gray-800 text-2xl font-bold mt-8 mb-4">เพิ่มบุคลากรใหม่</h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Personnel Insert Section -->
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        ข้อมูลบุคลากร
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST">
                        <!-- Personnel Fields -->
                        <?php
                        $fields = [
                            ['label' => 'ชื่อ-สกุล', 'name' => 'personnel_name', 'value' => '', 'id' => 'personnel-name', 'required' => true],
                            ['label' => 'ที่อยู่', 'name' => 'personnel_address', 'value' => ''],
                            ['label' => 'เบอร์โทร', 'name' => 'personnel_phone', 'value' => ''],
                            ['label' => 'ชื่อผู้ใช้', 'name' => 'personnel_username', 'value' => ''],
                            ['label' => 'รหัสผ่าน', 'name' => 'personnel_password', 'value' => ''],
                        ];
                        foreach ($fields as $field): ?>
                            <div class="flex items-center gap-4">
                                <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                                <div class="w-7/12 lg:w-8/12">
                                    <input type="<?php echo $field['type'] ?? 'text'; ?>"
                                           name="<?php echo $field['name']; ?>"
                                           id="<?php echo $field['id'] ?? ''; ?>"
                                           value="<?php echo htmlspecialchars($field['value']); ?>"
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                           <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Personnel Type -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=personnel_type" class="text-blue-600 hover:underline">ประเภทบุคลากร:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <?php if (count($personnelTypes) > 0): ?>
                                    <select name="personnel_type_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                        <?php foreach ($personnelTypes as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type['personnel_type_id']); ?>">
                                                <?php echo htmlspecialchars($type['personnel_type_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <span class="text-gray-600">ไม่พบข้อมูลประเภทบุคลากร</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4 mt-6">
                            <a href="/?page=personnel_list"
                               class="mt-4 bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 transition duration-200">
                                ยกเลิก
                            </a>
                            <button type="submit"
                                    class="mt-4 bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 transition duration-200">
                                บันทึก
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Focus on name field when page loads -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('personnel-name').focus();
        });
    </script>
</body>

</html>