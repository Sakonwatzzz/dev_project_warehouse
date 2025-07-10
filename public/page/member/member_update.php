<?php
require_once __DIR__ . '../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

// Validate required GET parameter
if (!isset($_GET['id'])) {
    die("ระบุ ID ไม่ถูกต้อง");
}

// Fetch member types for dropdown
$query = "SELECT member_type_id, member_type_name FROM member_type";
$results = $pdo->query($query);

// Handle POST request for updating member
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

// Retrieve member data
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM member WHERE member_id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$member) {
    echo "ไม่พบข้อมูล";
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>แก้ไขข้อมูลสมาชิก</title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <h2 class="text-gray-800 text-2xl font-bold mt-8 mb-4">กำลังแก้ไขข้อมูลสมาชิก</h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Member Update Section -->
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        ข้อมูลสมาชิก
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($member['member_id']); ?>">

                        <!-- Member Fields -->
                        <?php
                        $fields = [
                            ['label' => 'รหัสสมาชิก', 'name' => 'id', 'value' => $member['member_id'], 'readonly' => true],
                            ['label' => 'ชื่อ-สกุล', 'name' => 'name', 'value' => $member['member_name'], 'id' => 'member-name', 'required' => true],
                            ['label' => 'ที่อยู่', 'name' => 'address', 'value' => $member['member_address']],
                            ['label' => 'เบอร์โทร', 'name' => 'phone', 'value' => $member['member_phone']],
                            ['label' => 'วันที่สมัคร', 'name' => 'registerdate', 'value' => date('Y-m-d', strtotime($member['member_register_date'])), 'readonly' => true, 'type' => 'date'],
                        ];
                        foreach ($fields as $field): ?>
                            <div class="flex items-center gap-4">
                                <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                                <div class="w-7/12 lg:w-8/12">
                                    <?php if (!($field['readonly'] ?? false)): ?>
                                        <input type="<?php echo $field['type'] ?? 'text'; ?>"
                                            name="<?php echo $field['name']; ?>"
                                            id="<?php echo $field['id'] ?? ''; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                            <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>>
                                    <?php else: ?>
                                        <input type="<?php echo $field['type'] ?? 'text'; ?>"
                                            name="<?php echo $field['name']; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="w-full p-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600"
                                            readonly>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Member Type -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=member_type" class="text-blue-600 hover:underline">ประเภทสมาชิก:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <select name="typename" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <?php while ($typeRow = $results->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($typeRow['member_type_id']); ?>"
                                            <?php echo $typeRow['member_type_id'] == $member['member_type_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($typeRow['member_type_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4 mt-6">
                            <a href="/?page=member_detail&tabel=member&id=<?php echo htmlspecialchars($member['member_id']); ?>"
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

    <!-- Focus on name field when editing -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('member-name').focus();
        });
    </script>
</body>

</html>