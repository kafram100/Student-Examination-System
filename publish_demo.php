<?php
require 'db.php';

$exam_code = 'DEMO01';

$stmt = $pdo->prepare("UPDATE exams SET is_published = 1 WHERE exam_code = ?");
$stmt->execute([$exam_code]);

echo "Exam $exam_code published!";
?>
