<?php
// private/functions.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Secures a page by ensuring the user is logged in.
 * Redirects to login page if they are not.
 */
function check_admin_login($conn) {
    $is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

    if (!$is_logged_in) {
        header("location: ../public/login.php");
        exit;
    }

    // --- Global Session Termination Check ---
    // On every authenticated page load, check if a global logout has been triggered.
    
    // 1. Get the last global logout time from the database
    $last_global_logout = null;
    try {
        $result = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key = 'last_global_logout' LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) {
            $last_global_logout = strtotime($row['setting_value']);
        }
    } catch (Exception $e) {
        // If table doesn't exist, we can't check, so we just continue.
    }

    // 2. Get the user's login time from their session.
    $user_login_time = $_SESSION['login_time'] ?? 0;

    // 3. If a global logout was triggered AFTER this user logged in, destroy their session.
    if ($last_global_logout && $user_login_time < $last_global_logout) {
        // Cleanly destroy the session
        $_SESSION = array();
        session_destroy();
        header("location: ../public/login.php?reason=session_terminated");
        exit;
    }
}