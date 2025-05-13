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
    stock_check.system_quantity, 
    stock_check.stock_check_date, 
    stock_check.stock_check_remark, 
    personnel.personnel_id, 
    personnel.personnel_name, 
    stock_check.lot_product_id, 
    stock_check.personnel_id, 
    lot_product.lot_product_id, 
    lot_product.lot_product_remain
FROM
    stock_check
    LEFT JOIN stock_check_status
        ON stock_check.check_status_id = stock_check_status.check_status_id
    LEFT JOIN product
        ON stock_check.product_id = product.product_id
    LEFT JOIN lot
        ON stock_check.lot_id = lot.lot_id
    LEFT JOIN warehouse
        ON stock_check.warehouse_id = warehouse.warehouse_id
    LEFT JOIN personnel
        ON stock_check.personnel_id = personnel.personnel_id
    LEFT JOIN lot_product
        ON stock_check.lot_product_id = lot_product.lot_product_id;";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $check_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo ("Eror : " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }
    </style>
</head>

<body>
    <div class="flex justify-center p-2">
        <h1 class="font-bold text-2xl">ประวัติรายการตรวจสอบสต๊อกสินค้า</h1>
    </div>

    <div id="tab-content">
        <!-- Active Stock Checks Tab -->
        <div id="active-tab" class="tab-content">
            <div class="bg-white overflow-hidden shadow-md rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    รหัสเช็คสต๊อก</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    วันที่</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    คลังสินค้า</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    จำนวนรายการ</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    พนักงาน</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    สถานะ</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    หมายเหตุ</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="active-stock-checks">
                            <!-- Data will be loaded via AJAX -->
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">

                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            แสดง <span id="showing-records">0</span> จาก <span id="total-records">0</span>
                            รายการ
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px"
                                aria-label="Pagination">
                                <a href="#"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                        fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </a>
                                <a href="#"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    1 </a>
                                <a href="#"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    2 </a>
                                <a href="#"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-blue-500 bg-blue-50">
                                    3 </a>
                                <a href="#"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                        fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div id="history-tab" class="tab-content hidden">
            <div class="bg-white overflow-hidden shadow-md rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    รหัสเช็คสต๊อก</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    วันที่</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    คลังสินค้า</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    จำนวนรายการ</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    พนักงาน</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ผู้อนุมัติ</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    สถานะ</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="history-stock-checks">
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            แสดง <span id="history-showing-records">0</span> จาก <span
                                id="history-total-records">0</span> รายการ
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px"
                                aria-label="Pagination">
                                <!-- Pagination for history will be loaded dynamically -->
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>