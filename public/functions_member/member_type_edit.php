<?php
require_once __DIR__ . '../../../config/database_server.php';

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
            <input type="hidden" name="id" value="<?= htmlspecialchars($member['member_type_id']) ?>">



            <?php if ($results->rowCount() > 0) { ?>
                <label for="typename">ประเภทสมาชิก:</label>
                <select name="typename" id="typename">
                    <option value="">-- เลือกประเภทสมาชิก --</option> <!-- Empty option -->
                    <?php while ($row = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                        <option value="<?= htmlspecialchars($row['member_type_id']); ?>" <?= $row['member_type_id'] == $member['member_type_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($row['member_type_name']); ?>
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
