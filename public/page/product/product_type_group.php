<?php
require_once __DIR__ . '../../../../config/database_server.php';

$query = "
    SELECT COUNT(*) as total_count
    FROM personnel
    INNER JOIN product_type ON personnel.personnel_type_id = product_type.product_type_id
    WHERE product_type.product_type_name = :type
";


try {
    // Prepare the SQL statement
    $stmt = $pdo->prepare($query);
    $stmt->execute();

    // Fetch product type data
    $productTypeData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/product_type_list.css">
    <link href="../../output.css" rel="stylesheet">
    <title>Product Types group</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

        .title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .search {
            margin-top: 10px;
        }

        .search input {
            padding: 5px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="title">
            <h2>Product Types</h2>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="Search product types...">
            </div>
        </div>

        <?php if ($productTypeData): ?>
            <table id="dataTable">
                <thead>
                    <tr>
                        <th>Product Type ID</th>
                        <th>Product Type Name</th>
                        <th>Product Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($personnelData as $personnel): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($personnel['personnel_name']); ?></td>
                            <td><?php echo htmlspecialchars($personnel['product_type_name']); ?></td>
                            <td><?php echo htmlspecialchars($personnel['personnel_username']); ?></td>
                            <td class="text-center">
                                <a href="?page=personnel_update&id=<?php echo $personnel['personnel_id']; ?>" class="...">แก้ไข</a>
                                <a href="?page=bar_personnel&id=<?php echo $personnel['personnel_id']; ?>" class="...">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        <?php else: ?>
            <p>No product types found.</p>
        <?php endif; ?>
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const rows = table.getElementsByTagName("tr");

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName("td");
                let rowMatches = false;

                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        rowMatches = true;
                        break;
                    }
                }

                rows[i].style.display = rowMatches ? "" : "none";
            }
        }
    </script>
</body>

</html>