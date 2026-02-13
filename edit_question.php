<?php
require 'db.php';
require 'auth.php';

requireLogin();

if (!isset($_GET['id'])) {
    die("Question ID required.");
}

$question_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch question with exam ownership verification
$stmt = $pdo->prepare("
    SELECT q.*, e.id as exam_id, e.title as exam_title, e.user_id, e.is_published 
    FROM questions q 
    JOIN exams e ON q.exam_id = e.id 
    WHERE q.id = ? AND e.user_id = ?
");
$stmt->execute([$question_id, $user_id]);
$question = $stmt->fetch();

if (!$question) {
    die("Question not found or access denied.");
}

if ($question['is_published']) {
    die("Cannot edit questions in a published exam.");
}

$exam_id = $question['exam_id'];
$success = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    checkCSRF();
    
    $q_type = sanitizeInput($_POST['q_type']);
    $question_text_raw = trim($_POST['question_text'] ?? '');
    $question_text = sanitizeInput($question_text_raw);
    $option_a = isset($_POST['option_a']) ? sanitizeInput($_POST['option_a']) : null;
    $option_b = isset($_POST['option_b']) ? sanitizeInput($_POST['option_b']) : null;
    $option_c = isset($_POST['option_c']) ? sanitizeInput($_POST['option_c']) : null;
    $option_d = isset($_POST['option_d']) ? sanitizeInput($_POST['option_d']) : null;
    $option_e = isset($_POST['option_e']) ? sanitizeInput($_POST['option_e']) : null;
    $correct_option = ($q_type === 'mcq') ? sanitizeInput($_POST['correct_option']) : null;
    $marks = (int)$_POST['marks'];
    
    // Validate question type
    $valid_types = ['mcq', 'theory', 'file'];
    if (!in_array($q_type, $valid_types)) {
        $error = "Invalid question type.";
    } elseif ($marks < 1) {
        $error = "Marks must be at least 1.";
    } elseif ($q_type === 'mcq' && (empty($option_a) || empty($option_b) || empty($correct_option))) {
        $error = "Please fill in all required fields for MCQ.";
    } elseif ($q_type === 'mcq' && !in_array($correct_option, ['A', 'B', 'C', 'D', 'E'])) {
        $error = "Invalid correct option.";
    } elseif ($q_type !== 'file' && empty($question_text)) {
        $error = "Question text is required.";
    } else {
        if ($q_type === 'file') {
            $option_a = null;
            $option_b = null;
            $option_c = null;
            $option_d = null;
            $correct_option = null;
            $existing_file = $question['option_e'] ?? null;

            if (isset($_FILES['question_file']) && $_FILES['question_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                $allowed_mimes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg',
                    'image/png',
                ];
                $upload = storeUploadedFile(
                    $_FILES['question_file'],
                    'uploads/questions',
                    $allowed_extensions,
                    $allowed_mimes,
                    10 * 1024 * 1024,
                    'question'
                );
                if (!empty($upload['error'])) {
                    $error = $upload['error'];
                } else {
                    $option_e = $upload['path'];
                    if ($question_text === '') {
                        $base = pathinfo($_FILES['question_file']['name'], PATHINFO_FILENAME);
                        $question_text = sanitizeInput($base);
                    }
                }
            } else {
                $option_e = $existing_file;
            }

            if (empty($option_e)) {
                $error = "Please upload a file for this question.";
            }

            if ($question_text === '') {
                $question_text = $question['question_text'] ?? 'File Upload Question';
            }
        }

        if (empty($error)) {
        $stmt = $pdo->prepare("
            UPDATE questions 
            SET q_type = ?, question_text = ?, option_a = ?, option_b = ?, option_c = ?, 
                option_d = ?, option_e = ?, correct_option = ?, marks = ? 
            WHERE id = ?
        ");
            if ($stmt->execute([$q_type, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $correct_option, $marks, $question_id])) {
                $success = "Question updated successfully.";
                // Refresh question data
                $stmt = $pdo->prepare("
                    SELECT q.*, e.id as exam_id, e.title as exam_title, e.user_id, e.is_published 
                    FROM questions q 
                    JOIN exams e ON q.exam_id = e.id 
                    WHERE q.id = ? AND e.user_id = ?
                ");
                $stmt->execute([$question_id, $user_id]);
                $question = $stmt->fetch();
            } else {
                $error = "Failed to update question.";
            }
        }
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    checkCSRF();
    
    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
    if ($stmt->execute([$question_id])) {
        header("Location: view_exam.php?id=$exam_id&deleted=1");
        exit;
    } else {
        $error = "Failed to delete question.";
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Question - <?= htmlspecialchars($question['exam_title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        .navbar-modern {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            padding: 0.75rem 0;
        }
        body.theme-dark .navbar-modern {
            background: linear-gradient(135deg, #020617 0%, #1e1b4b 100%);
        }
        .navbar-modern .navbar-brand {
            color: white !important;
            font-weight: 600;
        }
        .navbar-modern .btn-outline-light {
            color: #fff;
            border-color: rgba(255,255,255,0.5);
        }
        .navbar-modern .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            border-color: #fff;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-modern mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Exam System</a>
            <form method="POST" action="logout.php" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
            </form>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="view_exam.php?id=<?= $exam_id ?>"><?= htmlspecialchars($question['exam_title']) ?></a></li>
                        <li class="breadcrumb-item active">Edit Question</li>
                    </ol>
                </nav>

                <div class="card">
                    <div class="card-header">
                        Edit Question
                        <a href="view_exam.php?id=<?= $exam_id ?>" class="btn btn-sm btn-secondary float-end">Back to Exam</a>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label>Question Type *</label>
                                    <select name="q_type" id="q_type" class="form-select" onchange="toggleQuestionType()" required>
                                        <option value="mcq" <?= $question['q_type'] === 'mcq' ? 'selected' : '' ?>>Multiple Choice (MCQ)</option>
                                        <option value="theory" <?= $question['q_type'] === 'theory' ? 'selected' : '' ?>>Theory/Short Answer</option>
                                        <option value="file" <?= $question['q_type'] === 'file' ? 'selected' : '' ?>>File Upload</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label>Marks *</label>
                                    <input type="number" name="marks" class="form-control" value="<?= $question['marks'] ?>" min="1" required>
                                </div>
                            </div>

                            <div class="mb-3" id="question-text-wrap">
                                <label>Question Text *</label>
                                <textarea name="question_text" id="question_text" class="form-control" rows="3" required><?= htmlspecialchars($question['question_text']) ?></textarea>
                            </div>
                            
                            <div class="mb-3" id="file-prompt" style="display:none;">
                                <label>Upload Question File</label>
                                <input type="file" name="question_file" id="question_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-has-file="<?= !empty($question['option_e']) ? '1' : '0' ?>">
                                <?php if (!empty($question['option_e'])): ?>
                                    <small class="text-muted">Current file: <a href="<?= htmlspecialchars($question['option_e']) ?>" target="_blank">Download</a></small>
                                <?php else: ?>
                                    <small class="text-muted">Upload the question prompt as a file (PDF, DOC, or image).</small>
                                <?php endif; ?>
                            </div>

                            <div id="mcq-options">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Option A *</label>
                                        <input type="text" name="option_a" id="option_a" class="form-control" value="<?= htmlspecialchars($question['option_a'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Option B *</label>
                                        <input type="text" name="option_b" id="option_b" class="form-control" value="<?= htmlspecialchars($question['option_b'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Option C</label>
                                        <input type="text" name="option_c" id="option_c" class="form-control" value="<?= htmlspecialchars($question['option_c'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Option D</label>
                                        <input type="text" name="option_d" id="option_d" class="form-control" value="<?= htmlspecialchars($question['option_d'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Option E (Optional)</label>
                                        <input type="text" name="option_e" id="option_e" class="form-control" value="<?= htmlspecialchars($question['option_e'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Correct Option *</label>
                                        <select name="correct_option" id="correct_option" class="form-select">
                                            <option value="A" <?= $question['correct_option'] === 'A' ? 'selected' : '' ?>>A</option>
                                            <option value="B" <?= $question['correct_option'] === 'B' ? 'selected' : '' ?>>B</option>
                                            <option value="C" <?= $question['correct_option'] === 'C' ? 'selected' : '' ?>>C</option>
                                            <option value="D" <?= $question['correct_option'] === 'D' ? 'selected' : '' ?>>D</option>
                                            <option value="E" <?= $question['correct_option'] === 'E' ? 'selected' : '' ?>>E</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="update" class="btn btn-primary">Update Question</button>
                                <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to DELETE this question? This action cannot be undone!');">Delete Question</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleQuestionType() {
            const type = document.getElementById('q_type').value;
            const mcqOptions = document.getElementById('mcq-options');
            const mcqInputs = mcqOptions.querySelectorAll('input, select');
            const questionTextWrap = document.getElementById('question-text-wrap');
            const questionText = document.getElementById('question_text');
            const filePrompt = document.getElementById('file-prompt');
            const fileInput = document.getElementById('question_file');

            if (type === 'mcq') {
                mcqOptions.style.display = 'block';
                document.getElementById('option_a').required = true;
                document.getElementById('option_b').required = true;
                document.getElementById('correct_option').required = true;
                questionTextWrap.style.display = 'block';
                questionText.required = true;
                filePrompt.style.display = 'none';
                fileInput.required = false;
            } else {
                mcqOptions.style.display = 'none';
                mcqInputs.forEach(input => input.required = false);
                if (type === 'file') {
                    questionTextWrap.style.display = 'none';
                    questionText.required = false;
                    filePrompt.style.display = 'block';
                    fileInput.required = (fileInput.getAttribute('data-has-file') !== '1');
                } else {
                    questionTextWrap.style.display = 'block';
                    questionText.required = true;
                    filePrompt.style.display = 'none';
                    fileInput.required = false;
                }
            }
        }
        // Initialize
        toggleQuestionType();
    </script>
    <script defer src="theme.js"></script>
    <script src="js/offline-db.js"></script>
    <script src="js/sync-manager.js"></script>
    <script>
        // Initialize offline form handling for edit question
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="POST"]');
            const examId = <?= json_encode($exam_id) ?>;
            const questionId = <?= json_encode($question_id) ?>;
            
            form.addEventListener('submit', async function(e) {
                const submitter = e.submitter;
                const isDelete = submitter && submitter.name === 'delete';
                
                // Check if we have file upload - if so, can't do offline
                const fileInput = form.querySelector('input[type="file"]');
                const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
                
                if (hasFile) {
                    // File upload requires online - let normal form submission proceed
                    return;
                }
                
                // Prevent default submission
                e.preventDefault();
                
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
                
                try {
                    // Get form data
                    const formData = new FormData(form);
                    const data = {};
                    formData.forEach((value, key) => {
                        data[key] = value;
                    });
                    
                    let operationType = isDelete ? 'delete_question' : 'update_question';
                    
                    // Queue the operation
                    await offlineDB.addToSyncQueue(operationType, 'questions', data, questionId);
                    
                    // Update UI
                    const pendingCount = await offlineDB.getPendingCount();
                    if (typeof syncManager !== 'undefined') {
                        syncManager.updatePendingCount(pendingCount);
                        
                        const message = isDelete ? 'Question deleted locally.' : 'Question updated locally.';
                        
                        if (syncManager.isOnline) {
                            syncManager.showNotification(message + ' Syncing...', 'success');
                            await syncManager.sync();
                            setTimeout(() => {
                                window.location.href = 'view_exam.php?id=' + examId + '&synced=1';
                            }, 1000);
                        } else {
                            syncManager.showNotification(message + ' Will sync when online.', 'warning');
                            setTimeout(() => {
                                window.location.href = 'view_exam.php?id=' + examId + '&offline=1';
                            }, 1500);
                        }
                    } else {
                        alert('Changes saved locally. Will sync when online.');
                        window.location.href = 'view_exam.php?id=' + examId + '&offline=1';
                    }
                } catch (error) {
                    console.error('Error:', error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = isDelete ? 'Delete Question' : 'Update Question';
                    
                    if (typeof syncManager !== 'undefined') {
                        syncManager.showNotification('Failed to save. Please try again.', 'danger');
                    } else {
                        alert('Failed to save. Please try again.');
                    }
                }
            });
        });
    </script>
</body>
</html>

