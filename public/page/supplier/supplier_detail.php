<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Validate required GET parameters
if (!isset($_GET['id'])) {
    die("ระบุ ID ไม่ถูกต้อง");
}

// Retrieve and validate 'tabel' parameter (optional, depending on your app logic)
$tabel = isset($_GET['tabel']) ? $_GET['tabel'] : 'supplier'; // Default to 'supplier' if not provided
if ($tabel !== 'supplier') {
    die("ระบุประเภทไม่ถูกต้อง"); // "Invalid type specified"
}

// Retrieve supplier data
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM supplier WHERE supplier_id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$supplier) {
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
    <title>รายละเอียดผู้ส่งมอบ</title>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <h2 class="text-gray-800 text-2xl font-bold mt-8 mb-4">รายละเอียดผู้ส่งมอบ</h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        ข้อมูลผู้ส่งมอบ
                    </h3>
                    <div class="p-6 space-y-1">
                        <?php
                        $fields = [
                            ['label' => 'รหัสผู้ส่งมอบ', 'value' => $supplier['supplier_id']],
                            ['label' => 'ชื่อผู้ส่งมอบ', 'value' => $supplier['supplier_name']],
                            ['label' => 'ที่อยู่', 'value' => $supplier['supplier_address']],
                            ['label' => 'เบอร์โทร', 'value' => $supplier['supplier_phone']],
                            ['label' => 'รายละเอียด', 'value' => $supplier['supplier_detail']],
                        ];
                        foreach ($fields as $field): ?>
                            <div class="flex items-center gap-4">
                                <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                                <div class="w-7/12 lg:w-8/12">
                                    <input type="text"
                                           value="<?php echo htmlspecialchars($field['value']); ?>"
                                           class="w-full p-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600"
                                           readonly>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="flex justify-end gap-4 mt-6">
                            <a href="/?page=supplier_list"
                               class="mt-4 bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                                กลับไปที่รายการ
                            </a>
                            <a href="/?page=supplier_update&id=<?php echo htmlspecialchars($supplier['supplier_id']); ?>"
                               class="mt-4 bg-yellow-500 text-white px-4 py-2 rounded-md hover:bg-yellow-600 transition duration-200">
                                แก้ไข
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>