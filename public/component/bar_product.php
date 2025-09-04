<?php
ob_start();
require_once __DIR__ . '/../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

// Get the current page from URL
$current_page = isset($_GET['page']) ? $_GET['page'] : 'product_list';

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="../../output.css" rel="stylesheet">
    <link href="../style/bar_button.css" rel="stylesheet">
    <title>Product Navigation</title>
</head>

<body class="text-gray-800 min-h-screen overflow-x-hidden font-sans">
    <!-- Button Bar for Desktop/Tablet (1024px and above) -->
    <div class="button-bb hidden lg:flex flex-wrap gap-2 p-2 px-4" role="navigation">

        <!-- Product Section -->
        <div class="section-container border rounded-lg shadow-md">
            <!-- <div class="text-center text-white bg-[#1e40af] border rounded-tr-md rounded-tl-md  ">
                <h1>สินค้า</h1>
            </div> -->
            <div class="button-grid flex flex-wrap gap-2 p-1">
                <a href="?page=product_list" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'product_list') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="รายการสินค้า">
                        <i class="fas fa-box text-xl mb-1"></i>
                        <span>รายการสินค้า</span>
                    </button>
                </a>
                <a href="?page=product_insert" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'product_insert') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="เพิ่มสินค้า">
                        <i class="fas fa-plus-circle text-xl mb-1"></i>
                        <span>เพิ่มสินค้า</span>
                    </button>
                </a>
                <a href="?page=product_type" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'product_type') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="ประเภทสินค้า">
                        <i class="fas fa-tags text-xl mb-1"></i>
                        <span>ประเภทสินค้า</span>
                    </button>
                </a>
                <a href="?page=product_brand" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'product_brand') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="แบรนด์">
                        <i class="fas fa-copyright text-xl mb-1"></i>
                        <span>แบรนด์</span>
                    </button>
                </a>
            </div>
        </div>

        <!-- Warehouse Section -->
        <div class="section-container border border-gray-300 rounded-md shadow-md">
            <!-- <div class="text-center text-white bg-[#1e40af] border rounded-tr-md rounded-tl-md  ">
                <h1>คลังสินค้า</h1>
            </div> -->
            <div class="button-grid flex flex-wrap gap-2 p-2">
                <a href="?page=warehouse_list" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'warehouse_list') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="คลัง">
                        <i class="fas fa-warehouse text-xl mb-1"></i>
                        <span>คลัง</span>
                    </button>
                </a>
                <a href="?page=lot_insert" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'lot_insert') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="รับสินค้าใหม่">
                        <i class="fas fa-truck-loading text-xl mb-1"></i>
                        <span>รับสินค้าใหม่</span>
                    </button>
                </a>
                <a href="?page=lot_list" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'lot_list') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="รายการรับสินค้า">
                        <i class="fas fa-list text-xl mb-1"></i>
                        <span>รายการรับสินค้า</span>
                    </button>
                </a>
                <a href="?page=lot_insertout" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'lot_insertout') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="ย้ายสินค้าในคลัง">
                        <i class="fas fa-dolly text-xl mb-1"></i>
                        <span>ย้ายสินค้าในคลัง</span>
                    </button>
                </a>
                <a href="?page=lot_warehouseinout" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'lot_warehouseinout') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="รายการย้ายคลัง">
                        <i class="fas fa-exchange-alt text-xl mb-1"></i>
                        <span>รายการย้ายคลัง</span>
                    </button>
                </a>
            </div>
        </div>
    </div>

    <!-- Dropdown for Mobile and Smaller Tablets (below 1024px) -->
    <div class="dropdown-container lg:hidden p-2" role="navigation">
        <!-- Products Dropdown -->
        <div class="dropdown-section mb-2">
            <button class="dropdown-btn w-full py-3 px-4 font-bold text-sm text-white rounded-md shadow-sm flex justify-between items-center focus:ring-2 focus:ring-blue-600 transition-all duration-200" onclick="toggleDropdown('productsDropdown')" aria-expanded="false" aria-controls="productsDropdown">
                สินค้า <span>▼</span>
            </button>
            <div id="productsDropdown" class="dropdown-content hidden bg-[#f1f9fe] border border-[#a6c4de] rounded-md mt-1 shadow-lg max-h-80 overflow-y-auto z-10">
                <a href="?page=product_list" class="<?php echo ($current_page == 'product_list') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <i class="fas fa-box text-xl mb-1"></i>
                    <span>รายการสินค้า</span>
                </a>
                <a href="?page=product_insert" class="<?php echo ($current_page == 'product_insert') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <i class="fas fa-plus-circle text-xl mb-1"></i>
                    <span>เพิ่มสินค้า</span>
                </a>
                <a href="?page=product_type" class="<?php echo ($current_page == 'product_type') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <i class="fas fa-tags text-xl mb-1"></i>
                    <span>ประเภทสินค้า</span>
                </a>
                <a href="?page=product_brand" class="<?php echo ($current_page == 'product_brand') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <i class="fas fa-copyright text-xl mb-1"></i>
                    <span>แบรนด์</span>
                </a>
            </div>
        </div>
        <!-- Warehouse Dropdown -->
        <div class="dropdown-section mb-2">
            <button class="dropdown-btn w-full py-3 px-4 font-bold text-sm text-white rounded-md shadow-sm flex justify-between items-center focus:ring-2 focus:ring-blue-600 transition-all duration-200" onclick="toggleDropdown('warehouseDropdown')" aria-expanded="false" aria-controls="warehouseDropdown">
                คลังสินค้า <span>▼</span>
            </button>
            <div id="warehouseDropdown" class="dropdown-content hidden bg-[#f1f9fe] border border-[#a6c4de] rounded-md mt-1 shadow-lg max-h-80 overflow-y-auto z-10">
                <a href="?page=warehouse_list" class="<?php echo ($current_page == 'warehouse_list') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <i class="fas fa-warehouse text-xl mb-1"></i>
                    <span>คลัง</span>
                </a>
                <a href="?page=lot_insert" class="<?php echo ($current_page == 'lot_insert') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <i class="fas fa-truck-loading text-xl mb-1"></i>
                    <span>รับสินค้าใหม่</span>
                </a>
                <a href="?page=lot_list" class="<?php echo ($current_page == 'lot_list') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <i class="fas fa-list text-xl mb-1"></i>
                    <span>รายการรับสินค้า</span>
                </a>
                <a href="?page=lot_insertout" class="<?php echo ($current_page == 'lot_insertout') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <i class="fas fa-dolly text-xl mb-1"></i>
                    <span>ย้ายสินค้าในคลัง</span>
                </a>
                <a href="?page=lot_warehouseinout" class="<?php echo ($current_page == 'lot_warehouseinout') ? 'active' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0">
                    <i class="fas fa-exchange-alt text-xl mb-1"></i>
                    <span>รายการย้ายคลัง</span>
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