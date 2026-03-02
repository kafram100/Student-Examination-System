<?php
require_once 'db.php';

echo "Installing proctoring schema...\n";

// Read the SQL schema file
$sql = file_get_contents('sql/proctoring_schema_simple.sql');

// Split by semicolon to execute each statement separately
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            $pdo->exec($statement);
            echo "Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            echo "Error executing statement: " . $e->getMessage() . "\n";
            echo "Statement: " . $statement . "\n";
        }
    }
}

echo "Proctoring schema installation completed!\n";
?>