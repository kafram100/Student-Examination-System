<?php
require_once '../db.php';
require_once '../auth.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

if (($_SESSION['role'] ?? '') !== 'lecturer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
if (!$session_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
    exit;
}

$lecturer_id = (int)($_SESSION['user_id'] ?? 0);

try {
    $stmt = $pdo->prepare("\n        UPDATE exam_sessions_proctoring esp\n        JOIN attempts a ON esp.exam_attempt_id = a.id\n        JOIN exams e ON a.exam_id = e.id\n        SET esp.proctoring_status = 'flagged'\n        WHERE esp.id = ? AND e.user_id = ?\n    ");
    $stmt->execute([$session_id, $lecturer_id]);

    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Session not found or already flagged']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Flag session API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to flag session']);
}
?>
