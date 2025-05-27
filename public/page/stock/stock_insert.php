<?php
require_once __DIR__ . '../../../../config/database_server.php';


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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
                <h1 class="text-2xl font-bold">ตรวจสอบสต๊อก</h1>
                <div class="flex gap-2 items-center">
                    <div>
                        <i class="far fa-clock mr-2"></i><span id="displayDateTime"></span>
                    </div>
                    <div>
                        <button class="flex items-center space-x-2 focus:outline-none">
                            <span class="md text-sm">ชื่อผู้ใช้งาน</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="p-2 py-4 flex justify-end gap-2">
                <button hidden id="btn-add-stock-check" class="bg-blue-600 hover:bg-blue-700 text-white rounded-md p-2 px-3 text-sm">
                    <span class="text">เพิ่มรายการตรวจสอบ</span>
                    <span class="spinner hidden animate-spin h-5 w-5 border-2 border-t-transparent border-white rounded-full ml-2"></span>
                </button>
                <a id="btn-delete-stock-session" class="bg-red-600 hover:bg-red-700 text-white rounded-md p-2 px-3 text-sm cursor-pointer">
                    <span class="text">ลบรายการตรวจสอบสต๊อกสินค้า</span>
                    <span class="spinner hidden animate-spin h-4 w-4 border-2 border-t-transparent border-white rounded-full ml-2"></span>
                </a>
                <select hidden id="stock_id_select" class="w-48 border rounded-md px-3 py-2 text-sm cursor-pointer hover:bg-gray-200">
                    <option value="">เลือกเซสชัน</option>
                </select>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md mb-6">
                <form id="form_insert_stock">
                    <input type="hidden" name="personnel_id" id="personnel_id" value="1">
                    <input type="hidden" name="warehouse_id" id="warehouse_id">
                    <input type="hidden" name="lot_id" id="lot_id">
                    <input type="hidden" name="lot_product_id" id="lot_product_id">
                    <input type="hidden" name="is_update" id="is_update" value="0">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div hidden>
                            <label class="block text-sm font-medium text-gray-700 mb-1">หมายเลขเซชัน</label>
                            <input id="current_stock_id" type="text" class="w-full border rounded-md px-3 py-2 bg-gray-100" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="product_id">สแกนรหัสสินค้า</label>
                            <input type="text" id="product_id_input" name="product_id" class="w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="ป้อนรหัสสินค้า...." autocomplete="off" autofocus>
                            <div id="barcode_suggestions" class="text-z-10 w-full bg-white border border-gray-200 rounded-b-lg shadow-lg hidden"></div>
                        </div>
                        <div>
                            <label for="stock_check_actual" class="block text-sm font-medium text-gray-700 mb-1">จำนวนที่นับได้จริง</label>
                            <input type="number" id="stock_check_actual" name="stock_check_actual" class="w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="500" min="0" value="0">
                        </div>
                        <div class="col-span-full">
                            <label for="stock-check-remark" class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
                            <textarea id="stock-check-remark" name="stock_check_remark" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="ระบุหมายเหตุ (ถ้ามี)"></textarea>
                        </div>
                    </div>
                    <div id="product_info" class="mt-4 hidden"></div>
                    <div class="mt-4 flex justify-between items-center">
                        <p class="text-sm text-green-700">
                            <i class="fas fa-info-circle mr-1"></i>
                            รูปแบบการเพิ่ม: รหัส*จำนวน (เช่น 12345*2)
                        </p>
                        <button id="btn-submit" type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md flex items-center cursor-pointer" hidden>
                            <span class="text" hidden>บันทึก</span>
                            <span class="spinner hidden animate-spin h-4 w-4 border-2 border-t-transparent border-white rounded-full ml-2" hidden></span>
                        </button>
                    </div>
                    <input type="hidden" name="stock_date" id="stock_date">
                </form>
                <div class="flex justify-end mt-4">
                    <button id="btn-confirm-check" class="bg-green-600 text-white px-4 py-2 rounded-md flex items-center hover:bg-green-700 disabled:bg-gray-400 cursor-pointer" disabled>
                        <span class="text">ยืนยันรายการตรวจสอบสต๊อกสินค้า</span>
                        <span class="spinner hidden animate-spin h-4 w-4 border-2 border-t-transparent border-white rounded-full ml-2"></span>
                    </button>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-md rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-blue-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">วันที่</th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">รหัสสินค้า</th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">ชื่อสินค้า</th>
                                <th class="px-6 py-3 text-right text-sm font-bold text-gray-900 uppercase">จำนวนจากระบบ</th>
                                <th class="px-6 py-3 text-right text-sm font-bold text-gray-900 uppercase">จำนวนนับจริง</th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">พนักงาน</th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">สถานะ</th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">หมายเหตุ</th>
                                <th class="px-6 py-3 text-center text-sm font-bold text-gray-900 uppercase">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="stock_check_table" class="tbody bg-white divide-y divide-gray-200"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.getElementById('displayDateTime').textContent = luxon.DateTime.now().setZone('Asia/Bangkok').toFormat('dd/MM/yyyy HH:mm:ss');
    </script>
</body>

</html>