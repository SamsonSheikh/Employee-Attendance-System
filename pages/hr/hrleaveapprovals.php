<?php
session_start();

// Security Check
if (!isset($_SESSION['user_id'])) {
    // header("Location: login.php");
    // exit();
}

require_once '../../includes/db_connect.php';

// Fetch HR User's Name
$hr_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : "Kevin";

// ==========================================================
// HANDLE APPROVE / DENY ACTIONS
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action']; // 'Approved' or 'Denied'

    if (in_array($action, ['Approved', 'Denied'])) {
        $update_stmt = $conn->prepare("UPDATE leave_requests SET approval_status = ? WHERE request_id = ?");
        $update_stmt->bind_param("si", $action, $request_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Redirect to prevent form resubmission on refresh
        header("Location: hrleaveapprovals.php");
        exit();
    }
}

// ==========================================================
// FETCH LEAVE REQUESTS
// ==========================================================
// Query for Pending Leaves
$pending_query = "
    SELECT lr.*, u.first_name, u.last_name, lt.leave_name 
    FROM leave_requests lr 
    JOIN users u ON lr.user_id = u.user_id 
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id 
    WHERE lr.approval_status = 'Pending' 
    ORDER BY lr.created_at ASC
";
$pending_result = $conn->query($pending_query);

// Query for Leave History (Approved or Denied)
$history_query = "
    SELECT lr.*, u.first_name, u.last_name, lt.leave_name 
    FROM leave_requests lr 
    JOIN users u ON lr.user_id = u.user_id 
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id 
    WHERE lr.approval_status != 'Pending' 
    ORDER BY lr.created_at DESC 
    LIMIT 50
";
$history_result = $conn->query($history_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approvals | FlowTime</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hrleaveapprovals.css">
</head>
<body>

    <header class="mobile-header">
        <div class="sidebar-brand">
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

            <div class="sidebar-brand desktop-brand">
                <span class="brand-icon"><i class="ph-fill ph-clock-user"></i></span>
                <span class="brand-text">FlowTime</span>
            </div>

            <div class="sidebar-menu-wrapper">
                <ul class="sidebar-links">
                    <li><a href="../../pages/hr/hrdashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li><a href="../../pages/hr/hrattendance.php"><i class="ph ph-user-focus"></i> Attendance</a></li>
                    <li class="active"><a href="../../pages/hr/hrleaveapprovals.php"><i class="ph ph-calendar-check"></i> Leave Approvals</a></li>
                    <li><a href="../../pages/hr/hremployees.php"><i class="ph ph-users"></i> Employees</a></li>
                    <li><a href="../../pages/hr/hrsettings.php"><i class="ph ph-gear"></i> Settings</a></li>
                </ul>
            </div>
            <div class="sidebar-footer"></div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>Leave Approvals</h1>
                <p class="subtitle">Review and manage employee time-off requests.</p>
            </header>

            <div class="tab-navigation">
                <button class="tab-btn active" onclick="switchTab('pending')">Pending Requests</button>
                <button class="tab-btn" onclick="switchTab('history')">Leave History</button>
            </div>

            <section id="tab-pending" class="tab-content active">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Dates Requested</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pending_result && $pending_result->num_rows > 0): ?>
                                <?php while($row = $pending_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="emp-name">
                                            <div class="avatar-sm">
                                                <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </td>
                                        <td><span class="type-badge"><?php echo htmlspecialchars($row['leave_name']); ?></span></td>
                                        <td>
                                            <?php echo date('M d', strtotime($row['start_date'])) . ' - ' . date('M d, Y', strtotime($row['end_date'])); ?>
                                        </td>
                                        <td class="reason-cell" title="<?php echo htmlspecialchars($row['reason']); ?>">
                                            <?php echo htmlspecialchars($row['reason'] ? $row['reason'] : 'No reason provided'); ?>
                                        </td>
                                        <td><?php echo date('M d, g:i A', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                                <button type="submit" name="action" value="Approved" class="btn-icon approve" title="Approve">
                                                    <i class="ph ph-check-circle"></i>
                                                </button>
                                                <button type="submit" name="action" value="Denied" class="btn-icon deny" title="Deny">
                                                    <i class="ph ph-x-circle"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="ph ph-coffee"></i>
                                        <p>You're all caught up! No pending leave requests.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="tab-history" class="tab-content">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Dates Requested</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history_result && $history_result->num_rows > 0): ?>
                                <?php while($row = $history_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="emp-name">
                                            <div class="avatar-sm">
                                                <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </td>
                                        <td><span class="type-badge"><?php echo htmlspecialchars($row['leave_name']); ?></span></td>
                                        <td>
                                            <?php echo date('M d', strtotime($row['start_date'])) . ' - ' . date('M d, Y', strtotime($row['end_date'])); ?>
                                        </td>
                                        <td>
                                            <?php $statusClass = strtolower($row['approval_status']); ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($row['approval_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        <i class="ph ph-folder-open"></i>
                                        <p>No leave history found.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <script>
        // Sidebar Toggle Logic
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

        // Tab Switcher Logic
        function switchTab(tabId) {
            // Hide all contents and remove active class from buttons
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            // Show target content and add active class to clicked button
            document.getElementById('tab-' + tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>