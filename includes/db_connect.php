<?php
// db_connect.php - SAFE TO COMMIT TO GITHUB

// 1. Pull in the ignored configuration file
require_once 'config.php';

// 2. Enable mysqli exceptions for better error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 3. Attempt the connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // 4. Set the character set to handle special characters properly
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    // 5. Catch connection errors
    die("<strong>Database Connection Failed:</strong> Ensure your local config.php file is set up correctly and your XAMPP MySQL server is running.");
}
?>