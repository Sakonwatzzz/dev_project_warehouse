<?php
ob_start();
require_once __DIR__ . '../../../config/database_server.php';

// Get the current page from URL
$current_page = isset($_GET['page']) ? $_GET['page'] : 'product_list';
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'body-bg': '#e3f2fd',
                        'button-bar-bg': '#d5e1f1',
                        'button-bar-border': '#96aac4',
                        'section-gradient-start': '#cbdbee',
                        'section-gradient-end': '#d5e1f1',
                        'section-border': '#a6c4de',
                        'section-header-bg': '#c2d9f1',
                        'section-header-border': '#a6c4de',
                        'button-bg': '#e1f5fe',
                        'button-border': '#4fc3f7',
                        'button-hover-start': '#fffad1',
                        'button-hover-end': '#fdbb2d',
                        'button-hover-border': '#ffc107',
                        'dropdown-btn-bg': '#e1f5fe',
                        'dropdown-content-bg': '#f1f9fe',
                        'dropdown-border': '#a6c4de',
                        'dropdown-hover-bg': '#d0f0ff',
                        'dropdown-active-bg': '#b3e5fc',
                        'dropdown-divider': '#d5e1f1',
                    }
                }
            }
        }
    </script>
    <title>Product Navigation</title>
</head>

<body class="bg-body-bg text-gray-800 min-h-screen overflow-x-hidden font-sans">
    <!-- Button Bar for Desktop/Tablet (1024px and above) -->
    <div class="button-bb hidden lg:flex flex-wrap gap-2 p-2 bg-button-bar-bg border-b-8 border-button-bar-border shadow-md">
        
        <!-- Product Section -->
        <div class="section-container bg-gradient-to-r from-section-gradient-start to-section-gradient-end border-2 border-section-border rounded-lg shadow-lg">
            <div class="section-header bg-section-header-bg text-center font-bold text-sm py-2 border-b border-section-header-border rounded-t-md">
                สินค้า
            </div>
            <div class="button-grid flex flex-wrap gap-2 p-2">
                <a href="?page=product_list" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'product_list') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="รายการสินค้า">
                        <img src="../image/box.png" alt="ไอคอนรายการสินค้า" class="w-8 h-auto mb-1">
                        <span>รายการสินค้า</span>
                    </button>
                </a>
                <a href="?page=product_insert" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'product_insert') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="เพิ่มสินค้า">
                        <img src="../image/add-product.png" alt="ไอคอนเพิ่มสินค้า" class="w-8 h-auto mb-1">
                        <span>เพิ่มสินค้า</span>
                    </button>
                </a>
                <a href="?page=product_type" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'product_type') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="ประเภทสินค้า">
                        <img src="../image/box.png" alt="ไอคอนประเภทสินค้า" class="w-8 h-auto mb-1">
                        <span>ประเภทสินค้า</span>
                    </button>
                </a>
                <a href="?page=product_brand" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'product_brand') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="แบรนด์">
                        <img src="../image/box.png" alt="ไอคอนแบรนด์" class="w-8 h-auto mb-1">
                        <span>แบรนด์</span>
                    </button>
                </a>
            </div>
        </div>

        <!-- Warehouse Section -->
        <div class="section-container bg-gradient-to-r from-section-gradient-start to-section-gradient-end border-2 border-section-border rounded-lg shadow-lg">
            <div class="section-header bg-section-header-bg text-center font-bold text-sm py-2 border-b border-section-header-border rounded-t-md">
                คลังสินค้า
            </div>
            <div class="button-grid flex flex-wrap gap-2 p-2">
                <a href="?page=warehouse_list" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'warehouse_list') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="คลัง">
                        <img src="../image/warehouse.png" alt="ไอคอนคลัง" class="w-8 h-auto mb-1">
                        <span>คลัง</span>
                    </button>
                </a>
                <a href="?page=lot_listinsert" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'lot_listinsert') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="รับสินค้าใหม่">
                        <img src="../image/warehouse.png" alt="ไอคอนรับสินค้าใหม่" class="w-8 h-auto mb-1">
                        <span>รับสินค้าใหม่</span>
                    </button>
                </a>
                <a href="?page=lot_list" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'lot_list') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="รายการรับสินค้า">
                        <img src="../image/warehouse.png" alt="ไอคอนรายการรับสินค้า" class="w-8 h-auto mb-1">
                        <span>รายการรับสินค้า</span>
                    </button>
                </a>
                <a href="?page=lot_insertout" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'lot_insertout') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="ย้ายสินค้าในคลัง">
                        <img src="../image/warehouse.png" alt="ไอคอนย้ายสินค้าในคลัง" class="w-8 h-auto mb-1">
                        <span>ย้ายสินค้าในคลัง</span>
                    </button>
                </a>
                <a href="?page=lot_warehouseinout" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'lot_warehouseinout') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="รายการย้ายคลัง">
                        <img src="../image/warehouse.png" alt="ไอคอนรายการย้ายคลัง" class="w-8 h-auto mb-1">
                        <span>รายการย้ายคลัง</span>
                    </button>
                </a>
            </div>
        </div>
    </div>

    <!-- Dropdown for Mobile and Smaller Tablets (below 1024px) -->
    <div class="dropdown-container lg:hidden p-2">
        <!-- Products Dropdown -->
        <div class="dropdown-section mb-2">
            <button class="dropdown-btn w-full bg-dropdown-btn-bg border border-button-border py-3 px-4 font-bold text-sm text-gray-800 rounded-md shadow-sm flex justify-between items-center hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg focus:ring-2 focus:ring-blue-600 transition-all duration-200" onclick="toggleDropdown('productsDropdown')" aria-expanded="false" aria-controls="productsDropdown">
                สินค้า <span>▼</span>
            </button>
            <div id="productsDropdown" class="dropdown-content hidden bg-dropdown-content-bg border border-dropdown-border rounded-md mt-1 shadow-lg max-h-80 overflow-y-auto">
                <a href="?page=product_list" class="<?php echo ($current_page == 'product_list') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg agrippa text-sm last:border-b-0">
                    <img src="../image/box.png" alt="ไอคอนรายการสินค้า" class="w-6 h-auto">
                    <span>รายการสินค้า</span>
                </a>
                <a href="?page=product_insert" class="<?php echo ($current_page == 'product_insert') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/add-product.png" alt="ไอคอนเพิ่มสินค้า" class="w-6 h-auto">
                    <span>เพิ่มสินค้า</span>
                </a>
                <a href="?page=product_type" class="<?php echo ($current_page == 'product_type') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/box.png" alt="ไอคอนประเภทสินค้า" class="w-6 h-auto">
                    <span>ประเภทสินค้า</span>
                </a>
                <a href="?page=product_brand" class="<?php echo ($current_page == 'product_brand') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/box.png" alt="ไอคอนแบรนด์" class="w-6 h-auto">
                    <span>แบรนด์</span>
                </a>
            </div>
        </div>

        <!-- Warehouse Dropdown -->
        <div class="dropdown-section mb-2">
            <button class="dropdown-btn w-full bg-dropdown-btn-bg border border-button-border py-3 px-4 font-bold text-sm text-gray-800 rounded-md shadow-sm flex justify-between items-center hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg focus:ring-2 focus:ring-blue-600 transition-all duration-200" onclick="toggleDropdown('warehouseDropdown')" aria-expanded="false" aria-controls="warehouseDropdown">
                คลังสินค้า <span>▼</span>
            </button>
            <div id="warehouseDropdown" class="dropdown-content hidden bg-dropdown-content-bg border border-dropdown-border rounded-md mt-1 shadow-lg max-h-80 overflow-y-auto">
                <a href="?page=warehouse_list" class="<?php echo ($current_page == 'warehouse_list') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/warehouse.png" alt="ไอคอนคลัง" class="w-6 h-auto">
                    <span>คลัง</span>
                </a>
                <a href="?page=lot_listinsert" class="<?php echo ($current_page == 'lot_listinsert') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/warehouse.png" alt="ไอคอนรับสินค้าใหม่" class="w-6 h-auto">
                    <span>รับสินค้าใหม่</span>
                </a>
                <a href="?page=lot_list" class="<?php echo ($current_page == 'lot_list') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/warehouse.png" alt="ไอคอนรายการรับสินค้า" class="w-6 h-auto">
                    <span>รายการรับสินค้า</span>
                </a>
                <a href="?page=lot_insertout" class="<?php echo ($current_page == 'lot_insertout') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/warehouse.png" alt="ไอคอนย้ายสินค้าในคลัง" class="w-6 h-auto">
                    <span>ย้ายสินค้าในคลัง</span>
                </a>
                <a href="?page=lot_warehouseinout" class="<?php echo ($current_page == 'lot_warehouseinout') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-smlice last:border-b-0">
                    <img src="../image/warehouse.png" alt="ไอคอนรายการย้ายคลัง" class="w-6 h-auto">
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