<?php
require_once 'db.php';

/**
 * Script to generate exam activity reports after exams are completed
 */

echo "Generating exam activity reports...\n";

try {
    // Get exams that have been completed recently (within last 24 hours) without reports
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.id, e.user_id
        FROM exams e
        JOIN attempts a ON e.id = a.exam_id
        WHERE a.status = 'completed'
        AND e.id NOT IN (
            SELECT exam_id FROM exam_reports WHERE exam_id = e.id
        )
        AND e.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    
    $stmt->execute();
    $exams = $stmt->fetchAll();
    
    foreach ($exams as $exam) {
        $exam_id = $exam['id'];
        $lecturer_id = $exam['user_id'];
        
        // Get statistics for the exam
        $stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN exam_attempt_id IN (
                    SELECT exam_attempt_id FROM exam_activity_logs 
                    WHERE severity IN ('high', 'critical')
                ) THEN 1 ELSE 0 END) as flagged_attempts,
                SUM(CASE WHEN exam_attempt_id IN (
                    SELECT exam_attempt_id FROM exam_activity_logs 
                    WHERE severity = 'critical'
                ) THEN 1 ELSE 0 END) as high_risk_attempts,
                AVG(CASE WHEN severity = 'low' THEN 1 
                        WHEN severity = 'medium' THEN 2 
                        WHEN severity = 'high' THEN 3 
                        WHEN severity = 'critical' THEN 4 
                        ELSE 0 END) as avg_severity
            FROM attempts 
            WHERE exam_id = ?
        ");
        
        $stats_stmt->execute([$exam_id]);
        $stats = $stats_stmt->fetch();
        
        // Calculate average severity (convert from scale 1-4 to 0-100 scale)
        $avg_severity_scaled = $stats['avg_severity'] ? ($stats['avg_severity'] / 4) * 100 : 0;
        
        // Create summary report
        $summary = "Exam completed with {$stats['total_attempts']} attempts. ";
        $summary .= "{$stats['flagged_attempts']} flagged for potential cheating. ";
        $summary .= "{$stats['high_risk_attempts']} identified as high risk. ";
        $summary .= "Average severity rating: " . round($avg_severity_scaled, 2) . "/100.";
        
        // Insert the report
        $insert_stmt = $pdo->prepare("
            INSERT INTO exam_reports 
            (exam_id, lecturer_id, total_attempts, flagged_attempts, high_risk_attempts, average_severity, summary_report)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insert_stmt->execute([
            $exam_id,
            $lecturer_id,
            $stats['total_attempts'],
            $stats['flagged_attempts'],
            $stats['high_risk_attempts'],
            $avg_severity_scaled,
            $summary
        ]);
        
        echo "Generated report for exam ID: $exam_id (Lecturer ID: $lecturer_id)\n";
    }
    
    echo "Exam activity report generation completed!\n";
    
} catch (Exception $e) {
    echo "Error generating exam reports: " . $e->getMessage() . "\n";
}
?>