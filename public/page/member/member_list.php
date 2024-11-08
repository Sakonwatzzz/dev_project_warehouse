<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once './db/warehouse_db.php';
$resultmember = fetchAll($pdo, 'member');

// SQL query to get all members with their types
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

$resultmember = $conn->query($query);

if (!$resultmember) {
    die("Query failed: " . $conn->error);
}

// SQL query to get member types for the dropdown
$queryTypes = "SELECT
    member_type.member_type_id, 
    member_type.member_type_name
FROM member_type";
$results = $conn->query($queryTypes);

if (!$results) {
    die("Query failed: " . $conn->error); // แสดงข้อความหากเกิดข้อผิดพลาดในการ query
} elseif (is_array($results)) {
    die("Unexpected array result. Verify database connection and query syntax.");
}

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM member WHERE member_id = ?");
$stmt->execute([$id]);

// header("Location: /index.php?page=member_list");    
// exit;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../../style/personnel_list.css">
    <title>Document</title>
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
</head>

<body>
    <div class="personnel-list">
        <h2 class="title">ข้อมูลสมาชิก</h2>
        <div class="search">
            <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="ค้นหาสมาชิก...">
        </div>

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขสมาชิกสำเร็จ!</div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
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
                <?php while ($member_list = $resultmember->fetch_assoc()): ?>
                    <!-- ส่งค่าพารามิเตอร์ type และ id ไปยัง detail.php ในการตั้งค่า onclick -->
                    <tr onclick="window.location.href='component/detailtest.php?type=member&id=<?php echo($member_list['member_id']) ?>'">
                        <td><?php echo($member_list['member_id']) ?></td>
                        <td><?php echo($member_list['member_name']) ?></td>
                        <td><?php echo($member_list['member_address']) ?></td>
                        <td><?php echo($member_list['member_phone']) ?></td>
                        <td><?php echo($member_list['member_register_date']) ?></td>
                        <td><?php echo($member_list['member_type_name']) ?></td> <!-- Display member type name -->
                        <td>
                            <a href="member_edit.php?id=<?php echo($member_list['member_id']) ?>">แก้ไข</a>
                            |
                            <a href="?id=<?php echo($member_list['member_id']) ?>"
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