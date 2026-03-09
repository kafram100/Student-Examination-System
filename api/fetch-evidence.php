<?php
require_once '../db.php';
require_once '../auth.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

// Only allow lecturers to access this
if (($_SESSION['role'] ?? '') !== 'lecturer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$exam_attempt_id = filter_input(INPUT_GET, 'exam_attempt_id', FILTER_VALIDATE_INT);

if (!$student_id && !$exam_attempt_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'student_id or exam_attempt_id is required']);
    exit;
}

try {
    $logs = [];
    $attempt_ids = [];

    if ($exam_attempt_id) {
        $attempt_ids[] = (int)$exam_attempt_id;

        $stmt = $pdo->prepare('
            SELECT esl.*, a.student_fullname, a.student_index
            FROM exam_security_logs esl
            JOIN attempts a ON esl.exam_attempt_id = a.id
            WHERE esl.exam_attempt_id = ?
            ORDER BY esl.timestamp DESC
            LIMIT 100
        ');
        $stmt->execute([$exam_attempt_id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($student_id && !$exam_attempt_id) {
        // Resolve student index for the selected student
        $student_stmt = $pdo->prepare('SELECT student_index FROM users WHERE id = ?');
        $student_stmt->execute([$student_id]);
        $student_row = $student_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student_row) {
            // Fallback: map via proctoring sessions -> attempts
            $attempt_stmt = $pdo->prepare('
                SELECT a.student_index
                FROM attempts a
                JOIN exam_sessions_proctoring esp ON a.id = esp.exam_attempt_id
                WHERE esp.student_id = ?
                LIMIT 1
            ');
            $attempt_stmt->execute([$student_id]);
            $student_row = $attempt_stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$student_row || empty($student_row['student_index'])) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Student not found']);
            exit;
        }

        $student_index = $student_row['student_index'];

        $stmt = $pdo->prepare('
            SELECT esl.*, a.student_fullname, a.student_index
            FROM exam_security_logs esl
            JOIN attempts a ON esl.exam_attempt_id = a.id
            WHERE a.student_index = ?
            ORDER BY esl.timestamp DESC
            LIMIT 100
        ');
        $stmt->execute([$student_index]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as $log) {
            $id = isset($log['exam_attempt_id']) ? (int)$log['exam_attempt_id'] : 0;
            if ($id > 0) {
                $attempt_ids[] = $id;
            }
        }
    }

    $attempt_ids = array_values(array_unique(array_filter(array_map('intval', $attempt_ids), function ($id) {
        return $id > 0;
    })));

    $evidence_images = [];
    $evidence_recordings = [];
    $upload_dir = __DIR__ . '/../uploads/proctoring/';

    if (is_dir($upload_dir)) {
        $files = scandir($upload_dir);

        foreach ($files as $file) {
            // Image evidence files
            if (preg_match('/^proctoring_img_(\d+)_/', $file, $match)) {
                $attempt_id_from_file = (int)$match[1];
                if (!empty($attempt_ids) && !in_array($attempt_id_from_file, $attempt_ids, true)) {
                    continue;
                }

                $full_file_path = $upload_dir . $file;
                if (!is_file($full_file_path)) {
                    continue;
                }

                $file_path = 'uploads/proctoring/' . $file;
                $file_time = @filemtime($full_file_path);

                $log_entry = null;
                foreach ($logs as $log) {
                    if ((int)($log['exam_attempt_id'] ?? 0) === $attempt_id_from_file) {
                        $log_entry = $log;
                        break;
                    }
                }

                if (!$log_entry) {
                    $log_entry = [
                        'activity_type' => 'suspicious_activity',
                        'description' => 'Cheating evidence captured',
                        'timestamp' => date('Y-m-d H:i:s', $file_time ?: time()),
                        'severity' => 'medium'
                    ];
                }

                $evidence_images[] = [
                    'path' => $file_path,
                    'exam_attempt_id' => $attempt_id_from_file,
                    'activity_type' => (string)($log_entry['activity_type'] ?? 'suspicious_activity'),
                    'description' => (string)($log_entry['description'] ?? 'Cheating evidence captured'),
                    'timestamp' => (string)($log_entry['timestamp'] ?? date('Y-m-d H:i:s', $file_time ?: time())),
                    'severity' => (string)($log_entry['severity'] ?? 'medium')
                ];

                continue;
            }

            // Audio/video evidence files (contains microphone audio)
            if (!preg_match('/^proctoring_(\d+)_/', $file, $match)) {
                continue;
            }

            $attempt_id_from_file = (int)$match[1];
            if (!empty($attempt_ids) && !in_array($attempt_id_from_file, $attempt_ids, true)) {
                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($extension, ['webm', 'mp4', 'mov', 'avi', 'mkv'], true)) {
                continue;
            }

            $full_file_path = $upload_dir . $file;
            if (!is_file($full_file_path)) {
                continue;
            }

            $file_time = @filemtime($full_file_path);
            $evidence_recordings[] = [
                'path' => 'uploads/proctoring/' . $file,
                'exam_attempt_id' => $attempt_id_from_file,
                'timestamp' => date('Y-m-d H:i:s', $file_time ?: time()),
                'filename' => $file
            ];
        }
    }

    usort($evidence_images, function ($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    usort($evidence_recordings, function ($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    $images_by_attempt = [];
    foreach ($evidence_images as $img) {
        $attempt_id = (int)($img['exam_attempt_id'] ?? 0);
        if ($attempt_id < 1) {
            continue;
        }
        if (!isset($images_by_attempt[$attempt_id])) {
            $images_by_attempt[$attempt_id] = [];
        }
        $images_by_attempt[$attempt_id][] = $img['path'];
    }

    $recordings_by_attempt = [];
    foreach ($evidence_recordings as $recording) {
        $attempt_id = (int)($recording['exam_attempt_id'] ?? 0);
        if ($attempt_id < 1) {
            continue;
        }
        if (!isset($recordings_by_attempt[$attempt_id])) {
            $recordings_by_attempt[$attempt_id] = [];
        }
        $recordings_by_attempt[$attempt_id][] = $recording;
    }

    $activities = [];
    foreach ($logs as $log) {
        $attempt_id = (int)($log['exam_attempt_id'] ?? 0);
        $activities[] = [
            'exam_attempt_id' => $attempt_id,
            'activity_type' => (string)($log['activity_type'] ?? 'activity'),
            'description' => (string)($log['description'] ?? 'No details available.'),
            'timestamp' => (string)($log['timestamp'] ?? ''),
            'severity' => (string)($log['severity'] ?? 'medium'),
            'images' => array_slice($images_by_attempt[$attempt_id] ?? [], 0, 4),
            'recordings' => array_slice($recordings_by_attempt[$attempt_id] ?? [], 0, 2)
        ];
    }

    if (empty($activities) && !empty($evidence_images)) {
        foreach ($evidence_images as $img) {
            $attempt_id = (int)($img['exam_attempt_id'] ?? 0);
            $activities[] = [
                'exam_attempt_id' => $attempt_id,
                'activity_type' => (string)($img['activity_type'] ?? 'suspicious_activity'),
                'description' => (string)($img['description'] ?? 'Cheating evidence captured'),
                'timestamp' => (string)($img['timestamp'] ?? ''),
                'severity' => (string)($img['severity'] ?? 'medium'),
                'images' => [$img['path']],
                'recordings' => array_slice($recordings_by_attempt[$attempt_id] ?? [], 0, 2)
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'images' => $evidence_images,
        'recordings' => $evidence_recordings
    ]);
} catch (Throwable $e) {
    error_log('Fetch evidence API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch evidence'
    ]);
}
?>
