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
                    <li><a href="?page=bar_index" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_index') ? 'active' : ''; ?>">หน้าแรก</a></li>
                    <li><a href="#">เบิกจ่าย</a></li>
                    <li><a href="?page=bar_stor" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_stor') ? 'active' : ''; ?>">คลัง</a></li>
                    <li><a href="?page=bar_personnel" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_personnel') ? 'active' : ''; ?>">สมาชิก</a></li>
                    <li><a href="#">ขาย</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="button-bar">
        <?php
        $page = $_GET['page'] ?? 'bar_index';
        switch ($page) {
            case 'bar_personnel':
                include 'component/bar_personnel.php';
                include 'page/personnels/personnel_list.php';
                break;
            case 'bar_stor':
                include 'component/bar_stor.php';
                break;
            case 'bar_index':
                include 'component/bar_index.php';
                include 'component/dashboard.php';
                break;
        }
        ?>
    </div>


</body>

</html>