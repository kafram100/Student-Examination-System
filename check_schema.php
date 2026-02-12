<?php
require 'db.php';
$stmt = $pdo->query("DESCRIBE exams");
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($fields as $field) {
    echo $field['Field'] . " | " . $field['Type'] . " | " . $field['Null'] . " | " . $field['Default'] . "\n";
}
?>
