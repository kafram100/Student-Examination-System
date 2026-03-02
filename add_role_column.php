<?php
require_once 'db.php';

echo "Adding role column to users table...\n";

try {
    // Check if role column already exists
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $columnExists = $result->fetch();
    
    if (!$columnExists) {
        // Add the role column
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'lecturer'");
        echo "Role column added successfully.\n";
        
        // Update existing users to have the lecturer role
        $pdo->exec("UPDATE users SET role = 'lecturer'");
        echo "Assigned 'lecturer' role to existing users.\n";
    } else {
        echo "Role column already exists.\n";
    }
    
    echo "Role column setup completed!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>