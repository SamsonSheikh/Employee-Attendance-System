<?php
// Script to update the attendance_logs table for multi-punch support.
require_once __DIR__ . '/includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 800px; margin: 50px auto;'>";
echo "<h2>Database Schema Migration: Attendance Logs</h2>";

try {
    // Check if new columns already exist to make the script re-runnable
    $result = $conn->query("SHOW COLUMNS FROM `attendance_logs` LIKE 'morning_clock_in'");
    if ($result->num_rows == 0) {
        echo "<p>Applying schema changes...</p>";

        // 1. Rename old columns if they exist
        $conn->query("ALTER TABLE `attendance_logs` CHANGE `clock_in` `morning_clock_in` DATETIME NULL DEFAULT NULL;");
        echo "<p>✅ Renamed `clock_in` to `morning_clock_in`.</p>";
        
        $conn->query("ALTER TABLE `attendance_logs` CHANGE `clock_out` `morning_clock_out` DATETIME NULL DEFAULT NULL;");
        echo "<p>✅ Renamed `clock_out` to `morning_clock_out`.</p>";

        // 2. Add new columns for the afternoon session
        $conn->query("ALTER TABLE `attendance_logs` ADD `afternoon_clock_in` DATETIME NULL DEFAULT NULL AFTER `morning_clock_out`;");
        echo "<p>✅ Added `afternoon_clock_in` column.</p>";
        
        $conn->query("ALTER TABLE `attendance_logs` ADD `afternoon_clock_out` DATETIME NULL DEFAULT NULL AFTER `afternoon_clock_in`;");
        echo "<p>✅ Added `afternoon_clock_out` column.</p>";

        echo "<h3 style='color: #155724;'>Migration successful! You can now delete this file.</h3>";
    } else {
        echo "<p style='color: #065f46;'>Schema already appears to be up-to-date. No changes were made.</p>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'><strong>Migration Failed:</strong> " . $e->getMessage() . "</div>";
    echo "<p>Please check your database permissions or apply the SQL changes manually.</p>";
}

echo "</div>";
$conn->close();
?>
