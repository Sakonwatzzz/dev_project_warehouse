<?php
require_once __DIR__ . '../../../../config/database_server.php';

// รับค่าจาก URL เพื่อหา type ของผลิตภัณฑ์
$productTypeId = isset($_GET['id']) ? $_GET['id'] : '';

// คำสั่ง SQL เพื่อดึงข้อมูลสินค้าในประเภทที่เลือก
$query = "
    SELECT 
        product.product_id, 
        product.product_barcode, 
        product.product_name, 
        product.product_price1, 
        product.product_price2, 
        product.product_price3, 
        product.product_price4, 
        product.product_capital, 
        product.product_type_id
    FROM 
        product
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
                <h2 class="text-2xl font-bold">รายการสินค้าตามประเภท</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาสินค้า...">
            </div>
        </div>

        <?php if ($productData): ?>
            <table class="data-table mt-5 mb-8 w-[100%]  border shadow-lg" id="dataTable">
                <thead>
                    <tr>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">รหัสสินค้า</th>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">บาร์โค้ด</th>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ชื่อสินค้า</th>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ราคาขาย 1</th>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ราคาขาย 2</th>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ราคาขาย 3</th>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ราคาทุน</th>
                        <th class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">จัดการ</th>
                    </tr>
                </thead>

                <!--ลูปข้อมูลตามประเภทสินค้า-->
                <tbody>
                    <?php foreach ($productData as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_barcode']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_price1']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_price2']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_price3']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_price4']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_capital']); ?></td>
                            <td class="text-center">
                                <a href="?page=product_update&id=<?php echo $product['product_id']; ?>" class="bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 text-white text-lg border border-yellow-700 px-2 py-1 rounded shadow-md hover:bg-[#FFF9C4]">แก้ไข</a>
                                <a href="?page=product_delete&id=<?php echo $product['product_id']; ?>" onclick="return confirm('ต้องการลบสินค้า?')" class="bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 px-4 py-1 rounded shadow-md hover:bg-[#FFEBEE]">ลบ</a>
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
            <a href="/?page=product_type_group">ย้อนกลับ</a>
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
