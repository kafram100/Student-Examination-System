<?php
require 'db.php';

try {
    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
    $pdo->exec($sql);
    echo "Database initialized successfully.";
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
