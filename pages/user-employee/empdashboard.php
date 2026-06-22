<?php
session_start();

// Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$employee_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "Employee");

// Fetch user's QR identifier
require_once '../../includes/db_connect.php';
$stmt_user_data = $conn->prepare("SELECT qr_identifier FROM users WHERE user_id = ?");
$stmt_user_data->bind_param("i", $user_id);
$stmt_user_data->execute();
$user_data = $stmt_user_data->get_result()->fetch_assoc();
$user_qr_identifier = $user_data['qr_identifier'] ?? '';
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
        // Check for clock-in record to apply restrictions
        $stmt_check = $conn->prepare("SELECT clock_in FROM attendance_logs WHERE user_id = ? AND log_date = ? AND clock_out IS NULL");
        $stmt_check->bind_param("is", $user_id, $date_today);
        $stmt_check->execute();
        $log_check = $stmt_check->get_result()->fetch_assoc();

        if ($log_check) {
            $clock_in_time = new DateTime($log_check['clock_in']);
            $current_time = new DateTime($time_now);
            $interval_seconds = $current_time->getTimestamp() - $clock_in_time->getTimestamp();

            if ($interval_seconds < 60) { // Time out restriction: 1 minute
                $message = "Clock-out restricted. You must be clocked in for at least 1 minute.";
            } else {
                $stmt_update = $conn->prepare("UPDATE attendance_logs SET clock_out = ? WHERE user_id = ? AND log_date = ? AND clock_out IS NULL");
                $stmt_update->bind_param("sis", $time_now, $user_id, $date_today);
                $stmt_update->execute();
                if ($stmt_update->affected_rows > 0) {
                    $message = "Clocked out successfully at " . date('h:i A');
                }
            }
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
    <style>
        /* QR Scanner Modal Styles */
        #qr-scanner-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none; /* Hidden by default */
            justify-content: center;
            align-items: center;
            flex-direction: column;
            z-index: 1000;
        }
        #qr-scanner-modal-content {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 450px;
            text-align: center;
        }
        #qr-reader {
            border: 1px solid #eee;
            border-radius: 8px;
        }
        #cancel-scan-btn { margin-top: 15px; padding: 10px 20px; border-radius: 5px; border: none; background-color: #718096; color: white; cursor: pointer; font-weight: bold; }
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
                <form method="POST" id="clockForm">
                    <button type="button" id="clockInBtn" class="btn-massive btn-clock-in" <?= ($is_clocked_in || $is_clocked_out) ? 'disabled' : ''; ?>>CLOCK IN</button>
                    <button type="button" id="clockOutBtn" class="btn-massive btn-clock-out" <?= (!$is_clocked_in || $is_clocked_out) ? 'disabled' : ''; ?>>CLOCK OUT</button>
                </form>
            </div>

            <section class="features-grid">
                <div class="feature-card" onclick="location.href='empattendance.php'">
                    <div class="feature-icon"><i class="ph ph-calendar-check"></i></div>
                    <h3>My Attendance</h3>
                    <p>View your daily clock-ins and clock-outs.</p>
                </div>
                <div class="feature-card" onclick="location.href='#'">
                    <div class="feature-icon"><i class="ph ph-clock"></i></div>
                    <h3>My Schedule</h3>
                    <p>Check your upcoming assigned shifts.</p>
                </div>
            </section>
        </main>

    <!-- QR Scanner Modal -->
    <div id="qr-scanner-modal">
        <div id="qr-scanner-modal-content">
            <h4>Scan Your QR Code to Verify</h4>
            <div id="qr-reader" style="margin-top: 15px;"></div>
            <button id="cancel-scan-btn">Cancel</button>
        </div>
    </div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
    const userQRIdentifier = <?php echo json_encode($user_qr_identifier); ?>;
    const clockForm = document.getElementById('clockForm');
    const scannerModal = document.getElementById('qr-scanner-modal');
    let html5QrcodeScanner;

    function onScanSuccess(decodedText, decodedResult) {
        html5QrcodeScanner.clear();
        scannerModal.style.display = 'none';

        if (decodedText === userQRIdentifier) {
            // QR code is correct, submit the form
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = window.currentClockAction; // 'clock_in' or 'clock_out'
            hiddenInput.value = '1';
            clockForm.appendChild(hiddenInput);
            clockForm.submit();
        } else {
            alert('Invalid QR Code. Verification failed. Please try again.');
        }
    }

    function startScanner(action) {
        if (!userQRIdentifier) {
            alert('Error: QR Identifier not set for your account. Please contact an administrator.');
            return;
        }
        window.currentClockAction = action;
        scannerModal.style.display = 'flex';
        html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", { fps: 10, qrbox: 250 });
        html5QrcodeScanner.render(onScanSuccess);
    }

    document.getElementById('clockInBtn')?.addEventListener('click', () => startScanner('clock_in'));
    document.getElementById('clockOutBtn')?.addEventListener('click', () => startScanner('clock_out'));

    document.getElementById('cancel-scan-btn')?.addEventListener('click', () => {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear().catch(err => console.warn("Scanner already cleared or failed to clear:", err));
        }
        scannerModal.style.display = 'none';
    });
</script>
</body>
</html>