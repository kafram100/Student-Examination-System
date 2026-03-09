<?php
require 'db.php';
require 'auth.php';

requireLogin();

// Only allow lecturers to access this page
if ($_SESSION['role'] !== 'lecturer') {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all exams for this lecturer
$stmt = $pdo->prepare("SELECT * FROM exams WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$exams = $stmt->fetchAll();

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Students Overview - Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="assets/css/theme.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        /* Base body styles with proper theme support */
        body {
            background: var(--bg-secondary) !important;
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
        }
        
        /* Ensure proper background in all modes */
        html {
            background: var(--bg-secondary) !important;
        }
        
        /* Light mode defaults */
        :root {
            --card-background: var(--bg-primary);
            --card-border-color: var(--gray-200);
            --header-bg-start: var(--gray-50);
            --header-bg-end: var(--bg-secondary);
            --header-border-color: var(--gray-200);
            --shadow-color: rgba(0, 0, 0, 0.1);
            --shadow-hover-color: rgba(0, 0, 0, 0.15);
        }
        
        /* Dark mode overrides for better contrast */
        body.theme-dark {
            --card-background: #1e293b;
            --card-border-color: #334155;
            --header-bg-start: #334155;
            --header-bg-end: #1e293b;
            --header-border-color: #475569;
            --shadow-color: rgba(0, 0, 0, 0.5);
            --shadow-hover-color: rgba(0, 0, 0, 0.7);
        }
        
        .student-card {
            background: var(--card-background);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px var(--shadow-color), 0 2px 4px -1px var(--shadow-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--card-border-color);
            margin-bottom: 1rem;
        }
        
        .student-card:hover {
            box-shadow: 0 20px 25px -5px var(--shadow-hover-color), 0 10px 10px -5px var(--shadow-hover-color);
            transform: translateY(-2px);
            border-color: var(--primary);
        }
        
        .exam-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-background);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 1px 3px 0 var(--shadow-color), 0 1px 2px 0 var(--shadow-color);
            border: 1px solid var(--card-border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card:hover {
            box-shadow: 0 20px 25px -5px var(--shadow-hover-color), 0 10px 10px -5px var(--shadow-hover-color);
            transform: translateY(-4px);
            border-color: var(--primary);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        /* Navbar improvements */
        .navbar-modern {
            background: linear-gradient(135deg, #312e81 0%, #4338ca 100%);
            border-bottom: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        body.theme-dark .navbar-modern {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-bottom-color: rgba(148,163,184,0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }
        
        .navbar-brand {
            color: #ffffff !important;
            text-shadow: 0 2px 6px rgba(0,0,0,0.35);
            letter-spacing: 0.2px;
        }
        
        .navbar-brand i {
            color: #e0e7ff;
        }
        .navbar-actions {
            gap: 0.65rem;
        }

        .navbar-text {
            color: #f8fafc !important;
            font-weight: 600;
            margin: 0;
        }

        .navbar-modern .btn-outline-light {
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.7) !important;
            background: rgba(255, 255, 255, 0.08);
        }

        .navbar-modern .btn-outline-light:hover,
        .navbar-modern .btn-outline-light:focus-visible {
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.9) !important;
            background: rgba(255, 255, 255, 0.2);
        }

        .theme-toggle-btn {
            width: 2.2rem;
            height: 2.2rem;
            padding: 0;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .theme-toggle-btn i {
            font-size: 0.9rem;
            line-height: 1;
            color: inherit;
        }

        .logout-btn {
            padding: 0.36rem 0.8rem;
            font-weight: 500;
        }

        body.theme-dark .navbar-modern .btn-outline-light {
            color: #e2e8f0 !important;
            border-color: rgba(148, 163, 184, 0.8) !important;
            background: rgba(15, 23, 42, 0.35);
        }

        body.theme-dark .navbar-modern .btn-outline-light:hover,
        body.theme-dark .navbar-modern .btn-outline-light:focus-visible {
            color: #f8fafc !important;
            border-color: rgba(148, 163, 184, 1) !important;
            background: rgba(51, 65, 85, 0.75);
        }
        
        /* Page header */
        .page-title {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 0.25rem;
            font-size: 1.875rem;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-top: 0.25rem;
        }
        
        /* Card modern improvements */
        .card-modern {
            background: var(--card-background);
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 6px -1px var(--shadow-color), 0 2px 4px -1px var(--shadow-color);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, var(--header-bg-start) 0%, var(--header-bg-end) 100%);
            border-bottom: 1px solid var(--header-border-color);
            padding: 1.25rem 1.5rem;
        }
        
        .card-header-modern h3 {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.25rem;
        }
        body.theme-dark .card-header-modern {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%) !important;
            border-bottom-color: #334155 !important;
        }

        body.theme-dark .card-header-modern h3,
        body.theme-dark .card-header-modern i {
            color: #e2e8f0 !important;
        }
        
        .card-body {
            background: transparent;
            color: var(--text-primary);
        }
        
        /* Text colors */
        .text-muted {
            color: var(--text-muted) !important;
        }
        
        /* Links */
        a.text-decoration-none {
            transition: all var(--transition-fast);
        }
        
        a.text-decoration-none:hover {
            opacity: 0.9;
        }
        
        /* Empty state improvements */
        .text-center.text-muted {
            color: var(--text-muted) !important;
        }
        
        body.theme-dark .text-center.text-muted p {
            color: var(--text-secondary);
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .stat-label {
                font-size: 0.8rem;
            }
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
            <div class="navbar-actions d-flex align-items-center">
                <span class="navbar-text">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <button type="button" id="themeToggleBtn" class="btn btn-outline-light btn-sm theme-toggle-btn" aria-label="Switch to dark mode" title="Switch to dark mode">
                    <i id="themeSunIcon" class="bi bi-sun-fill"></i>
                    <i id="themeMoonIcon" class="bi bi-moon-stars-fill d-none"></i>
                </button>
                <form method="POST" action="logout.php" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <button type="submit" class="btn btn-outline-light btn-sm logout-btn">
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
                <h1 class="page-title">Students Overview</h1>
                <p class="page-subtitle">Overview of students who took your exams</p>
            </div>
        </div>

        <!-- Exam Selection -->
        <div class="card-modern mb-4">
            <div class="card-header-modern">
                <h3 class="mb-0"><i class="bi bi-journal me-2"></i>Select Exam</h3>
            </div>
            <div class="card-body">
                <?php if (empty($exams)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-journal-x fs-1 mb-2"></i>
                        <p>No exams available. Create an exam first.</p>
                        <a href="create_exam.php" class="btn btn-primary">Create Exam</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($exams as $exam): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="exam-stats">
                                    <a href="exam_stats.php?id=<?= $exam['id'] ?>" class="text-decoration-none">
                                        <div class="stat-card">
                                            <div class="stat-value"><?= htmlspecialchars($exam['title']) ?></div>
                                            <div class="stat-label"><?= htmlspecialchars($exam['course_name']) ?></div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Overall Stats -->
        <?php if (!empty($exams)): ?>
            <div class="card-modern">
                <div class="card-header-modern">
                    <h3 class="mb-0"><i class="bi bi-graph-up me-2"></i>Overall Statistics</h3>
                </div>
                <div class="card-body">
                    <?php
                    // Get overall statistics for all exams by this lecturer
                    $overall_stats = $pdo->prepare("
                        SELECT 
                            COUNT(DISTINCT a.student_index) as total_students,
                            COUNT(a.id) as total_attempts,
                            AVG(a.score) as avg_score,
                            MAX(a.score) as highest_score,
                            MIN(a.score) as lowest_score
                        FROM attempts a
                        JOIN exams e ON a.exam_id = e.id
                        WHERE e.user_id = ?
                    ");
                    $overall_stats->execute([$user_id]);
                    $stats = $overall_stats->fetch();
                    ?>
                    
                    <div class="exam-stats">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['total_students'] ?></div>
                            <div class="stat-label">Unique Students</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['total_attempts'] ?></div>
                            <div class="stat-label">Total Attempts</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['avg_score'] ? number_format($stats['avg_score'], 2) : '0.00' ?></div>
                            <div class="stat-label">Avg. Score</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['highest_score'] ?: '0' ?></div>
                            <div class="stat-label">Highest Score</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
        (function () {
            const THEME_KEY = 'ses-theme';
            const DARK_CLASS = 'theme-dark';
            const toggleButton = document.getElementById('themeToggleBtn');
            const sunIcon = document.getElementById('themeSunIcon');
            const moonIcon = document.getElementById('themeMoonIcon');

            function readCookieTheme() {
                const cookieTheme = document.cookie
                    .split('; ')
                    .find(function (row) { return row.startsWith('theme='); });

                return cookieTheme ? cookieTheme.split('=')[1] : '';
            }

            function applyTheme(theme) {
                const isDark = theme === 'dark';

                document.body.classList.toggle(DARK_CLASS, isDark);
                document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
                document.cookie = 'theme=' + (isDark ? 'dark' : 'light') + '; path=/; max-age=31536000';

                if (sunIcon && moonIcon) {
                    sunIcon.classList.toggle('d-none', isDark);
                    moonIcon.classList.toggle('d-none', !isDark);
                }

                if (toggleButton) {
                    const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
                    toggleButton.setAttribute('aria-label', label);
                    toggleButton.setAttribute('title', label);
                }
            }

            let savedTheme = localStorage.getItem(THEME_KEY) || readCookieTheme();
            if (savedTheme !== 'dark' && savedTheme !== 'light') {
                savedTheme = 'light';
            }

            applyTheme(savedTheme);

            if (toggleButton) {
                toggleButton.addEventListener('click', function () {
                    const nextTheme = document.body.classList.contains(DARK_CLASS) ? 'light' : 'dark';
                    localStorage.setItem(THEME_KEY, nextTheme);
                    applyTheme(nextTheme);
                });
            }
        })();
    </script>
</body>
</html>
