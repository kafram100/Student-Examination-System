<?php
require 'db.php';

try {
    $pdo->exec("ALTER TABLE answers ADD COLUMN marks_awarded DECIMAL(6,2) DEFAULT NULL AFTER is_correct");
    echo "Column 'marks_awarded' added to answers.\n";
} catch (PDOException $e) {
    echo "Column 'marks_awarded' might already exist or error: " . $e->getMessage() . "\n";
}
?>
