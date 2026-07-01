<?php
// Include the authentication function from the same directory
require_once __DIR__ . '/functions.php'; // Change extension to .php if you rename it!
require_once __DIR__ . '/../../includes/db_connect.php';
check_admin_login($conn);
// Get admin's name
$admin_name = isset($_SESSION["username"]) ? $_SESSION["username"] : "Admin";

// Dashboard counters from the existing database tables
$total_users = 0;
$active_users = 0;
$department_total = 0;

$total_users_result = $conn->query("SELECT COUNT(user_id) AS total_users FROM users");
if ($total_users_result && $total_users_result->num_rows > 0) {
    $total_users = (int) $total_users_result->fetch_assoc()['total_users'];
}

$department_result = $conn->query("SELECT COUNT(department_id) AS total_departments FROM departments");
if ($department_result && $department_result->num_rows > 0) {
    $department_total = (int) $department_result->fetch_assoc()['total_departments'];
}

// Active users are now defined as users who have clocked in today.
$active_users_result = $conn->query("SELECT COUNT(DISTINCT user_id) AS active_today FROM attendance_logs WHERE log_date = CURDATE() AND (morning_clock_in IS NOT NULL OR afternoon_clock_in IS NOT NULL)");
if ($active_users_result && $active_users_result->num_rows > 0) {
    $active_users = (int) $active_users_result->fetch_assoc()['active_today'];
}

// =========================
// RECENT LOGIN ACTIVITY
// =========================
$recent_logins = [];
$login_activity_error = null;
try {
    $login_activity_result = $conn->query("
        SELECT la.login_time, la.ip_address, u.first_name, u.last_name, u.email
        FROM login_activity la
        JOIN users u ON la.user_id = u.user_id
        WHERE la.status = 'success'
        ORDER BY la.login_time DESC
        LIMIT 10
    ");
    if ($login_activity_result) {
        while ($row = $login_activity_result->fetch_assoc()) {
            $recent_logins[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    $login_activity_error = "Could not fetch login activity. The 'login_activity' table might be missing.";
}

// =========================
// FETCH DATA FOR FORMS
// =========================
$users = [];
$users_result = $conn->query("SELECT user_id, first_name, last_name FROM users ORDER BY first_name ASC, last_name ASC");
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | EAS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        /* Fix for Recent Login Activity Table */
        .activity-table { width: 100%; border-collapse: collapse; }
        .activity-table th, .activity-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .activity-table th { background-color: #f8fafc; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; }
        .activity-table .user-name { font-weight: 600; display: block; }
        .activity-table .user-email { font-size: 0.85rem; color: var(--text-muted); }
    </style>
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
                    <li class="active"><a href="../../pages/user-admin/admin_dashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li><a href="../../pages/user-admin/adminusers.php"><i class="ph ph-users"></i> Master Users</a></li>
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
                <h1>Welcome back <?php echo htmlspecialchars($admin_name); ?>!</h1>
            </header>

            <section class="stats-grid">
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars((string) $total_users); ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-user-check"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars((string) $active_users); ?></h3>
                        <p>Active Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-building"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars((string) $department_total); ?></h3>
                        <p>Departments</p>
                    </div>
                </div>

            </section>

            <section class="data-grid" style="grid-template-columns: 1fr;">
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2>Recent Login Activity</h2>
                            <p class="section-desc">Showing the last 10 successful logins to the system.</p>
                        </div>
                    </div>
                    <div class="table-container" style="margin-top: 1rem;">
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Login Time</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($login_activity_error): ?>
                                    <tr><td colspan="3" style="text-align: center; color: #ef4444; padding: 2rem;"><?php echo htmlspecialchars($login_activity_error); ?></td></tr>
                                <?php elseif (empty($recent_logins)): ?>
                                    <tr><td colspan="3" style="text-align: center; color: #718096; padding: 2rem;">No recent login activity found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_logins as $login): ?>
                                    <tr>
                                        <td>
                                            <span class="user-name"><?php echo htmlspecialchars($login['first_name'] . ' ' . $login['last_name']); ?></span>
                                            <span class="user-email"><?php echo htmlspecialchars($login['email']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('M d, Y, h:i A', strtotime($login['login_time']))); ?></td>
                                        <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Security Controls Section -->
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
                                    <h3>Terminate All Login Sessions</h3>
                                    <p>Immediately log out all users from the system.</p>
                                </div>
                            </div>
                            <form class="security-form" id="terminateSessionsForm" method="POST" action="terminate_sessions.php">
                                <div class="form-group">
                                    <label for="confirmationText">Confirmation <span class="required">*</span></label>
                                    <input type="text" id="confirmationText" name="confirmation" class="form-control" placeholder="Type TERMINATE to confirm" required>
                                    <small style="display:block; margin-top:6px; color:#666;">This will force every user, including yourself, to log in again.</small>
                                </div>
                                <button type="submit" class="btn-danger" onclick="return confirm('⚠️ WARNING: This will immediately terminate all active login sessions. Proceed?');">
                                    <i class="ph ph-prohibit"></i> Terminate All Sessions
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
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

        menuToggle.addEventListener('click', toggleMenu);
        closeSidebar.addEventListener('click', toggleMenu);
        sidebarOverlay.addEventListener('click', toggleMenu);
    </script>
</body>
</html>