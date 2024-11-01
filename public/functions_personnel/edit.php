<?php
require_once __DIR__ . '../../../config/database_server.php';


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

    header("Location: /home.php?page=personnel_list");
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
    <title>Edit Personnel</title>
    <link rel="stylesheet" href="/style/personnel.css">
</head>

<body>
    <div class="main-body">
        <h2 class="title">Edit Personnel</h2>
        <p class="sub-title">แก้ไขข้อมูลบุคลากร</p>

        <form class="form-container" action="edit.php" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($personnel['personnel_id']) ?>">

            <label for="name">ชื่อบุคลากร:</label>
            <input type="text" name="name" value="<?= htmlspecialchars($personnel['personnel_name']) ?>">

            <?php if ($results->rowCount() > 0) { ?>
                <label for="typename">ประเภทบุคลากร:</label>
                <select name="typename" id="typename">
                    <?php while ($row = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                        <option value="<?= htmlspecialchars($row['personnel_type_id']); ?>" <?= ($row['personnel_type_id'] == $personnel['personnel_type_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($row['personnel_type_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <p>No personnel types available.</p>
            <?php } ?>

            <label for="address">ที่อยู่บุคลากร:</label>
            <input type="text" name="address" value="<?= htmlspecialchars($personnel['personnel_address']) ?>">

            <label for="phone">เบอร์โทรศัพท์บุคลากร:</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($personnel['personnel_phone']) ?>">

            <label for="username">ชื่อผู้ใช้:</label>
            <input type="text" name="username" value="<?= htmlspecialchars($personnel['personnel_username']) ?>">

            <label for="password">รหัสผ่าน:</label>
            <input type="text" name="password" value="<?= htmlspecialchars($personnel['personnel_password']) ?>">

            <button type="submit" class="submit-button">บันทึก</button>
        </form>
    </div>
</body>

</html>