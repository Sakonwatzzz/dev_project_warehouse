<?php
require_once __DIR__ . '../../../../config/database_server.php';
$sale_id = $_GET['sale_id'] ?? $_POST['sale_id'] ?? $_SESSION['current_sale_id'] ?? null;
if (!$sale_id) {
    $error_message = "ไม่พบรหัสการขาย: ค่า sale_id ว่าง";
} else {
    try {
        $query = "SELECT 
                sale.sale_id, 
                sale.sale_date, 
                sale.sale_status_id, 
                sale.member_id, 
                sale.personnel_id, 
                sale_product.sale_product_id, 
                sale_product.sale_product_qty, 
                sale_product.sale_product_price, 
                sale_product.sale_product_capital, 
                sale_product.product_id, 
                sale_product.warehouse_id, 
                product.product_name, 
                product.product_price1, 
                product.product_price2, 
                product.product_price3, 
                product.product_price4, 
                product.product_capital, 
                member.member_name, 
                member.member_phone, 
                member.member_register_date, 
                member.member_type_id, 
                member.member_address, 
                personnel.personnel_name, 
                personnel.personnel_phone, 
                personnel.personnel_username, 
                sale_status.sale_status_name
            FROM
                sale
                LEFT JOIN sale_product ON sale.sale_id = sale_product.sale_id
                LEFT JOIN product ON product.product_id = sale_product.product_id
                LEFT JOIN member ON sale.member_id = member.member_id
                LEFT JOIN personnel ON sale.personnel_id = personnel.personnel_id
                LEFT JOIN sale_status ON sale.sale_status_id = sale_status.sale_status_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($sale_products)) {
            $error_message = "ไม่พบข้อมูลการขายในระบบ";
        } else {
            // คำนวณยอดรวมสำหรับแต่ละ sale_id
            $total_price_by_sale = [];
            foreach ($sale_products as $sale_product) {
                $sale_id = $sale_product['sale_id'];
                if (!isset($total_price_by_sale[$sale_id])) {
                    $total_price_by_sale[$sale_id] = 0;
                }
                $total_price_by_sale[$sale_id] += $sale_product['sale_product_qty'] * $sale_product['sale_product_price'];
            }
        }
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        $error_message = "การเชื่อมต่อฐานข้อมูลเกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดบิลการขาย</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4">
        <!-- ส่วนที่ 1: ข้อมูลบิล -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">รายละเอียดบิล</h2>
                <a href="http://localhost:8000/?page=sale_list" class="text-blue-500 hover:text-blue-700">กลับ</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- รหัสการขาย -->
                <div>
                    <p class="text-gray-600 font-medium">รหัสการขาย:</p>
                    <p class="text-gray-800">0011</p>
                </div>
                <!-- วันที่ขาย -->
                <div>
                    <p class="text-gray-600 font-medium">วันที่ขาย:</p>
                    <p class="text-gray-800">28/04/2025 03:31:50</p>
                </div>
                <!-- รหัสสถานะการขาย -->
                <div>
                    <p class="text-gray-600 font-medium">รหัสสถานะการขาย:</p>
                    <p class="text-gray-800">สำเร็จ</p>
                </div>
                <!-- รหัสสมาชิก -->
                <div>
                    <p class="text-gray-600 font-medium">รหัสสมาชิก:</p>
                    <p class="text-gray-800">นายสมชาย ใจดี</p>
                </div>
                <!-- พนักงาน -->
                <div>
                    <p class="text-gray-600 font-medium">พนักงาน:</p>
                    <p class="text-gray-800">นายสมชาย ใจดี</p>
                </div>
                <!-- POS -->
                <div>
                    <p class="text-gray-600 font-medium">POS:</p>
                    <p class="text-gray-800">POS #1 (Website)</p>
                </div>
            </div>
        </div>

        <!-- ส่วนที่ 2: รายการสินค้าที่ขาย -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">รายการสินค้าที่ขาย</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-200 text-gray-700">
                            <th class="py-3 px-4 text-left border-b">รหัสสินค้า</th>
                            <th class="py-3 px-4 text-left border-b">ชื่อสินค้า</th>
                            <th class="py-3 px-4 text-left border-b">จำนวน</th>
                            <th class="py-3 px-4 text-left border-b">ราคาขาย</th>
                            <th class="py-3 px-4 text-left border-b">ราคาทุน</th>
                            <th class="py-3 px-4 text-left border-b">รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 border-b text-gray-600">0008</td>
                            <td class="py-3 px-4 border-b text-gray-600">กาแฟร้อนอเมริกาโน่</td>
                            <td class="py-3 px-4 border-b text-gray-600">1 ชิ้น</td>
                            <td class="py-3 px-4 border-b text-gray-600">29.00</td>
                            <td class="py-3 px-4 border-b text-gray-600">-</td>
                            <td class="py-3 px-4 border-b text-gray-600">29.00</td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 border-b text-gray-600">0011</td>
                            <td class="py-3 px-4 border-b text-gray-600">ชานมไข่มุก</td>
                            <td class="py-3 px-4 border-b text-gray-600">1 ชิ้น</td>
                            <td class="py-3 px-4 border-b text-gray-600">69.00</td>
                            <td class="py-3 px-4 border-b text-gray-600">-</td>
                            <td class="py-3 px-4 border-b text-gray-600">69.00</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-200 text-gray-700">
                            <td colspan="5" class="py-3 px-4 text-right border-t font-semibold">รวมทั้งสิ้น</td>
                            <td class="py-3 px-4 border-t font-semibold">98.00</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="py-3 px-4 text-right text-gray-600">ส่วนลด</td>
                            <td class="py-3 px-4 text-gray-600">0.00</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="py-3 px-4 text-right text-gray-600">ค่าจัดส่ง</td>
                            <td class="py-3 px-4 text-gray-600">0.00</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="py-3 px-4 text-right text-gray-600">ส่วนลดโปรโมชั่น</td>
                            <td class="py-3 px-4 text-gray-600">91.59</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="py-3 px-4 text-right text-gray-600">ภาษี 7 %</td>
                            <td class="py-3 px-4 text-gray-600">6.41</td>
                        </tr>
                        <tr class="border-t">
                            <td colspan="5" class="py-3 px-4 text-right font-semibold text-gray-800">ยอดรวมสุทธิ</td>
                            <td class="py-3 px-4 font-semibold text-gray-800">98.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</body>

</html>