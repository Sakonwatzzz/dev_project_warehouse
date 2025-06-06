<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to check for duplicate usernames
function checkUsernameExists($pdo, $username)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_username = ?");
    $stmt->execute([$username]);
    return $stmt->fetchColumn() > 0;
}

// Handle AJAX request for checking username
if (isset($_GET['action']) && $_GET['action'] === 'check_username') {
    header('Content-Type: application/json');
    $username = $_GET['username'] ?? '';
    $exists = checkUsernameExists($pdo, $username);
    echo json_encode(['exists' => $exists]);
    exit;
}

// Fetch personnel types for dropdown
try {
    $query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type ORDER BY personnel_type_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $personnelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Personnel type fetch error: " . htmlspecialchars($e->getMessage()));
}

// Handle POST request for inserting a new personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('Location: ?page=personnel_add&error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    $personnel_name = trim($_POST['personnel_name'] ?? '');
    $personnel_type_id = filter_var($_POST['personnel_type_name'] ?? 0, FILTER_VALIDATE_INT);
    $personnel_address = trim($_POST['personnel_address'] ?? '');
    $personnel_phone = trim($_POST['personnel_phone'] ?? '');
    $personnel_username = trim($_POST['personnel_username'] ?? '');
    $personnel_password = $_POST['personnel_password'] ?? '';

    // Validate input
    $errors = [];
    if (empty($personnel_name) || strlen($personnel_name) > 255) {
        $errors[] = 'ชื่อ-สกุลต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (!$personnel_type_id) {
        $errors[] = 'กรุณาเลือกประเภทบุคลากร';
    }
    if (empty($personnel_address) || strlen($personnel_address) > 500) {
        $errors[] = 'ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร';
    }
    if (!empty($personnel_phone) && !preg_match('/^[0-9]{10}$/', $personnel_phone)) {
        $errors[] = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว';
    }
    if (empty($personnel_username) || strlen($personnel_username) > 50) {
        $errors[] = 'ชื่อผู้ใช้ต้องไม่ว่างและมีความยาวไม่เกิน 50 ตัวอักษร';
    }
    if (checkUsernameExists($pdo, $personnel_username)) {
        $errors[] = 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบและแก้ไขใหม่';
    }
    if (empty($personnel_password) || strlen($personnel_password) < 6) {
        $errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    }

    if (!empty($errors)) {
        header('Location: ?page=personnel_add&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $personnel_create_date = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('
            INSERT INTO personnel (personnel_name, personnel_type_id, personnel_address, personnel_phone, personnel_username, personnel_password, personnel_create_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$personnel_name, $personnel_type_id, $personnel_address, $personnel_phone, $personnel_username, $personnel_password, $personnel_create_date]);

        $personnel_id = $pdo->lastInsertId();
        header("Location: /?page=personnel_detail&tabel=personnel&id=$personnel_id&success=1");
        exit;
    } catch (PDOException $e) {
        header('Location: ?page=personnel_add&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style/page_insert.css">
    <title>เพิ่มบุคลากร | ระบบจัดการข้อมูล</title>
</head>

<body class="font-sans bg-gray-50 text-gray-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-blue-900">เพิ่มบุคลากร</h1>
            <p class="text-gray-600">กรอกข้อมูลเพื่อเพิ่มบุคลากรใหม่ลงในระบบ</p>
        </div>

        <!-- Form Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
            <form id="personnelForm" class="space-y-4" action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- ชื่อ-สกุล -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="personnel_name" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ชื่อ-สกุล:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="personnel_name" id="personnel_name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            autocomplete="off">
                        <span id="nameError" class="text-rose-500 text-xs mt-1 hidden">ชื่อ-สกุลต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร</span>
                    </div>
                </div>

                <!-- ที่อยู่ -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="personnel_address" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ที่อยู่:</label>
                    <div class="w-full sm:w-3/4">
                        <textarea name="personnel_address" id="personnel_address" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none" rows="3"></textarea>
                        <span id="addressError" class="text-rose-500 text-xs mt-1 hidden">ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร</span>
                    </div>
                </div>

                <!-- เบอร์โทร -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="personnel_phone" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">เบอร์โทร:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="personnel_phone" id="personnel_phone" maxlength="10" pattern="[0-9]{10}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="phoneError" class="text-rose-500 text-xs mt-1 hidden">เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว</span>
                    </div>
                </div>

                <!-- ชื่อผู้ใช้ -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="personnel_username" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ชื่อผู้ใช้:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="personnel_username" id="personnel_username" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            autocomplete="new-username">
                        <span id="usernameError" class="text-rose-500 text-xs mt-1 hidden">ชื่อผู้ใช้ต้องไม่ว่างและมีความยาวไม่เกิน 50 ตัวอักษร</span>
                    </div>
                </div>

                <!-- รหัสผ่าน -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="personnel_password" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">รหัสผ่าน:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="password" name="personnel_password" id="personnel_password" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            autocomplete="new-password">
                        <span id="passwordError" class="text-rose-500 text-xs mt-1 hidden">รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</span>
                    </div>
                </div>

                <!-- ประเภทบุคลากร -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="personnel_type_name" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">
                        <a href="?page=personnel_type" class="text-indigo-600 hover:underline">ประเภทบุคลากร:</a>
                    </label>
                    <div class="w-full sm:w-3/4">
                        <?php if (count($personnelTypes) > 0): ?>
                            <select name="personnel_type_name" id="personnel_type_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500" required>
                                <option value="" disabled selected>เลือกประเภทบุคลากร</option>
                                <?php foreach ($personnelTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['personnel_type_id']); ?>">
                                        <?php echo htmlspecialchars($type['personnel_type_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span id="typeError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกประเภทบุคลากร</span>
                        <?php else: ?>
                            <span class="text-gray-600">ไม่พบข้อมูลประเภทบุคลากร</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- วันที่และเวลา -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="personnel_create_date" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">วันที่และเวลา:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="personnel_create_date" id="personnel_create_date" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600 cursor-not-allowed">
                    </div>
                </div>

                <!-- ปุ่ม -->
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
                        <a href="/?page=personnel_list"
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
        // อัปเดตวันที่และเวลาแบบเรียลไทม์
        function updateDateTime() {
            const dateInput = document.getElementById('personnel_create_date');
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            dateInput.value = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }

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

        async function checkUsername(username) {
            try {
                const response = await fetch(`?action=check_username&username=${encodeURIComponent(username)}`);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const data = await response.json();
                return data.exists;
            } catch (error) {
                console.error('Error checking username:', error);
                return false;
            }
        }

        document.getElementById('personnelForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const name = document.getElementById('personnel_name').value.trim();
            const address = document.getElementById('personnel_address').value.trim();
            const phone = document.getElementById('personnel_phone').value.trim();
            const username = document.getElementById('personnel_username').value.trim();
            const password = document.getElementById('personnel_password').value;
            const type = document.getElementById('personnel_type_name').value;

            const nameError = document.getElementById('nameError');
            const addressError = document.getElementById('addressError');
            const phoneError = document.getElementById('phoneError');
            const usernameError = document.getElementById('usernameError');
            const passwordError = document.getElementById('passwordError');
            const typeError = document.getElementById('typeError');

            let hasError = false;

            if (!name || name.length > 255) {
                nameError.textContent = 'ชื่อ-สกุลต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
                nameError.classList.remove('hidden');
                hasError = true;
            } else {
                nameError.classList.add('hidden');
            }

            if (!address || address.length > 500) {
                addressError.textContent = 'ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร';
                addressError.classList.remove('hidden');
                hasError = true;
            } else {
                addressError.classList.add('hidden');
            }

            if (phone && !/^[0-9]{10}$/.test(phone)) {
                phoneError.textContent = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว';
                phoneError.classList.remove('hidden');
                hasError = true;
            } else {
                phoneError.classList.add('hidden');
            }

            if (!username || username.length > 50) {
                usernameError.textContent = 'ชื่อผู้ใช้ต้องไม่ว่างและมีความยาวไม่เกิน 50 ตัวอักษร';
                usernameError.classList.remove('hidden');
                hasError = true;
            } else {
                const exists = await checkUsername(username);
                if (exists) {
                    usernameError.textContent = 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบและแก้ไขใหม่';
                    usernameError.classList.remove('hidden');
                    hasError = true;
                } else {
                    usernameError.classList.add('hidden');
                }
            }

            if (!password || password.length < 6) {
                passwordError.textContent = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
                passwordError.classList.remove('hidden');
                hasError = true;
            } else {
                passwordError.classList.add('hidden');
            }

            if (!type) {
                typeError.textContent = 'กรุณาเลือกประเภทบุคลากร';
                typeError.classList.remove('hidden');
                hasError = true;
            } else {
                typeError.classList.add('hidden');
            }

            if (!hasError) {
                this.submit();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            document.getElementById('personnel_name').focus();
            updateDateTime();
            setInterval(updateDateTime, 1000);

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