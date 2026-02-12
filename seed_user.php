<?php
require 'db.php';

$username = 'admin';
$email = 'admin@exam.com';
$password = 'admin123';

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        echo "User 'admin' already exists.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $hash]);
        echo "User created successfully.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
