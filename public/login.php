<?php
require_once __DIR__ . '../../config/database_server.php';
require_once __DIR__ . '/auth_handler.php';

session_start();
$pdo = Database::getInstance()->getConnection();


header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');


// ตรวจสอบ session ถ้าล็อกอินแล้วให้เปลี่ยนเส้นทางไปหน้าที่เหมาะสม
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'sale') {
        header('Location: index.php?page=sale_add');
    } else if ($_SESSION['role'] === 'warehouse') {
        header('Location: index.php?page=stock_insert');
    } else {
        header('Location: index.php?page=bar_index');
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT p.personnel_password, pt.personnel_type_name AS role 
                            FROM personnel p 
                            LEFT JOIN personnel_type pt ON p.personnel_type_id = pt.personnel_type_id 
                            WHERE p.personnel_username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && $user['personnel_password'] === $password) {
        $role = $user['role'] ?? 'user';
        $accessToken = createTokens($username, $role);
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;

        // เปลี่ยนเส้นทางตามบทบาท
        if ($role === 'sale') {
            header('Location: index.php?page=sale_add');
        } else if ($role === 'warehouse') {
            header('Location: index.php?page=stock_insert');
        } else {
            header('Location: index.php?page=bar_index');
        }
        exit();
    } else {
        $error = "ล็อกอินไม่สำเร็จ: ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ล็อกอิน</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">เข้าสู่ระบบ</h2>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                <input
                    type="text"
                    name="username"
                    id="username"
                    placeholder="กรอกชื่อผู้ใช้"
                    required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="กรอกรหัสผ่าน"
                    required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <button
                    type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    ล็อกอิน
                </button>
            </div>
        </form>
    </div>

    <script>
        // ป้องกันการย้อนกลับโดยการรีเฟรชหน้า
        if (window.history && window.history.pushState) {
            window.history.pushState(null, null, window.location.href);
            window.onpopstate = function() {
                window.history.pushState(null, null, window.location.href);
                window.location.reload();
            };
        }
    </script>
</body>

</html>