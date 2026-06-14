<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
$user_id = $_SESSION['user_id'];
require_once '../../includes/db_connect.php';

$message = "";

// Process Password Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass === $confirm_pass) {
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $message = "Your password has been securely updated.";
        } else {
            $message = "Error updating your password. Please try again.";
        }
    } else {
        $message = "Passwords do not match. Try again.";
    }
}

// Retrieve Profile Information
$stmt_user = $conn->prepare("
    SELECT u.first_name, u.last_name, u.email, d.department_name, r.role_name, s.shift_name, s.start_time, s.end_time 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.department_id 
    LEFT JOIN roles r ON u.role_id = r.role_id 
    LEFT JOIN shifts s ON u.shift_id = s.shift_id 
    WHERE u.user_id = ?
");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_info = $stmt_user->get_result()->fetch_assoc();
if (!$user_info) {
    $user_info = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/employeedashboard.css">
    <style>
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; }
        .card { background-color: var(--bg-card); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .card h2 { font-size: 1.25rem; margin-bottom: 1.5rem; color: var(--text-main); }
        .card p { margin-bottom: 0.75rem; color: var(--text-main); font-size: 0.95rem; }
        .card p strong { color: var(--text-muted); font-weight: 500; display: inline-block; width: 120px; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; margin-top: 0.5rem; margin-bottom: 1rem; color: var(--text-main); background: var(--bg-card); }
        .form-group label { font-weight: 500; font-size: 0.9rem; color: var(--text-main); }
        .btn-primary { background-color: var(--primary-color); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; cursor: pointer; font-weight: 500; width: 100%; }
        .btn-primary:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <header class="dashboard-header">
        <a href="empdashboard.php" class="logo">
            <i class="ph-fill ph-clock-user"></i>
            FlowTime
        </a>
        <a href="../public/logout.php" class="logout-btn"><i class="ph ph-sign-out"></i> Logout</a>
    </header>

        <main class="content">
            <header class="page-header">
                <h1>My Profile & Security</h1>
            </header>
            <?php if ($message): ?><div style="padding: 1rem; background: #e0f2fe; color: #0369a1; border-radius: 0.5rem; margin-bottom: 1.5rem;"><strong><?= htmlspecialchars($message) ?></strong></div><?php endif; ?>
            
            <div class="card-grid">
                <div class="card">
                    <h2>Account Information</h2>
                    <p><strong>Name:</strong> <?= htmlspecialchars(($user_info['first_name'] ?? 'Unknown') . ' ' . ($user_info['last_name'] ?? 'User')) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user_info['email'] ?? 'N/A') ?></p>
                    <p><strong>Role:</strong> <?= htmlspecialchars($user_info['role_name'] ?? 'Not Assigned') ?></p>
                    <p><strong>Department:</strong> <?= htmlspecialchars($user_info['department_name'] ?? 'Not Assigned') ?></p>
                    <p><strong>Shift Assignment:</strong> <?= htmlspecialchars($user_info['shift_name'] ?? 'No Shift') ?> (<?= htmlspecialchars($user_info['start_time'] ?? '') ?> - <?= htmlspecialchars($user_info['end_time'] ?? '') ?>)</p>
                </div>

                <div class="card">
                    <h2>Change Password</h2>
                    <form method="POST">
                        <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" required></div>
                        <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                        <button type="submit" name="update_password" class="btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </main>
</body>
</html>