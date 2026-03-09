<?php
require 'db.php';
require 'auth.php';

redirectIfLoggedIn();

$error = '';
$showLecturerForm = ((isset($_GET['role']) && $_GET['role'] === 'lecturer') || isset($_GET['registered']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $showLecturerForm = true;
    checkCSRF();

    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!checkRateLimit('login_' . $_SERVER['REMOTE_ADDR'])) {
        $error = 'Too many login attempts. Please try again in 5 minutes.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'lecturer';
            session_regenerate_id(true);
            rotateCSRFToken();
            resetRateLimit('login_' . $_SERVER['REMOTE_ADDR']);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials.';
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Student Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="theme.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem;
        }

        .auth-container {
            width: 100%;
            max-width: 480px;
            animation: fadeIn 0.6s ease;
        }

        .auth-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .auth-header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
        }

        .auth-icon {
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

        .auth-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .auth-subtitle {
            opacity: 0.9;
            font-size: 0.9375rem;
        }

        .auth-body {
            padding: 2rem;
        }

        .role-switch {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .role-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            padding: 0.8rem 0.875rem;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .role-chip.lecturer {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #4338ca;
        }

        .role-chip.lecturer:hover {
            background: #e0e7ff;
            color: #3730a3;
        }

        .role-chip.student {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-color: transparent;
            color: #ffffff;
            box-shadow: 0 8px 18px -8px rgba(16, 185, 129, 0.45);
        }

        .role-chip.student:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px -8px rgba(16, 185, 129, 0.5);
            color: #ffffff;
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
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
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
            color: #6366f1;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 1.125rem;
            padding: 0.25rem;
        }

        .password-toggle:hover {
            color: #6366f1;
        }

        .btn-login {
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
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .auth-footer {
            text-align: center;
            padding: 1.5rem 2rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }

        .auth-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .auth-links a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            transition: color 0.2s;
        }

        .auth-links a:hover {
            color: #4f46e5;
            text-decoration: underline;
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

        .alert-success {
            background: #d1fae5;
            color: #065f46;
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
            .auth-container { max-width: 100%; }
            .auth-card { border-radius: 20px; }
            .auth-header { padding: 2.5rem 1.5rem; }
            .auth-body { padding: 2rem 1.5rem; }
            .auth-icon { width: 72px; height: 72px; font-size: 1.75rem; }
            .auth-title { font-size: 1.75rem; }
            .auth-subtitle { font-size: 1rem; }
            .form-input { padding: 1rem 1rem 1rem 3rem; font-size: 1rem; }
            .input-icon { font-size: 1.25rem; }
            .btn-login { padding: 1.125rem; font-size: 1.0625rem; }
            .form-label { font-size: 0.9375rem; }
            .auth-links a { font-size: 0.9375rem; }
            .role-switch { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h1 class="auth-title"><?= $showLecturerForm ? 'Lecturer Login' : 'Choose Access' ?></h1>
                <p class="auth-subtitle"><?= $showLecturerForm ? 'Enter your username and password to continue' : 'Select Lecturer Login or Take Exam' ?></p>
            </div>

            <div class="auth-body">
                <?php if (!$showLecturerForm): ?>
                <div class="role-switch" aria-label="Choose login type">
                    <a href="login.php?role=lecturer" class="role-chip lecturer">
                        <i class="bi bi-person-badge"></i>Lecturer Login
                    </a>
                    <a href="student_login.php" class="role-chip student">
                        <i class="bi bi-pencil-square"></i>Take Exam
                    </a>
                </div>
                <?php endif; ?>

                <?php if ($showLecturerForm): ?>
                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill"></i>
                            Registration successful! Please login.
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php?role=lecturer">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <div class="input-wrapper">
                                <i class="bi bi-person input-icon"></i>
                                <input type="text" name="username" id="username" class="form-input" placeholder="Enter your username" required maxlength="50">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-wrapper">
                                <i class="bi bi-lock input-icon"></i>
                                <input type="password" name="password" id="password" class="form-input" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($showLecturerForm): ?>
            <div class="auth-footer">
                <div class="auth-links">
                    <a href="forgot_password.php">
                        <i class="bi bi-key me-1"></i>Forgot Password?
                    </a>
                    <a href="register.php">
                        <i class="bi bi-person-plus me-1"></i>Create Account
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                toggleBtn.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            });
        }
    </script>
    <script defer src="theme.js"></script>
</body>
</html>