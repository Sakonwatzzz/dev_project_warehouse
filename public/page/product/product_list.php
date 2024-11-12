<?php
require_once __DIR__ . "../../../../config/database_server.php";

$searchTerm = '';
if (isset($_GET['search'])) {
    $searchTerm = $_GET['search'];
    // Update the query to filter based on the search term
    $query = "SELECT
        product.product_id, 
        product.product_barcode, 
        product.product_name, 
        product.product_price1, 
        product.product_price2, 
        product.product_price3, 
        product.product_price4, 
        product.product_capital, 
        product.product_type_id, 
        product.product_brand_id,
        product_brand.product_brand_name, 
        product_type.product_type_name
    FROM
        product
    INNER JOIN
        product_brand ON product.product_brand_id = product_brand.product_brand_id
    INNER JOIN
        product_type ON product.product_type_id = product_type.product_type_id
    WHERE 
        product.product_name LIKE :searchTerm
    ORDER BY 
        product.product_id ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['searchTerm' => '%' . $searchTerm . '%']);
} else {
    // Default query if no search term is entered
    $query = "SELECT
        product.product_id, 
        product.product_barcode, 
        product.product_name, 
        product.product_price1, 
        product.product_price2, 
        product.product_price3, 
        product.product_price4, 
        product.product_capital, 
        product.product_type_id, 
        product.product_brand_id,
        product_brand.product_brand_name, 
        product_type.product_type_name
    FROM
        product
    INNER JOIN
        product_brand ON product.product_brand_id = product_brand.product_brand_id
    INNER JOIN
        product_type ON product.product_type_id = product_type.product_type_id
    ORDER BY 
        product.product_id ASC";
    $stmt = $pdo->query($query);
}


// -------------------------------------------------------------
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
    $stmt->execute([$id]);
    header('Location: /index.php?page=product_list');
    exit;
}
// -------------------------------------------------------------


try {
    $productResults = $pdo->query($query); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้
} catch (PDOException $e) {
    echo 'error' . $e->getMessage();
}


// Query สำหรับประเภทสินค้า
// $queryTypes = "SELECT
//     product_type.product_type_id, 
//     product_type.product_type_name
// FROM product_type";
// $typeResults = $pdo->query(query: $queryTypes); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

// if (!$typeResults) {
//     die("Query failed: " . $pdo->getMessage());
// }

// Query สำหรับประเภทแบรนด์สินค้า
// $querybrandTypes = "SELECT
//     product_brand.product_brand_id, 
//     product_brand.product_brand_name
// FROM product_brand";
// $brandResults = $pdo->query($querybrandTypes); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

// if (!$brandResults) {
//     die("Query failed: " . $pdo->);
// }


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">

    <style>
        .notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            color: #000;
            border: 2px solid #A0A0A0;
            border-radius: 5px;
            font-family: "Tahoma", "Verdana", sans-serif;
            width: 400px;
            height: 120px;
            box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
        }

        .notification-header {
            background-color: #ffdfa0;
            color: #000;
            padding: 10px;
            font-weight: bold;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }

        .notification-body {
            padding: 20px;
            font-size: 16px;
            text-align: center;
        }
    </style>
    <title>Document</title>
</head>

<body>
    <div class="personnel-list">
        <!-- Search Form php
        <form method="get" action="">
            <input type="text" name="search" tabindex="1" autofocus value="   ?php echo ($searchTerm);    " placeholder="ค้นหาสินค้า...">
            <button type="submit">ค้นหา</button>
        </form> -->

        <div class="search">
            <input type="search" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาบุคลากร...">
        </div>


        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขสินค้าสำเร็จ!</div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
                <tr>
                    <!-- <th>รหัสสินค้า</th> -->
                    <th>ชื่อสินค้า</th>
                    <th>ราคาที่ 4</th>
                    <th>เงินทุน</th>
                    <th>ประเภทสินค้า</th>
                    <th>แบรนด์สินค้า</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($product_list = $productResults->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr onclick="window.location.href='page/product/product_detail.php?tabel=product&id=<?php echo ($product_list['product_id']) ?>'">

                        <td><?php echo ($product_list['product_name']) ?></td>
                        <td><?php echo ($product_list['product_price4']) ?></td>
                        <td><?php echo ($product_list['product_capital']) ?></td>
                        <td><?php echo ($product_list['product_type_name']) ?></td>
                        <td><?php echo ($product_list['product_brand_name']) ?></td>
                        <td>
                            <a
                                href="page/product/product_update.php?id=<?php echo ($product_list['product_id']) ?>">แก้ไข</a>
                            <a href="page/product/product_list.php?id=<?php echo ($product_list['product_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>

        </table>

    </div>
    <!-- <script>
        window.onload = function() {
            document.getElementById("searchInput").focus();
        };
    </script> -->
    <script>
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            let foundAny = false; // ตัวแปรเพื่อเก็บสถานะการค้นหา

            for (let i = 1; i < tr.length; i++) { // เริ่มจาก 1 เพื่อข้าม header
                let found = false;
                const td = tr[i].getElementsByTagName("td");
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none"; // แสดงหรือซ่อนแถว
                if (found) {
                    foundAny = true; // ถ้าพบข้อมูลอย่างน้อย 1 แถว
                }
            }

            // แสดงข้อความเมื่อไม่พบข้อมูล
            const noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    const message = document.createElement("p");
                    message.id = "noDataMessage";
                    message.textContent = "ไม่พบข้อมูล";
                    message.style.color = "red"; // ปรับสีข้อความ
                    message.style.fontWeight = "bold"; // ปรับความหนาของข้อความ
                    message.style.marginTop = "10px"; // เพิ่มระยะห่างด้านบน
                    table.parentNode.insertBefore(message, table); // เพิ่มข้อความก่อนตาราง
                }
            } else {
                if (noDataMessage) {
                    noDataMessage.remove(); // ลบข้อความเมื่อพบข้อมูล
                }
            }
        }
    </script>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById("searchInput");
            searchInput.focus();
            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.style.display = 'block'; // แสดงการแจ้งเตือน

                // ลบการแจ้งเตือนหลัง 2 วินาที
                setTimeout(() => {
                    notification.style.display = 'none'; // ซ่อนการแจ้งเตือน
                }, 2000);
            }
        });
    </script>
</body>

</html>