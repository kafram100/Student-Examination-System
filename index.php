<?php
session_start();
// If user is already logged in as lecturer, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="theme.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .hero-container {
            width: 100%;
            max-width: 500px;
            text-align: center;
            animation: fadeIn 0.6s ease;
        }
        
        .hero-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 3rem 2rem;
            overflow: hidden;
        }
        
        .hero-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: white;
        }
        
        .hero-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        
        .hero-subtitle {
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .exam-code-form {
            background: #f9fafb;
            border-radius: 16px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            margin-bottom: 1rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);
        }
        
        .btn-outline {
            width: 100%;
            padding: 1rem;
            background: transparent;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            color: #6366f1;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
            border-color: #6366f1;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .divider-text {
            padding: 0 1rem;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 576px) {
            body { padding: 0.5rem; }
            .hero-container { max-width: 100%; }
            .hero-card { padding: 2rem 1.5rem; }
            .hero-title { font-size: 1.5rem; }
            .hero-icon { width: 80px; height: 80px; font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="hero-container">
        <div class="hero-card">
            <div class="hero-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            
            <h1 class="hero-title">Student Exam System</h1>
            <p class="hero-subtitle">
                Take your exams securely online. Enter your exam code to get started.
            </p>
            
            <div class="exam-code-form">
                <form action="student_login.php" method="GET">
                    <input 
                        type="text" 
                        name="exam_code" 
                        placeholder="Enter your exam code" 
                        class="form-input" 
                        required 
                        maxlength="20"
                        autocomplete="off"
                    >
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Start Exam
                    </button>
                </form>
            </div>
            
            <div class="divider">
                <span class="divider-text">OR</span>
            </div>
            
            <a href="login.php" class="btn-outline">
                <i class="bi bi-person-circle me-2"></i>Lecturer Login
            </a>
        </div>
    </div>
    
    <script defer src="theme.js"></script>
</body>
</html>