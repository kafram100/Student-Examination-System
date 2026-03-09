<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Allow students or authenticated staff
if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_index'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $exam_attempt_id = isset($_SESSION['exam_attempt_id']) ? (int)$_SESSION['exam_attempt_id'] : 0;
    $exam_id = isset($_SESSION['exam_id']) ? (int)$_SESSION['exam_id'] : 0;

    if ($exam_attempt_id <= 0 || $exam_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing exam session context']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT user_id, exam_type FROM exams WHERE id = ?');
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Exam not found']);
        exit;
    }

    if (!requiresProctoringForExamType($exam['exam_type'] ?? '')) {
        unset($_SESSION['proctoring_enabled'], $_SESSION['proctoring_exam_id']);
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Proctoring is only available for Exam, Mid-semester, and Quiz assessments.']);
        exit;
    }

    $_SESSION['proctoring_enabled'] = true;
    $_SESSION['proctoring_exam_id'] = $exam_id;

    $lecturer_id = (int)($exam['user_id'] ?? 0);
    $student_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    $stmt = $pdo->prepare("SELECT id FROM exam_sessions_proctoring WHERE exam_attempt_id = ? AND proctoring_status = 'active' LIMIT 1");
    $stmt->execute([$exam_attempt_id]);
    $active_session_id = (int)($stmt->fetchColumn() ?: 0);

    if ($active_session_id <= 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO exam_sessions_proctoring
            (exam_attempt_id, student_id, lecturer_id, start_time, proctoring_status)
            VALUES (?, ?, ?, NOW(), 'active')"
        );

        $result = $stmt->execute([$exam_attempt_id, $student_id, $lecturer_id]);
        if (!$result) {
            throw new Exception('Failed to create proctoring session');
        }
    }

    echo json_encode(['success' => true, 'message' => 'Proctoring session enabled']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to enable proctoring session']);
    error_log('Enable proctoring error: ' . $e->getMessage());
}
?>
