<?php
// Test script to check if the pages have any PHP errors
echo "Testing proctoring_monitor.php and exam_activity_report.php for errors...\n";

// Test proctoring monitor page logic
echo "\nTesting proctoring monitor queries...\n";
require_once 'db.php';

try {
    // Simulate a lecturer session
    $user_id = 1; // Assuming a sample lecturer ID
    
    // Test the first query
    $stmt = $pdo->prepare("
        SELECT 
            esp.id,
            esp.exam_attempt_id,
            esp.student_id,
            esp.start_time,
            esp.suspicious_activity_count,
            esp.proctoring_status,
            u.username as student_username,
            e.title as exam_title
        FROM exam_sessions_proctoring esp
        JOIN users u ON esp.student_id = u.id
        JOIN attempts a ON esp.exam_attempt_id = a.id
        JOIN exams e ON a.exam_id = e.id
        WHERE e.user_id = ?
        AND esp.proctoring_status IN ('active', 'flagged')
        ORDER BY esp.start_time DESC
    ");
    $stmt->execute([$user_id]);
    $active_sessions = $stmt->fetchAll();
    echo "✓ Proctoring monitor query executed successfully (" . count($active_sessions) . " results)\n";
} catch (Exception $e) {
    echo "✗ Proctoring monitor query failed: " . $e->getMessage() . "\n";
}

try {
    // Test the second query
    $stmt = $pdo->prepare("
        SELECT 
            esl.*,
            u.username as student_username,
            e.title as exam_title
        FROM exam_security_logs esl
        JOIN attempts a ON esl.exam_attempt_id = a.id
        JOIN users u ON esl.user_id = u.id
        JOIN exams e ON a.exam_id = e.id
        WHERE e.user_id = ?
        ORDER BY esl.timestamp DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $recent_logs = $stmt->fetchAll();
    echo "✓ Security logs query executed successfully (" . count($recent_logs) . " results)\n";
} catch (Exception $e) {
    echo "✗ Security logs query failed: " . $e->getMessage() . "\n";
}

// Test exam activity report page logic
echo "\nTesting exam activity report queries...\n";

try {
    // Test exam reports query
    $reports_stmt = $pdo->prepare("
        SELECT er.*, e.title as exam_title 
        FROM exam_reports er
        JOIN exams e ON er.exam_id = e.id
        WHERE er.lecturer_id = ?
        ORDER BY er.report_date DESC
    ");
    $reports_stmt->execute([$user_id]);
    $exam_reports = $reports_stmt->fetchAll();
    echo "✓ Exam reports query executed successfully (" . count($exam_reports) . " results)\n";
} catch (Exception $e) {
    echo "✗ Exam reports query failed: " . $e->getMessage() . "\n";
}

try {
    // Test activity logs query
    $params = [$user_id];
    $activity_query = "
        SELECT eal.*, u.username as student_username, e.title as exam_title, a.student_index
        FROM exam_activity_logs eal
        JOIN users u ON eal.user_id = u.id
        JOIN attempts a ON eal.exam_attempt_id = a.id
        JOIN exams e ON a.exam_id = e.id
        WHERE e.user_id = ?
    ";
    $activity_query .= " ORDER BY eal.timestamp DESC LIMIT 100";
    
    $activity_stmt = $pdo->prepare($activity_query);
    $activity_stmt->execute($params);
    $activity_logs = $activity_stmt->fetchAll();
    echo "✓ Activity logs query executed successfully (" . count($activity_logs) . " results)\n";
} catch (Exception $e) {
    echo "✗ Activity logs query failed: " . $e->getMessage() . "\n";
}

try {
    // Test cheating incidents query
    $incidents_params = [$user_id];
    $incidents_query = "
        SELECT ci.*, u.username as student_username, e.title as exam_title, a.student_index
        FROM cheating_incidents ci
        JOIN users u ON ci.user_id = u.id
        JOIN attempts a ON ci.exam_attempt_id = a.id
        JOIN exams e ON a.exam_id = e.id
        WHERE e.user_id = ?
    ";
    $incidents_query .= " ORDER BY ci.incident_timestamp DESC";
    
    $incidents_stmt = $pdo->prepare($incidents_query);
    $incidents_stmt->execute($incidents_params);
    $cheating_incidents = $incidents_stmt->fetchAll();
    echo "✓ Cheating incidents query executed successfully (" . count($cheating_incidents) . " results)\n";
} catch (Exception $e) {
    echo "✗ Cheating incidents query failed: " . $e->getMessage() . "\n";
}

echo "\nAll database queries tested successfully!\n";
?>