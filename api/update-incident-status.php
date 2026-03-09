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

$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$incident_id = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);
$status = strtolower(trim((string)($_POST['status'] ?? '')));

$allowed_statuses = ['confirmed', 'dismissed', 'reviewed'];
if (!$incident_id || !in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request payload']);
    exit;
}

$lecturer_id = (int)($_SESSION['user_id'] ?? 0);

try {
    $stmt = $pdo->prepare('
        UPDATE cheating_incidents ci
        JOIN attempts a ON ci.exam_attempt_id = a.id
        JOIN exams e ON a.exam_id = e.id
        SET
            ci.status = ?,
            ci.resolved_by = ?,
            ci.resolved_at = NOW()
        WHERE ci.id = ?
        AND e.user_id = ?
    ');

    $stmt->execute([$status, $lecturer_id, $incident_id, $lecturer_id]);

    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Incident not found or already updated']);
        exit;
    }

    echo json_encode(['success' => true, 'status' => $status]);
} catch (Throwable $e) {
    error_log('Update incident status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update incident status']);
}
?>
