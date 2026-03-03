<?php
require_once 'db.php';
require_once 'auth.php';

// Allow students or authenticated staff
if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_index'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['token']) || !hash_equals($_SESSION['csrf_token'], $_POST['token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    // Set the proctoring session variable
    $_SESSION['proctoring_enabled'] = true;
    
    // Get exam attempt ID
    $exam_attempt_id = $_SESSION['exam_attempt_id'] ?? null;
    $exam_id = $_SESSION['exam_id'] ?? null;
    $student_id = $_SESSION['user_id'] ?? 0;
    
    if ($exam_attempt_id && $exam_id && $student_id !== null) {
        // Fetch exam details to get the lecturer ID
        $stmt = $pdo->prepare("SELECT user_id FROM exams WHERE id = ?");
        $stmt->execute([$exam_id]);
        $lecturer_id = $stmt->fetchColumn();
        
        // Create a proctoring session record
        $stmt = $pdo->prepare("
            INSERT INTO exam_sessions_proctoring 
            (exam_attempt_id, student_id, lecturer_id, start_time, proctoring_status) 
            VALUES (?, ?, ?, NOW(), 'active')
        ");
        
        $result = $stmt->execute([$exam_attempt_id, $student_id, $lecturer_id]);
        
        if (!$result) {
            throw new Exception('Failed to create proctoring session');
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Proctoring session enabled']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log('Enable proctoring error: ' . $e->getMessage());
}
?>