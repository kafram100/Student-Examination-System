<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow authenticated users to log activities
if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_index'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

try {
    $required_fields = ['type', 'description', 'exam_attempt_id'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception('Missing required field: ' . $field);
        }
    }

    $exam_attempt_id = filter_var($input['exam_attempt_id'], FILTER_VALIDATE_INT);
    if (!$exam_attempt_id) {
        throw new Exception('Invalid exam attempt ID');
    }

    $activity_type = trim((string)$input['type']);
    $description = trim((string)$input['description']);
    $timestamp = isset($input['timestamp']) ? (string)$input['timestamp'] : date('Y-m-d H:i:s');

    $severity_map = [
        'tab_switch' => 'high',
        'window_blur' => 'medium',
        'window_unfocused' => 'medium',
        'print_attempt' => 'medium',
        'right_click' => 'low',
        'dev_tools' => 'high',
        'copy_attempt' => 'medium',
        'paste_attempt' => 'medium',
        'screenshot_attempt' => 'high',
        'exit_fullscreen' => 'high',
        'camera_disabled' => 'critical',
        'multiple_device' => 'high'
    ];
    $severity = $severity_map[$activity_type] ?? 'medium';

    // Resolve exam attempt ownership
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
        throw new Exception('Invalid exam attempt');
    }

    if (isset($_SESSION['student_index'])) {
        if ((string)$attempt['student_index'] !== (string)$_SESSION['student_index']) {
            throw new Exception('Unauthorized access to exam attempt');
        }
    } elseif (isset($_SESSION['user_id']) && (int)$attempt['lecturer_id'] !== (int)$_SESSION['user_id']) {
        throw new Exception('Unauthorized access to exam attempt');
    }

    $actor_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($actor_user_id < 1) {
        $actor_user_id = (int)$attempt['lecturer_id'];
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    // Log into proctoring/security log table
    $security_stmt = $pdo->prepare('
        INSERT INTO exam_security_logs
        (exam_attempt_id, user_id, activity_type, description, timestamp, ip_address, severity)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $security_stmt->execute([
        $exam_attempt_id,
        $actor_user_id,
        $activity_type,
        $description,
        $timestamp,
        $ip_address,
        $severity
    ]);

    // Mirror into exam activity logs for the activity report page.
    $activity_stmt = $pdo->prepare('
        INSERT INTO exam_activity_logs
        (exam_attempt_id, user_id, activity_type, description, severity, timestamp, ip_address, user_agent, additional_data)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)
    ');
    $activity_stmt->execute([
        $exam_attempt_id,
        $actor_user_id,
        $activity_type,
        $description,
        $severity,
        $ip_address,
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        json_encode(['source' => 'log_activity', 'timestamp' => date('Y-m-d H:i:s')])
    ]);

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
        'message' => 'Activity logged successfully',
        'incident_created' => $incident_created
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    error_log('Proctoring activity log error: ' . $e->getMessage());
}
?>
