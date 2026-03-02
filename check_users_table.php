<?php
require_once 'db.php';

echo "Checking users table structure:\n";
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")" . ($column['Null'] === 'YES' ? ' NULL' : '') . ($column['Key'] ? ' ' . strtoupper($column['Key']) : '') . ($column['Extra'] ? ' ' . $column['Extra'] : '') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>