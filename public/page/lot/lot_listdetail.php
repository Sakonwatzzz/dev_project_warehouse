<?php
require_once __DIR__ . '../../../../config/database_server.php';

// รับค่า lot_id จาก URL
$lot_id = $_GET['id'] ?? null;

// ตรวจสอบว่า lot_id มีค่าหรือไม่
if (!$lot_id) {
    die(json_encode(['success' => false, 'error' => 'lot_id is missing. Debug: ' . json_encode($_GET)]));
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        if (!isset($_POST['barcode']) || !isset($_POST['product_name']) || !isset($_POST['qty']) || !isset($_POST['capital']) || !isset($_POST['lot_product_id'])) {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }

        // Start transaction for consistent data
        $pdo->beginTransaction();

        // Update product name and capital in product table
        $productStmt = $pdo->prepare("UPDATE product SET 
            product_name = ?,
            product_capital = ?
            WHERE product_id = ?");
        $productStmt->execute([$_POST['product_name'], $_POST['capital'], $_POST['barcode']]);

        // Update quantity and price in lot_product table
        $stmt = $pdo->prepare("UPDATE lot_product SET 
            lot_product_qty = ?, 
            lot_product_remain = ?,
            lot_product_capital = ?
            WHERE lot_product_id = ? AND lot_id = ?");
        $stmt->execute([
            $_POST['qty'],
            $_POST['qty'], // Set remain to same as qty
            $_POST['capital'],
            $_POST['lot_product_id'],
            $lot_id
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lot_id'])) {
    try {
        $pdo->beginTransaction();

        // First check if there are any products with missing information
        $check_query = "SELECT lp.product_id, COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name, 
                        lp.lot_product_capital 
                        FROM lot_product lp
                        LEFT JOIN product p ON p.product_id = lp.product_id
                        WHERE lp.lot_id = ? 
                        AND (p.product_name IS NULL OR p.product_name = 'ยังไม่ระบุ' OR lp.lot_product_capital = 0)";

        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$_POST['lot_id']]);
        $invalid_products = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($invalid_products) > 0) {
            // There are products with missing information
            throw new Exception("พบสินค้าที่ข้อมูลไม่ครบถ้วน กรุณาตรวจสอบและแก้ไขก่อนยืนยันรายการ");
        }

        // Update product names if needed
        if (isset($_POST['product_name']) && isset($_POST['barcode'])) {
            $productStmt = $pdo->prepare("UPDATE product SET product_name = ? WHERE product_id = ?");
            $productStmt->execute([$_POST['product_name'], $_POST['barcode']]);
        }

        // Update lot status to confirmed (status_id = 2)
        $updateLot = $pdo->prepare("UPDATE lot SET lot_status_id = 2 WHERE lot_id = ?");
        $updateLot->execute([$_POST['lot_id']]);

        $pdo->commit();

        // Redirect with success message
        header('Location: index.php?page=lot_listinsert&success=1');
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
        header('Location: index.php?page=lot_view&id=' . $_POST['lot_id'] . '&error=' . urlencode($e->getMessage()));
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
        $capital = isset($_POST['capital']) && is_numeric($_POST['capital']) ? (float) $_POST['capital'] : null;

        // Start transaction
        $pdo->beginTransaction();

        // Check if product exists in lot
        $stmt = $pdo->prepare("SELECT lp.*, p.product_name, p.product_capital 
                              FROM lot_product lp 
                              LEFT JOIN product p ON p.product_id = lp.product_id 
                              WHERE lp.lot_id = ? AND lp.product_id = ?");
        $stmt->execute([$lot_id, $barcode]);
        $existing_product = $stmt->fetch();

        if ($existing_product) {
            if ($isDeleteMode) {
                $newQty = $existing_product['lot_product_qty'] - $quantity;
                if ($newQty <= 0) {
                    $delete = $pdo->prepare("DELETE FROM lot_product WHERE lot_id = ? AND product_id = ?");
                    $delete->execute([$lot_id, $barcode]);
                } else {
                    $update = $pdo->prepare("UPDATE lot_product SET 
                        lot_product_qty = ?,
                        lot_product_remain = ?
                        WHERE lot_id = ? AND product_id = ?");
                    $update->execute([$newQty, $newQty, $lot_id, $barcode]);
                }
            } else {
                $newQty = $existing_product['lot_product_qty'] + $quantity;
                // Use provided capital, or fall back to existing lot_product_capital
                $newCapital = $capital !== null ? $capital : $existing_product['lot_product_capital'];

                $update = $pdo->prepare("UPDATE lot_product SET 
                    lot_product_qty = ?,
                    lot_product_remain = ?,
                    lot_product_capital = ?
                    WHERE lot_id = ? AND product_id = ?");
                $update->execute([$newQty, $newQty, $newCapital, $lot_id, $barcode]);

                // Update product table with new capital if provided
                if ($capital !== null) {
                    $updateProduct = $pdo->prepare("UPDATE product SET product_capital = ? WHERE product_id = ?");
                    $updateProduct->execute([$capital, $barcode]);
                }
            }
        } else {
            if (!$isDeleteMode) {
                $stmt_product = $pdo->prepare("SELECT product_id, product_name, product_capital 
                                              FROM product WHERE product_id = ?");
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
                    ) VALUES (?, ?, 0, 0, 0, 0, ?, NULL, NULL)");

                    $product_name = isset($_POST['product_name']) ? $_POST['product_name'] : "ยังไม่ระบุ";
                    $insert_product->execute([$barcode, $product_name, $capital ?? 0]);
                } else {
                    // If product exists and capital is provided, update product_capital
                    if ($capital !== null) {
                        $updateProduct = $pdo->prepare("UPDATE product SET product_capital = ? WHERE product_id = ?");
                        $updateProduct->execute([$capital, $barcode]);
                    }
                }

                // Use provided capital, or fall back to product_capital if exists, or 0
                $finalCapital = $capital ?? ($product_exists['product_capital'] ?? 0);

                $insert = $pdo->prepare("INSERT INTO lot_product (
                    lot_id, 
                    product_id, 
                    lot_product_qty, 
                    lot_product_remain,
                    lot_product_capital, 
                    lot_product_date,
                    lot_product_status
                ) VALUES (?, ?, ?, ?, ?, NOW(), 1)");

                $insert->execute([$lot_id, $barcode, $quantity, $quantity, $finalCapital]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

$query = "SELECT
    l.lot_id,
    l.lot_name,
    l.lot_detail,
    l.lot_date,
    l.warehouse_from_id,
    w1.warehouse_name as warehouse_from_name,  -- ดึงชื่อคลังต้นทาง
    l.warehouse_inout,
    l.warehouse_id,
    w2.warehouse_name,  -- ดึงชื่อคลังปลายทาง
    sup.supplier_id,
    sup.supplier_name,
    ls.lot_status_id,
    ls.lot_status_name
FROM
    lot l
    LEFT JOIN warehouse w1 ON l.warehouse_from_id = w1.warehouse_id  -- คลังต้นทาง
    LEFT JOIN warehouse w2 ON l.warehouse_id = w2.warehouse_id  -- คลังปลายทาง
    INNER JOIN supplier sup ON l.supplier_id = sup.supplier_id
    INNER JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
WHERE l.lot_id = :lot_id";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot_data) {
        die("ไม่พบข้อมูลล็อตสินค้า");
    }

    // Query products in lot
    $product_query = "SELECT
        lp.lot_id,
        lp.lot_product_id,
        lp.product_id,
        COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
        lp.lot_product_qty,
        lp.lot_product_remain,
        lp.lot_product_capital,
        p.product_capital,
        lp.lot_product_date,
        lp.lot_product_status,
        CASE 
            WHEN p.product_id IS NULL THEN 1
            ELSE 0
        END as needs_update
    FROM
        lot_product lp
        LEFT JOIN product p ON p.product_id = lp.product_id
    WHERE 
        lp.lot_id = :lot_id
    ORDER BY 
        needs_update DESC,  
        lp.lot_product_date DESC";

    $stmt = $pdo->prepare($product_query);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}
// ดึงข้อมูลคลังสินค้าจากฐานข้อมูล
$query = "SELECT warehouse_id, warehouse_name FROM warehouse ORDER BY warehouse_name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lot_status_id = $lot_data['lot_status_id'] ?? null;
$lot_status_name = $lot_data['lot_status_name'] ?? null;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดล็อตสินค้า</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #f8f9fa;
        }

        .card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .highlight-row {
            background-color: #fff3cd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f3f4f6;
            text-align: left;
            padding: 0.75rem;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        td {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .total-row {
            background-color: #f9fafb;
            font-weight: 500;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        /* Add the styles for delete mode */
        .bg-pink-100 {
            background-color: #fbcfe8 !important;
        }

        .bg-pink-500 {
            background-color: #ec4899 !important;
        }

        /* Override hover behavior when in pink-100 mode */
        .bg-pink-100:hover {
            background-color: #fbcfe8 !important;
        }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>

<body class="min-h-screen">
    <div class="mx-auto px-4 py-8 container max-w-8xl">
        <h1 class="text-4xl font-bold text-gray-800 mb-6">รายละเอียดล็อตสินค้า</h1>
        <?php if ($lot_data): ?>
            <div class="card mb-6">
                <div class="text-end mb-4">
                    <span class="<?php echo $lot_data['lot_status_id'] == 1 ? 'text-yellow-600' : 'text-green-600'; ?> font-semibold">
                        <i class="fas <?php echo $lot_data['lot_status_id'] == 1 ? 'fa-clock mr-1' : 'fa-check-circle mr-1'; ?>"></i>
                        <?php echo htmlspecialchars($lot_data['lot_status_name']); ?>
                    </span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-center">
                        <span class="text-gray-600 font-medium w-40">รหัส Lot สินค้า:</span>
                        <span><?php echo htmlspecialchars($lot_data['lot_id']); ?></span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-600 font-medium w-40">ชื่อ Lot สินค้า:</span>
                        <span><?php echo htmlspecialchars($lot_data['lot_name']); ?></span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-600 font-medium w-40">วันที่รับสินค้า:</span>
                        <span><?php echo htmlspecialchars($lot_data['lot_date']); ?></span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-600 font-medium w-40">รับมาจากผู้ส่งมอบ:</span>
                        <span><?php echo htmlspecialchars($lot_data['supplier_name']); ?></span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-600 font-medium w-40">ลงคลัง:</span>
                        <span><?php echo htmlspecialchars($lot_data['warehouse_name']); ?></span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-600 font-medium w-40">นำมาจากคลัง:</span>
                        <span>
                            <?php
                            if (!empty($lot_data['warehouse_from_name'])) {
                                echo htmlspecialchars($lot_data['warehouse_from_name']);
                            } elseif (!empty($lot_data['warehouse_from_id'])) {
                                echo htmlspecialchars($lot_data['warehouse_from_id']) . " (ไม่พบชื่อคลัง)";
                            } else {
                                echo "ยังไม่ระบุ";
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex items-center">
                        <?php
                        if (!empty($lot_data['warehouse_inout'])) {
                            if ($lot_data['warehouse_inout'] == 1) {
                                echo '<span class="text-green-600"><i class="fas fa-sign-in-alt mr-1"></i>รับเข้า</span>';
                            } elseif ($lot_data['warehouse_inout'] == 2) {
                                echo '<span class="text-red-600"><i class="fas fa-sign-out-alt mr-1"></i>นำออก</span>';
                            } else {
                                echo htmlspecialchars($lot_data['warehouse_inout']);
                            }
                        } else {
                            echo "ยังไม่ระบุ";
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว"): ?>
            <div class="card mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">สแกนสินค้าเข้าล็อต</h2>
                <form id="barcode-form" class="space-y-4">
                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <label class="block text-gray-600 font-medium w-32" for="product_id">รหัสบาร์โค้ด:</label>
                        <div class="flex-1">
                            <input type="text" id="inputField" name="product_id"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                pattern="[0-9*\-]*" placeholder="เช่น 12345*2*150 หรือ -12345*3" autofocus>
                        </div>
                        <!-- <button type="submit" class="btn bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                            <i class="fas fa-plus mr-2"></i>เพิ่ม/ลบ
                        </button> -->
                    </div>
                    <div class="flex flex-row space-x-8">
                        <p class="text-sm text-green-700 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            รูปแบบการเพิ่ม: รหัส*จำนวน*ราคาทุน (เช่น 12345*2*150)
                        </p>
                        <p class="text-sm text-red-700 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            รูปแบบการลบ: -รหัส*จำนวน (เช่น -12345*3)
                        </p>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">รายการสินค้าที่สแกน</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full" id="products-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">รหัสบาร์โค้ด</th>
                            <th class="px-4 py-3 text-left">ชื่อสินค้า</th>
                            <th class="px-4 py-3 text-center">จำนวน</th>
                            <th class="px-4 py-3 text-right">ราคาทุน</th>
                            <th class="px-4 py-3 text-right">มูลค่ารวม</th>
                            <th class="px-4 py-3 text-center">สถานะ</th>
                            <?php if (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว"): ?>
                                <th class="px-4 py-3 text-center">การจัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($lot_products) == 0) {
                            echo '<tr><td colspan="' . (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว" ? 7 : 6) . '" class="text-center py-4 text-gray-500">ไม่พบรายการสินค้า</td></tr>';
                        } else {
                            $total_value = 0;
                            $total_quantity = 0;
                            foreach ($lot_products as $index => $item):
                                $item_total = $item['lot_product_qty'] * $item['lot_product_capital'];
                                $total_value += $item_total;
                                $total_quantity += $item['lot_product_qty'];
                        ?>
                                <tr class="hover:bg-gray-50" data-product-id="<?php echo htmlspecialchars($item['product_id']); ?>">
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($item['product_id']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td class="px-4 py-3 text-center"><?php echo htmlspecialchars($item['lot_product_qty']); ?></td>
                                    <td class="px-4 py-3 text-right"><?php echo number_format($item['lot_product_capital'], 2); ?></td>
                                    <td class="px-4 py-3 text-right"><?php echo number_format($item_total, 2); ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full <?php echo $lot_data['lot_status_id'] == 2 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <i class="fas <?php echo $lot_data['lot_status_id'] == 2 ? 'fa-check mr-1' : 'fa-clock mr-1'; ?>"></i>
                                            <?php echo htmlspecialchars($lot_data['lot_status_name']); ?>
                                        </span>
                                    </td>
                                    <?php if (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว"): ?>
                                        <td class="px-4 py-3 text-center">
                                            <button type="button" class="btn bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded mr-1 edit-product"
                                                data-id="<?php echo htmlspecialchars($item['lot_product_id']); ?>"
                                                data-qty="<?php echo htmlspecialchars($item['lot_product_qty']); ?>"
                                                data-capital="<?php echo htmlspecialchars($item['product_capital'] ?? $item['lot_product_capital']); ?>"
                                                data-name="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                data-barcode="<?php echo htmlspecialchars($item['product_id']); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded toggle-delete-mode"
                                                data-barcode="<?php echo htmlspecialchars($item['product_id']); ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php
                            endforeach;
                            ?>
                            <tr class="total-row border-t-2 border-gray-200">
                                <td colspan="2" class="px-4 py-3 text-right font-medium">ยอดรวม:</td>
                                <td class="px-4 py-3 text-center font-bold"><?php echo number_format($total_quantity, 0); ?></td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right font-bold"><?php echo number_format($total_value, 2); ?> บาท</td>
                                <td colspan="<?php echo (!is_null($lot_status_id) && $lot_status_id != 2 && $lot_status_name !== "ยืนยันแล้ว" ? 2 : 1); ?>" class="px-4 py-3"></td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($lot_products) > 0 && $lot_data['lot_status_id'] == 1): ?>
                <div class="mt-6 text-center sm:text-right">
                    <form id="confirm-form" method="POST" action="" onsubmit="return validateForm(event)">
                        <button type="submit" class="btn bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                            <i class="fas fa-check-circle mr-2"></i>ยืนยันรายการ
                        </button>
                        <input type="hidden" name="lot_id" value="<?php echo $lot_data['lot_id']; ?>">
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between mt-6">
            <a href="?page=lot_listinsert" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">
                <i class="fas fa-arrow-left mr-2"></i>กลับ
            </a>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-800">แก้ไขข้อมูลสินค้า</h2>
                <button type="button" id="close-modal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="edit-form" class="space-y-4">
                <input type="hidden" id="lot_product_id" name="lot_product_id">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="barcode" id="product_barcode_hidden">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">บาร์โค้ด:</label>
                    <input type="text" class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100" id="product_barcode" readonly>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">ชื่อสินค้า:</label>
                    <input type="text" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="product_name" name="product_name">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">จำนวน:</label>
                    <input type="number" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="qty" name="qty" required min="1">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">ราคาทุน:</label>
                    <input type="number" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="capital" name="capital" required min="0" step="0.01">
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" class="btn bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium" onclick="closeEditModal()">ยกเลิก</button>
                    <button type="button" class="btn bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg font-medium" id="save-edit">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function validateForm(event) {
            event.preventDefault(); // Prevent form submission before validation

            // Select only product rows, not the summary row
            const productRows = document.querySelectorAll('#products-table tbody tr:not(.total-row)');

            if (productRows.length === 0) {
                Swal.fire({
                    icon: "warning",
                    title: "ไม่มีสินค้า",
                    text: "กรุณาเพิ่มสินค้าก่อนยืนยันรายการ",
                    confirmButtonColor: "#3085d6"
                });
                return false;
            }

            // Collect items with incomplete data
            let invalidProducts = [];
            productRows.forEach(row => {
                const barcode = row.cells[0].textContent.trim();
                const productName = row.cells[1].textContent.trim();
                const capital = parseFloat(row.cells[3].textContent.replace(/,/g, '')) || 0;

                // Check product name and capital price
                if (productName === "ยังไม่ระบุ" || productName === "ไม่มีสินค้าในระบบ" || capital === 0) {
                    invalidProducts.push({
                        barcode: barcode,
                        name: productName,
                        capital: capital
                    });
                }
            });

            // If there are invalid products
            if (invalidProducts.length > 0) {
                let errorHTML = "<div style='text-align:left; max-height:300px; overflow-y:auto;'>";
                errorHTML += "<p>กรุณาตรวจสอบและแก้ไขข้อมูลสินค้าต่อไปนี้ก่อนยืนยัน:</p><ul>";

                invalidProducts.forEach(product => {
                    errorHTML += `<li>บาร์โค้ด <b>${product.barcode}</b>: `;
                    let issues = [];
                    if (product.name === "ยังไม่ระบุ" || product.name === "ไม่มีสินค้าในระบบ") {
                        issues.push("ชื่อสินค้าไม่ถูกต้อง");
                    }
                    if (product.capital === 0) {
                        issues.push("ราคาทุนเป็น 0");
                    }
                    errorHTML += issues.join(" และ ") + "</li>";
                });

                errorHTML += "</ul></div>";

                Swal.fire({
                    icon: "warning",
                    title: "ข้อมูลไม่ครบถ้วน",
                    html: errorHTML,
                    confirmButtonColor: "#3085d6",
                    confirmButtonText: "ตกลง"
                });
                return false;
            }

            // If all data is complete, show confirmation dialog
            Swal.fire({
                icon: "question",
                title: "ยืนยันรายการ?",
                text: "คุณต้องการยืนยันรายการนี้หรือไม่?",
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#d33",
                confirmButtonText: "ใช่, ยืนยัน",
                cancelButtonText: "ยกเลิก"
            }).then((result) => {
                if (result.isConfirmed) {
                    // Disable the button to prevent multiple clicks
                    let confirmBtn = document.querySelector('#confirm-form button[type="submit"]');
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังดำเนินการ...';
                    }

                    // Submit the form
                    document.getElementById("confirm-form").submit();
                }
            });

            return false; // Prevent automatic form submission
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal
        $('#cancel-edit, #close-modal').on('click', function() {
            $('#editModal').fadeOut(200);
        });
      
        const inputField = document.getElementById('inputField');

        // ฟังก์ชันสำหรับการเปลี่ยนสีพื้นหลังตามการกดปุ่ม
        function changeInputBackground(event) {
            if (event.keyCode === 46) { // 46 = Delete
                inputField.classList.remove('bg-white');
                inputField.classList.add('bg-pink-100'); // เพิ่มคลาสสำหรับการลบข้อมูล
            }

            if (event.keyCode === 45) { // 45 = Insert
                inputField.classList.remove('bg-white');
                inputField.classList.add('bg-green-100'); // เพิ่มคลาสสำหรับการเพิ่มข้อมูล
            }
        }

        // ฟังก์ชันสำหรับการรีเซ็ตพื้นหลังเมื่อเริ่มกรอกข้อมูล
        function resetInputBackground() {
            inputField.classList.remove('bg-pink-100');
            inputField.classList.remove('bg-green-100');
            inputField.classList.add('bg-white');
        }

        // ฟังการกดปุ่มและการเริ่มกรอกข้อมูล
        inputField.addEventListener('keydown', function(event) {
            changeInputBackground(event); // เปลี่ยนพื้นหลังตามการกดปุ่ม
        });

        // รีเซ็ตพื้นหลังเมื่อเริ่มกรอกข้อมูล
        inputField.addEventListener('focus', function() {
            resetInputBackground(); // รีเซ็ตพื้นหลังเมื่อเริ่มกรอกข้อมูล
        });

        $(document).ready(function() {
            // Shared state
            const state = {
                activeDeleteBarcode: null,
                barcodeInput: $('input[name="product_id"]')
            };

            // Utility functions
            const utils = {
                handleServerResponse: function(response) {
                    console.log('Server response:', response);
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (response.error || 'กรุณาลองใหม่อีกครั้ง'));
                    }
                },

                handleServerError: function(xhr) {
                    console.error('Server response:', xhr.responseText);
                    try {
                        const htmlContent = xhr.responseText;
                        const jsonMatch = htmlContent.match(/\{.*\}/);
                        if (jsonMatch) {
                            const jsonResponse = JSON.parse(jsonMatch[0]);
                            if (jsonResponse.success) {
                                location.reload();
                                return;
                            }
                        }
                    } catch (e) {
                        console.error('JSON parsing error:', e);
                    }
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง');
                    state.barcodeInput.focus();
                },

                sendRequest: function(data) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: data,
                        dataType: 'json',
                        success: function(response) {
                            utils.handleServerResponse(response);
                        },
                        error: function(xhr) {
                            utils.handleServerError(xhr);
                        }
                    });
                },

                validateFormFields: function(qty, capital, productName) {
                    if (!productName || productName.trim() === '') {
                        alert('กรุณาระบุชื่อสินค้า');
                        $('#product_name').focus();
                        return false;
                    }

                    if (qty <= 0) {
                        alert('จำนวนสินค้าต้องมากกว่า 0');
                        $('#qty').focus();
                        return false;
                    }

                    if (capital < 0) {
                        alert('ราคาทุนต้องไม่ต่ำกว่า 0');
                        $('#capital').focus();
                        return false;
                    }

                    return true;
                },

                updateRowHighlighting: function() {
                    // Remove highlight from all rows
                    $('#products-table tbody tr').removeClass('bg-pink-100');

                    // Add highlight to the active delete row
                    if (state.activeDeleteBarcode) {
                        $(`#products-table tbody tr[data-product-id="${state.activeDeleteBarcode}"]`).addClass('bg-pink-100');
                    }
                }
            };

            // Event handlers
            const handlers = {
                toggleDeleteMode: function(e) {
                    const $target = $(e.target);
                    const barcode = $target.data('barcode');
                    const isActive = state.activeDeleteBarcode === barcode;

                    // Reset all buttons
                    $('.toggle-delete-mode')
                        .removeClass('bg-pink-500')
                        .addClass('bg-red-500')
                        .html('<i class="fas fa-trash-alt"></i>');

                    if (!isActive) {
                        $target
                            .removeClass('bg-red-500')
                            .addClass('bg-pink-500')
                            .html('<i class="fas fa-times"></i>');
                        state.barcodeInput.addClass('bg-pink-100');
                        state.activeDeleteBarcode = barcode;
                    } else {
                        state.barcodeInput.removeClass('bg-pink-100');
                        state.activeDeleteBarcode = null;
                    }

                    utils.updateRowHighlighting();
                    state.barcodeInput.focus();
                },

                handleBarcodeInput: function(e) {
                    if (e.which === 13) { // Enter key
                        e.preventDefault();
                        const inputValue = $(e.target).val();

                        // Split input by "*" delimiter
                        const parts = inputValue.split('*');
                        let product_id = parts[0]; // First part is always the barcode

                        // Check if the barcode starts with '-', indicating delete mode
                        let isDeleteMode = state.activeDeleteBarcode !== null;
                        if (product_id.startsWith('-')) {
                            isDeleteMode = true;
                            product_id = product_id.substring(1); // Remove the '-' prefix
                        }

                        // Create the request data
                        let requestData = {
                            barcode: product_id,
                            deleteMode: isDeleteMode
                        };

                        // If there's a quantity specified (format: barcode*qty)
                        if (parts.length > 1 && parts[1]) {
                            requestData.qty = parseInt(parts[1]);
                        }

                        // If there's a capital price specified (format: barcode*qty*price)
                        if (parts.length > 2 && parts[2]) {
                            requestData.capital = parseFloat(parts[2]);
                        }

                        // Clear the input field
                        $(e.target).val('');

                        // Send the request with all gathered data
                        utils.sendRequest(requestData);

                        // If we're not in delete mode initiated by '-', keep the active delete barcode
                        if (!product_id.startsWith('-') && isDeleteMode) {
                            state.activeDeleteBarcode = product_id;
                        } else {
                            state.activeDeleteBarcode = null;
                        }
                    }
                },

                handleEditProduct: function(e) {
                    const $target = $(e.currentTarget);
                    const productData = {
                        id: $target.data('id'),
                        qty: $target.data('qty'),
                        capital: $target.data('capital'),
                        name: $target.data('name'),
                        barcode: $target.data('barcode')
                    };

                    console.log('Edit product data:', productData);

                    $('#lot_product_id').val(productData.id);
                    $('#product_barcode').val(productData.barcode);
                    $('#product_barcode_hidden').val(productData.barcode);
                    $('#product_name').val(productData.name);
                    $('#qty').val(productData.qty);
                    $('#capital').val(productData.capital);

                    $('#editModal').fadeIn(200);
                    $('#product_name').focus();
                },

                handleSaveEdit: function(e) {
                    const form = $('#edit-form');
                    const $submitButton = $(e.target);

                    if (!form[0].checkValidity()) {
                        form[0].reportValidity();
                        return;
                    }

                    const qty = parseFloat($('#qty').val());
                    const capital = parseFloat($('#capital').val());
                    const productName = $('#product_name').val();

                    if (!utils.validateFormFields(qty, capital, productName)) {
                        return;
                    }

                    $submitButton.prop('disabled', true).text('กำลังบันทึก...');

                    const formData = form.serialize();
                    console.log('Sending data:', formData);

                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: formData,
                        success: function(response) {
                            utils.handleServerResponse(response);
                            closeEditModal();
                        },
                        error: function(xhr) {
                            utils.handleServerError(xhr);
                        },
                        complete: function() {
                            $submitButton.prop('disabled', false).text('บันทึก');
                        }
                    });
                },

                handleInputValidation: function(e) {
                    const $input = $(e.target);
                    const value = parseFloat($input.val());

                    const minValue = $input.attr($input.attr('id') === 'qty' ? 0 : -1);

                    $input.toggleClass('border-red-500', value <= minValue);
                }
            };

            // Event bindings
            $('.toggle-delete-mode').on('click', handlers.toggleDeleteMode);
            state.barcodeInput.on('keypress', handlers.handleBarcodeInput);
            $('.edit-product').on('click', handlers.handleEditProduct);
            $('#save-edit').on('click', handlers.handleSaveEdit);
            $('#qty, #capital').on('input', handlers.handleInputValidation);

            // Modal close handlers
            $('#editModal').on('click', function(e) {
                if (e.target === this) closeEditModal();
            });

            // Close modal on Escape key
            $(document).keydown(function(e) {
                if (e.key === "Escape" && $('#editModal').is(':visible')) {
                    closeEditModal();
                }
            });
        });
    </script>
</body>
</html>