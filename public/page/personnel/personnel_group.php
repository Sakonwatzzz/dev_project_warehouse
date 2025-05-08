<?php
require_once __DIR__ . '../../../../config/database_server.php';

// รับค่าจาก URL เพื่อหา type ของตำแหน่ง
$type = isset($_GET['type']) ? $_GET['type'] : '';

// เพิ่มตัวแปรสำหรับการค้นหา
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle DELETE request
if (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM personnel WHERE personnel_id = ?");
        $stmt->execute([$id]);
        header("Location: /?page=personnel_group&type=" . urlencode($type) . "&delete_success=1" . (!empty($search) ? "&search=" . urlencode($search) : ""));
        exit;
    } catch (PDOException $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}

// Count total records for the specified personnel type with search condition
$query = "
    SELECT COUNT(*) as total_count
    FROM personnel
    INNER JOIN personnel_type ON personnel.personnel_type_id = personnel_type.personnel_type_id
    WHERE personnel_type.personnel_type_name = :type";
$params = [':type' => $type];

if (!empty($search)) {
    $query .= " AND (personnel.personnel_id LIKE :search OR personnel.personnel_name LIKE :search OR personnel.personnel_username LIKE :search)";
    $params[':search'] = "%$search%";
}

try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];
} catch (PDOException $e) {
    $count = 0;
    echo "Count error: " . htmlspecialchars($e->getMessage());
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $total_query = "
        SELECT COUNT(*)
        FROM personnel
        INNER JOIN personnel_type ON personnel.personnel_type_id = personnel_type.personnel_type_id
        WHERE personnel_type.personnel_type_name = :type";
    $total_params = [':type' => $type];

    if (!empty($search)) {
        $total_query .= " AND (personnel.personnel_id LIKE :search OR personnel.personnel_name LIKE :search OR personnel.personnel_username LIKE :search)";
        $total_params[':search'] = "%$search%";
    }

    $stmt = $pdo->prepare($total_query);
    foreach ($total_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}

// Fetch personnel data for the specified type with search condition
$query = "
    SELECT    
        p.personnel_id,
        p.personnel_name,
        p.personnel_address,
        p.personnel_username,
        pt.personnel_type_name
    FROM
        personnel p
    INNER JOIN
        personnel_type pt ON p.personnel_type_id = pt.personnel_type_id
    WHERE
        pt.personnel_type_name = :type";
$params = [':type' => $type];

if (!empty($search)) {
    $query .= " AND (p.personnel_id LIKE :search OR p.personnel_name LIKE :search OR p.personnel_username LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY p.personnel_name LIMIT :offset, :records_per_page";

try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $personnelData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $personnelData = [];
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
    <title>กลุ่มบุคลากร: <?php echo htmlspecialchars($type); ?></title>
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

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
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
                            <i class="fas fa-list mr-2"></i>รายชื่อบุคลากร: <?php echo htmlspecialchars($type); ?>
                        </h2>
                        <span class="px-3 py-1 bg-white font-bold border border-gray-500 rounded-md text-sm md:text-base"><?php echo $count; ?> รายการ</span>
                    </div>
                    <div class="w-full md:w-1/2">
                        <form action="" method="GET" class="w-full" id="searchForm">
                            <input type="hidden" name="page" value="personnel_group">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
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
                        <?php if (count($personnelData) > 0): ?>
                            <?php foreach ($personnelData as $personnel): ?>
                                <tr data-id="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                                    <td data-label="รหัสบุคลากร" class="cursor-pointer hover:underline"><?php echo htmlspecialchars($personnel['personnel_id']); ?></td>
                                    <td data-label="ชื่อ-นามสกุล" class="cursor-pointer hover:underline"><?php echo htmlspecialchars($personnel['personnel_name']); ?></td>
                                    <td data-label="ประเภทบุคลากร"><?php echo htmlspecialchars($personnel['personnel_type_name']); ?></td>
                                    <td data-label="ชื่อผู้ใช้"><?php echo htmlspecialchars($personnel['personnel_username']); ?></td>
                                    <td data-label="การจัดการ" class="text-center">
                                        <div class="action-buttons">
                                            <a href="?page=personnel_update&id=<?php echo htmlspecialchars($personnel['personnel_id']); ?>"
                                                class="edit-btn bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-sm">
                                                <i class="fas fa-edit mr-1"></i>แก้ไข
                                            </a>
                                            <a href="?page=personnel_group&type=<?php echo urlencode($type); ?>&id=<?php echo htmlspecialchars($personnel['personnel_id']); ?>&action=delete<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                                                onclick="return confirm('ยืนยันการลบข้อมูล?')"
                                                class="delete-btn bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition flex items-center text-sm">
                                                <i class="fas fa-trash-alt mr-1"></i>ลบ
                                            </a>
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
                    <a href="?page=personnel_group&type=<?php echo urlencode($type); ?>&page_num=<?= $page - 1 ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=personnel_group&type=<?php echo urlencode($type); ?>&page_num=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=personnel_group&type=<?php echo urlencode($type); ?>&page_num=<?= $i ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="rounded-md transition-colors <?php echo $i == $page ? 'active' : 'bg-[#aec5e366] text-black hover:bg-gray-300'; ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-1 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=personnel_group&type=<?php echo urlencode($type); ?>&page_num=<?= $total_pages ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>

                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=personnel_group&type=<?php echo urlencode($type); ?>&page_num=<?= $page + 1 ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="bg-[#aec5e366] text-black rounded-md hover:bg-gray-300 transition-colors">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Navigation Links -->
        <div class="flex space-x-2 mt-5 justify-center">
            <div class="detail-action">
                <a href="/?page=personnel_list" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">หน้ารายการ</a>
            </div>
            <div class="detail-action">
                <a href="/?page=personnel_type" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">หน้าประเภท</a>
            </div>
        </div>

        <!-- Notification -->
        <div id="notification" class="notification hidden">
            <div id="notification-header" class="p-2 rounded-t-md font-bold"></div>
            <div id="notification-body" class="p-4 text-center text-base"></div>
        </div>
    </div>

    <script>
        // ฟังก์ชันตรวจสอบเมื่อลบข้อความค้นหาจนหมด
        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                window.location.href = '?page=personnel_group&type=<?php echo urlencode($type); ?>';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('delete_success')) {
                showNotification('ลบข้อมูลสำเร็จ', 'bg-cyan-200');
            }

            // ถ้ามีการค้นหา ให้ focus ที่ช่องค้นหา
            if (urlParams.has('search')) {
                document.getElementById('searchInput').focus();
            }

            // เพิ่ม event listener สำหรับการกด Escape เพื่อยกเลิกการค้นหา
            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = '?page=personnel_group&type=<?php echo urlencode($type); ?>';
                }
            });
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