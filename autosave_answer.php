<?php
require 'db.php';
require 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

checkCSRF();

if (!isset($_SESSION['student_index']) || !isset($_SESSION['exam_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.']);
    exit;
}

$student_index = $_SESSION['student_index'];
$exam_id = $_SESSION['exam_id'];

// Get ongoing attempt
$stmt = $pdo->prepare("SELECT id FROM attempts WHERE exam_id = ? AND student_index = ? AND status = 'ongoing' ORDER BY id DESC LIMIT 1");
$stmt->execute([$exam_id, $student_index]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'No active attempt found.']);
    exit;
}

$question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
if ($question_id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid question.']);
    exit;
}

// Validate question belongs to this exam
$stmt = $pdo->prepare("SELECT id, q_type, correct_option FROM questions WHERE id = ? AND exam_id = ?");
$stmt->execute([$question_id, $exam_id]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Question not found.']);
    exit;
}

$answer_type = $question['q_type'];
$selected_option = null;
$theory_answer = null;
$file_path = null;
$is_correct = null;

if ($answer_type === 'mcq') {
    $selected_option = isset($_POST['selected_option']) ? strtoupper(trim($_POST['selected_option'])) : null;
    $valid_options = ['A', 'B', 'C', 'D', 'E'];
    if ($selected_option !== null && $selected_option !== '' && !in_array($selected_option, $valid_options, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid option selected.']);
        exit;
    }
    if ($selected_option === '') {
        $selected_option = null;
    }
    if ($selected_option !== null) {
        $is_correct = ($selected_option === $question['correct_option']) ? 1 : 0;
    }
} elseif ($answer_type === 'theory') {
    $theory_answer = isset($_POST['theory_answer']) ? trim($_POST['theory_answer']) : null;
    if ($theory_answer === '') {
        $theory_answer = null;
    }
} elseif ($answer_type === 'file') {
    if (isset($_FILES['file_answer']) && $_FILES['file_answer']['error'] !== UPLOAD_ERR_NO_FILE) {
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
        $prefix = 'autosave_' . $question_id . '_' . $safe_index;

        $upload = storeUploadedFile(
            $_FILES['file_answer'],
            'uploads/submissions',
            $allowed_extensions,
            $allowed_mimes,
            $max_upload_bytes,
            $prefix
        );

        if (!empty($upload['error'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $upload['error']]);
            exit;
        }

        $file_path = $upload['path'];
    } else {
        // No new file; keep existing file if present
        $file_path = null;
    }
} else {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unsupported question type.']);
    exit;
}

// Upsert answer
$stmt = $pdo->prepare("SELECT id, file_upload FROM answers WHERE attempt_id = ? AND question_id = ? LIMIT 1");
$stmt->execute([$attempt['id'], $question_id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    if ($answer_type === 'file' && $file_path === null) {
        $file_path = $existing['file_upload'];
    }

    $stmt = $pdo->prepare("UPDATE answers SET selected_option = ?, theory_answer = ?, file_upload = ?, is_correct = ? WHERE id = ?");
    $stmt->execute([$selected_option, $theory_answer, $file_path, $is_correct, $existing['id']]);
} else {
    $stmt = $pdo->prepare("INSERT INTO answers (attempt_id, question_id, selected_option, theory_answer, file_upload, is_correct) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$attempt['id'], $question_id, $selected_option, $theory_answer, $file_path, $is_correct]);
}

echo json_encode(['success' => true, 'saved_at' => date('c')]);
?>
