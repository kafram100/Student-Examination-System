<?php
require 'db.php';
require 'auth.php';

redirectIfLoggedIn();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
            ");
            $stmt->execute([$user['id'], $token_hash, $expires, $token_hash, $expires]);
            
            // In a real application, you would send an email here
            // For this demo, we'll show the reset link
            $reset_link = "reset_password.php?token=" . urlencode($token);
            $safe_reset_link = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');
            $success = "Password reset link generated. <br><a href=\"{$safe_reset_link}\">Click here to reset your password</a><br><br><small class='text-muted'>Note: In production, this link would be sent via email.</small>";
        } else {
            // Don't reveal if email exists or not for security
            $success = "If an account with that email exists, a password reset link has been sent.";
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Student Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .card { width: 100%; max-width: 400px; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="card">
        <h3 class="text-center mb-4">Forgot Password</h3>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="mb-3">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" required maxlength="100">
                <small class="text-muted">Enter the email associated with your account</small>
            </div>
            <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
        </form>
        <?php endif; ?>
        
        <div class="mt-3 text-center">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
    <script defer src="theme.js"></script>
</body>
</html>

