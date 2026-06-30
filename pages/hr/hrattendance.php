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

// Determine which tab is active
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'daily';

// Capture Search and Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$dept_id = isset($_GET['dept_id']) ? $_GET['dept_id'] : '';
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch Departments for the modal
$departments = [];
$depts_res = $conn->query("SELECT * FROM departments ORDER BY department_name ASC");
if($depts_res) {
    while($d = $depts_res->fetch_assoc()) {
        $departments[] = $d;
    }
}

// Determine the name of the currently selected department for the button text
$selected_dept_name = 'All Departments';
if ($dept_id !== '') {
    foreach ($departments as $dept) {
        if ($dept['department_id'] == $dept_id) {
            $selected_dept_name = $dept['department_name'];
            break;
        }
    }
}

// ==========================================================
// 1. FETCH DAILY ATTENDANCE LOGS
// ==========================================================
$q1 = "
    SELECT 
        a.log_id, a.log_date, a.morning_clock_in, a.morning_clock_out, a.afternoon_clock_in, a.afternoon_clock_out, a.status,
        u.first_name, u.last_name,
        d.department_name
    FROM attendance_logs a
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE 1=1
";
$params1 = [];
$types1 = "";

if (!empty($search)) {
    $q1 .= " AND CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    $params1[] = "%$search%";
    $types1 .= "s";
}
if ($dept_id !== '') {
    $q1 .= " AND u.department_id = ?";
    $params1[] = intval($dept_id);
    $types1 .= "i";
}
$q1 .= " ORDER BY a.log_date DESC, a.morning_clock_in DESC LIMIT 100";

$stmt1 = $conn->prepare($q1);
if (!empty($params1)) {
    $stmt1->bind_param($types1, ...$params1);
}
$stmt1->execute();
$result = $stmt1->get_result();

// ==========================================================
// 2. FETCH MONTHLY SUMMARIES (PAYROLL DATA)
// ==========================================================
$q2 = "
    SELECT 
        u.user_id, u.first_name, u.last_name, d.department_name,
        SUM(CASE WHEN a.status != 'Absent' AND (a.morning_clock_in IS NOT NULL OR a.afternoon_clock_in IS NOT NULL) THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as total_absent,
        SUM(CASE WHEN a.morning_clock_in IS NOT NULL THEN 1 ELSE 0 END) + SUM(CASE WHEN a.afternoon_clock_in IS NOT NULL THEN 1 ELSE 0 END) as total_clock_ins,
        SUM(CASE WHEN a.morning_clock_out IS NOT NULL THEN 1 ELSE 0 END) + SUM(CASE WHEN a.afternoon_clock_out IS NOT NULL THEN 1 ELSE 0 END) as total_clock_outs,
        SUM(
            COALESCE(TIMESTAMPDIFF(MINUTE, a.morning_clock_in, a.morning_clock_out), 0) + 
            COALESCE(TIMESTAMPDIFF(MINUTE, a.afternoon_clock_in, a.afternoon_clock_out), 0)
        ) / 60 as total_hours
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN attendance_logs a ON u.user_id = a.user_id AND MONTH(a.log_date) = ? AND YEAR(a.log_date) = ?
    WHERE 1=1
";
$params2 = [$selected_month, $selected_year];
$types2 = "ii";

if (!empty($search)) {
    $q2 .= " AND CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    $params2[] = "%$search%";
    $types2 .= "s";
}
if ($dept_id !== '') {
    $q2 .= " AND u.department_id = ?";
    $params2[] = intval($dept_id);
    $types2 .= "i";
}

$q2 .= " GROUP BY u.user_id, u.first_name, u.last_name, d.department_name ORDER BY u.first_name ASC";

$summary_stmt = $conn->prepare($q2);
$summary_stmt->bind_param($types2, ...$params2);
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
                <button class="tab-btn <?php echo ($active_tab === 'daily') ? 'active' : ''; ?>" onclick="switchTab('daily')">Daily Logs</button>
                <button class="tab-btn <?php echo ($active_tab === 'monthly') ? 'active' : ''; ?>" onclick="switchTab('monthly')">Monthly Summaries</button>
            </div>

            <section id="tab-daily" class="tab-content <?php echo ($active_tab === 'daily') ? 'active' : ''; ?>">
                <div class="action-bar">
                    <form method="GET" class="search-filter-group">
                        <input type="hidden" name="tab" value="daily">
                        
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Search employee name..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn" title="Search">
                                <i class="ph ph-magnifying-glass"></i>
                            </button>
                        </div>
                        
                        <input type="hidden" name="dept_id" id="daily_dept_id" value="<?php echo htmlspecialchars($dept_id); ?>">
                        <button type="button" class="filter-dropdown dept-select-btn" onclick="openDeptModal('daily')">
                            <span id="daily_dept_text"><?php echo htmlspecialchars($selected_dept_name); ?></span>
                            <i class="ph ph-caret-down"></i>
                        </button>

                        <button type="submit" class="filter-btn">
                            <i class="ph ph-funnel"></i> Apply Filter
                        </button>
                    </form>

                    <button class="btn-export">
                        <i class="ph ph-download-simple"></i> Export CSV
                    </button>
                </div>

                <div class="table-container">
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
                                        <td><?php echo $row['morning_clock_in'] ? date('h:i A', strtotime($row['morning_clock_in'])) : '<span class="missing-punch">--:--</span>'; ?></td>
                                        <td><?php echo $row['morning_clock_out'] ? date('h:i A', strtotime($row['morning_clock_out'])) : '<span class="missing-punch">--:--</span>'; ?></td>
                                        <td><?php echo $row['afternoon_clock_in'] ? date('h:i A', strtotime($row['afternoon_clock_in'])) : '<span class="missing-punch">--:--</span>'; ?></td>
                                        <td><?php echo $row['afternoon_clock_out'] ? date('h:i A', strtotime($row['afternoon_clock_out'])) : '<span class="missing-punch">--:--</span>'; ?></td>
                                        <td>
                                            <?php $statusClass = strtolower(str_replace(' ', '-', $row['status'])); ?>
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
                                    <td colspan="9" class="empty-state">
                                        <i class="ph ph-folder-open"></i>
                                        <p>No attendance records found for this filter.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="tab-monthly" class="tab-content <?php echo ($active_tab === 'monthly') ? 'active' : ''; ?>">
                <div class="action-bar" style="align-items: flex-start;">
                    <form method="GET" class="month-filter-form" style="flex: 1;">
                        <input type="hidden" name="tab" value="monthly">
                        
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Search employee..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn" title="Search">
                                <i class="ph ph-magnifying-glass"></i>
                            </button>
                        </div>
                        
                        <input type="hidden" name="dept_id" id="monthly_dept_id" value="<?php echo htmlspecialchars($dept_id); ?>">
                        <button type="button" class="filter-dropdown dept-select-btn" onclick="openDeptModal('monthly')">
                            <span id="monthly_dept_text"><?php echo htmlspecialchars($selected_dept_name); ?></span>
                            <i class="ph ph-caret-down"></i>
                        </button>

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

                        <button type="submit" class="filter-btn">
                            <i class="ph ph-funnel"></i> Generate Report
                        </button>
                    </form>

                    <button class="btn-export">
                        <i class="ph ph-download-simple"></i> Export Payroll
                    </button>
                </div>

                <div class="table-container">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Selected Period</th>
                                <th>Days Present</th>
                                <th>Days Absent</th>
                                <th>Total In</th>
                                <th>Total Out</th>
                                <th>Total Hours Worked</th>
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
                                        
                                        <td style="color: #2b6cb0; font-weight: 600;"><?php echo $row['total_clock_ins'] ? $row['total_clock_ins'] : '0'; ?></td>
                                        <td style="color: #c05621; font-weight: 600;"><?php echo $row['total_clock_outs'] ? $row['total_clock_outs'] : '0'; ?></td>
                                        
                                        <td>
                                            <span class="hours-badge">
                                                <?php echo $row['total_hours'] ? number_format($row['total_hours'], 2) . ' hrs' : '0.00 hrs'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <i class="ph ph-calendar-blank"></i>
                                        <p>No recorded hours found for this query.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <div class="modal-overlay" id="deptModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Select Department</h2>
                <button type="button" class="close-modal" onclick="closeDeptModal()"><i class="ph ph-x"></i></button>
            </div>
            <div class="dept-list">
                <div class="dept-item <?php echo ($dept_id === '') ? 'selected' : ''; ?>" onclick="selectDept(this, '', 'All Departments')">
                    All Departments
                </div>
                <?php foreach($departments as $dept): ?>
                    <div class="dept-item <?php echo ($dept_id == $dept['department_id']) ? 'selected' : ''; ?>" 
                         onclick="selectDept(this, '<?php echo $dept['department_id']; ?>', '<?php echo htmlspecialchars(addslashes($dept['department_name'])); ?>')">
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
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

        // --- Tab Switching Logic ---
        function switchTab(tabId) {
            // Update URL to preserve tab state without reloading
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);

            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            document.getElementById('tab-' + tabId).classList.add('active');
            event.target.classList.add('active');
        }

        // --- Department Modal Logic ---
        let currentDeptTarget = '';
        const deptModal = document.getElementById('deptModal');

        function openDeptModal(target) {
            currentDeptTarget = target;
            deptModal.classList.add('active');
        }

        function closeDeptModal() {
            deptModal.classList.remove('active');
        }

        function selectDept(element, id, name) {
            // Update the hidden input and button text for the specific form that triggered the modal
            if(currentDeptTarget === 'daily') {
                document.getElementById('daily_dept_id').value = id;
                document.getElementById('daily_dept_text').innerText = name;
            } else if(currentDeptTarget === 'monthly') {
                document.getElementById('monthly_dept_id').value = id;
                document.getElementById('monthly_dept_text').innerText = name;
            }
            
            // Visually highlight the selection in the modal
            document.querySelectorAll('.dept-item').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            
            closeDeptModal();
        }

        // Close modal if clicking outside the white box
        deptModal.addEventListener('click', (e) => {
            if (e.target === deptModal) closeDeptModal();
        });
    </script>
</body>
</html>