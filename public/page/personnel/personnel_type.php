<?php require_once __DIR__ . '../../../../config/database_server.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typename = $_POST['typename'];
    $stmt = $pdo->prepare('INSERT INTO personnel_type (personnel_type_name)
    VALUES (?)');
    $stmt->execute([$typename]);

    header('Location: /index.php?page=personnel_type');
    exit;
}

// Fetch personnel types for dropdown
$query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$stmt = $pdo->prepare($query);
$stmt->execute();

$personnelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM personnel_type WHERE personnel_type_id = ?");
    $stmt->execute([$id]);

    header("Location: /index.php?page=personnel_type");
    exit;
}

// จำนวนข้อมูลทั้งหมดในตาราง
$query = "SELECT COUNT(*) as total_count FROM personnel_type";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM personnel_type WHERE personnel_type_id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?page=personnel_type_list");
    exit;
}

// การแบ่งหน้า
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// นับจำนวนรายการทั้งหมด
try {
    $total_query = "SELECT COUNT(*) FROM personnel_type";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

try {
    $query = "SELECT * 
              FROM personnel_type
              LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $personnelTypes = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>


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
    <title>Document</title>
</head>

<body>

    <div class="personnel-list">
        <table class="data-table" id="dataTable">

            <div class="title">
                <div class="count">
                    <h2 style=" font-weight: bold;">ประเภทบุคลากร</h2>
                    <div class="listcount">
                        <?php echo $count; ?>
                    </div>
                </div>
                <div class="search">
                    <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1"
                        placeholder="ค้นหา...">
                </div>
            </div>
            <tr>
                <form class="form-container" action="" method="POST">
                    <!-- เปลี่ยน action ไปที่ไฟล์ปัจจุบัน -->
                    <td></td>
                    <td>
                        <input type="text" id="typename" name="typename" placeholder="ชื่อประเภทบุคลากร"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />

                    </td>
                    <td>
                        <button type="submit"
                            class="px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                            เพิ่ม
                        </button>

                    </td>
                </form>
            </tr>


            <thead>
                <tr>
                    <th>รหัสประเภทบุคลากร</th>
                    <th>ชื่อประเภทบุคลากร</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($personnelTypes as $personnel_type_list): ?>
                    <tr>
                        <td><?php echo ($personnel_type_list['personnel_type_id']) ?></td>
                        <td><?php echo ($personnel_type_list['personnel_type_name']) ?></td>
                        <td>
                            <a
                                href="page/personnel/personnel_type_update.php?id=<?php echo ($personnel_type_list['personnel_type_id']) ?>">แก้ไข</a>
                            |
                            <a href="?id=<?php echo ($personnel_type_list['personnel_type_id']) ?>"
                                onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!--โค้ดนี้ใช้ในการแบ่งหน้าในตาราง-->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=personnel_type&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=personnel_type&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=personnel_type&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
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