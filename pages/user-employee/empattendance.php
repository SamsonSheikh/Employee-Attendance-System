<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
require_once '../../includes/db_connect.php';

$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Fetch strictly this user's logs
$stmt = $conn->prepare("SELECT log_date, clock_in, clock_out, status FROM attendance_logs WHERE user_id = ? AND DATE_FORMAT(log_date, '%Y-%m') = ? ORDER BY log_date DESC");
$stmt->bind_param("is", $user_id, $month_filter);
$stmt->execute();
$attendance = $stmt->get_result();

$total_seconds = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Timesheets</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/employeedashboard.css">
    <style>
        .table-container { background: var(--bg-card); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02); border: 1px solid var(--border-color); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95rem; }
        th, td { padding: 1rem; border-bottom: 1px solid var(--border-color); }
        th { background-color: var(--bg-main); font-weight: 600; color: var(--text-main); }
        .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 500; }
        .badge-ontime { background: #d1fae5; color: #065f46; }
        .badge-late { background: #fef3c7; color: #92400e; }
        .badge-absent { background: #fee2e2; color: #991b1b; }
        .badge-halfday { background: #e0e7ff; color: #3730a3; }
        .filter-form { margin-bottom: 1.5rem; }
        .form-control { padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); }
        .summary-box { 
            background: var(--secondary-color); 
            border: 1px solid var(--border-color); 
            padding: 1rem 1.5rem; 
            border-radius: 12px; 
            margin-bottom: 1.5rem; 
        }
        .summary-box h3 { 
            margin: 0 0 0.5rem 0; 
            font-size: 1.1rem; 
            color: var(--text-main); 
        }
        .summary-box p { 
            margin: 0; 
            font-size: 1rem; 
            color: var(--text-muted); 
        }
        .summary-box p strong { 
            color: var(--primary-color); 
            font-weight: 600; 
        }
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
                <h1>My Timesheets</h1>
            </header>

            <?php
            // Pre-calculate total hours before displaying the table
            $temp_attendance = [];
            while ($row = $attendance->fetch_assoc()) {
                $temp_attendance[] = $row;
                if ($row['clock_in'] && $row['clock_out']) {
                    $total_seconds += strtotime($row['clock_out']) - strtotime($row['clock_in']);
                }
            }
            $total_hours = floor($total_seconds / 3600);
            $total_minutes = floor(($total_seconds / 60) % 60);
            $total_duration_display = sprintf('%d hrs %d mins', $total_hours, $total_minutes);
            ?>
            
            <div class="table-container">
                <form method="GET" class="filter-form">
                    <label style="font-weight: bold; margin-right: 10px;">Select Month:</label>
                    <input type="month" name="month" class="form-control" style="width: 200px; display: inline-block;" value="<?= htmlspecialchars($month_filter) ?>" onchange="this.form.submit()">
                </form>
                
                <div class="summary-box">
                    <h3>Month Summary for <?= date('F Y', strtotime($month_filter)) ?></h3>
                    <p>Total Work Hours: <strong><?= htmlspecialchars($total_duration_display) ?></strong></p>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Status</th>
                            <th>Total Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($temp_attendance)): ?>
                            <?php foreach ($temp_attendance as $row): ?>
                                <?php
                                    $duration_display = '--';
                                    if ($row['clock_in'] && $row['clock_out']) {
                                        $clock_in_time = new DateTime($row['clock_in']);
                                        $clock_out_time = new DateTime($row['clock_out']);
                                        $interval = $clock_out_time->diff($clock_in_time);
                                        $duration_display = $interval->format('%h hrs %i mins');
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['log_date']) ?></td>
                                    <td><?= $row['clock_in'] ? date('h:i A', strtotime($row['clock_in'])) : '--' ?></td>
                                    <td><?= $row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : '--' ?></td>
                                    <td><span class="badge badge-<?= strtolower(str_replace(' ', '', $row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                    <td><?= $duration_display ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No attendance records found for this month.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
</body>
</html>