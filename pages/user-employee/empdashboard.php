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

$message = "";

// Handle Clock In / Clock Out
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date_today = date('Y-m-d');
    $time_now = date('Y-m-d H:i:s');
    
    // Fetch the current log for today to decide action
    $stmt_check = $conn->prepare("SELECT * FROM attendance_logs WHERE user_id = ? AND log_date = ?");
    $stmt_check->bind_param("is", $user_id, $date_today);
    $stmt_check->execute();
    $today_log = $stmt_check->get_result()->fetch_assoc();

    if (isset($_POST['clock_in'])) {
        if (!$today_log) {
            // Morning Clock In: No record for today, so create one.
            $stmt = $conn->prepare("INSERT INTO attendance_logs (user_id, log_date, morning_clock_in, status) VALUES (?, ?, ?, 'On Time')");
            $stmt->bind_param("iss", $user_id, $date_today, $time_now);
            if ($stmt->execute()) {
                $message = "Morning clock-in successful at " . date('h:i A');
            }
        } elseif (!empty($today_log['morning_clock_out']) && empty($today_log['afternoon_clock_in'])) {
            // Afternoon Clock In: Morning is done, now clocking in for afternoon.
            $stmt = $conn->prepare("UPDATE attendance_logs SET afternoon_clock_in = ? WHERE log_id = ?");
            $stmt->bind_param("si", $time_now, $today_log['log_id']);
            if ($stmt->execute()) {
                $message = "Afternoon clock-in successful at " . date('h:i A');
            }
        } else {
            $message = "Clock-in action is not available at this time.";
        }
    } elseif (isset($_POST['clock_out'])) {
        if ($today_log) {
            if (!empty($today_log['morning_clock_in']) && empty($today_log['morning_clock_out'])) {
                // Morning Clock Out
                $stmt = $conn->prepare("UPDATE attendance_logs SET morning_clock_out = ? WHERE log_id = ?");
                $stmt->bind_param("si", $time_now, $today_log['log_id']);
                if ($stmt->execute()) {
                    $message = "Morning clock-out successful at " . date('h:i A');
                }
            } elseif (!empty($today_log['afternoon_clock_in']) && empty($today_log['afternoon_clock_out'])) {
                // Afternoon Clock Out
                $stmt = $conn->prepare("UPDATE attendance_logs SET afternoon_clock_out = ? WHERE log_id = ?");
                $stmt->bind_param("si", $time_now, $today_log['log_id']);
                if ($stmt->execute()) {
                    $message = "Afternoon clock-out successful. Shift completed.";
                }
            } else {
                $message = "Clock-out action is not available at this time.";
            }
        } else {
            $message = "Cannot clock out without clocking in first.";
        }
    }
}

// Check Current Status
$date_today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM attendance_logs WHERE user_id = ? AND log_date = ?");
$stmt->bind_param("is", $user_id, $date_today);
$stmt->execute();
$result = $stmt->get_result();
$log = $result->fetch_assoc();

$can_clock_in = false;
$can_clock_out = false;

if (!$log) {
    // Not clocked in at all
    $status_text = 'Not Clocked In';
    $status_class = 'status-clocked-out';
    $can_clock_in = true;
} elseif (empty($log['morning_clock_out'])) {
    // Clocked in for the morning
    $status_text = 'Currently: CLOCKED IN (Morning)';
    $status_class = 'status-clocked-in';
    $can_clock_out = true;
} elseif (empty($log['afternoon_clock_in'])) {
    // On lunch break
    $status_text = 'On Lunch Break';
    $status_class = 'status-clocked-out';
    $can_clock_in = true;
} elseif (empty($log['afternoon_clock_out'])) {
    // Clocked in for the afternoon
    $status_text = 'Currently: CLOCKED IN (Afternoon)';
    $status_class = 'status-clocked-in';
    $can_clock_out = true;
} else {
    // Day is complete
    $status_text = 'Shift Completed for Today';
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
        /* Live Clock Styles */
        .live-clock-wrapper {
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem 2rem;
            margin: 0 auto 2rem auto;
            max-width: 400px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }
        .live-clock-time {
            font-family: 'ui-monospace', 'SFMono-Regular', Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 3rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }
        .live-clock-date {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
            font-weight: 500;
        }
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
                <div class="live-clock-wrapper">
                    <div id="live-clock-time"></div>
                    <div id="live-clock-date"></div>
                </div>
                <div class="status-indicator <?= $status_class; ?>"><?= $status_text; ?></div>
                <form method="POST" id="clockForm">
                    <button type="button" id="clockInBtn" class="btn-massive btn-clock-in" <?= !$can_clock_in ? 'disabled' : ''; ?>>CLOCK IN</button>
                    <button type="button" id="clockOutBtn" class="btn-massive btn-clock-out" <?= !$can_clock_out ? 'disabled' : ''; ?>>CLOCK OUT</button>
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

    // --- Live Clock Logic ---
    function updateClock() {
        const now = new Date();
        
        // Format Time
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // Hour '0' should be '12'
        const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
        
        // Format Date
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const dateString = now.toLocaleDateString('en-US', options);

        document.getElementById('live-clock-time').textContent = timeString;
        document.getElementById('live-clock-date').textContent = dateString;
    }
    updateClock(); // Initial call to display clock immediately
    setInterval(updateClock, 1000); // Update every second
</script>
</body>
</html>