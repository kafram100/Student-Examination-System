<?php
require_once 'db.php';
session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Only allow students or authenticated users
if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_index'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$session_token = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
$request_token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
if ($session_token === '' || $request_token === '' || !hash_equals($session_token, $request_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_POST['activity_type']) || !isset($_POST['description']) || !isset($_POST['exam_attempt_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $activity_type = trim((string)$_POST['activity_type']);
    $description = trim((string)$_POST['description']);
    $exam_attempt_id = (int)$_POST['exam_attempt_id'];
    $submitted_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $severity = strtolower(trim((string)($_POST['severity'] ?? 'medium')));

    if ($exam_attempt_id < 1 || $activity_type === '') {
        throw new Exception('Invalid activity payload');
    }

    $valid_severities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($severity, $valid_severities, true)) {
        $severity = 'medium';
    }

    // Resolve exam attempt and ownership
    $attempt_stmt = $pdo->prepare('
        SELECT a.id, a.student_index, a.student_fullname, e.user_id AS lecturer_id
        FROM attempts a
        JOIN exams e ON a.exam_id = e.id
        WHERE a.id = ?
        LIMIT 1
    ');
    $attempt_stmt->execute([$exam_attempt_id]);
    $attempt = $attempt_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid exam attempt']);
        exit;
    }

    if (isset($_SESSION['student_index'])) {
        if ((string)$attempt['student_index'] !== (string)$_SESSION['student_index']) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid exam attempt']);
            exit;
        }
    } elseif (isset($_SESSION['user_id'])) {
        if ((int)$attempt['lecturer_id'] !== (int)$_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid exam attempt']);
            exit;
        }
    }

    $actor_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : $submitted_user_id;
    if ($actor_user_id < 1) {
        $actor_user_id = (int)$attempt['lecturer_id'];
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $additional_data = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'session_id' => session_id()
    ]);

    // Insert activity log
    $insert_log_stmt = $pdo->prepare('
        INSERT INTO exam_activity_logs
        (exam_attempt_id, user_id, activity_type, description, severity, ip_address, user_agent, additional_data)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $insert_log_stmt->execute([
        $exam_attempt_id,
        $actor_user_id,
        $activity_type,
        $description,
        $severity,
        $ip_address,
        $user_agent,
        $additional_data
    ]);

    // Auto-create cheating incidents for suspicious events.
    $high_risk_types = ['tab_switch', 'window_blur', 'dev_tools', 'screenshot_attempt', 'multiple_device', 'camera_disabled', 'exit_fullscreen'];
    $should_create_incident = in_array($severity, ['high', 'critical'], true) || in_array($activity_type, $high_risk_types, true);
    $incident_created = false;

    if ($should_create_incident) {
        $incident_type_map = [
            'tab_switch' => 'tab_switching',
            'window_blur' => 'tab_switching',
            'window_unfocused' => 'tab_switching',
            'screenshot_attempt' => 'screen_capture',
            'multiple_device' => 'multiple_device',
            'camera_disabled' => 'audio_anomaly',
            'dev_tools' => 'suspicious_movement',
            'exit_fullscreen' => 'suspicious_movement'
        ];

        $incident_type = $incident_type_map[$activity_type] ?? 'suspicious_movement';
        $confidence_level = ($severity === 'critical' || in_array($activity_type, ['camera_disabled', 'multiple_device'], true)) ? 'high' : (($severity === 'high') ? 'high' : 'medium');

        // De-duplicate repeated events within a short window.
        $dedupe_stmt = $pdo->prepare('
            SELECT id
            FROM cheating_incidents
            WHERE exam_attempt_id = ?
            AND incident_type = ?
            AND status IN (\'pending\', \'reviewed\')
            AND incident_timestamp >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
            LIMIT 1
        ');
        $dedupe_stmt->execute([$exam_attempt_id, $incident_type]);

        if (!$dedupe_stmt->fetch(PDO::FETCH_ASSOC)) {
            $incident_stmt = $pdo->prepare('
                INSERT INTO cheating_incidents
                (exam_attempt_id, user_id, incident_type, confidence_level, status, notes)
                VALUES (?, ?, ?, ?, \'pending\', ?)
            ');
            $incident_stmt->execute([
                $exam_attempt_id,
                $actor_user_id,
                $incident_type,
                $confidence_level,
                $description
            ]);
            $incident_created = true;
        }
    }

    echo json_encode([
        'success' => true,
        'incident_created' => $incident_created
    ]);
} catch (Throwable $e) {
    error_log('Error logging exam activity: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to log activity']);
}
?>
