<?php
require_once 'db.php';
require_once 'auth.php';

// Only allow authenticated users
requireLogin();

$reason = $_GET['reason'] ?? 'Unknown reason';
$exam_attempt_id = $_GET['attempt_id'] ?? null;

// Log the termination
if ($exam_attempt_id) {
    $stmt = $pdo->prepare("
        UPDATE exam_sessions_proctoring 
        SET proctoring_status = 'violated', 
            end_time = NOW()
        WHERE exam_attempt_id = ? AND student_id = ?
    ");
    $stmt->execute([$exam_attempt_id, $_SESSION['user_id']]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Terminated - Student Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .termination-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .termination-icon {
            font-size: 3rem;
            color: #e74c3c;
            margin-bottom: 1rem;
        }
        
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="termination-card">
        <div class="termination-icon">
            <i class="bi bi-x-octagon"></i>
        </div>
        <h2 class="text-danger mb-3">Exam Terminated</h2>
        <p class="lead">Your exam session has been terminated due to a security violation.</p>
        <div class="alert alert-warning text-start">
            <strong>Reason:</strong> <?= htmlspecialchars($reason) ?>
        </div>
        <p>For more information, please contact your lecturer or administrator.</p>
        <a href="index.php" class="btn btn-home text-white">Return to Home</a>
    </div>
</body>
</html>