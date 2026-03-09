<?php
require 'db.php';
require 'auth.php';

requireLogin();

if (!isset($_GET['id'])) {
    die('Attempt ID required.');
}

$attempt_id = (int)$_GET['id'];

// Fetch attempt + exam ownership
$stmt = $pdo->prepare(
    "SELECT a.*, a.student_fullname as full_name, a.student_index as index_number, e.id AS exam_id, e.title AS exam_title, e.user_id
    FROM attempts a
    JOIN exams e ON a.exam_id = e.id
    WHERE a.id = ? AND e.user_id = ?"
);
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    die('Attempt not found or access denied.');
}

// Fetch answers with question details
$stmt = $pdo->prepare(" 
    SELECT a.id AS answer_id, a.selected_option, a.theory_answer, a.file_upload, a.is_correct, a.marks_awarded,
           q.id AS question_id, q.q_type, q.question_text, q.correct_option, q.marks
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    WHERE a.attempt_id = ?
    ORDER BY q.id ASC
");
$stmt->execute([$attempt_id]);
$answers = $stmt->fetchAll();

$error = '';
$success = '';
$is_completed_attempt = isset($attempt['status']) && $attempt['status'] === 'completed';
$can_grade = $is_completed_attempt && !empty($answers);

if (!$is_completed_attempt) {
    $error = 'This attempt is still ongoing and has not been submitted yet.';
} elseif (empty($answers)) {
    $error = 'No submitted answers were found for this attempt.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    if (!$can_grade) {
        $error = 'This attempt cannot be graded yet.';
    } else {
        $marks_input = $_POST['marks_awarded'] ?? [];

        foreach ($answers as $ans) {
            if ($ans['q_type'] === 'mcq') {
                continue;
            }

            $ans_id = $ans['answer_id'];
            $max_marks = (float)$ans['marks'];
            $raw = isset($marks_input[$ans_id]) ? trim($marks_input[$ans_id]) : '';
            $value = ($raw === '') ? null : (float)$raw;

            if ($value !== null) {
                if ($value < 0) {
                    $value = 0;
                }
                if ($value > $max_marks) {
                    $value = $max_marks;
                }
            }

            $stmt_upd = $pdo->prepare('UPDATE answers SET marks_awarded = ? WHERE id = ?');
            $stmt_upd->execute([$value, $ans_id]);
        }

        // Recalculate attempt score
        $stmt = $pdo->prepare(" 
            SELECT q.q_type, q.marks, a.is_correct, a.marks_awarded
            FROM answers a
            JOIN questions q ON a.question_id = q.id
            WHERE a.attempt_id = ?
        ");
        $stmt->execute([$attempt_id]);
        $rows = $stmt->fetchAll();

        $score = 0;
        $total_marks = 0;
        foreach ($rows as $row) {
            $total_marks += (float)$row['marks'];
            if ($row['q_type'] === 'mcq') {
                if ($row['is_correct']) {
                    $score += (float)$row['marks'];
                }
            } else {
                $score += (float)($row['marks_awarded'] ?? 0);
            }
        }

        $stmt = $pdo->prepare('UPDATE attempts SET score = ?, total_marks = ? WHERE id = ?');
        $stmt->execute([$score, $total_marks, $attempt_id]);

        $success = 'Grades saved successfully.';

        // Refresh answers
        $stmt = $pdo->prepare(" 
            SELECT a.id AS answer_id, a.selected_option, a.theory_answer, a.file_upload, a.is_correct, a.marks_awarded,
                   q.id AS question_id, q.q_type, q.question_text, q.correct_option, q.marks
            FROM answers a
            JOIN questions q ON a.question_id = q.id
            WHERE a.attempt_id = ?
            ORDER BY q.id ASC
        ");
        $stmt->execute([$attempt_id]);
        $answers = $stmt->fetchAll();
        $can_grade = !empty($answers);
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grade Attempt - <?= htmlspecialchars($attempt['exam_title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        .answer-card {
            border: 1px solid var(--theme-card-border);
            border-radius: 12px;
            padding: 1rem;
            background: var(--theme-card-bg);
            margin-bottom: 1rem;
        }
        .answer-meta {
            color: var(--theme-muted);
            font-size: 0.875rem;
        }
        .answer-value {
            background: var(--bg-tertiary);
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 0.5rem;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="mb-1">Grade Attempt</h3>
                <div class="answer-meta">
                    <?= htmlspecialchars($attempt['exam_title']) ?> &middot;
                    <?= htmlspecialchars($attempt['full_name'] ?? 'Student') ?> (<?= htmlspecialchars($attempt['student_index']) ?>)
                </div>
            </div>
            <a href="exam_stats.php?id=<?= $attempt['exam_id'] ?>" class="btn btn-secondary">Back to Results</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($can_grade): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <?php foreach ($answers as $index => $ans): ?>
                    <div class="answer-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold">Q<?= $index + 1 ?> (<?= strtoupper($ans['q_type']) ?>)</div>
                                <div class="answer-meta">Marks: <?= $ans['marks'] ?></div>
                            </div>
                            <?php if ($ans['q_type'] === 'mcq'): ?>
                                <span class="badge <?= $ans['is_correct'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $ans['is_correct'] ? 'Correct' : 'Wrong' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2"><?= htmlspecialchars($ans['question_text']) ?></div>

                        <?php if ($ans['q_type'] === 'mcq'): ?>
                            <div class="answer-value">
                                Selected: <strong><?= htmlspecialchars($ans['selected_option'] ?? '-') ?></strong> |
                                Correct: <strong><?= htmlspecialchars($ans['correct_option']) ?></strong>
                            </div>
                        <?php elseif ($ans['q_type'] === 'fill_in'): ?>
                            <div class="answer-value">
                                Student Answer: <?= htmlspecialchars($ans['theory_answer'] ?? '-') ?><br>
                                Correct Answer: <strong><?= htmlspecialchars($ans['correct_option']) ?></strong>
                            </div>
                        <?php elseif ($ans['q_type'] === 'theory'): ?>
                            <div class="answer-value"><?= htmlspecialchars($ans['theory_answer'] ?? '-') ?></div>
                        <?php elseif ($ans['q_type'] === 'file'): ?>
                            <?php if (!empty($ans['file_upload'])): ?>
                                <div class="answer-value">
                                    <a href="<?= htmlspecialchars($ans['file_upload']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        Download Submission
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="answer-value">No file uploaded.</div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($ans['q_type'] !== 'mcq'): ?>
                            <div class="mt-3">
                                <label class="form-label">Marks Awarded (max <?= $ans['marks'] ?>)</label>
                                <input type="number" step="0.5" min="0" max="<?= $ans['marks'] ?>" name="marks_awarded[<?= $ans['answer_id'] ?>]" class="form-control" value="<?= htmlspecialchars($ans['marks_awarded'] ?? '') ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary w-100">Save Grades</button>
            </form>
        <?php else: ?>
            <div class="alert alert-info mb-0">No submitted result is available to grade for this attempt.</div>
        <?php endif; ?>
    </div>
    <script defer src="theme.js"></script>
</body>
</html>
