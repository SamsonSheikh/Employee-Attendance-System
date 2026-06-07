<?php
// db_connect.php - SAFE TO COMMIT TO GITHUB

// 1. Pull in the configuration array
$db_config = require_once 'config.php';

// 2. Set the default PHP timezone for date() and time() functions
date_default_timezone_set($db_config['php_timezone']);

// 3. Enable strict error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 4. Establish the database connection
    $conn = new mysqli(
        $db_config['host'], 
        $db_config['user'], 
        $db_config['pass'], 
        $db_config['name']
    );

    // 5. Enforce UTF-8 encoding
    $conn->set_charset("utf8mb4");

    // 6. Synchronize MySQL timezone to ensure exact clock-in timestamps
    $conn->query("SET time_zone = '" . $db_config['sql_timezone'] . "'");

} catch (Exception $e) {
    die("<strong>Database Connection Failed:</strong> Ensure your local config.php file is set up correctly and MySQL is running.");
}
?>