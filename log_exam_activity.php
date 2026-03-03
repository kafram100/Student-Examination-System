<?php
require_once 'db.php';
session_start();

// Set CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Only allow students or authenticated users
if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_index'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Check if required fields are present
if (!isset($_POST['activity_type']) || !isset($_POST['description']) || !isset($_POST['exam_attempt_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $activity_type = $_POST['activity_type'];
    $description = $_POST['description'];
    $exam_attempt_id = (int)$_POST['exam_attempt_id'];
    $user_id = (int)$_POST['user_id'];
    $severity = $_POST['severity'] ?? 'medium';
    
    // Validate severity level
    $valid_severities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($severity, $valid_severities)) {
        $severity = 'medium';
    }
    
    // Verify that the exam attempt belongs to the user taking the exam
    $stmt = $pdo->prepare("SELECT student_index FROM attempts WHERE id = ? AND student_index = ?");
    $stmt->execute([$exam_attempt_id, $_SESSION['student_index']]);
    $attempt = $stmt->fetch();
    
    if (!$attempt) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid exam attempt']);
        exit;
    }
    
    // Insert the activity log
    $stmt = $pdo->prepare("
        INSERT INTO exam_activity_logs 
        (exam_attempt_id, user_id, activity_type, description, severity, ip_address, user_agent, additional_data) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $additional_data = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'session_id' => session_id()
    ]);
    
    $stmt->execute([$exam_attempt_id, $user_id, $activity_type, $description, $severity, $ip_address, $user_agent, $additional_data]);
    
    // Return success response
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error logging exam activity: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to log activity']);
}
?>