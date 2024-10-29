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
        <div class="dis">
            <p>Administrater System - Power by Wisdom Planet</p>
        </div>
        
        <div class="navbar">
        <div class="logo">
                <img src="image/stock-rotation.png" alt="">
            </div>
            <div class="link">
                <ul>
                    <li><a href="#">เบิกจ่าย</a></li>
                    <li class="dropdown">
                        <a href="#" class="nav-link">คลัง <i class="fa fa-chevron-down"></i></a>
                        <div class="dropdown-content">
                            <a href="#">รายการสินค้า</a>
                            <a href="#">คลังสินค้า</a>
                            <a href="#">การรับสินค้า</a>
                            <a href="#">การเบิกสินค้า</a>
                        </div>
                    </li>
                    <li><a href="?page=personnel" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'personnel') ? 'active' : ''; ?>">สมาชิก</a></li>
                    <!-- <li><a href="#">โครงการ</a></li> -->
                    <li><a href="#">ขาย</a></li>
                    <!-- <li><a href="#">อสังหา</a></li>
                    <li><a href="#">สถิติ</a></li>
                    <li><a href="#">Course</a></li>
                    <li><a href="#">เพิ่มเติม</a></li>
                    <li><a href="#">ระบบเฉพาะ</a></li>
                    <li><a href="#">รายงาน</a></li>
                    <li><a href="#">องค์กร</a></li>
                    <li><a href="#">งบประมาณ</a></li>
                    <li><a href="#">คงระบบ</a></li>
                    <li><a href="#">นักศึกษา</a></li>
                    <li><a href="#">หน้าเว็บ</a></li> -->
                </ul>
            </div>
        </div>
    </div>


    
</body>

</html>