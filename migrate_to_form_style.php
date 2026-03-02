<?php
require_once 'db.php';

echo "Migrating system to Google Forms style (no permanent student registration)...\n";

try {
    // Add fullname field to attempts table to store student's name for each attempt
    $pdo->exec("ALTER TABLE attempts ADD COLUMN student_fullname VARCHAR(100)");
    
    // Make student_index nullable since we'll be storing fullname instead
    $pdo->exec("ALTER TABLE attempts MODIFY COLUMN student_index VARCHAR(50)");
    
    // Update existing attempts to have some data if possible
    $pdo->exec("UPDATE attempts SET student_fullname = CONCAT('Student-', student_index) WHERE student_fullname IS NULL OR student_fullname = ''");
    
    // Drop the students table since we don't need permanent student records
    $pdo->exec("DROP TABLE IF EXISTS students");
    
    echo "Database migration completed!\n";
    echo "- Added student_fullname column to attempts table\n";
    echo "- Removed students table (no more permanent student records)\n";
    echo "- System now works like Google Forms - students enter details per exam\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
?>