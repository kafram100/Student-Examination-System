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
        u.username as student_username,
        e.title as exam_title
    FROM exam_sessions_proctoring esp
    JOIN users u ON esp.student_id = u.id
    JOIN attempts a ON esp.exam_attempt_id = a.id
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
        u.username as student_username,
        e.title as exam_title
    FROM exam_security_logs esl
    JOIN attempts a ON esl.exam_attempt_id = a.id
    JOIN users u ON esl.user_id = u.id
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
        <div class="page-header d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="page-title">Proctoring Monitor</h1>
                <p class="page-subtitle">Monitor active exam sessions and security events</p>
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
                                                <button class="btn btn-sm btn-primary flex-fill">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh the page every 30 seconds to get latest data
        setInterval(() => {
            location.reload();
        }, 30000);
        
        // Function to handle flagging a session
        function flagSession(sessionId) {
            if (confirm('Are you sure you want to flag this session for review?')) {
                // In a real implementation, this would make an AJAX call
                console.log('Flagging session:', sessionId);
            }
        }
    </script>
</body>
</html>