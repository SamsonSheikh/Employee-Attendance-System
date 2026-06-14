<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
$user_id = $_SESSION['user_id'];
require_once '../../includes/db_connect.php';

$message = "";

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_leave'])) {
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];

    if (strtotime($end_date) < strtotime($start_date)) {
        $message = "Error: End date cannot be before start date.";
    } else {
        $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, leave_type_id, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $user_id, $leave_type_id, $start_date, $end_date, $reason);
        
        if ($stmt->execute()) {
            $message = "Leave request successfully submitted and is now pending approval.";
        } else {
            $message = "Failed to submit leave request.";
        }
    }
}

// Fetch types & history
$leave_types = $conn->query("SELECT * FROM leave_types");

$stmt_past = $conn->prepare("SELECT lr.*, lt.leave_name FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.user_id = ? ORDER BY lr.created_at DESC");
$stmt_past->bind_param("i", $user_id);
$stmt_past->execute();
$past_requests = $stmt_past->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Time-Off Requests</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hrdashboard.css">
    <style>
        .history-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .history-table th, .history-table td { padding: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.9rem; }
        .history-table th { color: var(--text-muted); font-weight: 500; }
        .badge { padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: bold; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-denied { background: #fee2e2; color: #991b1b; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; }
        .card { background-color: var(--bg-card); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .card h2 { font-size: 1.25rem; margin-bottom: 1.5rem; color: var(--text-main); }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; margin-top: 0.5rem; margin-bottom: 1rem; color: var(--text-main); background: var(--bg-card); }
        .form-group label { font-weight: 500; font-size: 0.9rem; color: var(--text-main); }
        .btn-primary { background-color: var(--primary-color); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; cursor: pointer; font-weight: 500; width: 100%; }
        .btn-primary:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <div class="logo">
            <span class="logo-icon"><i class="ph-fill ph-person-simple-walk"></i></span>
            <span class="logo-text">Vizitor</span>
        </div>
        <ul class="nav-links">
            <li><span class="active">Employee Portal</span></li>
        </ul>
        <div class="user-controls">
            <div class="user-profile">
                <img src="https://ui-avatars.com/api/?name=Employee&background=random" alt="User Avatar">
            </div>
        </div>
    </nav>

    <div class="main-wrapper">
        <aside class="sidebar">
            <ul class="sidebar-links">
                <li><a href="empdashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                <li><a href="empattendance.php"><i class="ph ph-clock"></i> My Timesheets</a></li>
                <li class="active"><a href="empleaves.php"><i class="ph ph-calendar-blank"></i> Time-Off Requests</a></li>
                <li><a href="empprofile.php"><i class="ph ph-user"></i> My Profile</a></li>
                <li><a href="../public/logout.php" style="color: var(--primary-color);"><i class="ph ph-sign-out"></i> Logout</a></li>
            </ul>
        </aside>
        <main class="content">
            <header class="page-header">
                <h1>Time-Off Requests</h1>
            </header>
            <?php if ($message): ?><div style="padding: 1rem; background: #e0f2fe; color: #0369a1; border-radius: 0.5rem; margin-bottom: 1.5rem;"><strong><?= htmlspecialchars($message) ?></strong></div><?php endif; ?>
            
            <div class="card-grid">
                <div class="card">
                    <h2>Apply for Leave</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Leave Type</label>
                            <select name="leave_type_id" class="form-control" required>
                                <option value="">Select a type...</option>
                                <?php while($lt = $leave_types->fetch_assoc()): ?>
                                    <option value="<?= $lt['leave_type_id'] ?>"><?= htmlspecialchars($lt['leave_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" required></div>
                        <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" required></div>
                        <div class="form-group"><label>Reason</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
                        <button type="submit" name="submit_leave" class="btn-primary">Submit Request</button>
                    </form>
                </div>

                <div class="card">
                    <h2>My Requests Tracker</h2>
                    <table class="history-table">
                        <tr><th>Type</th><th>Dates</th><th>Status</th></tr>
                        <?php if ($past_requests->num_rows > 0): ?>
                            <?php while ($req = $past_requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['leave_name']) ?></td>
                                    <td><?= htmlspecialchars($req['start_date']) ?> to <?= htmlspecialchars($req['end_date']) ?></td>
                                    <td><span class="badge badge-<?= strtolower($req['approval_status']) ?>"><?= htmlspecialchars($req['approval_status']) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3">You have not submitted any leave requests yet.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>