<?php
session_start();
$_SESSION = array();
session_destroy();

// Kick them back out to the login page sitting in the same public directory
header("location: ../public/login.php");
exit;
?>