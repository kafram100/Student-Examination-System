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

$session_ids = [];
$raw_session_ids = $_POST['session_ids'] ?? '';

if (is_string($raw_session_ids) && $raw_session_ids !== '') {
    $decoded = json_decode($raw_session_ids, true);
    if (is_array($decoded)) {
        $session_ids = $decoded;
    }
}

if (empty($session_ids) && isset($_POST['session_id'])) {
    $session_ids = [$_POST['session_id']];
}

$session_ids = array_values(array_unique(array_filter(array_map('intval', (array)$session_ids), function ($id) {
    return $id > 0;
})));

if (empty($session_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid session IDs provided']);
    exit;
}

$lecturer_id = (int)($_SESSION['user_id'] ?? 0);

$makePlaceholders = function (int $count): string {
    return implode(',', array_fill(0, $count, '?'));
};

try {
    $session_placeholder_sql = $makePlaceholders(count($session_ids));

    // Resolve only selected sessions owned by this lecturer.
    $resolve_sql = "
        SELECT DISTINCT esp.id, esp.exam_attempt_id
        FROM exam_sessions_proctoring esp
        JOIN attempts a ON esp.exam_attempt_id = a.id
        JOIN exams e ON a.exam_id = e.id
        WHERE e.user_id = ?
        AND esp.id IN ($session_placeholder_sql)
    ";
    $resolve_params = array_merge([$lecturer_id], $session_ids);
    $resolve_stmt = $pdo->prepare($resolve_sql);
    $resolve_stmt->execute($resolve_params);
    $resolved_rows = $resolve_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($resolved_rows)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No matching sessions found']);
        exit;
    }

    $resolved_session_ids = array_values(array_unique(array_filter(array_map('intval', array_column($resolved_rows, 'id')), function ($id) {
        return $id > 0;
    })));

    if (empty($resolved_session_ids)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No matching sessions found']);
        exit;
    }

    $attempt_ids = array_values(array_unique(array_filter(array_map('intval', array_column($resolved_rows, 'exam_attempt_id')), function ($id) {
        return $id > 0;
    })));

    $deleted_logs = 0;
    $deleted_sessions = 0;

    $pdo->beginTransaction();

    if (!empty($attempt_ids)) {
        $attempt_placeholder_sql = $makePlaceholders(count($attempt_ids));
        $delete_logs_sql = "DELETE FROM exam_security_logs WHERE exam_attempt_id IN ($attempt_placeholder_sql)";
        $delete_logs_stmt = $pdo->prepare($delete_logs_sql);
        $delete_logs_stmt->execute($attempt_ids);
        $deleted_logs = $delete_logs_stmt->rowCount();
    }

    $resolved_session_placeholder_sql = $makePlaceholders(count($resolved_session_ids));
    $delete_sessions_sql = "
        DELETE esp
        FROM exam_sessions_proctoring esp
        JOIN attempts a ON esp.exam_attempt_id = a.id
        JOIN exams e ON a.exam_id = e.id
        WHERE e.user_id = ?
        AND esp.id IN ($resolved_session_placeholder_sql)
    ";
    $delete_session_params = array_merge([$lecturer_id], $resolved_session_ids);
    $delete_sessions_stmt = $pdo->prepare($delete_sessions_sql);
    $delete_sessions_stmt->execute($delete_session_params);
    $deleted_sessions = $delete_sessions_stmt->rowCount();

    $pdo->commit();

    $deleted_files = 0;
    $upload_dir = __DIR__ . '/../uploads/proctoring/';

    if (!empty($attempt_ids) && is_dir($upload_dir)) {
        foreach ($attempt_ids as $attempt_id) {
            $patterns = [
                $upload_dir . 'proctoring_img_' . $attempt_id . '_*',
                $upload_dir . 'proctoring_' . $attempt_id . '_*'
            ];

            foreach ($patterns as $pattern) {
                $matches = glob($pattern) ?: [];
                foreach ($matches as $file_path) {
                    if (is_file($file_path) && @unlink($file_path)) {
                        $deleted_files++;
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'deleted_sessions' => $deleted_sessions,
        'deleted_logs' => $deleted_logs,
        'deleted_files' => $deleted_files,
        'selected_sessions' => count($resolved_session_ids)
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Delete proctoring records API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete proctoring records']);
}
?>
