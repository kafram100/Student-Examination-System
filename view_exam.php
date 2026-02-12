<?php
require 'db.php';
require 'auth.php';

requireLogin();

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$exam_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch Exam
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Exam not found or access denied.");
}

// Fetch Questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();

// Handle Publish/Unpublish Action
if (isset($_POST['publish'])) {
    checkCSRF();
    $stmt = $pdo->prepare("UPDATE exams SET is_published = 1 WHERE id = ?");
    $stmt->execute([$exam_id]);
    header("Location: view_exam.php?id=$exam_id");
    exit;
}

if (isset($_POST['unpublish'])) {
    checkCSRF();
    $stmt = $pdo->prepare("UPDATE exams SET is_published = 0 WHERE id = ?");
    $stmt->execute([$exam_id]);
    header("Location: view_exam.php?id=$exam_id");
    exit;
}

// Handle Result Release
if (isset($_POST['toggle_results'])) {
    checkCSRF();
    $new_status = $exam['results_released'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE exams SET results_released = ? WHERE id = ?");
    $stmt->execute([$new_status, $exam_id]);
    header("Location: view_exam.php?id=$exam_id");
    exit;
}

// Handle Exam Deletion
if (isset($_POST['delete_exam'])) {
    checkCSRF();
    // Delete exam (cascade will handle related data)
    $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ? AND user_id = ?");
    $stmt->execute([$exam_id, $user_id]);
    header('Location: dashboard.php?deleted=1');
    exit;
}

// Handle Question Deletion
if (isset($_POST['delete_question'])) {
    checkCSRF();
    $question_id = $_POST['question_id'];
    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ? AND exam_id = ?");
    $stmt->execute([$question_id, $exam_id]);
    header("Location: view_exam.php?id=$exam_id");
    exit;
}

$csrf_token = generateCSRFToken();
$student_link = "student_login.php?code=" . urlencode($exam['exam_code']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Manage Exam - <?= htmlspecialchars($exam['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --primary: #6366f1;
            --primary-light: #818cf8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --primary: #818cf8;
            --primary-light: #a5b4fc;
            --success: #34d399;
            --warning: #fbbf24;
            --danger: #f87171;
            --info: #60a5fa;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.3), 0 1px 2px -1px rgb(0 0 0 / 0.3);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.4), 0 2px 4px -2px rgb(0 0 0 / 0.4);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.5), 0 4px 6px -4px rgb(0 0 0 / 0.5);
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
        }
        
        /* Navbar */
        .navbar-modern {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            padding: 1rem 0;
            box-shadow: var(--shadow-lg);
        }
        
        [data-theme="dark"] .navbar-modern {
            background: linear-gradient(135deg, #020617 0%, #1e1b4b 100%);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }
        
        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .theme-toggle:hover {
            transform: scale(1.1);
        }
        
        /* Breadcrumb */
        .breadcrumb {
            background: var(--bg-secondary);
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .breadcrumb-item.active {
            color: var(--text-secondary);
        }
        
        /* Cards */
        .card-modern {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .card-modern:hover {
            box-shadow: var(--shadow-lg);
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 1.5rem;
        }
        
        .card-body-modern {
            padding: 1.5rem;
        }
        
        /* Badges */
        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .badge-published {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .badge-draft {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        
        .badge-hidden {
            background: rgba(148, 163, 184, 0.15);
            color: var(--text-muted);
        }
        
        .badge-released {
            background: rgba(59, 130, 246, 0.15);
            color: var(--info);
        }
        
        /* Buttons */
        .btn-modern {
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }
        
        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            color: white;
        }
        
        .btn-success-modern {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
        }
        
        .btn-warning-modern {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: #1f2937;
        }
        
        .btn-danger-modern {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
        }
        
        .btn-info-modern {
            background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
            color: white;
        }
        
        .btn-outline-modern {
            background: transparent;
            border: 2px solid var(--border-color);
            color: var(--text-secondary);
        }
        
        .btn-outline-modern:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--bg-primary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
        }
        
        .info-icon.code { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
        .info-icon.time { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .info-icon.status { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .info-icon.results { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.125rem;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* Student Link Box */
        .link-box {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
            border: 2px dashed var(--primary);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .link-box a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            font-family: monospace;
            font-size: 0.9375rem;
        }
        .link-copy {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .link-copy:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(99, 102, 241, 0.08);
        }
        
        /* Questions Section */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2.5rem 0 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .question-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        
        .question-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .question-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 700;
            margin-right: 0.75rem;
        }
        
        .question-type {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--bg-primary);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }
        
        .question-text {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }
        
        .question-marks {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.9375rem;
        }
        
        .options-list {
            list-style: none;
            padding: 0;
            margin: 0.75rem 0 0;
        }
        
        .options-list li {
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.375rem;
            border-radius: 8px;
            font-size: 0.9375rem;
            color: var(--text-secondary);
            background: var(--bg-primary);
        }
        
        .options-list li.correct {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            font-weight: 600;
        }
        
        .question-hint {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-style: italic;
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: var(--bg-primary);
            border-radius: 8px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--bg-card);
            border: 2px dashed var(--border-color);
            border-radius: 16px;
        }
        
        .empty-state-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: white;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.4s ease forwards;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            .card-header-modern {
                padding: 1rem;
            }
            .card-header-modern h2 {
                font-size: 1.25rem;
            }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            .info-item {
                padding: 0.75rem;
            }
            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            .btn-group {
                width: 100%;
                flex-direction: column;
            }
            .btn-group .btn {
                width: 100%;
                margin: 0.25rem 0;
            }
            .question-card {
                padding: 1rem;
            }
            .question-header {
                flex-direction: column;
                gap: 0.75rem;
            }
            .question-text {
                font-size: 0.9375rem;
            }
            .d-flex.flex-wrap.gap-2 {
                flex-direction: column;
            }
            .d-flex.flex-wrap.gap-2 .btn,
            .d-flex.flex-wrap.gap-2 form {
                width: 100%;
            }
            .link-box {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .link-copy {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .navbar-brand {
                font-size: 1rem;
            }
            .breadcrumb {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }
            .badge-modern {
                font-size: 0.65rem;
                padding: 0.375rem 0.75rem;
            }
            .question-number {
                width: 24px;
                height: 24px;
                font-size: 0.75rem;
            }
            .options-list li {
                font-size: 0.875rem;
                padding: 0.375rem 0.5rem;
            }
            .btn-modern {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
        }
        
        /* Smart watch / Very small screens */
        @media (max-width: 320px) {
            .navbar-brand span {
                display: none;
            }
            .card-body-modern {
                padding: 1rem;
            }
            .info-icon {
                width: 32px;
                height: 32px;
                font-size: 0.875rem;
            }
            .info-label {
                font-size: 0.65rem;
            }
            .info-value {
                font-size: 0.875rem;
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
            <form method="POST" action="logout.php" class="d-flex align-items-center">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <button type="submit" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </button>
            </form>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="dashboard.php"><i class="bi bi-house-door me-1"></i>Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($exam['title']) ?></li>
            </ol>
        </nav>

        <!-- Exam Details Card -->
        <div class="card-modern animate-fade-in">
            <div class="card-header-modern">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2 class="mb-2"><?= htmlspecialchars($exam['title']) ?></h2>
                        <p class="mb-0 opacity-75"><i class="bi bi-book me-2"></i><?= htmlspecialchars($exam['course_name']) ?></p>
                    </div>
                    <span class="badge-modern <?= $exam['is_published'] ? 'badge-published' : 'badge-draft' ?>">
                        <?= $exam['is_published'] ? 'Published' : 'Draft' ?>
                    </span>
                </div>
            </div>
            
            <div class="card-body-modern">
                <!-- Info Grid -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon code"><i class="bi bi-key"></i></div>
                        <div class="info-content">
                            <div class="info-label">Access Code</div>
                            <div class="info-value" style="font-family: monospace;"><?= htmlspecialchars($exam['exam_code']) ?></div>
                        </div>
                    </div>
                    
                    <?php if ($exam['exam_type'] === 'Exam' || $exam['exam_type'] === 'Mid-semester'): ?>
                    <div class="info-item">
                        <div class="info-icon time"><i class="bi bi-clock"></i></div>
                        <div class="info-content">
                            <div class="info-label">Duration</div>
                            <div class="info-value"><?= $exam['duration'] ?> minutes</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <div class="info-icon status"><i class="bi bi-calendar"></i></div>
                        <div class="info-content">
                            <div class="info-label">Availability</div>
                            <div class="info-value">
                                <?= $exam['start_datetime'] ? date('M d, Y', strtotime($exam['start_datetime'])) : 'Anytime' ?>
                                <?php if ($exam['end_datetime']): ?>
                                    - <?= date('M d, Y', strtotime($exam['end_datetime'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon results"><i class="bi bi-trophy"></i></div>
                        <div class="info-content">
                            <div class="info-label">Results</div>
                            <div class="info-value">
                                <span class="badge-modern <?= $exam['results_released'] ? 'badge-released' : 'badge-hidden' ?>">
                                    <?= $exam['results_released'] ? 'Released' : 'Hidden' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($exam['assessment_file']): ?>
                <div class="mb-3">
                    <a href="<?= htmlspecialchars($exam['assessment_file']) ?>" target="_blank" class="btn btn-outline-modern btn-modern">
                        <i class="bi bi-file-earmark-arrow-down me-2"></i>Download Assessment File
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="edit_exam.php?id=<?= $exam_id ?>" class="btn btn-outline-modern btn-modern">
                        <i class="bi bi-pencil me-2"></i>Edit Details
                    </a>
                    
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to <?= $exam['is_published'] ? 'unpublish' : 'publish' ?> this exam?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" name="<?= $exam['is_published'] ? 'unpublish' : 'publish' ?>" class="btn btn-success-modern btn-modern">
                            <i class="bi bi-<?= $exam['is_published'] ? 'eye-slash' : 'check-circle' ?> me-2"></i>
                            <?= $exam['is_published'] ? 'Unpublish Exam' : 'Publish Exam' ?>
                        </button>
                    </form>
                    
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" name="toggle_results" class="btn btn-warning-modern btn-modern">
                            <i class="bi bi-<?= $exam['results_released'] ? 'eye-slash' : 'eye' ?> me-2"></i>
                            <?= $exam['results_released'] ? 'Hide Results' : 'Release Results' ?>
                        </button>
                    </form>

                    <a href="exam_stats.php?id=<?= $exam_id ?>" class="btn btn-info-modern btn-modern">
                        <i class="bi bi-graph-up me-2"></i>View Results
                    </a>
                    
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to DELETE this exam? This action cannot be undone!');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" name="delete_exam" class="btn btn-danger-modern btn-modern">
                            <i class="bi bi-trash me-2"></i>Delete
                        </button>
                    </form>
                </div>
                
                <?php if ($exam['is_published']): ?>
                    <div class="link-box">
                        <i class="bi bi-link-45deg" style="font-size: 1.25rem; color: var(--primary);"></i>
                        <div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Student Access Link</div>
                            <a href="<?= htmlspecialchars($student_link) ?>" target="_blank">
                                <?= htmlspecialchars($student_link) ?>
                            </a>
                        </div>
                        <button type="button" class="link-copy" id="copyStudentLink" data-link="<?= htmlspecialchars($student_link) ?>">
                            <i class="bi bi-clipboard"></i>
                            <span>Copy</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Questions Section -->
        <div class="section-header">
            <h3 class="section-title">
                <i class="bi bi-question-circle me-2" style="color: var(--primary);"></i>
                Assessment Questions <span style="color: var(--text-muted); font-size: 1rem;">(<?= count($questions) ?>)</span>
            </h3>
            <?php if (!$exam['is_published']): ?>
                <div class="btn-group">
                   <a href="add_question.php?exam_id=<?= $exam_id ?>" class="btn btn-primary-modern btn-modern">
                      <i class="bi bi-plus-lg me-2"></i>Add Question
                   </a>
                   <a href="import_questions.php?exam_id=<?= $exam_id ?>" class="btn btn-outline-modern btn-modern">
                      <i class="bi bi-upload me-2"></i>Import CSV
                   </a>
                   <a href="download_template.php" class="btn btn-outline-modern btn-modern" title="Download CSV Template">
                      <i class="bi bi-download me-2"></i>Template
                   </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($questions)): ?>
            <div class="empty-state animate-fade-in">
                <div class="empty-state-icon">
                    <i class="bi bi-question-lg"></i>
                </div>
                <h4 class="mb-2">No Questions Yet</h4>
                <p class="text-muted mb-4">Add your first question to get started with this assessment</p>
                <?php if (!$exam['is_published']): ?>
                    <a href="add_question.php?exam_id=<?= $exam_id ?>" class="btn btn-primary-modern btn-modern btn-lg">
                        <i class="bi bi-plus-lg me-2"></i>Add First Question
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="questions-list">
                <?php foreach ($questions as $index => $q): ?>
                    <div class="question-card animate-fade-in" style="animation-delay: <?= $index * 0.05 ?>s">
                        <div class="question-header">
                            <div class="d-flex align-items-start">
                                <span class="question-number"><?= $index + 1 ?></span>
                                <div>
                                    <div class="question-text"><?= htmlspecialchars($q['question_text']) ?></div>
                                    <span class="question-type"><?= strtoupper($q['q_type']) ?></span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="question-marks"><?= $q['marks'] ?> marks</span>
                                <a href="edit_question.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-modern" title="Edit Question">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this question?');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                    <button type="submit" name="delete_question" class="btn btn-sm btn-outline-danger" title="Delete Question" style="border-color: var(--danger); color: var(--danger);">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <?php if ($q['q_type'] === 'mcq'): ?>
                            <ul class="options-list">
                                <li class="<?= $q['correct_option'] == 'A' ? 'correct' : '' ?>">A) <?= htmlspecialchars($q['option_a']) ?></li>
                                <li class="<?= $q['correct_option'] == 'B' ? 'correct' : '' ?>">B) <?= htmlspecialchars($q['option_b']) ?></li>
                                <?php if ($q['option_c']): ?>
                                    <li class="<?= $q['correct_option'] == 'C' ? 'correct' : '' ?>">C) <?= htmlspecialchars($q['option_c']) ?></li>
                                <?php endif; ?>
                                <?php if ($q['option_d']): ?>
                                    <li class="<?= $q['correct_option'] == 'D' ? 'correct' : '' ?>">D) <?= htmlspecialchars($q['option_d']) ?></li>
                                <?php endif; ?>
                                <?php if ($q['option_e']): ?>
                                    <li class="<?= $q['correct_option'] == 'E' ? 'correct' : '' ?>">E) <?= htmlspecialchars($q['option_e']) ?></li>
                                <?php endif; ?>
                            </ul>
                        <?php elseif ($q['q_type'] === 'theory'): ?>
                            <div class="question-hint">
                                <i class="bi bi-pencil-square me-2"></i>Students will type a text response
                            </div>
                        <?php elseif ($q['q_type'] === 'file'): ?>
                            <div class="question-hint">
                                <i class="bi bi-file-earmark-arrow-up me-2"></i>Students will upload a file response
                            </div>
                            <?php if (!empty($q['option_e'])): ?>
                                <div class="mt-2">
                                    <a href="<?= htmlspecialchars($q['option_e']) ?>" target="_blank" class="btn btn-sm btn-outline-modern">
                                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Question File
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script defer src="theme.js"></script>
    <script>
        const copyBtn = document.getElementById('copyStudentLink');
        if (copyBtn) {
            copyBtn.addEventListener('click', async () => {
                const link = copyBtn.getAttribute('data-link');
                try {
                    await navigator.clipboard.writeText(link);
                    copyBtn.querySelector('span').textContent = 'Copied';
                    copyBtn.querySelector('i').className = 'bi bi-check2';
                    setTimeout(() => {
                        copyBtn.querySelector('span').textContent = 'Copy';
                        copyBtn.querySelector('i').className = 'bi bi-clipboard';
                    }, 1500);
                } catch (e) {
                    alert('Copy failed. Please select and copy the link manually.');
                }
            });
        }
    </script>
</body>
</html>

