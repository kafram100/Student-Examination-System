<?php
require 'db.php';

try {
    $sql = "ALTER TABLE students ADD COLUMN program VARCHAR(100) AFTER full_name";
    $pdo->exec($sql);
    echo "Added 'program' column to students table.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') { // Duplicate column error code
         echo "Column 'program' already exists.";
    } else {
         echo "DB Error: " . $e->getMessage(); // Likely already exists or table locked
    }
}
?>
