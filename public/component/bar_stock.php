<?php
ob_start();
require_once __DIR__ . '../../../config/database_server.php';

// Get the current page from URL
$current_page = isset($_GET['page']) ? $_GET['page'] : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <title>Document</title>
</head>

<body>
   <nav class="button-bb hidden lg:flex flex-wrap gap-2 p-2 bg-[#d5e1f1] border-b-8 border-[#96aac4] shadow-md" role="navigation">
        <!-- Member Section -->
        <div class="section-container bg-gradient-to-r from-[#cbdbee] to-[#d5e1f1] border-2 border-[#a6c4de] rounded-lg shadow-lg">
            <!-- <div class="section-header bg-[#c2d9f1] text-center font-bold text-sm py-2 border-b border-[#a6c4de] rounded-t-md">
                เช็คสต๊อก
            </div> -->
            <div class="button-grid flex flex-wrap gap-2 p-1">
                <a href="?page=stock_insert" class="button-link w-24 lg:w-28" aria-current="<?php echo ($current_page == 'stock_insert') ? 'page' : 'false'; ?>">
                    <button class="<?php echo ($current_page == 'stock_insert') ? 'bg-gradient-to-t from-[#fffad1] to-[#fdbb2d] border-[#ffc107] shadow-inner' : 'bg-[#e1f5fe] border-[#4fc3f7]'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-[#fffad1] hover:to-[#fdbb2d] hover:border-[#ffc107] hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-[#fffad1] focus:to-[#fdbb2d] focus:border-[#ffc107] focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="รายชื่อสมาชิก">
                        <img src="../image/team.png" alt="ไอคอนรายชื่อสมาชิก" class="w-8 h-auto mb-1">
                        <span>เช็คสต๊อกสินค้า</span>
                    </button>
                </a>
                <a href="?page=stock_list" class="button-link w-24 lg:w-28" aria-current="<?php echo ($current_page == 'stock_list') ? 'page' : 'false'; ?>">
                    <button class="<?php echo ($current_page == 'stock_list') ? 'bg-gradient-to-t from-[#fffad1] to-[#fdbb2d] border-[#ffc107] shadow-inner' : 'bg-[#e1f5fe] border-[#4fc3f7]'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-[#fffad1] hover:to-[#fdbb2d] hover:border-[#ffc107] hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-[#fffad1] focus:to-[#fdbb2d] focus:border-[#ffc107] focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="เพิ่มสมาชิก">
                        <img src="../image/add.png" alt="ไอคอนเพิ่มสมาชิก" class="w-8 h-auto mb-1">
                        <span>ประวัติรายการตรวจสอบสินค้า</span>
                    </button>
                </a>
              
            </div>
        </div>
    </nav>
    <!-- Dropdown for Mobile and Smaller Tablets (below 1024px) -->
    <nav class="dropdown-container lg:hidden p-2" role="navigation">
        <!-- Member Dropdown -->
        <div class="dropdown-section mb-2">
            <button
                class="dropdown-btn w-full bg-[#e1f5fe] border border-[#4fc3f7] py-3 px-4 font-bold text-sm text-gray-800 rounded-md shadow-sm flex justify-between items-center hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] focus:ring-2 focus:ring-blue-600 transition-all duration-200"
                onclick="toggleDropdown('memberDropdown')" aria-expanded="false" aria-controls="memberDropdown">
                เช็คสต๊อก <span>▼</span>
            </button>
            <div id="memberDropdown"
                class="dropdown-content hidden bg-[#f1f9fe] border border-[#a6c4de] rounded-md mt-1 shadow-lg max-h-80 overflow-y-auto">
                <a href="?page=stock_insert"
                    class="<?php echo ($current_page == 'stock_insert') ? 'bg-[#b3e5fc] font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0"
                    aria-current="<?php echo ($current_page == 'stock_insert') ? 'page' : 'false'; ?>">
                    <img src="../image/team.png" alt="ไอคอนรายชื่อสมาชิก" class="w-6 h-auto">
                    <span>เช็คสต๊อกสินค้า</span>
                </a>
                <a href="?page=stock_list"
                    class="<?php echo ($current_page == 'stock_list') ? 'bg-[#b3e5fc] font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-[#d5e1f1] hover:bg-[#d0f0ff] focus:bg-[#d0f0ff] text-sm last:border-b-0"
                    aria-current="<?php echo ($current_page == 'stock_list') ? 'page' : 'false'; ?>">
                    <img src="../image/add.png" alt="ไอคอนเพิ่มสมาชิก" class="w-6 h-auto">
                    <span>ประวัติรายการตรวจสอบสินค้า</span>
                </a>
           
            </div>
        </div>
    </nav>
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