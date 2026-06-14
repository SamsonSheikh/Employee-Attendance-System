<?php
session_start();

// Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$employee_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "Employee");

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard | FlowTime</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/employeedashboard.css">
    <style>
        .punch-clock { text-align: center; margin: 2rem 0; padding: 3rem; background-color: var(--white); border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .btn-massive { font-size: 1.5rem; padding: 1.5rem 0; border-radius: 12px; font-weight: bold; border: none; cursor: pointer; transition: transform 0.1s; width: 100%; max-width: 300px; display: inline-block; margin: 1rem; }
        .btn-massive:active { transform: scale(0.98); }
        .btn-clock-in { background-color: #10b981; color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); }
        .btn-clock-out { background-color: #ef4444; color: white; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); }
        .btn-massive:disabled { background-color: #d1d5db; box-shadow: none; cursor: not-allowed; color: #6b7280; }
        .status-indicator { font-size: 1.25rem; font-weight: 600; padding: 0.75rem 2rem; border-radius: 999px; display: inline-block; margin-bottom: 2rem; }
        .status-clocked-in { background-color: #d1fae5; color: #065f46; border: 2px solid #34d399; }
        .status-clocked-out { background-color: #fee2e2; color: #991b1b; border: 2px solid #f87171; }
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
                <h1>Welcome back, <?php echo htmlspecialchars($employee_name); ?>!</h1>
                <p class="subtitle">This is your personal employee portal. Access your attendance and schedule here.</p>
            </header>

            <?php if ($message): ?><div style="padding: 1rem; background: #e0f2fe; color: #0369a1; border-radius: 0.5rem; margin-bottom: 1.5rem;"><strong><?= htmlspecialchars($message) ?></strong></div><?php endif; ?>
            
            <div class="punch-clock">
                <div class="status-indicator <?= $status_class; ?>"><?= $status_text; ?></div>
                <form method="POST">
                    <button type="submit" name="clock_in" class="btn-massive btn-clock-in" <?= ($is_clocked_in || $is_clocked_out) ? 'disabled' : ''; ?>>CLOCK IN</button>
                    <button type="submit" name="clock_out" class="btn-massive btn-clock-out" <?= (!$is_clocked_in || $is_clocked_out) ? 'disabled' : ''; ?>>CLOCK OUT</button>
                </form>
            </div>

            <section class="features-grid">
                <div class="feature-card" onclick="location.href='empattendance.php'">
                    <div class="feature-icon"><i class="ph ph-calendar-check"></i></div>
                    <h3>My Attendance</h3>
                    <p>View your daily clock-ins and clock-outs.</p>
                </div>
                <div class="feature-card" onclick="location.href='empleaves.php'">
                    <div class="feature-icon"><i class="ph ph-coffee"></i></div>
                    <h3>Leave Requests</h3>
                    <p>Apply for PTO, sick leave, or vacation.</p>
                </div>
                <div class="feature-card" onclick="location.href='#'">
                    <div class="feature-icon"><i class="ph ph-clock"></i></div>
                    <h3>My Schedule</h3>
                    <p>Check your upcoming assigned shifts.</p>
                </div>
                <div class="feature-card" onclick="location.href='empprofile.php'">
                    <div class="feature-icon"><i class="ph ph-user"></i></div>
                    <h3>Profile Details</h3>
                    <p>Update your personal information.</p>
                </div>
            </section>
        </main>
</body>
</html>