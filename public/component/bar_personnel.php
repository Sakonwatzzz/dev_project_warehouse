<?php
ob_start();
require_once __DIR__ . '../../../config/database_server.php';
// Get the current page from URL
$current_page = isset($_GET['page']) ? $_GET['page'] : 'personnel_list';
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
    <title>Personnel Navigation</title>
</head>

<body class="bg-body-bg text-gray-800 min-h-screen overflow-x-hidden font-sans">
    <!-- Button Bar for Desktop/Tablet (1024px and above) -->
    <div class="button-bb hidden lg:flex flex-wrap gap-2 p-2 bg-button-bar-bg border-b-8 border-button-bar-border shadow-md">
        <!-- Personnel Section -->
        <div class="section-container bg-gradient-to-r from-section-gradient-start to-section-gradient-end border-2 border-section-border rounded-lg shadow-lg">
            <div class="button-grid flex flex-wrap gap-2 p-2">
                <a href="?page=personnel_list" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'personnel_list') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-End hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="รายชื่อบุคลากร">
                        <img src="../image/team.png" alt="ไอคอนรายชื่อบุคลากร" class="w-8 h-auto mb-1">
                        <span>รายชื่อบุคลากร</span>
                    </button>
                </a>
                <a href="?page=personnel_insert" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'personnel_insert') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="เพิ่มบุคลากร">
                        <img src="../image/add.png" alt="ไอคอนเพิ่มบุคลากร" class="w-8 h-auto mb-1">
                        <span>เพิ่มบุคลากร</span>
                    </button>
                </a>
                <a href="?page=personnel_type" class="button-link w-24 lg:w-28">
                    <button class="<?php echo ($current_page == 'personnel_type') ? 'bg-gradient-to-t from-button-hover-start to-button-hover-end border-button-hover-border shadow-inner' : 'bg-button-bg border-button-border'; ?> w-full h-20 flex flex-col items-center justify-center border rounded-md transition-all duration-200 hover:bg-gradient-to-t hover:from-button-hover-start hover:to-button-hover-end hover:border-button-hover-border hover:-translate-y-0.5 focus:bg-gradient-to-t focus:from-button-hover-start focus:to-button-hover-end focus:border-button-hover-border focus:ring-2 focus:ring-blue-600 shadow-sm text-xs font-bold text-gray-800" aria-label="ประเภทบุคลากร">
                        <img src="../image/customer.png" alt="ไอคอนประเภทบุคลากร" class="w-8 h-auto mb-1">
                        <span>ประเภทบุคลากร</span>
                    </button>
                </a>
            </div>
        </div>
    </div>

    <!-- Dropdown for Mobile and Smaller Tablets (below 1024px) -->
    <div class="dropdown-container lg:hidden p-2">
        <!-- Personnel Dropdown -->
        <div class="dropdown-section mb-2">
            <button class="dropdown-btn w-full bg-dropdown-btn-bg border border-button-border py-3 px-4 font-bold text-sm text-gray-800 rounded-md shadow-sm flex justify-between items-center hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg focus:ring-2 focus:ring-blue-600 transition-all duration-200" onclick="toggleDropdown('personnelDropdown')" aria-expanded="false" aria-controls="personnelDropdown">
                บุคลากร <span>▼</span>
            </button>
            <div id="personnelDropdown" class="dropdown-content hidden bg-dropdown-content-bg border border-dropdown-border rounded-md mt-1 shadow-lg max-h-80 overflow-y-auto">
                <a href="?page=personnel_list" class="<?php echo ($current_page == 'personnel_list') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/team.png" alt="ไอคอนรายชื่อบุคลากร" class="w-6 h-auto">
                    <span>รายชื่อบุคลากร</span>
                </a>
                <a href="?page=personnel_insert" class="<?php echo ($current_page == 'personnel_insert') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/add.png" alt="ไอคอนเพิ่มบุคลากร" class="w-6 h-auto">
                    <span>เพิ่มบุคลากร</span>
                </a>
                <a href="?page=personnel_type" class="<?php echo ($current_page == 'personnel_type') ? 'bg-dropdown-active-bg font-bold' : ''; ?> flex items-center gap-2 p-3 text-gray-800 border-b border-dropdown-divider hover:bg-dropdown-hover-bg focus:bg-dropdown-hover-bg text-sm last:border-b-0">
                    <img src="../image/customer.png" alt="ไอคอนประเภทบุคลากร" class="w-6 h-auto">
                    <span>ประเภทบุคลากร</span>
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