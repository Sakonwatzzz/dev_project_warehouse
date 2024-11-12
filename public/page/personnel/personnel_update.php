<?php
require_once __DIR__ . '../../../../config/database_server.php';


$query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$results = $pdo->query($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $typename = $_POST['typename'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("
        UPDATE personnel 
        SET personnel_name = ?,personnel_type_id = ?, personnel_address = ?, personnel_phone = ?, personnel_username = ?, personnel_password = ? 
        WHERE personnel_id = ?
    ");
    $stmt->execute([$name, $typename, $address,  $phone, $username, $password, $id]);

    header("Location: /home.php?page=personnel_list&success=1");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM personnel WHERE personnel_id = ?");
$stmt->execute([$id]);
$personnel = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/member_edit.css">
    <style>
        .edit-box {
            margin: 3rem 10rem 4rem 8rem;
        }
    </style>
    <title>Edit Personnel</title>
</head>

<body>
    <div class="edit-box">
        <h2 class="title">แก้ไขบุคลากร</h2>

        <form class="form-container" action="personnel_update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo($personnel['personnel_id']) ?>">

            <div class="box">
                <label for="name">ชื่อบุคลากร:</label>
                <input type="text" name="name" value="<?php echo($personnel['personnel_name']) ?>">
            </div>


            <?php if ($results->rowCount() > 0) { ?>
                <div class="box">
                    <label for="typename">ประเภทบุคลากร:</label>
                    <select name="typename" id="typename">
                        <?php while ($personnel_update = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo($personnel_update['personnel_type_id']); ?>" <?= ($personnel_update['personnel_type_id'] == $personnel['personnel_type_id']) ? 'selected' : ''; ?>>
                                <?php echo($personnel_update['personnel_type_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } else { ?>
                <p>No personnel types available.</p>
            <?php } ?>

            <div class="box">
                <label for="address">ที่อยู่บุคลากร:</label>
                <input type="text" name="address" value="<?php echo($personnel['personnel_address']) ?>">
            </div>

            <div class="box">
                <label for="phone">เบอร์โทรศัพท์บุคลากร:</label>
                <input type="text" name="phone" value="<?php echo($personnel['personnel_phone']) ?>">
            </div>

            <div class="box">
                <label for="username">ชื่อผู้ใช้:</label>
                <input type="text" name="username" value="<?php echo($personnel['personnel_username']) ?>">
            </div>

            <div class="box">
                <label for="password">รหัสผ่าน:</label>
                <input type="text" name="password" value="<?php echo($personnel['personnel_password']) ?>">
            </div>


            <div class="box">
                <label for=""></label>
                <button class="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>
        </form>
    </div>
</body>

</html>