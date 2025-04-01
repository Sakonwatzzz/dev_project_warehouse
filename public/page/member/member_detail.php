<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Validate required GET parameters
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    die("ระบุประเภทหรือ ID ไม่ถูกต้อง");
}

// Handle POST request for redirection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'];
    header("Location: member_detail.php?tabel=member&id=" . $member_id);
    exit();
}

// Retrieve GET parameters
$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? (bool)$_GET['is_update'] : false;

// Prepare SQL query based on table type
switch ($tabel) {
    case 'member':
        $stmt = $pdo->prepare("
            SELECT member.*, member_type.member_type_name
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

// Fetch member types for dropdown
$queryTypes = "SELECT member_type_id, member_type_name FROM member_type";
$member_type_results = $pdo->query($queryTypes);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>รายละเอียดสมาชิก</title>
</head>

<body class="bg-gray-100 font-sans antialiased">
    <div class="container mx-auto px-4 py-8 max-w-3xl">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="p-4 <?php echo $is_update ? 'bg-gradient-to-r from-yellow-400 to-yellow-600' : 'bg-gradient-to-r from-blue-400 to-blue-600'; ?>">
                <h2 class="text-xl font-semibold text-white">
                    <?php echo $is_update ? 'แก้ไขข้อมูลสมาชิก' : 'รายละเอียดสมาชิก'; ?>
                </h2>
            </div>

            <!-- Form -->
            <form action="../../page/member/member_update.php" method="POST" class="p-6 space-y-6">
                <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($member['member_id']); ?>">

                <!-- Member Details -->
                <div class="space-y-4">
                    <!-- Member ID -->
                    <div class="flex items-center  border-b border-gray-200 pb-2">
                        <span class="font-medium text-gray-700">รหัสสมาชิก:</span>
                        <span class="text-gray-900 text-start">
                            <?php if ($is_update): ?>
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($member['member_id']); ?>">
                            <?php endif; ?>
                            <?php echo htmlspecialchars($member['member_id']); ?>
                        </span>
                    </div>

                    <!-- Member Name -->
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <span class="font-medium text-gray-700">ชื่อ-สกุล:</span>
                        <div class="flex-1 ml-4">
                            <?php if ($is_update): ?>
                                <input type="text" name="name" id="member-name" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500" value="<?php echo htmlspecialchars($member['member_name']); ?>">
                            <?php else: ?>
                                <span class="text-gray-900"><?php echo htmlspecialchars($member['member_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Member Type -->
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <span class="font-medium text-gray-700">ประเภทสมาชิก:</span>
                        <div class="flex-1 ml-4">
                            <?php if ($is_update): ?>
                                <select name="typename" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                    <?php while ($typeRow = $member_type_results->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($typeRow['member_type_id']); ?>" <?php echo $typeRow['member_type_id'] == $member['member_type_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($typeRow['member_type_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            <?php else: ?>
                                <span class="text-gray-900"><?php echo htmlspecialchars($member['member_type_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Member Address -->
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <span class="font-medium text-gray-700">ที่อยู่:</span>
                        <div class="flex-1 ml-4">
                            <?php if ($is_update): ?>
                                <input type="text" name="address" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500" value="<?php echo htmlspecialchars($member['member_address']); ?>">
                            <?php else: ?>
                                <span class="text-gray-900"><?php echo htmlspecialchars($member['member_address']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Member Phone -->
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <span class="font-medium text-gray-700">เบอร์โทร:</span>
                        <div class="flex-1 ml-4">
                            <?php if ($is_update): ?>
                                <input type="text" name="phone" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500" value="<?php echo htmlspecialchars($member['member_phone']); ?>">
                            <?php else: ?>
                                <span class="text-gray-900"><?php echo htmlspecialchars($member['member_phone']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Registration Date -->
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <span class="font-medium text-gray-700">วันที่สมัคร:</span>
                        <div class="flex-1 ml-4">
                            <?php if ($is_update): ?>
                                <input type="date" name="registerdate" class="w-full p-2 border border-gray-300 rounded bg-gray-100" value="<?php echo date('Y-m-d', strtotime($member['member_register_date'])); ?>" readonly>
                            <?php else: ?>
                                <span class="text-gray-900"><?php echo htmlspecialchars($member['member_register_date']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-4 mt-6">
                    <?php if ($is_update): ?>
                        <a href="/?page=member_detail&tabel=member&id=<?php echo htmlspecialchars($member['member_id']); ?>" class="px-6 py-2 bg-red-500 text-white rounded-lg shadow hover:bg-red-600 transition duration-200">
                            ยกเลิก
                        </a>
                        <button type="submit" class="px-6 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 transition duration-200">
                            บันทึก
                        </button>
                    <?php else: ?>
                        <a href="/?page=member_list" class="px-6 py-2 bg-gray-700 text-white rounded-lg shadow hover:bg-gray-800 transition duration-200">
                            ย้อนกลับ
                        </a>
                        <a href="?page=member_detail&tabel=member&id=<?php echo htmlspecialchars($member['member_id']); ?>&is_update=1" class="px-6 py-2 bg-blue-700 text-white rounded-lg shadow hover:bg-blue-800 transition duration-200">
                            ต้องการแก้ไข
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Focus on name field when editing -->
    <?php if ($is_update): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('member-name').focus();
            });
        </script>
    <?php endif; ?>
</body>

</html>