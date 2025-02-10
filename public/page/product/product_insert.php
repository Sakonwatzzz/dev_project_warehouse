<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// Function to check for duplicate product_ids
function checkproduct_idExists($pdo, $product_id)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE product_id = ?");
    $stmt->execute([$product_id]);
    return $stmt->fetchColumn() > 0;
}

// แยก endpoint สำหรับ AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'check_product_id') {
    header('Content-Type: application/json');
    $product_id = $_GET['product_id'] ?? '';
    $exists = checkproduct_idExists($pdo, $product_id);
    echo json_encode(['exists' => $exists]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];

    if (checkproduct_idExists($pdo, $product_id)) {
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

        $product_id = $pdo->lastInsertId();
        header("Location: index.php?page=product_detail&tabel=product&id=$product_id");
        exit();
    } catch (PDOException $e) {
        die('Error: ' . $e->getMessage());
    }
}
ob_end_flush();

// Query สำหรับข้อมูลสินค้า
$query = "SELECT
    product.product_id, 
    product.product_name, 
    product.product_price1, 
    product.product_price2, 
    product.product_price3, 
    product.product_price4, 
    product.product_capital, 
    product.product_type_id, 
    product.product_brand_id,
    product_brand.product_brand_name, 
    product_type.product_type_name
FROM
    product
INNER JOIN
    product_brand ON product.product_brand_id = product_brand.product_brand_id
INNER JOIN
    product_type ON product.product_type_id = product_type.product_type_id";

$productResults = $pdo->query($query);

// Query สำหรับประเภทสินค้า
$queryTypes = "SELECT
    product_type.product_type_id, 
    product_type.product_type_name
FROM product_type";
$typeResults = $pdo->query($queryTypes);

if (!$typeResults) {
    die("Query failed: " . $pdo->errorInfo());
}

// Query สำหรับประเภทแบรนด์สินค้า
$querybrandTypes = "SELECT
    product_brand.product_brand_id, 
    product_brand.product_brand_name
FROM product_brand";
$brandResults = $pdo->query($querybrandTypes);

if (!$brandResults) {
    die("Query failed: " . $pdo->errorInfo());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>product_insert</title>

    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            width: 300px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .modal-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .modal-message {
            margin: 15px 0;
            color: #666;
        }

        .modal-button {
            margin-top: 15px;
            padding: 8px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .modal-button:hover {
            background-color: #45a049;
        }

        .modal-button.warning {
            background-color: #f44336;
        }

        .modal-button.warning:hover {
            background-color: #da190b;
        }

        .error-input {
            border-color: #FF0000 !important;
            background-color: #FFEBEE !important;
        }

        .notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            color: #000;
            border: 2px solid #A0A0A0;
            border-radius: 5px;
            font-family: "Tahoma", "Verdana", sans-serif;
            width: 400px;
            height: 120px;
            box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
        }

        .notification-header {
            background-color: #A0C4FF;
            color: #000;
            padding: 10px;
            font-weight: bold;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }

        .notification-header.error {
            background-color: #FF9494;
        }

        .notification-body {
            padding: 20px;
            font-size: 16px;
            text-align: center;
        }

        .product_id-focus {
            background-color: #e8f4ff !important;
            border-color: #2196F3 !important;
        }

        .error-input {
            border-color: #FF0000 !important;
            background-color: #FFEBEE !important;
        }

        input:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 5px rgba(33, 150, 243, 0.3);
        }
    </style>
</head>

<body>
    <!-- Modal for duplicate product_id -->
    <div id="duplicateModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">แจ้งเตือน</div>
            <div class="modal-message">รหัสบาร์โค้ดนี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบและแก้ไขใหม่</div>
            <button class="modal-button warning" onclick="closeModal('duplicateModal')">ตกลง</button>
        </div>
    </div>

    <!-- Modal for empty product_id -->
    <div id="emptyproduct_idModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">แจ้งเตือน</div>
            <div class="modal-message">กรุณากรอกรหัสบาร์โค้ด</div>
            <button class="modal-button" onclick="closeModal('emptyproduct_idModal')">ตกลง</button>
        </div>
    </div>
    <div class="bg-[#F0F0F0] font-[Tahoma] ">
        <div class="mx-[40rem] mt-8 bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg rounded-md">
            <div id="notification" class="notification">
                <div id="notification-header" class="notification-header">การแจ้งเตือน</div>
                <div id="notification-body" class="notification-body">เพิ่มสินค้าสำเร็จ!</div>
            </div>

            <h2 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border  border-[#8f959b] shadow  font-bold p-5">เพิ่มสินค้า</h2>

            <!-- Form for adding products -->
            <form class="p-5  space-y-2" action="" method="POST" id="productForm">
                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="product_id">รหัสสินค้า:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner"
                        type="text"
                        name="product_id"
                        id="product_id"
                        autocomplete="off"
                        required
                        autofocus>
                </div>
                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="name">ชื่อสินค้า:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="name" id="name" required>
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="price_1">ราคาที่1:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="price_1" id="price_1" required>
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="price_2">ราคาที่2:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="price_2" id="price_2" required>
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="price_3">ราคาที่3:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="price_3" id="price_3" required>
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="price_4">ราคาที่4:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="price_4" id="price_4" required>
                </div>

                <div class="flex items-center ">
                    <label class="w-[110px] text-base mr-4" for="capital">ราคาทุน:</label>
                    <input class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" type="text" name="capital" id="capital" required>
                </div>

                <?php if ($typeResults->rowCount() > 0) { ?>
                    <div class="flex items-center ">
                        <label class="w-[110px] text-base mr-4" for="typename">ประเภทสินค้า:</label>
                        <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="typename" id="typename">
                            <?php while ($row = $typeResults->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo ($row['product_type_id']); ?>">
                                    <?php echo ($row['product_type_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <p>ไม่พบข้อมูลประเภทสินค้า</p>
                <?php } ?>

                <?php if ($brandResults->rowCount() > 0) { ?>
                    <div class="flex items-center ">
                        <label class="w-[110px] text-base mr-4" for="typebrandname">ประเภทแบรนด์สินค้า:</label>
                        <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="typebrandname" id="typebrandname" required>
                            <?php while ($row = $brandResults->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo ($row['product_brand_id']); ?>">
                                    <?php echo ($row['product_brand_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <p>ไม่พบข้อมูลประเภทแบรนด์สินค้า</p>
                <?php } ?>

                <div class="flex justify-end mt-5 gap-2">
                    <a href="/?page=product_list" class="
                        bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 
                        border-t-white 
                        border-l-white 
                        px-4 
                        py-1 
                        rounded 
                        shadow-md 
                        hover:bg-[#FFEBEE] 
                        active:border-[#EF9A9A] 
                        active:border-b-white 
                        active:border-r-white 
                        active:shadow-inner
                    ">ยกเลิก</a>

                    <button
                        type="submit"
                        class="
                            bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg border border-green-700 
                            border-t-white 
                            border-l-white 
                            px-4 
                            py-1 
                            rounded 
                            shadow-md 
                            hover:bg-[#E8F5E9] 
                            active:border-[#A5D6A7] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner
                        ">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById('productForm');
            const product_idInput = document.getElementById('product_id');

            // Function to show modal
            function showModal(modalId) {
                document.getElementById(modalId).style.display = 'block';
                if (modalId === 'duplicateModal') {
                    product_idInput.classList.add('error-input');
                }
            }

            // Function to close modal
            window.closeModal = function(modalId) {
                document.getElementById(modalId).style.display = 'none';
                product_idInput.focus();
                if (modalId === 'duplicateModal') {
                    product_idInput.select();
                }
            }

            // Close modals when clicking outside
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                    product_idInput.focus();
                }
            }

            // Close modals with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const modals = document.getElementsByClassName('modal');
                    for (let modal of modals) {
                        if (modal.style.display === 'block') {
                            modal.style.display = 'none';
                            product_idInput.focus();
                        }
                    }
                }
            });

            async function checkproduct_id(product_id) {
                try {
                    const response = await fetch(`?action=check_product_id&product_id=${encodeURIComponent(product_id)}`);
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    const data = await response.json();
                    return data.exists;
                } catch (error) {
                    console.error('Error checking product_id:', error);
                    return false;
                }
            }

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const product_id = product_idInput.value.trim();
                if (!product_id) {
                    showModal('emptyproduct_idModal');
                    return;
                }

                const exists = await checkproduct_id(product_id);
                if (exists) {
                    showModal('duplicateModal');
                } else {
                    product_idInput.classList.remove('error-input');
                    form.submit();
                }
            });

            // Remove error styling when product_id input changes
            product_idInput.addEventListener('input', function() {
                this.classList.remove('error-input');
            });
        });
    </script>
</body>

</html>