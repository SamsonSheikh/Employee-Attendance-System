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
function check_admin_login() {
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        // Path routes back out to the public folder's login page
        header("location: ../public/login.php");
        exit;
    }
}