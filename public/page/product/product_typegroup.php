<?php
require_once __DIR__ . '../../../../config/database_server.php';

// รับค่าจาก URL เพื่อหา type ของผลิตภัณฑ์
$productTypeId = isset($_GET['id']) ? $_GET['id'] : '';

// คำสั่ง SQL เพื่อดึงข้อมูลสินค้าในประเภทที่เลือก
$query = "
    SELECT 
        product.product_id, 
        product.product_name, 
        product.product_price1, 
        product.product_price2, 
        product.product_price3, 
        product.product_price4, 
        product.product_capital, 
        product.product_type_id,
        product_type.product_type_name
    FROM 
        product
    INNER JOIN
        product_type ON product.product_type_id = product_type.product_type_id
    WHERE 
        product.product_type_id = :product_type_id
    ORDER BY 
        product.product_name;  
";

try {
    // ใช้ prepared statement เพื่อป้องกัน SQL Injection
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':product_type_id', $productTypeId, PDO::PARAM_INT);
    $stmt->execute();

    // ดึงข้อมูลสินค้าตามประเภท
    $productData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
    exit();
}

// ดึงชื่อประเภทสินค้า
$queryTypeName = "SELECT product_type_name FROM product_type WHERE product_type_id = :product_type_id";
try {
    $stmtType = $pdo->prepare($queryTypeName);
    $stmtType->bindParam(':product_type_id', $productTypeId, PDO::PARAM_INT);
    $stmtType->execute();
    $typeResult = $stmtType->fetch(PDO::FETCH_ASSOC);
    $productTypeName = $typeResult['product_type_name'];
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
    exit();
}
// คำสั่ง SQL เพื่อคำนวณจำนวนข้อมูลทั้งหมดในประเภทที่เลือก
$query = "
    SELECT COUNT(*) as total_count
    FROM product
    WHERE product.product_type_id = :product_type_id
";

try {
    // ใช้ prepared statement เพื่อป้องกัน SQL Injection
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':product_type_id', $productTypeId, PDO::PARAM_INT);
    $stmt->execute();

    // ดึงจำนวนข้อมูลทั้งหมด
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <link href="../../output.css" rel="stylesheet">
    <title>กลุ่มสินค้า</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

        .title {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .count {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .listcount {
            font-size: 16px;
            text-align: right;
            color: #000;
            padding: 4px 10px 4px 10px;
            border: 1px solid gray;
            border-radius: 5px;
            background-color: #fff;
            display: inline-block;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            width: 70px;
        }

        .detail-action {
            margin-top: 25px;
            width: auto;
            height: 40px;
        }

        .detail-action a {
            border-radius: 6px;
            width: 50px;
            height: 40px;
            padding: 10px;
            background-color: #333;
            text-decoration: none;
            color: #fff;
        }

        .detail-action a:hover {
            background-color: #000000;
        }
    </style>
</head>

<body>
    <div class="font-[Tahoma] mx-[23rem] mt-11">
        <div class="title">
            <div class="count">
                <h2 class="text-2xl font-bold">
                    ประเภท:
                    <?php
                    if (!empty($productData)) {
                        echo htmlspecialchars($productData[0]['product_type_name']);
                    } else {
                        echo htmlspecialchars($productTypeName ?? 'ไม่ทราบประเภท');
                    }
                    ?>
                </h2>


                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>

            <div class="mt-4 md:mt-0">
                <input type="text" id="searchInput" onkeyup="searchTable()"
                    placeholder="ค้นหาประเภทสินค้า..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <?php if ($productData): ?>
            <table class="data-table mt-5 mb-8 w-[100%]  border shadow-lg" id="dataTable">
                <thead>
                    <tr>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">รหัสสินค้า</th>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ชื่อสินค้า</th>

                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">จัดการ</th>
                    </tr>
                </thead>

                <!--ลูปข้อมูลตามประเภทสินค้า-->
                <tbody>
                    <?php foreach ($productData as $product): ?>
                        <tr class="bg-white hover:bg-gray-100 cursor-pointer">
                            <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td class="py-3 px-4 text-center space-x-2 border border-gray-300">
                                <a href="?page=product_update&id=<?php echo $product['product_id']; ?>"
                                    class="inline-flex items-center px-3 py-1 bg-yellow-500 text-white text-sm font-medium rounded-md hover:bg-yellow-600 transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    แก้ไข
                                </a>
                                <a href="?page=product_delete&id=<?php echo $product['product_id']; ?>" onclick="return confirm('ต้องการลบสินค้า?')"
                                    class="inline-flex items-center px-3 py-1 bg-red-500 text-white text-sm font-medium rounded-md hover:bg-red-600 transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a2 2 0 00-2 2h8a2 2 0 00-2-2m-4 0V3m-3 4h10"></path>
                                    </svg>
                                    ลบ
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>ไม่มีสินค้าในประเภทนี้</p>
        <?php endif; ?>

        <!--ย้อนกลับไปหน้าเดิม-->
        <div class="detail-action">
            <a href="/?page=product_type"
                class="mt-4 p-2 px-6 bg-gray-700 text-white border rounded-lg shadow-lg mr-2 hover:bg-gray-800">
                ย้อนกลับ
            </a>
        </div>
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            const thead = table.querySelector("thead");
            let foundAny = false;

            // Always keep the header visible
            thead.style.display = "";

            // Search through data rows (start from index 1 to skip header)
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName("td");
                let rowMatchesSearch = false;

                // Check every cell in the row
                for (let j = 0; j < td.length; j++) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        rowMatchesSearch = true;
                        break;
                    }
                }

                tr[i].style.display = rowMatchesSearch ? "" : "none";
                if (rowMatchesSearch) {
                    foundAny = true;
                }
            }

            // Handle "No data" message
            let noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    noDataMessage = document.createElement("p");
                    noDataMessage.id = "noDataMessage";
                    noDataMessage.textContent = "ไม่พบข้อมูล";
                    noDataMessage.style.color = "red";
                    noDataMessage.style.fontWeight = "bold";
                    noDataMessage.style.textAlign = "center";
                    noDataMessage.style.marginTop = "10px";
                    table.parentNode.insertBefore(noDataMessage, table.nextSibling);
                }
            } else {
                if (noDataMessage) {
                    noDataMessage.remove();
                }
            }
        }
    </script>
</body>

</html>