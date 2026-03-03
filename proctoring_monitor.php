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
        COALESCE(u.username, a.student_fullname) as student_username,
        e.title as exam_title
    FROM exam_sessions_proctoring esp
    JOIN attempts a ON esp.exam_attempt_id = a.id
    LEFT JOIN users u ON esp.student_id = u.id
    JOIN exams e ON a.exam_id = e.id
    WHERE e.user_id = ?
    AND esp.proctoring_status IN ('active', 'flagged')
    ORDER BY esp.start_time DESC
");
$stmt->execute([$user_id]);
$active_sessions = $stmt->fetchAll();

// Get recent security logs
$stmt = $pdo->prepare("
    SELECT 
        esl.*,
        COALESCE(u.username, a.student_fullname) as student_username,
        e.title as exam_title
    FROM exam_security_logs esl
    JOIN attempts a ON esl.exam_attempt_id = a.id
    LEFT JOIN users u ON esl.user_id = u.id
    JOIN exams e ON a.exam_id = e.id
    WHERE e.user_id = ?
    ORDER BY esl.timestamp DESC
    LIMIT 20
");
$stmt->execute([$user_id]);
$recent_logs = $stmt->fetchAll();

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

        <div class="row">
            <!-- Active Sessions -->
            <div class="col-lg-8">
                <div class="card-modern mb-4">
                    <div class="card-header-modern">
                        <h3 class="mb-0"><i class="bi bi-camera-video me-2"></i>Active Sessions</h3>
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
                                    <div class="col-md-6 mb-3">
                                        <div class="session-card <?= $session['proctoring_status'] === 'flagged' ? 'flagged' : 'active' ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="mb-0"><?= htmlspecialchars($session['student_username']) ?></h5>
                                                <span class="status-badge <?= 'status-' . $session['proctoring_status'] ?>">
                                                    <?= ucfirst($session['proctoring_status']) ?>
                                                </span>
                                            </div>
                                            
                                            <p class="small text-muted mb-2">
                                                <i class="bi bi-journal-text me-1"></i>
                                                <?= htmlspecialchars($session['exam_title']) ?>
                                            </p>
                                            
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="activity-indicator active"></span>
                                                <span class="small">Active now</span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between small text-muted">
                                                <span><i class="bi bi-clock me-1"></i><?= $session['start_time'] ?></span>
                                                <span><i class="bi bi-shield-exclamation me-1"></i><?= $session['suspicious_activity_count'] ?> alerts</span>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <div class="video-preview">
                                                    <span class="text-light">Video Feed</span>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2 mt-3">
                                                <button class="btn btn-sm btn-primary flex-fill" onclick="viewVideo('<?= htmlspecialchars(basename($session['video_recording_path'] ?? '')) ?>', '<?= htmlspecialchars($session['student_username'], ENT_QUOTES) ?>')">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger flex-fill">
                                                    <i class="bi bi-flag me-1"></i>Flag
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
                                    <div class="security-log-item <?= $log['severity'] ?>">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($log['student_username']) ?></strong>
                                            <small class="text-muted"><?= $log['timestamp'] ?></small>
                                        </div>
                                        <div class="small">
                                            <span class="badge bg-secondary"><?= htmlspecialchars($log['activity_type']) ?></span>
                                        </div>
                                        <p class="mb-0 small"><?= htmlspecialchars($log['description']) ?></p>
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
                                <div class="display-6 text-warning"><?= array_sum(array_column($active_sessions, 'suspicious_activity_count')) ?></div>
                                <small class="text-muted">Total Alerts</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Modal -->
    <div class="modal fade" id="videoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content card-modern">
                <div class="modal-header card-header-modern">
                    <h5 class="modal-title"><i class="bi bi-camera-video me-2"></i>Video Record: <span id="modalStudentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0" style="background: #000; position: relative;">
                    <video id="proctoringVideoPlayer" controls style="width: 100%; max-height: 70vh;">
                        Your browser does not support the video tag.
                    </video>
                    <div id="noVideoMessage" class="p-5 d-none d-flex flex-column align-items-center justify-content-center" style="height: 400px; background: var(--bs-body-bg);">
                        <i class="bi bi-camera-video-off display-1 text-muted mb-3"></i>
                        <h4 style="color: var(--bs-body-color);">No Video Available</h4>
                        <p class="text-muted">The video recording for this session is not available yet or could not be loaded.</p>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--gray-200);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let videoModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            videoModal = new bootstrap.Modal(document.getElementById('videoModal'));
            
            // Stop video when modal is closed
            document.getElementById('videoModal').addEventListener('hidden.bs.modal', function () {
                const videoPlayer = document.getElementById('proctoringVideoPlayer');
                videoPlayer.pause();
                videoPlayer.src = '';
            });
        });

        function viewVideo(videoFileName, studentName) {
            document.getElementById('modalStudentName').textContent = studentName;
            const videoPlayer = document.getElementById('proctoringVideoPlayer');
            const noVideoMessage = document.getElementById('noVideoMessage');
            
            if (videoFileName) {
                videoPlayer.src = 'uploads/proctoring/' + videoFileName;
                videoPlayer.classList.remove('d-none');
                noVideoMessage.classList.add('d-none');
                videoPlayer.play().catch(e => console.log("Auto-play prevented", e));
            } else {
                videoPlayer.src = '';
                videoPlayer.classList.add('d-none');
                noVideoMessage.classList.remove('d-none');
            }
            
            videoModal.show();
        }

        // Auto-refresh the page every 30 seconds to get latest data
        setInterval(() => {
            // Check if modal is open, if not, reload
            const modalElement = document.getElementById('videoModal');
            if (!modalElement.classList.contains('show') && modalElement.style.display !== 'block') {
                location.reload();
            }
        }, 30000);
        
        // Function to handle flagging a session
        function flagSession(sessionId) {
            if (confirm('Are you sure you want to flag this session for review?')) {
                // In a real implementation, this would make an AJAX call
                console.log('Flagging session:', sessionId);
            }
        }
    </script>
    <script defer src="theme.js"></script>
</body>
</html>