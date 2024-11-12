<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brandname = $_POST['brandname'] ?? '';

    try {
        $stmt = $pdo->prepare('INSERT INTO product_brand (product_brand_name) VALUES (?)');
        $stmt->execute([$brandname]);

        header('Location: /index.php?page=product_brand');
        exit;
    } catch (PDOException $e) {
        echo 'Database error: ' . $e->getMessage();
    }
}


//ส่งตัวแปร 2 ตัว
if (isset($_GET['is_del'])) {
    $product_brand_id = $_GET['product_brand_id'];
    $stmt = $pdo->prepare("DELETE FROM product_brand WHERE product_brand_id = ?");
    $stmt->execute([$product_brand_id]);
    header('Location: /index.php?page=product_brand');
    // กลับไปหน้าแสดงผล
}

$query = "SELECT product_brand_id, 
                product_brand_name 
            FROM product_brand";
$stmt = $pdo->prepare($query);
$stmt->execute();

try {
    $productbrands = $pdo->query($query);
} catch (Exception $e) {
    echo 'error' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
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
        <h2 class="title">ข้อมูลแบรนสินค้า</h2>
        <div class="search">
            <input type="search" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาประเภทสินค้า...">
        </div>

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขแบรนสำเร็จ!</div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>รหัสประแบรนต์สินค้า</th>
                    <th>ชื่อประแบรนต์สินค้า</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <form action="" method="POST"> <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
                        <td></td> <!-- ช่องรหัสแบรนสินค้า ให้แสดงเป็น "อัตโนมัติ" -->
                        <td>
                            <input type="text" id="brandname" name="brandname" placeholder="ชื่อประแบรนต์สินค้า" required
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />
                        </td>
                        <td>
                            <button type="submit" class="px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                                เพิ่ม
                            </button>

                        </td>
                    </form>
                </tr>

                <?php while ($product_brand_list = $productbrands->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo ($product_brand_list['product_brand_id']) ?></td>

                        <td><?php echo ($product_brand_list['product_brand_name']) ?></td>

                        <td>
                            <a
                                href="product_brand_update.php?id=<?php echo ($product_brand_list['product_brand_id']) ?>">แก้ไข</a>
                            <a href="?id=<?php echo ($product_brand_list['product_brand_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </div>

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