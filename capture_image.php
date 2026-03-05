<?php
require_once 'db.php';
require_once 'auth.php';

// Only allow authenticated users to save images
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

    // Validate image data
    if (!isset($_POST['image_data']) || empty($_POST['image_data'])) {
        throw new Exception('No image data provided');
    }

    // Decode base64 image data
    $image_data = $_POST['image_data'];
    $image_parts = explode(";base64,", $image_data);
    if (count($image_parts) < 2) {
        throw new Exception('Invalid image data format');
    }

    $image_type = str_replace('data:image/', '', $image_parts[0]);
    $image_base64 = $image_parts[1];

    // Validate image type
    $allowed_types = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($image_type), $allowed_types)) {
        throw new Exception('Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.');
    }

    // Decode the base64 image
    $image_decoded = base64_decode($image_base64);
    if ($image_decoded === false) {
        throw new Exception('Failed to decode image data');
    }

    // Validate file size (limit to 10MB)
    if (strlen($image_decoded) > 10 * 1024 * 1024) {
        throw new Exception('Image too large. Maximum size is 10MB.');
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/uploads/proctoring/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $filename = 'proctoring_img_' . $exam_attempt_id . '_' . time() . '_' . uniqid() . '.' . $image_type;
    $filepath = $upload_dir . $filename;

    // Save the image file
    if (!file_put_contents($filepath, $image_decoded)) {
        throw new Exception('Failed to save image file');
    }

    // Log the incident in the database
    $stmt = $pdo->prepare("
        INSERT INTO exam_security_logs 
        (exam_attempt_id, user_id, activity_type, description, timestamp, ip_address, severity) 
        VALUES (?, ?, ?, ?, NOW(), ?, ?)
    ");

    $activity_type = $_POST['activity_type'] ?? 'suspicious_activity';
    $description = $_POST['description'] ?? 'Suspicious activity detected';
    $severity = $_POST['severity'] ?? 'medium';
    $user_id = $_SESSION['user_id'] ?? 0;

    $result = $stmt->execute([
        $exam_attempt_id,
        $user_id,
        $activity_type,
        $description,
        $_SERVER['REMOTE_ADDR'],
        $severity
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Image captured and saved successfully',
            'filename' => $filename
        ]);
    } else {
        throw new Exception('Failed to save image info to database');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    error_log('Proctoring image capture error: ' . $e->getMessage());
}
?>