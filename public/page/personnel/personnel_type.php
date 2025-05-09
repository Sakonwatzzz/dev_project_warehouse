<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Add search variable
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        die('Database error: ' . htmlspecialchars($e->getMessage()));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['personnel_type'])) {
        $personnel_type = trim($_POST['personnel_type']);
        if (!empty($personnel_type)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO personnel_type (personnel_type_name) VALUES (?)');
                $stmt->execute([$personnel_type]);
                header('Location: ?page=personnel_type&add_success=1' . (!empty($search) ? '&search=' . urlencode($search) : ''));
                exit;
            } catch (PDOException $e) {
                echo 'Database error: ' . htmlspecialchars($e->getMessage());
                exit;
            }
        }
    } elseif (isset($_POST['update_personnel_type']) && isset($_POST['id'])) {
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        $newpersonnel_type = trim($_POST['update_personnel_type']);
        if ($id && !empty($newpersonnel_type)) {
            try {
                $stmt = $pdo->prepare("UPDATE personnel_type SET personnel_type_name = ? WHERE personnel_type_id = ?");
                $stmt->execute([$newpersonnel_type, $id]);
                header('Location: ?page=personnel_type&edit_success=1' . (!empty($search) ? '&search=' . urlencode($search) : ''));
                exit;
            } catch (PDOException $e) {
                echo 'Database error: ' . htmlspecialchars($e->getMessage());
                exit;
            }
        }
    }
}

if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM personnel_type WHERE personnel_type_id = ?");
            $stmt->execute([$id]);
            header("Location: ?page=personnel_type&delete_success=1" . (!empty($search) ? '&search=' . urlencode($search) : ''));
            exit;
        }
    } catch (PDOException $e) {
        echo "Delete error: " . htmlspecialchars($e->getMessage());
        exit;
    }
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_count FROM personnel_type");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
} catch (PDOException $e) {
    $count = 0;
    echo "Count error: " . htmlspecialchars($e->getMessage());
}

$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $count_query = "SELECT COUNT(*) FROM personnel_type";
    $params = [];
    if (!empty($search)) {
        $count_query .= " WHERE personnel_type_name LIKE :search OR personnel_type_id LIKE :search";
        $params[':search'] = "%$search%";
    }

    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}

try {
    $query = "
        SELECT 
            pn.personnel_type_id,
            pn.personnel_type_name,
            COALESCE(COUNT(p.personnel_id), 0) as personnel_count
        FROM personnel_type pn
        LEFT JOIN personnel p ON pn.personnel_type_id = p.personnel_type_id";
    
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE pn.personnel_type_name LIKE :search OR pn.personnel_type_id LIKE :search";
        $params[':search'] = "%$search%";
    }

    $query .= " GROUP BY pn.personnel_type_id, pn.personnel_type_name 
        LIMIT :offset, :records_per_page";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $personnel_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $personnel_types = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>จัดการประเภทบุคลากร</title>
    <style>
        @media (max-width: 768px) {
            .table-responsive {
                display: none;
            }

            .card-responsive {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .table-responsive {
                display: block;
            }

            .card-responsive {
                display: none;
            }
        }
    </style>
</head>

<body class="font-sans bg-gray-100">
    <div class="container mx-auto px-4 py-8 md:px-8 lg:px-16">
        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-list mr-2"></i>รายการประเภทบุคลากร
                        </h2>
                        <span class="px-3 py-1 bg-white font-bold border border-gray-500 rounded-md text-sm md:text-base"><?php echo $count; ?> รายการ</span>
                    </div>
                    <div class="w-full md:w-1/2">
                        <form action="" method="GET" class="w-full" id="searchForm">
                            <input type="hidden" name="page" value="personnel_type">
                            <div class="relative">
                                <input type="text" name="search" id="searchInput"
                                    placeholder="ค้นหาประเภทบุคลากร..." value="<?php echo htmlspecialchars($search); ?>"
                                    class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base"
                                    oninput="checkEmptySearch(this)">
                                <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="p-4 md:p-6">
                <!-- Table View (Desktop/iPad) -->
                <div class="table-responsive overflow-x-auto">
                    <table class="w-full border-collapse" id="dataTable">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 border text-left text-gray-700 font-semibold text-sm md:text-base">รหัสประเภท</th>
                                <th class="px-4 py-3 border text-left text-gray-700 font-semibold text-sm md:text-base">ชื่อประเภทบุคลากร</th>
                                <th class="px-4 py-3 border text-left text-gray-700 font-semibold text-sm md:text-base">จำนวนบุคลากร</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Add Form Row -->
                            <tr>
                                <form action="" method="POST">
                                    <td class="px-4 py-2 border bg-white text-center text-gray-600 text-sm md:text-base">อัตโนมัติ</td>
                                    <td class="px-4 py-2 border bg-white">
                                        <input type="text" id="personnel_type" name="personnel_type"
                                            placeholder="ชื่อประเภทบุคลากร"
                                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base"
                                            required />
                                    </td>
                                    <td class="px-4 py-2 border bg-white text-center text-sm md:text-base">-</td>
                                    <td class="px-4 py-2 border bg-white text-center">
                                        <button type="submit"
                                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex items-center mx-auto text-sm md:text-base">
                                            <i class="fas fa-plus mr-1"></i>เพิ่ม
                                        </button>
                                    </td>
                                </form>
                            </tr>
                            <!-- Data Rows -->
                            <?php if (count($personnel_types) > 0): ?>
                                <?php foreach ($personnel_types as $personneltype): ?>
                                    <tr class="hover:bg-gray-100" data-id="<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>">
                                        <td class="px-4 py-1 border text-sm md:text-base"><?php echo htmlspecialchars($personneltype['personnel_type_id']); ?></td>
                                        <td class="px-4 py-1 border type-name text-sm md:text-base"><?php echo htmlspecialchars($personneltype['personnel_type_name']); ?></td>
                                        <td class="px-4 py-1 border text-center">
                                            <a href="?page=personnel_group&type=<?php echo urlencode($personneltype['personnel_type_name']); ?>"
                                                class="text-orange-600 text-sm md:text-base hover:underline">
                                                <?php echo number_format($personneltype['personnel_count']); ?> รายการ
                                            </a>
                                        </td>
                                        <td class="px-4 py-1 border text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button onclick="enableEdit(this)"
                                                    class="edit-btn bg-yellow-500 text-white px-4 py-1 rounded-lg hover:bg-yellow-600 transition flex items-center text-sm md:text-base">
                                                    <i class="fas fa-edit mr-1"></i>แก้ไข
                                                </button>
                                                <a href="?page=personnel_type&id=<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>"
                                                    onclick="return confirm('ยืนยันการลบข้อมูล?')"
                                                    class="delete-btn bg-red-500 text-white px-4 py-1 rounded-lg hover:bg-red-600 transition flex items-center text-sm md:text-base">
                                                    <i class="fas fa-trash-alt mr-1"></i>ลบ
                                                </a>
                                                <button class="save-btn hidden bg-green-500 text-white px-4 py-1 rounded-lg hover:bg-green-600 transition items-center text-sm md:text-base"
                                                    onclick="saveEdit(this)">
                                                    <i class="fas fa-save mr-1"></i>บันทึก
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-gray-600 text-sm md:text-base">ไม่มีข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Card View (Mobile) -->
                <div class="card-responsive">
                    <!-- Add Form Card -->
                    <div class="bg-white p-4 mb-4 rounded-lg shadow">
                        <form action="" method="POST">
                            <div class="mb-4">
                                <label class="block text-gray-700 font-semibold mb-2 text-sm">ชื่อประเภทบุคลากร</label>
                                <input type="text" id="personnel_type_mobile" name="personnel_type"
                                    placeholder="ชื่อประเภทบุคลากร"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    required />
                            </div>
                            <button type="submit"
                                class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex items-center justify-center text-sm">
                                <i class="fas fa-plus mr-1"></i>เพิ่ม
                            </button>
                        </form>
                    </div>
                    <!-- Data Cards -->
                    <?php if (count($personnel_types) > 0): ?>
                        <?php foreach ($personnel_types as $personneltype): ?>
                            <div class="bg-white p-4 mb-4 rounded-lg shadow" data-id="<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">รหัสประเภท:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($personneltype['personnel_type_id']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm type-name">ชื่อประเภท:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($personneltype['personnel_type_name']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">จำนวนบุคลากร:</span>
                                    <a href="?page=personnel_group&type=<?php echo urlencode($personneltype['personnel_type_name']); ?>"
                                        class="text-orange-600 hover:underline text-sm">
                                        <?php echo number_format($personneltype['personnel_count']); ?> รายการ
                                    </a>
                                </div>
                                <div class="flex justify-center space-x-2 mt-4">
                                    <button onclick="enableEditMobile(this)"
                                        class="edit-btn bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition flex items-center text-sm">
                                        <i class="fas fa-edit mr-1"></i>แก้ไข
                                    </button>
                                    <a href="?page=personnel_type&id=<?php echo htmlspecialchars($personneltype['personnel_type_id']); ?>"
                                        onclick="return confirm('ยืนยันการลบข้อมูล?')"
                                        class="delete-btn bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition flex items-center text-sm">
                                        <i class="fas fa-trash-alt mr-1"></i>ลบ
                                    </a>
                                    <button class="save-btn hidden bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition items-center text-sm"
                                        onclick="saveEditMobile(this)">
                                        <i class="fas fa-save mr-1"></i>บันทึก
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-600 text-sm">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination mt-5 flex flex-wrap justify-center items-center gap-2">
            <?php if ($total_pages > 1): ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=personnel_type&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=personnel_type&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=personnel_type&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 rounded-md transition-colors text-sm md:text-base <?= $i == $page ? 'bg-gray-800 text-white' : 'bg-blue-100 text-black hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                    <a href="?page=personnel_type&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>

                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=personnel_type&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="hidden fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white border-2 border-gray-400 rounded-md shadow-lg w-80 md:w-96 h-32 z-50">
        <div id="notification-header" class="p-2 rounded-t-md font-bold text-sm md:text-base"></div>
        <div id="notification-body" class="p-5 text-center text-base md:text-lg"></div>
    </div>

    <script>
        // Function to check if search input is empty
        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                window.location.href = '?page=personnel_type';
            }
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

            // Focus on search input if search parameter exists
            if (urlParams.has('search')) {
                document.getElementById('searchInput').focus();
            }

            // Add event listener for Escape key to clear search
            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = '?page=personnel_type';
                }
            });
        });

        // Preserve search input before form submission
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                localStorage.setItem('searchPreserve', this.value);
            }
        });

        // Restore search input after page load
        document.addEventListener('DOMContentLoaded', () => {
            const preserved = localStorage.getItem('searchPreserve');
            if (preserved) {
                const input = document.getElementById('searchInput');
                input.value = preserved;
                input.focus();
                input.selectionStart = input.selectionEnd = preserved.length;
                localStorage.removeItem('searchPreserve');
            }
        });

        function enableEdit(button) {
            const row = button.closest('tr');
            const typeNameCell = row.querySelector('.type-name');
            const originalName = typeNameCell.textContent.trim();

            typeNameCell.innerHTML = `<input type="text" class="edit-input w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base" value="${originalName}" />`;
            button.classList.add('hidden');
            row.querySelector('.delete-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
        }

        function enableEditMobile(button) {
            const card = button.closest('div[data-id]');
            const typeNameSpan = card.querySelector('.type-name + span');
            const originalName = typeNameSpan.textContent.trim();

            typeNameSpan.innerHTML = `<input type="text" class="edit-input w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" value="${originalName}" />`;
            button.classList.add('hidden');
            card.querySelector('.delete-btn').classList.add('hidden');
            card.querySelector('.save-btn').classList.remove('hidden');
        }

        function saveEdit(button) {
            const row = button.closest('tr');
            const typeId = row.getAttribute('data-id');
            const newName = row.querySelector('.edit-input').value.trim();
            const typeNameCell = row.querySelector('.type-name');

            if (!newName) {
                alert('กรุณากรอกชื่อประเภทบุคลากร');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${typeId}&update_personnel_type=${encodeURIComponent(newName)}`
                })
                .then(response => {
                    if (response.ok) {
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

        function saveEditMobile(button) {
            const card = button.closest('div[data-id]');
            const typeId = card.getAttribute('data-id');
            const newName = card.querySelector('.edit-input').value.trim();
            const typeNameSpan = card.querySelector('.type-name + span');

            if (!newName) {
                alert('กรุณากรอกชื่อประเภทบุคลากร');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${typeId}&update_personnel_type=${encodeURIComponent(newName)}`
                })
                .then(response => {
                    if (response.ok) {
                        typeNameSpan.textContent = newName;
                        const editBtn = card.querySelector('.edit-btn');
                        const deleteBtn = card.querySelector('.delete-btn');
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

            header.className = `p-2 rounded-t-md font-bold ${headerClass} text-sm md:text-base`;
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