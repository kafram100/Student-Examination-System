<?php
require_once 'db.php';

$result = $pdo->query("SHOW COLUMNS FROM users");
$rows = $result->fetchAll();

echo "Users table columns:\n";
foreach ($rows as $row) {
    echo $row['Field'] . "\n";
}
?>