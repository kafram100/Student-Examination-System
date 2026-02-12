<?php
require 'db.php';

try {
    // Add course_code to exams table
    $pdo->exec("ALTER TABLE exams ADD COLUMN course_code VARCHAR(20) DEFAULT NULL AFTER course_name");
    echo "Column 'course_code' added to exams.\n";
} catch (PDOException $e) {
    echo "Column 'course_code' might already exist or error: " . $e->getMessage() . "\n";
}
?>
