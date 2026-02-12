<?php
require 'db.php';

try {
    // Add theory_answer and file_upload to answers table
    $pdo->exec("ALTER TABLE answers ADD COLUMN theory_answer TEXT DEFAULT NULL AFTER selected_option");
    $pdo->exec("ALTER TABLE answers ADD COLUMN file_upload VARCHAR(255) DEFAULT NULL AFTER theory_answer");
    echo "Columns added to answers table.\n";
} catch (PDOException $e) {
    echo "Error updating answers table: " . $e->getMessage() . "\n";
}
?>
