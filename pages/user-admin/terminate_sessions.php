<?php
require_once __DIR__ . '/functions.php';

require_once __DIR__ . '/../../includes/db_connect.php';
check_admin_login($conn);

function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmation = trim($_POST['confirmation'] ?? '');

    if ($confirmation !== 'TERMINATE') {
        set_flash('error', 'Invalid confirmation text. Action aborted.');
        header('Location: admin_dashboard.php');
        exit;
    }

    try {
        // Use an `app_settings` table to store a global logout time.
        // This is safer than trying to delete session files.
        $sql = "INSERT INTO app_settings (setting_key, setting_value) VALUES ('last_global_logout', NOW()) ON DUPLICATE KEY UPDATE setting_value = NOW()";
        $conn->query($sql);

        set_flash('success', 'All user sessions have been marked for termination. Users will be logged out on their next action.');
        header('Location: admin_dashboard.php');
        exit;
    } catch (Exception $e) {
        set_flash('error', 'Failed to terminate sessions: ' . $e->getMessage());
        header('Location: admin_dashboard.php');
        exit;
    }
}
?>