<?php
require 'db.php';

try {
    // Add assessment_file to exams table
    $pdo->exec("ALTER TABLE exams ADD COLUMN assessment_file VARCHAR(255) DEFAULT NULL AFTER instructions");
    echo "Column 'assessment_file' added to exams.\n";
} catch (PDOException $e) {
    echo "Column 'assessment_file' might already exist or error: " . $e->getMessage() . "\n";
}

try {
    // Add q_type to questions table
    $pdo->exec("ALTER TABLE questions ADD COLUMN q_type ENUM('mcq', 'theory') DEFAULT 'mcq' AFTER exam_id");
    echo "Column 'q_type' added to questions.\n";
} catch (PDOException $e) {
    echo "Column 'q_type' might already exist or error: " . $e->getMessage() . "\n";
}
?>
