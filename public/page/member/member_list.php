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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8"> <!-- กำหนด charset ของเอกสาร -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- กำหนดให้แสดงผลบนอุปกรณ์มือถือได้ดี -->

    <link rel="stylesheet" href="../../style/personnel_list.css"> <!-- โหลดไฟล์ CSS สำหรับการตกแต่ง -->
    <title>Document</title>
</head>

<body>
    <div class="personnel-list">
        <h2 class="title">ข้อมูลสมาชิก</h2> <!-- แสดงหัวข้อ "ข้อมูลสมาชิก" -->
        
        <div class="search"> 
            <!-- ฟอร์มค้นหาสมาชิก -->
            <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาสมาชิก...">
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
                    <th>รหัส</th>
                    <th>ชื่อ นามสกุล</th>
                    <th>ที่อยู่สมาชิก</th>
                    <th>เบอร์โทรศัพท์สมาชิก</th>
                    <th>วันที่</th>
                    <th>ประเภทสมาชิก</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($member_list = $resultmember->fetch(PDO::FETCH_ASSOC)): ?>
                    <!-- ใช้ while เพื่อลูปข้อมูลที่ได้จากฐานข้อมูล -->
                    <tr onclick="window.location.href='page/member/member_detail.php?tabel=member&id=<?php echo($member_list['member_id']) ?>'">
                        <!-- เมื่อคลิกแถวนี้จะพาไปยังหน้ารายละเอียดของสมาชิก -->
                        <td><?php echo($member_list['member_id']) ?></td> <!-- แสดงรหัสสมาชิก -->
                        <td><?php echo($member_list['member_name']) ?></td> <!-- แสดงชื่อสมาชิก -->
                        <td><?php echo($member_list['member_address']) ?></td> <!-- แสดงที่อยู่สมาชิก -->
                        <td><?php echo($member_list['member_phone']) ?></td> <!-- แสดงเบอร์โทรศัพท์สมาชิก -->
                        <td><?php echo($member_list['member_register_date']) ?></td> <!-- แสดงวันที่ลงทะเบียน -->
                        <td><?php echo($member_list['member_type_name']) ?></td> <!-- แสดงประเภทสมาชิก -->
                        <td>
                            <a href="page/member/member_update.php?id=<?php echo($member_list['member_id']) ?>">แก้ไข</a>
                            | <!-- ลิงก์ไปหน้าแก้ไขข้อมูลสมาชิก -->
                            <a href="?id=<?php echo($member_list['member_id']) ?>"
                               onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                            <!-- ลิงก์ไปหน้าลบข้อมูลสมาชิก และมีการยืนยันการลบ -->
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
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
            const searchInput =document.getElementById("searchInput");
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
