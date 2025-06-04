<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../config/database_server.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Set timezone
date_default_timezone_set('Asia/Bangkok');
$displayDateTime = date("d/m/Y H:i:s");

// Get sale_id from GET or session
$sale_id = $_GET['sale_id'] ?? $_SESSION['current_sale_id'] ?? null;

if (!$sale_id) {
    die(json_encode(['status' => 'error', 'message' => 'Error: sale_id is required.']));
}
if (!$sale_id) {
    $_SESSION['error'] = "กรุณาเปิดบิลใหม่ก่อนดูใบเสร็จ";
    header("Location: sale_add.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเสร็จการขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'TH Sarabun New', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-600 to-indigo-800 text-white shadow-lg rounded-lg mb-6">
            <div class="py-4 px-6 flex justify-between items-center">
                <h1 class="text-2xl font-bold">ใบเสร็จการขาย</h1>
                <div class="flex items-center space-x-4">
                    <p class="text-sm text-white"><strong>สมาชิก:</strong> <span id="member_name"></span></p>
                    <span class="text-sm">บิล #: <span
                            id="sale_id"><?php echo htmlspecialchars($sale_id); ?></span></span>
                    <span class="text-sm">วันที่: <?php echo $displayDateTime; ?></span>
                </div>
            </div>
        </header>
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4 mb-6">
            <!-- <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่อโฆษณา</label>
                    <input type="text" name="ad_title" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                </div> -->

            <!-- <div>
                <label for="ad_image_1" class="block text-sm font-medium text-gray-700 mb-1">เลือกรูปภาพโฆษณา</label>
                <label for="ad_image_1"
                    class="flex items-center justify-center h-[50px] w-[50px] border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm">
                    📷
                </label>
                <input id="ad_image_1" type="file" name="ad_image[]" accept="image/*" required class="hidden" />
            </div> -->

            <div class="flex justify-center gap-4">
                <div class="">
                    <label class="block text-sm font-medium text-gray-700">เลือกรูปภาพโฆษณา</label>
                    <input type="file" name="ad_image" accept="image/*" required
                        class="mt-1 block  py-4 p-4 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm h-[50px] w-[50px]" />
                </div>

                <div class="">
                    <label class="block text-sm font-medium text-gray-700">เลือกรูปภาพโฆษณา</label>
                    <input type="file" name="ad_image" accept="image/*" required
                        class="mt-1 block  py-4 p-4 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm h-[50px] w-[50px]" />
                </div>

                <div class="">
                    <label class="block text-sm font-medium text-gray-700">เลือกรูปภาพโฆษณา</label>
                    <input type="file" name="ad_image" accept="image/*" required
                        class="mt-1 block  py-4 p-4 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm h-[50px] w-[50px]" />
                </div>

                <div class="">
                    <label class="block text-sm font-medium text-gray-700">เลือกรูปภาพโฆษณา</label>
                    <input type="file" name="ad_image" accept="image/*" required
                        class="mt-1 block  py-4 p-4 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm h-[50px] w-[50px]" />
                </div>
            </div>

            <!-- <div>
                    <label class="block text-sm font-medium text-gray-700">ลิงก์เมื่อคลิก (optional)</label>
                    <input type="url" name="ad_link" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                </div> -->

            <!-- <div>
                <label class="block text-sm font-medium text-gray-700">ตำแหน่งการแสดง</label>
                <select name="ad_position" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="checkout">หน้าแจ้งชำระเงิน</option>
                    <option value="invoice">หน้าใบเสร็จ</option>
                    <option value="homepage">หน้าแรก</option>
                </select>
            </div> -->

            <button type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-500  ease-in-outshadow-lg"
                hidden>
                บันทึกโฆษณา
            </button>
        </form>

        <!-- Sale Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6" hidden>
            <h2 class="text-xl font-semibold text-gray-800 mb-4 flex justify-center items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                ข้อมูลการขาย
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600"><strong>พนักงาน:</strong> <span id="personnel_name"></span></p>
                    <p class="text-sm text-gray-600"><strong>สถานะ:</strong> <span id="sale_status_name"></span></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600"><strong>สมาชิก:</strong> <span id="member_name"></span></p>
                    <p class="text-sm text-gray-600"><strong>เบอร์โทร:</strong> <span id="member_phone"></span></p>
                </div>
            </div>
        </div>
        <div class="flex justify-center items-center gap-10">
            <div class="bg-red-100  h-96 w-96">
                <label for="ad_image_1" class="block text-sm font-medium text-gray-700 mb-1">เลือกรูปภาพโฆษณา</label>
                <label for="ad_image_1"
                    class="flex items-center justify-center  h-96 w-96 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5">
                            <path stroke-linejoin="round"
                                d="M21.25 13V8.5a5 5 0 0 0-5-5h-8.5a5 5 0 0 0-5 5v7a5 5 0 0 0 5 5h6.26" />
                            <path stroke-linejoin="round"
                                d="m3.01 17l2.74-3.2a2.2 2.2 0 0 1 2.77-.27a2.2 2.2 0 0 0 2.77-.27l2.33-2.33a4 4 0 0 1 5.16-.43l2.47 1.91M8.01 10.17a1.66 1.66 0 1 0-.02-3.32a1.66 1.66 0 0 0 .02 3.32" />
                            <path stroke-miterlimit="10" d="M18.994 15.5v5M16.5 18.005h5" />
                        </g>
                    </svg>
                </label>
                <input id="ad_image_1" type="file" name="ad_image[]" accept="image/*" required class="hidden" />
            </div>
            <!-- Product List -->
            <div class="bg-white rounded-lg shadow-md w-[50%] p-6 mb-6 ">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    รายการสินค้า
                </h2>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-700">
                                <th class="py-3 px-4 text-left font-medium text-sm">รหัสสินค้า</th>
                                <th class="py-3 px-4 text-left font-medium text-sm">ชื่อสินค้า</th>
                                <th class="py-3 px-4 text-center font-medium text-sm">จำนวน</th>
                                <th class="py-3 px-4 text-right font-medium text-sm">ราคาขาย</th>
                                <th class="py-3 px-4 text-right font-medium text-sm">ยอดรวม</th>
                            </tr>
                        </thead>
                        <tbody id="product_table_body"></tbody>
                    </table>
                </div>
                <div id="no_products" class="text-center py-8 text-gray-500 hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <p class="mt-2">ไม่พบสินค้าในการขายนี้</p>
                </div>

                <h2 class="text-xl font-semibold mb-3 text-gray-800 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    สรุปการชำระเงิน
                </h2>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex justify-between text-gray-800 mb-2">
                        <span class="font-medium text-sm">ยอดสุทธิ (ไม่รวม VAT):</span>
                        <span class="text-sm" id="sale-net-amount">0.00 บาท</span>
                    </div>
                    <div class="flex justify-between text-gray-800 mb-2">
                        <span class="font-medium text-sm">VAT (7%):</span>
                        <span class="text-sm" id="sale-vat-amount">0.00 บาท</span>
                    </div>
                    <div class="flex justify-between text-gray-800 mb-2">
                        <span class="font-medium text-sm">ยอดรวมสินค้า:</span>
                        <span class="text-sm" id="sale-total-amount">0.00 บาท</span>
                    </div>
                    <div class="border-t border-gray-200 pt-2 flex justify-between font-semibold text-sm">
                        <span>ยอดรวมสุทธิที่ต้องชำระ:</span>
                        <span class="text-blue-700" id="total-net">0.00 บาท</span>
                    </div>
                    <div class="flex justify-between text-gray-800 mt-2">
                        <span class="font-medium text-sm">เงินที่ชำระ:</span>
                        <span class="text-sm" id="sale-paid-amount">0.00 บาท</span>
                    </div>
                    <div class="flex justify-between text-gray-800 mt-2">
                        <span class="font-medium text-sm">เงินทอน:</span>
                        <span class="text-sm text-blue-700 font-semibold" id="sale-change-amount">0.00 บาท</span>
                    </div>
                </div>
            </div>
            <div class="bg-red-100 h-96 w-96">
                <label for="ad_image_1" class="block text-sm font-medium text-gray-700 mb-1">เลือกรูปภาพโฆษณา</label>
                <label for="ad_image_1"
                    class="flex items-center justify-center h-96 w-96 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5">
                            <path stroke-linejoin="round"
                                d="M21.25 13V8.5a5 5 0 0 0-5-5h-8.5a5 5 0 0 0-5 5v7a5 5 0 0 0 5 5h6.26" />
                            <path stroke-linejoin="round"
                                d="m3.01 17l2.74-3.2a2.2 2.2 0 0 1 2.77-.27a2.2 2.2 0 0 0 2.77-.27l2.33-2.33a4 4 0 0 1 5.16-.43l2.47 1.91M8.01 10.17a1.66 1.66 0 1 0-.02-3.32a1.66 1.66 0 0 0 .02 3.32" />
                            <path stroke-miterlimit="10" d="M18.994 15.5v5M16.5 18.005h5" />
                        </g>
                    </svg>
                </label>
                <input id="ad_image_1" type="file" name="ad_image[]" accept="image/*" required class="hidden" />
            </div>
        </div>
        <div class="flex justify-center gap-4">
            <div class="">
                <label class="block text-sm font-medium text-gray-700">เลือกรูปภาพโฆษณา</label>
                <input type="file" name="ad_image" accept="image/*" required
                    class="mt-1 block  py-4 p-4 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm h-[50px] w-[50px]" />
            </div>

            <div class="">
                <label class="block text-sm font-medium text-gray-700">เลือกรูปภาพโฆษณา</label>
                <input type="file" name="ad_image" accept="image/*" required
                    class="mt-1 block  py-4 p-4 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm h-[50px] w-[50px]" />
            </div>

            <div class="">
                <label class="block text-sm font-medium text-gray-700">เลือกรูปภาพโฆษณา</label>
                <input type="file" name="ad_image" accept="image/*" required
                    class="mt-1 block  py-4 p-4 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm h-[50px] w-[50px]" />
            </div>

            <div class="">
                <label class="block text-sm font-medium text-gray-700">เลือกรูปภาพโฆษณา</label>
                <input type="file" name="ad_image" accept="image/*" required
                    class="mt-1 block  py-4 p-4 border-2 border-dashed border-indigo-500 cursor-pointer transition duration-200 shadow-sm h-[50px] w-[50px]" />
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            const saleId = '<?php echo htmlspecialchars($sale_id); ?>';

            function fetchSaleDetail() {
                $.ajax({
                    url: 'page/sale/api/api_sale_detail.php',
                    method: 'GET',
                    data: {
                        sale_id: saleId
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            updateUI(response.data);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'ข้อผิดพลาด',
                                text: response.message,
                                confirmButtonText: 'ตกลง'
                            });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'ข้อผิดพลาด',
                            text: 'ไม่สามารถดึงข้อมูลการขายได้: ' + (xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'),
                            confirmButtonText: 'ตกลง'
                        });
                    }
                });
            }

            function updateUI(data) {
                $('#personnel_name').text(data.sale.personnel_name || 'N/A');
                $('#sale_status_name').text(data.sale.sale_status_name || 'รอดำเนินการ');
                $('#member_name').text(data.sale.member_name || 'ไม่มี');
                $('#member_phone').text(data.sale.member_phone || '-');

                const $tbody = $('#product_table_body').empty();
                if (data.products.length === 0) {
                    $('#no_products').removeClass('hidden');
                } else {
                    $('#no_products').addClass('hidden');
                    data.products.forEach(product => {
                        $tbody.append(`
                    <tr class="border-t border-gray-200 hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm">${product.product_id}</td>
                        <td class="py-3 px-4 text-sm">${product.product_name}</td>
                        <td class="py-3 px-4 text-center text-sm">${product.sale_product_qty}</td>
                        <td class="py-3 px-4 text-right text-sm">${parseFloat(product.sale_product_price).toFixed(2)}</td>
                        <td class="py-3 px-4 text-right font-medium text-sm">${(product.sale_product_qty * product.sale_product_price).toFixed(2)}</td>
                    </tr>
                `);
                    });
                }

                const sale_net_amount = data.sale_total_amount / 1.07;
                const sale_vat_amount = data.sale_total_amount - sale_net_amount;
                $('#sale-net-amount').text(sale_net_amount.toFixed(2) + ' บาท');
                $('#sale-vat-amount').text(sale_vat_amount.toFixed(2) + ' บาท');
                $('#sale-total-amount').text(data.sale_total_amount.toFixed(2) + ' บาท');
                $('#total-net').text(data.sale_total_amount.toFixed(2) + ' บาท');
                $('#sale-paid-amount').text(parseFloat(data.sale_paid_amount).toFixed(2) + ' บาท');
                $('#sale-change-amount').text(parseFloat(data.sale_change_amount).toFixed(2) + ' บาท');
            }

            fetchSaleDetail();
            setInterval(fetchSaleDetail, 2000);

            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                    confirmButtonText: 'ตกลง'
                });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>