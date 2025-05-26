<?php
require_once __DIR__ . '../../../../config/database_server.php';
try {
    $query = "SELECT
        stock_check.product_id,
        product.product_name,
        stock_check.stock_check_id,
        stock_check.lot_id,
        warehouse.warehouse_id,
        warehouse.warehouse_name,
        stock_check.stock_check_remark,
        personnel.personnel_name,
        stock_check.lot_product_id,
        lot_product.lot_product_remain,
        stock.stock_id,
        stock.stock_date,
        stock_check.stock_check_system,
        stock_check.stock_check_actual,
        stock.check_status_id,
        stock_checks.check_status_name
    FROM
        stock_check
        LEFT JOIN product ON stock_check.product_id = product.product_id
        LEFT JOIN warehouse ON stock_check.warehouse_id = warehouse.warehouse_id
        LEFT JOIN lot ON stock_check.lot_id = lot.lot_id
        LEFT JOIN lot_product ON stock_check.lot_product_id = lot_product.lot_product_id
        LEFT JOIN stock ON stock_check.stock_id = stock.stock_id
        LEFT JOIN personnel ON stock.personnel_id = personnel.personnel_id
        LEFT JOIN stock_check_status stock_checks ON stock.check_status_id = stock_checks.check_status_id
        WHERE  stock.check_status_id = 1
        ORDER BY stock.stock_date DESC
        ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $check_stock = $stmt;
} catch (Exception $e) {
    echo ("Error: " . $e->getMessage());
}

$warehouse_check = $pdo->query("SELECT warehouse_id, warehouse_name FROM warehouse");
$status_check = $pdo->query("SELECT check_status_id, check_status_name FROM stock_check_status");

date_default_timezone_set('Asia/Bangkok');
$currentDateTime = date("Y-m-d H:i:s");
$displayDateTime = date("d/m/Y H:i:s");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบเช็คสต๊อกสินค้า</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/luxon/3.3.0/luxon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Sarabun', sans-serif;
        }

        @keyframes spinner {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .small-toast {
            font-size: 14px !important;
            padding: 8px 12px !important;
            max-width: 300px !important;
            line-height: 1.4 !important;
        }

        .small-toast .swal2-title {
            font-size: 16px !important;
            margin: 0 !important;
        }

        .small-toast .swal2-timer-progress-bar {
            height: 3px !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="page/stock/js/search_product.js"></script>
    <script src="page/stock/js/delete_product_check.js"></script>
    <script src="page/stock/js/stock_insert_list.js?t=<?= time() ?>"></script>
</head>

<body class="main-body bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <header class="bg-blue-600 text-white shadow-lg">
            <div class="container mx-auto px-4 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-semibold">เช็คสต๊อกสินค้า</h1>
                <div class="flex gap-2">
                    <div class="items-center">
                        <i class="far fa-clock mr-2"></i><?= $displayDateTime ?>
                    </div>
                    <div>
                        <button class="flex items-center space-x-2 focus:outline-none">
                            <span class="md:block">ชื่อผู้ใช้งาน</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="p-2 py-2 flex justify-end">
                <button id="btn-add-stock-check" class="bg-blue-500 hover:bg-blue-600 rounded-md p-2 py-2 text-white">
                    <span class="text">เพิ่มรายการตรวจสอบสต๊อกสินค้าใหม่</span> 
                    <span
                        class="spinner hidden animate-spin h-5 w-5 border-2 border-t-transparent border-white rounded-full"></span>
                </button>
                <select id="stock_id_select" class="w-full border rounded px-2 py-1 cursor-pointer  hover:bg-gray-200">
                    <option value="">เลือกเซสชัน</option>
                </select>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-md mb-6">
                <form id="form_insert_stock">
                    <input type="hidden" name="personnel_id" id="personnel_id" value="1">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div >
                            <label class="block text-sm font-medium text-gray-700 mb-1">หมายเลขเซสชัน</label>
                            <input id="current_stock_id" type="text"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"
                                for="product_id">แสกนรหัสสินค้า</label>
                            <input type="text" id="product_id_input" name="product_id"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="ป้อนรหัสสินค้า...." autocomplete="off" autofocus>
                            <div id="barcode_suggestions"
                                class="z-10 w-full bg-white border border-gray-200 rounded-b-lg shadow-lg hidden"></div>
                        </div>
                        <div>
                            <label for="stock_check_actual"
                                class="block text-sm font-medium text-gray-700 mb-1">จำนวนที่นับได้จริง</label>
                            <input type="number" id="stock_check_actual" name="stock_check_actual"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="ป้อนจำนวน..." min="0" value="0">
                        </div>
                        <div hidden>
                            <label for="warehouse_name"
                                class="block text-sm font-medium text-gray-700 mb-1">จำนวนในระบบ</label>
                            <input type="text" id="warehouse_name" name="warehouse_name" readonly
                                class="w-full rounded-md border border-gray-300 px-3 py-2 bg-gray-100"
                                placeholder="กรุณากรอกรหัสสินค้าเพื่อแสดงคลัง">
                            <input type="hidden" id="warehouse_id" name="warehouse_id">
                        </div>
                        <div hidden>
                            <label for="stock_check_system"
                                class="block text-sm font-medium text-gray-700 mb-1">คลังสินค้า</label>
                            <input type="number" id="stock_check_system" name="stock_check_system" readonly
                                class="w-full rounded-md border border-gray-300 px-3 py-2 bg-gray-100" placeholder="">
                        </div>
                        <div class="block pt-2">
                            <p class="text-sm text-green-700 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                รูปแบบการเพิ่ม: รหัส*จำนวน (เช่น 12345*2)
                            </p>
                        </div>
                        <div>
                            <label for="stock-check-remark"
                                class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
                            <textarea id="stock-check-remark" name="stock_check_remark" rows="3"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="ระบุหมายเหตุ (ถ้ามี)"></textarea>
                        </div>
                        <div hidden>
                            <label for="check_status_id"
                                class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                            <select id="check_status_id"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php while ($status = $status_check->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $status['check_status_id']; ?>">
                                        <?php echo $status['check_status_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div id="product_info" class="mt-4 col-span-4 hidden"></div>
                    <div class="mt-4 flex justify-end py-2 p-2">
                        <button id="btn-submit" type="submit"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md flex items-center cursor-pointer">
                            <span class="text">บันทึก</span>
                            <span
                                class="spinner hidden animate-spin h-5 w-5 border-2 border-t-transparent border-white rounded-full ml-2"></span>
                        </button>
                    </div>
                    <input type="hidden" name="stock_date" id="stock_date" value="<?= $currentDateTime ?>">
                </form>
                <div class="flex justify-end">
                    <button id="btn-confirm-check"
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md flex items-center cursor-pointer disabled:bg-gray-400"
                        disabled>
                        <span class="text">ยืนยันรายการตรวจสอบ</span>
                        <span
                            class="spinner hidden animate-spin h-5 w-5 border-2 border-t-transparent border-white rounded-full ml-2"></span>
                    </button>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-md rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th id="checkbox-header"
                                    class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                                    <input type="checkbox" id="select-all-list"
                                        class="rounded text-blue-500 focus:ring-blue-400">
                                </th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border"
                                    hidden>
                                    รหัสเช็คสต๊อก</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border"
                                    hidden>
                                    รหัสเช็คสต๊อกรายการสินค้า</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                    วันที่</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border"
                                    hidden>
                                    คลังสินค้า</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                    รหัสสินค้า</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                    ชื่อสินค้า</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                    จำนวนสินค้าจากระบบ</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                    จำนวนสินค้าที่นับได้จริง</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                    พนักงาน</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                    สถานะ</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                    หมายเหตุ</th>
                                <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                    การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="stock_check_table" class="bg-white divide-y divide-gray-200">
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>