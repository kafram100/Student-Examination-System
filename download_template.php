<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="questions_template.csv"');

$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['question_type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_answer', 'marks']);

// Add sample rows to guide the user
// Sample MCQ question
fputcsv($output, ['mcq', 'What is 2+2?', '1', '2', '3', '4', '', 'D', '1']);

// Sample Fill-in question
fputcsv($output, ['fill_in', 'The capital of France is ______.', '', '', '', '', '', 'Paris', '2']);

// Sample Theory question
fputcsv($output, ['theory', 'Explain the concept of object-oriented programming.', '', '', '', '', '', '', '5']);

// Sample File upload question
fputcsv($output, ['file', 'Upload your project documentation here.', '', '', '', '', '', '', '10']);

fclose($output);
exit;
?>
