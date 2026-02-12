<?php
require 'db.php';

try {
    $sql = file_get_contents(__DIR__ . '/sql/students_table.sql');
    $pdo->exec($sql);
    echo "Students table created successfully.";
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
