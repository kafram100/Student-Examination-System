<?php
require_once '../db.php';
require_once '../auth.php';

requireLogin();

// Only allow lecturers to access this
if ($_SESSION['role'] !== 'lecturer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Student ID is required']);
    exit;
}

try {
    // Get the student index for this student ID
    $student_stmt = $pdo->prepare("SELECT student_index FROM users WHERE id = ?");
    $student_stmt->execute([$student_id]);
    $student_row = $student_stmt->fetch();
    
    if (!$student_row) {
        // If not found in users table, try to find via exam attempts
        $attempt_stmt = $pdo->prepare("
            SELECT DISTINCT a.student_index 
            FROM attempts a
            JOIN exam_sessions_proctoring esp ON a.id = esp.exam_attempt_id
            WHERE esp.student_id = ?
            LIMIT 1
        ");
        $attempt_stmt->execute([$student_id]);
        $student_row = $attempt_stmt->fetch();
    }
    
    if (!$student_row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit;
    }
    
    $student_index = $student_row['student_index'];
    
    // Get all security logs for this student
    $stmt = $pdo->prepare("
        SELECT esl.*, a.student_fullname, a.student_index
        FROM exam_security_logs esl
        JOIN attempts a ON esl.exam_attempt_id = a.id
        WHERE a.student_index = ?
        ORDER BY esl.timestamp DESC
        LIMIT 50
    ");
    
    $stmt->execute([$student_index]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get direct evidence from the proctoring uploads folder
    $evidence_images = [];
    $upload_dir = __DIR__ . '/../uploads/proctoring/';
    
    if (is_dir($upload_dir)) {
        $files = scandir($upload_dir);
        foreach ($files as $file) {
            if (preg_match('/^proctoring_img_' . preg_quote($student_id, '/') . '_/', $file)) {
                $file_path = 'uploads/proctoring/' . $file;
                $file_time = filemtime($upload_dir . $file);
                
                // Try to find matching log entry or create a default one
                $log_entry = null;
                foreach ($logs as $log) {
                    if (strpos($file, $log['exam_attempt_id']) !== false) {
                        $log_entry = $log;
                        break;
                    }
                }
                
                if (!$log_entry) {
                    // Create a default entry if no matching log found
                    $log_entry = [
                        'activity_type' => 'suspicious_activity',
                        'description' => 'Cheating evidence captured',
                        'timestamp' => date('Y-m-d H:i:s', $file_time),
                        'severity' => 'medium'
                    ];
                }
                
                $evidence_images[] = [
                    'path' => $file_path,
                    'activity_type' => $log_entry['activity_type'],
                    'description' => $log_entry['description'],
                    'timestamp' => $log_entry['timestamp']
                ];
            }
        }
    }
    
    // Sort by timestamp descending
    usort($evidence_images, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    echo json_encode([
        'success' => true,
        'images' => $evidence_images
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>