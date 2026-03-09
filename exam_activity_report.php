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

// Get filter parameters
$exam_id = $_GET['exam_id'] ?? null;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$severity_filter = $_GET['severity'] ?? null;

// Build query for exam reports
$reports_query = "
    SELECT er.*, e.title as exam_title 
    FROM exam_reports er
    JOIN exams e ON er.exam_id = e.id
    WHERE er.lecturer_id = ?
    ORDER BY er.report_date DESC
";

$reports_stmt = $pdo->prepare($reports_query);
$reports_stmt->execute([$user_id]);
$exam_reports = $reports_stmt->fetchAll();

// Build query for activity logs
$activity_query = "
    SELECT
        eal.*,
        COALESCE(NULLIF(a.student_fullname, ''), CONCAT('Student ', a.student_index), 'Unknown Student') as student_username,
        e.title as exam_title,
        a.student_index
    FROM exam_activity_logs eal
    JOIN attempts a ON eal.exam_attempt_id = a.id
    JOIN exams e ON a.exam_id = e.id
    WHERE e.user_id = ?
";

$params = [$user_id];

// Add filters if specified
if ($exam_id) {
    $activity_query .= " AND e.id = ?";
    $params[] = $exam_id;
}

if ($start_date) {
    $activity_query .= " AND eal.timestamp >= ?";
    $params[] = $start_date . ' 00:00:00';
}

if ($end_date) {
    $activity_query .= " AND eal.timestamp <= ?";
    $params[] = $end_date . ' 23:59:59';
}

if ($severity_filter) {
    $activity_query .= " AND eal.severity = ?";
    $params[] = $severity_filter;
}

$activity_query .= " ORDER BY eal.timestamp DESC LIMIT 100"; // Limit to last 100 for performance

$activity_stmt = $pdo->prepare($activity_query);
$activity_stmt->execute($params);
$activity_logs = $activity_stmt->fetchAll();

$grouped_activity_logs = [];
foreach ($activity_logs as $log) {
    $student_name = trim((string)($log['student_username'] ?? 'Unknown Student'));
    if ($student_name === '') {
        $student_name = 'Unknown Student';
    }

    $student_index_value = trim((string)($log['student_index'] ?? 'N/A'));
    if ($student_index_value === '') {
        $student_index_value = 'N/A';
    }

    $group_key = $student_name . '|' . $student_index_value;

    if (!isset($grouped_activity_logs[$group_key])) {
        $grouped_activity_logs[$group_key] = [
            'student_name' => $student_name,
            'student_index' => $student_index_value,
            'logs' => []
        ];
    }

    $grouped_activity_logs[$group_key]['logs'][] = $log;
}

$grouped_activity_logs = array_values($grouped_activity_logs);
// Build query for cheating incidents
$incidents_query = "
    SELECT
        ci.*,
        COALESCE(NULLIF(a.student_fullname, ''), CONCAT('Student ', a.student_index), 'Unknown Student') as student_username,
        e.title as exam_title,
        a.student_index
    FROM cheating_incidents ci
    JOIN attempts a ON ci.exam_attempt_id = a.id
    JOIN exams e ON a.exam_id = e.id
    WHERE e.user_id = ?
";

$incidents_params = [$user_id];

// Add filters if specified
if ($exam_id) {
    $incidents_query .= " AND e.id = ?";
    $incidents_params[] = $exam_id;
}

if ($start_date) {
    $incidents_query .= " AND ci.incident_timestamp >= ?";
    $incidents_params[] = $start_date . ' 00:00:00';
}

if ($end_date) {
    $incidents_query .= " AND ci.incident_timestamp <= ?";
    $incidents_params[] = $end_date . ' 23:59:59';
}

$incidents_query .= " ORDER BY ci.incident_timestamp DESC";

$incidents_stmt = $pdo->prepare($incidents_query);
$incidents_stmt->execute($incidents_params);
$cheating_incidents = $incidents_stmt->fetchAll();

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Activity Reports - Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="assets/css/theme.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid var(--gray-300);
            margin-bottom: 0.5rem;
            border-radius: 0 4px 4px 0;
            background: transparent;
        }

        .activity-item.low { border-left-color: var(--success); }
        .activity-item.medium { border-left-color: var(--warning); }
        .activity-item.high { border-left-color: var(--info); }
        .activity-item.critical { border-left-color: var(--danger); background-color: var(--danger-light); }

        .activity-accordion .accordion-item {
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 0.75rem;
        }

        .activity-accordion .accordion-button {
            background: #ffffff;
            color: #1e293b;
            font-weight: 600;
            box-shadow: none;
        }

        .activity-accordion .accordion-button:not(.collapsed) {
            background: var(--gray-100);
            color: #0f172a;
        }

        .activity-accordion .accordion-body {
            background: #ffffff;
            border-top: 1px solid var(--gray-200);
            padding: 0.875rem;
        }

        .student-group-meta {
            font-size: 0.85rem;
        }

        .incident-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 1rem;
            background: #ffffff;
            color: #1e293b;
        }
        
        .incident-card.confirmed {
            border-left: 4px solid var(--danger);
            background-color: var(--danger-light);
        }
        
        .incident-card.dismissed {
            border-left: 4px solid var(--success);
            background-color: var(--success-light);
        }
        
        .severity-badge {
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .severity-low { background-color: var(--success-light); color: var(--success); }
        .severity-medium { background-color: var(--warning-light); color: var(--warning); }
        .severity-high { background-color: var(--info-light); color: var(--info); }
        .severity-critical { background-color: var(--danger-light); color: var(--danger); }
        
        .filter-section {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            color: #1e293b;
        }
        
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
        body.theme-dark .incident-card,
        body.theme-dark .filter-section {
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

                body.theme-dark .activity-accordion .accordion-item {
            border-color: #334155 !important;
            background: #1e293b !important;
        }

        body.theme-dark .activity-accordion .accordion-button {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }

        body.theme-dark .activity-accordion .accordion-button:not(.collapsed) {
            background: #263448 !important;
            color: #f8fafc !important;
        }

        body.theme-dark .activity-accordion .accordion-button::after {
            filter: invert(1) brightness(1.8);
        }

        body.theme-dark .activity-accordion .accordion-body {
            background: #1e293b !important;
            border-top-color: #334155 !important;
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
                    <h1 class="page-title h3 fw-bold mb-1">Exam Activity Reports</h1>
                    <p class="page-subtitle text-muted mb-0">Monitor student activities and detect potential cheating</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="exam_id" class="form-label">Filter by Exam</label>
                    <select name="exam_id" id="exam_id" class="form-select">
                        <option value="">All Exams</option>
                        <?php
                        $exams_stmt = $pdo->prepare("SELECT id, title FROM exams WHERE user_id = ? ORDER BY created_at DESC");
                        $exams_stmt->execute([$user_id]);
                        $exams = $exams_stmt->fetchAll();
                        
                        foreach ($exams as $exam):
                        ?>
                            <option value="<?= $exam['id'] ?>" <?= $exam_id == $exam['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($exam['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="severity" class="form-label">Severity</label>
                    <select name="severity" id="severity" class="form-select">
                        <option value="">All Severities</option>
                        <option value="low" <?= $severity_filter == 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $severity_filter == 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $severity_filter == 'high' ? 'selected' : '' ?>>High</option>
                        <option value="critical" <?= $severity_filter == 'critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?>">
                </div>
                
                <div class="col-12 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-funnel me-2"></i>Apply Filters</button>
                    <a href="exam_activity_report.php" class="btn btn-outline-secondary px-4 ms-2">Reset</a>
                </div>
            </form>
        </div>

        <div class="row">
            <!-- Activity Logs -->
            <div class="col-lg-8">
                <div class="card-modern mb-4">
                    <div class="card-header-modern">
                        <h3 class="mb-0"><i class="bi bi-activity me-2"></i>Activity Logs</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activity_logs)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-activity fs-1 mb-2"></i>
                                <p>No activity logs found for the selected criteria</p>
                            </div>
                        <?php else: ?>
                            <div class="accordion activity-accordion" id="activityLogsByStudent">
                                <?php foreach ($grouped_activity_logs as $group_index => $student_group): ?>
                                    <?php
                                        $collapse_id = 'studentLogsCollapse' . $group_index;
                                        $heading_id = 'studentLogsHeading' . $group_index;
                                        $student_logs = $student_group['logs'];
                                        $latest_time = (string)($student_logs[0]['timestamp'] ?? '');
                                    ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="<?= $heading_id ?>">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>" aria-expanded="false" aria-controls="<?= $collapse_id ?>">
                                                <div class="d-flex justify-content-between align-items-center w-100 me-2 gap-2">
                                                    <span>
                                                        <?= htmlspecialchars($student_group['student_name']) ?>
                                                        <span class="text-muted">(<?= htmlspecialchars($student_group['student_index']) ?>)</span>
                                                    </span>
                                                    <span class="d-flex align-items-center gap-2 student-group-meta">
                                                        <span class="badge bg-primary"><?= count($student_logs) ?> activities</span>
                                                        <?php if ($latest_time !== ''): ?>
                                                            <span class="text-muted">Last: <?= htmlspecialchars($latest_time) ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="<?= $collapse_id ?>" class="accordion-collapse collapse" aria-labelledby="<?= $heading_id ?>" data-bs-parent="#activityLogsByStudent">
                                            <div class="accordion-body">
                                                <?php foreach ($student_logs as $log): ?>
                                                    <?php
                                                        $log_severity = strtolower((string)($log['severity'] ?? 'medium'));
                                                        if (!in_array($log_severity, ['low', 'medium', 'high', 'critical'], true)) {
                                                            $log_severity = 'medium';
                                                        }
                                                    ?>
                                                    <div class="activity-item <?= $log_severity ?>">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <span class="badge bg-secondary"><?= htmlspecialchars($log['activity_type']) ?></span>
                                                            <span class="severity-badge severity-<?= $log_severity ?>">
                                                                <?= ucfirst($log_severity) ?>
                                                            </span>
                                                        </div>
                                                        <p class="mb-1 small"><?= htmlspecialchars($log['description']) ?></p>
                                                        <div class="d-flex justify-content-between">
                                                            <?php if (!empty($log['exam_title'])): ?>
                                                                <small class="text-muted">Exam: <?= htmlspecialchars($log['exam_title']) ?></small>
                                                            <?php else: ?>
                                                                <small class="text-muted"></small>
                                                            <?php endif; ?>
                                                            <small class="text-muted"><?= htmlspecialchars((string)($log['timestamp'] ?? '')) ?></small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Cheating Incidents -->
                <div class="card-modern">
                    <div class="card-header-modern">
                        <h3 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Cheating Incidents</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cheating_incidents)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-check-circle fs-1 mb-2"></i>
                                <p>No cheating incidents detected</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($cheating_incidents as $incident): ?>
                                <div class="incident-card <?= $incident['status'] ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="mb-0">
                                            <?= htmlspecialchars($incident['student_username']) ?> 
                                            (<?= htmlspecialchars($incident['student_index']) ?>)
                                        </h5>
                                        <span class="badge <?= $incident['status'] === 'confirmed' ? 'bg-danger' : ($incident['status'] === 'dismissed' ? 'bg-success' : 'bg-warning') ?>">
                                            <?= ucfirst($incident['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <p class="small text-muted mb-2">
                                        <i class="bi bi-journal-text me-1"></i>
                                        <?= htmlspecialchars($incident['exam_title']) ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between small text-muted mb-2">
                                        <span>
                                            <i class="bi bi-shield-exclamation me-1"></i>
                                            <?= htmlspecialchars($incident['incident_type']) ?>
                                        </span>
                                        <span>
                                            <i class="bi bi-confidence me-1"></i>
                                            <?= ucfirst($incident['confidence_level']) ?> Confidence
                                        </span>
                                    </div>
                                    
                                    <p class="mb-2"><?= htmlspecialchars($incident['notes']) ?></p>

                                    <small class="text-muted">Incident occurred at: <?= $incident['incident_timestamp'] ?></small>

                                    <?php if (in_array($incident['status'], ['pending', 'reviewed'], true)): ?>
                                        <div class="d-flex gap-2 mt-3">
                                            <button type="button" class="btn btn-sm btn-danger" onclick="updateIncidentStatus(<?= (int)$incident['id'] ?>, 'confirmed')">
                                                <i class="bi bi-check2-circle me-1"></i>Confirm
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateIncidentStatus(<?= (int)$incident['id'] ?>, 'dismissed')">
                                                <i class="bi bi-x-circle me-1"></i>Dismiss
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Summary Stats -->
            <div class="col-lg-4">
                <div class="card-modern mb-4">
                    <div class="card-header-modern">
                        <h3 class="mb-0"><i class="bi bi-graph-up me-2"></i>Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="display-6 text-primary"><?= count($activity_logs) ?></div>
                                <small class="text-muted">Activity Logs</small>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="display-6 text-warning"><?= count($cheating_incidents) ?></div>
                                <small class="text-muted">Incidents Detected</small>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="mb-3">Activity Distribution</h6>
                            <?php
                            // Count activities by severity
                            $severity_counts = [
                                'low' => 0,
                                'medium' => 0,
                                'high' => 0,
                                'critical' => 0
                            ];
                            
                            foreach ($activity_logs as $log) {
                                if (isset($severity_counts[$log['severity']])) {
                                    $severity_counts[$log['severity']]++;
                                }
                            }
                            ?>
                            
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?= count($activity_logs) ? ($severity_counts['low']/count($activity_logs))*100 : 0 ?>%" title="Low Risk"></div>
                                <div class="progress-bar bg-warning" style="width: <?= count($activity_logs) ? ($severity_counts['medium']/count($activity_logs))*100 : 0 ?>%" title="Medium Risk"></div>
                                <div class="progress-bar bg-info" style="width: <?= count($activity_logs) ? ($severity_counts['high']/count($activity_logs))*100 : 0 ?>%" title="High Risk"></div>
                                <div class="progress-bar bg-danger" style="width: <?= count($activity_logs) ? ($severity_counts['critical']/count($activity_logs))*100 : 0 ?>%" title="Critical Risk"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between small">
                                <span><span class="badge bg-success">L</span> <?= $severity_counts['low'] ?></span>
                                <span><span class="badge bg-warning">M</span> <?= $severity_counts['medium'] ?></span>
                                <span><span class="badge bg-info">H</span> <?= $severity_counts['high'] ?></span>
                                <span><span class="badge bg-danger">C</span> <?= $severity_counts['critical'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-modern">
                    <div class="card-header-modern">
                        <h3 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Security Features</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Tab switching detection</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Screenshot prevention</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Copy/paste protection</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Print prevention</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Developer tools detection</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Multiple device detection</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Screen recording detection</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i>Real-time monitoring</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = <?= json_encode($csrf_token) ?>;

        function updateIncidentStatus(incidentId, status) {
            const id = parseInt(incidentId, 10);
            if (!id || !status) {
                return;
            }

            fetch('api/update-incident-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams({
                    csrf_token: csrfToken,
                    incident_id: String(id),
                    status: String(status)
                })
            })
                .then(function(response) {
                    return response.json().then(function(data) {
                        if (!response.ok || !data.success) {
                            const message = (data && data.error) ? data.error : 'Failed to update incident status';
                            throw new Error(message);
                        }
                        return data;
                    });
                })
                .then(function() {
                    location.reload();
                })
                .catch(function(error) {
                    alert(error && error.message ? error.message : 'Failed to update incident status');
                });
        }
    </script>
    <script defer src="theme.js"></script>
</body>
</html>












