<?php
session_start();

// Security Check (Mocked for testing, uncomment header redirection for production)
if (!isset($_SESSION['user_id'])) {
    // header("Location: ../public/login.php");
    // exit();
    $_SESSION['user_id'] = 1; // Temporary mock user_id for demo purposes
}

$user_id = $_SESSION['user_id'];
require_once '../../includes/db_connect.php';

$message = "";

// Handle Clock In / Clock Out
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date_today = date('Y-m-d');
    $time_now = date('Y-m-d H:i:s');

    if (isset($_POST['clock_in'])) {
        $stmt = $conn->prepare("INSERT IGNORE INTO attendance_logs (user_id, log_date, clock_in, status) VALUES (?, ?, ?, 'On Time')");
        $stmt->bind_param("iss", $user_id, $date_today, $time_now);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $message = "Clocked in successfully at " . date('h:i A');
        } else {
            $message = "You have already clocked in today.";
        }
    } elseif (isset($_POST['clock_out'])) {
        $stmt = $conn->prepare("UPDATE attendance_logs SET clock_out = ? WHERE user_id = ? AND log_date = ? AND clock_out IS NULL");
        $stmt->bind_param("sis", $time_now, $user_id, $date_today);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $message = "Clocked out successfully at " . date('h:i A');
        } else {
            $message = "You haven't clocked in yet, or you already clocked out.";
        }
    }
}

// Check Current Status
$date_today = date('Y-m-d');
$stmt = $conn->prepare("SELECT clock_in, clock_out FROM attendance_logs WHERE user_id = ? AND log_date = ?");
$stmt->bind_param("is", $user_id, $date_today);
$stmt->execute();
$result = $stmt->get_result();
$log = $result->fetch_assoc();

$is_clocked_in = $log && !empty($log['clock_in']) && empty($log['clock_out']);
$is_clocked_out = $log && !empty($log['clock_out']);

if ($is_clocked_in) {
    $status_text = 'Currently: CLOCKED IN';
    $status_class = 'status-clocked-in';
} elseif ($is_clocked_out) {
    $status_text = 'Shift Completed (Clocked Out)';
    $status_class = 'status-clocked-out';
} else {
    $status_text = 'Not Clocked In';
    $status_class = 'status-clocked-out';
}

// Quick Stats
$stmt_leaves = $conn->prepare("SELECT COUNT(*) as approved_leaves FROM leave_requests WHERE user_id = ? AND approval_status = 'Approved' AND start_date >= CURDATE()");
$stmt_leaves->bind_param("i", $user_id);
$stmt_leaves->execute();
$leaves_stats = $stmt_leaves->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hrdashboard.css">
    <style>
        .punch-clock { text-align: center; margin: 3rem 0; padding: 3rem; }
        .btn-massive { font-size: 2rem; padding: 2rem 0; border-radius: 12px; font-weight: bold; border: none; cursor: pointer; transition: transform 0.1s; width: 100%; max-width: 350px; display: inline-block; margin: 1rem; }
        .btn-massive:active { transform: scale(0.98); }
        .btn-clock-in { background-color: #10b981; color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); }
        .btn-clock-out { background-color: #ef4444; color: white; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); }
        .btn-massive:disabled { background-color: #d1d5db; box-shadow: none; cursor: not-allowed; color: #6b7280; }
        .status-indicator { font-size: 1.25rem; font-weight: 600; padding: 0.75rem 2rem; border-radius: 999px; display: inline-block; margin-bottom: 2rem; }
        .status-clocked-in { background-color: #d1fae5; color: #065f46; border: 2px solid #34d399; }
        .status-clocked-out { background-color: #fee2e2; color: #991b1b; border: 2px solid #f87171; }
        .card { background-color: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02); }
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
                <li class="active"><a href="empdashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                <li><a href="empattendance.php"><i class="ph ph-clock"></i> My Timesheets</a></li>
                <li><a href="empleaves.php"><i class="ph ph-calendar-blank"></i> Time-Off Requests</a></li>
                <li><a href="empprofile.php"><i class="ph ph-user"></i> My Profile</a></li>
                <li><a href="../public/logout.php" style="color: var(--primary-color);"><i class="ph ph-sign-out"></i> Logout</a></li>
            </ul>
        </aside>
        <main class="content">
            <header class="page-header">
                <h1>Web Timeclock</h1>
            </header>
            <?php if ($message): ?><div style="padding: 1rem; background: #e0f2fe; color: #0369a1; border-radius: 0.5rem; margin-bottom: 1.5rem;"><strong><?= htmlspecialchars($message) ?></strong></div><?php endif; ?>
            
            <div class="card punch-clock">
                <div class="status-indicator <?= $status_class; ?>"><?= $status_text; ?></div>
                <form method="POST">
                    <button type="submit" name="clock_in" class="btn-massive btn-clock-in" <?= ($is_clocked_in || $is_clocked_out) ? 'disabled' : ''; ?>>CLOCK IN</button>
                    <button type="submit" name="clock_out" class="btn-massive btn-clock-out" <?= (!$is_clocked_in || $is_clocked_out) ? 'disabled' : ''; ?>>CLOCK OUT</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>