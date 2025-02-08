<?php
require_once __DIR__ . '../../../../config/database_server.php';
session_start();

function cleanInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// ตรวจสอบและดึงข้อมูลล็อต
$lot_id = isset($_GET['id']) ? cleanInput($_GET['id']) : '';
if (empty($lot_id)) {
    die("กรุณาระบุรหัสล็อต");
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            ls.lot_status_name,
            w.warehouse_name,
            s.store_name,
            sup.supplier_name
        FROM lot l
        LEFT JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
        LEFT JOIN warehouse w ON l.warehouse_id = w.warehouse_id
        LEFT JOIN store s ON l.store_id = s.store_id
        LEFT JOIN supplier sup ON l.supplier_id = sup.supplier_id
        WHERE l.lot_id = ?
    ");
    $stmt->execute([$lot_id]);
    $lot_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot_info) {
        die("ไม่พบข้อมูลล็อตที่ระบุ");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("เกิดข้อผิดพลาดในการโหลดข้อมูลล็อต");
}

// สำหรับการตรวจสอบสินค้าจากบาร์โค้ด
function checkProduct($barcode)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT product_id, product_name, product_barcode FROM product WHERE product_barcode = ?");
    $stmt->execute([$barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        // ถ้าไม่มีสินค้า ให้เพิ่มสินค้าใหม่พร้อมข้อมูลขั้นต่ำ
        $insertStmt = $pdo->prepare("INSERT INTO product (product_barcode, product_name) VALUES (?, ?)");
        $insertStmt->execute([$barcode, 'สินค้าไม่ทราบชื่อ']);
        $product_id = $pdo->lastInsertId();

        // คืนค่าสินค้าใหม่ที่สร้าง
        return [
            'product_id' => $product_id,
            'product_name' => 'สินค้าไม่ทราบชื่อ',
            'product_barcode' => $barcode
        ];
    }
    return $product;
}
// สำหรับการเพิ่มสินค้าใหม่เข้าล็อต
function addProductToLot($lot_id, $product_id, $qty, $capital)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO lot_product (lot_id, lot_product_id, lot_product_qty, lot_product_remain, lot_product_capital)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$lot_id, $product_id, $qty, $qty, $capital]);
    } catch (PDOException $e) {
        return false;
    }
}

// รับค่าจาก Ajax request สำหรับการสแกนบาร์โค้ด
if (isset($_POST['action']) && $_POST['action'] === 'scan') {
    $barcode = cleanInput($_POST['barcode']);
    $product = checkProduct($barcode);

    header('Content-Type: application/json');
    if ($product) {
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบสินค้าในระบบ',
            'newProductUrl' => 'add_product.php?barcode=' . urlencode($barcode)
        ]);
    }
    exit;
}

// รับค่าจาก Ajax request สำหรับการเพิ่มสินค้าเข้าล็อต
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $lot_id = cleanInput($_POST['lot_id']);
    $product_id = cleanInput($_POST['product_id']);
    $qty = cleanInput($_POST['qty']);
    $capital = cleanInput($_POST['capital']);

    $success = addProductToLot($lot_id, $product_id, $qty, $capital);

    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการล็อตสินค้า</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <h1 class="text-4xl font-bold mb-8">จัดการล็อตสินค้า</h1>

        <!-- ข้อมูลล็อต -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4">ข้อมูลล็อต</h2>
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <p class="font-semibold mb-2">รหัส Lot สินค้า:</p>
                    <p><?= htmlspecialchars($lot_info['lot_id']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-2">วันที่รับสินค้า:</p>
                    <p><?= date('d/m/Y', strtotime($lot_info['lot_date'])) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-2">คลังสินค้า:</p>
                    <p><?= htmlspecialchars($lot_info['warehouse_name']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-2">ผู้ส่งมอบ:</p>
                    <p><?= htmlspecialchars($lot_info['supplier_name']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-2">ร้านค้า:</p>
                    <p><?= htmlspecialchars($lot_info['store_name']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-2">สถานะ:</p>
                    <p><?= htmlspecialchars($lot_info['lot_status_name']) ?></p>
                </div>
            </div>
        </div>

        <!-- ส่วนสแกนสินค้า -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-semibold mb-4">สแกนสินค้าเข้าล็อต</h2>

            <div class="mb-4">
                <label class="block mb-2">สแกนบาร์โค้ดสินค้า:</label>
                <input type="text" id="barcode-input"
                    class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                    autofocus>
            </div>

            <!-- ฟอร์มสำหรับกรอกข้อมูลเพิ่มเติมหลังสแกน -->
            <div id="product-form" class="hidden">
                <div class="mb-4">
                    <p class="font-semibold">ชื่อสินค้า: <span id="product-name"></span></p>
                    <p class="font-semibold">บาร์โค้ด: <span id="product-barcode"></span></p>
                </div>
                <div class="mb-4">
                    <label class="block mb-2">จำนวน:</label>
                    <input type="number" id="qty-input"
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                </div>
                <div class="mb-4">
                    <label class="block mb-2">ราคาทุน:</label>
                    <input type="number" id="capital-input" step="0.01"
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                </div>
                <button id="add-product"
                    class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                    เพิ่มสินค้า
                </button>
            </div>
        </div>

        <!-- ตารางแสดงรายการสินค้า -->
        <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold mb-4">รายการสินค้าที่สแกน</h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 border">บาร์โค้ด</th>
                            <th class="px-4 py-2 border">ชื่อสินค้า</th>
                            <th class="px-4 py-2 border">จำนวน</th>
                            <th class="px-4 py-2 border">ราคาทุน</th>
                            <th class="px-4 py-2 border">มูลค่ารวม</th>
                            <th class="px-4 py-2 border">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="scanned-products">
                        <!-- จะถูกเติมด้วย JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- คำนวนรายการ -->
            <div class="mt-8 flex space-x-2 justify-end">
                <div class=" flex flex-col">
                    <label for="">จำนวนรายการ</label>
                    <label for="" class=" w-[230px] border border-gray-400">88</label>
                </div>
                <div class=" flex flex-col">
                    <label for="">ยอดรวม</label>
                    <label for="" class=" w-[230px] border border-gray-400">12000</label>
                </div>

            </div>
            <div class="mt-4 flex justify-end">
                <button id="save-all" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">
                    บันทึกทั้งหมด
                </button>
            </div>
        </div>

    </div>

    <script>
        $(document).ready(function() {
            let currentProduct = null;

            // จัดการการสแกนบาร์โค้ด
            $('#barcode-input').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    const barcode = $(this).val();

                    $.post('scan_products.php', {
                        action: 'scan',
                        barcode: barcode
                    }, function(response) {
                        if (response.success) {
                            currentProduct = response.product;
                            $('#product-name').text(response.product.product_name);
                            $('#product-barcode').text(response.product.product_barcode);
                            $('#product-form').removeClass('hidden');
                            $('#qty-input').focus();
                        } else {
                            // ถ้าไม่พบสินค้า คุณอาจจะถามว่าผู้ใช้ต้องการเพิ่มสินค้าใหม่ทันที
                            if (confirm('ไม่พบสินค้าในระบบ ต้องการเพิ่มสินค้าใหม่หรือไม่?')) {
                                window.location.href = response.newProductUrl;
                            }
                        }
                    });

                    $(this).val('');
                }
            });

            // จัดการการเพิ่มสินค้า
            $('#add-product').click(function() {
                if (!currentProduct) return;

                const qty = $('#qty-input').val();
                const capital = $('#capital-input').val();

                if (!qty || !capital) {
                    alert('กรุณากรอกข้อมูลให้ครบ');
                    return;
                }

                $.post('scan_products.php', {
                    action: 'add',
                    lot_id: '<?= $lot_id ?>',
                    product_id: currentProduct.product_id,
                    qty: qty,
                    capital: capital
                }, function(response) {
                    if (response.success) {
                        // เพิ่มรายการในตาราง
                        const total = qty * capital;
                        $('#scanned-products').append(`
                        <tr>
                            <td class="px-4 py-2">${currentProduct.product_barcode}</td>
                            <td class="px-4 py-2">${currentProduct.product_name}</td>
                            <td class="px-4 py-2 text-right">${qty}</td>
                            <td class="px-4 py-2 text-right">${parseFloat(capital).toFixed(2)}</td>
                            <td class="px-4 py-2 text-right">${total.toFixed(2)}</td>
                        </tr>
                    `);

                        // รีเซ็ตฟอร์ม
                        $('#product-form').addClass('hidden');
                        $('#qty-input').val('');
                        $('#capital-input').val('');
                        $('#barcode-input').focus();
                        currentProduct = null;
                    } else {
                        alert('เกิดข้อผิดพลาดในการเพิ่มสินค้า');
                    }
                });
            });
        });

        // Function to remove product from the table
        function removeProduct(element, productId) {
            if (confirm('คุณแน่ใจที่จะลบสินค้านี้หรือไม่?')) {
                $(element).closest('tr').remove();
            }
        }
    </script>


   
</body>

</html>