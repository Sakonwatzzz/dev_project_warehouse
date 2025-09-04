<?php
require_once __DIR__ . '../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

$accessToken = getToken('access_token');
if (!$accessToken || !validateToken($accessToken)) {
    $refreshToken = getToken('refresh_token');
    if ($refreshToken && validateToken($refreshToken)) {
        $accessToken = refreshToken($refreshToken);
    } else {
        header('Location: login.php');
        exit();
    }
}

$query = "SELECT member_type_id, member_type_name FROM member_type";
$results = $pdo->query($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare("
        UPDATE member_type  
        SET member_type_name = ? 
        WHERE member_type_id = ?
    ");
    $stmt->execute([$typename, $id]);

    header('Location: /index.php?page=member_list');  // Redirect back to the list page
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM member_type WHERE member_type_id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member Type</title>
    <link rel="stylesheet" href="/style/member_edit.css">
</head>
<body>
    <div class="main-body">
        <h2 class="sub-title">แก้ไขประเภทสมาชิก</h2>

        <form class="form-container" action="" method="POST">
            <input type="hidden" name="id" value="<?php echo($member['member_type_id']) ?>">



            <?php if ($results->rowCount() > 0) { ?>
                <label for="typename">ประเภทสมาชิก:</label>
                <select name="typename" id="typename">
                    <option value="">-- เลือกประเภทสมาชิก --</option> <!-- Empty option -->
                    <?php while ($member_type_update = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                        <option value="<?php echo($member_type_update['member_type_id']); ?>" <?= $member_type_update['member_type_id'] == $member['member_type_id'] ? 'selected' : ''; ?>>
                            <?php echo($member_type_update['member_type_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <p>ไม่พบข้อมูลประเภทสมาชิก</p>
            <?php } ?>

            <button class="submit-button" type="submit">Update</button>
        </form>
    </div>
</body>
</html>
