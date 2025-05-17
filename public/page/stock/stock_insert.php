<?php
require_once __DIR__ . '../../../../config/database_server.php';

try {
    $query = "SELECT
        stock_check_status.check_status_id, 
        stock_check_status.check_status_name, 
        stock_check.check_status_id, 
        stock_check.product_id, 
        product.product_name, 
        product.product_id, 
        stock_check.stock_check_id, 
        stock_check.lot_id, 
        lot.lot_id, 
        warehouse.warehouse_id, 
        warehouse.warehouse_name, 
        stock_check.warehouse_id, 
        stock_check.stock_check_system, 
        stock_check.stock_check_date, 
        stock_check.stock_check_remark, 
        stock_check.stock_check_actual, 
        personnel.personnel_id, 
        personnel.personnel_name, 
        stock_check.lot_product_id, 
        stock_check.personnel_id, 
        lot_product.lot_product_id, 
        lot_product.lot_product_remain
    FROM
        stock_check
        LEFT JOIN stock_check_status ON stock_check.check_status_id = stock_check_status.check_status_id
        LEFT JOIN product ON stock_check.product_id = product.product_id
        LEFT JOIN lot ON stock_check.lot_id = lot.lot_id
        LEFT JOIN warehouse ON stock_check.warehouse_id = warehouse.warehouse_id
        LEFT JOIN personnel ON stock_check.personnel_id = personnel.personnel_id
        LEFT JOIN lot_product ON stock_check.lot_product_id = lot_product.lot_product_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $check_stock = $stmt;
} catch (Exception $e) {
    echo ("Error: " . $e->getMessage());
}

$warehouse_check = $pdo->query("SELECT warehouse_id, warehouse_name FROM warehouse");
$status_check = $pdo->query("SELECT check_status_id, check_status_name FROM stock_check_status");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบเช็คสต๊อกสินค้า</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="page/stock/js/search_product.js"></script>
</head>

<body class="main-body bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <header class="bg-blue-600 text-white shadow-lg">
            <div class="container mx-auto px-4 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-semibold">ระบบบริหารจัดการคลังสินค้า</h1>
                <div class="flex items-center space-x-4">
                    <span id="current-time" name="stock_check_date" class="hidden md:block"></span>
                    <div class="relative group">
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
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">เช็คสต๊อกสินค้า</h2>
                <div>
                    <button id="btn-new-stock-check"
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        สร้างรายการเช็คสต๊อกใหม่
                    </button>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md mb-6">
                <form action="" method="POST" id="form_insert_stock">
                    <input type="id" name="stock_check_id">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="warehouse_id"
                                class="block text-sm font-medium text-gray-700 mb-1">คลังสินค้า</label>
                            <select id="warehouse_id" name="warehouse_id"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">ทั้งหมด</option>
                                <?php while ($warehouse_list_check = $warehouse_check->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $warehouse_list_check['warehouse_id']; ?>">
                                        <?php echo $warehouse_list_check['warehouse_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label for="barcode_input"
                                class="block text-sm font-medium text-gray-700 mb-1">ค้นหาสินค้า</label>
                            <input type="text" id="barcode_input"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="ป้อนรหัสสินค้า" autocomplete="off" autofocus>
                            <div id="barcode_suggestions"
                                class="z-10 w-full bg-white border border-gray-200 rounded-b-lg shadow-lg hidden"></div>
                        </div>
                        <div>
                            <label for="stock_check_system"
                                class="block text-sm font-medium text-gray-700 mb-1">จำนวนสินค้าจากระบบ</label>
                            <input type="number" id="stock_check_system"
                                class="w-full border px-3 py-2 rounded-md cursor-default" readonly>
                        </div>
                        <div>
                            <label for="stock_check_actual"
                                class="block text-sm font-medium text-gray-700 mb-1">จำนวนที่นับได้จริง</label>
                            <input type="number" id="stock_check_actual" value="0"
                                class="w-full border px-3 py-2 rounded-md ">
                        </div>
                        <!-- <div hidden>
                        <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">ช่วงวันที่</label>
                        <input type="text" id="date_range" class="w-full border px-3 py-2 rounded-md"
                            placeholder="เลือกช่วงวันที่">
                    </div> -->
                        <div hidden>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                            <select id="status"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php while ($status = $status_check->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $status['check_status_id']; ?>">
                                        <?php echo $status['check_status_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </form>
                <div id="product_info" class="mt-4 col-span-4 hidden"></div>
                <div class="mt-4 flex justify-end">
                    <button id="btn-search"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        ค้นหา
                    </button>
                </div>
            </div>

            <div id="tab-content">
                <div class="flex mb-4">
                    <button id="active-tab-btn" class="px-4 py-2 bg-blue-500 text-white rounded-l-md"
                        hidden>Active</button>
                    <!-- <button id="history-tab-btn"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-r-md">History</button> -->
                </div>
                <div id="active-tab" class="tab-content">
                    <div class="bg-white overflow-hidden shadow-md rounded-lg">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                                            <input type="checkbox" id="select-all-list"
                                                class="rounded text-blue-500 focus:ring-blue-400">
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            รหัสเช็คสต๊อก</th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            วันที่</th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            คลังสินค้า</th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            รหัสสินค้า</th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            ชื่อสินค้า</th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            จำนวนสินค้าจากระบบ</th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            จำนวนสินค้าที่นับได้จริง</th>
                                        <!-- <th class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            จำนวนรายการ</th> -->
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            พนักงาน</th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            สถานะ</th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            หมายเหตุ</th>
                                        <th
                                            class="px-6 py-3 text-left text-base font-bold text-gray-900 uppercase border">
                                            การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody id="stock_check_table" class="bg-white divide-y divide-gray-200"
                                    id="active-stock-check">
                                    <?php if ($check_stock->rowCount() > 0): ?>
                                        <?php while ($check_stock_list = $check_stock->fetch(PDO::FETCH_ASSOC)): ?>
                                            <tr>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm"><input
                                                        type="checkbox"
                                                        class="stock-list-select rounded text-blue-500 focus:ring-blue-400"
                                                        data-product-id="${product.product_id}" data-lot-id="${product.lot_id}"
                                                        data-lot-product-id="${product.lot_product_id}"
                                                        data-system-quantity="${product.lot_product_remain}"></td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['stock_check_id']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['stock_check_date']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['warehouse_name']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['product_id']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['product_name']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['stock_check_system']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['stock_check_actual']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['personnel_name']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['check_status_name']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                                    <?php echo ($check_stock_list['stock_check_remark']); ?>
                                                </td>
                                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm text-blue-600">
                                                    แก้ไข</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div id="new-stock-check-modal"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">สร้างรายการเช็คสต๊อกใหม่</h3>
                <button class="close-modal text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <form id="new-stock-check-form">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="modal-warehouse" class="block text-sm font-medium text-gray-700 mb-1">คลังสินค้า
                                <span class="text-red-600">*</span></label>
                            <select id="modal-warehouse" name="warehouse_id"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer hover:bg-gray-100 "
                                required>
                                <?php $warehouse_check->execute();
                                while ($warehouse_list_check = $warehouse_check->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $warehouse_list_check['warehouse_id']; ?>">
                                        <?php echo $warehouse_list_check['warehouse_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label for="stock-check-date"
                                class="block text-sm font-medium text-gray-700 mb-1">วันที่ตรวจนับ<span
                                    class="text-red-600">*</span></label>
                            <input type="datetime-local" id="stock-check-date" name="stock_check_date"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="stock-check-remark"
                            class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
                        <textarea id="stock-check-remark" name="stock_check_remark" rows="3"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="ระบุหมายเหตุ (ถ้ามี)"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">เลือกสินค้าที่ต้องการตรวจนับ</label>
                        <div class="border border-gray-300 rounded-md p-4">
                            <div class="mb-2 flex space-x-2">
                                <input type="text" id="product-filter"
                                    class="flex-grow rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="ค้นหาสินค้า...">
                                <button type="button" id="btn-filter-product"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">ค้นหา</button>
                            </div>
                            <div class="h-64 overflow-y-auto border border-gray-200 rounded-md">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                <input type="checkbox" id="select-all-products"
                                                    class="rounded text-blue-500 focus:ring-blue-400">
                                            </th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                รหัส</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                ชื่อสินค้า</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                ล็อต</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                จำนวนในระบบ</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                จำนวนที่นับได้จริง</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200" id="product-list"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-4">
                <button
                    class="close-modal px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none">ยกเลิก</button>
                <button id="btn-create-stock-check"
                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none">สร้างรายการเช็คสต๊อก</button>
            </div>
        </div>

    </div>

    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: "top",
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener("mouseenter", Swal.stopTimer);
                toast.addEventListener("mouseleave", Swal.resumeTimer);
            }
        });

        // Initialize Flatpickr for date range
        flatpickr("#date_range", {
            mode: "range",
            dateFormat: "d/m/Y",
            time_24hr: true
        });

        flatpickr("#stock-check-date", {
            enableTime: true,
            dateFormat: "Y-m-d\\TH:i",
            time_24hr: true,
            defaultDate: new Date()
        });

        // Toggle Modal
        let isProcessing = false;
        $('#btn-new-stock-check').click(function () {
            if (isProcessing) return;
            $('#new-stock-check-modal').removeClass('hidden');
        });

        $('.close-modal').click(function () {
            $('#new-stock-check-modal').addClass('hidden');
            $('#new-stock-check-form')[0].reset();
            $('#product-list').empty();
        });

        // Load products when warehouse is selected
        $('#modal-warehouse').change(function () {
            const warehouse_id = $(this).val();
            if (warehouse_id) {
                loadProducts(warehouse_id);
            } else {
                $('#product-list').html('<tr><td colspan="6" class="px-3 py-2 text-center text-sm text-gray-500">กรุณาเลือกคลังสินค้า</td></tr>');
            }
        });

        // Filter products
        $('#btn-filter-product').click(function () {
            const warehouse_id = $('#modal-warehouse').val();
            const query = $('#product-filter').val();
            if (warehouse_id) {
                loadProducts(warehouse_id, query);
            }
        });

        // Select all products
        $('#select-all-products').change(function () {
            $('#product-list input[type="checkbox"]').prop('checked', this.checked);
        });


        $('#select-all-list').change(function () {
            $('#active-stock-check input[type="checkbox"]');
        });

        // Load products via Ajax
        function loadProducts(warehouse_id, query = '') {
            $.ajax({
                url: 'page/stock/api/api_search_product.php',
                method: 'GET',
                data: { warehouse_id: warehouse_id, query: query },
                dataType: 'json',
                success: function (products) {
                    const tbody = $('#product-list').empty();
                    if (products.length === 0) {
                        tbody.append('<tr><td colspan="6" class="px-3 py-2 text-center text-sm text-gray-500">ไม่พบสินค้า</td></tr>');
                        return;
                    }
                    products.forEach(product => {
                        tbody.append(`
                            <tr>
                                <td class="px-3 py-2"><input type="checkbox" class="product-select rounded text-blue-500 focus:ring-blue-400" data-product-id="${product.product_id}" data-lot-id="${product.lot_id}" data-lot-product-id="${product.lot_product_id}" data-system-quantity="${product.lot_product_remain}"></td>
                                <td class="px-3 py-2">${product.product_id}</td>
                                <td class="px-3 py-2">${product.product_name}</td>
                                <td class="px-3 py-2">${product.lot_name || 'N/A'}</td>
                                <td class="px-3 py-2 text-right">${product.lot_product_remain}</td>
                                <td class="px-3 py-2 text-right"><input type="number" class="actual-quantity w-20 border rounded px-2 py-1" min="0" value="${product.lot_product_remain}"></td>
                            </tr>
                        `);
                    });
                },
                error: function () {
                    $('#product-list').html('<tr><td colspan="6" class="px-3 py-2 text-center text-sm text-gray-500">เกิดข้อผิดพลาดในการโหลดสินค้า</td></tr>');
                }
            });
        }

        // Create stock check
        $('#btn-create-stock-check').click(function () {
            if (isProcessing) return;
            isProcessing = true;
            $('#btn-create-stock-check').prop('disabled', true);

            const warehouse_id = $('#modal-warehouse').val();
            const stock_check_date = $('#stock-check-date').val();
            const stock_check_remark = $('#stock-check-remark').val();
            const personnel_id = 1; // Replace with actual logged-in personnel_id
            const products = [];

            $('#product-list .product-select:checked').each(function () {
                const row = $(this).closest('tr');
                const stock_check_actual = parseFloat(row.find('.actual-quantity').val());
                if (isNaN(stock_check_actual) || stock_check_actual < 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ข้อผิดพลาด',
                        text: `จำนวนจริงของ ${$(this).data('product-id')} ต้องเป็นตัวเลขและไม่ติดลบ`
                    });
                    isProcessing = false;
                    $('#btn-create-stock-check').prop('disabled', false);
                    return false;
                }
                products.push({
                    product_id: $(this).data('product-id'),
                    lot_id: $(this).data('lot-id'),
                    lot_product_id: $(this).data('lot-product-id'),
                    stock_check_system: parseFloat($(this).data('system-quantity')),
                    stock_check_actual: stock_check_actual
                });
            });
            $('#stock_check_table .stock-list-select:checked').each(function () {
                const stockrow = $(this).closest('tr');

            });

            if (!warehouse_id || !stock_check_date || products.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อผิดพลาด',
                    text: 'กรุณากรอกข้อมูลให้ครบถ้วนและเลือกสินค้าอย่างน้อย 1 รายการ'
                });
                isProcessing = false;
                $('#btn-create-stock-check').prop('disabled', false);
                return;
            }

            $.ajax({
                url: 'page/stock/api/api_insert_stock.php',
                method: 'POST',
                data: {
                    action: 'insert_check_products',
                    warehouse_id: warehouse_id,
                    stock_check_date: stock_check_date,
                    stock_check_remark: stock_check_remark,
                    personnel_id: personnel_id,
                    products: JSON.stringify(products)
                },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: 'บันทึกการตรวจสอบสต็อกสำเร็จ'
                        });
                        $('#new-stock-check-modal').addClass('hidden');
                        $('#new-stock-check-form')[0].reset();
                        $('#product-list').empty();
                        updateStockTable('active');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ข้อผิดพลาด',
                            text: response.message || 'ไม่สามารถบันทึกข้อมูล'
                        });
                    }
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'
                    });
                },
                complete: function () {
                    isProcessing = false;
                    $('#btn-create-stock-check').prop('disabled', false);
                }
            });
        });

        // Tab switching
        $('#active-tab-btn').click(function () {
            $('#active-tab').removeClass('hidden');
            // $('#history-tab').addClass('hidden');
            $(this).addClass('bg-blue-500 text-white').removeClass('bg-gray-200 text-gray-700');
            // $('#history-tab-btn').addClass('bg-gray-200 text-gray-700').removeClass('bg-blue-500 text-white');
            updateStockTable('active');
        });

        // $('#history-tab-btn').click(function () {
        //     $('#history-tab').removeClass('hidden');
        //     $('#active-tab').addClass('hidden');
        //     $(this).addClass('bg-blue-500 text-white').removeClass('bg-gray-200 text-gray-700');
        //     $('#active-tab-btn').addClass('bg-gray-200 text-gray-700').removeClass('bg-blue-500 text-white');
        //     updateStockTable('history');
        // });

        // Search and update table
        $('#btn-search').click(function () {
            updateStockTable($('#active-tab').hasClass('hidden') ? 'history' : 'active');
        });

        // function updateStockTable(tab) {
        //     const warehouse_id = $('#warehouse_id').val();
        //     const product_id = $('#product_id').val();
        //     const status = $('#status').val();
        //     const date_range = $('#date_range').val();
        //     const isHistory = tab === 'history';
        //     $.ajax({
        //         url: 'page/stock/api/api_stock_list.php',
        //         method: 'GET',
        //         data: { warehouse_id, product_id, status, date_range, tab },
        //         dataType: 'json',
        //         success: function (data) {
        //             const tbody = $(`#${tab}-stock-checks`).empty();
        //             if (!Array.isArray(data)) {
        //                 tbody.append(`<tr><td colspan="${isHistory ? 8 : 7}" class="px-6 py-4 text-center text-sm text-gray-500">ไม่มีข้อมูลหรือรูปแบบข้อมูลไม่ถูกต้อง</td></tr>`);
        //                 console.error('Response is not an array:', data);
        //                 return;
        //             }
        //             if (data.length === 0) {
        //                 tbody.append(`<tr><td colspan="${isHistory ? 8 : 7}" class="px-6 py-4 text-center text-sm text-gray-500">ไม่มีข้อมูล</td></tr>`);
        //                 return;
        //             }
        //             data.forEach(item => {
        //                 console.log('Rendering iten :' , item)
        //                 const row = `
        //                     <tr>
        //                         <td class="px-3 py-2">
        //                             <input type="checkbox" class="stock-check-select rounded text-blue-500 focus:ring-blue-400" 
        //                             data-stock-check-id="${item.stock_check_id}">
        //                         </td>
        //                         <td class="px-6 py-4">${item.stock_check_id}</td>
        //                         <td class="px-6 py-4">${item.stock_check_date}</td>
        //                         <td class="px-6 py-4">${item.warehouse_name}</td>
        //                         <td class="px-6 py-4">${item.product_id}</td>
        //                         <td class="px-6 py-4">${item.stock_check_system}</td>
        //                         <td class="px-6 py-4">${item.stock_check_actual}</td>
        //                         <td class="px-6 py-4">${item.item_count}</td>
        //                         <td class="px-6 py-4">${item.personnel_name}</td>
        //                         <td class="px-6 py-4">${item.check_status_name}</td>
        //                         <td class="px-6 py-4">${item.stock_check_remark || ''}</td>

        //                     </tr>`;
        //                 tbody.append(row);
        //             });
        //         },
        //         error: function () {
        //             Swal.fire({
        //                 icon: 'error',
        //                 title: 'ข้อผิดพลาด',
        //                 text: 'ไม่สามารถโหลดข้อมูลตารางได้'
        //             });
        //         }
        //     });
        // }

        // Update current time
        setInterval(() => {
            $('#current-time').text(luxon.DateTime.local().setZone('Asia/Bangkok').toFormat('dd/MM/yyyy HH:mm:ss'));
        }, 1000);
    </script>
</body>

</html>