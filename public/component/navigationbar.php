<?php
ob_start();
session_start();

// สร้าง CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function helper
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

// Add to top of file to get current page and its parts
$current_page = $_GET['page'] ?? 'dashboard'; // Default to dashboard when no page is specified
$page_parts = explode('_', $current_page);
$main_section = $page_parts[0] ?? '';
$sub_section = $page_parts[1] ?? '';

// Function to check if we're in a specific section
function isInSection($current_page, $section_pages)
{
    return in_array($current_page, $section_pages);
}

// Check if we're in dashboard section (for highlighting both home and dashboard)
$is_dashboard = ($current_page == 'dashboard' || $current_page == 'bar_index');

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="../../output.css" rel="stylesheet">
    <link href="../style/navbar.css" rel="stylesheet">
    <title>Admin System</title>

</head>

<body class="min-h-screen font-sans text-gray-800">
    <!-- Header -->
    <div class="glass-header top-0 z-50 px-4 py-4 shadow-lg">
        <div class="flex items-center justify-between">
            <h1 class="text-white text-xl lg:text-[24px] font-semibold tracking-wide drop-shadow-md">
                Admin Management System
            </h1>

            <!-- Hamburger Menu for Mobile -->
            <button class="lg:hidden bg-white bg-opacity-20 backdrop-blur-md border border-white border-opacity-30 text-white p-3 rounded-xl hover:bg-opacity-30 hover:scale-105 transition-all duration-300"
                aria-expanded="false"
                aria-controls="sidebar"
                onclick="toggleSidebar()">
                <i class="fas fa-bars text-lg"></i>
            </button>
        </div>

        <!-- Desktop Navbar -->
        <nav class="hidden lg:block mt-4">
            <ul class="flex flex-wrap gap-2">
                <li>
                    <a href="?page=bar_index" class="nav-link <?php echo ($main_section == 'dashboard' || $current_page == 'bar_index') ? 'active' : ''; ?> text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-users"></i> หน้าแรก
                    </a>
                </li>
                <li>
                    <a href="?page=bar_product" class="nav-link <?php echo ($main_section == 'product' || $main_section == 'lot' || $main_section == 'warehouse' || $current_page == 'bar_product') ? 'active' : ''; ?> text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-box"></i> สินค้า
                    </a>
                </li>
                <li>
                    <a href="?page=bar_personnel" class="nav-link <?php echo ($main_section == 'personnel' || $current_page == 'bar_personnel') ? 'active' : ''; ?> text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-users"></i> บุคลากร
                    </a>
                </li>
                <li>
                    <a href="?page=bar_member" class="nav-link <?php echo ($main_section == 'member' || $current_page == 'bar_member') ? 'active' : ''; ?> text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-user-friends"></i> สมาชิก
                    </a>
                </li>
                <li>
                    <a href="?page=bar_supplier" class="nav-link <?php echo ($main_section == 'supplier' || $current_page == 'bar_supplier') ? 'active' : ''; ?> text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-truck"></i> Supplier
                    </a>
                </li>
                <li>
                    <a href="?page=bar_store" class="nav-link <?php echo ($main_section == 'store' || $current_page == 'bar_store') ? 'active' : ''; ?> text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-store"></i> ร้านค้า
                    </a>
                </li>
                <li>
                    <a href="?page=bar_sale" class="nav-link <?php echo ($main_section == 'sale' || $current_page == 'bar_sale') ? 'active' : ''; ?> text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-cash-register"></i> ขาย
                    </a>
                </li>
                <li>
                    <a href="?page=bar_stock" class="nav-link <?php echo ($main_section == 'stock' || $current_page == 'bar_stock') ? 'active' : ''; ?> text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-warehouse"></i> เช็คสต๊อก
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Enhanced Sidebar for Mobile -->
    <div class="sidebar fixed top-0 left-0 w-80 h-full z-50 overflow-y-auto" id="sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header p-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-cog text-white text-lg"></i>
                </div>
                <h2 class="text-white text-lg font-semibold">Admin Panel</h2>
            </div>
            <button class="text-white bg-white bg-opacity-20 p-2 rounded-lg hover:bg-opacity-30 hover:rotate-90 transition-all duration-300"
                onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Enhanced Navigation Menu -->
        <div class="px-4 pb-6 ">
            <div class="space-y-2 pt-4  ">
                <!-- Dashboard -->
                <a href="?page=bar_index" class="sidebar-item  <?php echo $is_dashboard ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 rounded-xl text-gray-700 font-medium ">
                    <div class="sidebar-icon w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-blue-600"></i>
                    </div>
                    <div>
                        <div class="font-semibold">หน้าแรก</div>
                        <div class="text-xs text-gray-500">Dashboard</div>
                    </div>
                </a>

                <!-- Products -->
                <a href="?page=bar_product" class="sidebar-item <?php echo ($main_section == 'product' || $main_section == 'lot' || $main_section == 'warehouse' || $current_page == 'bar_product') ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 rounded-xl text-gray-700 font-medium">
                    <div class="sidebar-icon w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-box text-blue-600"></i>
                    </div>
                    <div>
                        <div class="font-semibold">สินค้า</div>
                        <div class="text-xs text-gray-500">Products Management</div>
                    </div>
                </a>

                <!-- Personnel -->
                <a href="?page=bar_personnel" class="sidebar-item <?php echo ($main_section == 'personnel' || $current_page == 'bar_personnel') ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 rounded-xl text-gray-700 font-medium">
                    <div class="sidebar-icon w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                    <div>
                        <div class="font-semibold">บุคลากร</div>
                        <div class="text-xs text-gray-500">Staff Management</div>
                    </div>
                </a>

                <!-- Members -->
                <a href="?page=bar_member" class="sidebar-item <?php echo ($main_section == 'member' || $current_page == 'bar_member') ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 rounded-xl text-gray-700 font-medium">
                    <div class="sidebar-icon w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-friends text-blue-600"></i>
                    </div>
                    <div>
                        <div class="font-semibold">สมาชิก</div>
                        <div class="text-xs text-gray-500">Members Management</div>
                    </div>
                </a>

                <!-- Suppliers -->
                <a href="?page=bar_supplier" class="sidebar-item <?php echo ($main_section == 'supplier' || $current_page == 'bar_supplier') ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 rounded-xl text-gray-700 font-medium">
                    <div class="sidebar-icon w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-truck text-blue-600"></i>
                    </div>
                    <div>
                        <div class="font-semibold">Supplier</div>
                        <div class="text-xs text-gray-500">Supplier Management</div>
                    </div>
                </a>

                <!-- Store -->
                <a href="?page=bar_store" class="sidebar-item <?php echo ($main_section == 'store' || $current_page == 'bar_store') ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 rounded-xl text-gray-700 font-medium">
                    <div class="sidebar-icon w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-store text-blue-600"></i>
                    </div>
                    <div>
                        <div class="font-semibold">ร้านค้า</div>
                        <div class="text-xs text-gray-500">Store Management</div>
                    </div>
                </a>

                <!-- Sales -->
                <a href="?page=bar_sale" class="sidebar-item <?php echo ($main_section == 'sale' || $current_page == 'bar_sale') ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 rounded-xl text-gray-700 font-medium">
                    <div class="sidebar-icon w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cash-register text-blue-600"></i>
                    </div>
                    <div>
                        <div class="font-semibold">ขาย</div>
                        <div class="text-xs text-gray-500">Sales Management</div>
                    </div>
                </a>

                <!-- Stock -->
                <a href="?page=bar_stock" class="sidebar-item <?php echo ($main_section == 'stock' || $current_page == 'bar_stock') ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 rounded-xl text-gray-700 font-medium">
                    <div class="sidebar-icon w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-warehouse text-blue-600"></i>
                    </div>
                    <div>
                        <div class="font-semibold">เช็คสต๊อก</div>
                        <div class="text-xs text-gray-500">Stock Management</div>
                    </div>
                </a>
            </div>

            <!-- Sidebar Footer -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="flex items-center gap-3 px-4">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-white"></i>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-700">Admin User</div>
                        <div class="text-xs text-gray-500">Administrator</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overlay -->
    <div class="overlay fixed inset-0 bg-black bg-opacity-50 opacity-0 invisible transition-all duration-300 z-40"
        id="overlay"
        onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class=" min-h-screen">
        <div class="">
            <?php
            // Get page parameter and set defaults
            $page = $_GET['page'] ?? 'dashboard'; // Default to dashboard when no page is specified
            $allowed_pages = ['bar_index', 'bar_product', 'bar_store', 'bar_personnel', 'bar_member', 'bar_supplier', 'bar_sale', 'bar_stock'];

            $dashboard_pages = [
                'dashboard'
            ];
            $product_pages = [
                'product_list',
                'product_insert',
                'product_type',
                'product_brand',
                'product_brandupdate',
                'product_detail',
                'product_update',
                'product_type_update',
                'product_typegroup',
                'product_brandgroup'
            ];
            $warehouse_page = [
                'warehouse_list',
                'warehouse_insert',
                'warehouse_update'
            ];
            $lot_pages = [
                'lot_insert',
                'lot_list',
                'lot_insert',
                'lot_productlist',
                'lot_productlistmove',
                'lot_productinsert',
                'lot_productupdate',
                'lot_update',
                'lot_detail',
                'lot_productdetail',
                'lot_listdetail',
                'lot_listinsertcopy',
                'supplier_lot',
                'personnel_lot',
                'lot_insertout',
                'lot_listdetailout',
                'lot_warehouseinout',
                'lot_detailmove'
            ];
            $member_pages = [
                'member_list',
                'member_insert',
                'member_type',
                'member_detail',
                'member_group',
                'member_update',
                'member_type_update',
                'select_member_dropdown'
            ];
            $personnel_pages = [
                'personnel_detail',
                'personnel_group',
                'personnel_insert',
                'personnel_list',
                'personnel_type',
                'personnel_update',
                'personnel_type_update',
                'select_personnel_dropdown'
            ];
            $supplier_pages = [
                'supplier_list',
                'supplier_update',
                'supplier_insert',
                'supplier_detail',
            ];
            $store_pages = [
                'store_list',
                'store_insert',
            ];
            $sale_pages = [
                'sale_add',
                'sale_list',
                'sale_bill',
                'sale_insert',
                'sale_detail',
                'sale_output',
                'sale_inserttest'
            ];
            $stock_pages = [
                'stock_insert',
                'stock_list'
            ];

            if ($page) {
                $parts = explode('_', $page);
                $prefix = $parts[0] ?? null;
                $module = $parts[1] ?? null;

                // If dashboard or bar_index, show dashboard UI
                if ($page == 'dashboard' || $page == 'bar_index') {
            ?>

            <?php
                    $barfile = __DIR__ . "/bar_index.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    } else {
                        echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                    }
                    $filepath = __DIR__ . "/../page/dashboard/dashboard.php";
                    if (file_exists($filepath)) {
                        include $filepath;
                    } else {
                        echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($filepath) . "</div>";
                    }
                } else {
                    if (in_array($page, $allowed_pages)) {
                        $basefile = __DIR__ . "/$page.php";
                        if (file_exists($basefile)) {
                            include $basefile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($basefile) . "</div>";
                        }
                    }

                    $basePath = __DIR__ . "/../page";

                    if ($prefix === 'bar') {
                        $folderMapping = [
                            'product' => 'product',
                            'supplier' => 'supplier',
                            'personnel' => 'personnel',
                            'member' => 'member',
                            'lot' => 'lot',
                            'sale' => 'sale',
                            'stock' => 'stock',
                            'store' => 'store'
                        ];

                        if (isset($folderMapping[$module])) {
                            $folderName = $folderMapping[$module];

                            if ($module === 'product') {
                                $action = $parts[2] ?? 'list';
                                $subaction = $parts[3] ?? null;

                                $filepath = "$basePath/$folderName/";
                                if ($subaction) {
                                    $filepath .= "{$folderName}_{$action}_{$subaction}.php";
                                } else {
                                    $filepath .= "{$folderName}_{$action}.php";
                                }
                            } else {
                                $filepath = "$basePath/$folderName/{$folderName}_list.php";
                            }
                        }
                    } elseif ($prefix === 'dashboard' && in_array($page, $dashboard_pages)) {
                        $barfile = __DIR__ . "/bar_index.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? '';
                        $filepath = "$basePath/dashboard/dashboard.php";

                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    } elseif ($prefix === 'member' && in_array($page, $member_pages)) {
                        $barfile = __DIR__ . "/bar_member.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? 'list';
                        $filepath = "$basePath/member/member_{$action}.php";

                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    } elseif ($prefix === 'product' && in_array($page, $product_pages)) {
                        $barfile = __DIR__ . "/bar_product.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? 'list';
                        if ($action) {
                            $filepath = "$basePath/product/product_{$action}.php";
                        }
                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    } elseif ($prefix === 'warehouse' && in_array($page, $warehouse_page)) {
                        $barfile = __DIR__ . "/bar_product.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? 'list';
                        $filepath = "$basePath/warehouse/warehouse_{$action}.php";

                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    } elseif ($prefix === 'lot' && in_array($page, $lot_pages)) {
                        $barfile = __DIR__ . "/bar_product.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? 'list';
                        $filepath = "$basePath/lot/lot_{$action}.php";

                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    } elseif ($prefix === 'warehouse' && $page === 'warehouse_lot') {
                        $barfile = __DIR__ . "/bar_product.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $filepath = "$basePath/lot/warehouse_lot.php";

                        if (isset($_GET['warehouse_id'])) {
                            $_REQUEST['warehouse_id'] = $_GET['warehouse_id'];
                        }
                    } elseif ($prefix === 'warehouse' && $page === 'warehouse_move') {
                        $barfile = __DIR__ . "/bar_product.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $filepath = "$basePath/lot/warehouse_move.php";

                        if (isset($_GET['warehouse_id'])) {
                            $_REQUEST['warehouse_id'] = $_GET['warehouse_id'];
                        }
                    } elseif ($prefix === 'supplier' && $page === 'supplier_lot') {
                        $barfile = __DIR__ . "/bar_product.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $filepath = "$basePath/lot/supplier_lot.php";

                        if (isset($_GET['supplier_id'])) {
                            $_REQUEST['supplier_id'] = $_GET['supplier_id'];
                        }
                    } elseif ($prefix === 'personnel' && $page === 'personnel_lot') {
                        $barfile = __DIR__ . "/bar_product.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $filepath = "$basePath/lot/personnel_lot.php";

                        if (isset($_GET['personnel_id'])) {
                            $_REQUEST['personnel_id'] = $_GET['personnel_id'];
                        }
                    } elseif ($prefix === 'personnel' && in_array($page, $personnel_pages)) {
                        $barfile = __DIR__ . "/bar_personnel.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? 'list';
                        $filepath = "$basePath/personnel/personnel_{$action}.php";

                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    } elseif ($prefix === 'supplier' && in_array($page, $supplier_pages)) {
                        $barfile = __DIR__ . "/bar_supplier.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? 'list';
                        $filepath = "$basePath/supplier/supplier_{$action}.php";

                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    }
                    elseif ($prefix === 'store' && in_array($page, $store_pages)) {
                        $barfile = __DIR__ . "/bar_store.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? 'list';
                        $filepath = "$basePath/store/store_{$action}.php";

                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    } elseif ($prefix === 'sale' && in_array($page, $sale_pages)) {
                        $barfile = __DIR__ . "/bar_sale.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? 'add';
                        $filepath = "$basePath/sale/sale_{$action}.php";

                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    } elseif ($prefix === 'stock' && in_array($page, $stock_pages)) {
                        $barfile = __DIR__ . "/bar_stock.php";
                        if (file_exists($barfile)) {
                            include $barfile;
                        } else {
                            echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($barfile) . "</div>";
                        }
                        $action = $parts[1] ?? 'insert';
                        $filepath = "$basePath/stock/stock_{$action}.php";

                        if (isset($_GET['id'])) {
                            $_REQUEST['id'] = $_GET['id'];
                        }
                    }
                    if (isset($filepath) && file_exists($filepath)) {
                        include $filepath;
                    } else if (isset($filepath)) {
                        echo "<div class='text-red-600'>ไม่พบไฟล์: " . htmlspecialchars($filepath) . "</div>";
                    }
                }
            } else {
                echo "<div class='text-red-600'>ไม่พบเพจที่คุณต้องการ</div>";
            }

            ob_end_flush();
            ?>
        </div>
    </main>

    <!-- Enhanced JavaScript -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const hamburger = document.querySelector('button[aria-controls="sidebar"]');

            const isOpen = sidebar.classList.contains('active');

            if (isOpen) {
                // Close sidebar
                sidebar.classList.remove('active');
                overlay.classList.remove('opacity-100', 'visible');
                overlay.classList.add('opacity-0', 'invisible');
                hamburger.setAttribute('aria-expanded', 'false');
                hamburger.innerHTML = '<div class="loading"></div>';
                setTimeout(() => {
                    hamburger.innerHTML = '<i class="fas fa-bars text-lg"></i>';
                }, 300);
            } else {
                // Open sidebar
                sidebar.classList.add('active');
                overlay.classList.remove('opacity-0', 'invisible');
                overlay.classList.add('opacity-100', 'visible');
                hamburger.setAttribute('aria-expanded', 'true');
                hamburger.innerHTML = '<div class="loading"></div>';
                setTimeout(() => {
                    hamburger.innerHTML = '<i class="fas fa-times text-lg"></i>';
                }, 300);
            }
        }

        // Close sidebar with Escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('overlay');
                const hamburger = document.querySelector('button[aria-controls="sidebar"]');

                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('opacity-100', 'visible');
                    overlay.classList.add('opacity-0', 'invisible');
                    hamburger.setAttribute('aria-expanded', 'false');
                    hamburger.innerHTML = '<i class="fas fa-bars text-lg"></i>';
                }
            }
        });

        // Add loading effects to navigation links without preventing navigation
        document.querySelectorAll('a[href^="?"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                this.style.opacity = '0.7';
                setTimeout(() => {
                    this.style.opacity = '1';
                }, 200);
            });
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'slideInUp 0.6s ease-out';
                }
            });
        }, observerOptions);

        // Observe dashboard cards
        document.querySelectorAll('.dashboard-card').forEach(card => {
            observer.observe(card);
        });

        // Auto-close sidebar when clicking on navigation items (mobile)
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (window.innerWidth < 1024) { // lg breakpoint
                    setTimeout(() => {
                        toggleSidebar();
                    }, 300);
                }
            });
        });
    </script>
</body>

</html>