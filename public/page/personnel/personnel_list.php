<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Add search parameter handling
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die('Database error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Handle POST requests for updating personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_personnel_name'])) {
        $id = $_POST['id'];
        $newName = $_POST['update_personnel_name'];
        $newTypeId = $_POST['update_personnel_type_id'];
        try {
            $stmt = $pdo->prepare("UPDATE personnel SET personnel_name = ?, personnel_type_id = ? WHERE personnel_id = ?");
            $stmt->execute([$newName, $newTypeId, $id]);
            header('Location: /index.php?page=personnel_list&edit_success=1' . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle DELETE request
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM personnel WHERE personnel_id = ?");
        $stmt->execute([$id]);
        header("Location: /index.php?page=personnel_list&delete_success=1" . (!empty($search) ? '&search=' . urlencode($search) : ''));
        exit;
    } catch (PDOException $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}

// Count total records in the personnel table (with search)
try {
    $count_query = "SELECT COUNT(*) as total_count FROM personnel";
    $count_params = [];
    if (!empty($search)) {
        $count_query .= " WHERE personnel_id LIKE :search OR personnel_name LIKE :search OR personnel_username LIKE :search";
        $count_params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
} catch (PDOException $e) {
    $count = 0;
    echo "Count error: " . htmlspecialchars($e->getMessage());
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $total_query = "SELECT COUNT(*) FROM personnel";
    $total_params = [];
    if (!empty($search)) {
        $total_query .= " WHERE personnel_id LIKE :search OR personnel_name LIKE :search OR personnel_username LIKE :search";
        $total_params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($total_query);
    $stmt->execute($total_params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}

// Fetch personnel data (with search)
try {
    $query = "
        SELECT 
            p.personnel_id,
            p.personnel_name,
            p.personnel_username,
            p.personnel_type_id,
            pt.personnel_type_name
        FROM personnel p
        INNER JOIN personnel_type pt ON p.personnel_type_id = pt.personnel_type_id";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE p.personnel_id LIKE :search OR p.personnel_name LIKE :search OR p.personnel_username LIKE :search";
        $params[':search'] = "%$search%";
    }
    $query .= " LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $personnel = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}

// Fetch personnel types for dropdown
try {
    $query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
    $personnel_types = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $personnel_types = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/dist/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Personnel List</title>
    <style>
        /* Custom styles for responsiveness */
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

        /* Mobile and Tablet Styles */
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

        /* Larger screens */
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
                            <i class="fas fa-list mr-2"></i>รายการบุคลากร
                        </h2>
                        <span class="px-3 py-1 bg-white font-bold border border-gray-500 rounded-md text-sm md:text-base"><?php echo $count; ?> รายการ</span>
                    </div>
                    <div class="w-full md:w-1/2">
                        <form action="" method="GET" class="w-full" id="searchForm">
                            <input type="hidden" name="page" value="personnel_list">
                            <div class="relative">
                                <input type="text" name="search" id="searchInput"
                                    placeholder="ค้นหา..."
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    class="search-bar focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    oninput="checkEmptySearch(this)">
                                <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="p-4">
                <table id="dataTable">
                    <thead>
                        <tr>
                            <th>รหัสบุคลากร</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ประเภทบุคลากร</th>
                            <th>ชื่อผู้ใช้</th>
                            <th class="text-center">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($personnel) > 0): ?>
                            <?php foreach ($personnel as $person): ?>
                                <tr data-id="<?php echo htmlspecialchars($person['personnel_id']); ?>">
                                    <td data-label="รหัสบุคลากร" class="cursor-pointer hover:underline"><?php echo htmlspecialchars($person['personnel_id']); ?></td>
                                    <td data-label="ชื่อ-นามสกุล" class="personnel-name cursor-pointer hover:underline" onclick="window.location.href='?page=personnel_detail&tabel=personnel&id=<?php echo htmlspecialchars($person['personnel_id']); ?>'"><?php echo htmlspecialchars($person['personnel_name']); ?></td>
                                    <td data-label="ประเภทบุคลากร" class="personnel-type"><?php echo htmlspecialchars($person['personnel_type_name']); ?></td>
                                    <td data-label="ชื่อผู้ใช้"><?php echo htmlspecialchars($person['personnel_username']); ?></td>
                                    <td data-label="การจัดการ" class="text-center">
                                        <div class="action-buttons flex justify-center">
                                            <button onclick="enableEdit(this)"
                                                class="edit-btn bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-sm">
                                                <i class="fas fa-edit mr-1"></i>แก้ไข
                                            </button>
                                            <a href="?page=personnel_list&id=<?php echo htmlspecialchars($person['personnel_id']); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
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
                    <a href="?page=personnel_list&page_num=<?= $page - 1 ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=personnel_list&page_num=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=personnel_list&page_num=<?= $i ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="rounded-md transition-colors <?php echo $i == $page ? 'active' : 'bg-[#aec5e366] text-black hover:bg-gray-300'; ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=personnel_list&page_num=<?= $total_pages ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>

                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=personnel_list&page_num=<?= $page + 1 ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
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
        // Function to handle empty search input
        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                window.location.href = '?page=personnel_list';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            // Focus on search input if search parameter exists
            if (urlParams.has('search')) {
                document.getElementById('searchInput').focus();
            }
            // Add event listener for Escape key to clear search
            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = '?page=personnel_list';
                }
            });
            // Show notifications for success actions
            if (urlParams.has('edit_success')) {
                showNotification('แก้ไขข้อมูลสำเร็จ', 'bg-cyan-200');
            } else if (urlParams.has('delete_success')) {
                showNotification('ลบข้อมูลสำเร็จ', 'bg-cyan-200');
            }
        });

        // ก่อน reload ให้เก็บไว้
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                localStorage.setItem('searchPreserve', this.value);
            }
        });

        // หลังโหลดหน้า
        document.addEventListener('DOMContentLoaded', () => {
            const preserved = localStorage.getItem('searchPreserve');
            if (preserved) {
                const input = document.getElementById('searchInput');
                input.value = preserved;
                input.focus();
                // เคลื่อนเคอร์เซอร์ไปหลังข้อความ
                input.selectionStart = input.selectionEnd = preserved.length;
                localStorage.removeItem('searchPreserve');
            }
        });

        function enableEdit(button) {
            const row = button.closest('tr');
            const personnelNameCell = row.querySelector('.personnel-name');
            const personnelTypeCell = row.querySelector('.personnel-type');
            const personnelId = row.getAttribute('data-id');

            const originalName = personnelNameCell.textContent.trim();
            const originalType = personnelTypeCell.textContent.trim();

            personnelNameCell.innerHTML = `<input type="text" class="edit-input p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="${originalName}" />`;

            let dropdown = `<select class="edit-select p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">`;
            <?php foreach ($personnel_types as $type): ?>
                dropdown += `<option value="<?php echo $type['personnel_type_id']; ?>" ${originalType === "<?php echo $type['personnel_type_name']; ?>" ? 'selected' : ''}><?php echo htmlspecialchars($type['personnel_type_name']); ?></option>`;
            <?php endforeach; ?>
            dropdown += `</select>`;
            personnelTypeCell.innerHTML = dropdown;

            button.classList.add('hidden');
            row.querySelector('.delete-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
        }

        function saveEdit(button) {
            const row = button.closest('tr');
            const personnelId = row.getAttribute('data-id');
            const newName = row.querySelector('.edit-input').value.trim();
            const newTypeId = row.querySelector('.edit-select').value;
            const personnelNameCell = row.querySelector('.personnel-name');
            const personnelTypeCell = row.querySelector('.personnel-type');

            if (!newName) {
                alert('กรุณากรอกชื่อบุคลากร');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${personnelId}&update_personnel_name=${encodeURIComponent(newName)}&update_personnel_type_id=${newTypeId}`
                })
                .then(response => {
                    if (response.ok) {
                        personnelNameCell.textContent = newName;
                        const selectedType = row.querySelector('.edit-select option:checked').textContent;
                        personnelTypeCell.textContent = selectedType;
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
    </script>
</body>

</html>