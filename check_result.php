<?php
require 'db.php';
require 'auth.php';

// Clear any existing student session data
unset($_SESSION['student_index']);
unset($_SESSION['student_fullname']);

$exam_code = $_GET['exam_code'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    $exam_code = $_POST['exam_code'];
    $student_index = trim($_POST['index_number']);
    
    // Validation
    if (empty($student_index) || empty($exam_code)) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^[A-Za-z0-9\s\-\_]+$/', $student_index)) {
        $error = "Index number can only contain letters, numbers, spaces, hyphens, and underscores.";
    } else {
        // Check if exam exists and is published
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE exam_code = ? AND is_published = 1");
        $stmt->execute([$exam_code]);
        $exam = $stmt->fetch();
        
        if (!$exam) {
            $error = "Invalid exam code.";
        } else {
            // Check if attempt exists for this index
            $stmt = $pdo->prepare("SELECT * FROM attempts WHERE exam_id = ? AND student_index = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$exam['id'], $student_index]);
            $attempt = $stmt->fetch();
            
            if (!$attempt || $attempt['status'] != 'completed') {
                $error = "No completed exam found for this index number.";
            } else {
                // Store student info in session
                $_SESSION['student_fullname'] = $attempt['student_fullname'] ?? 'Student';
                $_SESSION['student_index'] = $student_index;
                $_SESSION['exam_id'] = $exam['id'];
                
                // Redirect to results
                header("Location: student_result.php?view=results");
                exit;
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
    <title>Check Results - Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="theme.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            padding: 1rem;
        }
        
        .login-container {
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.6s ease;
        }
        
        .login-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
        }
        
        .login-icon {
            width: 72px;
            height: 72px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            backdrop-filter: blur(10px);
        }
        
        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            opacity: 0.9;
            font-size: 0.9375rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.9375rem;
            transition: all 0.2s;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.125rem;
        }
        
        .form-input:focus ~ .input-icon {
            color: #3b82f6;
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 0.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-outline {
            width: 100%;
            padding: 1rem;
            background: transparent;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            color: #4b5563;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-outline:hover {
            border-color: #d1d5db;
            background: #f9fafb;
            color: #1f2937;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 576px) {
            body { padding: 0.5rem; }
            .login-container { max-width: 100%; }
            .login-card { border-radius: 20px; }
            .login-header { padding: 2.5rem 1.5rem; }
            .login-body { padding: 2rem 1.5rem; }
            .login-icon { width: 72px; height: 72px; font-size: 1.75rem; }
            .login-title { font-size: 1.75rem; }
            .login-subtitle { font-size: 1rem; }
            .form-input { padding: 1rem 1rem 1rem 3rem; font-size: 1rem; }
            .input-icon { font-size: 1.25rem; }
            .btn-login, .btn-outline { padding: 1.125rem; font-size: 1.0625rem; }
            .form-label { font-size: 0.9375rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="bi bi-search"></i>
                </div>
                <h1 class="login-title">Check Results</h1>
                <p class="login-subtitle">Enter your details to view your scores</p>
            </div>
            
            <div class="login-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="exam_code">Exam Code</label>
                        <div class="input-wrapper">
                            <i class="bi bi-key input-icon"></i>
                            <input type="text" name="exam_code" id="exam_code" class="form-input" 
                                   placeholder="Enter exam code" required maxlength="20" 
                                   value="<?= htmlspecialchars($exam_code) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="index_number">Index Number</label>
                        <div class="input-wrapper">
                            <i class="bi bi-card-text input-icon"></i>
                            <input type="text" name="index_number" id="index_number" class="form-input" 
                                   placeholder="Enter your index number" required maxlength="50"
                                   pattern="[A-Za-z0-9\s\-\_]+" title="Letters, numbers, spaces, hyphens, and underscores only">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="bi bi-clipboard-data me-2"></i>Check Results
                    </button>
                    
                    <a href="student_login.php" class="btn-outline">
                        <i class="bi bi-arrow-left me-2"></i>Back to Exam Login
                    </a>
                </form>
            </div>
        </div>
    </div>
    
    <script defer src="theme.js"></script>
</body>
</html>
