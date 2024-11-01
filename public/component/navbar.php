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
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_index') ? 'active' : ''; ?>">หน้าแรก</a>
                    </li>
                    <li><a href="#">เบิกจ่าย</a></li>
                    <li><a href="?page=bar_product"
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_product') ? 'active' : ''; ?>">สินค้า</a>
                    </li>
                    <li><a href="?page=bar_personnel"
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_personnel') ? 'active' : ''; ?>">บุคลากร</a>
                    </li>
                    <li><a href="?page=bar_member"
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_member') ? 'active' : ''; ?>">สมาชิก</a>
                    </li>
                    <li><a href="#">ขาย</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="button-bar">
        <?php
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
            $parts = explode("_", $page);

            if (count($parts) === 2) {
                $module = $parts[0];
                $action = $parts[1];

                switch ("{$module}_{$action}") {
                    case "bar_index":
                        include __DIR__ . "/bar_index.php";
                        break;
                    case "bar_product":
                        include __DIR__ . "/bar_product.php";
                        include __DIR__ . "../../page/product/product_list.php";
                        break;
                    case "bar_personnel":
                        include __DIR__ . "/bar_personnel.php";
                        include __DIR__ . "../../page/personnel/personnel_list.php";
                        break;
                    case "bar_member":
                        include __DIR__ . "/bar_member.php";
                        include __DIR__ . "../../page/member/member_list.php";
                        break;
                    default:
                        if (in_array($page, ['personnel_list', 'personnel_add','personnel_type'])) {
                            include __DIR__ . "/bar_personnel.php";
                        }
                        if (in_array($page, ['member_list', 'member_add','member_type'])) {
                            include __DIR__ . "/bar_member.php";
                        }
                        if (in_array($page, ["product_list", "product_add","product_type","product_brand"])) {
                            include __DIR__ . "/bar_product.php";
                        }
                        break;
                }
            } else {
                echo "<h2>รูปแบบของพารามิเตอร์ไม่ถูกต้อง</h2>";
            }
        } else {
            include __DIR__ . "/bar_index.php";
        }
        ?>
    </div>



</body>

</html>