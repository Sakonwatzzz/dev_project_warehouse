<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Validate required GET parameters
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

// Handle POST request for updating personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $personnel_id = $_POST['personnel_id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare("
        UPDATE personnel 
        SET personnel_name = ?, personnel_address = ?, personnel_phone = ?, 
            personnel_username = ?, personnel_password = ?, personnel_type_id = ?
        WHERE personnel_id = ?
    ");
    $stmt->execute([$name, $address, $phone, $username, $password, $typename, $personnel_id]);

    header("Location: /?page=personnel_detail&tabel=personnel&id=" . $personnel_id);
    exit();
}

// Retrieve GET parameters
$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? (bool)$_GET['is_update'] : false;

// Prepare SQL query based on table type
switch ($tabel) {
    case 'personnel':
        $stmt = $pdo->prepare("
            SELECT personnel.*, personnel_type.personnel_type_name
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

// Fetch personnel types for dropdown
$queryTypes = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$personnel_type_results = $pdo->query($queryTypes);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title><?php echo $is_update ? 'แก้ไขข้อมูลบุคลากร' : 'รายละเอียดบุคลากร'; ?></title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <h2 class="text-gray-800 text-2xl font-bold mt-8 mb-4">
            <?php echo $is_update ? 'กำลังแก้ไขข้อมูลบุคลากร' : 'รายละเอียดบุคลากร'; ?>
        </h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Personnel Details Section -->
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        ข้อมูลบุคลากร
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST">
                        <input type="hidden" name="personnel_id" value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">

                        <!-- Personnel Fields -->
                        <?php
                        $fields = [
                            ['label' => 'รหัสบุคลากร', 'name' => 'personnel_id', 'value' => $personnel['personnel_id'], 'readonly' => true],
                            ['label' => 'ชื่อ-สกุล', 'name' => 'name', 'value' => $personnel['personnel_name'], 'id' => 'personnel-name', 'required' => true],
                            ['label' => 'ที่อยู่', 'name' => 'address', 'value' => $personnel['personnel_address']],
                            ['label' => 'เบอร์โทร', 'name' => 'phone', 'value' => $personnel['personnel_phone']],
                            ['label' => 'ชื่อผู้ใช้', 'name' => 'username', 'value' => $personnel['personnel_username']],
                            ['label' => 'รหัสผ่าน', 'name' => 'password', 'value' => $personnel['personnel_password']],
                        ];
                        foreach ($fields as $field): ?>
                            <div class="flex items-center gap-4">
                                <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                                <div class="w-7/12 lg:w-8/12">
                                    <?php if ($is_update && !($field['readonly'] ?? false)): ?>
                                        <input type="<?php echo $field['type'] ?? 'text'; ?>"
                                            name="<?php echo $field['name']; ?>"
                                            id="<?php echo $field['id'] ?? ''; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                            <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>>
                                    <?php elseif ($is_update && ($field['readonly'] ?? false)): ?>
                                        <input type="<?php echo $field['type'] ?? 'text'; ?>"
                                            name="<?php echo $field['name']; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="w-full p-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600"
                                            readonly>
                                    <?php else: ?>
                                        <span class="block w-full p-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600"><?php echo htmlspecialchars($field['value']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Personnel Type -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=personnel_type" class="text-blue-600 hover:underline">ประเภทบุคลากร:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <?php if ($is_update): ?>
                                    <select name="typename" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                        <?php while ($typeRow = $personnel_type_results->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo htmlspecialchars($typeRow['personnel_type_id']); ?>"
                                                <?php echo $typeRow['personnel_type_id'] == $personnel['personnel_type_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($typeRow['personnel_type_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <?php else: ?>
                                    <span class="block w-full p-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600"><?php echo htmlspecialchars($personnel['personnel_type_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4 mt-6">
                            <?php if ($is_update): ?>
                                <a href="/?page=personnel_detail&tabel=personnel&id=<?php echo htmlspecialchars($personnel['personnel_id']); ?>"
                                    class="mt-4 bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 transition duration-200">
                                    ยกเลิก
                                </a>
                                <button type="submit"
                                    class="mt-4 bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 transition duration-200">
                                    บันทึก
                                </button>
                            <?php else: ?>
                                <a href="/?page=personnel_list"
                                    class="mt-4 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                                    ย้อนกลับ
                                </a>
                                <a href="?page=personnel_detail&tabel=personnel&id=<?php echo htmlspecialchars($personnel['personnel_id']); ?>&is_update=1"
                                    class="mt-4 bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                                    ต้องการแก้ไข
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Focus on name field when editing -->
    <?php if ($is_update): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('personnel-name').focus();
            });
        </script>
    <?php endif; ?>
</body>

</html>