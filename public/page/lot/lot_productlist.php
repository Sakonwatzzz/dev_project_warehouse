<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

$query = "SELECT
lot_product.lot_product_id, 
lot_product.lot_id, 
lot_product.lot_product_qty, 
lot_product.lot_product_remain,  
lot_product.lot_product_capital, 
lot_product.lot_product_date, 
lot_product.lot_product_status,  
lot_product.product_id, 
product.product_name
FROM
    lot_product
    INNER JOIN lot ON lot_product.lot_id = lot.lot_id
    INNER JOIN product ON lot_product.product_id = product.product_id";
$result_lotproduct_list = $pdo->query($query);


// การลบข้อมูล
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM lot_product WHERE lot_product_id = ?");
    $stmt->execute([$id]);
    // หลังจากลบแล้วให้ redirect ไปหน้าหลัก และ exit เพื่อหยุดการทำงาน
    header("Location: index.php?page=lot_productlist");
    exit();
}

try {
    // การแบ่งหน้า
    $records_per_page = 5;
    $page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
    $offset = ($page - 1) * $records_per_page;

    // จำนวนข้อมูลทั้งหมดในตาราง
    $query = "SELECT COUNT(*) as total_count FROM lot_product";
    $stmt = $pdo->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];
    $total_pages = ceil($count / $records_per_page);
} catch (PDOException $e) {
    echo "Erro" . $e->getMessage();
}

try {
    $query = "SELECT
lot_product.lot_product_id, 
lot_product.lot_id, 
lot_product.lot_product_qty, 
lot_product.lot_product_remain,  
lot_product.lot_product_capital, 
lot_product.lot_product_date, 
lot_product.lot_product_status,  
lot_product.product_id, 
product.product_name 
FROM
    lot_product
    LEFT JOIN lot ON lot_product.lot_id = lot.lot_id
    LEFT JOIN product ON lot_product.product_id = product.product_id
LIMIT :offset, :records_per_page";

    $smtm = $pdo->prepare($query);
    $smtm->bindValue(':offset', $offset, PDO::PARAM_INT);
    $smtm->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $smtm->execute();
    $result_lotproduct_list = $smtm;
} catch (PDOException $e) {
    echo "" . $e->getMessage();
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    </style>

    <title>รายการรับสินค้า</title>
</head>

<body>
    <div class="font-[Tahoma] mx-[10rem] mt-11 rounded-md">
        <div class="title">
            <div class="count">
                <h2 class="text-2xl font-bold">ข้อมูลการรับสินค้า</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="ค้นหารายการรับสินค้า...">
            </div>
        </div>

        <table class="mt-4 w-[100%] bg-[#F0F0F0] border-2 border-[#A0A0A0] shadow-lg " id="dataTable">
            <thead>
                <tr>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        รหัสรายรับสินค้า</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        รหัสล็อตสินค้า</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        จำนวนสินค้าเข้า</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        จำนวนสินค้าคงเหลือ</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        ราคาต้นทุน</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        วันที่รับสินค้า</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        สถานะสินค้า</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        ชื่อสินค้า</th>
                    <th
                        class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                        การจัดการ</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php if ($result_lotproduct_list->rowCount() > 0): ?>
                    <?php while ($lot_product_list = $result_lotproduct_list->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr class=" bg-white hover:bg-[#aec5e366] cursor-pointer "
                            data-id="<?php echo $lot_product_list['lot_id']; ?>">
                            <td
                                onclick="window.location.href='?page=lot_productdetail&tabel=lot_product&id=<?php echo ($lot_product_list['lot_product_id']) ?>'">
                                <?php echo ($lot_product_list['lot_product_id']); ?>
                            </td>
                            <td><?php echo ($lot_product_list['lot_id']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_qty']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_remain']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_capital']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_date']); ?></td>
                            <td><?php echo ($lot_product_list['lot_product_status']); ?></td>
                            <td><?php echo ($lot_product_list['product_name']); ?></td>
                            <td>
                                <a href="?page=lot_productupdate&id=<?php echo ($lot_product_list['lot_product_id']); ?>" class="  bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 
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
                            active:shadow-inner ">แก้ไข</a>
                                <a href="?page=lot_productlist&id=<?php echo ($lot_product_list['lot_product_id']); ?>"
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
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">ไม่พบข้อมูล</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="box ">
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
                active:shadow-inner" href="/?page=lot_productinsert" value="เพิ่ม">เพิ่มการรับสินค้า</a class="submit-button">
            <!-- -->
        </div>
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=lot_product_list&page_num=1">First</a>
                    <a href="?page=lot_product_list&page_num=<?php echo ($page - 1); ?>">Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=lot_product_list&page_num=<?php echo $i; ?>"
                        class="<?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=lot_product_list&page_num=<?php echo ($page + 1); ?>">Next</a>
                    <a href="?page=lot_product_list&page_num=<?php echo $total_pages; ?>">Last</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput").value.toLowerCase(); // รับค่าการค้นหา
            const table = document.getElementById("dataTable"); // ระบุตารางที่ต้องการค้นหา
            const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr"); // ทุกแถวใน tbody
            let foundAny = false; // ตัวบ่งชี้ว่าพบผลการค้นหาหรือไม่

            for (const row of rows) {
                const cells = row.getElementsByTagName("td"); // แต่ละเซลล์ในแถว
                let match = false;

                for (const cell of cells) {
                    // ตรวจสอบว่าค่าในเซลล์ตรงกับข้อความค้นหาหรือไม่
                    if (cell.textContent.toLowerCase().includes(input)) {
                        match = true;
                        break; // หากพบแล้วออกจากลูป
                    }
                }

                // แสดงหรือซ่อนแถวตามผลลัพธ์การค้นหา
                row.style.display = match ? "" : "none";

                if (match) {
                    foundAny = true; // ถ้ามีแถวตรงกับการค้นหา
                }
            }

            // จัดการข้อความ "ไม่พบข้อมูล"
            let noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    // สร้างข้อความใหม่หากยังไม่มี
                    noDataMessage = document.createElement("p");
                    noDataMessage.id = "noDataMessage";
                    noDataMessage.textContent = "ไม่พบข้อมูล";
                    noDataMessage.className = "text-red-600 font-bold text-center mt-4";
                    table.parentNode.appendChild(noDataMessage);
                }
            } else if (noDataMessage) {
                // ลบข้อความ "ไม่พบข้อมูล" หากมีผลการค้นหา
                noDataMessage.remove();
            }
        }
    </script>

    <script>
        // การแสดงผลการแจ้งเตือนเมื่อการแก้ไขเสร็จสมบูรณ์
        document.addEventListener("DOMContentLoaded", function () {
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