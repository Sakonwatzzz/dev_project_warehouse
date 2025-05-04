
<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';

// Fetch member types for dropdown
try {
    $query = "SELECT member_type_id, member_type_name FROM member_type";
    $stmt = $pdo->query($query);
    $member_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $member_types = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}

// Handle POST requests for updating member
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_member_name'])) {
        $id = $_POST['id'];
        $newName = $_POST['update_member_name'];
        $newTypeId = $_POST['update_member_type_id'];
        try {
            $stmt = $pdo->prepare("UPDATE member SET member_name = ?, member_type_id = ? WHERE member_id = ?");
            $stmt->execute([$newName, $newTypeId, $id]);
            header('Location: index.php?page=member_list&edit_success=1');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle DELETE request
if (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM member WHERE member_id = ?");
        $stmt->execute([$id]);
        header("Location: index.php?page=member_list&delete_success=1");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}

// Total member count
$query = "SELECT COUNT(*) as total_count FROM member";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// Pagination
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $total_records = $pdo->query("SELECT COUNT(*) FROM member")->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}

// Fetch member data
try {
    $query = "
        SELECT 
            m.member_id,
            m.member_name,
            m.member_address,
            m.member_phone,
            m.member_register_date,
            m.member_type_id,
            mt.member_type_name
        FROM member m
        INNER JOIN member_type mt ON m.member_type_id = mt.member_type_id
        LIMIT :offset, :records_per_page
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $members = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/dist/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Member List</title>
    <style>
        body {
            font-family: 'Tahoma', sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .table-container {
            overflow-x: auto;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid #e5e7eb;
        }

        th {
            background: #f9fafb;
            font-weight: 600;
        }

        tr:hover {
            background: #f3f4f6;
        }

        .search-bar {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
        }

        .pagination a {
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            border-radius: 0.375rem;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .pagination a.active {
            background: #1f2937;
            color: white;
        }

        .notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border: 2px solid #d1d5db;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 400px;
            z-index: 50;
        }

        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 0 0.5rem;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                display: block;
            }

            thead {
                display: none;
            }

            tbody,
            tr {
                display: block;
            }

            tr {
                margin-bottom: 1rem;
                border: 1px solid #e5e7eb;
                border-radius: 0.375rem;
                background: white;
            }

            td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem;
                border: none;
                border-bottom: 1px solid #e5e7eb;
            }

            td:last-child {
                border-bottom: none;
            }

            td:before {
                content: attr(data-label);
                font-weight: 600;
                color: #374151;
                flex: 1;
            }

            td .edit-input,
            td .edit-select {
                flex: 2;
            }

            .action-buttons {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .pagination a {
                padding: 0.5rem;
                font-size: 0.875rem;
            }

            .search-bar {
                font-size: 0.875rem;
            }
        }

        @media (min-width: 769px) {
            .action-buttons {
                display: flex;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Table -->
        <div class="table-container mb-8">
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-3 border-b border-[#8f959b]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-list mr-2"></i>รายการสมาชิก
                        </h2>
                        <span class="px-3 py-1 bg-white font-bold border border-gray-500 rounded-md text-sm md:text-base"><?php echo $count; ?> รายการ</span>
                    </div>
                    <div class="w-full md:w-1/2">
                        <input type="text" id="searchInput" onkeyup="searchTable()"
                            placeholder="ค้นหา..."
                            class="search-bar focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
            <div class="p-4">
                <table id="dataTable">
                    <thead>
                        <tr>
                            <th>รหัสสมาชิก</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ประเภทสมาชิก</th>
                            <th>วันที่สมัคร</th>
                            <th class="text-center">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $member): ?>
                                <tr data-id="<?php echo htmlspecialchars($member['member_id']); ?>">
                                    <td data-label="รหัสสมาชิก" class="cursor-pointer hover:underline"><?php echo htmlspecialchars($member['member_id']); ?></td>
                                    <td data-label="ชื่อ-นามสกุล" class="member-name cursor-pointer hover:underline" onclick="window.location.href='?page=member_detail&tabel=member&id=<?php echo htmlspecialchars($member['member_id']); ?>'"><?php echo htmlspecialchars($member['member_name']); ?></td>
                                    <td data-label="ประเภทสมาชิก" class="member-type" ><?php echo htmlspecialchars($member['member_type_name']); ?></td>
                                    <td data-label="วันที่สมัคร"><?php echo htmlspecialchars($member['member_register_date']); ?></td>
                                    <td data-label="การจัดการ" class="text-center">
                                        <div class="action-buttons flex justify-center">
                                            <button onclick="enableEdit(this)"
                                                class="edit-btn bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-sm">
                                                <i class="fas fa-edit mr-1"></i>แก้ไข
                                            </button>
                                            <a href="?page=member_list&id=<?php echo htmlspecialchars($member['member_id']); ?>&action=delete"
                                                onclick="return confirm('ยืนยันการลบข้อมูล?')"
                                                class="delete-btn bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition flex items-center text-sm">
                                                <i class="fas fa-trash-alt mr-1"></i>ลบ
                                            </a>
                                            <button class="save-btn hidden bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 transition items-center text-sm"
                                                onclick="saveEdit(this)">
                                                <i class="fas fa-save mr-1"></i>บันทึก
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-600">ไม่มีข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination mt-5 flex justify-center items-center gap-2 flex-wrap">
            <?php if ($total_pages > 1): ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=member_list&page_num=<?= $page - 1 ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=member_list&page_num=1"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=member_list&page_num=<?= $i ?>"
                        class="rounded-md transition-colors <?php echo $i == $page ? 'active' : 'bg-[#aec5e366] text-black hover:bg-gray-300'; ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=member_list&page_num=<?= $total_pages ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>

                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=member_list&page_num=<?= $page + 1 ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="notification hidden">
        <div id="notification-header" class="p-2 rounded-t-md font-bold"></div>
        <div id="notification-body" class="p-4 text-center text-base"></div>
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            const thead = table.querySelector("thead");
            let foundAny = false;

            thead.style.display = window.innerWidth > 768 ? "" : "none";
            tr[0].style.display = window.innerWidth > 768 ? "" : "none";

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName("td");
                let rowMatchesSearch = false;

                for (let j = 0; j < td.length; j++) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        rowMatchesSearch = true;
                        break;
                    }
                }

                tr[i].style.display = rowMatchesSearch ? "" : "none";
                if (rowMatchesSearch) foundAny = true;
            }

            let noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    noDataMessage = document.createElement("p");
                    noDataMessage.id = "noDataMessage";
                    noDataMessage.textContent = "ไม่พบข้อมูล";
                    noDataMessage.className = "text-red-600 font-bold text-center mt-3";
                    table.parentNode.insertBefore(noDataMessage, table.nextSibling);
                }
            } else if (noDataMessage) {
                noDataMessage.remove();
            }
        }

        function enableEdit(button) {
            const row = button.closest('tr');
            const memberNameCell = row.querySelector('.member-name');
            const memberTypeCell = row.querySelector('.member-type');
            const memberId = row.getAttribute('data-id');

            const originalName = memberNameCell.textContent.trim();
            const originalType = memberTypeCell.textContent.trim();

            memberNameCell.innerHTML = `<input type="text" class="edit-input p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="${originalName}" />`;

            let dropdown = `<select class="edit-select p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">`;
            <?php foreach ($member_types as $type): ?>
                dropdown += `<option value="<?php echo $type['member_type_id']; ?>" ${originalType === "<?php echo $type['member_type_name']; ?>" ? 'selected' : ''}><?php echo htmlspecialchars($type['member_type_name']); ?></option>`;
            <?php endforeach; ?>
            dropdown += `</select>`;
            memberTypeCell.innerHTML = dropdown;

            button.classList.add('hidden');
            row.querySelector('.delete-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
        }

        function saveEdit(button) {
            const row = button.closest('tr');
            const memberId = row.getAttribute('data-id');
            const newName = row.querySelector('.edit-input').value.trim();
            const newTypeId = row.querySelector('.edit-select').value;
            const memberNameCell = row.querySelector('.member-name');
            const memberTypeCell = row.querySelector('.member-type');

            if (!newName) {
                alert('กรุณากรอกชื่อสมาชิก');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${memberId}&update_member_name=${encodeURIComponent(newName)}&update_member_type_id=${newTypeId}`
                })
                .then(response => {
                    if (response.ok) {
                        memberNameCell.textContent = newName;
                        const selectedType = row.querySelector('.edit-select option:checked').textContent;
                        memberTypeCell.textContent = selectedType;
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
            document.getElementById('searchInput').focus();
            if (urlParams.has('edit_success')) {
                showNotification('แก้ไขข้อมูลสำเร็จ', 'bg-cyan-200');
            } else if (urlParams.has('delete_success')) {
                showNotification('ลบข้อมูลสำเร็จ', 'bg-cyan-200');
            }
        });
    </script>
</body>

</html>