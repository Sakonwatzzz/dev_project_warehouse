<?php
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

// Define section pages
$product_section_pages = [
    'product_list',
    'product_insert',
    'product_type',
    'product_brand',
    'warehouse_list',
    'lot_listinsert',
    'lot_list',
    'lot_productlist',
    'lot_productinsert'
];

// Check if we're in dashboard section (for highlighting both home and dashboard)
$is_dashboard = ($current_page == 'dashboard' || $current_page == 'bar_index');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../style/navbar.css">

    <title>Admin System</title>
</head>

<body>
    <div class="header">
        <p>Admin Management system</p>
        <div class="navbar">
            <div class="link">
                <ul>
                    <li><a href="?page=bar_index"
                            class="<?php echo $is_dashboard ? 'active' : ''; ?>">หน้าแรก</a></li>
                    <li><a href="?page=bar_product"
                            class="<?php echo ($current_page == 'bar_product' || isInSection($current_page, $product_section_pages)) ? 'active' : ''; ?>">สินค้า</a></li>
                    <li><a href="?page=bar_personnel"
                            class="<?php echo ($main_section == 'personnel' || $current_page == 'bar_personnel') ? 'active' : ''; ?>">บุคลากร</a></li>
                    <li><a href="?page=bar_member"
                            class="<?php echo ($main_section == 'member' || $current_page == 'bar_member') ? 'active' : ''; ?>">สมาชิก</a></li>
                    <li><a href="?page=bar_supplier"
                            class="<?php echo ($main_section == 'supplier' || $current_page == 'bar_supplier') ? 'active' : ''; ?>">supplier</a></li>
                    <li><a href="?page=navigationbar_sale"
                            class="<?php echo $current_page == 'navigationbar_sale' ? 'active' : ''; ?>">ขาย</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="button-bar">
        <?php
        // Get page parameter and set defaults
        $page = $_GET['page'] ?? 'dashboard'; // Default to dashboard when no page is specified
        $allowed_pages = ['bar_index', 'bar_product', 'bar_personnel', 'bar_member', 'bar_supplier', 'navigationbar_sale'];

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
            'lot_listinsert',
            'lot_list',
            'lot_insert',
            'lot_productlist',
            'lot_productinsert',
            'lot_productupdate',
            'lot_update',
            'lot_detail',
            'lot_productdetail',
            'lot_listdetail',
            'lot_listinsertcopy',
            'warehouse_lot',
            'supplier_lot',
            'lot_insertout',
            'lot_insertdetailout',
            'lot_warehouseinout'

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

        if ($page) {

            $parts = explode('_', $page);
            $prefix = $parts[0] ?? null;  // 'bar' หรือ 'member'
            $module = $parts[1] ?? null;  // 'product', 'supplier', 'list', 'insert', etc.

            // If no page is specified or it's the homepage/bar_index, show dashboard
            if ($page == 'dashboard' || $page == 'bar_index') {
                // Load bar_index.php first
                $barfile = __DIR__ . "/bar_index.php";
                if (file_exists($barfile)) {
                    include $barfile;
                }
                $filepath = __DIR__ . "/../page/dashboard/dashboard.php";
                if (file_exists($filepath)) {
                    include $filepath;
                }
            }
            // Process other pages as normal
            else {
                // ขั้นแรก ให้รวมไฟล์แถบนำทางหากเป็นหน้าที่ได้รับอนุญาต
                if (in_array($page, $allowed_pages)) {
                    $basefile = __DIR__ . "/$page.php";
                    if (file_exists($basefile)) {
                        include $basefile;
                    }
                }

                // ตรวจสอบและโหลดหน้าต่างๆ
                $basePath = __DIR__ . "/../page";

                if ($prefix === 'bar') {
                    // แมปชื่อโมดูลกับชื่อโฟลเดอร์ที่ถูกต้อง
                    $folderMapping = [
                        'product' => 'product',
                        'supplier' => 'supplier',
                        'personnel' => 'personnel',
                        'member' => 'member',
                        'lot' => 'lot'
                    ];

                    if (isset($folderMapping[$module])) {
                        $folderName = $folderMapping[$module];

                        if ($module === 'product') {
                            // Special handling for product pages
                            $action = $parts[2] ?? 'list';
                            $subaction = $parts[3] ?? null;

                            $filepath = "$basePath/$folderName/";
                            if ($subaction) {
                                $filepath .= "{$folderName}_{$action}_{$subaction}.php";
                            } else {
                                $filepath .= "{$folderName}_{$action}.php";
                            }
                        } else {
                            // For all other modules
                            $filepath = "$basePath/$folderName/{$folderName}_list.php";
                        }
                    }
                } elseif ($prefix === 'dashboard' && in_array($page, $dashboard_pages)) {
                    // โหลด bar_index.php ก่อน
                    $barfile = __DIR__ . "/bar_index.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    }
                    $action = $parts[1] ?? '';
                    $filepath = "$basePath/dashboard/dashboard.php";

                    if (isset($_GET['id'])) {
                        $_REQUEST['id'] = $_GET['id'];
                    }
                } elseif ($prefix === 'member' && in_array($page, $member_pages)) {
                    // โหลด bar_member.php ก่อน
                    $barfile = __DIR__ . "/bar_member.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    }
                    $action = $parts[1] ?? 'list';
                    $filepath = "$basePath/member/member_{$action}.php";

                    if (isset($_GET['id'])) {
                        $_REQUEST['id'] = $_GET['id'];
                    }
                } elseif ($prefix === 'product' && in_array($page, $product_pages)) {
                    // โหลด bar_product.php ก่อน
                    $barfile = __DIR__ . "/bar_product.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    }
                    $action = $parts[1] ?? 'list';
                    if ($action) {
                        $filepath = "$basePath/product/product_{$action}.php";
                    }
                    if (isset($_GET['id'])) {
                        $_REQUEST['id'] = $_GET['id'];
                    }
                } elseif ($prefix === 'warehouse' && in_array($page, $warehouse_page)) {
                    // โหลด bar_product.php ก่อน
                    $barfile = __DIR__ . "/bar_product.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    }
                    $action = $parts[1] ?? 'list';
                    $filepath = "$basePath/warehouse/warehouse_{$action}.php";

                    if (isset($_GET['id'])) {
                        $_REQUEST['id'] = $_GET['id'];
                    }
                } elseif ($prefix === 'lot' && in_array($page, $lot_pages)) {
                    // โหลด bar_product.php ก่อน
                    $barfile = __DIR__ . "/bar_product.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    }
                    $action = $parts[1] ?? 'list';
                    $filepath = "$basePath/lot/lot_{$action}.php";

                    if (isset($_GET['id'])) {
                        $_REQUEST['id'] = $_GET['id'];
                    }
                } elseif ($prefix === 'warehouse' && $page === 'warehouse_lot') {
                    // โหลด bar_product.php ก่อน
                    $barfile = __DIR__ . "/bar_product.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    }
                    $filepath = "$basePath/lot/warehouse_lot.php";

                    if (isset($_GET['warehouse_id'])) {
                        $_REQUEST['warehouse_id'] = $_GET['warehouse_id'];
                    }
                }elseif ($prefix === 'supplier' && $page === 'supplier_lot') {
                    // โหลด bar_product.php ก่อน
                    $barfile = __DIR__ . "/bar_product.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    }
                    $filepath = "$basePath/lot/supplier_lot.php";

                    if (isset($_GET['supplier_id'])) {
                        $_REQUEST['supplier_id'] = $_GET['supplier_id'];
                    }
                }elseif ($prefix === 'personnel' && in_array($page, $personnel_pages)) {
                    // โหลด bar_personnel.php ก่อน
                    $barfile = __DIR__ . "/bar_personnel.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    }
                    $action = $parts[1] ?? 'list';
                    $filepath = "$basePath/personnel/personnel_{$action}.php";

                    if (isset($_GET['id'])) {
                        $_REQUEST['id'] = $_GET['id'];
                    }
                } elseif ($prefix === 'supplier' && in_array($page, $supplier_pages)) {
                    // โหลด bar_supplier.php ก่อน
                    $barfile = __DIR__ . "/bar_supplier.php";
                    if (file_exists($barfile)) {
                        include $barfile;
                    }
                    $action = $parts[1] ?? 'list';
                    $filepath = "$basePath/supplier/supplier_{$action}.php";

                    if (isset($_GET['id'])) {
                        $_REQUEST['id'] = $_GET['id'];
                    }
                }

                // Include the file if it exists
                if (isset($filepath) && file_exists($filepath)) {
                    include $filepath;
                } else if (isset($filepath)) {
                    echo "<h2>ไม่พบไฟล์: " . htmlspecialchars($filepath) . "</h2>";
                }
            }
        } else {
            echo "<h2>ไม่พบเพจที่คุณต้องการ</h2>";
        }
        ?>
    </div>

</body>

</html>