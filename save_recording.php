<?php
require_once 'db.php';
require_once 'auth.php';

// Only allow authenticated users to save recordings
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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    // Validate exam attempt ID
    $exam_attempt_id = filter_input(INPUT_POST, 'exam_attempt_id', FILTER_VALIDATE_INT);
    if (!$exam_attempt_id) {
        throw new Exception('Invalid exam attempt ID');
    }

    // Check if this recording belongs to the current user
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

    if (isset($_FILES['recording'])) {
        $file = $_FILES['recording'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Validate file type (only allow video formats)
        $allowed_types = ['video/webm', 'video/mp4', 'video/mov', 'video/avi'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Invalid file type. Only video files are allowed.');
        }
        
        // Validate file size (limit to 100MB)
        if ($file['size'] > 100 * 1024 * 1024) {
            throw new Exception('File too large. Maximum size is 100MB.');
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/uploads/proctoring/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'proctoring_' . $exam_attempt_id . '_' . time() . '_' . uniqid() . '.webm';
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to save recording file');
        }
        
        // Update existing recording info in database
        $stmt = $pdo->prepare("
            UPDATE exam_sessions_proctoring 
            SET video_recording_path = ?
            WHERE exam_attempt_id = ?
        ");
        
        $result = $stmt->execute([
            $filepath,
            $exam_attempt_id
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Recording saved successfully',
                'filename' => $filename
            ]);
        } else {
            throw new Exception('Failed to save recording info to database');
        }
    } else {
        throw new Exception('No recording file provided');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    error_log('Proctoring recording error: ' . $e->getMessage());
}

function getLecturerIdFromExam($exam_attempt_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT e.user_id 
        FROM attempts ea 
        JOIN exams e ON ea.exam_id = e.id 
        WHERE ea.id = ?
    ");
    $stmt->execute([$exam_attempt_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['user_id'] : null;
}
?>