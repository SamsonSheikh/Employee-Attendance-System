<?php
session_start();

// ==========================================================
// HANDLE LOGOUT
// ==========================================================
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_unset();
    session_destroy();
    header("Location: ../public/login.php");
    exit();
}

// Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/login.php");
    exit();
}

require_once '../../includes/db_connect.php';
$hr_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : "Kevin";
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// ==========================================================
// HANDLE FORM SUBMISSIONS
// ==========================================================
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Department
    if (isset($_POST['add_dept'])) {
        $dept_name = trim($_POST['department_name']);
        $stmt = $conn->prepare("INSERT IGNORE INTO departments (department_name) VALUES (?)");
        $stmt->bind_param("s", $dept_name);
        $stmt->execute();
        if($stmt->affected_rows > 0) $message = "Department added successfully.";
        else $message = "Department already exists.";
        $stmt->close();
    }
    
    // Apply Global Schedule to ALL Users
    if (isset($_POST['apply_global_schedule'])) {
        $users_res = $conn->query("SELECT user_id FROM users");
        
        $stmt = $conn->prepare("INSERT INTO employee_schedules (user_id, day_of_week, is_active, morning_in, morning_out, afternoon_in, afternoon_out) 
            VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
            is_active=VALUES(is_active), morning_in=VALUES(morning_in), morning_out=VALUES(morning_out), afternoon_in=VALUES(afternoon_in), afternoon_out=VALUES(afternoon_out)");
            
        while($u = $users_res->fetch_assoc()) {
            $emp_id = $u['user_id'];
            foreach ($days_of_week as $day) {
                $is_active = isset($_POST['active_'.$day]) ? 1 : 0;
                $m_in = !empty($_POST['morning_in_'.$day]) ? $_POST['morning_in_'.$day] : NULL;
                $m_out = !empty($_POST['morning_out_'.$day]) ? $_POST['morning_out_'.$day] : NULL;
                $a_in = !empty($_POST['afternoon_in_'.$day]) ? $_POST['afternoon_in_'.$day] : NULL;
                $a_out = !empty($_POST['afternoon_out_'.$day]) ? $_POST['afternoon_out_'.$day] : NULL;
                
                $stmt->bind_param("isissss", $emp_id, $day, $is_active, $m_in, $m_out, $a_in, $a_out);
                $stmt->execute();
            }
        }
        $stmt->close();
        $message = "Global weekly schedule applied to all employees.";
    }

    // Save Individual Schedule
    if (isset($_POST['save_individual_schedule'])) {
        $emp_id = intval($_POST['target_employee_id']);
        
        $stmt = $conn->prepare("INSERT INTO employee_schedules (user_id, day_of_week, is_active, morning_in, morning_out, afternoon_in, afternoon_out) 
            VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
            is_active=VALUES(is_active), morning_in=VALUES(morning_in), morning_out=VALUES(morning_out), afternoon_in=VALUES(afternoon_in), afternoon_out=VALUES(afternoon_out)");
            
        foreach ($days_of_week as $day) {
            $is_active = isset($_POST['ind_active_'.$day]) ? 1 : 0;
            $m_in = !empty($_POST['ind_morning_in_'.$day]) ? $_POST['ind_morning_in_'.$day] : NULL;
            $m_out = !empty($_POST['ind_morning_out_'.$day]) ? $_POST['ind_morning_out_'.$day] : NULL;
            $a_in = !empty($_POST['ind_afternoon_in_'.$day]) ? $_POST['ind_afternoon_in_'.$day] : NULL;
            $a_out = !empty($_POST['ind_afternoon_out_'.$day]) ? $_POST['ind_afternoon_out_'.$day] : NULL;
            
            $stmt->bind_param("isissss", $emp_id, $day, $is_active, $m_in, $m_out, $a_in, $a_out);
            $stmt->execute();
        }
        $stmt->close();
        $message = "Custom schedule saved for employee.";
    }

    // Redirect to prevent form resubmission popup
    if(!empty($message)){
        header("Location: hrsettings.php?msg=" . urlencode($message));
        exit();
    }
}

// ==========================================================
// FETCH EXISTING DATA
// ==========================================================
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name ASC");

// Fetch users
$users_query = "
    SELECT u.user_id, u.first_name, u.last_name, u.email, d.department_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.department_id 
    ORDER BY u.first_name ASC
";
$users_result = $conn->query($users_query);

// Fetch all saved schedules and organize them by user_id
$schedules = [];
$sched_res = $conn->query("SELECT * FROM employee_schedules");
if ($sched_res) {
    while($row = $sched_res->fetch_assoc()) {
        $schedules[$row['user_id']][$row['day_of_week']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | FlowTime</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hrsettings.css">
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
            <button class="close-sidebar" id="closeSidebar" aria-label="Close Sidebar"><i class="ph ph-x"></i></button>
            <div class="sidebar-brand desktop-brand">
                <span class="brand-icon"><i class="ph-fill ph-clock-user"></i></span>
                <span class="brand-text">FlowTime</span>
            </div>
            <div class="sidebar-menu-wrapper">
                <ul class="sidebar-links">
                    <li><a href="hrdashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li><a href="hrattendance.php"><i class="ph ph-user-focus"></i> Attendance</a></li>
                    <li><a href="hremployees.php"><i class="ph ph-users"></i> Employees</a></li>
                    <li class="active"><a href="hrsettings.php"><i class="ph ph-gear"></i> Settings</a></li>
                </ul>
            </div>
            
            <!-- NEW LOGOUT BUTTON FOOTER -->
            <div class="sidebar-footer" style="padding: 1.5rem; margin-top: auto; border-top: 1px solid var(--border-color);">
                <a href="?logout=true" style="display: flex; align-items: center; gap: 1rem; color: #e53e3e; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.2s;" onmouseover="this.style.color='#c53030'" onmouseout="this.style.color='#e53e3e'">
                    <i class="ph ph-sign-out" style="font-size: 1.25rem;"></i> Log Out
                </a>
            </div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>System Configuration</h1>
                <p class="subtitle">Manage departments and university-wide work schedules.</p>
            </header>

            <?php if(isset($_GET['msg']) && !empty($_GET['msg'])): ?>
                <div class="alert alert-success"><i class="ph ph-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?></div>
            <?php endif; ?>

            <div class="tab-navigation">
                <button class="tab-btn active" onclick="switchTab('departments', event)">Departments</button>
                <button class="tab-btn" onclick="switchTab('schedules', event)">Work Schedules</button>
            </div>

            <section id="tab-departments" class="tab-content active">
                <div class="settings-grid">
                    <div class="settings-card data-card">
                        <h3>Current Departments</h3>
                        <table class="data-table">
                            <thead><tr><th>ID</th><th>Department Name</th></tr></thead>
                            <tbody>
                                <?php if($departments && $departments->num_rows > 0): while($row = $departments->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['department_id']; ?></td>
                                        <td class="fw-500"><?php echo htmlspecialchars($row['department_name']); ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="2" class="empty-state">No departments configured.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="settings-card form-card">
                        <h3>Add Department</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Department Name</label>
                                <input type="text" name="department_name" required placeholder="e.g. IT, HR, Marketing">
                            </div>
                            <button type="submit" name="add_dept" class="btn-primary">Save Department</button>
                        </form>
                    </div>
                </div>
            </section>

            <section id="tab-schedules" class="tab-content">
                <div class="settings-grid schedule-layout">
                    
                    <div class="settings-card form-card">
                        <h3>University Base Schedule</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Define the default AM/PM working hours for the week and apply to all staff.</p>
                        
                        <form method="POST">
                            <div class="weekly-schedule-grid">
                                <?php foreach($days_of_week as $day): ?>
                                    <div class="day-row">
                                        <div class="day-label" style="margin-top: 0.4rem;">
                                            <input type="checkbox" name="active_<?php echo $day; ?>" checked>
                                            <span><?php echo $day; ?></span>
                                        </div>
                                        <div class="time-inputs-group">
                                            <div class="time-row">
                                                <span class="time-label">AM</span>
                                                <input type="time" name="morning_in_<?php echo $day; ?>" value="08:00">
                                                <span>to</span>
                                                <input type="time" name="morning_out_<?php echo $day; ?>" value="12:00">
                                            </div>
                                            <div class="time-row">
                                                <span class="time-label">PM</span>
                                                <input type="time" name="afternoon_in_<?php echo $day; ?>" value="13:00">
                                                <span>to</span>
                                                <input type="time" name="afternoon_out_<?php echo $day; ?>" value="17:00">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" name="apply_global_schedule" class="btn-primary" style="margin-top: 1.5rem; width: 100%;">Apply Schedule to All</button>
                        </form>
                    </div>

                    <div class="settings-card data-card">
                        <h3>Employee Schedules</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">View and set custom AM/PM schedules for individual employees.</p>
                        
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Current Schedule</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($users_result && $users_result->num_rows > 0): while($u = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="emp-profile">
                                                <div class="avatar-sm"><?php echo strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)); ?></div>
                                                <div class="emp-info">
                                                    <span class="emp-name"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></span>
                                                    <span style="display:block; font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($u['department_name'] ?? 'Unassigned'); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="schedule-summary-badges">
                                                <?php 
                                                $short_days = ['Monday'=>'M', 'Tuesday'=>'T', 'Wednesday'=>'W', 'Thursday'=>'Th', 'Friday'=>'F', 'Saturday'=>'S', 'Sunday'=>'Su'];
                                                foreach($days_of_week as $day) {
                                                    $sch = isset($schedules[$u['user_id']][$day]) ? $schedules[$u['user_id']][$day] : null;
                                                    $is_active = ($sch && $sch['is_active'] == 1);
                                                    $class = $is_active ? 'active-day' : 'inactive-day';
                                                    
                                                    if ($is_active) {
                                                        $m_in = $sch['morning_in'] ? date('h:i A', strtotime($sch['morning_in'])) : '--:--';
                                                        $m_out = $sch['morning_out'] ? date('h:i A', strtotime($sch['morning_out'])) : '--:--';
                                                        $a_in = $sch['afternoon_in'] ? date('h:i A', strtotime($sch['afternoon_in'])) : '--:--';
                                                        $a_out = $sch['afternoon_out'] ? date('h:i A', strtotime($sch['afternoon_out'])) : '--:--';
                                                        $title = "$day\\nAM: $m_in - $m_out\\nPM: $a_in - $a_out";
                                                    } else {
                                                        $title = "$day: Off";
                                                    }
                                                    
                                                    echo "<span class='day-badge $class' title='$title'>" . $short_days[$day] . "</span>";
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn-secondary btn-sm" onclick="openScheduleModal(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($u['first_name'] . ' ' . $u['last_name'])); ?>')">
                                                Edit Schedule
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="3" class="empty-state">No employees found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </section>

        </main>
    </div>

    <div class="modal-overlay" id="scheduleModal">
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header">
                <h2>Set Schedule: <span id="modalEmpName" style="color: var(--primary-color);"></span></h2>
                <button type="button" class="close-modal" onclick="closeScheduleModal()"><i class="ph ph-x"></i></button>
            </div>
            <form method="POST" style="padding: 1.5rem; max-height: 70vh; overflow-y: auto;">
                <input type="hidden" name="target_employee_id" id="target_employee_id">
                
                <div class="weekly-schedule-grid">
                    <?php foreach($days_of_week as $day): ?>
                        <div class="day-row">
                            <div class="day-label" style="margin-top: 0.4rem;">
                                <input type="checkbox" name="ind_active_<?php echo $day; ?>" id="chk_<?php echo $day; ?>" checked>
                                <span><?php echo $day; ?></span>
                            </div>
                            <div class="time-inputs-group">
                                <div class="time-row">
                                    <span class="time-label">AM</span>
                                    <input type="time" name="ind_morning_in_<?php echo $day; ?>" id="min_<?php echo $day; ?>">
                                    <span>to</span>
                                    <input type="time" name="ind_morning_out_<?php echo $day; ?>" id="mout_<?php echo $day; ?>">
                                </div>
                                <div class="time-row">
                                    <span class="time-label">PM</span>
                                    <input type="time" name="ind_afternoon_in_<?php echo $day; ?>" id="ain_<?php echo $day; ?>">
                                    <span>to</span>
                                    <input type="time" name="ind_afternoon_out_<?php echo $day; ?>" id="aout_<?php echo $day; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="modal-footer" style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                    <button type="button" class="btn-secondary" onclick="closeScheduleModal()" style="flex: 1;">Cancel</button>
                    <button type="submit" name="save_individual_schedule" class="btn-primary" style="flex: 1;">Save Custom Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Sidebar Logic
        const menuToggle = document.getElementById('menuToggle');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleMenu() { sidebar.classList.toggle('active'); sidebarOverlay.classList.toggle('active'); }
        menuToggle.addEventListener('click', toggleMenu);
        closeSidebar.addEventListener('click', toggleMenu);
        sidebarOverlay.addEventListener('click', toggleMenu);

        // Tab Switcher Logic
        function switchTab(tabId, event) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');
            if(event) event.target.classList.add('active');
        }

        // Modal Logic & Pre-population
        const scheduleModal = document.getElementById('scheduleModal');
        const modalEmpName = document.getElementById('modalEmpName');
        const userSchedules = <?php echo json_encode($schedules); ?>;
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        function openScheduleModal(employeeId, employeeName) {
            document.getElementById('target_employee_id').value = employeeId;
            modalEmpName.innerText = employeeName;
            
            // Check if user has schedules in DB, if not use defaults
            const sched = userSchedules[employeeId];
            
            days.forEach(day => {
                const activeCb = document.getElementById('chk_' + day);
                const min = document.getElementById('min_' + day);
                const mout = document.getElementById('mout_' + day);
                const ain = document.getElementById('ain_' + day);
                const aout = document.getElementById('aout_' + day);
                
                if (sched && sched[day]) {
                    // Pre-fill with DB Data
                    activeCb.checked = (sched[day].is_active == 1);
                    min.value = sched[day].morning_in ? sched[day].morning_in.substring(0,5) : '';
                    mout.value = sched[day].morning_out ? sched[day].morning_out.substring(0,5) : '';
                    ain.value = sched[day].afternoon_in ? sched[day].afternoon_in.substring(0,5) : '';
                    aout.value = sched[day].afternoon_out ? sched[day].afternoon_out.substring(0,5) : '';
                } else {
                    // Default values if they have never had a schedule saved
                    activeCb.checked = (day !== 'Saturday' && day !== 'Sunday');
                    min.value = '08:00'; mout.value = '12:00';
                    ain.value = '13:00'; aout.value = '17:00';
                }
            });

            scheduleModal.classList.add('active');
        }

        function closeScheduleModal() { scheduleModal.classList.remove('active'); }
        scheduleModal.addEventListener('click', (e) => { if (e.target === scheduleModal) closeScheduleModal(); });
    </script>
</body>
</html>