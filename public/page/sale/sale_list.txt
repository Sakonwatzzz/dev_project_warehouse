<?php
require_once __DIR__ . '../../../../config/database_server.php';

// ต้องกำหนด $sale_id (สมมติว่าได้จาก GET หรือ POST)
// $sale_id = isset($_GET['sale_id']) ? $_GET['sale_id'] : (isset($_POST['sale_id']) ? $_POST['sale_id'] : null);
// if (!$sale_id) {
//     die("Error: sale_id is required.");
// }

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        if (!isset($_POST['barcode']) || !isset($_POST['product_name']) || !isset($_POST['qty']) || !isset($_POST['price']) || !isset($_POST['capital']) || !isset($_POST['sale_product_id'])) {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }

        // Start transaction for consistent data
        $pdo->beginTransaction();

        // Update product name in product table
        $productStmt = $pdo->prepare("UPDATE product SET product_name = ? WHERE product_id = ?");
        $productStmt->execute([$_POST['product_name'], $_POST['barcode']]);

        // Update quantity and price in sale_product table
        $stmt = $pdo->prepare("UPDATE sale_product SET 
            sale_product_qty = ?, 
            sale_product_price = ?,
            sale_product_capital = ?
            WHERE sale_product_id = ? AND sale_id = ?");
        $stmt->execute([
            $_POST['qty'],
            $_POST['price'],
            $_POST['capital'],
            $_POST['sale_product_id'],
            $sale_id
        ]);

        // Commit transaction
        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Handle sale confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sale_id']) && isset($_POST['action']) && $_POST['action'] === 'confirm_sale') {
    try {
        $pdo->beginTransaction();

        // Check if there are any products with missing information
        $check_query = "SELECT sp.product_id, COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name, 
                        sp.sale_product_price, sp.sale_product_capital 
                        FROM sale_product sp
                        LEFT JOIN product p ON p.product_id = sp.product_id
                        WHERE sp.sale_id = ? 
                        AND (p.product_name IS NULL OR p.product_name = 'ยังไม่ระบุ' OR sp.sale_product_price = 0 OR sp.sale_product_capital = 0)";

        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$_POST['sale_id']]);
        $invalid_products = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($invalid_products) > 0) {
            throw new Exception("พบสินค้าที่ข้อมูลไม่ครบถ้วน กรุณาตรวจสอบและแก้ไขก่อนยืนยันการขาย");
        }

        // Update product names if needed
        if (isset($_POST['product_name']) && isset($_POST['barcode'])) {
            $productStmt = $pdo->prepare("UPDATE product SET product_name = ? WHERE product_id = ?");
            $productStmt->execute([$_POST['product_name'], $_POST['barcode']]);
        }

        // Update sale status to confirmed (sale_status_id = 2)
        $updateSale = $pdo->prepare("UPDATE sale SET sale_status_id = 2 WHERE sale_id = ?");
        $updateSale->execute([$_POST['sale_id']]);

        $pdo->commit();

        // Redirect with success message
        header('Location: index.php?page=sale_list&success=1');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // For AJAX requests, return JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }

        // For regular form submissions, redirect with error
        header('Location: index.php?page=sale_view&id=' . $_POST['sale_id'] . '&error=' . urlencode($e->getMessage()));
        exit();
    }
}

// Handle product scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {
    header('Content-Type: application/json');
    try {
        $barcode = $_POST['barcode'];
        $isDeleteMode = isset($_POST['deleteMode']) && $_POST['deleteMode'] === 'true';
        $quantity = isset($_POST['qty']) && is_numeric($_POST['qty']) ? (int) $_POST['qty'] : 1;
        $price = isset($_POST['price']) && is_numeric($_POST['price']) ? (float) $_POST['price'] : 0;
        $capital = isset($_POST['capital']) && is_numeric($_POST['capital']) ? (float) $_POST['capital'] : 0;

        // Check if product exists in sale
        $stmt = $pdo->prepare("SELECT sp.*, p.product_name, p.product_price4, p.product_capital 
                              FROM sale_product sp 
                              LEFT JOIN product p ON p.product_id = sp.product_id 
                              WHERE sp.sale_id = ? AND sp.product_id = ?");
        $stmt->execute([$sale_id, $barcode]);
        $existing_product = $stmt->fetch();

        if ($existing_product) {
            if ($isDeleteMode) {
                $newQty = $existing_product['sale_product_qty'] - $quantity;
                if ($newQty <= 0) {
                    $delete = $pdo->prepare("DELETE FROM sale_product WHERE sale_id = ? AND product_id = ?");
                    $delete->execute([$sale_id, $barcode]);
                } else {
                    $update = $pdo->prepare("UPDATE sale_product SET 
                        sale_product_qty = ?,
                        sale_product_price = ?,
                        sale_product_capital = ?
                        WHERE sale_id = ? AND product_id = ?");
                    $update->execute([$newQty, $existing_product['sale_product_price'], $existing_product['sale_product_capital'], $sale_id, $barcode]);
                }
            } else {
                $newQty = $existing_product['sale_product_qty'] + $quantity;
                $newPrice = $price > 0 ? $price : ($existing_product['product_price4'] ?? $existing_product['sale_product_price']);
                $newCapital = $capital > 0 ? $capital : ($existing_product['product_capital'] ?? $existing_product['sale_product_capital']);

                $update = $pdo->prepare("UPDATE sale_product SET 
                    sale_product_qty = ?,
                    sale_product_price = ?,
                    sale_product_capital = ?
                    WHERE sale_id = ? AND product_id = ?");
                $update->execute([$newQty, $newPrice, $newCapital, $sale_id, $barcode]);
            }
        } else {
            if (!$isDeleteMode) {
                $stmt_product = $pdo->prepare("SELECT product_name, product_price4, product_capital FROM product WHERE product_id = ?");
                $stmt_product->execute([$barcode]);
                $product_exists = $stmt_product->fetch();

                if (!$product_exists) {
                    $insert_product = $pdo->prepare("INSERT INTO product (
                        product_id, 
                        product_name,
                        product_price1,
                        product_price2,
                        product_price3,
                        product_price4,
                        product_capital,
                        product_type_id,
                        product_brand_id
                    ) VALUES (?, ?, 0, 0, 0, ?, ?, NULL, NULL)");
                    $product_name = isset($_POST['product_name']) ? $_POST['product_name'] : "ยังไม่ระบุ";
                    $insert_product->execute([$barcode, $product_name, $price, $capital]);
                } else {
                    $price = $price > 0 ? $price : $product_exists['product_price4'];
                    $capital = $capital > 0 ? $capital : $product_exists['product_capital'];
                }

                $insert = $pdo->prepare("INSERT INTO sale_product (
                    sale_id, 
                    product_id, 
                    sale_product_qty, 
                    sale_product_price, 
                    sale_product_capital, 
                    sale_product_booking, 
                    sale_product_status
                ) VALUES (?, ?, ?, ?, ?, 0, 1)");
                $insert->execute([$sale_id, $barcode, $quantity, $price, $capital]);
            }
        }

        // Update stock in product table (if applicable)
        $stmt_product = $pdo->prepare("SELECT stock FROM product WHERE product_id = ?");
        $stmt_product->execute([$barcode]);
        $product = $stmt_product->fetch();
        if ($product && isset($product['stock'])) {
            $newStock = $isDeleteMode ? $product['stock'] + $quantity : $product['stock'] - $quantity;
            if ($newStock < 0) {
                throw new Exception("สต็อกไม่เพียงพอสำหรับสินค้า $barcode");
            }
            $updateStock = $pdo->prepare("UPDATE product SET stock = ? WHERE product_id = ?");
            $updateStock->execute([$newStock, $barcode]);
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Query sale details
$query = "SELECT
    s.sale_id,
    s.sale_date,
    s.member_id,
    m.member_name,
    m.member_phone,
    m.member_register_date,
    m.member_type_id,
    s.personnel_id,
    p.personnel_name,
    p.personnel_phone,
    p.personnel_type_id,
    s.sale_status_id,
    ss.sale_status_name
FROM
    sale s
    INNER JOIN member m ON s.member_id = m.member_id
    INNER JOIN personnel p ON s.personnel_id = p.personnel_id
    INNER JOIN sale_status ss ON s.sale_status_id = ss.sale_status_id
WHERE s.sale_id = :sale_id";

try {
    $stmt = $pdo->prepare($query);
    // $stmt->execute(['sale_id' => $sale_id]);
    $sale_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query products in sale
    $product_query = "SELECT
        sp.sale_product_id,
        sp.sale_id,
        sp.product_id,
        COALESCE(pr.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
        sp.sale_product_qty,
        sp.sale_product_price,
        sp.sale_product_capital,
        sp.sale_product_booking,
        sp.sale_product_status,
        pr.product_price4,
        pr.product_capital,
        CASE 
            WHEN pr.product_name IS NULL OR pr.product_name = 'ยังไม่ระบุ' OR sp.sale_product_price = 0 OR sp.sale_product_capital = 0 THEN 1
            ELSE 0
        END as needs_update
    FROM
        sale_product sp
        LEFT JOIN product pr ON pr.product_id = sp.product_id
    WHERE 
        sp.sale_id = :sale_id
    ORDER BY 
        needs_update DESC,  
        sp.sale_product_id DESC";

    $stmt = $pdo->prepare($product_query);
    // $stmt->execute(['sale_id' => $sale_id]);
    $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total amount
    $total_amount = 0;
    foreach ($sale_products as $product) {
        $total_amount += $product['sale_product_qty'] * $product['sale_product_price'];
    }
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}

// ดึงข้อมูลสมาชิกจากฐานข้อมูล (สำหรับเลือกสมาชิก)
$query = "SELECT member_id, member_name FROM member ORDER BY member_name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลพนักงานจากฐานข้อมูล (สำหรับเลือกพนักงาน)
$query = "SELECT personnel_id, personnel_name FROM personnel ORDER BY personnel_name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sale_status_id = $sale_data['sale_status_id'] ?? null;
$sale_status_name = $sale_data['sale_status_name'] ?? null;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการการขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold text-center mb-6">รายละเอียดการขาย</h1>

        <!-- ข้อมูลการขาย -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">ข้อมูลการขาย</h2>
            <div class="grid grid-cols-2 gap-4">
                <p><strong>รหัสการขาย:</strong> <?php echo htmlspecialchars($sale_data['sale_id'] ?? 'N/A'); ?></p>
                <p><strong>วันที่ขาย:</strong> <?php echo htmlspecialchars($sale_data['sale_date'] ?? 'N/A'); ?></p>
                <p><strong>สมาชิก:</strong> <?php echo htmlspecialchars($sale_data['member_name'] ?? 'N/A'); ?></p>
                <p><strong>พนักงาน:</strong> <?php echo htmlspecialchars($sale_data['personnel_name'] ?? 'N/A'); ?></p>
                <p><strong>สถานะ:</strong> <?php echo htmlspecialchars($sale_data['sale_status_name'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <!-- ฟอร์มสแกนบาร์โค้ด -->
        <div class="">
            <h2 class="text-xl font-semibold mb-4">สแกนบาร์โค้ดสินค้า</h2>
            <div class="flex space-x-4 mb-4">
                <input type="text" id="barcode" class="p-2 w-full h-10 border focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="รหัสสินค้า">
                <!-- <input type="number" id="qty" class="w-24 p-2 border " min="1" value="1" placeholder="จำนวน"> -->
                <!-- <input type="number" id="price" class="w-24 p-2 border " step="0.01" placeholder="ราคาขาย">
                <input type="number" id="capital" class="w-24 p-2 border " step="0.01" placeholder="ต้นทุน"> -->
                <!-- <button id="scan_product" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    <i class="fas fa-barcode"></i> สแกน
                </button>
                <button id="delete_mode" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                    <i class="fas fa-trash"></i> ลบ
                </button> -->
            </div>
        </div>

        <!-- รายการสินค้า -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">รายการสินค้า</h2>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 p-2">รหัสสินค้า</th>
                            <th class="border border-gray-300 p-2">ชื่อสินค้า</th>
                            <th class="border border-gray-300 p-2">จำนวน</th>
                            <th class="border border-gray-300 p-2">ราคาขาย</th>
                            <th class="border border-gray-300 p-2">ต้นทุน</th>
                            <th class="border border-gray-300 p-2">ยอดรวม</th>
                            <th class="border border-gray-300 p-2">สถานะ</th>
                            <th class="border border-gray-300 p-2">แก้ไข</th>
                        </tr>
                    </thead>
                    <tbody id="product_table_body">
                        <?php foreach ($sale_products as $product): ?>
                            <tr data-id="<?php echo htmlspecialchars($product['sale_product_id']); ?>" class="<?php echo $product['needs_update'] ? 'bg-red-100' : ''; ?>">
                                <td class="border border-gray-300 p-2 text-center"><?php echo htmlspecialchars($product['product_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td class="border border-gray-300 p-2 text-center"><?php echo htmlspecialchars($product['sale_product_qty']); ?></td>
                                <td class="border border-gray-300 p-2 text-right"><?php echo number_format($product['sale_product_price'], 2); ?></td>
                                <td class="border border-gray-300 p-2 text-right"><?php echo number_format($product['sale_product_capital'], 2); ?></td>
                                <td class="border border-gray-300 p-2 text-right"><?php echo number_format($product['sale_product_qty'] * $product['sale_product_price'], 2); ?></td>
                                <td class="border border-gray-300 p-2 text-center"><?php echo $product['sale_product_status'] ? 'ใช้งาน' : 'ไม่ใช้งาน'; ?></td>
                                <td class="border border-gray-300 p-2 text-center">
                                    <button class="edit-product px-2 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600" 
                                            data-id="<?php echo htmlspecialchars($product['sale_product_id']); ?>" 
                                            data-barcode="<?php echo htmlspecialchars($product['product_id']); ?>" 
                                            data-name="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                            data-qty="<?php echo htmlspecialchars($product['sale_product_qty']); ?>" 
                                            data-price="<?php echo htmlspecialchars($product['sale_product_price']); ?>" 
                                            data-capital="<?php echo htmlspecialchars($product['sale_product_capital']); ?>">
                                        <i class="fas fa-edit"></i> แก้ไข
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (empty($sale_products)): ?>
                <p class="text-red-500 mt-2">ไม่พบสินค้าในการขายนี้</p>
            <?php endif; ?>
        </div>

        <!-- เครื่องคิดเลขสำหรับเงินทอน -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">คำนวณเงินทอน</h2>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700">ยอดรวม (บาท):</label>
                    <input type="text" id="total_amount" class="w-full p-2 border rounded-lg bg-gray-100" value="<?php echo number_format($total_amount, 2); ?>" readonly>
                </div>
                <div>
                    <label class="block text-gray-700">เงินที่ชำระ (บาท):</label>
                    <input type="number" id="paid_amount" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" step="0.01" placeholder="ป้อนจำนวนเงิน">
                </div>
                <div>
                    <label class="block text-gray-700">เงินทอน (บาท):</label>
                    <input type="text" id="change_amount" class="w-full p-2 border rounded-lg bg-gray-100" readonly>
                </div>
            </div>
            <div class="flex space-x-4">
                <button id="calculate_change" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    <i class="fas fa-calculator"></i> คำนวณเงินทอน
                </button>
                <button id="print_receipt" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600" disabled>
                    <i class="fas fa-print"></i> พิมพ์ใบเสร็จ
                </button>
            </div>
        </div>

        <!-- ฟอร์มยืนยันการขาย -->
        <div class="text-center mt-6">
            <form method="POST" action="">
                <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                <input type="hidden" name="action" value="confirm_sale">
                <button type="submit" class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600">
                    <i class="fas fa-check"></i> ยืนยันการขาย
                </button>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // สแกนบาร์โค้ด
            $('#scan_product').click(function() {
                let barcode = $('#barcode').val().trim();
                let product_name = $('#product_name').val().trim();
                let qty = $('#qty').val();
                let price = $('#price').val();
                let capital = $('#capital').val();
                let deleteMode = $('#delete_mode').hasClass('bg-red-700');

                if (!barcode) {
                    alert('กรุณาป้อนรหัสสินค้า');
                    return;
                }

                $.ajax({
                    url: '<?php echo basename(__FILE__); ?>',
                    type: 'POST',
                    data: {
                        barcode: barcode,
                        product_name: product_name,
                        qty: qty,
                        price: price,
                        capital: capital,
                        deleteMode: deleteMode
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload(); // รีเฟรชหน้าเพื่ออัปเดตตาราง
                        } else {
                            alert('ข้อผิดพลาด: ' + response.error);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        alert('เกิดข้อผิดพลาดในการสแกน');
                    }
                });
            });

            // สลับโหมดลบ
            $('#delete_mode').click(function() {
                $(this).toggleClass('bg-red-500 bg-red-700');
                let text = $(this).hasClass('bg-red-700') ? 'ยกเลิกโหมดลบ' : 'ลบ';
                $(this).text(text);
            });

            // แก้ไขสินค้า
            $('.edit-product').click(function() {
                let id = $(this).data('id');
                let barcode = $(this).data('barcode');
                let name = $(this).data('name');
                let qty = $(this).data('qty');
                let price = $(this).data('price');
                let capital = $(this).data('capital');

                $('#barcode').val(barcode);
                $('#product_name').val(name);
                $('#qty').val(qty);
                $('#price').val(price);
                $('#capital').val(capital);

                $('#scan_product').off('click').click(function() {
                    $.ajax({
                        url: '<?php echo basename(__FILE__); ?>',
                        type: 'POST',
                        data: {
                            action: 'update',
                            sale_product_id: id,
                            barcode: $('#barcode').val(),
                            product_name: $('#product_name').val(),
                            qty: $('#qty').val(),
                            price: $('#price').val(),
                            capital: $('#capital').val()
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('ข้อผิดพลาด: ' + response.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error);
                            alert('เกิดข้อผิดพลาดในการอัปเดต');
                        }
                    });
                });
            });

            // รีเซ็ตฟอร์มหลังสแกน
            $('#barcode').on('keypress', function(e) {
                if (e.which == 13) {
                    $('#scan_product').click();
                    $('#barcode').val('');
                    $('#product_name').val('');
                    $('#qty').val('1');
                    $('#price').val('');
                    $('#capital').val('');
                }
            });

            // คำนวณเงินทอน
            $('#calculate_change').click(function() {
                let total_amount = parseFloat($('#total_amount').val().replace(/,/g, '')) || 0;
                let paid_amount = parseFloat($('#paid_amount').val()) || 0;

                if (paid_amount < total_amount) {
                    alert('เงินที่ชำระไม่เพียงพอ');
                    $('#change_amount').val('');
                    $('#print_receipt').prop('disabled', true);
                    return;
                }

                let change = paid_amount - total_amount;
                $('#change_amount').val(change.toFixed(2));
                $('#print_receipt').prop('disabled', false);
            });

            // พิมพ์ใบเสร็จ
            $('#print_receipt').click(function() {
                let total_amount = parseFloat($('#total_amount').val().replace(/,/g, '')) || 0;
                let paid_amount = parseFloat($('#paid_amount').val()) || 0;
                let change_amount = parseFloat($('#change_amount').val()) || 0;

                let receiptWindow = window.open('', '_blank');
                receiptWindow.document.write(`
                    <html>
                    <head>
                        <title>ใบเสร็จการขาย</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .receipt { max-width: 600px; margin: auto; }
                            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            th { background-color: #f2f2f2; }
                            .text-right { text-align: right; }
                            .text-center { text-align: center; }
                        </style>
                    </head>
                    <body>
                        <div class="receipt">
                            <h2 class="text-center">ใบเสร็จการขาย</h2>
                            <p><strong>รหัสการขาย:</strong> <?php echo htmlspecialchars($sale_data['sale_id'] ?? 'N/A'); ?></p>
                            <p><strong>วันที่:</strong> <?php echo htmlspecialchars($sale_data['sale_date'] ?? 'N/A'); ?></p>
                            <p><strong>สมาชิก:</strong> <?php echo htmlspecialchars($sale_data['member_name'] ?? 'N/A'); ?></p>
                            <p><strong>พนักงาน:</strong> <?php echo htmlspecialchars($sale_data['personnel_name'] ?? 'N/A'); ?></p>
                            <table>
                                <thead>
                                    <tr>
                                        <th>รหัสสินค้า</th>
                                        <th>ชื่อสินค้า</th>
                                        <th>จำนวน</th>
                                        <th>ราคา</th>
                                        <th>ยอดรวม</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sale_products as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($product['sale_product_qty']); ?></td>
                                            <td class="text-right"><?php echo number_format($product['sale_product_price'], 2); ?></td>
                                            <td class="text-right"><?php echo number_format($product['sale_product_qty'] * $product['sale_product_price'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="text-right"><strong>ยอดรวม:</strong> <?php echo number_format($total_amount, 2); ?> บาท</p>
                            <p class="text-right"><strong>เงินที่ชำระ:</strong> ${paid_amount.toFixed(2)} บาท</p>
                            <p class="text-right"><strong>เงินทอน:</strong> ${change_amount.toFixed(2)} บาท</p>
                            <p class="text-center" style="margin-top: 20px;">ขอบคุณที่ใช้บริการ</p>
                        </div>
                        <script>
                            window.print();
                        </script>
                    </body>
                    </html>
                `);
                receiptWindow.document.close();
            });

            // คำนวณเงินทอนเมื่อกด Enter ในช่องเงินที่ชำระ
            $('#paid_amount').on('keypress', function(e) {
                if (e.which == 13) {
                    $('#calculate_change').click();
                }
            });
        });
    </script>
</body>
</html>