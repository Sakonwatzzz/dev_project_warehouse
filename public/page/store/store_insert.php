<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle POST request for inserting a new store
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('Location: ?page=store_add&error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $create_date = date('Y-m-d H:i:s'); // Current datetime

    // Validate input
    $errors = [];
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อร้านค้าต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }

    if (!empty($errors)) {
        header('Location: ?page=store_add&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO store (store_name, store_create_date) VALUES (?, ?)');
        $stmt->execute([$name, $create_date]);

        $store_id = $pdo->lastInsertId();
        header("Location: /?page=store_list&tabel=store&id=$store_id&success=เพิ่มข้อมูลสำเร็จ");
        exit;
    } catch (PDOException $e) {
        header('Location: ?page=store_add&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <link href="../../style/page_insert.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>เพิ่มร้านค้า | ระบบจัดการข้อมูล</title>
</head>

<body class="font-sans bg-gray-50 text-gray-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">เพิ่มร้านค้า</h1>
            <p class="text-gray-600">กรอกข้อมูลเพื่อเพิ่มร้านค้าใหม่ลงในระบบ</p>
        </div>

        <!-- Form Section -->
        <div class="bg-white rounded-lg shadow-lg border border-gray-100 p-6">
            <form id="storeForm" class="space-y-4" action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- ชื่อร้านค้า -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="store-name" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ชื่อร้านค้า:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="name" id="store-name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="nameError" class="text-rose-500 text-xs mt-1 hidden">ชื่อร้านค้าต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร</span>
                    </div>
                </div>

                <!-- วันที่สร้าง -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="store-create-date" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">วันที่สร้าง:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="create_date" id="store-create-date" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600 cursor-not-allowed">
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" onclick="showCancelModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        ยกเลิก
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>

        <!-- Cancel Confirmation Modal -->
        <div id="cancelModal"
            class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
            <div class="bg-white rounded-lg w-full max-w-md p-6 relative transform scale-95 transition-all duration-300">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">ยืนยันการยกเลิก</h3>
                    <p class="text-sm text-gray-500 mb-6">คุณแน่ใจหรือไม่ว่าต้องการยกเลิก? ข้อมูลที่กรอกจะไม่ถูกบันทึก</p>

                    <div class="flex justify-center gap-3">
                        <button type="button" onclick="closeCancelModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            อยู่ต่อ
                        </button>
                        <a href="/?page=store_list"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                            ยกเลิก
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div id="notification"
            class="fixed top-4 right-4 max-w-sm w-full bg-white rounded-lg shadow-lg border-l-4 transform translate-x-full transition-transform duration-300 z-50 flex items-center">
            <div id="notification-header" class="flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg">
                <i id="notificationIcon" class="fas fa-check-circle text-2xl"></i>
            </div>
            <div class="flex-1 ml-4 pr-4 py-4">
                <p id="notificationTitle" class="text-sm font-medium text-gray-900"></p>
                <p id="notificationMessage" class="mt-1 text-sm text-gray-500"></p>
            </div>
            <button onclick="hideNotification()" class="mr-4 text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <script>
        function showCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.classList.remove('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
        }

        function closeCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.classList.add('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
        }

        function showNotification(message, headerClass) {
            const notification = document.getElementById('notification');
            const header = document.getElementById('notification-header');
            const title = document.getElementById('notificationTitle');
            const body = document.getElementById('notificationMessage');
            const icon = document.getElementById('notificationIcon');

            header.className = `flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg ${headerClass}`;
            title.textContent = 'แจ้งเตือน';
            body.textContent = message;
            icon.className = `fas ${headerClass.includes('bg-green') ? 'fa-check-circle' : 'fa-exclamation-circle'} text-2xl`;
            notification.classList.remove('translate-x-full');

            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        function hideNotification() {
            const notification = document.getElementById('notification');
            notification.classList.add('translate-x-full');
        }

        // Update date and time in real-time
        function updateDateTime() {
            const dateInput = document.getElementById('store-create-date');
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            dateInput.value = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }

        document.getElementById('storeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('store-name').value.trim();
            const nameError = document.getElementById('nameError');

            let hasError = false;

            if (!name || name.length > 255) {
                nameError.textContent = 'ชื่อร้านค้าต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
                nameError.classList.remove('hidden');
                hasError = true;
            } else {
                nameError.classList.add('hidden');
            }

            if (!hasError) {
                this.submit();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            document.getElementById('store-name').focus();
            updateDateTime(); // Update date and time on page load
            setInterval(updateDateTime, 1000); // Update every second

            if (urlParams.has('error')) {
                showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                showCancelModal();
            }
        });

        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCancelModal();
            }
        });
    </script>
</body>

</html>