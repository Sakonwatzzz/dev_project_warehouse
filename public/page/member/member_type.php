<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['member_type']) && !empty(trim($_POST['member_type']))) {
        $member_type = trim($_POST['member_type']);
        try {
            $stmt = $pdo->prepare('INSERT INTO member_type (member_type_name) VALUES (?)');
            $stmt->execute([$member_type]);
            header('Location: /index.php?page=member_type&add_success=1');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_member_type']) && isset($_POST['id'])) {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        $new_member_type = trim($_POST['update_member_type']);
        if ($id && !empty($new_member_type)) {
            try {
                $stmt = $pdo->prepare("UPDATE member_type SET member_type_name = ? WHERE member_type_id = ?");
                $stmt->execute([$new_member_type, $id]);
                header('Location: /index.php?page=member_type&edit_success=1');
                exit;
            } catch (PDOException $e) {
                echo 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Check if there is an 'id' in the GET request to delete a member type
if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM member_type WHERE member_type_id = ?");
            $stmt->execute([$id]);
            header("Location: ?page=member_type&delete_success=1");
            exit;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

// Count all records in the member_type table
$query = "SELECT COUNT(*) as total_count FROM member_type";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// Pagination logic
$records_per_page = 6;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

// Count the total records
try {
    $total_query = "SELECT COUNT(*) FROM member_type";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Fetch data
try {
    $query = "SELECT 
        mb.member_type_id,
        mb.member_type_name,
        COALESCE(COUNT(m.member_id), 0) as member_count
        FROM member_type mb
        LEFT JOIN member m ON mb.member_type_id = m.member_type_id
        GROUP BY mb.member_type_id, mb.member_type_name 
        LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $member_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $member_types = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
    </style>
    <title>Member Type List</title>
</head>

<body>
    <div class="font-[Tahoma] mx-[30rem] mt-11">
        <div class="flex">
            <div class="flex justify-center items-center">
                <h2 class="w-[190px] text-2xl font-bold">ประเภทสมาชิก</h2>
                <div class="w-[120px] p-2 bg-blue-200 font-bold text-end border border-blue-500 rounded-md">
                    <?php echo $count; ?>
                </div>
            </div>

            <div class="w-full flex justify-end">
                <input type="text" id="searchInput" onkeyup="searchTable()"
                    placeholder="ค้นหา..."
                    class="w-2/3 px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <table class="w-full mt-5 mb-8 border-2 border-gray-400 shadow-lg" id="dataTable">
            <tr>
                <form action="" method="POST">
                    <td class="bg-white p-2">
                        <input type="text" id="member_type" name="member_type" placeholder="ชื่อประเภทสมาชิก" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </td>
                    <td class="bg-white border border-gray-300 text-center text-lg italic text-gray-600">อัตโนมัติ</td>
                    <td class="bg-white text-center border border-gray-300">
                        <button type="submit"
                            class="w-32 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-400">
                            เพิ่ม
                        </button>
                    </td>
                </form>
            </tr>
            <thead>
                <tr class="">
                    <th class="w-[40%]  bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">ประเภทสมาชิก</th>
                    <th class="w-[40%]  bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">จำนวน</th>
                    <th class="w-[20%]  bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">การจัดการ</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach ($member_types as $membertype): ?>
                    <tr data-id="<?php echo $membertype['member_type_id']; ?>" class="bg-white hover:bg-gray-100 cursor-pointer">
                        <td class="type-name py-2 px-4 border border-gray-300"><?php echo htmlspecialchars($membertype['member_type_name']); ?></td>
                        <td class="py-2 px-4 text-orange-600 border border-gray-300 text-center" onclick="window.location.href='?page=member_group&type=<?php echo urlencode($membertype['member_type_name']); ?>'">
                            <?php echo number_format($membertype['member_count']); ?> รายการ
                        </td>
                        <td class="py-3 px-4 text-center space-x-2 border border-gray-300">
                            <button onclick="enableEdit(this)"
                                class="edit-btn inline-flex items-center px-3 py-1 bg-yellow-500 text-white text-sm font-medium rounded-md hover:bg-yellow-600 transition">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                แก้ไข
                            </button>
                            <a href="?page=member_type&id=<?php echo $membertype['member_type_id']; ?>"
                                onclick="return confirm('ยืนยันการลบข้อมูล?')"
                                class="delete-btn inline-flex items-center px-3 py-1 bg-red-500 text-white text-sm font-medium rounded-md hover:bg-red-600 transition">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a2 2 0 00-2 2h8a2 2 0 00-2-2m-4 0V3m-3 4h10"></path>
                                </svg>
                                ลบ
                            </a>
                            <button class="save-btn hidden items-center px-3 py-1 bg-green-500 text-white text-sm font-medium rounded-md hover:bg-green-600 transition"
                                onclick="saveEdit(this)">
                                <div class="flex">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    บันทึก
                                </div>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=member_type&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=member_type&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=member_type&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="hidden fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white border-2 border-gray-400 rounded-md shadow-lg w-96 h-32 z-50">
        <div id="notification-header" class="p-2 rounded-t-md font-bold"></div>
        <div id="notification-body" class="p-5 text-center text-lg"></div>
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
            for (let i = 2; i < tr.length; i++) {
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

        function enableEdit(button) {
            const row = button.closest('tr');
            const typeNameCell = row.querySelector('.type-name');
            const originalName = typeNameCell.textContent.trim();

            // Replace cell content with input field
            typeNameCell.innerHTML = `<input type="text" class="edit-input w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="${originalName}" />`;

            // Hide edit and delete buttons, show save button
            button.classList.add('hidden');
            row.querySelector('.delete-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
        }

        function saveEdit(button) {
            const row = button.closest('tr');
            const typeId = row.getAttribute('data-id');
            const newName = row.querySelector('.edit-input').value.trim();
            const typeNameCell = row.querySelector('.type-name');

            if (!newName) {
                alert('กรุณากรอกชื่อประเภทสมาชิก');
                return;
            }

            // Send update request
            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${typeId}&update_member_type=${encodeURIComponent(newName)}`
                })
                .then(response => {
                    if (response.ok) {
                        // Update UI
                        typeNameCell.textContent = newName;
                        const editBtn = row.querySelector('.edit-btn');
                        const deleteBtn = row.querySelector('.delete-btn');
                        editBtn.classList.remove('hidden');
                        deleteBtn.classList.remove('hidden');
                        button.classList.add('hidden');
                        showNotification('แก้ไขสำเร็จ', 'bg-cyan-200');
                    } else {
                        throw new Error('Failed to save');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('เกิดข้อผิดพลาดในการบันทึก', 'bg-red-200');
                });
        }

        function showNotification(message, headerClass) {
            const notification = document.getElementById('notification');
            const header = document.getElementById('notification-header');
            const body = document.getElementById('notification-body');

            header.className = `p-2 rounded-t-md font-bold ${headerClass}`;
            header.textContent = 'แจ้งเตือน';
            body.textContent = message;
            notification.classList.remove('hidden');

            setTimeout(() => {
                notification.classList.add('hidden');
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('add_success')) {
                showNotification('เพิ่มข้อมูลสำเร็จ', 'bg-cyan-200');
            } else if (urlParams.has('edit_success')) {
                showNotification('แก้ไขข้อมูลสำเร็จ', 'bg-cyan-200');
            } else if (urlParams.has('delete_success')) {
                showNotification('ลบข้อมูลสำเร็จ', 'bg-cyan-200');
            }
            document.getElementById('searchInput').focus();
        });
    </script>
</body>

</html>