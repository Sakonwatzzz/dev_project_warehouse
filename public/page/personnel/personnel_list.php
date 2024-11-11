<?php
require_once __DIR__ . '../../../../config/database_server.php';

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

try {
    $resultspersonnel = $pdo->query($query);
} catch (Exception $e) {
    echo "error" . $e->getMessage();
    exit();
}

// ตรวจสอบว่ามีการส่งค่า id มาหรือไม่
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM personnel WHERE personnel_id = ?");
    $stmt->execute([$id]);
    header("Location: http://localhost:8000/home.php?page=personnel_list");
    exit;
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
            <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาบุคลากร...">
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
                <?php while ($personnel_list = $resultspersonnel->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr
                        onclick="window.location.href='page/personnel/personnel_detail.php?tabel=personnel&id=<?php echo ($personnel_list['personnel_id']) ?>'">
                        <td><?php echo ($personnel_list['personnel_id']) ?></td>
                        <td><?php echo ($personnel_list['personnel_name']) ?></td>
                        <td><?php echo ($personnel_list['personnel_type_name']) ?></td>
                        <td><?php echo ($personnel_list['personnel_address']) ?></td>
                        <td><?php echo ($personnel_list['personnel_phone']) ?></td>
                        <td><?php echo ($personnel_list['personnel_username']) ?></td>
                        <td>
                            <a href="page/personnel/personnel_update.php?id=<?php echo $personnel_list['personnel_id']; ?>">แก้ไข</a>
                            |
                            <a href="?id=<?php echo $personnel_list['personnel_id']; ?>" onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
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
            let foundAny = false;

            for (let i = 1; i < tr.length; i++) {
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
                tr[i].style.display = found ? "" : "none";
                if (found) {
                    foundAny = true;
                }
            }

            const noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    const message = document.createElement("p");
                    message.id = "noDataMessage";
                    message.textContent = "ไม่พบข้อมูล";
                    message.style.color = "red";
                    message.style.fontWeight = "bold";
                    message.style.marginTop = "10px";
                    table.parentNode.insertBefore(message, table);
                }
            } else {
                if (noDataMessage) {
                    noDataMessage.remove();
                }
            }
        }
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput =document.getElementById("searchInput");
            searchInput.focus();
            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.style.display = 'block';

                setTimeout(() => {
                    notification.style.display = 'none';
                }, 2000);
            }
        });
    </script>
</body>

</html>