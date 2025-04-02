<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Function to check for duplicate product_ids
function checkProductIdExists($pdo, $product_id)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE product_id = ?");
    $stmt->execute([$product_id]);
    return $stmt->fetchColumn() > 0;
}

// แยก endpoint สำหรับ AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'check_product_id') {
    header('Content-Type: application/json');
    $product_id = $_GET['product_id'] ?? '';
    $exists = checkProductIdExists($pdo, $product_id);
    echo json_encode(['exists' => $exists]);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];

    if (checkProductIdExists($pdo, $product_id)) {
        echo "<script>
                alert('รหัสบาร์โค้ดนี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบและแก้ไขใหม่');
                window.history.back();
              </script>";
        exit();
    }

    $name = $_POST['name'];
    $price_1 = $_POST['price_1'];
    $price_2 = $_POST['price_2'];
    $price_3 = $_POST['price_3'];
    $price_4 = $_POST['price_4'];
    $capital = $_POST['capital'];
    $typename = $_POST['typename'];
    $typebrandname = $_POST['typebrandname'];

    try {
        $stmt = $pdo->prepare('INSERT INTO product (product_id, product_name, product_price1, product_price2, product_price3, product_price4, product_capital, product_type_id, product_brand_id)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$product_id, $name, $price_1, $price_2, $price_3, $price_4, $capital, $typename, $typebrandname]);

        header("Location: /?page=product_detail&tabel=product&id=" . urlencode($product_id));
        exit();
    } catch (PDOException $e) {
        die('Error: ' . $e->getMessage());
    }
}
ob_end_flush();

// Query สำหรับประเภทสินค้า
$queryTypes = "SELECT 
    product_type_id, 
    product_type_name 
FROM product_type 
ORDER BY product_type_name";
$typeResults = $pdo->query($queryTypes);

if (!$typeResults) {
    die("Query failed: " . $pdo->errorInfo()[2]);
}

// Query สำหรับประเภทแบรนด์สินค้า
$queryBrandTypes = "SELECT 
    product_brand_id, 
    product_brand_name 
FROM product_brand 
ORDER BY product_brand_name";
$brandResults = $pdo->query($queryBrandTypes);

if (!$brandResults) {
    die("Query failed: " . $pdo->errorInfo()[2]);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>เพิ่มสินค้า</title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <h2 class="text-gray-800 text-2xl font-bold mt-8 mb-4">เพิ่มสินค้า</h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Product Insert Section -->
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        กรอกข้อมูลสินค้า
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST" id="productForm">
                        <!-- Product Fields -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">รหัสสินค้า:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="text" 
                                    name="product_id" 
                                    id="product_id" 
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                                    required 
                                    autocomplete="off" 
                                    autofocus>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ชื่อสินค้า:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="text" 
                                    name="name" 
                                    id="name" 
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                                    required>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาที่ 1:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" 
                                    name="price_1" 
                                    id="price_1" 
                                    step="0.01" 
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                                    required>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาที่ 2:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" 
                                    name="price_2" 
                                    id="price_2" 
                                    step="0.01" 
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                                    required>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาที่ 3:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" 
                                    name="price_3" 
                                    id="price_3" 
                                    step="0.01" 
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                                    required>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาที่ 4:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" 
                                    name="price_4" 
                                    id="price_4" 
                                    step="0.01" 
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                                    required>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ราคาทุน:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="number" 
                                    name="capital" 
                                    id="capital" 
                                    step="0.01" 
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                                    required>
                            </div>
                        </div>

                        <!-- Product Type -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=product_type" class="text-blue-600 hover:underline">ประเภทสินค้า:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <select name="typename" id="typename" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <?php while ($typeRow = $typeResults->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($typeRow['product_type_id']); ?>">
                                            <?php echo htmlspecialchars($typeRow['product_type_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <a href="?page=product_type_add" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                                    เพิ่ม
                                </a>
                            </div>
                        </div>

                        <!-- Product Brand -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=product_brand" class="text-blue-600 hover:underline">แบรนด์สินค้า:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <select name="typebrandname" id="typebrandname" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <?php while ($brandRow = $brandResults->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($brandRow['product_brand_id']); ?>">
                                            <?php echo htmlspecialchars($brandRow['product_brand_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <a href="?page=product_brand_add" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                                    เพิ่ม
                                </a>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4 mt-6">
                            <a href="/?page=product_list" 
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

    <!-- Modal for validation messages -->
    <div id="modalContainer" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md mx-auto">
            <div class="text-lg font-bold mb-4" id="modalTitle">แจ้งเตือน</div>
            <div class="mb-6" id="modalMessage">ข้อความแจ้งเตือน</div>
            <div class="flex justify-end">
                <button id="modalButton" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition duration-200">
                    ตกลง
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('productForm');
            const productIdInput = document.getElementById('product_id');
            const modalContainer = document.getElementById('modalContainer');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalButton = document.getElementById('modalButton');

            // Show modal with message
            function showModal(title, message, isError = false) {
                modalTitle.textContent = title;
                modalMessage.textContent = message;
                modalButton.className = isError 
                    ? 'bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition duration-200'
                    : 'bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition duration-200';
                modalContainer.classList.remove('hidden');
            }

            // Close modal
            function closeModal() {
                modalContainer.classList.add('hidden');
                productIdInput.focus();
            }

            // Set up modal button handler
            modalButton.addEventListener('click', closeModal);

            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !modalContainer.classList.contains('hidden')) {
                    closeModal();
                }
            });

            // Check if product ID exists
            async function checkProductId(productId) {
                try {
                    const response = await fetch(`?action=check_product_id&product_id=${encodeURIComponent(productId)}`);
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    const data = await response.json();
                    return data.exists;
                } catch (error) {
                    console.error('Error checking product ID:', error);
                    return false;
                }
            }

            // Form submission handler
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const productId = productIdInput.value.trim();
                
                if (!productId) {
                    showModal('แจ้งเตือน', 'กรุณากรอกรหัสสินค้า', true);
                    productIdInput.classList.add('border-red-500', 'bg-red-50');
                    return;
                }

                const exists = await checkProductId(productId);
                
                if (exists) {
                    showModal('แจ้งเตือน', 'รหัสสินค้านี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบและแก้ไขใหม่', true);
                    productIdInput.classList.add('border-red-500', 'bg-red-50');
                    productIdInput.select();
                } else {
                    form.submit();
                }
            });

            // Clear error styling when product ID input changes
            productIdInput.addEventListener('input', function() {
                this.classList.remove('border-red-500', 'bg-red-50');
            });

            // Focus on product ID field on page load
            productIdInput.focus();
        });
    </script>
</body>
</html>