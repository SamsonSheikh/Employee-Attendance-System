<?php
session_start();

// 1. Security Check: Ensure they are logged in and have HR/Admin privileges
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/login.php");
    exit();
}

// 2. Database Connection
require_once '../../includes/db_connect.php';

// 3. Fetch HR User's Name
$hr_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : "Kevin";

// ==========================================================
// 4. DYNAMIC DATABASE QUERIES (Mapped to EmAttendancedb)
// ==========================================================

// Card 1: Total Employees (From `users` table)
$emp_query = $conn->query("SELECT COUNT(user_id) AS total FROM users");
$total_employees = $emp_query ? $emp_query->fetch_assoc()['total'] : 0;
// Prevent division by zero later in the chart logic
$safe_total_employees = max(1, $total_employees); 

// Card 2: Present Today (From `attendance_logs` table for CURDATE)
$present_query = $conn->query("SELECT COUNT(log_id) AS total FROM attendance_logs WHERE log_date = CURDATE() AND clock_in IS NOT NULL AND status != 'Absent'");
$present_today = $present_query ? $present_query->fetch_assoc()['total'] : 0;

// Card 3: Late Arrivals Today (From `attendance_logs` ENUM status)
$late_query = $conn->query("SELECT COUNT(log_id) AS total FROM attendance_logs WHERE log_date = CURDATE() AND status = 'Late'");
$late_today = $late_query ? $late_query->fetch_assoc()['total'] : 0;


// ==========================================================
// 5. FETCH DATA FOR THE ATTENDANCE CHART (Last 30 Days)
// ==========================================================
$chart_data = [];
// Pre-fill an array with the last 30 days so dates with 0 data still show up
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_data[$date] = [
        'date' => $date, 
        'label' => date('M j', strtotime($date)), 
        'present' => 0, 
        'absent' => 0, 
        'leave' => 0
    ];
}

// Fetch Present and Absent counts per day
$att_query = "
    SELECT log_date, 
           SUM(CASE WHEN clock_in IS NOT NULL AND status != 'Absent' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance_logs 
    WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY log_date
";
$att_res = $conn->query($att_query);
if ($att_res) {
    while($row = $att_res->fetch_assoc()) {
        if (isset($chart_data[$row['log_date']])) {
            $chart_data[$row['log_date']]['present'] = $row['present_count'];
            $chart_data[$row['log_date']]['absent'] = $row['absent_count'];
        }
    }
}

// Fetch Leave counts per day
$leave_query = "
    SELECT start_date, end_date 
    FROM leave_requests 
    WHERE approval_status = 'Approved' 
      AND end_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      AND start_date <= CURDATE()
";
$leave_res = $conn->query($leave_query);
if ($leave_res) {
    while($row = $leave_res->fetch_assoc()) {
        $start = strtotime($row['start_date']);
        $end = strtotime($row['end_date']);
        // Iterate through each day of the approved leave range
        for ($current = $start; $current <= $end; $current += 86400) {
            $d = date('Y-m-d', $current);
            if (isset($chart_data[$d])) {
                $chart_data[$d]['leave']++;
            }
        }
    }
}

// Convert associative array to indexed array for JavaScript
$chart_data_json = json_encode(array_values($chart_data));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard | FlowTime</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hrdashboard.css">
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
                    <li class="active"><a href="../../pages/hr/hrdashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li><a href="../../pages/hr/hrattendance.php"><i class="ph ph-user-focus"></i> Attendance</a></li>
                    <li><a href="../../pages/hr/hremployees.php"><i class="ph ph-users"></i> Employees</a></li>
                    <li><a href="../../pages/hr/hrsettings.php"><i class="ph ph-gear"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="sidebar-footer"></div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>Welcome back <?php echo htmlspecialchars($hr_name); ?>!</h1>
            </header>

            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($total_employees); ?></h3>
                        <p>Total Employees</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-user-check"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($present_today); ?></h3>
                        <p>Present Today</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-clock"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($late_today); ?></h3>
                        <p>Late Arrivals</p>
                    </div>
                </div>
            </section>

            <section class="data-grid">
                
                <div class="chart-card">
                    <div class="card-header">
                        <h2>Attendance Overview</h2>
                        <div class="chart-filters">
                            <button id="btnToday" onclick="renderChart(1)">Today</button>
                            <button id="btnWeekly" class="active" onclick="renderChart(7)">Weekly</button>
                            <button id="btnMonthly" onclick="renderChart(30)">Month</button>
                        </div>
                    </div>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="dot present"></span> Present</span>
                        <span class="legend-item"><span class="dot absent"></span> Absents</span>
                        <span class="legend-item"><span class="dot leave"></span> Leave</span>
                    </div>
                    
                    <div class="mock-chart" id="dynamicChart">
                        </div>
                </div>

                <div class="holidays-card">
                    <div class="card-header">
                        <h2>Upcoming Holidays</h2>
                    </div>
                    <ul class="holiday-list">
                        <li>
                            <div class="holiday-date"><span class="dot present"></span> Monday 29 July</div>
                            <div class="holiday-name">Eid - Al - Ada</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot leave"></span> Tuesday 15 August</div>
                            <div class="holiday-name">Independence Day</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot absent"></span> Wednesday 16 August</div>
                            <div class="holiday-name">Parsi New Year</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot absent"></span> Tuesday 29 August</div>
                            <div class="holiday-name">Onam</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot absent"></span> Wednesday 16 August</div>
                            <div class="holiday-name">Raksha Bandhan</div>
                        </li>
                    </ul>
                </div>

            </section>
        </main>
    </div>

    <script>
        // --- Sidebar Logic ---
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


        // --- Dynamic Chart Logic ---
        // Grab data prepared by PHP
        const chartData = <?php echo $chart_data_json; ?>;
        const totalEmployees = <?php echo $safe_total_employees; ?>;
        
        function renderChart(days) {
            const chartContainer = document.getElementById('dynamicChart');
            chartContainer.innerHTML = ''; // Clear existing bars

            // Update active state on buttons
            document.getElementById('btnToday').classList.remove('active');
            document.getElementById('btnWeekly').classList.remove('active');
            document.getElementById('btnMonthly').classList.remove('active');
            
            if (days === 1) document.getElementById('btnToday').classList.add('active');
            else if (days === 7) document.getElementById('btnWeekly').classList.add('active');
            else if (days === 30) document.getElementById('btnMonthly').classList.add('active');

            // Get only the requested number of days from the end of the array
            const dataSlice = chartData.slice(-days);
            
            // Adjust bar width based on how many days we are showing to prevent clustering
            const barWidthStyle = days === 30 ? 'width: 8px;' : 'width: 15px;';

            dataSlice.forEach(day => {
                // Calculate heights as a percentage of total employees
                const presentPct = (day.present / totalEmployees) * 100;
                const absentPct = (day.absent / totalEmployees) * 100;
                const leavePct = (day.leave / totalEmployees) * 100;

                // Create the bar HTML
                const group = document.createElement('div');
                group.className = 'chart-group';
                
                // If viewing a full month, only show the date number to save space
                const displayLabel = days === 30 ? day.label.split(' ')[1] : day.label;

                group.innerHTML = `
                    <div class="bar present" style="height: ${presentPct}%; ${barWidthStyle}" title="Present: ${day.present}"></div>
                    <div class="bar absent" style="height: ${absentPct}%; ${barWidthStyle}" title="Absent: ${day.absent}"></div>
                    <div class="bar leave" style="height: ${leavePct}%; ${barWidthStyle}" title="Leave: ${day.leave}"></div>
                    <span class="label">${displayLabel}</span>
                `;
                
                chartContainer.appendChild(group);
            });
            
            // Auto-scroll to the end (most recent day) when changing views
            setTimeout(() => {
                chartContainer.scrollLeft = chartContainer.scrollWidth;
            }, 10);
        }

        // Initialize the chart with the Weekly view on page load
        document.addEventListener("DOMContentLoaded", () => {
            renderChart(7);
        });
    </script>
</body>
</html>