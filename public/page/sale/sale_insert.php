<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../config/database_server.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

// กำหนด timezone
date_default_timezone_set('Asia/Bangkok');
$currentDateTime = date("Y-m-d H:i:s");
$displayDateTime = date("d/m/Y H:i:s");

// รับ sale_id
$sale_id = $_POST['sale_id'] ?? $_GET['sale_id'] ?? $_SESSION['sale_id'] ?? null;

// ตรวจสอบหรือสร้าง sale ใหม่
if (!$sale_id && (!isset($_GET['page']) || $_GET['page'] !== 'sale_list')) {
    try {
        $pdo->beginTransaction();
        $insertSale = $pdo->prepare("INSERT INTO sale (sale_date, sale_status_id ,member_id, personnel_id) VALUES (?, 1,?,?,)");
        $insertSale->execute([$currentDateTime]);
        $sale_id = $pdo->lastInsertId();
        $_SESSION['sale_id'] = $sale_id;
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        die("Error initializing sale: " . $e->getMessage());
    }
}

if (!$sale_id) {
    die("Error: sale_id is required.");
}

/**
 * ฟังก์ชันช่วยแก้ไขยอดคงเหลือใน lot_product
 * @param PDO $pdo
 * @param string $productId
 * @param int $qty
 * @param bool $addMode
 */
function adjustLotRemain(PDO $pdo, string $productId, int $qty, bool $addMode = false)
{
    $remainingToProcess = $qty;
    while ($remainingToProcess > 0) {
        $stmtLot = $pdo->prepare("
            SELECT lot_product_id, lot_product_remain 
            FROM lot_product 
            WHERE product_id = ? AND lot_product_remain > 0
            ORDER BY lot_product_date ASC, lot_product_id ASC
            LIMIT 1
        ");
        $stmtLot->execute([$productId]);
        $lot = $stmtLot->fetch(PDO::FETCH_ASSOC);

        if (!$lot) {
            throw new Exception("สต็อกล็อตไม่เพียงพอสำหรับสินค้า $productId");
        }

        $current = (int) $lot['lot_product_remain'];
        if ($addMode) {
            $newRemain = $current + $remainingToProcess;
            $remainingToProcess = 0;
        } else {
            if ($current >= $remainingToProcess) {
                $newRemain = $current - $remainingToProcess;
                $remainingToProcess = 0;
            } else {
                $newRemain = 0;
                $remainingToProcess -= $current;
            }
        }

        $updateLot = $pdo->prepare("
            UPDATE lot_product 
            SET lot_product_remain = ? 
            WHERE lot_product_id = ?
        ");
        $updateLot->execute([$newRemain, $lot['lot_product_id']]);
    }
}

// Handle product add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode']) && !isset($_POST['action'])) {
    try {
        $barcode = trim($_POST['barcode']);
        $qty = is_numeric($_POST['qty'] ?? null) ? (int) $_POST['qty'] : 1;
        $price = is_numeric($_POST['price'] ?? null) ? (float) $_POST['price'] : 0;
        $capital = is_numeric($_POST['capital'] ?? null) ? (float) $_POST['capital'] : 0;

        if (empty($barcode)) {
            $_SESSION['error'] = "กรุณาป้อนรหัสสินค้า";
            header("Location: http://localhost:8000/index.php?page=sale_insert&sale_id=$sale_id");
            exit();
        }

        $pdo->beginTransaction();

        // ตรวจสอบสินค้าใน sale_product
        $stmt = $pdo->prepare("
            SELECT sale_product_qty, sale_product_price, sale_product_capital 
            FROM sale_product 
            WHERE sale_id = ? AND product_id = ?
        ");
        $stmt->execute([$sale_id, $barcode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = $existing['sale_product_qty'] + $qty;
            $newPrice = $price > 0 ? $price : $existing['sale_product_price'];
            $newCapital = $capital > 0 ? $capital : $existing['sale_product_capital'];

            $pdo->prepare("
                UPDATE sale_product 
                SET sale_product_qty = ?, sale_product_price = ?, sale_product_capital = ? 
                WHERE sale_id = ? AND product_id = ?
            ")->execute([$newQty, $newPrice, $newCapital, $sale_id, $barcode]);

            adjustLotRemain($pdo, $barcode, $qty, false);
        } else {
            $stmtP = $pdo->prepare("
                SELECT product_name, product_price4, product_capital 
                FROM product 
                WHERE product_id = ?
            ");
            $stmtP->execute([$barcode]);
            $prod = $stmtP->fetch(PDO::FETCH_ASSOC);
            if (!$prod) {
                throw new Exception("ไม่พบข้อมูลสินค้าหรือไม่มีสินค้า");
            }
            $unitPrice = $price > 0 ? $price : $prod['product_price4'];
            $unitCapital = $capital > 0 ? $capital : $prod['product_capital'];

            $pdo->prepare("
                INSERT INTO sale_product 
                (sale_id, product_id, sale_product_qty, sale_product_price, sale_product_capital)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$sale_id, $barcode, $qty, $unitPrice, $unitCapital]);

            adjustLotRemain($pdo, $barcode, $qty, false);
        }

        $pdo->commit();
        $_SESSION['success'] = "เพิ่มสินค้าสำเร็จ";
        header("Location: http://localhost:8000/index.php?page=sale_insert&sale_id=$sale_id");
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: http://localhost:8000/index.php?page=sale_insert&sale_id=$sale_id");
    }
    exit();
}

// Handle product delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $barcode = trim($_POST['barcode']);
        $qty = (int) ($_POST['qty'] ?? 1);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT sale_product_qty 
            FROM sale_product 
            WHERE sale_id = ? AND product_id = ?
        ");
        $stmt->execute([$sale_id, $barcode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = $existing['sale_product_qty'] - $qty;
            if ($newQty <= 0) {
                $pdo->prepare("
                    DELETE FROM sale_product 
                    WHERE sale_id = ? AND product_id = ?
                ")->execute([$sale_id, $barcode]);
            } else {
                $pdo->prepare("
                    UPDATE sale_product 
                    SET sale_product_qty = ? 
                    WHERE sale_id = ? AND product_id = ?
                ")->execute([$newQty, $sale_id, $barcode]);
            }
            adjustLotRemain($pdo, $barcode, $qty, true);
            $pdo->commit();
            $_SESSION['success'] = "ลบสินค้าสำเร็จ";
        } else {
            throw new Exception("ไม่พบสินค้าในรายการ");
        }

        header("Location: http://localhost:8000/index.php?page=sale_insert&sale_id=$sale_id");
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: http://localhost:8000/index.php?page=sale_insert&sale_id=$sale_id");
    }
    exit();
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $barcode = trim($_POST['barcode']);
        $qty = (int) ($_POST['qty'] ?? 1);
        $price = (float) ($_POST['price'] ?? 0);
        $capital = (float) ($_POST['capital'] ?? 0);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT sale_product_qty, sale_product_price, sale_product_capital 
            FROM sale_product 
            WHERE sale_id = ? AND product_id = ?
        ");
        $stmt->execute([$sale_id, $barcode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $oldQty = $existing['sale_product_qty'];
            $qtyDiff = $qty - $oldQty;

            $pdo->prepare("
                UPDATE sale_product 
                SET sale_product_qty = ?, sale_product_price = ?, sale_product_capital = ? 
                WHERE sale_id = ? AND product_id = ?
            ")->execute([$qty, $price, $capital, $sale_id, $barcode]);

            if ($qtyDiff > 0) {
                adjustLotRemain($pdo, $barcode, $qtyDiff, false);
            } elseif ($qtyDiff < 0) {
                adjustLotRemain($pdo, $barcode, abs($qtyDiff), true);
            }

            $pdo->commit();
            $_SESSION['success'] = "แก้ไขสินค้าสำเร็จ";
        } else {
            throw new Exception("ไม่พบสินค้าในรายการ");
        }

        header("Location: http://localhost:8000/index.php?page=sale_insert&sale_id=$sale_id");
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: http://localhost:8000/index.php?page=sale_insert&sale_id=$sale_id");
    }
    exit();
}

// Handle sale confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_sale') {
    try {
        $pdo->beginTransaction();

        $check_sale = $pdo->prepare("SELECT * FROM sale WHERE sale_id = ?");
        $check_sale->execute([$sale_id]);
        if (!$check_sale->fetch()) {
            throw new Exception("ไม่พบข้อมูลการขาย");
        }

        $check_products = $pdo->prepare("SELECT COUNT(*) FROM sale_product WHERE sale_id = ?");
        $check_products->execute([$sale_id]);
        if ($check_products->fetchColumn() == 0) {
            throw new Exception("ไม่พบสินค้าในรายการขาย กรุณาเพิ่มสินค้าก่อนยืนยันการขาย");
        }

        $check_query = "SELECT sp.product_id, COALESCE(p.product_name, 'ไม่มีสินค้าในระบบ') as product_name, 
                       sp.sale_product_price, sp.sale_product_capital 
                       FROM sale_product sp
                       LEFT JOIN product p ON p.product_id = sp.product_id
                       WHERE sp.sale_id = ? 
                       AND (p.product_name IS NULL OR p.product_name = 'ยังไม่ระบุ' OR sp.sale_product_price = 0 OR sp.sale_product_capital = 0)";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$sale_id]);
        $invalid_products = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($invalid_products) > 0) {
            $invalid_list = '';
            foreach ($invalid_products as $product) {
                $invalid_list .= "- รหัส: {$product['product_id']} ชื่อ: {$product['product_name']}\n";
            }
            throw new Exception("พบสินค้าที่ข้อมูลไม่ครบถ้วน:\n" . $invalid_list);
        }

        $updateSale = $pdo->prepare("UPDATE sale SET sale_status_id = 2 WHERE sale_id = ?");
        $updateSale->execute([$sale_id]);

        if (isset($_POST['member_id']) && !empty($_POST['member_id'])) {
            $updateMember = $pdo->prepare("UPDATE sale SET member_id = ? WHERE sale_id = ?");
            $updateMember->execute([$_POST['member_id'], $sale_id]);
        }

        if (isset($_POST['personnel_id']) && !empty($_POST['personnel_id'])) {
            $updatePersonnel = $pdo->prepare("UPDATE sale SET personnel_id = ? WHERE sale_id = ?");
            $updatePersonnel->execute([$_POST['personnel_id'], $sale_id]);
        }

        $pdo->commit();
        $_SESSION['success'] = "ยืนยันการขายสำเร็จ";
        header('Location: http://localhost:8000/index.php?page=sale_list');
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: http://localhost:8000/index.php?page=sale_insert&sale_id=$sale_id");
    }
    exit();
}

// Query sale details
try {
    $query = "SELECT
        s.sale_id, s.sale_date, s.member_id, m.member_name, m.member_phone,
        s.personnel_id, p.personnel_name, s.sale_status_id, ss.sale_status_name
    FROM sale s
    LEFT JOIN member m ON s.member_id = m.member_id
    LEFT JOIN personnel p ON s.personnel_id = p.personnel_id
    LEFT JOIN sale_status ss ON s.sale_status_id = ss.sale_status_id
    WHERE s.sale_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sale_id]);
    $sale_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $product_query = "SELECT
        sp.sale_product_id, sp.sale_id, sp.product_id,
        COALESCE(pr.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
        sp.sale_product_qty, sp.sale_product_price, sp.sale_product_capital
    FROM sale_product sp
    LEFT JOIN product pr ON pr.product_id = sp.product_id
    WHERE sp.sale_id = ?";
    $stmt = $pdo->prepare($product_query);
    $stmt->execute([$sale_id]);
    $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_amount = 0;
    foreach ($sale_products as $product) {
        $total_amount += $product['sale_product_qty'] * $product['sale_product_price'];
    }
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}

// ดึงข้อมูลสมาชิกและพนักงาน
$members = $pdo->query("SELECT member_id, member_name FROM member ORDER BY member_name");
$personnels = $pdo->query("SELECT personnel_id, personnel_name FROM personnel ORDER BY personnel_name");
$sale_status_result = $pdo->query("SELECT sale_status_id, sale_status_name FROM sale_status");

$sale_status_id = $sale_data['sale_status_id'] ?? 1;
$sale_status_name = $sale_data['sale_status_name'] ?? 'รอดำเนินการ';
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        #edit-product-modal {
            z-index: 1000;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <header class="bg-gradient-to-r from-blue-600 to-indigo-800 text-white shadow-lg">
        <div class="container mx-auto py-4 px-6 flex justify-between items-center">
            <h1 class="text-2xl font-bold">POS System</h1>
            <div class="flex items-center space-x-4">
                <span class="text-sm">พนักงาน:
                    <?php echo htmlspecialchars($sale_data['personnel_name'] ?? 'N/A'); ?></span>
                <span class="text-sm">วันที่: <?php echo $displayDateTime; ?></span>
            </div>
        </div>
    </header>

    <div class="container mx-auto p-6">
        <?php if (isset($_SESSION['success'])): ?>
            <script>
                <?php if (isset($_SESSION['success'])): ?>
                    Swal.fire({
                        toast: true,
                        position: 'center',
                        icon: 'success',
                        title: '<?php echo $_SESSION['success']; ?>',
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true
                    });
                    <?php unset($_SESSION['success']); endif; ?>
            </script>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: '<?php echo $_SESSION['error']; ?>',
                    confirmButtonText: 'ตกลง'
                });
            </script>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <form action="" method="POST" id="form_add_product" class="space-y-4">
                    <div class="flex space-x-4 mb-4">
                        <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                        <input type="text" name="barcode" id="barcode_input"
                            class="p-3 w-full h-8 border border-gray-300 rounded-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                            placeholder="ป้อนรหัสสินค้า" autofocus>
                        <input type="number" name="qty" value="1" min="1"
                            class="p-2 w-24 border border-gray-300 rounded-sm">
                        <button type="submit"
                            class="p-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                </form>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            รายการสินค้า
                        </h2>
                        <button id="delete_mode"
                            class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors flex items-center text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            โหมดลบ
                        </button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-700">
                                    <th class="py-3 px-4 text-left font-medium text-sm">รหัสสินค้า</th>
                                    <th class="py-3 px-4 text-left font-medium text-sm">ชื่อสินค้า</th>
                                    <th class="py-3 px-4 text-center font-medium text-sm">จำนวน</th>
                                    <th class="py-3 px-4 text-right font-medium text-sm">ราคาขาย</th>
                                    <th class="py-3 px-4 text-right font-medium text-sm">ยอดรวม</th>
                                    <th class="py-3 px-4 text-center font-medium text-sm">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sale_products as $product): ?>
                                    <tr class="border-t border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-4 text-sm">
                                            <?php echo htmlspecialchars($product['product_id']); ?>
                                        </td>
                                        <td class="py-3 px-4 text-sm">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </td>
                                        <td class="py-3 px-4 text-center text-sm">
                                            <?php echo htmlspecialchars($product['sale_product_qty']); ?>
                                        </td>
                                        <td class="py-3 px-4 text-right text-sm">
                                            <?php echo number_format($product['sale_product_price'], 2); ?>
                                        </td>
                                        <td class="py-3 px-4 text-right font-medium text-sm">
                                            <?php echo number_format($product['sale_product_qty'] * $product['sale_product_price'], 2); ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button
                                                    class="edit-product p-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors"
                                                    data-id="<?php echo htmlspecialchars($product['sale_product_id']); ?>"
                                                    data-barcode="<?php echo htmlspecialchars($product['product_id']); ?>"
                                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                    data-qty="<?php echo htmlspecialchars($product['sale_product_qty']); ?>"
                                                    data-price="<?php echo htmlspecialchars($product['sale_product_price']); ?>"
                                                    data-capital="<?php echo htmlspecialchars($product['sale_product_capital']); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </button>
                                                <form method="POST" action="" onsubmit="return confirmDelete();">
                                                    <input type="hidden" name="sale_id"
                                                        value="<?php echo htmlspecialchars($sale_id); ?>">
                                                    <input type="hidden" name="barcode"
                                                        value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                                    <input type="hidden" name="qty" value="1">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit"
                                                        class="p-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (empty($sale_products)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <p class="mt-2">ไม่พบสินค้าในการขายนี้</p>
                            <p class="text-sm">กรุณาเพิ่มสินค้า</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        ข้อมูลการขาย
                    </h2>
                    <form method="POST" action="" id="confirm-sale-form">
                        <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                        <input type="hidden" name="action" value="confirm_sale">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">วันที่ทำการขาย:</label>
                                <input name="sale_date" value="<?php echo $displayDateTime; ?>" readonly
                                    class="p-2 bg-gray-50 border border-gray-300 rounded text-gray-500 text-sm">
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">สถานะการขาย:</label>
                                <select name="sale_status_id"
                                    class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    disabled>
                                    <?php while ($sale_status = $sale_status_result->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $sale_status['sale_status_id']; ?>" <?php echo ($sale_status_id == $sale_status['sale_status_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sale_status['sale_status_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">สมาชิก:</label>
                                <select name="member_id"
                                    class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">ไม่เลือกสมาชิก</option>
                                    <?php while ($member = $members->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $member['member_id']; ?>" <?php echo ($sale_data['member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['member_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label class="text-base font-medium text-gray-700">พนักงานขาย:</label>
                                <select name="personnel_id"
                                    class="p-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">ไม่เลือกพนักงาน</option>
                                    <?php while ($personnel = $personnels->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $personnel['personnel_id']; ?>" <?php echo ($sale_data['personnel_id'] == $personnel['personnel_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        การชำระเงิน
                    </h2>
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="flex justify-between text-gray-800">
                            <span class="font-medium">ยอดรวมสินค้า:</span>
                            <span><?php echo number_format($total_amount, 2); ?> บาท</span>
                        </div>
                        <div class="border-t border-gray-200 mt-3 pt-3 flex justify-between font-semibold text-lg">
                            <span>ยอดรวมสุทธิ:</span>
                            <span class="text-blue-700"><?php echo number_format($total_amount, 2); ?> บาท</span>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-2">
                            <label class="text-sm font-medium text-gray-700">เงินที่ชำระ (บาท):</label>
                            <input type="number" id="paid_amount"
                                class="p-3 text-lg border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                step="0.01" placeholder="ป้อนจำนวนเงิน">
                        </div>
                        <div class="grid grid-cols-1 gap-2">
                            <label class="text-sm font-medium text-gray-700">เงินทอน (บาท):</label>
                            <input type="text" id="change_amount"
                                class="p-3 text-lg bg-gray-50 border border-gray-300 rounded-lg text-blue-700 font-semibold"
                                readonly>
                        </div>
                        <button id="calculate_change"
                            class="w-full py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                            คำนวณเงินทอน
                        </button>
                    </div>
                </div>

                <div class="space-y-3">
                    <button id="print_receipt"
                        class="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center"
                        disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        พิมพ์ใบเสร็จ
                    </button>
                    <button type="submit" form="confirm-sale-form"
                        class="w-full py-3 bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition-colors flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        ยืนยันการขาย
                    </button>
                </div>
            </div>
        </div>

        <div id="edit-product-modal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">แก้ไขสินค้า</h3>
                    <button id="close-modal" class="text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="sale_id" value="<?php echo htmlspecialchars($sale_id); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit-barcode" name="barcode">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสสินค้า</label>
                            <input type="text" id="edit-barcode-display"
                                class="w-full p-2 border border-gray-300 rounded" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสินค้า</label>
                            <input type="text" id="edit-product-name" class="w-full p-2 border border-gray-300 rounded"
                                readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                            <input type="number" id="edit-qty" name="qty"
                                class="w-full p-2 border border-gray-300 rounded" min="1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ราคาขาย</label>
                            <input type="number" id="edit-price" name="price"
                                class="w-full p-2 border border-gray-300 rounded" step="0.01">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ต้นทุน</label>
                            <input type="number" id="edit-capital" name="capital"
                                class="w-full p-2 border border-gray-300 rounded" step="0.01">
                        </div>
                    </div>
                    <div class="mt-6 flex space-x-3">
                        <button type="submit"
                            class="flex-1 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">บันทึกการแก้ไข</button>
                        <button type="button" id="cancel-edit"
                            class="flex-1 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById("barcode_input").addEventListener("keypress", function (event) {
            if (event.key === 13) { //in Ascii  = 13 
                event.preventDefault(); // ป้องกันการ reload หน้า
                document.getElementById("form_add_product").submit(); // เปลี่ยน ID ให้ตรง
            }
        });
    </script>
    <script>
        $(document).ready(function () {
            const Swal = window.Swal;
            const totalAmount = <?php echo $total_amount; ?>;

            // สลับโหมดลบ
            $('#delete_mode').click(function () {
                $(this).toggleClass('bg-red-500 bg-red-700');
                if ($(this).hasClass('bg-red-700')) {
                    $(this).html('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg> โหมดลบ (เปิดใช้งาน)');
                } else {
                    $(this).html('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg> โหมดลบ');
                }
            });

            // ยืนยันการลบ
            function confirmDelete() {
                return confirm('คุณต้องการลบสินค้านี้ใช่หรือไม่?');
            }

            // เปิด modal แก้ไข
            $('.edit-product').click(function () {
                $('#edit-barcode').val($(this).data('barcode'));
                $('#edit-barcode-display').val($(this).data('barcode'));
                $('#edit-product-name').val($(this).data('name'));
                $('#edit-qty').val($(this).data('qty'));
                $('#edit-price').val($(this).data('price'));
                $('#edit-capital').val($(this).data('capital'));
                $('#edit-product-modal').removeClass('hidden');
            });

            // ปิด modal
            $('#close-modal, #cancel-edit').click(function () {
                $('#edit-product-modal').addClass('hidden');
            });

            // คำนวณเงินทอน
            $('#calculate_change').click(function () {
                let paidAmount = parseFloat($('#paid_amount').val()) || 0;
                if (paidAmount < totalAmount) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'เงินไม่เพียงพอ',
                        text: 'เงินที่ชำระไม่เพียงพอกับยอดรวม',
                        confirmButtonText: 'ตกลง'
                    });
                    $('#change_amount').val('');
                    $('#print_receipt').prop('disabled', true);
                    return;
                }
                let change = paidAmount - totalAmount;
                $('#change_amount').val(change.toFixed(2));
                $('#print_receipt').prop('disabled', false);
            });

            // คำนวณเงินทอนเมื่อกด Enter
            $('#paid_amount').on('keypress', function (e) {
                if (e.which == 13) {
                    $('#calculate_change').click();
                }
            });

            // พิมพ์ใบเสร็จ
            $('#print_receipt').click(function () {
                let paidAmount = parseFloat($('#paid_amount').val()) || 0;
                let changeAmount = parseFloat($('#change_amount').val()) || 0;
                let receiptWindow = window.open('', '_blank');
                let html = `
                    <html>
                    <head>
                        <title>ใบเสร็จการขาย</title>
                        <style>
                            @page { size: 80mm 200mm; margin: 0; }
                            body { font-family: 'Arial', sans-serif; margin: 0; padding: 8mm; font-size: 12px; }
                            .receipt { width: 76mm; margin: auto; }
                            .header { text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
                            .store-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
                            .info { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 11px; }
                            table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 11px; }
                            th, td { text-align: left; padding: 3px 0; }
                            th { border-bottom: 1px solid #ddd; }
                            .total-section { margin-top: 10px; border-top: 1px dashed #000; padding-top: 10px; }
                            .totals { display: flex; justify-content: space-between; margin: 5px 0; }
                            .grand-total { font-weight: bold; font-size: 14px; margin: 10px 0; display: flex; justify-content: space-between; }
                            .payment-info { margin: 10px 0; border-top: 1px dashed #000; padding-top: 10px; }
                            .footer { text-align: center; margin-top: 20px; font-size: 11px; border-top: 1px dashed #000; padding-top: 10px; }
                            .text-right { text-align: right; }
                        </style>
                    </head>
                    <body>
                        <div class="receipt">
                            <div class="header">
                                <div class="store-name">POS SYSTEM</div>
                                <div>123 ถนนสุขุมวิท เขตวัฒนา กรุงเทพฯ</div>
                                <div>โทร: 02-123-4567</div>
                            </div>
                            <div class="info">
                                <span>เลขที่: <?php echo htmlspecialchars($sale_data['sale_id'] ?? 'N/A'); ?></span>
                                <span>วันที่: <?php echo htmlspecialchars($sale_data['sale_date'] ?? $displayDateTime); ?></span>
                            </div>
                            <div class="info">
                                <span>พนักงาน: <?php echo htmlspecialchars($sale_data['personnel_name'] ?? 'N/A'); ?></span>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>รายการ</th>
                                        <th class="text-right">จำนวน</th>
                                        <th class="text-right">ราคา</th>
                                        <th class="text-right">รวม</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sale_products as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td class="text-right"><?php echo htmlspecialchars($product['sale_product_qty']); ?></td>
                                            <td class="text-right"><?php echo htmlspecialchars(number_format($product['sale_product_price'], 2)); ?></td>
                                            <td class="text-right"><?php echo htmlspecialchars(number_format($product['sale_product_qty'] * $product['sale_product_price'], 2)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="total-section">
                                <div class="totals">
                                    <span>จำนวนสินค้า:</span>
                                    <span><?php echo count($sale_products); ?> รายการ</span>
                                </div>
                                <div class="grand-total">
                                    <span>ยอดรวมทั้งสิ้น:</span>
                                    <span><?php echo number_format($total_amount, 2); ?> บาท</span>
                                </div>
                            </div>
                            <div class="payment-info">
                                <div class="totals">
                                    <span>เงินที่รับ:</span>
                                    <span>${paidAmount.toFixed(2)} บาท</span>
                                </div>
                                <div class="totals">
                                    <span>เงินทอน:</span>
                                    <span>${changeAmount.toFixed(2)} บาท</span>
                                </div>
                            </div>
                            <div class="footer">
                                <div>ขอบคุณที่ใช้บริการ</div>
                                <div>THANK YOU FOR YOUR BUSINESS</div>
                            </div>
                        </div>
                    </body>
                    </html>
                `;
                receiptWindow.document.write(html);
                receiptWindow.document.close();
            });

            console.log('JavaScript loaded successfully');
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>