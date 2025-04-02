<?php
require_once __DIR__ . '../../../../config/database_server.php';

// รับค่าจาก URL เพื่อหา type ของตำแหน่ง
$type = isset($_GET['type']) ? $_GET['type'] : '';

// คำสั่ง SQL เพื่อดึงข้อมูลสมาชิกตามประเภท
$query = "
    SELECT    
        member.member_id,
        member.member_name, 
        member.member_phone, 
        member.member_type_id, 
        member_type.member_type_name
    FROM
        member
    INNER JOIN
        member_type ON member.member_type_id = member_type.member_type_id
    WHERE
        member_type.member_type_name = :type
    ORDER BY
        member.member_name;
";

try {
    // ใช้ prepared statement เพื่อป้องกัน SQL Injection
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->execute();

    // ดึงข้อมูลสมาชิก
    $memberData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
    exit();
}

// คำสั่ง SQL เพื่อคำนวณจำนวนข้อมูลทั้งหมดในประเภทที่กำหนด
$query = "
    SELECT COUNT(*) as total_count
    FROM member
    INNER JOIN member_type ON member.member_type_id = member_type.member_type_id
    WHERE member_type.member_type_name = :type
";

try {
    // ใช้ prepared statement เพื่อป้องกัน SQL Injection
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->execute();

    // ดึงจำนวนข้อมูลทั้งหมด
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <link href="../../output.css" rel="stylesheet">
    <title>กลุ่มสมาชิก</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

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

        .detail-action {
            margin-top: 25px;
            width: auto;
            height: 40px;
        }

        .detail-action a {
            border-radius: 6px;
            width: 50px;
            height: 40px;
            padding: 10px;
            background-color: #333;
            text-decoration: none;
            color: #fff;
        }

        .detail-action a:hover {
            background-color: #000000;
        }
    </style>
</head>

<body>
    <div class="font-[Tahoma] mx-[23rem] mt-11">
        <div class="title">
            <div class="count">
                <h2 class="text-2xl font-bold">รายชื่อสมาชิกตามประเภท: <?php echo htmlspecialchars($type); ?></h2>
                <div class="w-[120px] p-2 bg-blue-200 font-bold text-end border border-blue-500 rounded-md ">
                    <?php echo $count; ?>
                </div>
            </div>

            <div class="mt-4 md:mt-0">
                <input type="text" id="searchInput" onkeyup="searchTable()"
                    placeholder="ค้นหาสมาชิก..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <?php if ($memberData): ?>
            <table class="data-table mt-5 mb-8 w-[100%]  border shadow-lg" id="dataTable">
                <thead>
                    <tr>
                        <th class="w-[170px]  bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow text-center">ชื่อ-สกุล</th>
                        <th class="w-[170px]  bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow text-center">ประเภท</th>
                        <th class="w-[170px]  bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow text-center">เบอร์โทร</th>
                        <th class="w-[170px]  bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow text-center">จัดการ</th>
                    </tr>
                </thead>

                <!--ลูปข้อมูลตาม type ของประเภท-->
                <tbody>
                    <?php foreach ($memberData as $member): ?>
                        <tr class="bg-white hover:bg-gray-100 cursor-pointer">
                            <td class="py-2 px-4 border border-gray-300 text-center"><?php echo htmlspecialchars($member['member_name']); ?></td>
                            <td class="py-2 px-4 border border-gray-300 text-center"><?php echo htmlspecialchars($member['member_type_name']); ?></td>
                            <td class="py-2 px-4 border border-gray-300 text-center"><?php echo htmlspecialchars($member['member_phone']); ?></td>


                            <td class="py-3 px-4 text-center space-x-2 border border-gray-300">
                                <a href="?page=member_update&id=<?php echo $member['member_id']; ?>"
                                    class="inline-flex items-center px-3 py-1 bg-yellow-500 text-white text-sm font-medium rounded-md hover:bg-yellow-600 transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    แก้ไข
                                </a>
                                <a href="?page=member_list&id=<?php echo htmlspecialchars($member_list['member_id']); ?>&action=delete"
                                    onclick="return confirm('ลบหรือไม่ลบ สรุป?')"
                                    class="inline-flex items-center px-3 py-1 bg-red-500 text-white text-sm font-medium rounded-md hover:bg-red-600 transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a2 2 0 00-2 2h8a2 2 0 00-2-2m-4 0V3m-3 4h10"></path>
                                    </svg>
                                    ลบ
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>ไม่มีสมาชิกในประเภทนี้</p>
        <?php endif; ?>

        <div class="flex space-x-2">
            <!--ย้อนกลับไปหน้ารายการ-->
            <div class="detail-action">
                <a href="/?page=member_list">หน้ารายการ</a>
            </div>
            <!--ย้อนกลับไปหน้าเประเภท-->
            <div class="detail-action">
                <a href="/?page=member_type">หน้าประเภท</a>
            </div>
        </div>
    </div>

    <script>
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
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById("searchInput");
            searchInput.focus();
            if (urlParams.has('edit_success')) {
                const notificationEdit = document.getElementById('notification-edit');
                notificationEdit.style.display = 'block';
                setTimeout(() => {
                    notificationEdit.style.display = 'none';
                }, 2000);
            } else if (urlParams.has('delete_success')) {
                const notificationDelete = document.getElementById('notification-delete');
                notificationDelete.style.display = 'block';
                setTimeout(() => {
                    notificationDelete.style.display = 'none';
                }, 2000);
            }
        });
    </script>
</body>

</html>