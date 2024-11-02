<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once __DIR__ . '../../../db/warehouse_db.php';
$resultspersonnel = fetchAll($pdo, 'personnel');

$query = "SELECT    
    personnel.personnel_id,
    personnel.personnel_name, 
    personnel.personnel_address, 
    personnel.personnel_phone, 
    personnel.personnel_username, 
    personnel.personnel_type_id, 
    personnel_type.personnel_type_name
FROM
    personnel
INNER JOIN
    personnel_type ON personnel.personnel_type_id = personnel_type.personnel_type_id";

$resultspersonnel = $conn->query($query);

// Query สำหรับประเภท
$queryTypes = "SELECT
    personnel_type.personnel_type_id, 
    personnel_type.personnel_type_name
FROM personnel_type";
$typeResults = $conn->query($queryTypes); // กำหนดตัวแปรใหม่สำหรับผลลัพธ์ query นี้

if (!$typeResults) {
    die("Query failed: " . $conn->error);
}
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
        <h2>ข้อมูลบุคลากร</h2>

        <div class="search">
            <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="ค้นหาบุคลากร...">
        </div>

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขบุคลากรสำเร็จ!</div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ชื่อ นามสกุล</th>
                    <th>ประเภทบุคคลากร</th>
                    <th>ที่อยู่</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>ชื่อผู้ใช้</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resultspersonnel->fetch_assoc()): ?>
                    <tr onclick="window.location='component/detailtest.php?type=personnel&id=<?= htmlspecialchars($row['personnel_id']) ?>'">
                        <td><?= htmlspecialchars($row['personnel_id']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_name']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_type_name']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_address']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_phone']) ?></td>
                        <td><?= htmlspecialchars($row['personnel_username']) ?></td>
                        <td>
                            <a href="functions_personnel/edit.php?id=<?= htmlspecialchars($row['personnel_id']) ?>">แก้ไข</a> |
                            <a href="functions_personnel/delete.php?id=<?= htmlspecialchars($row['personnel_id']) ?>"
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