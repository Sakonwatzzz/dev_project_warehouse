<?php
require_once __DIR__ . '../../../../config/database_server.php'; // โหลดไฟล์การตั้งค่าการเชื่อมต่อฐานข้อมูล
ob_start(); // เริ่มต้นการเก็บบัฟเฟอร์ output (ใช้สำหรับจัดการการแสดงผล)

$query = "SELECT
    member.member_id, 
    member.member_name, 
    member.member_address, 
    member.member_phone, 
    member.member_register_date,
    member_type.member_type_id,
    member_type.member_type_name
FROM member
INNER JOIN member_type ON member.member_type_id = member_type.member_type_id";
// สร้างคำสั่ง SQL เพื่อดึงข้อมูลสมาชิกจากตาราง member และประเภทสมาชิกจากตาราง member_type โดยใช้การ JOIN กัน

try {
    // ลองรันคำสั่ง SQL และดึงข้อมูลมาเก็บในตัวแปร $resultmember
    $resultmember = $pdo->query($query);
} catch (PDOException $e) {
    // ถ้ามีข้อผิดพลาดในการรัน SQL จะแสดงข้อความผิดพลาด
    echo "Error: " . $e->getMessage();
}


// จำนวนข้อมูลทั้งหมดในตาราง
$query = "SELECT COUNT(*) as total_count FROM member";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM member WHERE member_id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?page=member_list"); 
}

// การแบ่งหน้า
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// นับจำนวนรายการทั้งหมด
try {
    $total_query = "SELECT COUNT(*) FROM member";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
try {
    $query = "SELECT
    member.member_id, 
    member.member_name, 
    member.member_address, 
    member.member_phone, 
    member.member_register_date,
    member_type.member_type_id,
    member_type.member_type_name
FROM member
INNER JOIN member_type ON member.member_type_id = member_type.member_type_id
LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $resultmember = $stmt;
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
    <div class="personnel-list">
        <div class="title">
            <div class="count">
                <h2 >ข้อมูลสมาชิก</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>

            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาสมาชิก...">
            </div>
        </div>


        <!-- ส่วนของการแจ้งเตือนเมื่อการแก้ไขเสร็จสมบูรณ์ -->
        <div id="notification" class="notification" style="display:none;">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขสมาชิกสำเร็จ!</div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
                <!-- ส่วนหัวของตาราง -->
                <tr>
                    <!-- <th>รหัส</th> -->
                    <th>ชื่อ นามสกุล</th>
                    <!-- <th>ที่อยู่สมาชิก</th> -->
                    <!-- <th>เบอร์โทรศัพท์สมาชิก</th> -->
                    <!-- <th>วันที่</th> -->
                    <th>ประเภทสมาชิก</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($member_list = $resultmember->fetch(PDO::FETCH_ASSOC)): ?>
                    <!-- ใช้ while เพื่อลูปข้อมูลที่ได้จากฐานข้อมูล -->
                    <tr onclick="window.location.href='page/member/member_detail.php?tabel=member&id=<?php echo ($member_list['member_id']) ?>'">
                        <!-- เมื่อคลิกแถวนี้จะพาไปยังหน้ารายละเอียดของสมาชิก -->
                        <!-- <td><?php echo ($member_list['member_id']) ?></td> แสดงรหัสสมาชิก -->
                        <td><?php echo ($member_list['member_name']) ?></td> <!-- แสดงชื่อสมาชิก -->
                        <!-- <td><?php echo ($member_list['member_address']) ?></td> แสดงที่อยู่สมาชิก -->
                        <!-- <td><?php echo ($member_list['member_phone']) ?></td> แสดงเบอร์โทรศัพท์สมาชิก -->
                        <!-- <td><?php echo ($member_list['member_register_date']) ?></td> แสดงวันที่ลงทะเบียน -->
                        <td><?php echo ($member_list['member_type_name']) ?></td> <!-- แสดงประเภทสมาชิก -->
                        <td>
                            <a href="page/member/member_update.php?id=<?php echo ($member_list['member_id']) ?>">แก้ไข</a>
                            | <!-- ลิงก์ไปหน้าแก้ไขข้อมูลสมาชิก -->
                            <a href="?page=member_list&id=<?php echo ($member_list['member_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                            <!-- ลิงก์ไปหน้าลบข้อมูลสมาชิก และมีการยืนยันการลบ -->
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!--โค้ดนี้ใช้ในการแบ่งหน้าในตาราง-->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=member_list&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=member_list&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=member_list&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ฟังก์ชันสำหรับค้นหาข้อมูลในตาราง
        function searchTable() {
            const input = document.getElementById("searchInput"); // หาตัว input ที่ใช้สำหรับค้นหา
            const filter = input.value.toLowerCase(); // เปลี่ยนค่าที่ผู้ใช้พิมพ์ให้เป็นตัวพิมพ์เล็ก
            const table = document.getElementById("dataTable"); // หาตารางข้อมูล
            const tr = table.getElementsByTagName("tr"); // หาทุกแถวในตาราง
            let foundAny = false; // ตัวแปรเพื่อตรวจสอบว่าพบข้อมูลหรือไม่

            for (let i = 1; i < tr.length; i++) { // เริ่มวนลูปจากแถวที่ 1 เพื่อข้าม header
                let found = false;
                const td = tr[i].getElementsByTagName("td"); // หาตัวแถว (td) ในแต่ละแถว
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText; // อ่านข้อความใน td
                        if (txtValue.toLowerCase().indexOf(filter) > -1) { // ถ้าคำค้นหาตรงกับข้อความใน td
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none"; // ถ้าพบคำค้นหาจะทำให้แถวนั้นแสดง
                if (found) {
                    foundAny = true; // ถ้าพบข้อมูลจะเปลี่ยนสถานะ
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