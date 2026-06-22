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
// 1. FETCH DAILY ATTENDANCE LOGS
// ==========================================================
$query = "
    SELECT 
        a.log_id, a.log_date, a.morning_clock_in, a.morning_clock_out, a.afternoon_clock_in, a.afternoon_clock_out, a.status,
        u.first_name, u.last_name,
        d.department_name
    FROM attendance_logs a
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    ORDER BY a.log_date DESC, a.morning_clock_in DESC
    LIMIT 100
";
$result = $conn->query($query);

// ==========================================================
// 2. FETCH MONTHLY SUMMARIES (PAYROLL DATA)
// ==========================================================
// Get selected month/year or default to current
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// The SQL query groups by user, counts days present/absent, and calculates total worked hours
$summary_stmt = $conn->prepare("
    SELECT 
        u.user_id, u.first_name, u.last_name, d.department_name,
        SUM(CASE WHEN a.status != 'Absent' AND a.clock_in IS NOT NULL THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as total_absent,
        SUM(TIMESTAMPDIFF(MINUTE, a.clock_in, a.clock_out)) / 60 as total_hours
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN attendance_logs a ON u.user_id = a.user_id AND MONTH(a.log_date) = ? AND YEAR(a.log_date) = ?
    GROUP BY u.user_id, u.first_name, u.last_name, d.department_name
    ORDER BY u.first_name ASC
");
$summary_stmt->bind_param("ii", $selected_month, $selected_year);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();

$monthName = date("F", mktime(0, 0, 0, $selected_month, 10));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | FlowTime</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hrattendance.css">
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
                    <li class="active"><a href="../../pages/hr/hrattendance.php"><i class="ph ph-user-focus"></i> Attendance</a></li>
                    <li><a href="../../pages/hr/hremployees.php"><i class="ph ph-users"></i> Employees</a></li>
                    <li><a href="../../pages/hr/hrsettings.php"><i class="ph ph-gear"></i> Settings</a></li>
                </ul>
            </div>
            <div class="sidebar-footer"></div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>Live Attendance & Timesheets</h1>
                <p class="subtitle">Monitor daily punches and export payroll data.</p>
            </header>

            <div class="tab-navigation">
                <button class="tab-btn <?php echo !isset($_GET['month']) ? 'active' : ''; ?>" onclick="switchTab('daily')">Daily Logs</button>
                <button class="tab-btn <?php echo isset($_GET['month']) ? 'active' : ''; ?>" onclick="switchTab('monthly')">Monthly Summaries</button>
            </div>

            <section id="tab-daily" class="tab-content <?php echo !isset($_GET['month']) ? 'active' : ''; ?>">
                <div class="action-bar">
                    <div class="search-filter-group">
                        <div class="search-box">
                            <i class="ph ph-magnifying-glass"></i>
                            <input type="text" placeholder="Search employee name...">
                        </div>
                        
                        <select class="filter-dropdown">
                            <option value="">All Departments</option>
                            <option value="IT">IT</option>
                            <option value="HR">HR</option>
                            <option value="Operations">Operations</option>
                        </select>
                    </div>

                    <button class="btn-export">
                        <i class="ph ph-download-simple"></i> Export CSV
                    </button>
                </div>

<<<<<<< HEAD
                <div class="table-container">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="emp-name">
                                            <div class="avatar-sm">
                                                <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['log_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($row['clock_in'])); ?></td>
                                        <td>
                                            <?php 
                                                echo $row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : '<span class="missing-punch">--:--</span>'; 
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusClass = strtolower(str_replace(' ', '-', $row['status']));
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn-icon" title="Edit Record"><i class="ph ph-pencil-simple"></i></button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="ph ph-folder-open"></i>
                                        <p>No attendance records found.</p>
=======
                <button class="btn-export">
                    <i class="ph ph-download-simple"></i> Export CSV
                </button>
            </section>

            <section class="table-container">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Morning In</th>
                            <th>Morning Out</th>
                            <th>Afternoon In</th>
                            <th>Afternoon Out</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="emp-name">
                                        <div class="avatar-sm">
                                            <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                        </div>
                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['log_date'])); ?></td>
                                    <td>
                                        <?php 
                                            echo $row['morning_clock_in'] ? date('h:i A', strtotime($row['morning_clock_in'])) : '<span class="missing-punch">--:--</span>'; 
                                        ?>
                                    </td>
                                    <td><?php echo $row['morning_clock_out'] ? date('h:i A', strtotime($row['morning_clock_out'])) : '<span class="missing-punch">--:--</span>'; ?></td>
                                    <td><?php echo $row['afternoon_clock_in'] ? date('h:i A', strtotime($row['afternoon_clock_in'])) : '<span class="missing-punch">--:--</span>'; ?></td>
                                    <td>
                                        <?php 
                                            echo $row['afternoon_clock_out'] ? date('h:i A', strtotime($row['afternoon_clock_out'])) : '<span class="missing-punch">--:--</span>'; 
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            // Apply dynamic CSS classes based on the ENUM status
                                            $statusClass = strtolower(str_replace(' ', '-', $row['status']));
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-icon" title="Edit Record"><i class="ph ph-pencil-simple"></i></button>
>>>>>>> d57d342e4679f9986e53377aa6d3656172a33105
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="tab-monthly" class="tab-content <?php echo isset($_GET['month']) ? 'active' : ''; ?>">
                <div class="action-bar" style="justify-content: space-between; display: flex;">
                    <form method="GET" class="month-filter-form">
                        <select name="month">
                            <?php 
                            for($m=1; $m<=12; $m++){
                                $selected = ($m == $selected_month) ? 'selected' : '';
                                echo "<option value='$m' $selected>" . date('F', mktime(0,0,0,$m,1)) . "</option>";
                            }
                            ?>
                        </select>
                        <select name="year">
                            <?php 
                            $currentYear = date('Y');
                            for($y = $currentYear; $y >= $currentYear - 2; $y--){
                                $selected = ($y == $selected_year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                        <button type="submit">Generate Report</button>
                    </form>

                    <button class="btn-export">
                        <i class="ph ph-download-simple"></i> Export Payroll Data
                    </button>
                </div>

                <div class="table-container">
                    <table class="attendance-table">
                        <thead>
                            <tr>
<<<<<<< HEAD
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Selected Period</th>
                                <th>Days Present</th>
                                <th>Days Absent</th>
                                <th>Total Hours Worked</th>
=======
                                <td colspan="9" class="empty-state">
                                    <i class="ph ph-folder-open"></i>
                                    <p>No attendance records found.</p>
                                </td>
>>>>>>> d57d342e4679f9986e53377aa6d3656172a33105
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($summary_result && $summary_result->num_rows > 0): ?>
                                <?php while($row = $summary_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="emp-name">
                                            <div class="avatar-sm">
                                                <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $monthName . ' ' . $selected_year; ?></td>
                                        <td style="color: #48bb78; font-weight: 600;"><?php echo $row['total_present'] ? $row['total_present'] : '0'; ?></td>
                                        <td style="color: #e53e3e; font-weight: 600;"><?php echo $row['total_absent'] ? $row['total_absent'] : '0'; ?></td>
                                        <td>
                                            <span class="hours-badge">
                                                <?php echo $row['total_hours'] ? number_format($row['total_hours'], 2) . ' hrs' : '0.00 hrs'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="ph ph-calendar-blank"></i>
                                        <p>No recorded hours found for this month.</p>
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
        // Sidebar Logic
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

        // Tab Switching Logic
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            document.getElementById('tab-' + tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>