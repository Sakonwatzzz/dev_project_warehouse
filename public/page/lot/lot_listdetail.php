<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ค่า ID ไม่ถูกต้อง");
}

$lot_id = $_GET['id'];

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $stmt = $pdo->prepare("UPDATE lot_product SET 
            lot_product_qty = ?, 
            lot_product_remain = ?,
            lot_product_capital = ?
            WHERE lot_product_id = ? AND lot_id = ?");

        $stmt->execute([
            $_POST['qty'],
            $_POST['qty'],
            $_POST['capital'],
            $_POST['lot_product_id'],
            $lot_id
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Handle product scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {
    ob_clean();
    header('Content-Type: application/json');
    try {
        $barcode = $_POST['barcode'];

        // Check if product exists
        $stmt = $pdo->prepare("SELECT * FROM product WHERE product_id = ?");
        $stmt->execute([$barcode]);
        $product = $stmt->fetch();

        if ($product) {
            // Check if product already exists in lot
            $stmt = $pdo->prepare("SELECT * FROM lot_product WHERE lot_id = ? AND product_id = ?");
            $stmt->execute([$lot_id, $barcode]);
            $existing_product = $stmt->fetch();

            if ($existing_product) {
                throw new Exception("สินค้านี้มีในล็อตแล้ว กรุณาใช้การแก้ไขแทน");
            }

            // Add product to lot_product
            $insert = $pdo->prepare("INSERT INTO lot_product (
                lot_id, 
                product_id, 
                lot_product_qty, 
                lot_product_remain,
                lot_product_capital, 
                lot_product_date,
                lot_product_status
            ) VALUES (?, ?, 1, 1, 0, NOW(), 1)");

            $insert->execute([$lot_id, $barcode]);

            echo json_encode(['success' => true]);
        } else {
            throw new Exception("ไม่พบรหัสสินค้านี้");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

$query = "SELECT
    l.lot_id,
    l.lot_detail,
    l.lot_date,
    w.warehouse_id,
    w.warehouse_name,
    s.store_id,
    s.store_name,
    sup.supplier_id,
    sup.supplier_name,
    ls.lot_status_id,
    ls.lot_status_name
FROM
    lot l
    INNER JOIN warehouse w ON l.warehouse_id = w.warehouse_id
    INNER JOIN store s ON l.store_id = s.store_id
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
        lp.lot_product_id,
        lp.product_id,
        p.product_name,
        lp.lot_product_qty,
        lp.lot_product_remain,
        lp.lot_product_capital,
        lp.lot_product_date,
        lp.lot_product_status
    FROM
        lot_product lp
        INNER JOIN product p ON p.product_id = lp.product_id
    WHERE lp.lot_id = :lot_id
    ORDER BY lp.lot_product_date DESC";

    $stmt = $pdo->prepare($product_query);
    $stmt->execute(['lot_id' => $lot_id]);
    $lot_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดล็อตสินค้า</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-8 mb-11">
        <h1 class="text-4xl font-bold mb-8">รายละเอียดล็อตสินค้า</h1>

        <?php if ($lot_data): ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="font-semibold mb-2">รหัส Lot สินค้า:</p>
                        <span><?php echo htmlspecialchars($lot_data['lot_id']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">วันที่รับสินค้า:</p>
                        <span><?php echo htmlspecialchars($lot_data['lot_date']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">คลังสินค้า:</p>
                        <span><?php echo htmlspecialchars($lot_data['warehouse_name']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">ผู้ส่งมอบ:</p>
                        <span><?php echo htmlspecialchars($lot_data['supplier_name']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">ร้านค้า:</p>
                        <span><?php echo htmlspecialchars($lot_data['store_name']); ?></span>
                    </div>
                    <div>
                        <p class="font-semibold mb-2">สถานะ:</p>
                        <span><?php echo htmlspecialchars($lot_data['lot_status_name']); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <h2 class="text-2xl font-semibold mb-4">สแกนสินค้าเข้าล็อต</h2>
        <form id="barcode-form" action="" method="POST">
            <div class="mb-4">
                <label class="block mb-2">สแกนบาร์โค้ดสินค้า:</label>
                <input type="number" name="product_id" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400" autofocus>
            </div>
        </form>

        <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold mb-4">รายการสินค้าที่สแกน</h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 border text-left">บาร์โค้ดสินค้า</th>
                            <th class="px-6 py-3 border text-left">ชื่อสินค้า</th>
                            <th class="px-6 py-3 border text-center">จำนวน</th>
                            <th class="px-6 py-3 border text-right">ราคาทุน</th>
                            <th class="px-6 py-3 border text-right">มูลค่ารวม</th>
                            <th class="px-6 py-3 border text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_value = 0;
                        foreach ($lot_products as $index => $item):
                            $item_total = $item['lot_product_qty'] * $item['lot_product_capital'];
                            $total_value += $item_total;
                        ?>

                            <tr class="bg-white hover:bg-[#aec5e366] cursor-pointer">
                                <td class="pl-6 py-2 border"><?php echo htmlspecialchars($item['product_id']); ?></td>
                                <td class="pl-6 py-2 border"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-center py-2 border"><?php echo htmlspecialchars($item['lot_product_qty']); ?></td>
                                <td class="text-right py-2 border"><?php echo number_format($item['lot_product_capital'], 2); ?></td>
                                <td class="text-right py-2 border"><?php echo number_format($item_total, 2); ?></td>
                                <td class="text-right py-2 border px-4">
                                    <button type="button"
                                        class="bg-blue-500 text-white px-4 py-1 rounded edit-product"
                                        data-id="<?php echo htmlspecialchars($item['lot_product_id']); ?>"
                                        data-qty="<?php echo htmlspecialchars($item['lot_product_qty']); ?>"
                                        data-capital="<?php echo htmlspecialchars($item['lot_product_capital']); ?>"
                                        data-name="<?php echo htmlspecialchars($item['product_name']); ?>"
                                        data-barcode="<?php echo htmlspecialchars($item['product_id']); ?>">
                                        แก้ไข
                                    </button>
                                </td>
                            </tr>
                            
                            <?php endforeach; ?>
                            <?php if (count($lot_products) > 0): ?>
                                <tr class=" bg-gray-300  text-2xl h-12">
                                    <td colspan="5" class="text-end">มูลค่ารวมทั้งสิ้น:</td>
                                    <td class="text-end py-4 pr-4"><?php echo number_format($total_value, 2); ?></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="modal-content bg-white p-6 rounded-lg shadow-lg w-96 mx-auto mt-20">
            <h3 class="text-xl font-semibold mb-4">แก้ไขข้อมูลสินค้า</h3>
            <form id="edit-form">
                <input type="hidden" id="lot_product_id" name="lot_product_id">
                <input type="hidden" name="action" value="update">

                <div class="mb-4">
                    <label class="block mb-2">บาร์โค้ด:</label>
                    <input type="text" class="w-full p-2 border rounded bg-gray-100" id="product_barcode" readonly>
                </div>

                <div class="mb-4">
                    <label class="block mb-2">ชื่อสินค้า:</label>
                    <input type="text" class="w-full p-2 border rounded bg-gray-100" id="product_name" readonly>
                </div>

                <div class="mb-4">
                    <label class="block mb-2">จำนวน:</label>
                    <input type="number" class="w-full p-2 border rounded" id="qty" name="qty" required min="1">
                </div>

                <div class="mb-4">
                    <label class="block mb-2">ราคาทุน:</label>
                    <input type="number" class="w-full p-2 border rounded" id="capital" name="capital" required min="0" step="0.01">
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded" onclick="closeEditModal()">ยกเลิก</button>
                    <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded" id="save-edit">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        $(document).ready(function() {
            // Barcode scanning handler
            $('input[name="product_id"]').on('keypress', function(e) {
                if (e.which == 13) { // Enter key pressed
                    e.preventDefault();
                    const product_id = $(this).val();

                    if (!product_id) {
                        alert('กรุณาสแกนบาร์โค้ดสินค้า');
                        return;
                    }

                    // Clear input field
                    $(this).val('');

                    // Send request to server
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            barcode: product_id
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('เกิดข้อผิดพลาด: ' + (response.error || 'ไม่สามารถเพิ่มสินค้าได้'));
                                $('input[name="product_id"]').focus();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Server response:', xhr.responseText);

                            // Try to parse any JSON response that might be embedded in HTML
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
                            $('input[name="product_id"]').focus();
                        }
                    });
                }
            });
        });

        // Edit button handler
        $('.edit-product').click(function() {
            const id = $(this).data('id');
            const qty = $(this).data('qty');
            const capital = $(this).data('capital');
            const name = $(this).data('name');
            const barcode = $(this).data('barcode');

            $('#lot_product_id').val(id);
            $('#product_barcode').val(barcode);
            $('#product_name').val(name);
            $('#qty').val(qty);
            $('#capital').val(capital);

            document.getElementById('editModal').classList.remove('hidden');
        });

        $('#save-edit').click(function() {
            const form = $('#edit-form');
            const submitButton = $(this);

            // Validate form fields
            if (!form[0].checkValidity()) {
                form[0].reportValidity();
                return;
            }

            // Get form values for validation
            const qty = parseFloat($('#qty').val());
            const capital = parseFloat($('#capital').val());

            // Additional validation
            if (qty <= 0) {
                alert('จำนวนสินค้าต้องมากกว่า 0');
                $('#qty').focus();
                return;
            }

            if (capital < 0) {
                alert('ราคาทุนต้องไม่ต่ำกว่า 0');
                $('#capital').focus();
                return;
            }

            // Disable submit button to prevent double submission
            submitButton.prop('disabled', true);
            submitButton.text('กำลังบันทึก...');

            // Send AJAX request
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    try {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('เกิดข้อผิดพลาด: ' + (response.error || 'ไม่สามารถบันทึกข้อมูลได้'));
                        }
                    } catch (e) {
                        console.error('Response parsing error:', e);
                        alert('เกิดข้อผิดพลาดในการประมวลผลข้อมูล');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Server response:', xhr.responseText);

                    // Try to parse any JSON response that might be embedded in HTML
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
                },
                complete: function() {
                    // Re-enable submit button
                    submitButton.prop('disabled', false);
                    submitButton.text('บันทึก');
                }
            });
        });

        // Add input validation handlers
        $('#qty').on('input', function() {
            let value = $(this).val();
            if (value <= 0) {
                $(this).addClass('border-red-500');
            } else {
                $(this).removeClass('border-red-500');
            }
        });

        $('#capital').on('input', function() {
            let value = $(this).val();
            if (value < 0) {
                $(this).addClass('border-red-500');
            } else {
                $(this).removeClass('border-red-500');
            }
        });

        // Close modal when clicking outside
        $('#editModal').click(function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>

    <style>
        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>

</body>

</html>