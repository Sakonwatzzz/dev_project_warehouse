<?php
require_once __DIR__ . '../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

// Validate required GET parameter
if (!isset($_GET['id'])) {
    die("ระบุ ID ไม่ถูกต้อง");
}

// Handle POST request for updating supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare("
        UPDATE supplier 
        SET supplier_name = ?, supplier_address = ?, supplier_phone = ?, supplier_detail = ?
        WHERE supplier_id = ?
    ");
    $stmt->execute([$name, $address, $phone, $detail, $id]);

    header("Location: /?page=supplier_detail&tabel=supplier&id=$id");
    exit;
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
    <title>แก้ไขข้อมูลผู้ส่งมอบ</title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <h2 class="text-gray-800 text-2xl font-bold mt-8 mb-4">กำลังแก้ไขข้อมูลผู้ส่งมอบ</h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Supplier Update Section -->
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        ข้อมูลผู้ส่งมอบ
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($supplier['supplier_id']); ?>">

                        <!-- Supplier Fields -->
                        <?php
                        $fields = [
                            ['label' => 'รหัสผู้ส่งมอบ', 'name' => 'id', 'value' => $supplier['supplier_id'], 'readonly' => true],
                            ['label' => 'ชื่อผู้ส่งมอบ', 'name' => 'name', 'value' => $supplier['supplier_name'], 'id' => 'supplier-name', 'required' => true],
                            ['label' => 'ที่อยู่', 'name' => 'address', 'value' => $supplier['supplier_address']],
                            ['label' => 'เบอร์โทร', 'name' => 'phone', 'value' => $supplier['supplier_phone']],
                            ['label' => 'รายละเอียด', 'name' => 'detail', 'value' => $supplier['supplier_detail']],
                        ];
                        foreach ($fields as $field): ?>
                            <div class="flex items-center gap-4">
                                <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                                <div class="w-7/12 lg:w-8/12">
                                    <?php if (!($field['readonly'] ?? false)): ?>
                                        <input type="text"
                                            name="<?php echo $field['name']; ?>"
                                            id="<?php echo $field['id'] ?? ''; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                            <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>>
                                    <?php else: ?>
                                        <input type="text"
                                            name="<?php echo $field['name']; ?>"
                                            value="<?php echo htmlspecialchars($field['value']); ?>"
                                            class="w-full p-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600"
                                            readonly>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4 mt-6">
                            <a href="/?page=supplier_detail&tabel=supplier&id=<?php echo htmlspecialchars($supplier['supplier_id']); ?>"
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
            document.getElementById('supplier-name').focus();
        });
    </script>
</body>

</html>