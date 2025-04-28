<?php
require_once __DIR__ . '../../../../config/database_server.php';

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

$sale_statusQuery = $pdo->query("SELECT sale_status_id, sale_status_name FROM sale_status");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตัวกรองการค้นหา</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4">
        <div class="bg-white shadow-md rounded-lg p-4 mb-4">
            <div class="flex justify-start items-center gap-4">
                <div class="flex items-center">
                    <input type="text" placeholder="ค้นหา..."
                        class="border border-gray-300 rounded-sm px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 w-96">
                </div>
                <div class="flex items-center space-x-2">
                    <input type="date"
                        class="border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="2025-04-28">
                    <span class="text-gray-600">ถึง</span>
                    <input type="date"
                        class="border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="2025-04-28">
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-gray-700">สถานะการจ่าย</h2>
                    <select name="sale_status_id"
                        class="border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php while ($status_list = $sale_statusQuery->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo htmlspecialchars($status_list['sale_status_id']); ?>">
                                <?php echo htmlspecialchars($status_list['sale_status_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <?php if (isset($error_message)): ?>
                <p class="text-center text-red-600 py-4"><?php echo $error_message; ?></p>
            <?php else: ?>
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-200 text-gray-700">
                            <th class="py-3 px-4 text-left border-b">รหัสการขาย</th>
                            <th class="py-3 px-4 text-left border-b">วันที่ขาย</th>
                            <th class="py-3 px-4 text-left border-b">สถานะการขาย</th>
                            <th class="py-3 px-4 text-left border-b">ชื่อสมาชิก</th>
                            <th class="py-3 px-4 text-left border-b">เบอร์โทรศัพท์สมาชิก</th>
                            <th class="py-3 px-4 text-left border-b">พนักงาน</th>
                            <th class="py-3 px-4 text-left border-b">ยอดรวม</th>
                            <th class="py-3 px-4 text-left border-b">ดูรายละเอียด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $current_sale_id = null;
                        foreach ($sale_products as $sale_product):
                            if ($current_sale_id !== $sale_product['sale_id']):
                                $current_sale_id = $sale_product['sale_id'];
                        ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['sale_id']); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['sale_date']); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['sale_status_name']); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['member_name']); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['member_phone']); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600"><?php echo htmlspecialchars($sale_product['personnel_name']); ?></td>
                                    <td class="py-3 px-4 border-b text-gray-600">
                                        ฿<?php echo number_format($total_price_by_sale[$sale_product['sale_id']], 2); ?>
                                    </td>
                                    <td class="py-3 px-4 border-b">
                                        <a href="http://localhost:8000/?page=sale_detail&sale_id=<?php echo urlencode($sale_product['sale_id']); ?>"
                                           class="text-blue-500 hover:text-blue-700">ดูรายละเอียด</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-200 text-gray-700">
                            <td colspan="6" class="py-3 px-4 text-right border-t font-semibold">รวมทั้งหมด</td>
                            <td class="py-3 px-4 border-t font-semibold">
                                ฿<?php echo number_format(array_sum($total_price_by_sale), 2); ?>
                            </td>
                            <td class="py-3 px-4 border-t"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>