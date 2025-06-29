<?php
session_start();
require_once __DIR__ . '../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

if (!isset($pdo)) {
    die("Error: PDO connection not established. Check database_server.php");
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: /login.php?timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token";
    } else {
        $username = trim($_POST['personnel_username'] ?? '');
        $password = trim($_POST['personnel_password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
        } else {
            try {
                $sql = "SELECT personnel_id, personnel_password, personnel_type_id FROM personnel WHERE personnel_username = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // เปรียบเทียบรหัสผ่านแบบ plain text
                    if ($password === $user['personnel_password']) {
                        $_SESSION['personnel_id'] = $user['personnel_id'];
                        $_SESSION['personnel_type_id'] = $user['personnel_type_id'];
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $_SESSION['LAST_ACTIVITY'] = time();
                        header('Location: /index.php?page=bar_index');
                        exit;
                    } else {
                        error_log("Password mismatch for username: $username");
                        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                    }
                } else {
                    error_log("User not found for username: $username");
                    $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                }
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage());
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex min-h-screen items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900">เข้าสู่ระบบ</h2>
                <p class="mt-2 text-sm text-gray-600">กรุณากรอกข้อมูลเพื่อเข้าสู่ระบบจัดการคลังสินค้า</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded relative" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
                <div class="mb-6 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded relative" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        <p>เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่</p>
                    </div>
                </div>
            <?php endif; ?>

            <form id="loginForm" class="space-y-6" action="" method="POST" autocomplete="off">
                <?php echo csrf_field(); ?>
                <div>
                    <label for="personnel_username" class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                    <div class="mt-1 relative">
                        <input type="text" name="personnel_username" id="personnel_username" 
                            autocomplete="new-username" 
                            autocorrect="off" 
                            autocapitalize="off" 
                            spellcheck="false"
                            required
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            placeholder="กรอกชื่อผู้ใช้">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                    </div>
                    <span id="usernameError" class="text-red-500 text-xs mt-1 hidden"></span>
                </div>
                <div>
                    <label for="personnel_password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                    <div class="mt-1 relative">
                        <input type="password" name="personnel_password" id="personnel_password" 
                            autocomplete="current-password" 
                            required
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focusоды:indigo-500 sm:text-sm"
                            placeholder="กรอกรหัสผ่าน">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                    </div>
                    <span id="passwordError" class="text-red-500 text-xs mt-1 hidden"></span>
                </div>

                <div>
                    <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        เข้าสู่ระบบ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('loginForm');
            const usernameInput = document.getElementById('personnel_username');
            const passwordInput = document.getElementById('personnel_password');
            const usernameError = document.getElementById('usernameError');
            const passwordError = document.getElementById('passwordError');

            form.addEventListener('submit', (e) => {
                let hasError = false;

                if (!usernameInput.value.trim()) {
                    usernameError.textContent = 'กรุณากรอกชื่อผู้ใช้';
                    usernameError.classList.remove('hidden');
                    hasError = true;
                } else {
                    usernameError.classList.add('hidden');
                }

                if (!passwordInput.value.trim()) {
                    passwordError.textContent = 'กรุณากรอกรหัสผ่าน';
                    passwordError.classList.remove('hidden');
                    hasError = true;
                } else {
                    passwordError.classList.add('hidden');
                }

                if (hasError) {
                    e.preventDefault();
                }
            });

            usernameInput.focus();
        });
    </script>
</body>
</html>