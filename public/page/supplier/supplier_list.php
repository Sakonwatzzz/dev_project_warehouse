<?php
require_once __DIR__ . '../../../../config/database_server.php';


$query = "SELECT
    supplier.supplier_id, 
    supplier.supplier_name, 
    supplier.supplier_address, 
    supplier.supplier_phone, 
    supplier.supplier_detail
FROM supplier";
try {
    $supplierResults = $pdo->query($query);
} catch (Exception $e) {
    echo "error" . $e->getMessage();
    exit();
}

// จำนวนข้อมูลทั้งหมดในตาราง
$query = "SELECT COUNT(*) as total_count FROM supplier";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM supplier WHERE supplier_id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?page=supplier_list&delete_success");
    exit;
}

// การแบ่งหน้า
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = ceil($count / $records_per_page);

// นับจำนวนรายการทั้งหมด
try {
    $total_query = "SELECT COUNT(*) FROM supplier";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

try {
    $query = "SELECT supplier_id, supplier_name, supplier_address, supplier_phone, supplier_detail 
              FROM supplier 
              LIMIT ?, ?";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(2, $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $supplierResults = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

?>
<!DOCTYPE html>
<html lang="th">

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

        /* CSS สำหรับการแจ้งเตือน */
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
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
    </style>
    <title>หน้าผู้ส่งมอบ</title>
</head>

<body>

    <div class="font-[Tahoma] mx-[23rem] mt-11">

        <div class="title">
            <div class="count">
                <h2 class="text-2xl font-bold">ข้อมูลผู้ส่งมอบ</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาสมาชิก...">
            </div>
        </div>

        <div id="notification-edit" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขผู้ส่งมอบสำเร็จ!</div>
        </div>
        <div id="notification-delete" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">ลบสำเร็จ!</div>
        </div>

        <table class="data-table mt-5 mb-8 w-[100%]  border shadow-lg" id="dataTable">
            <thead>
                <tr>
                    <!-- <th>รหัส</th> -->
                    <th class="w-[150px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ชื่อ</th>
                    <th class="w-[150px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ที่อยู่</th>
                    <th class="w-[150px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">เบอร์โทรศัพท์</th>
                    <th class="w-[150px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">รายละเอียด</th>
                    <th class="w-[150px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($supplier_list = $supplierResults->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td class="text-start pl-4"><?php echo ($supplier_list['supplier_name']) ?></td>
                        <td class="text-start pl-4"><?php echo ($supplier_list['supplier_address']) ?></td>
                        <td class="text-start pl-4"><?php echo ($supplier_list['supplier_phone']) ?></td>
                        <td class="text-start pl-4"><?php echo ($supplier_list['supplier_detail']) ?></td>
                        <td>
                            <a href="?page=supplier_update&id=<?php echo ($supplier_list['supplier_id']) ?>" class="
                            bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 
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
                            active:shadow-inner
                            ">แก้ไข</a>
                            <a href="?page=supplier_list&id=<?php echo ($supplier_list['supplier_id']) ?>" onclick="return confirm('ลบหรือไม่ลบ สรุป?')" class=" bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 
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
            </tbody>
        </table>

        <!--โค้ดนี้ใช้ในการแบ่งหน้าในตาราง-->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=supplier_list&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=supplier_list&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=supplier_list&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
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