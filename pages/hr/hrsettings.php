<?php
session_start();

// Security Check
if (!isset($_SESSION['user_id'])) {
    // header("Location: login.php");
    // exit();
}

require_once '../../includes/db_connect.php';
$hr_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : "Kevin";

// ==========================================================
// HANDLE FORM SUBMISSIONS
// ==========================================================
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Department
    if (isset($_POST['add_dept'])) {
        $dept_name = trim($_POST['department_name']);
        // Using IGNORE to prevent fatal errors if it already exists (since it's UNIQUE in DB)
        $stmt = $conn->prepare("INSERT IGNORE INTO departments (department_name) VALUES (?)");
        $stmt->bind_param("s", $dept_name);
        $stmt->execute();
        if($stmt->affected_rows > 0) $message = "Department added successfully.";
        else $message = "Department already exists.";
        $stmt->close();
    }
    
    // Add Shift
    if (isset($_POST['add_shift'])) {
        $shift_name = trim($_POST['shift_name']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $stmt = $conn->prepare("INSERT INTO shifts (shift_name, start_time, end_time) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $shift_name, $start_time, $end_time);
        if($stmt->execute()) $message = "Shift added successfully.";
        $stmt->close();
    }

    // Add Leave Type
    if (isset($_POST['add_leave'])) {
        $leave_name = trim($_POST['leave_name']);
        $stmt = $conn->prepare("INSERT IGNORE INTO leave_types (leave_name) VALUES (?)");
        $stmt->bind_param("s", $leave_name);
        $stmt->execute();
        if($stmt->affected_rows > 0) $message = "Leave type added successfully.";
        else $message = "Leave type already exists.";
        $stmt->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: hrsettings.php?msg=" . urlencode($message));
    exit();
}

// ==========================================================
// FETCH EXISTING DATA
// ==========================================================
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name ASC");
$shifts = $conn->query("SELECT * FROM shifts ORDER BY start_time ASC");
$leave_types = $conn->query("SELECT * FROM leave_types ORDER BY leave_name ASC");
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
            <button class="close-sidebar" id="closeSidebar" aria-label="Close Sidebar">
                <i class="ph ph-x"></i>
            </button>

            <div class="sidebar-brand desktop-brand">
                <span class="brand-icon"><i class="ph-fill ph-clock-user"></i></span>
                <span class="brand-text">FlowTime</span>
            </div>

            <div class="sidebar-menu-wrapper">
                <ul class="sidebar-links">
                    <li><a href="hrdashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li><a href="hrattendance.php"><i class="ph ph-user-focus"></i> Attendance</a></li>
                    <li><a href="hrleaveapprovals.php"><i class="ph ph-calendar-check"></i> Leave Approvals</a></li>
                    <li><a href="hremployees.php"><i class="ph ph-users"></i> Employees</a></li>
                    <li class="active"><a href="hrsettings.php"><i class="ph ph-gear"></i> Settings</a></li>
                </ul>
            </div>
            <div class="sidebar-footer"></div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>System Configuration</h1>
                <p class="subtitle">Manage departments, shifts, and organizational rules.</p>
            </header>

            <?php if(isset($_GET['msg']) && !empty($_GET['msg'])): ?>
                <div class="alert alert-success">
                    <i class="ph ph-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>

            <div class="tab-navigation">
                <button class="tab-btn active" onclick="switchTab('departments', event)">Departments</button>
                <button class="tab-btn" onclick="switchTab('shifts', event)">Work Shifts</button>
                <button class="tab-btn" onclick="switchTab('leaves', event)">Leave Types</button>
            </div>

            <section id="tab-departments" class="tab-content active">
                <div class="settings-grid">
                    <div class="settings-card data-card">
                        <h3>Current Departments</h3>
                        <table class="data-table">
                            <thead>
                                <tr><th>ID</th><th>Department Name</th></tr>
                            </thead>
                            <tbody>
                                <?php if($departments->num_rows > 0): while($row = $departments->fetch_assoc()): ?>
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

            <section id="tab-shifts" class="tab-content">
                <div class="settings-grid">
                    <div class="settings-card data-card">
                        <h3>Configured Shifts</h3>
                        <table class="data-table">
                            <thead>
                                <tr><th>Shift Name</th><th>Start Time</th><th>End Time</th></tr>
                            </thead>
                            <tbody>
                                <?php if($shifts->num_rows > 0): while($row = $shifts->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-500"><?php echo htmlspecialchars($row['shift_name']); ?></td>
                                        <td><span class="time-badge"><?php echo date('h:i A', strtotime($row['start_time'])); ?></span></td>
                                        <td><span class="time-badge"><?php echo date('h:i A', strtotime($row['end_time'])); ?></span></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="3" class="empty-state">No shifts configured.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="settings-card form-card">
                        <h3>Add Work Shift</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Shift Name</label>
                                <input type="text" name="shift_name" required placeholder="e.g. Morning Shift">
                            </div>
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="time" name="start_time" required>
                            </div>
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="time" name="end_time" required>
                            </div>
                            <button type="submit" name="add_shift" class="btn-primary">Save Shift</button>
                        </form>
                    </div>
                </div>
            </section>

            <section id="tab-leaves" class="tab-content">
                <div class="settings-grid">
                    <div class="settings-card data-card">
                        <h3>Approved Leave Categories</h3>
                        <table class="data-table">
                            <thead>
                                <tr><th>ID</th><th>Leave Category</th></tr>
                            </thead>
                            <tbody>
                                <?php if($leave_types->num_rows > 0): while($row = $leave_types->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['leave_type_id']; ?></td>
                                        <td><span class="type-badge"><?php echo htmlspecialchars($row['leave_name']); ?></span></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="2" class="empty-state">No leave types configured.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="settings-card form-card">
                        <h3>Add Leave Type</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Leave Category Name</label>
                                <input type="text" name="leave_name" required placeholder="e.g. Sick Leave, Bereavement">
                            </div>
                            <button type="submit" name="add_leave" class="btn-primary">Save Leave Type</button>
                        </form>
                    </div>
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

        // Tab Switcher Logic
        function switchTab(tabId, event) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            document.getElementById('tab-' + tabId).classList.add('active');
            if(event) event.target.classList.add('active');
        }
    </script>
</body>
</html>