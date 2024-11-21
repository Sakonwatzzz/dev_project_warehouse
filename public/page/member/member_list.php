<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';

$query = "SELECT    
    member.member_id,
    member.member_name, 
    member.member_address, 
    member.member_register_date, 
    member.member_type_id, 
    member_type.member_type_name
FROM
    member
INNER JOIN
    member_type ON member.member_type_id = member_type.member_type_id";

try {
    $resultsmember = $pdo->query($query);
} catch (Exception $e) {
    echo "error" . $e->getMessage();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $typename = $_POST['typename'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $register_date = $_POST['register_date'];

    $stmt = $pdo->prepare("
        UPDATE member 
        SET member_name = ?, member_type_id = ?, member_address = ?, member_phone = ?, member_register_date = ? 
        WHERE member_id = ?
    ");
    $stmt->execute([$name, $typename, $address, $phone, $register_date, $id]);

    header("Location: /?page=member_detail&tabel=member&id=$id");
    exit;
}

// ตรวจสอบว่ามีการส่งค่า id มาหรือไม่
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM member WHERE member_id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?page=member_list&delete_success");
    exit;
}

// Total member count
$query = "SELECT COUNT(*) as total_count FROM member";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// Pagination
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// Count total records
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
                member.member_type_id, 
                member_type.member_type_name 
              FROM member
              INNER JOIN member_type ON member.member_type_id = member_type.member_type_id
              LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $resultsmember = $stmt;
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
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <title>Member List</title>
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
            ;
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;

        }
    </style>
</head>

<body>
    <div class="personnel-list">
        <div class="title">
            <div class="count">
                <h2>ข้อมูลสมาชิก</h2>
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
            <div class="notification-body">แก้ไขบุคลากรสำเร็จ!</div>
        </div>
        <div id="notification-delete" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">ลบสำเร็จ!</div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
                <tr>
                    <th>ชื่อ นามสกุล</th>
                    <th>ประเภทสมาชิก</th>
                    <th>วันที่สมัคร</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($member_list = $resultsmember->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td onclick="window.location.href='?page=member_detail&tabel=member&id=<?php echo ($member_list['member_id']) ?>'"><?php echo ($member_list['member_name']) ?></td>
                        <td onclick="window.location.href='?page=member_group&tabel=member&id=<?php echo ($member_list['member_id']) ?>&type=<?php echo urlencode($member_list['member_type_name']) ?>'">
                            <?php echo ($member_list['member_type_name']) ?>
                        </td>
                        <td><?php echo ($member_list['member_register_date']) ?></td>
                        <td>
                            <a href="?page=member_update&id=<?php echo ($member_list['member_id']) ?>">แก้ไข</a>
                            <a href="?page=bar_member&id=<?php echo $member_list['member_id']; ?>" onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

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