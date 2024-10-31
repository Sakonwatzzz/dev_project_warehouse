<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
</head>

<body>
    <?php include 'component/navbar.php'; ?>

    <div class="content-item">
        <?php
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
            $is_page = explode("_", $page);

            // include './component/bar_' . $is_page[1] . '.php';
            // include './page/'.$is_page[1].'/'.$page.'.php';
            // include ''.$is_page[1].'/'.$page.'.php';
            
            // echo '<br>';
            // echo './component/bar_' . $is_page[1] . '.php';
            // echo '<br>';
            // echo './page/' . $is_page[1] . '/' . $page . '.php';

     
        }

        
        
           //     if (file_exists($pageFile)) {
        //         include $pageFile;
        //     } else {
        //         echo "<p>หน้าที่คุณเรียกไม่พบ</p>";
        //     }
        // } else {
        //     echo "<p>กรุณาเลือกเมนูเพื่อแสดงข้อมูล</p>";




        // if ($page === 'personnel_list') {
        //     include './component/bar_personnel.php';
        //     include './page/personnels/personnel_list.php';
        // } elseif ($page === 'personnel_add') {
        //     include './component/bar_personnel.php';
        //     include './page/personnels/personnel_add.php';
        // } elseif ($page === 'personnel_type') {
        //     include './component/bar_personnel.php';
        //     include './page/personnels/personnel_type.php';
        // } elseif ($page === 'personnel_status') {
        //     include './component/bar_personnel.php';
        //     include './page/personnels/personnel_status.php';
        // } elseif ($page === 'member_list') {
        //     include './component/bar_member.php';
        // include './page/members/member_list.php';
        // } elseif ($page === 'member_add') {
        //     include './component/bar_member.php';
        //     include './page/members/member_add.php';
        // } elseif ($page === 'member_type') {
        //     include './component/bar_member.php';
        //     include './page/members/member_type.php';
        // } elseif ($page === 'product_list') {
        //     include './component/bar_stor.php';
        // include './page/products/product_list.php';
        // } elseif ($page === 'product_add') {
        //     include './component/bar_stor.php';
        //     include './page/products/product_add.php';
        // } elseif ($page === 'product_type') {
        //     include './component/bar_stor.php';
        //     include './page/products/product_type.php';
        // } elseif ($page === 'product_brand') {
        //     include './component/bar_stor.php';
        //     include './page/products/product_brand.php';
        // } else {
        //     echo "<p>กรุณาเลือกเมนูเพื่อแสดงข้อมูล</p>";
        // }
        // } else {
        //     echo "<p>กรุณาเลือกเมนูเพื่อแสดงข้อมูล</p>";
        
        ?>
    </div>
</body>

</html>