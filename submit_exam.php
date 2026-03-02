<?php
require 'db.php';
require 'auth.php';

if (!isset($_SESSION['student_fullname']) || !isset($_SESSION['student_index']) || !isset($_SESSION['exam_id'])) {
    header("Location: student_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: take_exam.php");
    exit;
}

checkCSRF();

$student_index = $_SESSION['student_index'];
$exam_id = $_SESSION['exam_id'];

// Fetch Exam Details to check if proctoring was required
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

// Check if proctoring was required for this exam type
$requires_proctoring = false;
if (isset($exam['exam_type'])) {
    $exam_type = strtolower($exam['exam_type']);
    if (strpos($exam_type, 'examination') !== false || strpos($exam_type, 'mid-semester') !== false || strpos($exam_type, 'mid semester') !== false) {
        $requires_proctoring = true;
    }
}

// If proctoring was required, check if session was properly established
if ($requires_proctoring && !isset($_SESSION['proctoring_enabled'])) {
    // Log security violation
    $stmt = $pdo->prepare(
        "INSERT INTO exam_security_logs 
        (exam_attempt_id, user_id, activity_type, description, timestamp, ip_address, severity) 
        VALUES (?, ?, ?, ?, NOW(), ?, 'critical')"
    );
    $stmt->execute([
        $attempt['id'] ?? 0,
        $_SESSION['user_id'] ?? 0,
        'proctoring_bypass',
        'Attempted to submit exam without proper proctoring session',
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Redirect to violation page
    header("Location: exam_terminated.php?reason=Proctoring bypass attempt detected");
    exit;
}

// Mark proctoring as completed if it was required
if ($requires_proctoring && isset($_SESSION['proctoring_enabled'])) {
    $stmt = $pdo->prepare("UPDATE exam_sessions_proctoring SET proctoring_status = 'completed', end_time = NOW() WHERE exam_attempt_id = ?");
    $stmt->execute([$attempt['id'] ?? 0]);
    
    // Clear proctoring session
    unset($_SESSION['proctoring_enabled']);
}

// Get Attempt
$stmt = $pdo->prepare("SELECT * FROM attempts WHERE exam_id = ? AND student_index = ? AND status = 'ongoing'");
$stmt->execute([$exam_id, $student_index]);
$attempt = $stmt->fetch();

if (!$attempt) {
    // Maybe already submitted?
    header("Location: student_result.php");
    exit;
}

$attempt_id = $attempt['id'];
$answers_input = isset($_POST['answers']) ? $_POST['answers'] : [];
$theory_answers = isset($_POST['theory_answers']) ? $_POST['theory_answers'] : [];
$fill_in_answers = isset($_POST['fill_in_answers']) ? $_POST['fill_in_answers'] : [];
$is_auto_submit = !empty($_POST['auto_submit']);
$has_payload = !empty($answers_input) || !empty($theory_answers);
if (!$has_payload && !empty($_FILES)) {
    foreach ($_FILES as $file) {
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $has_payload = true;
            break;
        }
    }
}
$allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$allowed_mimes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png',
];
$max_upload_bytes = 10 * 1024 * 1024;
$safe_index = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$student_index);

// Fetch Questions and Correct Answers
$stmt = $pdo->prepare("SELECT id, q_type, correct_option, marks FROM questions WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_score = 0;
$total_marks = 0;

foreach ($questions as $q) {
    $qid = $q['id'];
    $q_type = $q['q_type'];

    $stmt_find = $pdo->prepare("SELECT id, selected_option, theory_answer, file_upload FROM answers WHERE attempt_id = ? AND question_id = ? LIMIT 1");
    $stmt_find->execute([$attempt_id, $qid]);
    $existing = $stmt_find->fetch(PDO::FETCH_ASSOC);
    $use_existing = $is_auto_submit && !$has_payload;
    
    $selected = null;
    $theory_ans = null;
    $file_path = null;
    $is_correct = 0;

    if ($q_type === 'mcq') {
        if ($use_existing && $existing) {
            $selected = $existing['selected_option'];
        } else {
            $selected = isset($answers_input[$qid]) ? $answers_input[$qid] : null;
        }
        $is_correct = ($selected === $q['correct_option']) ? 1 : 0;
        if ($is_correct) {
            $total_score += $q['marks'];
        }
    } elseif ($q_type === 'fill_in') {
        if ($use_existing && $existing) {
            $theory_ans = $existing['theory_answer'];
        } else {
            $theory_ans = isset($fill_in_answers[$qid]) ? trim($fill_in_answers[$qid]) : null;
        }
        if ($theory_ans === '') {
            $theory_ans = null;
        }
    } elseif ($q_type === 'theory') {
        if ($use_existing && $existing) {
            $theory_ans = $existing['theory_answer'];
        } else {
            $theory_ans = isset($theory_answers[$qid]) ? trim($theory_answers[$qid]) : null;
        }
        if ($theory_ans === '') {
            $theory_ans = null;
        }
    } elseif ($q_type === 'file') {
        if ($use_existing && $existing) {
            $file_path = $existing['file_upload'];
        } else {
            $file_key = "file_answers_$qid";
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] !== UPLOAD_ERR_NO_FILE) {
                $prefix = 'submission_' . $qid . '_' . $safe_index;
                $upload = storeUploadedFile(
                    $_FILES[$file_key],
                    'uploads/submissions',
                    $allowed_extensions,
                    $allowed_mimes,
                    $max_upload_bytes,
                    $prefix
                );
                if (empty($upload['error'])) {
                    $file_path = $upload['path'];
                }
            }
        }
    }
    
    $total_marks += $q['marks'];

    // Upsert Answer (supports autosave drafts)
    if ($existing) {
        if ($q_type === 'file' && $file_path === null) {
            $file_path = $existing['file_upload'];
        }
        $stmt_ans = $pdo->prepare("UPDATE answers SET selected_option = ?, theory_answer = ?, file_upload = ?, is_correct = ? WHERE id = ?");
        $stmt_ans->execute([$selected, $theory_ans, $file_path, $is_correct, $existing['id']]);
    } else {
        $stmt_ans = $pdo->prepare("INSERT INTO answers (attempt_id, question_id, selected_option, theory_answer, file_upload, is_correct) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ans->execute([$attempt_id, $qid, $selected, $theory_ans, $file_path, $is_correct]);
    }
}

// Update Attempt
$stmt = $pdo->prepare("UPDATE attempts SET score = ?, total_marks = ?, submit_time = NOW(), status = 'completed' WHERE id = ?");
$stmt->execute([$total_score, $total_marks, $attempt_id]);

// If this was an auto-submit, add a note
if (isset($_POST['auto_submit'])) {
    $stmt = $pdo->prepare("UPDATE attempts SET notes = CONCAT(COALESCE(notes, ''), ' Auto-submitted due to time expiration.') WHERE id = ?");
    $stmt->execute([$attempt_id]);
}

// Redirect
header("Location: student_result.php");
exit;
?>
