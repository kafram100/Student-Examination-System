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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    
    $title = sanitizeInput($_POST['title']);
    $exam_type = sanitizeInput($_POST['exam_type']);
    $course_name = sanitizeInput($_POST['course_name']);
    $course_code = sanitizeInput($_POST['course_code']);
    $instructions = sanitizeInput($_POST['instructions']);
    $duration = (int)$_POST['duration'];
    $attempts_allowed = (int)$_POST['attempts_allowed'];
    $exam_code = sanitizeInput($_POST['exam_code']);
    $start_datetime = !empty($_POST['start_datetime']) ? str_replace('T', ' ', $_POST['start_datetime']) : null;
    $end_datetime = !empty($_POST['end_datetime']) ? str_replace('T', ' ', $_POST['end_datetime']) : null;

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
    if (!$error && (empty($title) || empty($course_name) || ($is_exam_type && $duration < 0) || empty($exam_code))) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check uniqueness of code if changed
        if ($exam_code !== $exam['exam_code']) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE exam_code = ? AND id != ?");
            $stmt->execute([$exam_code, $exam_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'The Access Code "' . htmlspecialchars($exam_code) . '" is already in use by another assessment. Students need unique codes to login.';
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("UPDATE exams SET title = ?, exam_type = ?, course_name = ?, course_code = ?, instructions = ?, duration = ?, attempts_allowed = ?, exam_code = ?, start_datetime = ?, end_datetime = ? WHERE id = ?");
            if ($stmt->execute([$title, $exam_type, $course_name, $course_code, $instructions, $duration, $attempts_allowed, $exam_code, $start_datetime, $end_datetime, $exam_id])) {
                header("Location: view_exam.php?id=$exam_id");
                exit;
            } else {
                $error = 'Failed to update assessment.';
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
    <title>Edit Assessment - <?= htmlspecialchars($exam['title']) ?></title>
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
                    <div class="card-header">Edit Assessment</div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                     <label>Assessment Type *</label>
                                     <select name="exam_type" id="exam_type" class="form-select" onchange="toggleAssessmentFields()" required>
                                         <option value="Exam" <?= $exam['exam_type'] === 'Exam' ? 'selected' : '' ?>>Exam</option>
                                         <option value="Mid-semester" <?= $exam['exam_type'] === 'Mid-semester' ? 'selected' : '' ?>>Mid-semester</option>
                                         <option value="Quiz" <?= $exam['exam_type'] === 'Quiz' ? 'selected' : '' ?>>Quiz</option>
                                         <option value="Assignment" <?= $exam['exam_type'] === 'Assignment' ? 'selected' : '' ?>>Assignment</option>
                                     </select>
                                 </div>
                                 <div class="col-md-8">
                                     <label>Assessment Title *</label>
                                     <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($exam['title']) ?>" required>
                                 </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                     <label>Course Code</label>
                                     <input type="text" name="course_code" class="form-control" value="<?= htmlspecialchars($exam['course_code'] ?? '') ?>" placeholder="e.g. COMP101">
                                 </div>
                                 <div class="col-md-8">
                                     <label>Course Name *</label>
                                     <input type="text" name="course_name" class="form-control" value="<?= htmlspecialchars($exam['course_name']) ?>" required>
                                 </div>
                            </div>
                            <div class="mb-3">
                                <label>Student Access Code *</label>
                                <input type="text" name="exam_code" class="form-control" value="<?= htmlspecialchars($exam['exam_code']) ?>" required>
                                <small class="text-muted">Unique code students use to login. Must be unique for EACH assessment.</small>
                            </div>
                            <div class="mb-3">
                                <label>Instructions</label>
                                <textarea name="instructions" class="form-control" rows="3"><?= htmlspecialchars($exam['instructions']) ?></textarea>
                            </div>
                            <div class="row" id="exam-only-fields">
                                <div class="col-md-6 mb-3">
                                    <label>Duration (Minutes) *</label>
                                    <input type="number" name="duration" id="duration" class="form-control" value="<?= $exam['duration'] ?>" min="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Attempts Allowed *</label>
                                    <input type="number" name="attempts_allowed" id="attempts_allowed" class="form-control" value="<?= $exam['attempts_allowed'] ?>" min="1">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3" id="start-date-container">
                                    <label>Start Date/Time</label>
                                    <input type="datetime-local" name="start_datetime" class="form-control" value="<?= $exam['start_datetime'] ? date('Y-m-d\TH:i', strtotime($exam['start_datetime'])) : '' ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label id="end-date-label">End Date/Time</label>
                                    <input type="datetime-local" name="end_datetime" class="form-control" value="<?= $exam['end_datetime'] ? date('Y-m-d\TH:i', strtotime($exam['end_datetime'])) : '' ?>">
                                    <small class="text-muted" id="end-date-help">Students cannot take the assessment after this time.</small>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Assessment</button>
                            <a href="view_exam.php?id=<?= $exam_id ?>" class="btn btn-secondary">Cancel</a>
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
            
            // Fields to set defaults for when hidden
            const durationInput = document.getElementById('duration');
            const attemptsInput = document.getElementById('attempts_allowed');

            if (type === 'Quiz' || type === 'Assignment') {
                examOnlyFields.style.display = 'none';
                startDateContainer.style.display = 'none';
                endDateLabel.innerText = 'Submission Deadline';
                endDateHelp.innerText = 'Students must submit by this time.';
            } else {
                examOnlyFields.style.display = 'flex';
                startDateContainer.style.display = 'block';
                endDateLabel.innerText = 'End Date/Time';
                endDateHelp.innerText = 'Students cannot take the assessment after this time.';
            }
        }
        // Initialize on load
        toggleAssessmentFields();
    </script>
    <script defer src="theme.js"></script>
    <script src="js/offline-db.js"></script>
    <script src="js/sync-manager.js"></script>
    <script>
        // Initialize offline form handling for edit exam
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="POST"]');
            const examId = <?= json_encode($exam_id) ?>;
            
            form.addEventListener('submit', async function(e) {
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
                    
                    // Queue the operation
                    await offlineDB.addToSyncQueue('update_exam', 'exams', data, examId);
                    
                    // Update UI
                    const pendingCount = await offlineDB.getPendingCount();
                    if (typeof syncManager !== 'undefined') {
                        syncManager.updatePendingCount(pendingCount);
                        
                        if (syncManager.isOnline) {
                            syncManager.showNotification('Assessment updated and syncing...', 'success');
                            await syncManager.sync();
                            setTimeout(() => {
                                window.location.href = 'view_exam.php?id=' + examId + '&synced=1';
                            }, 1000);
                        } else {
                            syncManager.showNotification('Assessment updated locally. Will sync when online.', 'warning');
                            setTimeout(() => {
                                window.location.href = 'view_exam.php?id=' + examId + '&offline=1';
                            }, 1500);
                        }
                    } else {
                        alert('Assessment updated locally. Will sync when online.');
                        window.location.href = 'view_exam.php?id=' + examId + '&offline=1';
                    }
                } catch (error) {
                    console.error('Error:', error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Update Assessment';
                    
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

