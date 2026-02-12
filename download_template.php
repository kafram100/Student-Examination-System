<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="questions_template.csv"');

$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_option', 'marks']);

// Add a sample row to guide the user
fputcsv($output, ['What is 2+2?', '1', '2', '3', '4', '', 'D', '1']);

fclose($output);
exit;
?>
