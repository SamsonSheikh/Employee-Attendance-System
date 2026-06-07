<?php
// db_connect.php - SAFE TO COMMIT TO GITHUB

// Use __DIR__ to lock the path to the includes/ folder
$db_config = require_once __DIR__ . '/config.php';

// Set the default PHP timezone
date_default_timezone_set($db_config['php_timezone']);

// Enable strict error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $db_config['host'], 
        $db_config['user'], 
        $db_config['pass'], 
        $db_config['name']
    );

    $conn->set_charset("utf8mb4");
    $conn->query("SET time_zone = '" . $db_config['sql_timezone'] . "'");

} catch (Exception $e) {
    die("<strong>Database Connection Failed:</strong> Ensure your local config.php file is set up correctly and MySQL is running.");
}
?>