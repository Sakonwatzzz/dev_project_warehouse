<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

// การตรวจสอบว่าเป็นการ POST request หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typename = trim($_POST['typename'] ?? ''); // กำจัดช่องว่างที่ไม่จำเป็น

    // ตรวจสอบว่า typename ไม่ว่าง
    if (!empty($typename)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO product_type (product_type_name) VALUES (?)');
            $stmt->execute([$typename]);

            // รีเฟรชหน้าเดิมเมื่อบันทึกเสร็จ
            header("Location: ?page=product_type&success"); // เปลี่ยนไปยังหน้าหลังจากบันทึกข้อมูล
            exit; // หยุดการทำงานหลัง header
        } catch (PDOException $e) {
            // แสดงข้อความผิดพลาดหากมีปัญหาในการเพิ่มข้อมูล
            echo 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        echo 'Error: Type name is required.'; // หากไม่กรอกข้อมูล typename
    }
}

// ตรวจสอบว่า `id` มีค่าใน URL หรือไม่ก่อนที่จะทำการลบ
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // ตรวจสอบว่า id เป็นตัวเลข (ป้องกัน SQL Injection)
    if (is_numeric($id)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM product_type WHERE product_type_id = ?");
            $stmt->execute([$id]);

            // กลับไปหน้าแสดงผลหลังจากลบข้อมูล
            header('Location: /index.php?page=product_type');
            exit; // หยุดการทำงานหลัง header
        } catch (PDOException $e) {
            echo 'Database error: ' . htmlspecialchars($e->getMessage()); // แสดงข้อผิดพลาดหากมีปัญหา
        }
    } else {
        echo 'Invalid ID.'; // หาก `id` ไม่ถูกต้อง
    }
}

// ดึงข้อมูลประเภทสินค้าทั้งหมดมาแสดงผล
$query = "SELECT product_type_id, product_type_name FROM product_type";
$stmt = $pdo->prepare($query);
$stmt->execute();

try {
    // ใช้ fetch() ในลูป while เพื่อดึงข้อมูลทีละแถว
    $productTypes = $stmt->fetch(PDO::FETCH_ASSOC); // ดึงแถวแรก
} catch (PDOException $e) {
    echo 'Error: ' . htmlspecialchars($e->getMessage()); // แสดงข้อผิดพลาดหากไม่สามารถดึงข้อมูลได้
}


// จำนวนข้อมูลทั้งหมดในตาราง
$query = "SELECT COUNT(*) as total_count FROM product_type";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];


// การแบ่งหน้า
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// นับจำนวนรายการทั้งหมด
try {
    $total_query = "SELECT COUNT(*) FROM product_type";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// ดึงข้อมูลตามการแบ่งหน้า
try {
    $query = "SELECT product_type_id, product_type_name FROM product_type LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $resultsproducttype = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <style>
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
            background-color: cyan;
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

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 16px;
            text-decoration: none;
            background-color: #f1f1f1;
            color: black;
            border-radius: 5px;
            margin: 0 4px;
        }

        .pagination a.active {
            background-color: #5b97b2;
            ;
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;
            
        }
    </style>
    <title>Document</title>
</head>

<body>
    <div class="personnel-list">
        <div class="title">
            <div class="count">
                <h2 style="font-weight: bold;">ข้อมูลประเภทสินค้า</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาประเภทสินค้า...">
            </div>

        </div>



        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">เพิ่มสำเร็จ!</div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
                <tr>
                    <th>รหัสประเภทสินค้า</th>
                    <th>ชื่อประเภทสินค้า</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <form action="" method="POST">
                        <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
                        <td></td> <!-- ช่องรหัสประเภทสินค้า ให้แสดงเป็น "อัตโนมัติ" -->
                        <td>
                            <input type="text" id="typename" name="typename" placeholder="ชื่อประเภทสินค้า"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />

                        </td>
                        <td>
                            <button type="submit"
                                class="px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                                เพิ่ม
                            </button>

                        </td>
                    </form>
                </tr>

                <?php while ($product_type = $resultsproducttype->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo ($product_type['product_type_id']) ?></td>
                        <td><?php echo ($product_type['product_type_name']) ?></td>
                        <td>
                            <a href="product_type_update.php?id=<?php echo ($product_type['product_type_id']) ?>">แก้ไข</a>
                            <a href="?id=<?php echo ($product_type['product_type_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>


        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=product_type&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=product_type&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=product_type&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>


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