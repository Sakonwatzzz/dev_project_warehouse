<?php
require_once __DIR__ . '../../../config/database_server.php';
$results = fetchAll($pdo, 'personnel');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style/navbar.css">
    <link rel="stylesheet" href="../style/personnel_.css">
    <title>Document</title>
</head>

<body>
    <div class="box">
        <div class="icon-section">
            <ul class="icon-list">
                <li>
                    <a href="?page=personnel_list" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'personnel_list') ? 'active' : ''; ?>">
                        <img src="image/team.png" alt="">
                        <span>รายชื่อสมาชิก</span></a>

                </li>
                <li>
                        <a href="?page=personnel_add" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'personnel_add') ? 'active' : ''; ?>">
                        <img src="image/add.png" alt="">
                        <span>เพิ่มสมาชิก</span>
                </li>
                <li>
                    <img src="image/customer.png" alt="">
                    <span>ประเภทสมาชิก</span>
                </li>
                <li>
                    <img src="image/team-building.png" alt="">
                    <span>สถานะ</span>
                </li>
            </ul>
            <p>สมาชิก</p>
        </div>
    </div>
</body>

</html>