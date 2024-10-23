<?php


function fetchAll($pdo, $table)
{
    try {
        $query = $pdo->query("SELECT * FROM $table");
        return $query->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}


