<?php
require_once 'db.php';
require_once 'auth.php';

// Only allow authenticated users to log activities
if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_index'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

try {
    // Validate required fields
    $required_fields = ['type', 'description', 'timestamp', 'exam_attempt_id'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate exam attempt ID
    $exam_attempt_id = filter_var($input['exam_attempt_id'], FILTER_VALIDATE_INT);
    if (!$exam_attempt_id) {
        throw new Exception('Invalid exam attempt ID');
    }
    
    // Check if this activity belongs to the current user
    if (isset($_SESSION['student_index'])) {
        $stmt = $pdo->prepare("SELECT id FROM attempts WHERE id = ? AND student_index = ?");
        $stmt->execute([$exam_attempt_id, $_SESSION['student_index']]);
        $user_id = 0;
    } else {
        $stmt = $pdo->prepare("
            SELECT ea.id 
            FROM attempts ea 
            JOIN exams e ON ea.exam_id = e.id 
            WHERE ea.id = ? AND e.user_id = ?
        ");
        $stmt->execute([$exam_attempt_id, $_SESSION['user_id']]);
        $user_id = $_SESSION['user_id'];
    }
    
    if (!$stmt->rowCount()) {
        throw new Exception('Unauthorized access to exam attempt');
    }
    
    // Insert activity log
    $stmt = $pdo->prepare("
        INSERT INTO exam_security_logs 
        (exam_attempt_id, user_id, activity_type, description, timestamp, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $exam_attempt_id,
        $user_id,
        $input['type'],
        $input['description'],
        $input['timestamp'],
        $_SERVER['REMOTE_ADDR']
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Activity logged successfully'
        ]);
    } else {
        throw new Exception('Failed to log activity');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    error_log('Proctoring activity log error: ' . $e->getMessage());
}
?>