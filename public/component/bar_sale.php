<?php
ob_start();
require_once __DIR__ . '../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

// Get the current page from URL
$current_page = isset($_GET['page']) ? $_GET['page'] : 'sale_add';

$accessToken = getToken('access_token');
if (!$accessToken || !validateToken($accessToken)) {
    $refreshToken = getToken('refresh_token');
    if ($refreshToken && validateToken($refreshToken)) {
        $accessToken = refreshToken($refreshToken);
    } else {
        header('Location: login.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <link href="../style/bar_button.css" rel="stylesheet">
    <title>Sales Navigation</title>
</head>

<body class="text-gray-800 min-h-screen overflow-x-hidden font-sans">
    <!-- Button Bar for Desktop/Tablet (1024px and above) -->
    <div class="button-bb hidden lg:flex flex-wrap gap-2 p-2 px-4" role="navigation">
        <!-- Sales Section -->
        <div class="section-container border border-gray-300 rounded-md shadow-md">
            <div class="button-grid flex flex-wrap gap-2 p-1">
                <a href="?page=sale_add" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'sale_add') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="หน้าการขายสินค้า">
                        <i class="fas fa-shopping-cart text-xl mb-1"></i>
                        <span>หน้าการขายสินค้า</span>
                    </button>
                </a>
                <a href="?page=sale_list" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'sale_list') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="รายการขายสินค้า">
                        <i class="fas fa-list-alt text-xl mb-1"></i>
                        <span>รายการขายสินค้า</span>
                    </button>
                </a>
                <a href="?page=sale_output" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'sale_output') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="แสดงรายการขาย(ลูกค้า)">
                        <i class="fas fa-file-invoice text-xl mb-1"></i>
                        <span>แสดงรายการขาย(ลูกค้า)</span>
                    </button>
                </a>
                <a href="?page=sale_inserttest" class="button-link w-24 lg:w-28" hidden>
                    <button class="<?php echo ($current_page == 'sale_inserttest') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="หน้าการขายสินค้า(ทดลอง4)">
                        <i class="fas fa-flask text-xl mb-1"></i>
                        <span>หน้าการขายสินค้า(ทดลอง4)</span>
                    </button>
                </a>
            </div>
        </div>
    </div>

    <!-- Dropdown for Mobile -->
    <div class="dropdown-container lg:hidden p-2" role="navigation">
        <!-- Sales Dropdown -->
        <div class="dropdown-section mb-2">
            <button class="dropdown-btn w-full py-3 px-4 font-bold text-sm text-white rounded-md shadow-sm flex justify-between items-center focus:ring-2 focus:ring-blue-600 transition-all duration-200" onclick="toggleDropdown('salesDropdown')" aria-expanded="false" aria-controls="salesDropdown">
                การขาย <span>▼</span>
            </button>
            <div id="salesDropdown" class="dropdown-content hidden bg-[#f1f9fe] border border-[#a6c4de] rounded-md mt-1 shadow-lg max-h-80 overflow-y-auto z-10">
                <a href="?page=sale_add" class="<?php echo ($current_page == 'sale_add') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <img src="../image/box.png" alt="ไอคอนหน้าการขายสินค้า" class="w-6 h-auto">
                    <span>หน้าการขายสินค้า</span>
                </a>
                <a href="?page=sale_list" class="<?php echo ($current_page == 'sale_list') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <img src="../image/add-product.png" alt="ไอคอนรายการขายสินค้า" class="w-6 h-auto">
                    <span>รายการขายสินค้า</span>
                </a>
                <a href="?page=sale_output" class="<?php echo ($current_page == 'sale_output') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <img src="../image/box.png" alt="ไอคอนแสดงรายการขาย(ลูกค้า)" class="w-6 h-auto">
                    <span>แสดงรายการขาย(ลูกค้า)</span>
                </a>
                <a href="?page=sale_inserttest" class="<?php echo ($current_page == 'sale_inserttest') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <img src="../image/box.png" alt="ไอคอนหน้าการขายสินค้า(ทดลอง4)" class="w-6 h-auto">
                    <span>หน้าการขายสินค้า(ทดลอง4)</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            const btn = dropdown.previousElementSibling;
            const isOpen = !dropdown.classList.contains('block');

            // Close other dropdowns
            const allDropdowns = document.querySelectorAll('.dropdown-content');
            allDropdowns.forEach((other) => {
                if (other !== dropdown) {
                    other.classList.add('hidden');
                    other.classList.remove('block');
                    other.previousElementSibling.setAttribute('aria-expanded', 'false');
                }
            });

            // Toggle current dropdown
            if (isOpen) {
                dropdown.classList.remove('hidden');
                dropdown.classList.add('block');
                btn.setAttribute('aria-expanded', 'true');
            } else {
                dropdown.classList.add('hidden');
                dropdown.classList.remove('block');
                btn.setAttribute('aria-expanded', 'false');
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.dropdown-btn') && !event.target.closest('.dropdown-content')) {
                const dropdowns = document.querySelectorAll('.dropdown-content');
                dropdowns.forEach((dropdown) => {
                    dropdown.classList.add('hidden');
                    dropdown.classList.remove('block');
                    dropdown.previousElementSibling.setAttribute('aria-expanded', 'false');
                });
            }
        });

        // Close dropdowns on Escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                const dropdowns = document.querySelectorAll('.dropdown-content');
                dropdowns.forEach((dropdown) => {
                    dropdown.classList.add('hidden');
                    dropdown.classList.remove('block');
                    dropdown.previousElementSibling.setAttribute('aria-expanded', 'false');
                });
            }
        });
    </script>
</body>

</html>