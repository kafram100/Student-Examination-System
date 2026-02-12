<?php
require 'db.php';
require 'auth.php';

requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    checkCSRF();
    
    $title = sanitizeInput($_POST['title']);
    $exam_type = sanitizeInput($_POST['exam_type']);
    $course_name = sanitizeInput($_POST['course_name']);
    $course_code = sanitizeInput($_POST['course_code']);
    $instructions = sanitizeInput($_POST['instructions']);
    $duration = (int)$_POST['duration'];
    $attempts_allowed = (int)$_POST['attempts_allowed'];
    $start_datetime = !empty($_POST['start_datetime']) ? str_replace('T', ' ', $_POST['start_datetime']) : null;
    $end_datetime = !empty($_POST['end_datetime']) ? str_replace('T', ' ', $_POST['end_datetime']) : null;
    
    // Handle File Upload
    $assessment_file = null;
    if (isset($_FILES['assessment_file']) && $_FILES['assessment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $allowed_mimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        ];
        $upload = storeUploadedFile(
            $_FILES['assessment_file'],
            'uploads/assessments',
            $allowed_extensions,
            $allowed_mimes,
            10 * 1024 * 1024,
            'assessment'
        );
        if (!empty($upload['error'])) {
            $error = $upload['error'];
        } else {
            $assessment_file = $upload['path'];
        }
    }

    // Generate a unique access code if not provided
    $exam_code = trim($_POST['exam_code']);
    if (empty($exam_code)) {
        $exam_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    } else {
        // Check if code exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE exam_code = ?");
        $stmt->execute([$exam_code]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'The Access Code "' . htmlspecialchars($exam_code) . '" is already in use by another assessment. Students need unique codes to login. Please choose a different Access Code or leave it empty to auto-generate.';
        }
    }

    // Validate exam type
    $valid_types = ['Exam', 'Mid-semester', 'Quiz', 'Assignment'];
    if (!in_array($exam_type, $valid_types)) {
        $error = 'Invalid assessment type.';
    }
    
    // Validate datetime format
    if ($start_datetime && !strtotime($start_datetime)) {
        $error = 'Invalid start date/time.';
    }
    if ($end_datetime && !strtotime($end_datetime)) {
        $error = 'Invalid end date/time.';
    }
    if ($start_datetime && $end_datetime && strtotime($start_datetime) >= strtotime($end_datetime)) {
        $error = 'End date/time must be after start date/time.';
    }
    
    $is_exam_type = ($exam_type === 'Exam' || $exam_type === 'Mid-semester');
    if (!$error && (empty($title) || empty($course_name) || ($is_exam_type && $duration < 0))) {
        $error = 'Please fill in all required fields.';
    } elseif (!$error) {
        $stmt = $pdo->prepare("INSERT INTO exams (user_id, title, exam_type, course_name, course_code, instructions, assessment_file, duration, attempts_allowed, exam_code, start_datetime, end_datetime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], $title, $exam_type, $course_name, $course_code, $instructions, $assessment_file, $duration, $attempts_allowed, $exam_code, $start_datetime, $end_datetime])) {
            $exam_id = $pdo->lastInsertId();
            header("Location: view_exam.php?id=$exam_id");
            exit;
        } else {
            $error = 'Failed to create assessment.';
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Assessment - Student Exam System</title>
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
            <div class="d-flex">
                 <form method="POST" action="logout.php" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                 </form>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Create New Assessment</div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label>Assessment Type *</label>
                                    <select name="exam_type" id="exam_type" class="form-select" onchange="toggleAssessmentFields()" required>
                                        <option value="Exam">Exam</option>
                                        <option value="Mid-semester">Mid-semester</option>
                                        <option value="Quiz">Quiz</option>
                                        <option value="Assignment">Assignment</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label>Assessment Title *</label>
                                    <input type="text" name="title" class="form-control" placeholder="e.g. End of Term" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label>Course Code</label>
                                    <input type="text" name="course_code" class="form-control" placeholder="e.g. COMP101">
                                </div>
                                <div class="col-md-8">
                                    <label>Course Name *</label>
                                    <input type="text" name="course_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3" id="file-upload-section" style="display: none;">
                                <label>Upload Assessment File (PDF/Doc/Image)</label>
                                <input type="file" name="assessment_file" class="form-control">
                                <small class="text-muted">Optional: Upload a file (e.g. assignment handout) for students to download.</small>
                            </div>

                            <div class="mb-3">
                                <label>Student Access Code (Optional)</label>
                                <input type="text" name="exam_code" class="form-control" placeholder="Leave empty to auto-generate">
                                <small class="text-muted">Unique code students use to login. Must be unique for EACH assessment.</small>
                            </div>
                            <div class="mb-3">
                                <label>Instructions</label>
                                <textarea name="instructions" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="row" id="exam-only-fields">
                                <div class="col-md-6 mb-3">
                                    <label>Duration (Minutes) *</label>
                                    <input type="number" name="duration" id="duration" class="form-control" value="60" min="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Attempts Allowed *</label>
                                    <input type="number" name="attempts_allowed" id="attempts_allowed" class="form-control" value="1" min="1">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3" id="start-date-container">
                                    <label>Start Date/Time (Optional)</label>
                                    <input type="datetime-local" name="start_datetime" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label id="end-date-label">End Date/Time (Optional)</label>
                                    <input type="datetime-local" name="end_datetime" class="form-control">
                                    <small class="text-muted" id="end-date-help">Students cannot take the assessment after this time.</small>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Assessment</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAssessmentFields() {
            const type = document.getElementById('exam_type').value;
            const examOnlyFields = document.getElementById('exam-only-fields');
            const startDateContainer = document.getElementById('start-date-container');
            const endDateLabel = document.getElementById('end-date-label');
            const endDateHelp = document.getElementById('end-date-help');
            const fileUploadSection = document.getElementById('file-upload-section');
            
            // Fields to set defaults for when hidden
            const durationInput = document.getElementById('duration');
            const attemptsInput = document.getElementById('attempts_allowed');

            if (type === 'Quiz' || type === 'Assignment') {
                examOnlyFields.style.display = 'none';
                startDateContainer.style.display = 'none';
                fileUploadSection.style.display = 'block';
                endDateLabel.innerText = 'Submission Deadline *';
                endDateHelp.innerText = 'Students must submit by this time.';
                
                // Set flexible defaults for hidden fields
                durationInput.value = '0'; // 0 means unlimited in our updated logic
                attemptsInput.value = '999'; // High number for "no restriction"
            } else {
                examOnlyFields.style.display = 'flex';
                startDateContainer.style.display = 'block';
                fileUploadSection.style.display = 'none';
                endDateLabel.innerText = 'End Date/Time (Optional)';
                endDateHelp.innerText = 'Students cannot take the assessment after this time.';
                
                if(durationInput.value == '0') durationInput.value = '60';
                if(attemptsInput.value == '999') attemptsInput.value = '1';
            }
        }
        // Initialize on load
        toggleAssessmentFields();
    </script>
    <script defer src="theme.js"></script>
</body>
</html>

