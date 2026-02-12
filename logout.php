<?php
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

checkCSRF();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
?>
