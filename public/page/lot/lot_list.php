<?php
require_once __DIR__ . '../../../../config/database_server.php'; // โหลดไฟล์การตั้งค่าการเชื่อมต่อฐานข้อมูล
ob_start(); // เริ่มต้นการเก็บบัฟเฟอร์ output (ใช้สำหรับจัดการการแสดงผล)

// ดึงชื่อคลังมาแสดงผล
$query = "SELECT lot_status_id, lot_status_name FROM lot_status";
$stmt = $pdo->prepare($query);
$stmt->execute();
$status_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงชื่อคลังมาแสดงผล
$query = "SELECT warehouse_id, warehouse_name FROM warehouse";
$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงชื่อผู่ส่งมอบมาแสดงผล
$query = "SELECT supplier_id, supplier_name FROM supplier";
$stmt = $pdo->prepare($query);
$stmt->execute();
$supplier_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);



if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM lot WHERE lot_id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?page=lot_list");
}

try {
    // การแบ่งหน้า
    $records_per_page = 5;
    $page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
    $offset = ($page - 1) * $records_per_page;

    // จำนวนข้อมูลทั้งหมดในตาราง
    $query = "SELECT COUNT(*) as total_count FROM lot";
    $stmt = $pdo->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];
    $total_pages = ceil($count / $records_per_page);
} catch (PDOException $e) {
    echo "Erro" . $e->getMessage();
}

// คำสั่ง SQL ดึงข้อมูลพร้อม LIMIT
try {
    $query = "
    SELECT
        lot_status.lot_status_id, 
        lot_status.lot_status_name, 
        supplier.supplier_id, 
        supplier.supplier_name, 
        warehouse.warehouse_name, 
        warehouse.warehouse_id,
        lot.lot_id, 
        lot.lot_detail, 
        lot.lot_date, 
        lot.lot_status_id, 
        lot.warehouse_id
    FROM
        lot
        LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
        LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
        LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
    LIMIT :offset, :records_per_page";


    // ตรวจสอบคำสั่ง SQL ก่อนการ execute
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $result_lot_list = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8"> <!-- กำหนด charset ของเอกสาร -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- กำหนดให้แสดงผลบนอุปกรณ์มือถือได้ดี -->

    <link rel="stylesheet" href="../../style/personnel_list.css">
    <link href="../../output.css" rel="stylesheet">
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
    <div class="font-[Tahoma] mx-[20rem] mt-11 ">
        <div class="title">
            <div class="count">
                <h2 class="text-2xl font-bold">ข้อมูลการรับสินค้า</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>

            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1"
                    placeholder="ค้นหารายการรับสินค้า...">
            </div>
        </div>

        <!-- ส่วนของการแจ้งเตือนเมื่อการแก้ไขเสร็จสมบูรณ์ -->
        <div id="notification" class="notification" style="display:none;">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขสมาชิกสำเร็จ!</div>
        </div>
        <table class=" mt-5 mb-8 w-[100%]  border-2 border-[#A0A0A0] shadow-lg" id="dataTable">
            <thead>
                <!-- ส่วนหัวของตาราง -->
                <tr class="">
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">สถานะล็อต</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">คลังสินค้า</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ผู้ส่งมอบ</th>
                    <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">การจัดการ</th>
                </tr>
            </thead>
            <tbody class="">
                <?php
                if ($result_lot_list->rowCount() > 0) {
                    while ($lot_list = $result_lot_list->fetch(PDO::FETCH_ASSOC)): ?>
                        <!-- ใช้ while เพื่อลูปข้อมูลที่ได้จากฐานข้อมูล -->
                        <tr class=" bg-white hover:bg-[#aec5e366] cursor-pointer ">
                            <!-- เมื่อคลิกแถวนี้จะพาไปยังหน้ารายละเอียดของสมาชิก -->
                            <!-- <td><?php echo ($lot_list['lot_id']) ?></td> -->
                            <!-- <td><?php echo ($lot_list['lot_detail']) ?></td> -->
                            <!-- <td><?php echo ($lot_list['lot_date']) ?></td> -->
                            <td class="text-start pl-4" onclick="window.location.href='?page=lot_detail&tabel=lot&id=<?php echo ($lot_list['lot_id']) ?>'"><?php echo ($lot_list['lot_status_name']) ?></td>
                            <td class="text-start pl-4"><?php echo ($lot_list['warehouse_name']) ?></td>
                            <td class="text-start pl-4"><?php echo ($lot_list['supplier_name']) ?></td>
                            <td>
                                <a href="?page=lot_detail&tabel=lot&id=<?php echo ($lot_list['lot_id']) ?>" class="  bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 
                            text-white 
                            text-lg 
                            border border-yellow-700 
                            border-t-white 
                            border-l-white 
                            px-2 
                            py-1 
                            rounded 
                            shadow-md 
                            hover:bg-[#FFF9C4] 
                            active:border-[#FFF176] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner ">
                                    แก้ไข</a>


                                <a href="?page=lot_list&id=<?php echo ($lot_list['lot_id']) ?>"
                                    onclick="return confirm('ลบหรือไม่ลบ สรุป?')" class=" bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 
                            border-t-white 
                            border-l-white 
                            px-4 
                            py-1 
                            rounded 
                                            shadow-md 
                            hover:bg-[#FFEBEE] 
                            active:border-[#EF9A9A] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner">ลบ</a>
                                <!-- ลิงก์ไปหน้าลบข้อมูลสมาชิก และมีการยืนยันการลบ -->
                            </td>
                        </tr>

                <?php endwhile;
                } else {
                    echo "<tr><td colspan='8'>ไม่มีข้อมูล</td></tr>";
                } ?>
            </tbody>
        </table>
        <div class="box">
            <a class=" bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg border border-blue-700 
                            border-t-white 
                            border-l-white 
                            px-4 
                            py-4
                            rounded 
                            shadow-md 
                            hover:bg-[#E3F2FD] 
                            active:border-[#90CAF9] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner"
                href="/?page=lot_listinsert" value="เพิ่ม">เพิ่มการรับสินค้า</a
                class="submit-button">
            <!-- แก้คำว่า "lass" เป็น "class" -->
        </div>
        <!--โค้ดนี้ใช้ในการแบ่งหน้าในตาราง-->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=lot_list&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=lot_list&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_list&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ฟังก์ชันสำหรับค้นหาข้อมูลในตาราง
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            const thead = table.querySelector("thead");
            let foundAny = false;

            // Always keep the header visible
            thead.style.display = "";

            // Always keep the input row visible 
            tr[0].style.display = "";

            // Search through data rows (start from index 2 to skip header and input row)
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

    <script>
        // การแสดงผลการแจ้งเตือนเมื่อการแก้ไขเสร็จสมบูรณ์
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search); // ตรวจสอบ URL
            const searchInput = document.getElementById("searchInput");
            searchInput.focus();
            if (urlParams.has('success')) { // ถ้ามีพารามิเตอร์ success ใน URL
                const notification = document.getElementById('notification');
                notification.style.display = 'block'; // แสดงการแจ้งเตือน

                // ซ่อนการแจ้งเตือนหลัง 2 วินาที
                setTimeout(() => {
                    notification.style.display = 'none'; // ซ่อนการแจ้งเตือน
                }, 2000);
            }
        });
    </script>
</body>

</html>