<?php
require 'db.php';

try {
    $pdo->exec("ALTER TABLE exams ADD COLUMN exam_type ENUM('Exam', 'Mid-semester', 'Quiz', 'Assignment') DEFAULT 'Exam' AFTER title");
    echo "Column 'exam_type' added successfully.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Column 'exam_type' already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
