<?php
require 'db.php';
session_start();

if (!isset($_SESSION['student_fullname']) || !isset($_SESSION['student_index']) || !isset($_SESSION['exam_id'])) {
    header("Location: student_login.php");
    exit;
}

$student_fullname = $_SESSION['student_fullname'];
$student_index = $_SESSION['student_index'];
$exam_id = $_SESSION['exam_id'];

// Fetch Exam Info (to check if results released)
$stmt = $pdo->prepare("SELECT title, results_released FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

// Use the student info from the attempt record
$student = ['full_name' => $student_fullname];

// Fetch Attempt Info (Get the most recent completed attempt)
$stmt = $pdo->prepare("SELECT * FROM attempts WHERE exam_id = ? AND student_index = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$exam_id, $student_index]);
$attempt = $stmt->fetch();

if (!$attempt || $attempt['status'] != 'completed') {
    die("Exam not completed or not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        body {
            background: var(--theme-bg);
            color: var(--theme-text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .result-card {
            width: 100%;
            max-width: 520px;
            padding: 24px;
            text-align: center;
            background: var(--theme-card-bg);
            border: 1px solid var(--theme-card-border);
            border-radius: 16px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
        }
        .result-card .text-muted {
            color: var(--theme-muted) !important;
        }
        .progress {
            background: var(--theme-table-stripe);
            height: 10px;
            border-radius: 999px;
        }
        .progress-bar {
            border-radius: 999px;
        }
        body.theme-dark .result-card {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
        }
        body.theme-dark .progress {
            background: rgba(255,255,255,0.12);
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .result-card {
                padding: 1.5rem;
                margin: 1rem;
            }
            .result-card h3 {
                font-size: 1.25rem;
            }
            .result-card h1 {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 1rem 0.5rem;
            }
            .result-card {
                padding: 1.25rem;
                border-radius: 12px;
            }
            .result-card h3 {
                font-size: 1.125rem;
            }
            .result-card h1 {
                font-size: 1.75rem;
            }
            .result-card .lead {
                font-size: 1rem;
            }
            .btn {
                width: 100%;
            }
        }
        
        /* Smart watch / Very small screens */
        @media (max-width: 320px) {
            .result-card {
                padding: 1rem;
            }
            .result-card h3 {
                font-size: 1rem;
            }
            .result-card h1 {
                font-size: 1.5rem;
            }
            .progress {
                height: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="result-card">
        <h3><?= htmlspecialchars($exam['title']) ?></h3>
        <p class="text-muted"><?= htmlspecialchars($student['full_name'] ?? 'N/A') ?> (<?= htmlspecialchars($student_index) ?>)</p>
        
        <?php if ($exam['results_released']): ?>
            <div class="mt-4">
                <h1><?= (int)$attempt['score'] ?> / <?= (int)$attempt['total_marks'] ?></h1>
                <p class="lead">Your Score</p>
                <?php 
                    $percentage = ($attempt['total_marks'] > 0) ? ($attempt['score'] / $attempt['total_marks']) * 100 : 0;
                ?>
                <div class="progress mb-3">
                    <div class="progress-bar <?= $percentage >= 50 ? 'bg-success' : 'bg-danger' ?>" role="progressbar" style="width: <?= $percentage ?>%"></div>
                </div>
                <p><?= round($percentage, 2) ?>%</p>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mt-4">
                <h4>Results Not Yet Released</h4>
                <p>Please check back later or contact your lecturer.</p>
            </div>
            <p>You have successfully submitted your exam.</p>
        <?php endif; ?>
        
        <?php if (!empty($attempt['notes'])): ?>
            <div class="alert alert-info mt-3">
                <strong>Note:</strong> <?= htmlspecialchars($attempt['notes']) ?>
            </div>
        <?php endif; ?>
        
        <a href="student_login.php" class="btn btn-outline-primary mt-3">Home</a>
    </div>
    <script defer src="theme.js"></script>
</body>
</html>

