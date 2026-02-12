<?php
require 'db.php';
require 'auth.php';

$exam_code = isset($_GET['code']) ? $_GET['code'] : '';
$error = '';
$message = '';
$show_registration = false;

// Initialize variables
$index_number = '';
$full_name = '';
$department = '';
$program = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        $error = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $index_number = trim($_POST['index_number']);
        $exam_code = trim($_POST['exam_code']);
        $action = $_POST['action']; // 'start' or 'check_result'

        // Registration fields
        $full_name = trim($_POST['full_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $program = trim($_POST['program'] ?? '');

        if (!in_array($action, ['start', 'check_result'], true)) {
            $error = "Invalid action.";
        } elseif (empty($index_number) || empty($exam_code)) {
            $error = "Course Code and Index Number are required.";
        } else {
            // Check if student exists
            $stmt = $pdo->prepare("SELECT * FROM students WHERE index_number = ?");
            $stmt->execute([$index_number]);
            $student = $stmt->fetch();

            if (!$student) {
                // New Student: Check if registration details are submitted
                if (empty($full_name) || empty($department) || empty($program)) {
                    $show_registration = true; // Show the hidden fields
                    $message = "First time login? Please complete your details to register.";
                } else {
                    // Register New Student
                    $stmt = $pdo->prepare("INSERT INTO students (index_number, full_name, department, program) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$index_number, $full_name, $department, $program]);
                    $student = ['index_number' => $index_number]; // Successfully registered
                }
            }

            // If student exists (or just registered), proceed to exam validation
            if ($student && !$show_registration) {
                // Validate Exam Code
                $stmt = $pdo->prepare("SELECT id, title, is_published, start_datetime, end_datetime, attempts_allowed, duration FROM exams WHERE exam_code = ?");
                $stmt->execute([$exam_code]);
                $exam = $stmt->fetch();

                if ($exam && $exam['is_published']) {
                    // Check Attempts
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count, status, start_time FROM attempts WHERE exam_id = ? AND student_index = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$exam['id'], $index_number]);
                    $attempt_info = $stmt->fetch();
                    $attempt_count = $attempt_info ? $attempt_info['count'] : 0;
                    $last_status = $attempt_info ? $attempt_info['status'] : null;

                    if ($action === 'check_result') {
                        if ($attempt_count > 0 && ($last_status === 'completed')) {
                            $_SESSION['student_index'] = $index_number;
                            $_SESSION['exam_id'] = $exam['id'];
                            session_regenerate_id(true);
                            header("Location: student_result.php");
                            exit;
                        } else {
                            $error = "You have not completed this exam yet.";
                        }
                    } elseif ($action === 'start') {
                        // Check Schedule
                        $now = new DateTime();
                        $start = $exam['start_datetime'] ? new DateTime($exam['start_datetime']) : null;
                        $end = $exam['end_datetime'] ? new DateTime($exam['end_datetime']) : null;
                        
                        if ($start && $now < $start) {
                            $error = "Exam starts on " . $start->format('M d, Y h:i A');
                        } elseif ($end && $now > $end) {
                            $error = "Exam ended on " . $end->format('M d, Y h:i A');
                        } else {
                            // Check Attempts Limit
                            $active_attempt = ($last_status === 'ongoing');
                            
                            if (!$active_attempt && $attempt_count >= $exam['attempts_allowed']) {
                                $error = "You have already used all allowed attempts (" . $exam['attempts_allowed'] . "). Please check your results.";
                            } else {
                                // Start or Resume
                                $_SESSION['student_index'] = $index_number;
                                $_SESSION['exam_id'] = $exam['id'];
                                session_regenerate_id(true);
                                header("Location: take_exam.php");
                                exit;
                            }
                        }
                    }
                } else {
                    $error = "Invalid Course Code.";
                }
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
    <title>Student Portal</title>
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
        .login-card {
            max-width: 600px;
            width: 100%;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 16px 35px rgba(15, 23, 42, 0.12);
            background: var(--theme-card-bg);
            border: 1px solid var(--theme-card-border);
            position: relative;
        }
        .form-label {
            font-weight: 600;
            color: var(--theme-text);
        }
        .input-group-text {
            background: var(--theme-input-bg);
            color: var(--theme-muted);
            border-color: var(--theme-input-border);
        }
        .registration-fields {
            display: none;
            background: var(--theme-table-stripe);
            border: 1px solid var(--theme-card-border);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .show-reg { display: block !important; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        body.theme-dark .login-card {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
        }
        body.theme-dark .btn-close {
            filter: invert(1) grayscale(100%);
            opacity: 0.85;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .login-card {
                padding: 1.5rem;
                margin: 1rem;
            }
            h3 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 1rem 0.5rem;
            }
            .login-card {
                padding: 1.25rem;
                border-radius: 12px;
            }
            h3 {
                font-size: 1.25rem;
            }
            .input-group-text {
                font-size: 0.75rem;
            }
            .btn-lg {
                padding: 0.75rem 1rem;
                font-size: 1rem;
            }
        }
        
        /* Smart watch / Very small screens */
        @media (max-width: 320px) {
            .login-card {
                padding: 1rem;
            }
            h3 {
                font-size: 1.125rem;
            }
            .form-control-lg {
                font-size: 1rem;
                padding: 0.5rem 0.75rem;
            }
            .btn-lg {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <button type="button" class="btn-close position-absolute top-0 end-0 m-3" aria-label="Close" onclick="closeApp()"></button>
        <h3 class="text-center mb-4">Student Exam Portal</h3>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="mb-4">
                 <label class="form-label">Course Code</label>
                 <div class="input-group input-group-lg">
                    <input type="text" name="exam_code" class="form-control" value="<?= htmlspecialchars($exam_code) ?>" placeholder="e.g. CS101" required>
                    <span class="input-group-text text-muted fs-6 bg-light border-start-0">Provided by your lecturer</span>
                 </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Index Number</label>
                <input type="text" name="index_number" class="form-control form-control-lg" value="<?= htmlspecialchars($index_number) ?>" placeholder="Your Unique ID" required>
            </div>
            
            <!-- Registration Section: Hidden by default, shown if needed -->
            <div class="registration-fields <?= $show_registration ? 'show-reg' : '' ?>">
                <h5 class="mb-3 text-primary">New Student Registration</h5>
                <p class="small text-muted">We don't recognize this Index Number. Please fill in your details to continue.</p>
                
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($full_name) ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($department) ?>" placeholder="e.g. Computer Science">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Program</label>
                        <input type="text" name="program" class="form-control" value="<?= htmlspecialchars($program) ?>" placeholder="e.g. BSc IT">
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                <button type="submit" name="action" value="start" class="btn btn-success btn-lg px-5">Take Exam</button>
                <button type="submit" name="action" value="check_result" class="btn btn-outline-primary btn-lg px-5">Check Result</button>
            </div>
            
            <div class="text-center mt-3 text-muted small">
                Current Server Time: <?= date('M d, Y h:i A') ?>
            </div>
        </form>
    </div>
    <script>
        function closeApp() {
            if(confirm('Are you sure you want to exit the Exam System?')) {
                window.close();
                // Fallback message if window.close() is blocked
                setTimeout(function() {
                    if (!window.closed) {
                        alert("Browser security prevented automatic closing. Please close this tab manually.");
                    }
                }, 100);
            }
        }
    </script>
    <script defer src="theme.js"></script>
</body>
</html>

