<?php
// Include the authentication function from the same directory
require_once __DIR__ . '/functions.php';


require_once __DIR__ . '/../../includes/db_connect.php';

// Get admin's name
$admin_name = isset($_SESSION["username"]) ? $_SESSION["username"] : "Admin";

// Flash message handling
$flash = [];
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

check_admin_login($conn);

function set_flash($type, $message, $errors = []) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message, 'errors' => $errors];
}

// --- PHPMailer Integration ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
 
/**
 * Sends a welcome email to a new user with their account details and a unique QR code.
 * The QR code is generated using an external API.
 *
 * @param string $email The recipient's email address.
 * @param string $firstName The recipient's first name for personalization.
 * @param string $qrIdentifier The unique string to be encoded in the QR code.
 * @return bool Returns true if the mail is successfully accepted for delivery, false otherwise.
 */
function send_qr_email($email, $firstName, $qrIdentifier) {
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Fallback or error if PHPMailer is not installed
        error_log("PHPMailer not found. Cannot send email.");
        return false;
    }

    $db_config = require __DIR__ . '/../../includes/config.php';
    $mail_config = $db_config['mail'] ?? null;

    if (!$mail_config) {
        error_log("Mail configuration is missing.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $mail_config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_config['username'];
        $mail->Password   = $mail_config['password'];
        $mail->SMTPSecure = $mail_config['encryption'];
        $mail->Port       = $mail_config['port'];

        // Recipients
        $mail->setFrom($mail_config['from_address'], $mail_config['from_name']);
        $mail->addAddress($email, $firstName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Welcome to FlowTime - Your Account is Ready!";
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrIdentifier);
        $mail->Body    = "<html><body style='font-family: sans-serif; color: #333;'><h2>Welcome, " . htmlspecialchars($firstName) . "!</h2><p>Your account for the FlowTime system has been created successfully.</p><p>Please use the QR code below for attendance purposes. You can scan this directly from your phone.</p><img src='" . $qrCodeUrl . "' alt='Your Personal QR Code' style='display: block; margin: 20px 0;'/><p>If you have any questions, please contact the administration.</p></body></html>";
        $mail->AltBody = 'Welcome, ' . htmlspecialchars($firstName) . '! Your account has been created. Your QR code is available at: ' . $qrCodeUrl;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$action = $_GET['action'] ?? null;

// --- Add / Bulk Add User backend ---
if ($action === 'add_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = (string)($_POST['password'] ?? '');
        $role_id    = $_POST['role_id'] ?? null;
        $department_id = $_POST['department_id'] ?? null;

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $role_id === null || $role_id === '') {
            throw new Exception('Missing required fields.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address.');
        }

        // Ensure role exists
        $role_id_int = (int)$role_id;
        $stmt = $conn->prepare('SELECT role_id FROM roles WHERE role_id = ?');
        $stmt->bind_param('i', $role_id_int);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception('Selected role is invalid.');
        }
        $stmt->close();

        $department_id_int = null;
        if ($department_id !== null && $department_id !== '') {
            $department_id_int = (int)$department_id;
        }

        // Email uniqueness
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception('Email already exists.');
        }
        $stmt->close();

        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters.');
        }

        $qr_identifier = 'flowtime-' . bin2hex(random_bytes(16));
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, department_id, role_id, qr_identifier)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $department_param = $department_id_int;

        $stmt->bind_param(
            'ssssiis',
            $first_name,
            $last_name,
            $email,
            $password_hash,
            $department_param,
            $role_id_int,
            $qr_identifier
        );

        $stmt->execute();
        $stmt->close();

        // Send the welcome email with the QR code
        send_qr_email($email, $first_name, $qr_identifier);

        // Redirect to show the QR code modal on the same page
        $new_user_name = $first_name . ' ' . $last_name;
        header('Location: adminusers.php?flash=user_added&name=' . urlencode($new_user_name) . '&qr=' . urlencode($qr_identifier));
        exit;
    } catch (Throwable $e) {
        $flash['type'] = 'error';
        $flash['message'] = $e->getMessage();
        // Fall through to show flash; re-render page below
    }

// --- Bulk Add Users backend ---
} elseif ($action === 'bulk_add_users' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csvText = (string)($_POST['bulk_csv'] ?? '');
        $defaultRoleId = $_POST['role_id'] ?? '';
        $defaultDepartmentId = $_POST['department_id'] ?? '';

        $csvText = trim($csvText);
        if ($csvText === '') {
            throw new Exception('Please paste CSV/TSV data to bulk add users.');
        }

        $role_id_int = (int)$defaultRoleId;
        if ($defaultRoleId === '' || $defaultRoleId === null || $role_id_int <= 0) {
            throw new Exception('Default Role is required for bulk add.');
        }

        // Ensure default role exists
        $stmt = $conn->prepare('SELECT role_id FROM roles WHERE role_id = ?');
        $stmt->bind_param('i', $role_id_int);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception('Selected default role is invalid.');
        }
        $stmt->close();

        $department_id_int_default = null;
        if ($defaultDepartmentId !== null && $defaultDepartmentId !== '') {
            $department_id_int_default = (int)$defaultDepartmentId;
        }

        // Split lines (support both CSV and TSV by detecting delimiter per line)
        $lines = preg_split('/\r\n|\r|\n/', $csvText);
        if ($lines === false) {
            throw new Exception('Failed to parse bulk input.');
        }

        // If the first row looks like a header, skip it
        $lineIndex = 0;
        if (!empty($lines[0])) {
            $headerCandidate = strtolower(trim($lines[0]));
            if (str_contains($headerCandidate, 'first') && str_contains($headerCandidate, 'email')) {
                $lineIndex = 1;
            }
        }

        $added = 0;
        $errors = [];

        $conn->begin_transaction();

        for ($i = $lineIndex; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            // Choose delimiter
            $delimiter = str_contains($line, '\t') ? "\t" : ',';
            $parts = str_getcsv($line, $delimiter);
            if ($parts === false) {
                $errors[] = 'Row ' . ($i + 1) . ': Unable to parse line.';
                continue;
            }

            $first_name = trim($parts[0] ?? '');
            $last_name  = trim($parts[1] ?? '');
            $email      = trim($parts[2] ?? '');
            $password   = (string)trim($parts[3] ?? '');

            $rowRoleId = trim($parts[4] ?? '');
            $rowDepartmentId = trim($parts[5] ?? '');

            if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
                $errors[] = 'Row ' . ($i + 1) . ': Missing required fields (first_name,last_name,email,password).';
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Row ' . ($i + 1) . ': Invalid email address.';
                continue;
            }

            if (strlen($password) < 8) {
                $errors[] = 'Row ' . ($i + 1) . ': Password must be at least 8 characters.';
                continue;
            }

            $rowRoleIdInt = $rowRoleId !== '' ? (int)$rowRoleId : $role_id_int;
            if ($rowRoleId !== '' && $rowRoleIdInt <= 0) {
                $errors[] = 'Row ' . ($i + 1) . ': Invalid role_id.';
                continue;
            }

            $rowDepartmentIdInt = $rowDepartmentId !== '' ? (int)$rowDepartmentId : $department_id_int_default;

            // Role exists check
            $stmt = $conn->prepare('SELECT role_id FROM roles WHERE role_id = ?');
            $stmt->bind_param('i', $rowRoleIdInt);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $errors[] = 'Row ' . ($i + 1) . ': role_id does not exist.';
                $stmt->close();
                continue;
            }
            $stmt->close();

            // Email uniqueness
            $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = 'Row ' . ($i + 1) . ': Email already exists (' . $email . ').';
                $stmt->close();
                continue;
            }
            $stmt->close();

            $qr_identifier = 'flowtime-' . bin2hex(random_bytes(16));
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                'INSERT INTO users (first_name, last_name, email, password_hash, department_id, role_id, qr_identifier)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $department_param = $rowDepartmentIdInt;

            $stmt->bind_param(
                'ssssiis',
                $first_name,
                $last_name,
                $email,
                $password_hash,
                $department_param,
                $rowRoleIdInt,
                $qr_identifier
            );

            $stmt->execute();
            $stmt->close();

            // Send the welcome email with the QR code
            send_qr_email($email, $first_name, $qr_identifier);

            $added++;
        }

        if (!empty($errors) && $added === 0) {
            $conn->rollback();
            set_flash('error', 'Bulk add failed. No users were added. First error: ' . htmlspecialchars($errors[0], ENT_QUOTES, 'UTF-8'), $errors);
        } else {
            $conn->commit();
            $type = empty($errors) ? 'success' : 'warning';
            $message = 'Bulk add complete. Added: ' . $added . '. Failed: ' . count($errors) . '.';
            $error_list = !empty($errors) ? array_slice($errors, 0, 10) : [];
            set_flash($type, $message, $error_list);
        }

        header('Location: adminusers.php');
        exit;

    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        set_flash('error', $e->getMessage());
        header('Location: adminusers.php');
        exit;
    }
}

// Simple flash via redirect
if (isset($_GET['flash']) && $_GET['flash'] === 'bulk') {
    $flash['type'] = 'success';
    $flash['message'] = 'Bulk add processed. Please check the user list.';
}
if (isset($_GET['flash']) && $_GET['flash'] === 'deleted') {
    $flash['type'] = 'success';
    $flash['message'] = 'User deleted successfully.';
}
if (isset($_GET['flash']) && $_GET['flash'] === 'password_changed') {
    $flash['type'] = 'success';
    $flash['message'] = 'Password updated successfully.';
}
if (isset($_GET['flash']) && $_GET['flash'] === 'role_changed') {
    $flash['type'] = 'success';
    $flash['message'] = 'Role updated successfully.';
}
if (isset($_GET['error']) && $_GET['error'] === '1') {
    $flash['type'] = 'error';
    $flash['message'] = 'Failed to add user.';
}

// --- Load select options ---
$roles = [];
$stmt = $conn->query('SELECT role_id, role_name FROM roles ORDER BY role_name ASC');
while ($row = $stmt->fetch_assoc()) {
    $roles[] = $row;
}

$departments = [];
$stmt = $conn->query('SELECT department_id, department_name FROM departments ORDER BY department_name ASC');
while ($row = $stmt->fetch_assoc()) {
    $departments[] = $row;
}

// --- Bulk / Edit Account backends ---
if ($action === 'delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0) {
            throw new Exception('Invalid user id.');
        }

        $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();

        header('Location: adminusers.php?flash=deleted');
        exit;
    } catch (Throwable $e) {
        $flash['type'] = 'error';
        $flash['message'] = $e->getMessage();
    }
}

// Deactivation from security card
if ($action === 'deactivate_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($user_id <= 0) {
            throw new Exception('Invalid user selected for deactivation.');
        }
        if ($reason === '') {
            throw new Exception('A reason is required for deactivation.');
        }

        // Fetch user details before deleting for the report
        $stmt = $conn->prepare('SELECT first_name, last_name, email FROM users WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            throw new Exception('User to be deactivated not found.');
        }

        // Delete the user
        $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();

        // --- Generate and download the report file ---
        $adminUser = $_SESSION['username'] ?? 'Admin';
        $report_content = "USER ACCOUNT TERMINATION REPORT\r\n";
        $report_content .= "===================================\r\n";
        $report_content .= "Date: " . date('Y-m-d H:i:s') . "\r\n";
        $report_content .= "Deactivated User ID: " . $user_id . "\r\n";
        $report_content .= "Deactivated User Name: " . $user['first_name'] . ' ' . $user['last_name'] . "\r\n";
        $report_content .= "Deactivated User Email: " . $user['email'] . "\r\n";
        $report_content .= "-----------------------------------\r\n";
        $report_content .= "Reason for Deactivation:\r\n" . $reason . "\r\n";
        $report_content .= "-----------------------------------\r\n";
        $report_content .= "Action performed by: " . $adminUser . "\r\n";

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="deactivation_report_' . $user_id . '_' . date('Ymd') . '.txt"');
        header('Content-Length: ' . strlen($report_content));
        echo $report_content;
        exit;

    } catch (Throwable $e) {
        $flash['type'] = 'error';
        $flash['message'] = 'Deactivation failed: ' . $e->getMessage();
    }
}

if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_password = (string)($_POST['new_password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');

        if ($user_id <= 0) {
            throw new Exception('Invalid user id.');
        }
        if ($new_password === '' || $confirm_password === '') {
            throw new Exception('Password fields are required.');
        }
        if (strlen($new_password) < 8) {
            throw new Exception('New password must be at least 8 characters.');
        }
        if ($new_password !== $confirm_password) {
            throw new Exception('Password confirmation does not match.');
        }

        $stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            throw new Exception('User not found.');
        }
        $stmt->close();

        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->bind_param('si', $hash, $user_id);
        $stmt->execute();
        $stmt->close();

        header('Location: adminusers.php?flash=password_changed');
        exit;
    } catch (Throwable $e) {
        $flash['type'] = 'error';
        $flash['message'] = $e->getMessage();
    }
}

// Role Assignment
if ($action === 'update_user_role' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_role_id = (int)($_POST['new_role_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($user_id <= 0 || $new_role_id <= 0) {
            throw new Exception('Invalid user id or role id.');
        }
        if ($reason === '') {
            throw new Exception('Reason is required.');
        }

        // Fetch user details and role names for the report
        $stmt = $conn->prepare('
            SELECT u.first_name, u.last_name, u.email, r_old.role_name AS old_role_name, r_new.role_name AS new_role_name
            FROM users u
            LEFT JOIN roles r_old ON u.role_id = r_old.role_id
            CROSS JOIN (SELECT role_name FROM roles WHERE role_id = ?) AS r_new
            WHERE u.user_id = ?
        ');
        $stmt->bind_param('ii', $new_role_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $report_data = $result->fetch_assoc();
        $stmt->close();
        if (!$report_data) {
            throw new Exception('User not found.');
        }

        $stmt = $conn->prepare('UPDATE users SET role_id = ? WHERE user_id = ?');
        $stmt->bind_param('ii', $new_role_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // --- Generate and download the report file ---
        $adminUser = isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'Admin';
        $report_content = "USER ROLE ASSIGNMENT REPORT\r\n";
        $report_content .= "===================================\r\n";
        $report_content .= "Date: " . date('Y-m-d H:i:s') . "\r\n";
        $report_content .= "Action performed by: " . $adminUser . "\r\n";
        $report_content .= "-----------------------------------\r\n";
        $report_content .= "User ID: " . $user_id . "\r\n";
        $report_content .= "User Name: " . $report_data['first_name'] . ' ' . $report_data['last_name'] . "\r\n";
        $report_content .= "User Email: " . $report_data['email'] . "\r\n";
        $report_content .= "-----------------------------------\r\n";
        $report_content .= "Previous Role: " . ($report_data['old_role_name'] ?? 'N/A') . "\r\n";
        $report_content .= "New Role Assigned: " . $report_data['new_role_name'] . "\r\n";
        $report_content .= "-----------------------------------\r\n";
        $report_content .= "Reason for Change:\r\n" . $reason . "\r\n";

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="role_assignment_report_' . $user_id . '_' . date('Ymd') . '.txt"');
        header('Content-Length: ' . strlen($report_content));
        echo $report_content;
        exit;
    } catch (Throwable $e) {
        $flash['type'] = 'error';
        $flash['message'] = $e->getMessage();
    }
}

// --- Load user list ---
$users = [];
$sql = 'SELECT u.user_id, u.first_name, u.last_name, u.email,
               r.role_name, u.qr_identifier,
               u.department_id,
               d.department_name,
               u.created_at
        FROM users u
        LEFT JOIN roles r ON r.role_id = u.role_id
        LEFT JOIN departments d ON d.department_id = u.department_id
        ORDER BY u.user_id DESC';

$stmt = $conn->query($sql);
while ($row = $stmt->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Users | EAS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <header class="mobile-header">
        <div class="admin-brand">
            <span class="brand-icon"><i class="ph-fill ph-clock-user"></i></span>
            <span class="brand-text">FlowTime</span>
        </div>
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle Sidebar">
            <i class="ph ph-list"></i>
        </button>
    </header>

    <div class="main-wrapper">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <aside class="sidebar" id="sidebar">
            <button class="close-sidebar" id="closeSidebar" aria-label="Close Sidebar">
                <i class="ph ph-x"></i>
            </button>

            <div class="admin-brand desktop-brand">
                <span class="brand-icon"><i class="ph-fill ph-clock-user"></i></span>
                <span class="brand-text">FlowTime</span>
            </div>

            <div class="sidebar-menu-wrapper">
                <ul class="sidebar-links">
                    <li><a href="../../pages/user-admin/admin_dashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li class="active"><a href="../../pages/user-admin/adminusers.php"><i class="ph ph-users"></i> Master Users</a></li>
                </ul>
            </div>

            <div class="sidebar-footer">
                <a href="../../pages/public/logout.php" class="sidebar-logout">
                    <i class="ph ph-sign-out"></i> Logout
                </a>
            </div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>Master User Management</h1>
            </header>

            <section class="users-section">
                <div class="section-header">
                    <h2>User List</h2>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
                        <button class="btn-primary add-user-btn" type="button" onclick="document.getElementById('addUserModal').style.display='block'">
                            <i class="ph ph-plus"></i> Add New User
                        </button>
                        <button class="btn-secondary" type="button" onclick="document.getElementById('bulkAddModal').style.display='block'">
                            <i class="ph ph-file-csv"></i> Bulk Add Users
                        </button>
                    </div>
                </div>

                <?php if (!empty($flash['type']) && !empty($flash['message'])): ?>
                    <div style="margin: 12px 0; padding: 10px 12px; border-radius: 10px; background: <?php echo $flash['type'] === 'success' ? '#dcfce7' : '#fee2e2'; ?>; color: #111; border: 1px solid <?php echo $flash['type'] === 'success' ? '#86efac' : '#fca5a5'; ?>">
                        <p><?php echo h($flash['message']); ?></p>
                        <?php if (!empty($flash['errors'])): ?>
                            <ul style="margin-top: 8px; padding-left: 20px; font-size: 0.85rem;">
                                <?php foreach ($flash['errors'] as $error): echo '<li>' . h($error) . '</li>'; endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>#<?php echo h($u['user_id']); ?></td>
                                        <td><?php echo h($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                        <td><?php echo h($u['email']); ?></td>
                                        <td>
                                            <?php
                                                $roleName = $u['role_name'] ?? '';
                                                $badgeClass = 'badge-employee';
                                                $badgeText = 'Employee';
                                                if (strcasecmp($roleName, 'Administrator') === 0) {
                                                    $badgeClass = 'badge-admin';
                                                    $badgeText = 'Admin';
                                                } elseif (stripos($roleName, 'Manager') !== false || strcasecmp($roleName, 'Supervisor') === 0) {
                                                    $badgeClass = 'badge-hr';
                                                    $badgeText = 'HR';
                                                }
                                            ?>
                                            <span class="badge <?php echo h($badgeClass); ?>"><?php echo h($badgeText); ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-active">Active</span>
                                        </td>
                                        <td class="action-buttons">
                                            <form method="POST" action="adminusers.php?action=change_password" style="display:inline-flex; gap:8px;">
                                                <input type="hidden" name="user_id" value="<?php echo h($u['user_id']); ?>" />
                                                <input type="hidden" name="new_password" value="" />
                                                <input type="hidden" name="confirm_password" value="" />
                                                <button class="btn-icon edit-btn" title="Change Password (Use modal below)" type="button" onclick="openChangePasswordModal(<?php echo (int)$u['user_id']; ?>)" aria-label="Change password">
                                                    <i class="ph ph-pencil"></i>
                                                </button>
                                                <button class="btn-icon" title="View QR Code" type="button" onclick="openQrCodeModal('<?php echo h($u['first_name'] . ' ' . $u['last_name']); ?>', '<?php echo h($u['qr_identifier']); ?>')">
                                                    <i class="ph ph-qr-code"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="adminusers.php?action=delete_user" style="display:inline;" onsubmit="return confirm('Delete user #<?php echo h($u['user_id']); ?>? This cannot be undone.');">
                                                <input type="hidden" name="user_id" value="<?php echo h($u['user_id']); ?>" />
                                                <button class="btn-icon delete-btn" title="Delete" type="submit">
                                                    <i class="ph ph-trash"></i>
                                                </button>
                                            </form>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Add User Modal -->
            <div id="addUserModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999;">
                <div style="background:#fff; max-width:640px; margin:60px auto; padding:18px 18px; border-radius:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h2 style="margin:0; font-size:18px;">Add New User</h2>
                        <button type="button" class="btn-secondary" onclick="document.getElementById('addUserModal').style.display='none'">Close</button>
                    </div>

                    <form method="POST" action="adminusers.php?action=add_user">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input id="first_name" name="first_name" class="form-control" required />
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input id="last_name" name="last_name" class="form-control" required />
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="email">Email <span class="required">*</span></label>
                                <input id="email" name="email" type="email" class="form-control" required />
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="password">Password <span class="required">*</span></label>
                                <input id="password" name="password" type="password" class="form-control" required minlength="8" />
                                <small style="display:block; margin-top:6px; color:#666;">Minimum 8 characters.</small>
                            </div>
                            <div class="form-group">
                                <label for="role_id">Role <span class="required">*</span></label>
                                <select id="role_id" name="role_id" class="form-control" required>
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo h($r['role_id']); ?>"><?php echo h($r['role_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="department_id">Department</label>
                                <select id="department_id" name="department_id" class="form-control">
                                    <option value="">-- Optional --</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?php echo h($d['department_id']); ?>"><?php echo h($d['department_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                            <button type="button" class="btn-secondary" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
                            <button type="submit" class="btn-primary"><i class="ph ph-plus"></i> Create User</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bulk Add Users Modal -->
            <div id="bulkAddModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999;">
                <div style="background:#fff; max-width:740px; margin:60px auto; padding:18px 18px; border-radius:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h2 style="margin:0; font-size:18px;">Bulk Add Users</h2>
                        <button type="button" class="btn-secondary" onclick="document.getElementById('bulkAddModal').style.display='none'">Close</button>
                    </div>

                    <div style="margin: 10px 0 16px; padding: 10px 12px; border-radius: 10px; background: #f3f4f6; color:#111;">
                        <div style="font-weight:600; margin-bottom:6px;">Paste CSV/TSV</div>
                        <div style="font-size:13px; color:#374151;">
                            Columns: <b>first_name,last_name,email,password</b>, then optionally <b>role_id</b>, <b>department_id</b>.
                            If you omit role/department/shift columns, defaults below will be used.
                            <br/><br/>
                            Example (CSV):<br/>
                            <code>Jane,Smith,jane.smith@flowtime.com,Password123,3,1,1</code><br/>
                            <code>John,Doe,john.doe@flowtime.com,Password123</code>
                        </div>
                    </div>

                    <form method="POST" action="adminusers.php?action=bulk_add_users">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="bulk_csv">Bulk input (CSV/TSV)</label>
                                <textarea id="bulk_csv" name="bulk_csv" class="form-control" rows="8" placeholder="Paste rows here..." required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="role_id">Default Role <span class="required">*</span></label>
                                <select id="role_id" name="role_id" class="form-control" required>
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo h($r['role_id']); ?>"><?php echo h($r['role_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="department_id">Default Department</label>
                                <select id="department_id" name="department_id" class="form-control">
                                    <option value="">-- Optional --</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?php echo h($d['department_id']); ?>"><?php echo h($d['department_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                            <button type="button" class="btn-secondary" onclick="document.getElementById('bulkAddModal').style.display='none'">Cancel</button>
                            <button type="submit" class="btn-primary"><i class="ph ph-plus"></i> Bulk Add</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- QR Code Display Modal -->
            <div id="qrCodeDisplayModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:99999;">
                <div style="background:#fff; max-width:480px; text-align:center; margin:80px auto; padding:2rem; border-radius:12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                    <h2 style="margin-top:0; margin-bottom:0.5rem; font-size:1.25rem;">User Account Created!</h2>
                    <p style="margin-top:0; margin-bottom:1.5rem; color: var(--text-muted);">Here is the unique QR code for <strong id="qrUserName"></strong>.</p>
                    
                    <div style="padding:1rem; border:1px solid var(--border-color); border-radius:8px; background:#f8fafc; margin-bottom:1.5rem;">
                        <img id="qrCodeImage" src="" alt="User QR Code" style="max-width:220px; width:100%; display:block; margin:0 auto;">
                    </div>

                    <p style="font-size:0.8rem; color:var(--text-muted);">You can right-click to save this image or take a screenshot.</p>

                    <div style="display:flex; justify-content:center; gap:10px; margin-top:1.5rem;">
                        <button type="button" class="btn-primary" style="width:100%;" onclick="document.getElementById('qrCodeDisplayModal').style.display='none'">
                            <i class="ph ph-check-circle"></i> Got it, Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- Change Password Modal -->
            <div id="changePasswordModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:99999;">
                <div style="background:#fff; max-width:540px; margin:70px auto; padding:18px; border-radius:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h2 style="margin:0; font-size:18px;">Change Password</h2>
                        <button type="button" class="btn-secondary" onclick="document.getElementById('changePasswordModal').style.display='none'">Close</button>
                    </div>

                    <form method="POST" action="adminusers.php?action=change_password" onsubmit="return confirm('Update password for this user?');">
                        <input type="hidden" name="user_id" id="cp_user_id" />

                        <div class="form-group" style="margin-bottom:12px;">
                            <label for="cp_new_password">New Password <span class="required">*</span></label>
                            <input id="cp_new_password" name="new_password" type="password" class="form-control" required minlength="8" />
                        </div>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label for="cp_confirm_password">Confirm Password <span class="required">*</span></label>
                            <input id="cp_confirm_password" name="confirm_password" type="password" class="form-control" required minlength="8" />
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                            <button type="button" class="btn-secondary" onclick="document.getElementById('changePasswordModal').style.display='none'">Cancel</button>
                            <button type="submit" class="btn-primary"><i class="ph ph-key"></i> Update Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Role Assignment Section -->
            <section class="role-assignment-section">
                <div class="section-header">
                    <h2>Role Assignment</h2>
                    <p class="section-desc">Securely assign or modify user roles. Changes are logged for audit purposes.</p>
                </div>

                <div class="role-assignment-card">
                    <form class="role-assignment-form" id="roleAssignmentForm" method="POST" action="adminusers.php?action=update_user_role">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="userSelect">Select User <span class="required">*</span></label>
                                <select id="userSelect" name="user_id" class="form-control" required>
                                    <option value="">-- Choose a user --</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo (int)$u['user_id']; ?>"><?php echo h($u['first_name'] . ' ' . $u['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="newRole">New Role <span class="required">*</span></label>
                                <select id="newRole" name="new_role_id" class="form-control" required>
                                    <option value="">-- Select role --</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo h($r['role_id']); ?>"><?php echo h($r['role_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="reason">Reason for Change <span class="required">*</span></label>
                                <textarea id="reason" name="reason" class="form-control" rows="2" placeholder="Enter reason for role change..." required></textarea>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary"><i class="ph ph-check"></i> Assign Role</button>
                            <button type="reset" class="btn-secondary"><i class="ph ph-x"></i> Clear</button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Security Controls Section (UI placeholders preserved) -->
            <section class="security-controls-section">
                <div class="section-header">
                    <h2>Security Controls</h2>
                    <p class="section-desc">Emergency actions for user management and security breach response.</p>
                </div>

                <div class="security-grid">
                    <div class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon deactivate-icon"><i class="ph ph-lock"></i></div>
                            <div class="security-info">
                                <h3>Instant User Deactivation</h3>
                                <p>Immediately deactivate account (Emergency mode)</p>
                            </div>
                        </div>
                        <form class="security-form" id="deactivateUserForm" method="POST" action="adminusers.php?action=deactivate_user">
                            <div class="form-group">
                                <label for="deactivateUser">Select User <span class="required">*</span></label>
                                <select id="deactivateUser" name="user_id" class="form-control" required>
                                    <option value="">-- Choose a user --</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo (int)$u['user_id']; ?>"><?php echo h($u['first_name'] . ' ' . $u['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="deactivationReason">Security Incident Note <span class="required">*</span></label>
                                <textarea id="deactivationReason" name="reason" class="form-control" rows="2" placeholder="Document the security breach or reason..." required></textarea>
                            </div>
                            <button type="submit" class="btn-danger" onclick="return confirm('⚠️ WARNING: This will immediately deactivate the user account. Proceed?');"><i class="ph ph-prohibit"></i> Deactivate Account</button>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleMenu() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }

        if (menuToggle) menuToggle.addEventListener('click', toggleMenu);
        if (closeSidebar) closeSidebar.addEventListener('click', toggleMenu);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleMenu);

        // Change Password Modal helpers
        function openChangePasswordModal(userId) {
            const cpUserId = document.getElementById('cp_user_id');
            const modal = document.getElementById('changePasswordModal');
            if (!cpUserId || !modal) return;
            cpUserId.value = userId;
            modal.style.display = 'block';
        }
        
        function openQrCodeModal(userName, qrIdentifier) {
            const modal = document.getElementById('qrCodeDisplayModal');
            if (!modal || !qrIdentifier) return;
            
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrIdentifier)}`;
            document.getElementById('qrUserName').textContent = userName;
            document.getElementById('qrCodeImage').src = qrUrl;
            modal.style.display = 'block';
        }

        // Modal close on outside click
        function wireOutsideClose(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        wireOutsideClose('addUserModal');
        wireOutsideClose('bulkAddModal');
        wireOutsideClose('changePasswordModal');
        wireOutsideClose('qrCodeDisplayModal');

        // Check for new user QR code in URL to display the modal
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('flash') === 'user_added' && urlParams.has('qr')) {
                const userName = urlParams.get('name');
                const qrIdentifier = urlParams.get('qr');
                openQrCodeModal(userName, qrIdentifier);

                history.replaceState(null, '', window.location.pathname);
            }
        });
    </script>
</body>
</html>
