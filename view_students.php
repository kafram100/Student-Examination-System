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
        .student-card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: all var(--transition);
            border: 1px solid var(--gray-200);
            margin-bottom: 1rem;
        }
        
        .exam-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
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

    <script defer src="theme.js"></script>
</body>
</html>