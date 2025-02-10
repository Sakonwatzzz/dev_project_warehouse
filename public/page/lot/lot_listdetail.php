<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../config/database_server.php';


class InputValidator
{
    public static function clean($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'clean'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateNumeric($value, $min = null, $max = null)
    {
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($value === false) return false;
        if ($min !== null && $value < $min) return false;
        if ($max !== null && $value > $max) return false;
        return $value;
    }
}

class LotManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getLotInfo($lotId)
    {
        $stmt = $this->pdo->prepare("
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
        $stmt->execute([$lotId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getProductByBarcode($barcode)
    {
        $stmt = $this->pdo->prepare("
        SELECT 
            product_id,
            product_name,
            barcode as product_barcode,
            capital_price
        FROM product 
        WHERE barcode = ? AND active_status = 1
    ");
        $stmt->execute([$barcode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$lotManager = new LotManager($pdo);

$lot_id = isset($_GET['id']) ? InputValidator::clean($_GET['id']) : '';
if (empty($lot_id)) {
    die("กรุณาระบุรหัสล็อต");
}

try {
    $lot_info = $lotManager->getLotInfo($lot_id);
    if (!$lot_info) {
        die("ไม่พบข้อมูลล็อตที่ระบุ");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("เกิดข้อผิดพลาดในการโหลดข้อมูลล็อต");
}

if (isset($_GET['action']) && $_GET['action'] === 'get_product') {
    header('Content-Type: application/json');
    
    $barcode = isset($_GET['barcode']) ? InputValidator::clean($_GET['barcode']) : '';

    if (empty($barcode)) {
        http_response_code(400);
        echo json_encode(['error' => 'กรุณาระบุบาร์โค้ด']);
        exit;
    }

    try {
        $product = $lotManager->getProductByBarcode($barcode);
        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'ไม่พบสินค้า']);
            exit;
        }
        echo json_encode($product);
        exit;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'เกิดข้อผิดพลาดในการค้นหาสินค้า']);
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการล็อตสินค้า - <?= htmlspecialchars($lot_info['lot_id']) ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-4 md:p-8">
        <h1 class="text-3xl md:text-4xl font-bold mb-6 md:mb-8">จัดการล็อตสินค้า</h1>

        <!-- ข้อมูลล็อต -->
        <div class="bg-white rounded-lg shadow-lg p-4 md:p-6 mb-6 md:mb-8">
            <h2 class="text-xl md:text-2xl font-semibold mb-4">ข้อมูลล็อต</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div>
                    <p class="font-semibold mb-1">รหัส Lot สินค้า:</p>
                    <p><?= htmlspecialchars($lot_info['lot_id']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">วันที่รับสินค้า:</p>
                    <p><?= date('d/m/Y', strtotime($lot_info['lot_date'])) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">คลังสินค้า:</p>
                    <p><?= htmlspecialchars($lot_info['warehouse_name']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">ผู้ส่งมอบ:</p>
                    <p><?= htmlspecialchars($lot_info['supplier_name']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">ร้านค้า:</p>
                    <p><?= htmlspecialchars($lot_info['store_name']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">สถานะ:</p>
                    <p><?= htmlspecialchars($lot_info['lot_status_name']) ?></p>
                </div>
            </div>
        </div>

        <!-- ส่วนสแกนสินค้า -->
        <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
            <h2 class="text-xl md:text-2xl font-semibold mb-4">สแกนสินค้าเข้าล็อต</h2>

            <div class="mb-4">
                <label class="block mb-2 font-medium">สแกนบาร์โค้ดสินค้า:</label>
                <input type="text" id="barcode-input"
                    class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                    placeholder="สแกนหรือพิมพ์บาร์โค้ด" autofocus>
            </div>

            <div id="product-form" class="hidden bg-gray-50 p-4 rounded-lg mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="font-medium mb-1">ชื่อสินค้า:</p>
                        <p id="product-name"><?= htmlspecialchars($product['product_name'] ?? 'ไม่มี') ?></p>
                      

                    </div>
                    <div>
                        <p class="font-medium mb-1">บาร์โค้ด:</p>
                        <p id="product-barcode"><?= htmlspecialchars($product['product_barcode'] ?? 'ไม่มี') ?></p>
                    </div>
                    <div>
                        <label class="block font-medium mb-1">จำนวน:</label>
                        <input type="number" id="qty-input" min="1"
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block font-medium mb-1">ราคาทุน:</label>
                        <input type="number" id="capital-input" min="0.01" step="0.01"
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                </div>
                <button id="add-product" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 transition duration-200">
                    เพิ่มสินค้า
                </button>
            </div>
        </div>

        <!-- ตารางสินค้าที่สแกน -->
        <div class="mt-6 md:mt-8 bg-white rounded-lg shadow-lg p-4 md:p-6">
            <h3 class="text-lg md:text-xl font-semibold mb-4">รายการสินค้าที่สแกน</h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 border text-left">บาร์โค้ด</th>
                            <th class="px-4 py-2 border text-left">ชื่อสินค้า</th>
                            <th class="px-4 py-2 border text-right">จำนวน</th>
                            <th class="px-4 py-2 border text-right">ราคาทุน</th>
                            <th class="px-4 py-2 border text-right">มูลค่ารวม</th>
                            <th class="px-4 py-2 border text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="scanned-products"></tbody>
                </table>
            </div>

            <!-- ส่วนสรุปยอด -->
            <div class="mt-6 md:mt-8 flex flex-col md:flex-row md:justify-end space-y-4 md:space-y-0 md:space-x-4">
                <div class="flex flex-col">
                    <label class="font-medium mb-1">จำนวนรายการ</label>
                    <div id="total-items" class="w-full md:w-[230px] bg-gray-50 border px-3 py-2 rounded text-right">0</div>
                </div>
                <div class="flex flex-col">
                    <label class="font-medium mb-1">ยอดรวม</label>
                    <div id="total-amount" class="w-full md:w-[230px] bg-gray-50 border px-3 py-2 rounded text-right">0.00</div>
                </div>
            </div>

            <!-- ปุ่มบันทึก -->
            <div class="mt-6 text-right">
                <button id="save-lot-products" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600 transition duration-200">
                    บันทึกรายการนำเข้าสินค้า
                </button>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const LotManager = {
                scannedProducts: [],
                currentBarcode: '',

                init() {
                    this.bindEvents();
                    this.focusBarcodeInput();
                },

                bindEvents() {
                    $('#barcode-input').on('keypress', this.handleBarcodeInput.bind(this));
                    $('#add-product').on('click', this.handleAddProduct.bind(this));
                    $('#qty-input').on('keypress', this.handleQtyEnter.bind(this));
                    $('#capital-input').on('keypress', this.handleCapitalEnter.bind(this));
                    $(document).on('click', '.delete-product', this.handleDeleteProduct.bind(this));
                    $('#save-lot-products').on('click', this.handleSaveProducts.bind(this));

                    $(document).on('keydown', (e) => {
                        if (e.key === 'Escape') {
                            this.resetProductForm();
                            this.focusBarcodeInput();
                        }
                    });
                },

                async handleBarcodeInput(e) {
                    if (e.which === 13) {
                        const barcode = $(e.target).val().trim();
                        if (barcode) {
                            this.currentBarcode = barcode;
                            await this.fetchProductData(barcode);
                            $(e.target).val('');
                        }
                    }
                },

                async fetchProductData(barcode) {
                    try {
                        // เพิ่ม id ของ lot เข้าไปใน request
                        const response = await $.get(`${window.location.pathname}`, {
                            action: 'get_product',
                            barcode: barcode,
                            id: '<?= $lot_id ?>' // ส่ง lot_id ไปด้วย
                        });

                        console.log('Scanned barcode:', barcode);
                        console.log('Response from server:', response);

                        this.displayProductForm(response);

                    } catch (error) {
                        console.error('Error:', error);
                        alert(error.responseJSON?.error || 'เกิดข้อผิดพลาดในการค้นหาสินค้า');
                        this.resetProductForm();
                        this.focusBarcodeInput();
                    }
                },

                displayProductForm(product) {
                    $('#product-form')
                        .removeClass('hidden')
                        .data('product-id', product.product_id);
                    $('#product-name').text(product.product_name);
                    $('#product-barcode').text(product.product_barcode);
                    $('#capital-input').val(product.capital_price);
                    $('#qty-input').val(1).focus().select();
                },

                handleQtyEnter(e) {
                    if (e.which === 13) {
                        $('#capital-input').focus().select();
                    }
                },

                handleCapitalEnter(e) {
                    if (e.which === 13) {
                        this.handleAddProduct();
                    }
                },

                handleAddProduct() {
                    const productData = this.validateProductData();
                    if (!productData) return;

                    this.scannedProducts.push(productData);
                    this.updateUI();
                    this.resetProductForm();
                    this.focusBarcodeInput();
                },

                validateProductData() {
                    const productData = {
                        product_id: $('#product-form').data('product-id'),
                        barcode: $('#product-barcode').text(),
                        name: $('#product-name').text(),
                        qty: parseInt($('#qty-input').val()),
                        capital: parseFloat($('#capital-input').val())
                    };

                    if (!productData.product_id || !productData.barcode || !productData.name ||
                        isNaN(productData.qty) || productData.qty < 1 ||
                        isNaN(productData.capital) || productData.capital < 0) {
                        alert('กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง');
                        return null;
                    }

                    productData.totalAmount = productData.qty * productData.capital;
                    return productData;
                },

                handleDeleteProduct(e) {
                    const index = $(e.target).closest('tr').data('index');
                    if (confirm('ต้องการลบรายการนี้ใช่หรือไม่?')) {
                        this.scannedProducts.splice(index, 1);
                        this.updateUI();
                    }
                },

                async handleSaveProducts() {
                    if (this.scannedProducts.length === 0) {
                        alert('กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ');
                        return;
                    }

                    try {
                        const response = await $.ajax({
                            url: 'save_lot_products.php',
                            method: 'POST',
                            data: {
                                lot_id: '<?= $lot_id ?>',
                                products: JSON.stringify(this.scannedProducts)
                            }
                        });

                        if (response.success) {
                            alert('บันทึกข้อมูลสำเร็จ');
                            window.location.href = '?page=lot_productlist';
                        } else {
                            alert('เกิดข้อผิดพลาด: ' + (response.error || 'ไม่สามารถบันทึกข้อมูลได้'));
                        }
                    } catch (error) {
                        alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
                        console.error(error);
                    }
                },

                updateUI() {
                    this.updateProductTable();
                    this.updateTotals();
                },

                updateProductTable() {
                    const tbody = $('#scanned-products');
                    tbody.empty();

                    this.scannedProducts.forEach((product, index) => {
                        tbody.append(`
                            <tr class="${index % 2 === 0 ? 'bg-gray-50' : 'bg-white'}" data-index="${index}">
                                <td class="px-4 py-2 border">${this.escapeHtml(product.barcode)}</td>
                                <td class="px-4 py-2 border">${this.escapeHtml(product.name)}</td>
                                <td class="px-4 py-2 border text-right">${product.qty.toLocaleString()}</td>
                                <td class="px-4 py-2 border text-right">${product.capital.toFixed(2)}</td>
                                <td class="px-4 py-2 border text-right">${product.totalAmount.toFixed(2)}</td>
                                <td class="px-4 py-2 border text-center">
                                    <button class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 delete-product">
                                        ลบ
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                },

                updateTotals() {
                    const totalItems = this.scannedProducts.length;
                    const totalAmount = this.scannedProducts.reduce((sum, product) => sum + product.totalAmount, 0);

                    $('#total-items').text(totalItems.toLocaleString());
                    $('#total-amount').text(totalAmount.toFixed(2));
                },

                resetProductForm() {
                    $('#product-form').addClass('hidden').removeData('product-id');
                    $('#product-name').text('');
                    $('#product-barcode').text('');
                    $('#qty-input').val('');
                    $('#capital-input').val('');
                },

                focusBarcodeInput() {
                    $('#barcode-input').focus();
                },

                escapeHtml(unsafe) {
                    return unsafe
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");
                }
            };

            // Initialize the application
            LotManager.init();
        });
    </script>


</body>

</html>