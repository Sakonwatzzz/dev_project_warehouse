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

    header("Location: http://localhost:8000/home.php?page=personnel_list");    
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM personnel WHERE personnel_id = ?");
$stmt->execute([$id]);
$personnel = $stmt->fetch();
?>

<h2>Edit Personnel</h2>
<form action="edit.php" method="POST">
    <input type="hidden" name="id" value="<?= htmlspecialchars($personnel['personnel_id']) ?>">

    <label for="name">Name:</label>
    <input type="text" name="name" value="<?= htmlspecialchars($personnel['personnel_name']) ?>" >



    <!-- loop -->
    <?php
    if ($results->rowCount() > 0) {
    ?>
        <label for="typename">ประเภทบุคคลากร:</label>
        <select name="typename" id="typename" >
            <?php while ($row = $results->fetch(PDO::FETCH_ASSOC)) { ?>
                <option value="<?php echo htmlspecialchars($row['personnel_type_id']); ?>">
                    <?php echo htmlspecialchars($row['personnel_type_name']); ?>
                </option>
            <?php } ?>
        </select><br>
    <?php
    } else {
        echo "No personnel types available.";
    }
    ?>
    <!--  -->

    <label for="address">Address:</label>
    <input type="text" name="address" value="<?= htmlspecialchars($personnel['personnel_address']) ?>" >

    <label for="phone">Phone:</label>
    <input type="text" name="phone" value="<?= htmlspecialchars($personnel['personnel_phone']) ?>" >

    <label for="username">Username:</label>
    <input type="text" name="username" value="<?= htmlspecialchars($personnel['personnel_username']) ?>" >

    <label for="password">Password:</label>
    <input type="text" name="password" value="<?= htmlspecialchars($personnel['personnel_password']) ?>" >
    <input type="submit" value="Update">
</form>