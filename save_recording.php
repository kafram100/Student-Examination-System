<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

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

$session_token = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
$request_token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

// Verify CSRF token
if ($session_token === '' || $request_token === '' || !hash_equals($session_token, $request_token)) {
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
        $stmt = $pdo->prepare('SELECT id FROM attempts WHERE id = ? AND student_index = ?');
        $stmt->execute([$exam_attempt_id, $_SESSION['student_index']]);
    } else {
        $stmt = $pdo->prepare('
            SELECT ea.id
            FROM attempts ea
            JOIN exams e ON ea.exam_id = e.id
            WHERE ea.id = ? AND e.user_id = ?
        ');
        $stmt->execute([$exam_attempt_id, $_SESSION['user_id']]);
    }

    if (!$stmt->rowCount()) {
        throw new Exception('Unauthorized access to exam attempt');
    }

    if (!isset($_FILES['recording']) || !is_array($_FILES['recording'])) {
        throw new Exception('No recording file provided');
    }

    $file = $_FILES['recording'];

    // Validate file
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . (int)($file['error'] ?? -1));
    }

    // Validate extension and MIME type
    $allowed_extensions = ['webm', 'mp4', 'mov', 'avi', 'mkv'];
    $original_name = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if ($extension === '') {
        $extension = 'webm';
    }

    if (!in_array($extension, $allowed_extensions, true)) {
        throw new Exception('Invalid recording format. Allowed: webm, mp4, mov, avi, mkv');
    }

    $file_type = @mime_content_type($file['tmp_name']);
    if (
        is_string($file_type)
        && $file_type !== ''
        && strpos($file_type, 'video/') !== 0
        && $file_type !== 'application/octet-stream'
    ) {
        throw new Exception('Invalid file type. Video recording expected.');
    }

    // Validate file size (limit to 100MB)
    if ((int)($file['size'] ?? 0) > 100 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 100MB.');
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/uploads/proctoring/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        throw new Exception('Failed to create upload directory');
    }

    // Generate unique filename
    $filename = 'proctoring_' . $exam_attempt_id . '_' . time() . '_' . uniqid('', true) . '.' . $extension;
    $relative_path = 'uploads/proctoring/' . $filename;
    $absolute_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $absolute_path)) {
        throw new Exception('Failed to save recording file');
    }

    // Update recording reference in database (latest clip)
    $stmt = $pdo->prepare('
        UPDATE exam_sessions_proctoring
        SET video_recording_path = ?
        WHERE exam_attempt_id = ?
    ');

    $result = $stmt->execute([$relative_path, $exam_attempt_id]);

    if (!$result) {
        throw new Exception('Failed to save recording info to database');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Recording saved successfully',
        'filename' => $filename,
        'path' => $relative_path
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    error_log('Proctoring recording error: ' . $e->getMessage());
}
?>
