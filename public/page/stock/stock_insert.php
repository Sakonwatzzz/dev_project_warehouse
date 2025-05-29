<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Query เพื่อดึงข้อมูลคลัง
try {
    $query = "SELECT warehouse_id, warehouse_name FROM warehouse";
    $stmt = $pdo->query($query);
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching warehouses: " . $e->getMessage());
    $warehouses = [];
}

// กำหนดค่าเริ่มต้นสำหรับ personnel (ถ้าจำเป็น)
$personnel_id = $_SESSION['personnel_id'] ?? 1; // สมมติใช้ session หรือค่า default
$query = "SELECT personnel_name FROM personnel WHERE personnel_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$personnel_id]);
$personnel_name = $stmt->fetchColumn() ?: 'Unknown';
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

        /* สไตล์สำหรับ barcode_suggestions */
        #barcode_suggestions {
            max-height: 15rem;
            /* จำกัดความสูง (ประมาณ 5 รายการ) */
            overflow-y: auto;
            /* เพิ่ม Scrollbar */
            scrollbar-width: thin;
            /* Firefox */
        }

        /* ปรับแต่ง Scrollbar สำหรับ Chrome/Safari */
        #barcode_suggestions::-webkit-scrollbar {
            width: 6px;
        }

        #barcode_suggestions::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        #barcode_suggestions::-webkit-scrollbar-thumb {
            background: #a0aec0;
            border-radius: 4px;
        }

        #barcode_suggestions::-webkit-scrollbar-thumb:hover {
            background: #718096;
        }

        /* สไตล์ปุ่มกากบาท */
        #clear-suggestions {
            outline: none;
            transition: color 0.2s;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="page/stock/js/search_product.js"></script>
    <script src="page/stock/js/delete_product_check.js"></script>
    <script src="page/stock/js/stock_insert_list.js?t=<?= time() ?>"></script>
    <script src="page/stock/js/stock_switch.js?t=<?= time() ?>"></script>
</head>

<body class="main-body bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <header class="bg-blue-600 text-white shadow-lg">
            <div class="container mx-auto px-4 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold">ตรวจสอบสต๊อก <span id="warehouse_display">คลัง: ไม่ระบุ</span></h1>
                <div class="flex gap-4 items-center">
                    <div>
                        <i class="fas fa-user mr-2"></i><span
                            id="personnel_name"><?= htmlspecialchars($personnel_name) ?></span>
                        <input type="hidden" id="personnel_id" value="<?= htmlspecialchars($personnel_id) ?>">
                    </div>
                    <div>
                        <i class="far fa-clock mr-2"></i><span id="displayDateTime"></span>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="p-2 py-4 flex justify-end gap-2">
                <select id="warehouse_select"
                    class="w-48 border rounded-md px-3 py-2 text-sm cursor-pointer hover:bg-gray-200" hidden>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= $warehouse['warehouse_id'] ?>" <?php echo $warehouse['warehouse_id'] == 1 ? 'selected' : '' ?>>
                            <?php echo ($warehouse['warehouse_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="reset-warehouse" hidden
                    class="bg-gray-500 hover:bg-gray-600 text-white rounded-md p-2 text-sm cursor-pointer"
                    title="รีเซ็ตคลัง">
                    <i class="fas fa-undo"></i>
                </button>
                <select id="stock_id_select" hidden
                    class="w-48 border rounded-md px-3 py-2 text-sm cursor-pointer hover:bg-gray-200">
                    <option value="">เลือกใบตรวจนับสต๊อก</option>
                </select>
                <a id="btn-delete-stock-session"
                    class="bg-red-600 hover:bg-red-700 text-white rounded-md p-2 px-3 text-sm cursor-pointer">
                    <span class="text">ลบรายการตรวจสอบสต๊อกสินค้า</span>
                    <span
                        class="spinner hidden animate-spin h-4 w-4 border-2 border-t-transparent border-white rounded-full ml-2"></span>
                </a>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md mb-6">
                <form id="form_insert_stock">
                    <input type="hidden" name="warehouse_id" id="warehouse_id">
                    <input type="hidden" name="lot_id" id="lot_id">
                    <input type="hidden" name="lot_product_id" id="lot_product_id">
                    <input type="hidden" name="is_update" id="is_update" value="0">
                    <input type="hidden" name="stock_date" id="stock_date">
                    <input type="hidden" id="current_stock_id">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"
                                for="product_id">สแกนรหัสสินค้า</label>
                            <input type="text" id="product_id_input" name="product_id"
                                class="w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="ป้อนรหัสสินค้า...." autocomplete="off" autofocus>
                            <div id="barcode_suggestions"
                                class="z-10 w-full bg-white border border-gray-200 rounded-b-lg shadow-lg hidden max-h-60 overflow-y-auto">
                            </div>
                        </div>
                        <div>
                            <label for="stock_check_actual"
                                class="block text-sm font-medium text-gray-700 mb-1">จำนวนที่นับได้จริง</label>
                            <input type="number" id="stock_check_actual" name="stock_check_actual"
                                class="w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="500" min="0" value="1">
                        </div>
                        <div class="col-span-full">
                            <label for="stock-check-remark"
                                class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
                            <textarea id="stock-check-remark" name="stock_check_remark" rows="3"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="ระบุหมายเหตุ (ถ้ามี)"></textarea>
                        </div>
                    </div>
                    <div id="product_info" class="mt-4 hidden"></div>
                    <div class="mt-4 flex justify-between items-center">
                        <p class="text-sm text-green-700">
                            <i class="fas fa-info-circle mr-1"></i>
                            รูปแบบการเพิ่ม: รหัส*จำนวน (เช่น 12345*2)
                        </p>
                        <button id="btn-submit" type="submit"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md flex items-center cursor-pointer">
                            <span class="text">บันทึก</span>
                            <span
                                class="spinner hidden animate-spin h-4 w-4 border-2 border-t-transparent border-white rounded-full ml-2"></span>
                        </button>
                    </div>
                </form>
                <div class="flex justify-end mt-4">
                    <a id="btn-confirm-check"
                        class="bg-green-600 text-white px-4 py-2 rounded-md flex items-center hover:bg-green-700 disabled:bg-gray-400 cursor-pointer"
                        disabled>
                        <span class="text">ยืนยันรายการตรวจสอบสต๊อกสินค้า</span>
                        <span
                            class="spinner hidden animate-spin h-4 w-4 border-2 border-t-transparent border-white rounded-full ml-2"></span>
                    </a>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-md rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-blue-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">วันที่</th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">รหัสสินค้า
                                </th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">ชื่อสินค้า
                                </th>
                                <th class="px-6 py-3 text-right text-sm font-bold text-gray-900 uppercase">จำนวนจากระบบ
                                </th>
                                <th class="px-6 py-3 text-right text-sm font-bold text-gray-900 uppercase">จำนวนนับจริง
                                </th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase" hidden>พนักงาน
                                </th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase" hidden>สถานะ</th>
                                <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 uppercase">หมายเหตุ</th>
                                <th class="px-6 py-3 text-center text-sm font-bold text-gray-900 uppercase">การจัดการ
                                </th>
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

        // เพิ่ม event listener สำหรับ warehouse_select
        $("#warehouse_select").on("change", function () {
            const warehouse_id = $(this).val();
            const warehouse_name = $(this).find("option:selected").text().trim() || 'ไม่ระบุ';
            // อัปเดต warehouse_display
            $("#warehouse_display").text(`คลัง: ${warehouse_name}`);
            // ตั้งค่า warehouse_id ในฟอร์ม
            $("#warehouse_id").val(warehouse_id);
            // ล้างข้อมูลในฟอร์มและ suggestions
            $("#product_id_input").val("").focus();
            $("#barcode_suggestions").addClass("hidden").empty();
            $("#product_info").addClass("hidden").empty();
            $("#lot_id").val("");
            $("#lot_product_id").val("");
            $("#stock_check_actual").val("1");
            $("#stock-check-remark").val("");
            // แจ้งเตือนเมื่อเลือกคลัง (ไม่บังคับ)
            // if (warehouse_id) {
            //     Swal.fire({
            //         toast: true,
            //         position: "top-end",
            //         icon: "info",
            //         title: `เลือกคลัง: ${warehouse_name}`,
            //         showConfirmButton: false,
            //         timer: 1500,
            //         timerProgressBar: true
            //     });
            // }
        });

        // อัปเดต displayDateTime ทุกวินาที (ถ้าต้องการแสดงเวลาจริง)
        setInterval(function () {
            document.getElementById('displayDateTime').textContent = luxon.DateTime.now().setZone('Asia/Bangkok').toFormat('dd/MM/yyyy HH:mm:ss');
        }, 1000);
    </script>
</body>

</html>