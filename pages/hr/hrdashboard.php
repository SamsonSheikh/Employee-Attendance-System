<?php
session_start();

// 1. Security Check: Ensure they are logged in and have HR/Admin privileges
if (!isset($_SESSION['user_id'])) {
    // header("Location: login.php");
    // exit();
}
// Uncomment the above in production!

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

// Card 2: Present Today (From `attendance_logs` table for CURDATE)
$present_query = $conn->query("SELECT COUNT(log_id) AS total FROM attendance_logs WHERE log_date = CURDATE() AND clock_in IS NOT NULL");
$present_today = $present_query ? $present_query->fetch_assoc()['total'] : 0;

// Card 3: Late Arrivals Today (From `attendance_logs` ENUM status)
$late_query = $conn->query("SELECT COUNT(log_id) AS total FROM attendance_logs WHERE log_date = CURDATE() AND status = 'Late'");
$late_today = $late_query ? $late_query->fetch_assoc()['total'] : 0;

// Card 4: Pending Leave Requests (From `leave_requests` ENUM status)
$pending_query = $conn->query("SELECT COUNT(request_id) AS total FROM leave_requests WHERE approval_status = 'Pending'");
$pending_leaves = $pending_query ? $pending_query->fetch_assoc()['total'] : 0;

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
                    <li><a href="../../pages/hr/hrleaveapprovals.php"><i class="ph ph-calendar-check"></i> Leave Approvals</a></li>
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

                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-clipboard-text"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($pending_leaves); ?></h3>
                        <p>Pending Leaves</p>
                    </div>
                </div>

            </section>

            <section class="data-grid">
                
                <div class="chart-card">
                    <div class="card-header">
                        <h2>Attendance Overview</h2>
                        <div class="chart-filters">
                            <button>Today</button>
                            <button>Month</button>
                            <button class="active">Weekly</button>
                        </div>
                    </div>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="dot present"></span> Present</span>
                        <span class="legend-item"><span class="dot absent"></span> Absents</span>
                        <span class="legend-item"><span class="dot leave"></span> Leave</span>
                    </div>
                    
                    <div class="mock-chart">
                        <div class="chart-group">
                            <div class="bar present" style="height: 60%;"></div>
                            <div class="bar absent" style="height: 80%;"></div>
                            <div class="bar leave" style="height: 40%;"></div>
                            <span class="label">July 4</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 50%;"></div>
                            <div class="bar absent" style="height: 90%;"></div>
                            <div class="bar leave" style="height: 60%;"></div>
                            <span class="label">July 5</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 30%;"></div>
                            <div class="bar absent" style="height: 60%;"></div>
                            <div class="bar leave" style="height: 70%;"></div>
                            <span class="label">July 6</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 75%;"></div>
                            <div class="bar absent" style="height: 95%;"></div>
                            <div class="bar leave" style="height: 45%;"></div>
                            <span class="label">July 7</span>
                        </div>
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