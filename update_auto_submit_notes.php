<?php
require_once 'db.php';

echo "Checking and updating attempts table for auto-submit notes...\n";

try {
    // Check if the 'notes' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM attempts LIKE 'notes'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        // Add the notes column
        $pdo->exec("ALTER TABLE attempts ADD COLUMN notes TEXT");
        echo "Added 'notes' column to attempts table\n";
    } else {
        echo "'notes' column already exists in attempts table\n";
    }
    
    echo "Database update completed!\n";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>