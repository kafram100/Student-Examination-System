<?php
require 'db.php';
require 'auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];

// Fetch exams for the logged-in lecturer
$stmt = $pdo->prepare("SELECT * FROM exams WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$exams = $stmt->fetchAll();
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lecturer Dashboard - Student Assessment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="assets/css/theme.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, var(--gray-900) 0%, var(--gray-800) 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
        }
        body {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        .navbar-modern {
            background: linear-gradient(135deg, #312e81 0%, #4338ca 100%);
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        body.theme-dark .navbar-modern {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            border-bottom-color: rgba(148,163,184,0.2);
        }
        .navbar-brand {
            color: #ffffff !important;
            text-shadow: 0 2px 6px rgba(0,0,0,0.35);
            letter-spacing: 0.2px;
        }
        .navbar-brand i {
            color: #e0e7ff;
        }
        .navbar-text {
            color: rgba(255,255,255,0.85) !important;
        }
        .navbar-modern .btn-outline-light {
            color: #ffffff;
            border-color: rgba(255,255,255,0.7);
        }
        .navbar-modern .btn-outline-light:hover {
            background: rgba(255,255,255,0.15);
            color: #ffffff;
        }
        .dashboard-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
        }
        .dashboard-subtitle {
            color: var(--gray-400);
            margin-top: 0.25rem;
        }
        .page-title {
            color: var(--text-primary);
        }
        .page-subtitle {
            color: var(--text-secondary);
        }
        .exam-card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: all var(--transition);
            border: 1px solid var(--gray-200);
            height: 100%;
            color: var(--text-primary);
        }
        .exam-card:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-4px);
        }
        .exam-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .exam-icon.exam { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #2563eb; }
        .exam-icon.quiz { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #059669; }
        .exam-icon.assignment { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #d97706; }
        .exam-title {
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        .exam-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        .exam-code {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-tertiary);
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius);
            font-family: monospace;
            font-size: 0.875rem;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
            border: 1px dashed var(--gray-200);
        }
        .exam-code:hover {
            background: var(--primary-light);
            color: white;
            border-color: transparent;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.875rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-published {
            background: var(--success-light);
            color: var(--success);
        }
        .status-draft {
            background: var(--warning-light);
            color: var(--warning);
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        .empty-state-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 3rem;
            color: white;
        }
        .btn-floating {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-xl);
            z-index: 1000;
        }
        .stats-row {
            margin-bottom: 2rem;
        }
        .quick-action {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-fast);
            text-decoration: none;
            color: var(--text-primary);
            border: 1px solid var(--gray-200);
        }
        .quick-action:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
            color: var(--primary);
        }
        .quick-action i {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(99, 102, 241, 0.12);
            color: white;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .dashboard-title {
                font-size: 1.25rem;
            }
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }
            .page-header .btn {
                width: 100%;
            }
            .exam-card {
                padding: 1rem;
            }
            .exam-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }
            .exam-title {
                font-size: 1rem;
            }
            .btn-floating {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
                bottom: 1rem;
                right: 1rem;
            }
            .navbar-brand {
                font-size: 1rem;
            }
            .navbar-text {
                display: none;
            }
            .quick-action {
                padding: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            .page-title {
                font-size: 1.5rem;
            }
            .exam-meta {
                font-size: 0.75rem;
            }
            .exam-code {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            .status-badge {
                font-size: 0.65rem;
                padding: 0.25rem 0.5rem;
            }
            .empty-state {
                padding: 2rem 1rem;
            }
            .empty-state-icon {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
        }
        
        /* Smart watch / Very small screens */
        @media (max-width: 320px) {
            .navbar-brand span {
                display: none;
            }
            .exam-title {
                font-size: 0.875rem;
            }
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Modern Navbar -->
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
        <div class="page-header d-flex justify-content-between align-items-start">
            <div>
                <h1 class="page-title">My Assessments</h1>
                <p class="page-subtitle">Manage your exams, quizzes, and assignments</p>
            </div>
            <a href="create_exam.php" class="btn btn-primary-modern btn-modern">
                <i class="bi bi-plus-lg me-2"></i>Create New Assessment
            </a>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="view_students.php" class="quick-action">
                    <i class="bi bi-people-fill"></i>
                    <div>
                        <div class="fw-semibold">View Students</div>
                        <small class="text-muted">See all registered students</small>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="create_exam.php" class="quick-action">
                    <i class="bi bi-file-earmark-plus"></i>
                    <div>
                        <div class="fw-semibold">Create Assessment</div>
                        <small class="text-muted">Set up a new exam or quiz</small>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <div class="quick-action" style="cursor: default;">
                    <i class="bi bi-bar-chart-fill" style="background: var(--success);"></i>
                    <div>
                        <div class="fw-semibold"><?= count($exams) ?> Assessments</div>
                        <small class="text-muted">Total created</small>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($exams)): ?>
            <!-- Empty State -->
            <div class="empty-state animate-fade-in">
                <div class="empty-state-icon">
                    <i class="bi bi-clipboard-data"></i>
                </div>
                <h3 class="mb-2">No Assessments Yet</h3>
                <p class="text-muted mb-4">Create your first exam, quiz, or assignment to get started</p>
                <a href="create_exam.php" class="btn btn-primary-modern btn-modern btn-lg">
                    <i class="bi bi-plus-lg me-2"></i>Create Your First Assessment
                </a>
            </div>
        <?php else: ?>
            <!-- Assessment Cards Grid -->
            <div class="row g-4">
                <?php foreach ($exams as $index => $exam): 
                    $icon_class = 'exam';
                    if ($exam['exam_type'] === 'Quiz') $icon_class = 'quiz';
                    if ($exam['exam_type'] === 'Assignment') $icon_class = 'assignment';
                ?>
                    <div class="col-md-6 col-lg-4 animate-fade-in" style="animation-delay: <?= $index * 0.1 ?>s">
                        <div class="exam-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="exam-icon <?= $icon_class ?>">
                                    <i class="bi bi-<?= $icon_class === 'exam' ? 'file-text' : ($icon_class === 'quiz' ? 'question-circle' : 'journal-text') ?>"></i>
                                </div>
                                <span class="status-badge <?= $exam['is_published'] ? 'status-published' : 'status-draft' ?>">
                                    <i class="bi bi-<?= $exam['is_published'] ? 'check-circle' : 'pencil' ?>" style="font-size: 0.625rem;"></i>
                                    <?= $exam['is_published'] ? 'Published' : 'Draft' ?>
                                </span>
                            </div>
                            
                            <h3 class="exam-title"><?= htmlspecialchars($exam['title']) ?></h3>
                            <p class="exam-meta">
                                <i class="bi bi-book me-1"></i><?= htmlspecialchars($exam['course_name']) ?>
                                <?php if ($exam['course_code']): ?>
                                    <span class="text-muted">(<?= htmlspecialchars($exam['course_code']) ?>)</span>
                                <?php endif; ?>
                            </p>
                            
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="text-muted small">
                                    <i class="bi bi-clock me-1"></i><?= ($exam['duration'] <= 0) ? 'Unlimited' : $exam['duration'] . ' min' ?>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-tag me-1"></i><?= htmlspecialchars($exam['exam_type']) ?>
                                </span>
                            </div>
                            
                            <div class="exam-code mb-3" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($exam['exam_code']) ?>'); this.querySelector('span').textContent='Copied!'; setTimeout(() => this.querySelector('span').textContent='<?= htmlspecialchars($exam['exam_code']) ?>', 2000);">
                                <i class="bi bi-key"></i>
                                <span><?= htmlspecialchars($exam['exam_code']) ?></span>
                                <i class="bi bi-copy"></i>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="view_exam.php?id=<?= $exam['id'] ?>" class="btn btn-primary-modern btn-modern flex-fill">
                                    <i class="bi bi-gear me-1"></i>Manage
                                </a>
                                <a href="exam_stats.php?id=<?= $exam['id'] ?>" class="btn btn-outline-modern btn-modern">
                                    <i class="bi bi-graph-up"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script defer src="theme.js"></script>
</body>
</html>

