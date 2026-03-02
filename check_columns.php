<?php
require_once 'db.php';

echo "Checking exam_sessions_proctoring table structure:\n";
try {
    $stmt = $pdo->query("DESCRIBE exam_sessions_proctoring");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nChecking exam_security_logs table structure:\n";
try {
    $stmt = $pdo->query("DESCRIBE exam_security_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>