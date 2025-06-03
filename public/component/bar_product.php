<?php
ob_start();
require_once __DIR__ . '/../../config/database_server.php';

// Get the current page from URL
$current_page = isset($_GET['page']) ? $_GET['page'] : 'product_list';
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Product Navigation</title>
    <style>
        .section-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
        }

        .button-link {
            position: relative;
            overflow: hidden;
        }

        .button-link button {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e0f2fe;
            transition: all 0.3s ease;
        }

        .button-link button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .button-link button:hover::before {
            left: 100%;
        }

        .button-link button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .button-link button.active {
            background: linear-gradient(135deg, #38bdf8 0%, #0ea5e9 100%);
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.4);
            animation: pulse 1s;
            color: white;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(14, 165, 233, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0);
            }
        }

        .dropdown-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dropdown-content a {
            transition: all 0.3s ease;
        }

        .dropdown-content a:hover,
        .dropdown-content a.active {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            transform: translateX(5px);
        }

        .dropdown-content::-webkit-scrollbar {
            width: 4px;
        }

        .dropdown-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .dropdown-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 2px;
        }

        .dropdown-btn {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e0f2fe;
        }

        .dropdown-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body class="min-h-screen font-sans text-gray-800">
    <!-- Button Bar for Desktop/Tablet (1024px and above) -->
    <div class="button-bb hidden lg:flex flex-wrap gap-4 p-4 ">
        <!-- Product Section -->
        <div class="section-container rounded-lg overflow-hidden">
            <div class="section-header text-center font-bold text-sm py-2">
                สินค้า
            </div>
            <div class="button-grid flex flex-wrap gap-2 p-4">
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
        <div class="section-container rounded-lg overflow-hidden">
            <div class="section-header text-center font-bold text-sm py-2">
                คลังสินค้า
            </div>
            <div class="button-grid flex flex-wrap gap-2 p-4">
                <a href="?page=warehouse_list" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'warehouse_list') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="คลัง">
                        <i class="fas fa-warehouse text-xl mb-1"></i>
                        <span>คลัง</span>
                    </button>
                </a>
                <a href="?page=lot_listinsert" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'lot_listinsert') ? 'active' : ''; ?> w-full h-20 flex flex-col items-center justify-center rounded-md transition-all duration-200 text-xs font-bold" aria-label="รับสินค้าใหม่">
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
    <div class="dropdown-container lg:hidden p-4">
        <!-- Products Dropdown -->
        <div class="dropdown-section mb-4">
            <button class="dropdown-btn w-full py-3 px-4 font-bold text-sm rounded-md shadow-sm flex justify-between items-center transition-all duration-200" onclick="toggleDropdown('productsDropdown')" aria-expanded="false" aria-controls="productsDropdown">
                สินค้า <i class="fas fa-chevron-down"></i>
            </button>
            <div id="productsDropdown" class="dropdown-content hidden rounded-md mt-1 shadow-lg max-h-80 overflow-y-auto">
                <a href="?page=product_list" class="<?php echo ($current_page == 'product_list') ? 'active' : ''; ?> flex items-center gap-2 p-3 border-b border-gray-200 last:border-b-0 text-sm">
                    <i class="fas fa-box text-blue-600"></i>
                    <span>รายการสินค้า</span>
                </a>
                <a href="?page=product_insert" class="<?php echo ($current_page == 'product_insert') ? 'active' : ''; ?> flex items-center gap-2 p-3 border-b border-gray-200 last:border-b-0 text-sm">
                    <i class="fas fa-plus-circle text-blue-600"></i>
                    <span>เพิ่มสินค้า</span>
                </a>
                <a href="?page=product_type" class="<?php echo ($current_page == 'product_type') ? 'active' : ''; ?> flex items-center gap-2 p-3 border-b border-gray-200 last:border-b-0 text-sm">
                    <i class="fas fa-tags text-blue-600"></i>
                    <span>ประเภทสินค้า</span>
                </a>
                <a href="?page=product_brand" class="<?php echo ($current_page == 'product_brand') ? 'active' : ''; ?> flex items-center gap-2 p-3 border-b border-gray-200 last:border-b-0 text-sm">
                    <i class="fas fa-copyright text-blue-600"></i>
                    <span>แบรนด์</span>
                </a>
            </div>
        </div>

        <!-- Warehouse Dropdown -->
        <div class="dropdown-section mb-4">
            <button class="dropdown-btn w-full py-3 px-4 font-bold text-sm rounded-md shadow-sm flex justify-between items-center transition-all duration-200" onclick="toggleDropdown('warehouseDropdown')" aria-expanded="false" aria-controls="warehouseDropdown">
                คลังสินค้า <i class="fas fa-chevron-down"></i>
            </button>
            <div id="warehouseDropdown" class="dropdown-content hidden rounded-md mt-1 shadow-lg max-h-80 overflow-y-auto">
                <a href="?page=warehouse_list" class="<?php echo ($current_page == 'warehouse_list') ? 'active' : ''; ?> flex items-center gap-2 p-3 border-b border-gray-200 last:border-b-0 text-sm">
                    <i class="fas fa-warehouse text-blue-600"></i>
                    <span>คลัง</span>
                </a>
                <a href="?page=lot_listinsert" class="<?php echo ($current_page == 'lot_listinsert') ? 'active' : ''; ?> flex items-center gap-2 p-3 border-b border-gray-200 last:border-b-0 text-sm">
                    <i class="fas fa-truck-loading text-blue-600"></i>
                    <span>รับสินค้าใหม่</span>
                </a>
                <a href="?page=lot_list" class="<?php echo ($current_page == 'lot_list') ? 'active' : ''; ?> flex items-center gap-2 p-3 border-b border-gray-200 last:border-b-0 text-sm">
                    <i class="fas fa-list text-blue-600"></i>
                    <span>รายการรับสินค้า</span>
                </a>
                <a href="?page=lot_insertout" class="<?php echo ($current_page == 'lot_insertout') ? 'active' : ''; ?> flex items-center gap-2 p-3 border-b border-gray-200 last:border-b-0 text-sm">
                    <i class="fas fa-dolly text-blue-600"></i>
                    <span>ย้ายสินค้าในคลัง</span>
                </a>
                <a href="?page=lot_warehouseinout" class="<?php echo ($current_page == 'lot_warehouseinout') ? 'active' : ''; ?> flex items-center gap-2 p-3 border-b border-gray-200 last:border-b-0 text-sm">
                    <i class="fas fa-exchange-alt text-blue-600"></i>
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