<?php
require_once 'db.php';
require_once 'auth.php';

requireLogin();

// Only allow lecturers to access this page
if ($_SESSION['role'] !== 'lecturer') {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$active_sessions = [];
$recent_logs = [];
$load_error = null;

try {
    // Get all active exam sessions for this lecturer
    $stmt = $pdo->prepare("
        SELECT 
            esp.id,
            esp.exam_attempt_id,
            esp.student_id,
            esp.start_time,
            esp.suspicious_activity_count,
            esp.proctoring_status,
            esp.video_recording_path,
            CONCAT(a.student_fullname, ' - ', a.student_index) as student_username,
            e.title as exam_title
        FROM exam_sessions_proctoring esp
        JOIN attempts a ON esp.exam_attempt_id = a.id
        JOIN exams e ON a.exam_id = e.id
        WHERE e.user_id = ?
        AND e.exam_type IN ('Exam', 'Mid-semester', 'Quiz')
        AND esp.proctoring_status IN ('active', 'flagged')
        ORDER BY esp.start_time DESC
    ");
    $stmt->execute([$user_id]);
    $active_sessions = $stmt->fetchAll();

    // Get recent security logs
    $stmt = $pdo->prepare("
        SELECT 
            esl.*,
            CONCAT(a.student_fullname, ' - ', a.student_index) as student_username,
            e.title as exam_title
        FROM exam_security_logs esl
        JOIN attempts a ON esl.exam_attempt_id = a.id
        JOIN exams e ON a.exam_id = e.id
        WHERE e.user_id = ?
        AND e.exam_type IN ('Exam', 'Mid-semester', 'Quiz')
        ORDER BY esl.timestamp DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $recent_logs = $stmt->fetchAll();
} catch (Throwable $e) {
    $load_error = 'Unable to load proctoring data right now. Please refresh and try again.';
    error_log('Proctoring monitor error: ' . $e->getMessage());
}

$total_alerts = array_sum(array_map('intval', array_column($active_sessions, 'suspicious_activity_count')));

$printable_sessions = array_map(function (array $session): array {
    $student_label = trim((string)($session['student_username'] ?? ''));
    if ($student_label === '' || $student_label === '-') {
        $student_label = 'Unknown Student';
    }

    $exam_title = trim((string)($session['exam_title'] ?? ''));
    if ($exam_title === '') {
        $exam_title = 'Unknown Exam';
    }

    return [
        'session_id' => (int)($session['id'] ?? 0),
        'exam_attempt_id' => (int)($session['exam_attempt_id'] ?? 0),
        'student_id' => (int)($session['student_id'] ?? 0),
        'student_name' => $student_label,
        'exam_title' => $exam_title,
    ];
}, $active_sessions);

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proctoring Monitor - Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="assets/css/theme.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        .session-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all var(--transition-fast);
            background: #ffffff;
            color: #1e293b;
        }
        
        .session-card.flagged {
            border-left: 4px solid var(--danger);
            background-color: rgba(239, 68, 68, 0.05);
        }
        
        .session-card.active {
            border-left: 4px solid var(--success);
        }
        
        .activity-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .activity-indicator.active {
            background-color: var(--success);
            animation: pulse 1.5s infinite;
        }
        
        .activity-indicator.inactive {
            background-color: var(--gray-400);
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }
        
        .security-log-item {
            padding: 0.75rem;
            border-left: 3px solid var(--gray-300);
            margin-bottom: 0.5rem;
        }
        
        .security-log-item.medium { border-left-color: var(--warning); }
        .security-log-item.high { border-left-color: var(--danger); }
        .security-log-item.critical { border-left-color: #dc2626; background-color: rgba(220, 38, 38, 0.1); }
        
        .video-preview {
            width: 100%;
            max-width: 320px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            background-color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 4/3;
        }
        
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active { background-color: rgba(16, 185, 129, 0.15); color: var(--success); }
        .status-flagged { background-color: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .status-violated { background-color: rgba(239, 68, 68, 0.15); color: var(--danger); }

        .card-modern {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 1.5rem !important;
            color: #1e293b;
        }
        
        .card-header-modern {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: transparent;
            color: #1e293b;
        }
        
        .card-body {
            padding: 1.5rem;
        }

        .navbar-modern .btn-outline-light {
            color: #e2e8f0 !important;
            border-color: rgba(226, 232, 240, 0.7) !important;
            background: transparent !important;
            font-weight: 600;
        }

        .navbar-modern .btn-outline-light i {
            color: inherit !important;
        }

        .navbar-modern .btn-outline-light:hover,
        .navbar-modern .btn-outline-light:focus {
            color: #ffffff !important;
            border-color: #f8fafc !important;
            background: rgba(248, 250, 252, 0.12) !important;
        }
        /* Strict Dark Mode Overrides */
        body.theme-dark {
            background: #0f172a !important;
            color: #f1f5f9 !important;
        }
        
        body.theme-dark .navbar-modern {
            background: #1e293b !important;
            border-bottom: 1px solid #334155 !important;
        }

        body.theme-dark .navbar-brand,
        body.theme-dark .navbar-text,
        body.theme-dark .page-title,
        body.theme-dark .page-subtitle {
            color: #f1f5f9 !important;
            -webkit-text-fill-color: #f1f5f9 !important;
            background: none !important;
        }

        body.theme-dark .card-modern,
        body.theme-dark .session-card,
        body.theme-dark .empty-state {
            background: #1e293b !important;
            border-color: #334155 !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.2) !important;
        }

        body.theme-dark .card-header-modern {
            background: transparent !important;
            border-bottom-color: #334155 !important;
            color: #f1f5f9 !important;
        }
        
        body.theme-dark .text-muted {
            color: #94a3b8 !important;
        }
        
        body.theme-dark .border-top {
            border-top-color: #334155 !important;
        }

        body.theme-dark .security-log-item {
            border-left-color: #475569 !important;
        }
        
        body.theme-dark .btn-outline-secondary,
        body.theme-dark .btn-outline-light {
            color: #f1f5f9 !important;
            border-color: #475569 !important;
        }
        
        body.theme-dark .btn-outline-secondary:hover,
        body.theme-dark .btn-outline-light:hover {
            background: #334155 !important;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-modern sticky-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard-fill me-2"></i>Exam System
            </a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <form method="POST" action="logout.php" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <button type="submit" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Page Header -->
        <div class="page-header mb-4 mt-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="page-title h3 fw-bold mb-1">Proctoring Monitor</h1>
                    <p class="page-subtitle text-muted mb-0">Monitor active exam sessions and security events</p>
                </div>
            </div>
        </div>

        <?php if ($load_error): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
                <span><?= htmlspecialchars($load_error) ?></span>
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="location.reload()">Retry</button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Active Sessions -->
            <div class="col-lg-8">
                <div class="card-modern mb-4">
                    <div class="card-header-modern d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h3 class="mb-0"><i class="bi bi-camera-video me-2"></i>Active Sessions</h3>
                        <?php if (!empty($active_sessions)): ?>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="mark-all-btn" onclick="toggleMarkAllSessions()">
                                    <i class="bi bi-check2-square me-1"></i>Mark All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="print-all-btn" onclick="printAllStudentActivities()">
                                    <i class="bi bi-printer me-1"></i>Print All
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" id="delete-selected-btn" onclick="deleteMarkedSessions()" disabled>
                                    <i class="bi bi-trash me-1"></i>Delete
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_sessions)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-camera-off"></i>
                                </div>
                                <h3>No Active Sessions</h3>
                                <p class="text-muted">No students are currently taking exams with proctoring enabled</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                                                <?php foreach ($active_sessions as $session): ?>
                                    <?php
                                        $status = (string)($session['proctoring_status'] ?? 'active');
                                        if (!in_array($status, ['active', 'flagged', 'violated', 'completed'], true)) {
                                            $status = 'active';
                                        }
                                        $session_card_class = $status === 'flagged' ? 'flagged' : 'active';
                                        $student_label = trim((string)($session['student_username'] ?? ''));
                                        if ($student_label === '' || $student_label === '-') {
                                            $student_label = 'Unknown Student';
                                        }
                                        $exam_title = trim((string)($session['exam_title'] ?? ''));
                                        if ($exam_title === '') {
                                            $exam_title = 'Unknown Exam';
                                        }
                                        $start_time = (string)($session['start_time'] ?? '');
                                        $alerts_count = (int)($session['suspicious_activity_count'] ?? 0);
                                        $attempt_id = (int)($session['exam_attempt_id'] ?? 0);
                                        $student_id = (int)($session['student_id'] ?? 0);
                                        $session_id = (int)($session['id'] ?? 0);
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="session-card <?= $session_card_class ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="mb-0"><?= htmlspecialchars($student_label) ?></h5>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input
                                                        class="form-check-input mt-0 session-checkbox"
                                                        type="checkbox"
                                                        value="<?= $session_id ?>"
                                                        onchange="updateDeleteSelectedButtonState()"
                                                        aria-label="Select <?= htmlspecialchars($student_label) ?>"
                                                    >
                                                    <span class="status-badge <?= 'status-' . $status ?>">
                                                        <?= ucfirst($status) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <p class="small text-muted mb-2">
                                                <i class="bi bi-journal-text me-1"></i>
                                                <?= htmlspecialchars($exam_title) ?>
                                            </p>

                                            <div class="d-flex align-items-center mb-2">
                                                <span class="activity-indicator active"></span>
                                                <span class="small">Active now</span>
                                            </div>

                                            <div class="d-flex justify-content-between small text-muted">
                                                <span><i class="bi bi-clock me-1"></i><?= htmlspecialchars($start_time) ?></span>
                                                <span><i class="bi bi-shield-exclamation me-1"></i><?= $alerts_count ?> alerts</span>
                                            </div>

                                            <div class="mt-3">
                                                <div class="video-preview">
                                                    <span class="text-light">Proctoring Active</span>
                                                </div>
                                            </div>

                                            <div class="d-flex gap-2 mt-3">
                                                <button
                                                    class="btn btn-sm btn-primary flex-fill"
                                                    onclick='viewEvidence(<?= $attempt_id ?>, <?= $student_id ?>, <?= json_encode($student_label, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'
                                                >
                                                    <i class="bi bi-eye me-1"></i>View
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger flex-fill" onclick='deleteSessionRecords(<?= $session_id ?>, <?= json_encode($student_label, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Security Logs -->
            <div class="col-lg-4">
                <div class="card-modern mb-4">
                    <div class="card-header-modern">
                        <h3 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Recent Activity</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_logs)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-shield-check fs-1 mb-2"></i>
                                <p>No security events logged recently</p>
                            </div>
                        <?php else: ?>
                            <div class="security-logs-list">
                                                                <?php foreach ($recent_logs as $log): ?>
                                    <?php
                                        $severity = strtolower((string)($log['severity'] ?? 'medium'));
                                        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
                                            $severity = 'medium';
                                        }
                                        $student_label = trim((string)($log['student_username'] ?? ''));
                                        if ($student_label === '') {
                                            $student_label = 'Unknown Student';
                                        }
                                        $activity_type = trim((string)($log['activity_type'] ?? 'activity'));
                                        if ($activity_type === '') {
                                            $activity_type = 'activity';
                                        }
                                        $description = trim((string)($log['description'] ?? 'No details available.'));
                                        if ($description === '') {
                                            $description = 'No details available.';
                                        }
                                        $log_timestamp = (string)($log['timestamp'] ?? '');
                                        $log_attempt_id = (int)($log['exam_attempt_id'] ?? 0);

                                        // Check if there are images associated with this activity
                                        $activity_images = [];
                                        if ($log_attempt_id > 0) {
                                            $upload_dir = __DIR__ . '/uploads/proctoring/';
                                            if (is_dir($upload_dir)) {
                                                $files = scandir($upload_dir);
                                                foreach ($files as $file) {
                                                    if (strpos($file, 'proctoring_img_' . $log_attempt_id . '_') !== false) {
                                                        $activity_images[] = 'uploads/proctoring/' . $file;
                                                    }
                                                }
                                            }
                                        }
                                    ?>
                                    <div class="security-log-item <?= $severity ?>">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($student_label) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($log_timestamp) ?></small>
                                        </div>
                                        <div class="small">
                                            <span class="badge bg-secondary"><?= htmlspecialchars($activity_type) ?></span>
                                        </div>
                                        <p class="mb-0 small"><?= htmlspecialchars($description) ?></p>
                                        <?php if (!empty($activity_images)): ?>
                                        <div class="mt-2">
                                            <small class="text-info">Captured evidence:</small>
                                            <div class="d-flex gap-2 mt-1">
                                                <?php foreach (array_slice($activity_images, 0, 3) as $img): ?>
                                                <img src="<?= htmlspecialchars($img) ?>" alt="Evidence" class="rounded" style="width: 60px; height: 40px; object-fit: cover; border: 1px solid var(--info);">
                                                <?php endforeach; ?>
                                                <?php if (count($activity_images) > 3): ?>
                                                <small class="text-muted">+<?= count($activity_images) - 3 ?> more</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Summary Stats -->
                <div class="card-modern">
                    <div class="card-header-modern">
                        <h3 class="mb-0"><i class="bi bi-graph-up me-2"></i>Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="display-6 text-primary"><?= count($active_sessions) ?></div>
                                <small class="text-muted">Active Sessions</small>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="display-6 text-warning"><?= $total_alerts ?></div>
                                <small class="text-muted">Total Alerts</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Evidence Modal -->
    <div class="modal fade" id="evidenceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content card-modern">
                <div class="modal-header card-header-modern">
                    <h5 class="modal-title"><i class="bi bi-images me-2"></i>Student Activity: <span id="modalStudentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="background: var(--bs-body-bg); position: relative;">
                    <div id="evidenceContainer" class="p-3">
                        <div id="evidenceImages" class="row g-3">
                            <!-- Activity cards will be loaded here dynamically -->
                        </div>
                        <div id="noEvidenceMessage" class="d-none text-center py-5">
                            <i class="bi bi-activity display-1 text-muted mb-3"></i>
                            <h4 class="text-muted">No Activity Found</h4>
                            <p class="text-muted">No activity events found for this student session.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--gray-200);">
                    <button type="button" class="btn btn-primary" onclick="printStudentActivity()"><i class="bi bi-printer me-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let evidenceModal;
        const csrfToken = <?= json_encode($csrf_token) ?>;
        const printableSessions = <?= json_encode($printable_sessions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            evidenceModal = new bootstrap.Modal(document.getElementById('evidenceModal'));
            
            // Clear evidence when modal is closed
            document.getElementById('evidenceModal').addEventListener('hidden.bs.modal', function () {
                const evidenceImages = document.getElementById('evidenceImages');
                evidenceImages.innerHTML = '';
                document.getElementById('noEvidenceMessage').classList.remove('d-none');
            });

            updateDeleteSelectedButtonState();
        });

        function viewEvidence(examAttemptId, studentId, studentName) {
            document.getElementById('modalStudentName').textContent = studentName;
            const evidenceImages = document.getElementById('evidenceImages');
            const noEvidenceMessage = document.getElementById('noEvidenceMessage');

            evidenceImages.innerHTML = '';
            noEvidenceMessage.classList.add('d-none');

            const params = new URLSearchParams();
            if (examAttemptId) {
                params.set('exam_attempt_id', String(examAttemptId));
            }
            if (studentId) {
                params.set('student_id', String(studentId));
            }

            if (!params.toString()) {
                noEvidenceMessage.classList.remove('d-none');
                evidenceImages.classList.add('d-none');
                evidenceModal.show();
                return;
            }
            fetch('api/fetch-evidence.php?' + params.toString(), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function(response) {
                    return response.json().then(function(data) {
                        if (!response.ok || !data.success) {
                            const errorMessage = (data && data.error) ? data.error : 'Failed to fetch activity';
                            throw new Error(errorMessage);
                        }
                        return data;
                    });
                })
                .then(function(data) {
                    const activities = Array.isArray(data.activities) ? data.activities : [];
                    const legacyImages = Array.isArray(data.images) ? data.images : [];
                    const recordings = Array.isArray(data.recordings) ? data.recordings : [];
                    let hasContent = false;

                    if (recordings.length > 0) {
                        const mediaCol = document.createElement('div');
                        mediaCol.className = 'col-12';

                        const mediaCard = document.createElement('div');
                        mediaCard.className = 'card h-100';

                        const mediaBody = document.createElement('div');
                        mediaBody.className = 'card-body';

                        const title = document.createElement('h6');
                        title.className = 'mb-3';
                        title.innerHTML = '<i class="bi bi-mic-fill me-2"></i>Audio/Video Evidence';
                        mediaBody.appendChild(title);

                        recordings.forEach(function(recording, index) {
                            const clipWrap = document.createElement('div');
                            clipWrap.className = 'mb-3';

                            const clipMeta = document.createElement('small');
                            clipMeta.className = 'text-muted d-block mb-2';
                            clipMeta.textContent = 'Clip ' + (index + 1) + ' • ' + (recording.timestamp || 'Unknown time');

                            const video = document.createElement('video');
                            video.controls = true;
                            video.preload = 'metadata';
                            video.style.width = '100%';
                            video.style.maxHeight = '260px';
                            video.className = 'rounded border';
                            video.src = recording.path;

                            clipWrap.appendChild(clipMeta);
                            clipWrap.appendChild(video);
                            mediaBody.appendChild(clipWrap);
                        });

                        mediaCard.appendChild(mediaBody);
                        mediaCol.appendChild(mediaCard);
                        evidenceImages.appendChild(mediaCol);
                        hasContent = true;
                    }

                    if (activities.length > 0) {
                        activities.forEach(function(activity) {
                            const col = document.createElement('div');
                            col.className = 'col-12';

                            const card = document.createElement('div');
                            card.className = 'card h-100';

                            const cardBody = document.createElement('div');
                            cardBody.className = 'card-body';

                            const topRow = document.createElement('div');
                            topRow.className = 'd-flex flex-wrap justify-content-between gap-2 mb-2';

                            const metaLeft = document.createElement('div');
                            const typeBadge = document.createElement('span');
                            typeBadge.className = 'badge bg-secondary me-2';
                            typeBadge.textContent = activity.activity_type || 'activity';

                            const severityBadge = document.createElement('span');
                            const severity = String(activity.severity || 'medium').toLowerCase();
                            let severityClass = 'bg-secondary';
                            if (severity === 'low') severityClass = 'bg-success';
                            if (severity === 'medium') severityClass = 'bg-warning text-dark';
                            if (severity === 'high') severityClass = 'bg-danger';
                            if (severity === 'critical') severityClass = 'bg-dark';
                            severityBadge.className = 'badge ' + severityClass;
                            severityBadge.textContent = severity;

                            metaLeft.appendChild(typeBadge);
                            metaLeft.appendChild(severityBadge);

                            const timeText = document.createElement('small');
                            timeText.className = 'text-muted';
                            timeText.textContent = activity.timestamp || 'Unknown time';

                            topRow.appendChild(metaLeft);
                            topRow.appendChild(timeText);

                            const desc = document.createElement('p');
                            desc.className = 'mb-2';
                            desc.textContent = activity.description || 'No description';

                            cardBody.appendChild(topRow);
                            cardBody.appendChild(desc);

                            if (Array.isArray(activity.images) && activity.images.length > 0) {
                                const imageWrap = document.createElement('div');
                                imageWrap.className = 'd-flex flex-wrap gap-2 mt-2';

                                activity.images.forEach(function(path) {
                                    const img = document.createElement('img');
                                    img.src = path;
                                    img.alt = 'Activity capture';
                                    img.className = 'rounded border';
                                    img.style.width = '96px';
                                    img.style.height = '64px';
                                    img.style.objectFit = 'cover';
                                    imageWrap.appendChild(img);
                                });

                                cardBody.appendChild(imageWrap);
                            }

                            if (Array.isArray(activity.recordings) && activity.recordings.length > 0) {
                                const recordingLabel = document.createElement('small');
                                recordingLabel.className = 'text-muted d-block mt-2';
                                recordingLabel.textContent = 'Audio/video clip available in section above.';
                                cardBody.appendChild(recordingLabel);
                            }

                            card.appendChild(cardBody);
                            col.appendChild(card);
                            evidenceImages.appendChild(col);
                        });

                        hasContent = true;
                    } else if (legacyImages.length > 0) {
                        legacyImages.forEach(function(image) {
                            const col = document.createElement('div');
                            col.className = 'col-md-6 col-lg-4';

                            const img = document.createElement('img');
                            img.src = image.path;
                            img.className = 'card-img-top';
                            img.alt = 'Cheating Evidence';
                            img.style.height = '200px';
                            img.style.objectFit = 'cover';

                            const card = document.createElement('div');
                            card.className = 'card h-100';

                            const cardBody = document.createElement('div');
                            cardBody.className = 'card-body';

                            const timestamp = document.createElement('p');
                            timestamp.className = 'card-text small text-muted';
                            timestamp.textContent = image.timestamp || 'Unknown time';

                            const desc = document.createElement('p');
                            desc.className = 'card-text small';
                            const activityLabel = image.activity_type || 'activity';
                            const activityDesc = image.description || 'No description';
                            desc.textContent = activityLabel + ': ' + activityDesc;

                            cardBody.appendChild(timestamp);
                            cardBody.appendChild(desc);
                            card.appendChild(img);
                            card.appendChild(cardBody);

                            col.appendChild(card);
                            evidenceImages.appendChild(col);
                        });

                        hasContent = true;
                    }

                    if (hasContent) {
                        noEvidenceMessage.classList.add('d-none');
                        evidenceImages.classList.remove('d-none');
                    } else {
                        noEvidenceMessage.classList.remove('d-none');
                        evidenceImages.classList.add('d-none');
                    }
                })
                .catch(function(error) {
                    console.error('Error fetching activity:', error);
                    noEvidenceMessage.classList.remove('d-none');
                    evidenceImages.classList.add('d-none');
                });

            evidenceModal.show();
        }
        function printStudentActivity() {
            const studentName = (document.getElementById('modalStudentName').textContent || 'Student').trim();
            const activityContainer = document.getElementById('evidenceContainer');

            if (!activityContainer) {
                alert('No activity content to print.');
                return;
            }

            const printWindow = window.open('', '_blank', 'width=1100,height=800');
            if (!printWindow) {
                alert('Please allow pop-ups to print this activity.');
                return;
            }

            const timestamp = new Date().toLocaleString();
            const printableHtml = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Student Activity - ${studentName}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
                        h1 { margin: 0 0 8px 0; font-size: 22px; }
                        p.meta { margin: 0 0 18px 0; color: #4b5563; font-size: 13px; }
                        .card { border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; }
                        .card-body { padding: 12px; }
                        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; margin-right: 6px; }
                        .bg-secondary { background: #e5e7eb; color: #111827; }
                        .bg-success { background: #d1fae5; color: #065f46; }
                        .bg-warning { background: #fef3c7; color: #92400e; }
                        .bg-danger { background: #fee2e2; color: #991b1b; }
                        .bg-dark { background: #111827; color: #f9fafb; }
                        .text-muted { color: #6b7280; }
                        img { border: 1px solid #d1d5db; border-radius: 6px; margin-right: 8px; margin-top: 8px; }
                        @media print {
                            @page { size: auto; margin: 12mm; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Student Activity Report</h1>
                    <p class="meta"><strong>Student:</strong> ${studentName} | <strong>Printed:</strong> ${timestamp}</p>
                    ${activityContainer.innerHTML}
                </body>
                </html>
            `;

            printWindow.document.open();
            printWindow.document.write(printableHtml);
            printWindow.document.close();
            printWindow.focus();
            printWindow.onload = function () {
                printWindow.print();
                printWindow.close();
            };
        }
        async function fetchActivityBundleForSession(session) {
            const params = new URLSearchParams();
            if (session.exam_attempt_id) {
                params.set('exam_attempt_id', String(session.exam_attempt_id));
            }
            if (session.student_id) {
                params.set('student_id', String(session.student_id));
            }

            const response = await fetch('api/fetch-evidence.php?' + params.toString(), {
                headers: { 'Accept': 'application/json' }
            });

            let data = {};
            try {
                data = await response.json();
            } catch (err) {
                throw new Error('Invalid activity response for ' + (session.student_name || 'student'));
            }

            if (!response.ok || !data.success) {
                const errorMessage = (data && data.error) ? data.error : 'Failed to fetch activity';
                throw new Error((session.student_name || 'Student') + ': ' + errorMessage);
            }

            return data;
        }

        function extractUniqueImagePaths(data) {
            const source = Array.isArray(data.images) ? data.images : [];
            const paths = source
                .map(function (item) {
                    if (typeof item === 'string') {
                        return item;
                    }
                    return item && item.path ? item.path : '';
                })
                .filter(function (path) {
                    return typeof path === 'string' && path.trim() !== '';
                });

            return Array.from(new Set(paths));
        }

        async function loadImageAsDataUrl(path) {
            try {
                const response = await fetch(path, { cache: 'no-store' });
                if (!response.ok) {
                    return null;
                }

                const blob = await response.blob();

                if (blob.type === 'image/webp') {
                    return await new Promise(function (resolve) {
                        const objectUrl = URL.createObjectURL(blob);
                        const image = new Image();

                        image.onload = function () {
                            const canvas = document.createElement('canvas');
                            canvas.width = image.width;
                            canvas.height = image.height;
                            const context = canvas.getContext('2d');

                            if (!context) {
                                URL.revokeObjectURL(objectUrl);
                                resolve(null);
                                return;
                            }

                            context.drawImage(image, 0, 0);
                            URL.revokeObjectURL(objectUrl);
                            resolve(canvas.toDataURL('image/png'));
                        };

                        image.onerror = function () {
                            URL.revokeObjectURL(objectUrl);
                            resolve(null);
                        };

                        image.src = objectUrl;
                    });
                }

                return await new Promise(function (resolve) {
                    const reader = new FileReader();
                    reader.onload = function () { resolve(reader.result); };
                    reader.onerror = function () { resolve(null); };
                    reader.readAsDataURL(blob);
                });
            } catch (err) {
                return null;
            }
        }
        function getImageFormat(dataUrl) {
            if (typeof dataUrl !== 'string') {
                return 'JPEG';
            }
            if (dataUrl.startsWith('data:image/png')) {
                return 'PNG';
            }
            return 'JPEG';
        }

        async function renderStudentActivitiesToPdf(doc, session, bundle, isFirstStudent) {
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const margin = 12;
            let y = margin;
            let studentPageCount = 1;

            if (!isFirstStudent) {
                doc.addPage();
            }

            const drawHeader = function (continued) {
                y = margin;

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(15);
                doc.setTextColor(17, 24, 39);
                doc.text('Proctoring Activity Report', margin, y);
                y += 7;

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);
                doc.setTextColor(55, 65, 81);
                doc.text('Student: ' + (session.student_name || 'Unknown Student'), margin, y);
                y += 5;
                doc.text('Exam: ' + (session.exam_title || 'Unknown Exam'), margin, y);
                y += 5;
                doc.text('Generated: ' + new Date().toLocaleString(), margin, y);
                if (continued) {
                    doc.text('Page ' + studentPageCount + ' (continued)', pageWidth - margin, y, { align: 'right' });
                } else {
                    doc.text('Page ' + studentPageCount, pageWidth - margin, y, { align: 'right' });
                }
                y += 5;

                doc.setDrawColor(203, 213, 225);
                doc.line(margin, y, pageWidth - margin, y);
                y += 6;
            };

            drawHeader(false);

            const activities = Array.isArray(bundle.activities) ? bundle.activities : [];

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(12);
            doc.setTextColor(17, 24, 39);
            doc.text('Activity Log', margin, y);
            y += 6;

            if (!activities.length) {
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);
                doc.setTextColor(75, 85, 99);
                doc.text('No activity events recorded for this student.', margin, y);
                y += 7;
            } else {
                for (let i = 0; i < activities.length; i += 1) {
                    const activity = activities[i] || {};
                    const head = (i + 1) + '. ' + (activity.timestamp || 'Unknown time') + ' | ' + (activity.activity_type || 'activity') + ' | ' + (activity.severity || 'medium');
                    const detail = String(activity.description || 'No description');

                    const headLines = doc.splitTextToSize(head, pageWidth - (margin * 2));
                    const detailLines = doc.splitTextToSize(detail, pageWidth - (margin * 2) - 3);
                    const neededHeight = ((headLines.length + detailLines.length) * 4.4) + 4;

                    if (y + neededHeight > pageHeight - margin - 8) {
                        studentPageCount += 1;
                        doc.addPage();
                        drawHeader(true);

                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(12);
                        doc.setTextColor(17, 24, 39);
                        doc.text('Activity Log (continued)', margin, y);
                        y += 6;
                    }

                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(10);
                    doc.setTextColor(17, 24, 39);
                    doc.text(headLines, margin, y);
                    y += headLines.length * 4.4;

                    doc.setFont('helvetica', 'normal');
                    doc.setTextColor(55, 65, 81);
                    doc.text(detailLines, margin + 3, y);
                    y += (detailLines.length * 4.4) + 2;
                }
            }

            const imagePaths = extractUniqueImagePaths(bundle);

            if (y > pageHeight - margin - 20) {
                studentPageCount += 1;
                doc.addPage();
                drawHeader(true);
            }

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(12);
            doc.setTextColor(17, 24, 39);
            doc.text('Captured Images', margin, y);
            y += 6;

            if (!imagePaths.length) {
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);
                doc.setTextColor(75, 85, 99);
                doc.text('No captured proctoring images found.', margin, y);
                return;
            }

            const gapX = 6;
            const imageBoxWidth = (pageWidth - (margin * 2) - gapX) / 2;
            const imageHeight = 54;
            const imageBoxHeight = 66;
            let column = 0;

            for (let i = 0; i < imagePaths.length; i += 1) {
                if (y + imageBoxHeight > pageHeight - margin) {
                    studentPageCount += 1;
                    doc.addPage();
                    drawHeader(true);

                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(12);
                    doc.setTextColor(17, 24, 39);
                    doc.text('Captured Images (continued)', margin, y);
                    y += 6;
                    column = 0;
                }

                const x = margin + (column * (imageBoxWidth + gapX));
                const dataUrl = await loadImageAsDataUrl(imagePaths[i]);

                if (dataUrl) {
                    doc.addImage(dataUrl, getImageFormat(dataUrl), x, y, imageBoxWidth, imageHeight);
                } else {
                    doc.setDrawColor(203, 213, 225);
                    doc.rect(x, y, imageBoxWidth, imageHeight);
                    doc.setFont('helvetica', 'italic');
                    doc.setFontSize(9);
                    doc.setTextColor(107, 114, 128);
                    doc.text('Image unavailable', x + (imageBoxWidth / 2), y + (imageHeight / 2), { align: 'center' });
                }

                const fileName = imagePaths[i].split('/').pop() || 'capture';
                const caption = doc.splitTextToSize(fileName, imageBoxWidth);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.setTextColor(75, 85, 99);
                doc.text(caption[0], x, y + imageHeight + 4);

                if (column === 1) {
                    column = 0;
                    y += imageBoxHeight;
                } else {
                    column = 1;
                }
            }
        }

        async function printAllStudentActivities() {
            if (!Array.isArray(printableSessions) || printableSessions.length < 1) {
                alert('No student sessions available to print.');
                return;
            }

            const printBtn = document.getElementById('print-all-btn');
            const originalBtnHtml = printBtn ? printBtn.innerHTML : '';

            if (printBtn) {
                printBtn.disabled = true;
                printBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preparing PDF...';
            }

            try {
                const JsPdfCtor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : (window.jsPDF || null);
                if (!JsPdfCtor) {
                    throw new Error('PDF library is unavailable.');
                }

                // Keep one report sheet minimum per student, then add pages based on image volume.
                const uniqueSessions = Array.from(new Map(
                    printableSessions.map(function (session) {
                        const key = session.student_id > 0 ? 'student-' + session.student_id : 'session-' + session.session_id;
                        return [key, session];
                    })
                ).values());

                const doc = new JsPdfCtor({ unit: 'mm', format: 'a4', orientation: 'portrait' });

                for (let i = 0; i < uniqueSessions.length; i += 1) {
                    const session = uniqueSessions[i];
                    const bundle = await fetchActivityBundleForSession(session);
                    await renderStudentActivitiesToPdf(doc, session, bundle, i === 0);
                }

                const now = new Date();
                const pad = function (n) { return String(n).padStart(2, '0'); };
                const filename = 'All_Student_Activities_' +
                    now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate()) + '_' +
                    pad(now.getHours()) + pad(now.getMinutes()) + '.pdf';

                doc.save(filename);
            } catch (err) {
                console.error('Print all activities error:', err);
                alert(err && err.message ? err.message : 'Unable to generate PDF for all student activities.');
            } finally {
                if (printBtn) {
                    printBtn.disabled = false;
                    printBtn.innerHTML = originalBtnHtml;
                }
            }
        }
        // Auto-refresh the page every 30 seconds to get latest data
        setInterval(() => {
            // Check if modal is open, if not, reload
            const modalElement = document.getElementById('evidenceModal');
            if (!modalElement.classList.contains('show') && modalElement.style.display !== 'block') {
                location.reload();
            }
        }, 30000);
        
        function getSelectedSessionIds() {
            return Array.from(document.querySelectorAll('.session-checkbox:checked'))
                .map(function (checkbox) {
                    return parseInt(checkbox.value, 10);
                })
                .filter(function (id) {
                    return Number.isInteger(id) && id > 0;
                });
        }

        function updateDeleteSelectedButtonState() {
            const checkboxes = Array.from(document.querySelectorAll('.session-checkbox'));
            const deleteBtn = document.getElementById('delete-selected-btn');
            const markAllBtn = document.getElementById('mark-all-btn');

            if (!deleteBtn || !markAllBtn || checkboxes.length === 0) {
                return;
            }

            const selectedCount = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            deleteBtn.disabled = selectedCount < 1;

            if (selectedCount === checkboxes.length) {
                markAllBtn.innerHTML = '<i class="bi bi-square me-1"></i>Unmark All';
            } else {
                markAllBtn.innerHTML = '<i class="bi bi-check2-square me-1"></i>Mark All';
            }
        }

        function toggleMarkAllSessions() {
            const checkboxes = Array.from(document.querySelectorAll('.session-checkbox'));
            if (checkboxes.length === 0) {
                return;
            }

            const allChecked = checkboxes.every(function (checkbox) {
                return checkbox.checked;
            });

            checkboxes.forEach(function (checkbox) {
                checkbox.checked = !allChecked;
            });

            updateDeleteSelectedButtonState();
        }

        function deleteSessionRecords(sessionId, studentName) {
            const id = parseInt(sessionId, 10);
            if (!id) {
                return;
            }

            const label = studentName || 'this student';
            if (!confirm('Delete proctoring records for ' + label + '? This action cannot be undone.')) {
                return;
            }

            performDeleteRequest([id]);
        }

        function deleteMarkedSessions() {
            const sessionIds = getSelectedSessionIds();
            if (sessionIds.length < 1) {
                return;
            }

            if (!confirm('Delete proctoring records for all marked students? This action cannot be undone.')) {
                return;
            }

            performDeleteRequest(sessionIds);
        }

        function performDeleteRequest(sessionIds) {
            fetch('api/delete-proctoring-records.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams({
                    csrf_token: csrfToken,
                    session_ids: JSON.stringify(sessionIds)
                })
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok || !data.success) {
                            const message = (data && data.error) ? data.error : 'Unable to delete proctoring records.';
                            throw new Error(message);
                        }
                        return data;
                    });
                })
                .then(function () {
                    location.reload();
                })
                .catch(function (error) {
                    alert(error.message || 'Unable to delete proctoring records.');
                });
        }
    </script>
    <script defer src="theme.js"></script>
</body>
</html>

