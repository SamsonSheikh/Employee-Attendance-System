<?php
session_start();

// Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/login.php");
    exit();
}

require_once '../../includes/db_connect.php';

// Fetch HR User's Name
$hr_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : "Kevin";

// ==========================================================
// HANDLE "ADD EMPLOYEE" FORM SUBMISSION
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $dept_id = intval($_POST['department_id']);
    $shift_id = intval($_POST['shift_id']);
    
    // Automatically fetch the role_id for "employee"
    $role_query = $conn->query("SELECT role_id FROM roles WHERE role_name = 'employee' LIMIT 1");
    // Fallback to 3 if the query fails, assuming 'admin'=1, 'hr'=2, 'employee'=3
    $role_id = ($role_query && $role_query->num_rows > 0) ? $role_query->fetch_assoc()['role_id'] : 3;
    
    // Set a default temporary password for new accounts
    $default_password = "Password123!";
    $password_hash = password_hash($default_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, department_id, role_id, shift_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiii", $first_name, $last_name, $email, $password_hash, $dept_id, $role_id, $shift_id);
    
    if ($stmt->execute()) {
        header("Location: hremployees.php?success=1");
        exit();
    }
    $stmt->close();
}

// ==========================================================
// FETCH DATA FOR DISPLAY & FORMS
// ==========================================================
// 1. Fetch Users Master List
$users_query = "
    SELECT u.*, d.department_name, r.role_name, s.shift_name, s.start_time, s.end_time
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN shifts s ON u.shift_id = s.shift_id
    ORDER BY u.created_at DESC
";
$users_result = $conn->query($users_query);

// 2. Fetch Dropdown Options for the Modal
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name ASC");
$shifts = $conn->query("SELECT * FROM shifts ORDER BY shift_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees | FlowTime</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hremployees.css">
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
                    <li><a href="../../pages/hr/hrattendance.php"><i class="ph ph-user-focus"></i> Attendance</a></li>
                    <li class="active"><a href="../../pages/hr/hremployees.php"><i class="ph ph-users"></i> Employees</a></li>
                    <li><a href="../../pages/hr/hrsettings.php"><i class="ph ph-gear"></i> Settings</a></li>
                </ul>
            </div>
            <div class="sidebar-footer"></div>
        </aside>

        <main class="content">
            <header class="page-header">
                <div class="header-content">
                    <div>
                        <h1>Employee Roster</h1>
                        <p class="subtitle">Manage staff accounts, roles, and shift assignments.</p>
                    </div>
                    <button class="btn-primary" id="openAddModalBtn">
                        <i class="ph ph-plus"></i> Add Employee
                    </button>
                </div>
            </header>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="ph ph-check-circle"></i> Employee added successfully! Default password is: <strong>Password123!</strong>
                </div>
            <?php endif; ?>

            <section class="action-bar">
                <div class="search-box">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" id="employeeSearch" placeholder="Search by name or email...">
                </div>
            </section>

            <section class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee Details</th>
                            <th>Department & Role</th>
                            <th>Assigned Shift</th>
                            <th>Join Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_result && $users_result->num_rows > 0): ?>
                            <?php while($row = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="emp-profile">
                                            <div class="avatar-sm">
                                                <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="emp-info">
                                                <span class="emp-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                                <span class="emp-email"><?php echo htmlspecialchars($row['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="role-info">
                                            <span class="dept-badge"><?php echo htmlspecialchars($row['department_name'] ?? 'Unassigned'); ?></span>
                                            <span class="role-text"><?php echo htmlspecialchars($row['role_name'] ?? 'No Role'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="shift-info">
                                            <strong><?php echo htmlspecialchars($row['shift_name'] ?? 'No Shift'); ?></strong>
                                            <?php if($row['start_time']): ?>
                                                <span class="shift-times">
                                                    <?php echo date('h:i A', strtotime($row['start_time'])) . ' - ' . date('h:i A', strtotime($row['end_time'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" title="Reset Password"><i class="ph ph-key"></i></button>
                                            <button class="btn-icon" title="Edit Profile"><i class="ph ph-pencil-simple"></i></button>
                                            <button class="btn-icon text-danger" title="Deactivate"><i class="ph ph-user-minus"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="ph ph-users"></i>
                                    <p>No employees found in the database.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="addEmployeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Employee</h2>
                <button class="close-modal" id="closeAddModalBtn"><i class="ph ph-x"></i></button>
            </div>
            <form method="POST" action="hremployees.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" required placeholder="e.g. Jane">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required placeholder="e.g. Doe">
                    </div>
                    <div class="form-group full-width">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="jane.doe@company.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" required>
                            <option value="">Select Department</option>
                            <?php while($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $dept['department_id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Work Shift</label>
                        <select name="shift_id" required>
                            <option value="">Select Shift</option>
                            <?php while($shift = $shifts->fetch_assoc()): ?>
                                <option value="<?php echo $shift['shift_id']; ?>">
                                    <?php echo htmlspecialchars($shift['shift_name']) . " (" . date('h:i A', strtotime($shift['start_time'])) . " - " . date('h:i A', strtotime($shift['end_time'])) . ")"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelAddModalBtn">Cancel</button>
                    <button type="submit" name="add_employee" class="btn-primary">Create Account</button>
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

        function toggleMenu() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }

        menuToggle.addEventListener('click', toggleMenu);
        closeSidebar.addEventListener('click', toggleMenu);
        sidebarOverlay.addEventListener('click', toggleMenu);

        // Modal Logic
        const addModal = document.getElementById('addEmployeeModal');
        const openModalBtn = document.getElementById('openAddModalBtn');
        const closeAddModalBtn = document.getElementById('closeAddModalBtn');
        const cancelAddModalBtn = document.getElementById('cancelAddModalBtn');

        function openModal() { addModal.classList.add('active'); }
        function closeModal() { addModal.classList.remove('active'); }

        openModalBtn.addEventListener('click', openModal);
        closeAddModalBtn.addEventListener('click', closeModal);
        cancelAddModalBtn.addEventListener('click', closeModal);

        // Close modal if clicking outside the white box
        addModal.addEventListener('click', (e) => {
            if (e.target === addModal) closeModal();
        });
    </script>
</body>
</html>