<?php
require_once 'db.php';

echo "Updating database schema for fill-in questions...\n";

// Update the q_type column to include 'fill_in' option
try {
    // Get current enum values
    $stmt = $pdo->query("SHOW COLUMNS FROM questions LIKE 'q_type'");
    $columnInfo = $stmt->fetch();
    
    $type = $columnInfo['Type'];
    echo "Current q_type definition: $type\n";
    
    // Extract current enum values
    preg_match("/^enum\('(.+)'\)$/i", $type, $matches);
    if (isset($matches[1])) {
        $currentEnums = explode("','", $matches[1]);
        echo "Current enum values: " . implode(", ", $currentEnums) . "\n";
        
        // Add 'fill_in' if it's not already present
        if (!in_array('fill_in', $currentEnums)) {
            $currentEnums[] = 'fill_in';
            $newEnum = "ENUM('" . implode("','", $currentEnums) . "')";
            
            $alterSql = "ALTER TABLE questions MODIFY COLUMN q_type $newEnum";
            $pdo->exec($alterSql);
            echo "Successfully updated q_type enum to include 'fill_in'\n";
        } else {
            echo "'fill_in' already exists in q_type enum\n";
        }
    } else {
        echo "Could not parse enum values\n";
    }
    
    echo "Database schema update completed!\n";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>