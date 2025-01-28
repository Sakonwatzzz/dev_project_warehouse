<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

try {
    // ดึงรหัสล็อตสินค้าล่าสุด
    $stmt = $pdo->query("SELECT lot_id FROM lot ORDER BY lot_id DESC LIMIT 1");
    $latest_lot = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_lot_id = $latest_lot ? $latest_lot['lot_id'] : 'LOT001'; // ถ้าไม่มีข้อมูลให้เริ่มที่ LOT001

    // ดึงข้อมูล product_id (ส่วนนี้ยังคงเหมือนเดิม)
    $stmt = $pdo->query("SELECT product_id FROM product");
    $product_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the form data
        $lot_id = $_POST['lot_id'];
        $lot_product_qty = $_POST['lot_product_qty'];
        $lot_product_remain = $_POST['lot_product_remain'];
        $lot_product_capital = $_POST['lot_product_capital'];
        $lot_product_date = $_POST['lot_product_date'];
        $lot_product_status = $_POST['lot_product_status'];
        $product_id = $_POST['product_id'];

        // Validate inputs
        if (empty($lot_id) || empty($lot_product_qty) || empty($product_id)) {
            throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
        }

        // Prepare the SQL insert statement
        $stmt = $pdo->prepare('INSERT INTO lot_product (lot_id, lot_product_qty, lot_product_remain, lot_product_capital, lot_product_date, lot_product_status, product_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)');

        // Execute the query with the data from the form
        $stmt->execute([
            $lot_id,
            $lot_product_qty,
            $lot_product_remain,
            $lot_product_capital,
            $lot_product_date,
            $lot_product_status,
            $product_id
        ]);

        // Get the last inserted ID and redirect
        $lot_product_id = $pdo->lastInsertId();
        header("Location: /?page=lot_productlist&success=1");
        exit;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>การรับรายการสินค้า</title>

    <style>
        
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

        .notification-body {
            padding: 20px;
            font-size: 16px;
            text-align: center;
        }

        .error-message {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #dc2626;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border-radius: 0.375rem;
        }
    </style>
</head>

<body>
    <div class="flex justify-center">
        <div class="w-[50%] m-[5rem] border border-gray-500">
            <div id="notification" class="notification">
                <div class="notification-header">การแจ้งเตือน</div>
                <div class="notification-body">การรับสินค้าสำเร็จ!</div>
            </div>

            <h2 class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-2 border border-blue-700 shadow font-bold p-5">
                การรับรายการสินค้า
            </h2>

            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form class="p-5 space-y-2" method="POST">
                <div class="flex items-center">
                    <label for="lot_id" class="w-[150px] text-base mr-4">รหัสล็อตสินค้า:</label>
                    <input type="text"
                        id="lot_id"
                        name="lot_id"
                        value="<?php echo htmlspecialchars($current_lot_id); ?>"
                        class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-gray-100"
                        readonly>
                </div>

                <div class="flex items-center">
                    <label class="w-[150px] text-base mr-4" for="lot_product_qty">จำนวนสินค้าเข้า:</label>
                    <input type="number" id="lot_product_qty" name="lot_product_qty" class="flex-1 h-9 px-2 border border-[#7F9DB9]" required>
                </div>

                <div class="flex items-center">
                    <label class="w-[150px] text-base mr-4" for="lot_product_remain">จำนวนสินค้าคงเหลือ:</label>
                    <input type="number" id="lot_product_remain" name="lot_product_remain" class="flex-1 h-9 px-2 border border-[#7F9DB9]" required>
                </div>

                <div class="flex items-center">
                    <label class="w-[150px] text-base mr-4" for="lot_product_capital">ราคาต้นทุน:</label>
                    <input type="number" step="0.01" id="lot_product_capital" name="lot_product_capital" class="flex-1 h-9 px-2 border border-[#7F9DB9]" required>
                </div>

                <div class="flex items-center">
                    <label class="w-[150px] text-base mr-4" for="lot_product_date">วันที่รับสินค้า:</label>
                    <input type="date" id="lot_product_date" name="lot_product_date" value="<?php echo date('Y-m-d'); ?>" class="flex-1 h-9 px-2 border border-[#7F9DB9]" readonly>
                </div>

                <div class="flex items-center">
                    <label class="w-[150px] text-base mr-4" for="lot_product_status">สถานะสินค้า:</label>
                    <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="lot_product_status" id="lot_product_status" required>
                        <option value="1">พร้อมจำหน่าย</option>
                        <option value="0">ไม่พร้อมจำหน่าย</option>
                    </select>
                </div>

                <div class="flex items-center">
                    <label class="w-[150px] text-base mr-4" for="product_id">รหัสสินค้า:</label>
                    <select class="flex-1 h-9 px-2 border border-[#7F9DB9] bg-white shadow-inner" name="product_id" id="product_id" required>
                        <option value="">เลือกรหัสสินค้า</option>
                        <?php foreach ($product_ids as $product): ?>
                            <option value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                <?php echo htmlspecialchars($product['product_id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end mt-5 gap-2">
                    <a href="/?page=lot_productlist" class="bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 border-t-white border-l-white px-4 py-1 rounded shadow-md hover:bg-[#FFEBEE] active:border-[#EF9A9A] active:border-b-white active:border-r-white active:shadow-inner">
                        ยกเลิก
                    </a>
                    <button type="submit" class="bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg border border-green-700 border-t-white border-l-white px-4 py-1 rounded shadow-md hover:bg-[#E8F5E9] active:border-[#A5D6A7] active:border-b-white active:border-r-white active:shadow-inner">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.style.display = 'block';

                setTimeout(() => {
                    notification.style.display = 'none';
                }, 2000);
            }

            // Auto-populate lot_product_remain with lot_product_qty
            document.getElementById('lot_product_qty').addEventListener('input', function() {
                document.getElementById('lot_product_remain').value = this.value;
            });
        });
    </script>
</body>

</html>