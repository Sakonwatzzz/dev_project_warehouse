<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';

// Handle POST requests for updating supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_supplier_name'])) {
        $id = $_POST['id'];
        $newName = $_POST['update_supplier_name'];
        $newAddress = $_POST['update_supplier_address'];
        try {
            $stmt = $pdo->prepare("UPDATE supplier SET supplier_name = ?, supplier_address = ? WHERE supplier_id = ?");
            $stmt->execute([$newName, $newAddress, $id]);
            header('Location: index.php?page=supplier_list&edit_success=1');
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
        $stmt = $pdo->prepare("DELETE FROM supplier WHERE supplier_id = ?");
        $stmt->execute([$id]);
        header("Location: index.php?page=supplier_list&delete_success=1");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}

// Add search variable
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Total supplier count with search
try {
    $count_query = "SELECT COUNT(*) as total_count FROM supplier";
    $params = [];
    if (!empty($search)) {
        $count_query .= " WHERE supplier_id LIKE :search OR supplier_name LIKE :search";
        $params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $count = $stmt->fetchColumn();
} catch (PDOException $e) {
    echo "Count error: " . htmlspecialchars($e->getMessage());
    $count = 0;
}

// Pagination
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $total_records = $count; // Use the count from the search query
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}

// Fetch supplier data with search
try {
    $query = "
        SELECT 
            supplier_id, 
            supplier_name, 
            supplier_address, 
            supplier_phone, 
            supplier_detail 
        FROM supplier";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE supplier_id LIKE :search OR supplier_name LIKE :search";
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
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $suppliers = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/dist/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>รายการผู้ส่งมอบ</title>
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

            td .edit-input {
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
                            <i class="fas fa-list mr-2"></i>รายการผู้ส่งมอบ
                        </h2>
                        <span class="px-3 py-1 bg-white font-bold border border-gray-500 rounded-md text-sm md:text-base"><?php echo $count; ?> รายการ</span>
                    </div>
                    <div class="w-full md:w-1/2">
                        <form action="" method="GET" id="searchForm">
                            <input type="hidden" name="page" value="supplier_list">
                            <input type="hidden" name="page_num" value="1">
                            <div class="relative">
                                <input type="text" name="search" id="searchInput"
                                    placeholder="ค้นหาผู้ส่งมอบ..."
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    class="search-bar focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                            <th>รหัสผู้ส่งมอบ</th>
                            <th>ชื่อ</th>
                            <th>ที่อยู่</th>
                            <th>เบอร์โทรศัพท์</th>
                            <th class="text-center">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($suppliers) > 0): ?>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr data-id="<?php echo htmlspecialchars($supplier['supplier_id']); ?>">
                                    <td data-label="รหัสผู้ส่งมอบ" class="cursor-pointer hover:underline"><?php echo htmlspecialchars($supplier['supplier_id']); ?></td>
                                    <td data-label="ชื่อ" class="supplier-name cursor-pointer hover:underline" onclick="window.location.href='?page=supplier_detail&tabel=supplier&id=<?php echo htmlspecialchars($supplier['supplier_id']); ?>'"><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                    <td data-label="ที่อยู่" class="supplier-address"><?php echo htmlspecialchars($supplier['supplier_address']); ?></td>
                                    <td data-label="เบอร์โทรศัพท์"><?php echo htmlspecialchars($supplier['supplier_phone']); ?></td>
                                    <td data-label="การจัดการ" class="text-center">
                                        <div class="action-buttons flex justify-center">
                                            <button onclick="enableEdit(this)"
                                                class="edit-btn bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-sm">
                                                <i class="fas fa-edit mr-1"></i>แก้ไข
                                            </button>
                                            <a href="?page=supplier_list&id=<?php echo htmlspecialchars($supplier['supplier_id']); ?>&action=delete"
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
                    <a href="?page=supplier_list&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=supplier_list&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=supplier_list&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="rounded-md transition-colors <?php echo $i == $page ? 'active' : 'bg-[#aec5e366] text-black hover:bg-gray-300'; ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=supplier_list&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>

                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=supplier_list&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
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
        function enableEdit(button) {
            const row = button.closest('tr');
            const supplierNameCell = row.querySelector('.supplier-name');
            const supplierAddressCell = row.querySelector('.supplier-address');

            const originalName = supplierNameCell.textContent.trim();
            const originalAddress = supplierAddressCell.textContent.trim();

            supplierNameCell.innerHTML = `<input type="text" class="edit-input p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="${originalName}" />`;
            supplierAddressCell.innerHTML = `<input type="text" class="edit-input w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="${originalAddress}" />`;

            button.classList.add('hidden');
            row.querySelector('.delete-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
        }

        function saveEdit(button) {
            const row = button.closest('tr');
            const supplierId = row.getAttribute('data-id');
            const newName = row.querySelector('.supplier-name .edit-input').value.trim();
            const newAddress = row.querySelector('.supplier-address .edit-input').value.trim();
            const supplierNameCell = row.querySelector('.supplier-name');
            const supplierAddressCell = row.querySelector('.supplier-address');

            if (!newName || !newAddress) {
                alert('กรุณากรอกชื่อและที่อยู่ของผู้ส่งมอบ');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${supplierId}&update_supplier_name=${encodeURIComponent(newName)}&update_supplier_address=${encodeURIComponent(newAddress)}`
                })
                .then(response => {
                    if (response.ok) {
                        supplierNameCell.textContent = newName;
                        supplierAddressCell.textContent = newAddress;
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

        // ฟังก์ชันตรวจสอบเมื่อลบข้อความค้นหาจนหมด
        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                // ถ้าช่องค้นหาว่างเปล่า ให้เปลี่ยนเส้นทางไปยังหน้าแสดงข้อมูลทั้งหมด
                window.location.href = '?page=supplier_list';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('searchInput');

            // ถ้ามีการค้นหา ให้ focus ที่ช่องค้นหา
            if (urlParams.has('search')) {
                searchInput.focus();
            }

            // เพิ่ม event listener สำหรับการกด Escape เพื่อยกเลิกการค้นหา
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = '?page=supplier_list';
                }
            });

            // เก็บค่า search ก่อน submit form
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    localStorage.setItem('searchPreserve', this.value);
                }
            });

            // คืนค่า search หลังโหลดหน้า
            const preserved = localStorage.getItem('searchPreserve');
            if (preserved) {
                searchInput.value = preserved;
                searchInput.focus();
                // เคลื่อนเคอร์เซอร์ไปหลังข้อความ
                searchInput.selectionStart = searchInput.selectionEnd = preserved.length;
                localStorage.removeItem('searchPreserve');
            }

            // เพิ่ม event listener สำหรับตรวจสอบเมื่อลบข้อความค้นหา
            searchInput.addEventListener('input', function() {
                checkEmptySearch(this);
            });

            // แสดงการแจ้งเตือนตามสถานะ
            if (urlParams.has('edit_success')) {
                showNotification('แก้ไขข้อมูลสำเร็จ', 'bg-cyan-200');
            } else if (urlParams.has('delete_success')) {
                showNotification('ลบข้อมูลสำเร็จ', 'bg-cyan-200');
            }
        });
    </script>
</body>

</html>