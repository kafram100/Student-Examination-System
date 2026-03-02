<?php
require_once 'db.php';

echo "Fixing missing lecturer_id values in proctoring sessions...\n";

try {
    // Update proctoring sessions to include the lecturer_id where it's missing
    $stmt = $pdo->prepare("
        UPDATE exam_sessions_proctoring esp
        INNER JOIN attempts a ON esp.exam_attempt_id = a.id
        INNER JOIN exams e ON a.exam_id = e.id
        SET esp.lecturer_id = e.user_id
        WHERE esp.lecturer_id IS NULL OR esp.lecturer_id = 0
    ");
    $stmt->execute();
    
    $updatedRows = $stmt->rowCount();
    echo "Updated $updatedRows proctoring session(s)\n";
    
    echo "Fix completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>