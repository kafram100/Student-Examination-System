<?php
require 'db.php';
require 'auth.php';

requireLogin();

if (!isset($_GET['exam_id'])) {
    die("Exam ID required.");
}

$exam_id = $_GET['exam_id'];
$user_id = $_SESSION['user_id'];

// Verify ownership
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Exam not found or access denied.");
}

if ($exam['is_published']) {
    die("Cannot add questions to a published exam.");
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    
    $q_type = sanitizeInput($_POST['q_type']);
    $question_text_raw = trim($_POST['question_text'] ?? '');
    $question_text = sanitizeInput($question_text_raw);
    $use_csv = ($q_type === 'mcq' && !empty($_POST['use_csv']));
    $option_a = isset($_POST['option_a']) ? sanitizeInput($_POST['option_a']) : null;
    $option_b = isset($_POST['option_b']) ? sanitizeInput($_POST['option_b']) : null;
    $option_c = isset($_POST['option_c']) ? sanitizeInput($_POST['option_c']) : null;
    $option_d = isset($_POST['option_d']) ? sanitizeInput($_POST['option_d']) : null;
    $option_e = isset($_POST['option_e']) ? sanitizeInput($_POST['option_e']) : null;
    $correct_option = null;
    
    if ($q_type === 'mcq') {
        $correct_option = sanitizeInput($_POST['correct_option']);
    } elseif ($q_type === 'fill_in' && isset($_POST['fill_in_correct_answer'])) {
        // For fill-in questions, store the correct answer in the correct_option field
        $correct_option = sanitizeInput($_POST['fill_in_correct_answer']);
    }
    
    $marks = (int)$_POST['marks'];
    
    // Validate question type
    $valid_types = ['mcq', 'theory', 'file', 'fill_in'];
    if (!in_array($q_type, $valid_types)) {
        $error = "Invalid question type.";
    } elseif ($marks < 1 && !$use_csv) {
        $error = "Marks must be at least 1.";
    } elseif ($q_type === 'mcq' && !$use_csv && (empty($option_a) || empty($option_b) || empty($correct_option))) {
        $error = "Please fill in all required fields for MCQ.";
    } elseif ($q_type === 'mcq' && !$use_csv && !in_array($correct_option, ['A', 'B', 'C', 'D', 'E'])) {
        $error = "Invalid correct option.";
    } elseif ($q_type === 'fill_in' && !$use_csv && empty($correct_option)) {
        $error = "Please enter the correct answer for the fill-in question.";
    } elseif ($q_type !== 'file' && !$use_csv && empty($question_text)) {
        $error = "Please fill in all required fields.";
    } else {
        if ($use_csv) {
            if (!isset($_FILES['mcq_csv']) || $_FILES['mcq_csv']['error'] !== UPLOAD_ERR_OK) {
                $error = "Please upload a CSV file.";
            } else {
                $ext = strtolower(pathinfo($_FILES['mcq_csv']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'csv') {
                    $error = "Invalid file type. Please upload a CSV file.";
                } else {
                    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
                    $mime = $finfo ? finfo_file($finfo, $_FILES['mcq_csv']['tmp_name']) : '';
                    if ($finfo) {
                        finfo_close($finfo);
                    }
                    $allowed_mimes = ['text/csv', 'text/plain', 'application/vnd.ms-excel'];
                    if (!empty($mime) && !in_array($mime, $allowed_mimes, true)) {
                        $error = "Invalid CSV file.";
                    } else {
                        $handle = fopen($_FILES['mcq_csv']['tmp_name'], 'r');
                        if ($handle === false) {
                            $error = "Unable to read CSV file.";
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO questions (exam_id, q_type, question_text, option_a, option_b, option_c, option_d, option_e, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $imported = 0;
                            $row = fgetcsv($handle);
                            if ($row !== false) {
                                $normalized = array_map('strtolower', $row);
                                if (!in_array('question_text', $normalized, true)) {
                                    // Process as data row
                                    $data_rows = [$row];
                                } else {
                                    $data_rows = [];
                                }
                            } else {
                                $data_rows = [];
                            }

                            while (($row = fgetcsv($handle)) !== false) {
                                $data_rows[] = $row;
                            }
                            fclose($handle);

                            foreach ($data_rows as $row) {
                                if (count($row) < 7) {
                                    continue;
                                }
                                $csv_question = sanitizeInput(trim($row[0] ?? ''));
                                $csv_a = sanitizeInput(trim($row[1] ?? ''));
                                $csv_b = sanitizeInput(trim($row[2] ?? ''));
                                $csv_c = sanitizeInput(trim($row[3] ?? ''));
                                $csv_d = sanitizeInput(trim($row[4] ?? ''));
                                $csv_e = sanitizeInput(trim($row[5] ?? ''));
                                $csv_correct = strtoupper(trim($row[6] ?? ''));
                                $csv_marks = isset($row[7]) ? (int)$row[7] : 1;

                                if ($csv_marks < 1) {
                                    $csv_marks = 1;
                                }
                                if (empty($csv_question) || empty($csv_a) || empty($csv_b)) {
                                    continue;
                                }
                                if (!in_array($csv_correct, ['A', 'B', 'C', 'D', 'E'], true)) {
                                    continue;
                                }
                                $csv_c = $csv_c !== '' ? $csv_c : null;
                                $csv_d = $csv_d !== '' ? $csv_d : null;
                                $csv_e = $csv_e !== '' ? $csv_e : null;

                                $stmt->execute([$exam_id, 'mcq', $csv_question, $csv_a, $csv_b, $csv_c, $csv_d, $csv_e, $csv_correct, $csv_marks]);
                                $imported++;
                            }

                            if ($imported > 0) {
                                $success = "Imported $imported MCQ questions from CSV.";
                            } else {
                                $error = "No valid MCQ questions found in the CSV file.";
                            }
                        }
                    }
                }
            }
        }

        if ($q_type === 'file') {
            $option_a = null;
            $option_b = null;
            $option_c = null;
            $option_d = null;
            $correct_option = null;
            $option_e = null;

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
                    if ($question_text === '') {
                        $question_text = 'File Upload Question';
                    }
                }
            } else {
                $error = "Please upload a file for this question.";
            }
        }

        if (empty($error) && !$use_csv) {
            $stmt = $pdo->prepare("INSERT INTO questions (exam_id, q_type, question_text, option_a, option_b, option_c, option_d, option_e, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$exam_id, $q_type, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $correct_option, $marks])) {
                $success = "Question added successfully.";
            } else {
                $error = "Failed to add question.";
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Question - <?= htmlspecialchars($exam['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Add Question to: <strong><?= htmlspecialchars($exam['title']) ?></strong>
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
                                        <option value="mcq">Multiple Choice (MCQ)</option>
                                        <option value="theory">Theory/Short Answer</option>
                                        <option value="fill_in">Fill-in-the-Blank</option>
                                        <option value="file">File Upload (Student uploads a file)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label>Marks *</label>
                                    <input type="number" name="marks" id="marks" class="form-control" value="1" min="1" required>
                                </div>
                            </div>

                            <div class="mb-3" id="question-text-wrap">
                                <label>Question Text *</label>
                                <textarea name="question_text" id="question_text" class="form-control" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3" id="file-prompt" style="display:none;">
                                <label>Upload Question File *</label>
                                <input type="file" name="question_file" id="question_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <small class="text-muted">Upload the question prompt as a file (PDF, DOC, or image).</small>
                            </div>

                            <div class="mb-3" id="mcq-csv" style="display:none;">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="use_csv" name="use_csv" value="1" onchange="toggleCsvMode()">
                                    <label class="form-check-label" for="use_csv">Import MCQs from CSV instead of manual entry</label>
                                </div>
                                <input type="file" name="mcq_csv" id="mcq_csv" class="form-control" accept=".csv">
                                <small class="text-muted">CSV columns: question_text, option_a, option_b, option_c, option_d, option_e, correct_option, marks</small>
                            </div>

                            <div id="mcq-options">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Option A *</label>
                                        <input type="text" name="option_a" id="option_a" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Option B *</label>
                                        <input type="text" name="option_b" id="option_b" class="form-control">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Option C</label>
                                        <input type="text" name="option_c" id="option_c" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Option D</label>
                                        <input type="text" name="option_d" id="option_d" class="form-control">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Option E (Optional)</label>
                                        <input type="text" name="option_e" id="option_e" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Correct Option *</label>
                                        <select name="correct_option" id="correct_option" class="form-select">
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="D">D</option>
                                            <option value="E">E</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fill-in-the-Blank Correct Answer Section -->
                            <div id="fill-in-correct-answer" style="display:none;">
                                <div class="mb-3">
                                    <label>Correct Answer (Hidden from Students) *</label>
                                    <input type="text" name="fill_in_correct_answer" id="fill_in_correct_answer" class="form-control" placeholder="Enter the correct answer">
                                    <small class="text-muted">This answer will be used to grade student responses but won't be visible to students during the exam.</small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Save Question</button>
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
            const csvWrap = document.getElementById('mcq-csv');
            const marksInput = document.getElementById('marks');
            const fillInAnswerSection = document.getElementById('fill-in-correct-answer');
            const fillInAnswerInput = document.getElementById('fill_in_correct_answer');

            if (type === 'mcq') {
                mcqOptions.style.display = 'block';
                document.getElementById('option_a').required = true;
                document.getElementById('option_b').required = true;
                document.getElementById('correct_option').required = true;
                questionTextWrap.style.display = 'block';
                questionText.required = true;
                filePrompt.style.display = 'none';
                fileInput.required = false;
                csvWrap.style.display = 'block';
                marksInput.required = true;
                fillInAnswerSection.style.display = 'none';
                fillInAnswerInput.required = false;
            } else if (type === 'fill_in') {
                mcqOptions.style.display = 'none';
                mcqInputs.forEach(input => input.required = false);
                csvWrap.style.display = 'none';
                questionTextWrap.style.display = 'block';
                questionText.required = true;
                filePrompt.style.display = 'none';
                fileInput.required = false;
                marksInput.required = true;
                fillInAnswerSection.style.display = 'block';
                fillInAnswerInput.required = true;
            } else {
                mcqOptions.style.display = 'none';
                mcqInputs.forEach(input => input.required = false);
                csvWrap.style.display = 'none';
                if (type === 'file') {
                    questionTextWrap.style.display = 'none';
                    questionText.required = false;
                    filePrompt.style.display = 'block';
                    fileInput.required = true;
                    marksInput.required = true;
                    fillInAnswerSection.style.display = 'none';
                    fillInAnswerInput.required = false;
                } else {
                    questionTextWrap.style.display = 'block';
                    questionText.required = true;
                    filePrompt.style.display = 'none';
                    fileInput.required = false;
                    marksInput.required = true;
                    fillInAnswerSection.style.display = 'none';
                    fillInAnswerInput.required = false;
                }
            }
        }
        function toggleCsvMode() {
            const useCsv = document.getElementById('use_csv');
            const mcqOptions = document.getElementById('mcq-options');
            const questionTextWrap = document.getElementById('question-text-wrap');
            const questionText = document.getElementById('question_text');
            const csvInput = document.getElementById('mcq_csv');
            const marksInput = document.getElementById('marks');

            if (useCsv.checked) {
                mcqOptions.style.display = 'none';
                questionTextWrap.style.display = 'none';
                questionText.required = false;
                csvInput.required = true;
                marksInput.required = false;
            } else {
                mcqOptions.style.display = 'block';
                questionTextWrap.style.display = 'block';
                questionText.required = true;
                csvInput.required = false;
                marksInput.required = true;
            }
        }
        // Initialize
        toggleQuestionType();
        toggleCsvMode();
    </script>
    <script defer src="theme.js"></script>
    <script src="js/offline-db.js"></script>
    <script src="js/sync-manager.js"></script>
    <script>
        // Initialize offline form handling for add question
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="POST"]');
            const examId = <?= json_encode($exam_id) ?>;
            
            form.addEventListener('submit', async function(e) {
                // Check if we have file upload - if so, can't do offline
                const fileInput = form.querySelector('input[type="file"]');
                const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
                const useCsv = document.getElementById('use_csv')?.checked;
                
                if (hasFile || useCsv) {
                    // File upload or CSV requires online - let normal form submission proceed
                    return;
                }
                
                // Validate MCQ fields
                const qType = document.getElementById('q_type').value;
                if (qType === 'mcq') {
                    const optionA = document.getElementById('option_a').value.trim();
                    const optionB = document.getElementById('option_b').value.trim();
                    const correctOption = document.getElementById('correct_option').value;
                    
                    if (!optionA || !optionB || !correctOption) {
                        alert('Please fill in at least Option A, Option B, and select the correct option.');
                        e.preventDefault();
                        return;
                    }
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
                    data.exam_id = examId;
                    
                    // Queue the operation
                    await offlineDB.addToSyncQueue('add_question', 'questions', data);
                    
                    // Update UI
                    const pendingCount = await offlineDB.getPendingCount();
                    if (typeof syncManager !== 'undefined') {
                        syncManager.updatePendingCount(pendingCount);
                        
                        if (syncManager.isOnline) {
                            syncManager.showNotification('Question saved and syncing...', 'success');
                            await syncManager.sync();
                            setTimeout(() => {
                                window.location.href = 'view_exam.php?id=' + examId + '&synced=1';
                            }, 1000);
                        } else {
                            syncManager.showNotification('Question saved locally. Will sync when online.', 'warning');
                            setTimeout(() => {
                                window.location.href = 'view_exam.php?id=' + examId + '&offline=1';
                            }, 1500);
                        }
                    } else {
                        alert('Question saved locally. Will sync when online.');
                        window.location.href = 'view_exam.php?id=' + examId + '&offline=1';
                    }
                } catch (error) {
                    console.error('Error:', error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Save Question';
                    
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

