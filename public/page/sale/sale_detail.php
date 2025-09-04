<?php
require_once __DIR__ . '../../../../config/database_server.php';

$accessToken = getToken('access_token');
if (!$accessToken || !validateToken($accessToken)) {
    $refreshToken = getToken('refresh_token');
    if ($refreshToken && validateToken($refreshToken)) {
        $accessToken = refreshToken($refreshToken);
    } else {
        header('Location: login.php');
        exit();
    }
}

$sale_id = $_GET['sale_id'] ?? $_POST['sale_id'] ?? $_SESSION['current_sale_id'] ?? null;
if (!$sale_id) {
    $error_message = "ไม่พบรหัสการขาย: ค่า sale_id ว่าง";
} else {
    try {
        // ดึงข้อมูลการขาย และสินค้า
        $query = "SELECT 
                sale.sale_id, 
                sale.sale_date, 
                sale.sale_status_id, 
                sale.member_id, 
                sale.personnel_id, 
                sale_status.sale_status_name,	
                sale.sale_net_amount, 
	            sale.sale_vat_amount, 
	            sale.sale_paid_amount, 
	            sale.sale_total_amount, 
	            sale.sale_change_amount,
                member.member_name,
                member.member_phone,
                personnel.personnel_name,
                personnel.personnel_phone,
                sale_product.sale_product_id,
                sale_product.sale_product_qty,
                sale_product.sale_product_price,
                sale_product.sale_product_capital,
                product.product_id,
                product.product_name
            FROM sale
            LEFT JOIN sale_product ON sale.sale_id = sale_product.sale_id
            LEFT JOIN product ON sale_product.product_id = product.product_id
            LEFT JOIN member ON sale.member_id = member.member_id
            LEFT JOIN personnel ON sale.personnel_id = personnel.personnel_id
            LEFT JOIN sale_status ON sale.sale_status_id = sale_status.sale_status_id
            WHERE sale.sale_id = :sale_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['sale_id' => $sale_id]);
        $sale_records = $stmt;


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

        <?php while ($sale_detail_result = $sale_records->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="bg-white shadow-md rounded-lg p-6 mb-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">รายละเอียดบิล</h2>
                    <a href="http://localhost:8000/?page=sale_list" class="text-blue-500 hover:text-blue-700">กลับ</a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-gray-600 font-medium">รหัสการขาย:</p>
                        <p class="text-gray-800"><?php echo htmlspecialchars($sale_detail_result['sale_id']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">วันที่ขาย:</p>
                        <p class="text-gray-800"><?php echo htmlspecialchars($sale_detail_result['sale_date']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">สถานะ:</p>
                        <p class="text-gray-800"><?php echo htmlspecialchars($sale_detail_result['sale_status_name']); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">ชื่อสมาชิก:</p>
                        <p class="text-gray-800"><?php echo htmlspecialchars($sale_detail_result['member_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">พนักงาน:</p>
                        <p class="text-gray-800"><?php echo htmlspecialchars($sale_detail_result['personnel_name']); ?></p>
                    </div>
                </div>
            </div>

            <!-- รายการสินค้า -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">รายการสินค้าที่ขาย</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-200 text-gray-700">
                                <th class="py-3 px-4 text-left border-b">รหัสสินค้า</th>
                                <th class="py-3 px-4 text-left border-b">ชื่อสินค้า</th>
                                <th class="py-3 px-4 text-right border-b">จำนวน</th>
                                <th class="py-3 px-4 text-right border-b">ราคาขาย/ชิ้น</th>
                                <th class="py-3 px-4 text-right border-b">ราคาทุน</th>
                                <th class="py-3 px-4 text-right border-b">รวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4 border-b">
                                    <?php echo htmlspecialchars($sale_detail_result['product_id']); ?></td>
                                <td class="py-3 px-4 border-b">
                                    <?php echo htmlspecialchars($sale_detail_result['product_name']); ?>
                                </td>
                                <td class="py-3 px-4 border-b text-right">
                                    <?php echo htmlspecialchars($sale_detail_result['sale_product_qty']); ?>
                                </td>
                                <td class="py-3 px-4 border-b text-right">
                                    <?php echo number_format($sale_detail_result['sale_product_price'], 2); ?>
                                </td>
                                <td class="py-3 px-4 border-b text-right">
                                    <?php echo number_format($sale_detail_result['sale_product_capital'], 2); ?>
                                </td>
                                <td class="py-3 px-4 border-b text-right">
                                    <?php echo number_format($sale_detail_result['sale_product_qty'] * $sale_detail_result['sale_product_price'], 2); ?>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-100">
                                <td colspan="5" class="py-3 px-4 text-right font-semibold">รวมเงินทั้งสิ้น</td>
                                <td class="py-3 px-4 text-right font-semibold">
                                    <?php echo ($sale_detail_result['sale_total_amount']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5" class="py-3 px-4 text-right text-gray-600">ยอดสุทธิ (ไม่รวม VAT):</td>
                                <td class="py-3 px-4 text-right text-gray-600">
                                    <?php echo ($sale_detail_result['sale_net_amount']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5" class="py-3 px-4 text-right text-gray-600">VAT (7%):</td>
                                <td class="py-3 px-4 text-right text-gray-600">
                                    <?php echo ($sale_detail_result['sale_vat_amount']); ?>
                                </td>
                            </tr>
                            <tr class="border-t">
                                <td colspan="5" class="py-3 px-4 text-right font-bold text-gray-800">ยอดรวมสุทธิ</td>
                                <td class="py-3 px-4 text-right font-bold text-gray-800">
                                    <?php echo ($sale_detail_result['sale_total_amount']); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</body>

</html>