<?php
require 'db.php';
require 'auth.php';

redirectIfLoggedIn();

$error = '';
$success = '';
$valid_token = false;
$user_id = null;

// Validate token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        $error = 'Invalid reset token format.';
    } else {
        $token_hash = hash('sha256', $token);
    
        $stmt = $pdo->prepare("
            SELECT user_id, expires_at 
            FROM password_resets 
            WHERE token = ? AND expires_at > NOW()
        ");
        $stmt->execute([$token_hash]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            $valid_token = true;
            $user_id = $reset['user_id'];
        } else {
            $error = 'Invalid or expired reset token. Please request a new password reset.';
        }
    }
} else {
    $error = 'No reset token provided.';
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    checkCSRF();
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Validate password strength
        $passwordErrors = validatePasswordStrength($password);
        if (!empty($passwordErrors)) {
            $error = 'Password requirements: ' . implode(', ', $passwordErrors);
        } else {
            // Update password
            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($stmt->execute([$hash, $user_id])) {
                // Delete used token
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$user_id]);
                session_regenerate_id(true);
                rotateCSRFToken();
                
                $success = 'Password reset successful! You can now <a href="login.php">login</a> with your new password.';
            } else {
                $error = 'Failed to reset password. Please try again.';
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
    <title>Reset Password - Student Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .card { width: 100%; max-width: 400px; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="card">
        <h3 class="text-center mb-4">Reset Password</h3>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($valid_token && !$success): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="mb-3">
                <label>New Password</label>
                <input type="password" name="password" class="form-control" required minlength="8">
                <small class="text-muted">Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char</small>
            </div>
            <div class="mb-3">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
        <?php endif; ?>
        
        <?php if (!$valid_token || $success): ?>
        <div class="mt-3 text-center">
            <a href="forgot_password.php">Request New Reset Link</a> | 
            <a href="login.php">Back to Login</a>
        </div>
        <?php endif; ?>
    </div>
    <script defer src="theme.js"></script>
</body>
</html>

