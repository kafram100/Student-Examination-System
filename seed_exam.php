<?php
require 'db.php';

// Hardcoded for demo purposes since we just created 'admin'
$username = 'admin'; 

echo "Checking for user: $username...<br>";

// Get User ID
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        $user_id = $user['id'];
        echo "User Found: ID $user_id<br>";
        
        // Check if demo exam exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE user_id = ? AND title = 'Demo Exam'");
        $stmt->execute([$user_id]);
        
        if ($stmt->fetchColumn() == 0) {
            // Create Draft Exam
            $exam_code = 'DEMO01';
            $stmt = $pdo->prepare("INSERT INTO exams (user_id, title, course_name, instructions, duration, attempts_allowed, exam_code, is_published) VALUES (?, 'Demo Exam', 'General Knowledge', 'This is a demo exam to test the system.', 30, 1, ?, 0)");
            $stmt->execute([$user_id, $exam_code]);
            echo "Demo Exam created successfully. Refresh your dashboard!";
        } else {
            echo "Demo Exam already exists.";
        }
    } else {
        echo "User 'admin' not found. Please register or login first.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
