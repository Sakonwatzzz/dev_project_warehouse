<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';

// Handle POST request for inserting a new supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare('INSERT INTO supplier (supplier_name, supplier_address, supplier_phone, supplier_detail) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $address, $phone, $detail]);

    $supplier_id = $pdo->lastInsertId();
    header("Location: /?page=supplier_detail&tabel=supplier&id=$supplier_id");
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>เพิ่มผู้ส่งมอบ</title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <h2 class="text-gray-800 text-2xl font-bold mt-8 mb-4">เพิ่มผู้ส่งมอบใหม่</h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Supplier Insert Section -->
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        ข้อมูลผู้ส่งมอบ
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST">
                        <!-- Supplier Fields -->
                        <?php
                        $fields = [
                            ['label' => 'ชื่อผู้ส่งมอบ', 'name' => 'name', 'value' => '', 'id' => 'supplier-name', 'required' => true],
                            ['label' => 'ที่อยู่', 'name' => 'address', 'value' => ''],
                            ['label' => 'เบอร์โทร', 'name' => 'phone', 'value' => ''],
                            ['label' => 'รายละเอียด', 'name' => 'detail', 'value' => ''],
                        ];
                        foreach ($fields as $field): ?>
                            <div class="flex items-center gap-4">
                                <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                                <div class="w-7/12 lg:w-8/12">
                                    <input type="text"
                                        name="<?php echo $field['name']; ?>"
                                        id="<?php echo $field['id'] ?? ''; ?>"
                                        value="<?php echo htmlspecialchars($field['value']); ?>"
                                        class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                        <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4 mt-6">
                            <a href="/?page=supplier_list"
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
            document.getElementById('supplier-name').focus();
        });
    </script>
</body>

</html>