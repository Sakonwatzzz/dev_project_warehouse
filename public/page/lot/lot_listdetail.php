<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../config/database_server.php';

// Improved input sanitization and validation functions
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

// Database operations class
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
                product_barcode,
                capital_price
            FROM product 
            WHERE barcode = ?
        ");
        $stmt->execute([$barcode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Initialize manager
$lotManager = new LotManager($pdo);

// Handle lot ID validation
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

// Handle AJAX product lookup
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

$query = "SELECT product_id, product_barcode, product_name, product_price1, product_price2, product_price3, product_price4, product_capital, product_type_id FROM product";
$stmt = $pdo->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize default values
$product_name = 'ไม่มีข้อมูล';
$product_barcode = 'ไม่มีข้อมูล';

// Set values if products exist
if (!empty($products) && isset($products[0])) {
    $product = $products[0];
    $product_name = htmlspecialchars($product['product_name']);
    $product_barcode = htmlspecialchars($product['product_barcode']);
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

        <!-- Lot Information Card -->
        <div class="bg-white rounded-lg shadow-lg p-4 md:p-6 mb-6 md:mb-8">
            <h2 class="text-xl md:text-2xl font-semibold mb-4">ข้อมูลล็อต</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div>
                    <p class="font-semibold mb-1">รหัส Lot สินค้า:</p>
                    <p class=""><?= htmlspecialchars($lot_info['lot_id']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">วันที่รับสินค้า:</p>
                    <p class=""><?= date('d/m/Y', strtotime($lot_info['lot_date'])) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">คลังสินค้า:</p>
                    <p class=""><?= htmlspecialchars($lot_info['warehouse_name']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">ผู้ส่งมอบ:</p>
                    <p class=""><?= htmlspecialchars($lot_info['supplier_name']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">ร้านค้า:</p>
                    <p class=""><?= htmlspecialchars($lot_info['store_name']) ?></p>
                </div>
                <div>
                    <p class="font-semibold mb-1">สถานะ:</p>
                    <p class=""><?= htmlspecialchars($lot_info['lot_status_name']) ?></p>
                </div>
            </div>
        </div>

        <!-- Product Scanning Section -->
        <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
            <h2 class="text-xl md:text-2xl font-semibold mb-4">สแกนสินค้าเข้าล็อต</h2>

            <div class="mb-4">
                <label class="block mb-2 font-medium">สแกนบาร์โค้ดสินค้า:</label>
                <input type="text" id="barcode-input"
                    class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                    placeholder="สแกนหรือพิมพ์บาร์โค้ด" autofocus>
            </div>

            <!-- Product Form -->
            <div id="product-form" class="hidden bg-gray-50 p-4 rounded-lg mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="font-medium mb-1">ชื่อสินค้า:</p>
                        <p class="bg-gray-50 p-2 rounded" id="product-name"><?= htmlspecialchars($product_name) ?></p>
                    </div>
                    <div>
                        <p class="font-medium mb-1">บาร์โค้ด:</p>
                        <p class="bg-gray-50 p-2 rounded" id="product-barcode"><?= htmlspecialchars($product_barcode) ?></p>
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
                <button id="add-product"
                    class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 transition duration-200">
                    เพิ่มสินค้า
                </button>
            </div>
        </div>

        <!-- Scanned Products Table -->
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

            <!-- Totals Section -->
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
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const LotManager = {
                scannedProducts: [],

                init() {
                    this.bindEvents();
                    this.focusBarcodeInput();
                },

                bindEvents() {
                    // Barcode scanning
                    $('#barcode-input').on('keypress', this.handleBarcodeInput.bind(this));
                    // Product form
                    $('#add-product').on('click', this.handleAddProduct.bind(this));
                    $('#qty-input').on('keypress', this.handleQtyEnter.bind(this));
                    $('#capital-input').on('keypress', this.handleCapitalEnter.bind(this));
                    // Delete product
                    $(document).on('click', '.delete-product', this.handleDeleteProduct.bind(this));

                    // Global keyboard shortcuts
                    $(document).on('keydown', (e) => {
                        if (e.key === 'Escape') {
                            this.resetProductForm();
                            this.focusBarcodeInput();
                        }
                    });
                },

                handleBarcodeInput(e) {
                    if (e.which === 13) { // Enter key
                        const barcode = $(e.target).val().trim();
                        if (barcode) {
                            this.fetchProductData(barcode);
                            $(e.target).val('');
                        }
                    }
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

                async fetchProductData(barcode) {
                    try {
                        const response = await $.get('?action=get_product', {
                            barcode
                        });
                        this.displayProductForm(response);
                    } catch (error) {
                        this.showError(error.responseJSON?.error || 'เกิดข้อผิดพลาดในการค้นหาสินค้า');
                        this.focusBarcodeInput();
                    }
                },

                displayProductForm(product) {
                    $('#product-name').val(product.product_name);
                    $('#product-barcode').val(product.barcode);
                    $('#capital-input').val(product.capital_price);
                    $('#qty-input').val(1);
                    $('#product-form').removeClass('hidden');
                    $('#qty-input').focus().select();
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
                    const barcode = $('#product-barcode').text();
                    const name = $('#product-name').text();
                    const qty = parseInt($('#qty-input').val());
                    const capital = parseFloat($('#capital-input').val());

                    if (!barcode || !name || isNaN(qty) || qty < 1 || isNaN(capital) || capital < 0) {
                        this.showError('กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง');
                        return null;
                    }

                    return {
                        barcode,
                        name,
                        qty,
                        capital,
                        totalAmount: qty * capital
                    };
                },

                handleDeleteProduct(e) {
                    const index = $(e.target).data('index');
                    if (confirm('ต้องการลบรายการนี้ใช่หรือไม่?')) {
                        this.scannedProducts.splice(index, 1);
                        this.updateUI();
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
                    <tr class="${index % 2 === 0 ? 'bg-gray-50' : 'bg-white'}">
                        <td class="px-4 py-2 border">${this.escapeHtml(product.barcode)}</td>
                        <td class="px-4 py-2 border">${this.escapeHtml(product.name)}</td>
                        <td class="px-4 py-2 border text-right">${product.qty.toLocaleString()}</td>
                        <td class="px-4 py-2 border text-right">${product.capital.toFixed(2)}</td>
                        <td class="px-4 py-2 border text-right">${product.totalAmount.toFixed(2)}</td>
                        <td class="px-4 py-2 border text-center">
                            <button class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 delete-product" 
                                    data-index="${index}">ลบ</button>
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
                    $('#product-form').addClass('hidden');
                    $('#product-name').text('');
                    $('#product-barcode').text('');
                    $('#qty-input').val('');
                    $('#capital-input').val('');
                },

                focusBarcodeInput() {
                    $('#barcode-input').focus();
                },

                showError(message) {
                    alert(message);
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