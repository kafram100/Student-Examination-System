<?php
require_once 'db.php';

echo "Checking database tables...\n";

// Get all tables
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Existing tables:\n";
foreach ($tables as $table) {
    echo "- $table\n";
}

echo "\nChecking for specific tables:\n";

// Check for exam_activity_logs table
try {
    $result = $pdo->query("SELECT COUNT(*) FROM exam_activity_logs LIMIT 1;");
    echo "✓ exam_activity_logs table exists\n";
} catch (Exception $e) {
    echo "✗ exam_activity_logs table does not exist: " . $e->getMessage() . "\n";
}

// Check for exam_reports table
try {
    $result = $pdo->query("SELECT COUNT(*) FROM exam_reports LIMIT 1;");
    echo "✓ exam_reports table exists\n";
} catch (Exception $e) {
    echo "✗ exam_reports table does not exist: " . $e->getMessage() . "\n";
}

// Check for cheating_incidents table
try {
    $result = $pdo->query("SELECT COUNT(*) FROM cheating_incidents LIMIT 1;");
    echo "✓ cheating_incidents table exists\n";
} catch (Exception $e) {
    echo "✗ cheating_incidents table does not exist: " . $e->getMessage() . "\n";
}

// Check for exam_sessions_proctoring table
try {
    $result = $pdo->query("SELECT COUNT(*) FROM exam_sessions_proctoring LIMIT 1;");
    echo "✓ exam_sessions_proctoring table exists\n";
} catch (Exception $e) {
    echo "✗ exam_sessions_proctoring table does not exist: " . $e->getMessage() . "\n";
}

// Check for exam_security_logs table
try {
    $result = $pdo->query("SELECT COUNT(*) FROM exam_security_logs LIMIT 1;");
    echo "✓ exam_security_logs table exists\n";
} catch (Exception $e) {
    echo "✗ exam_security_logs table does not exist: " . $e->getMessage() . "\n";
}
?>