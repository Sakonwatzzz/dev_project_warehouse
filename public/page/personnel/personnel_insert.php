<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';
session_start();
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
        echo "<script>
                showModal('ข้อผิดพลาด', 'Invalid CSRF token', true);
              </script>";
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
        echo "<script>
                showModal('ข้อผิดพลาด', '" . implode('\\n', array_map('htmlspecialchars', $errors)) . "', true);
              </script>";
        exit;
    }

    try {
        $personnel_create_date = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('
            INSERT INTO personnel (personnel_name, personnel_type_id, personnel_address, personnel_phone, personnel_username, personnel_password, personnel_create_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$personnel_name, $personnel_type_id, $personnel_address, $personnel_phone, $personnel_username, $personnel_password, $personnel_create_date ]);

        $personnel_id = $pdo->lastInsertId();
        header("Location: /?page=personnel_detail&tabel=personnel&id=$personnel_id");
        exit;
    } catch (PDOException $e) {
        echo "<script>
                showModal('ข้อผิดพลาด', 'Database error: " . htmlspecialchars($e->getMessage()) . "', true);
              </script>";
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
    <title>เพิ่มบุคลากร</title>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <h2 class="text-gray-800 text-2xl font-bold mt-8 mb-4">เพิ่มบุคลากร</h2>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Personnel Insert Section -->
            <div class="w-full">
                <div class="bg-gray-50 border-2 border-gray-300 shadow-md rounded-lg">
                    <h3 class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-xl font-bold p-4 border-b-2 border-gray-400 rounded-t-lg">
                        กรอกข้อมูลบุคลากร
                    </h3>
                    <form class="p-6 space-y-1" action="" method="POST" id="personnelForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <!-- Personnel Fields -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ชื่อ-สกุล:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="text"
                                    name="personnel_name"
                                    id="personnel_name"
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    required
                                    autocomplete="off"
                                    autofocus>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ที่อยู่:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <textarea name="personnel_address"
                                    id="personnel_address"
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"
                                    rows="3"
                                    required></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">เบอร์โทร:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="text"
                                    name="personnel_phone"
                                    id="personnel_phone"
                                    maxlength="10"
                                    pattern="[0-9]{10}"
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">ชื่อผู้ใช้:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="text"
                                    name="personnel_username"
                                    id="personnel_username"
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    required
                                    autocomplete="new-username">
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">รหัสผ่าน:</label>
                            <div class="w-7/12 lg:w-8/12">
                                <input type="password"
                                    name="personnel_password"
                                    id="personnel_password"
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    required
                                    autocomplete="new-password">
                            </div>
                        </div>

                        <!-- Personnel Type -->
                        <div class="flex items-center gap-4">
                            <label class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">
                                <a href="?page=personnel_type" class="text-blue-600 hover:underline">ประเภทบุคลากร:</a>
                            </label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <?php if (count($personnelTypes) > 0): ?>
                                    <select name="personnel_type_name" id="personnel_type_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required>
                                        <option value="" disabled selected>เลือกประเภทบุคลากร</option>
                                        <?php foreach ($personnelTypes as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type['personnel_type_id']); ?>">
                                                <?php echo htmlspecialchars($type['personnel_type_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <!-- <a href="?page=personnel_type_add" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                                        เพิ่ม
                                    </a> -->
                                <?php else: ?>
                                    <span class="text-gray-600">ไม่พบข้อมูลประเภทบุคลากร</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- วันที่สมัคร -->
                        <div class="flex items-center  flex-col sm:flex-row sm:items-center gap-4">
                            <label for="personnel_create_date" class="w-5/12 pl-11 p-2 lg:w-4/12 text-base font-medium text-gray-700">วันที่และเวลา</label>
                            <div class="w-7/12 lg:w-8/12 flex items-center gap-2">
                                <input type="text" name="personnel_create_date" id="personnel_create_date" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly
                                    class="w-full p-2 border border-gray-300 bg-gray-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none cursor-not-allowed">
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4 mt-6">
                            <a href="/?page=personnel_list"
                                class="mt-4 bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 transition duration-200">
                                ยกเลิก
                            </a>
                            <button type="submit"
                                class="mt-4 bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 transition duration-200">
                                บันทึก
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for validation messages -->
    <div id="modalContainer" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md mx-auto">
            <div class="text-lg font-bold mb-4" id="modalTitle">แจ้งเตือน</div>
            <div class="mb-6" id="modalMessage">ข้อความแจ้งเตือน</div>
            <div class="flex justify-end">
                <button id="modalButton" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition duration-200">
                    ตกลง
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('personnelForm');
            const usernameInput = document.getElementById('personnel_username');
            const modalContainer = document.getElementById('modalContainer');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalButton = document.getElementById('modalButton');

            // Show modal with message
            function showModal(title, message, isError = false) {
                modalTitle.textContent = title;
                modalMessage.textContent = message;
                modalButton.className = isError ?
                    'bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition duration-200' :
                    'bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition duration-200';
                modalContainer.classList.remove('hidden');
            }

            // Close modal
            function closeModal() {
                modalContainer.classList.add('hidden');
                usernameInput.focus();
            }

            // Set up modal button handler
            modalButton.addEventListener('click', closeModal);

            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !modalContainer.classList.contains('hidden')) {
                    closeModal();
                }
            });

            // Check if username exists
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

            // Form submission handler
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const name = document.getElementById('personnel_name').value.trim();
                const address = document.getElementById('personnel_address').value.trim();
                const phone = document.getElementById('personnel_phone').value.trim();
                const username = usernameInput.value.trim();
                const password = document.getElementById('personnel_password').value;
                const type = document.getElementById('personnel_type_name').value;

                let errors = [];

                if (!name || name.length > 255) {
                    errors.push('ชื่อ-สกุลต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร');
                    document.getElementById('personnel_name').classList.add('border-red-500', 'bg-red-50');
                }

                if (!address || address.length > 500) {
                    errors.push('ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร');
                    document.getElementById('personnel_address').classList.add('border-red-500', 'bg-red-50');
                }

                if (phone && !/^[0-9]{10}$/.test(phone)) {
                    errors.push('เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว');
                    document.getElementById('personnel_phone').classList.add('border-red-500', 'bg-red-50');
                }

                if (!username || username.length > 50) {
                    errors.push('ชื่อผู้ใช้ต้องไม่ว่างและมีความยาวไม่เกิน 50 ตัวอักษร');
                    usernameInput.classList.add('border-red-500', 'bg-red-50');
                } else {
                    const exists = await checkUsername(username);
                    if (exists) {
                        errors.push('ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบและแก้ไขใหม่');
                        usernameInput.classList.add('border-red-500', 'bg-red-50');
                    }
                }

                if (!password || password.length < 6) {
                    errors.push('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
                    document.getElementById('personnel_password').classList.add('border-red-500', 'bg-red-50');
                }

                if (!type) {
                    errors.push('กรุณาเลือกประเภทบุคลากร');
                    document.getElementById('personnel_type_name').classList.add('border-red-500', 'bg-red-50');
                }

                if (errors.length > 0) {
                    showModal('ข้อผิดพลาด', errors.join('\n'), true);
                    return;
                }

                form.submit();
            });

            // Clear error styling when inputs change
            ['personnel_name', 'personnel_address', 'personnel_phone', 'personnel_username', 'personnel_password', 'personnel_type_name'].forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', function() {
                        this.classList.remove('border-red-500', 'bg-red-50');
                    });
                }
            });

            // Focus on name field on page load
            document.getElementById('personnel_name').focus();
        });
    </script>
</body>

</html>