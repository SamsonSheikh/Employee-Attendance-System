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
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// ==========================================================
// HANDLE FORM SUBMISSIONS (ADD, EDIT, DELETE)
// ==========================================================
$message = "";
$message_type = "success";

// 1. Handle Add Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $dept_id = intval($_POST['department_id']);
    $role_id = intval($_POST['role_id']);
    
    // Set a default temporary password for new accounts
    $default_password = "Password123!";
    $password_hash = password_hash($default_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, department_id, role_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssii", $first_name, $last_name, $email, $password_hash, $dept_id, $role_id);
    
    if ($stmt->execute()) {
        $message = "Employee added successfully! Default password is: Password123!";
    } else {
        $message = "Error adding employee. Email might already exist.";
        $message_type = "error";
    }
    $stmt->close();
}

// 2. Handle Edit Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $edit_id = intval($_POST['edit_user_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $dept_id = intval($_POST['department_id']);
    $role_id = intval($_POST['role_id']);
    
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, department_id = ?, role_id = ? WHERE user_id = ?");
    $stmt->bind_param("ssssii", $first_name, $last_name, $email, $dept_id, $role_id, $edit_id);
    
    if ($stmt->execute()) {
        $message = "Employee profile updated successfully!";
    } else {
        $message = "Error updating employee. Email might be in use by another account.";
        $message_type = "error";
    }
    $stmt->close();
}

// 3. Handle Delete (Deactivate) Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $del_id = intval($_POST['delete_user_id']);
    // Performing a hard delete. The ON DELETE CASCADE in the DB cleans up attendance/schedules.
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $del_id);
    if($stmt->execute()) {
        $message = "Employee account deactivated.";
    }
    $stmt->close();
}

// ==========================================================
// FETCH DATA FOR DISPLAY & FORMS
// ==========================================================

// Capture Search and Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_dept_id = isset($_GET['dept_id']) ? $_GET['dept_id'] : '';

// Fetch Departments (For Modals)
$departments = [];
$depts_res = $conn->query("SELECT * FROM departments ORDER BY department_name ASC");
if($depts_res) {
    while($d = $depts_res->fetch_assoc()) {
        $departments[] = $d;
    }
}

// Fetch Roles into an array so we can reuse it in multiple modals
$roles_array = [];
$roles_res = $conn->query("SELECT * FROM roles ORDER BY role_name ASC");
if($roles_res) {
    while($r = $roles_res->fetch_assoc()){
        $roles_array[] = $r;
    }
}

// Determine selected department name for the filter button
$selected_dept_name = 'All Departments';
if ($filter_dept_id !== '') {
    foreach ($departments as $dept) {
        if ($dept['department_id'] == $filter_dept_id) {
            $selected_dept_name = $dept['department_name'];
            break;
        }
    }
}

// Fetch Users Master List with Search & Filters
$users_query = "
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.created_at, u.department_id, u.role_id,
           d.department_name, r.role_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN roles r ON u.role_id = r.role_id
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search)) {
    $users_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($filter_dept_id !== '') {
    $users_query .= " AND u.department_id = ?";
    $params[] = intval($filter_dept_id);
    $types .= "i";
}

$users_query .= " ORDER BY u.created_at DESC";

$stmt_users = $conn->prepare($users_query);
if (!empty($params)) {
    $stmt_users->bind_param($types, ...$params);
}
$stmt_users->execute();
$users_result = $stmt_users->get_result();

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
    <title>Employees | FlowTime</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hremployees.css">
    <style>
        .search-filter-group { display: flex; gap: 1rem; flex-wrap: wrap; }
        .search-box {
            display: flex; align-items: center; background: var(--bg-card);
            border: 1px solid var(--border-color); border-radius: 6px;
            padding: 0; overflow: hidden;
        }
        .search-box input { border: none; outline: none; padding: 0.55rem 1rem; font-size: 0.9rem; width: 220px; }
        .search-btn {
            background: none; border: none; border-left: 1px solid var(--border-color);
            padding: 0.55rem 0.8rem; color: var(--text-muted); cursor: pointer;
            transition: all 0.2s; display: flex; align-items: center; justify-content: center;
        }
        .search-btn:hover { color: var(--primary-color); background-color: #fff5f2; }

        .filter-dropdown {
            padding: 0.6rem 1rem; border: 1px solid var(--border-color);
            border-radius: 6px; background-color: var(--bg-card);
            color: var(--text-main); font-size: 0.9rem; cursor: pointer; outline: none;
        }
        .dept-select-btn { display: flex; justify-content: space-between; align-items: center; min-width: 200px; text-align: left; }

        .schedule-summary-badges { display: flex; gap: 0.3rem; }
        .day-badge {
            display: flex; align-items: center; justify-content: center;
            width: 24px; height: 24px; border-radius: 4px;
            font-size: 0.7rem; font-weight: 600; cursor: help; transition: 0.2s;
        }
        .day-badge.active-day { background-color: var(--primary-color); color: white; box-shadow: 0 2px 4px rgba(242, 107, 77, 0.2); }
        .day-badge.inactive-day { background-color: #edf2f7; color: #a0aec0; }
        .day-badge:hover { transform: scale(1.1); }

        .dept-list { padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem; max-height: 50vh; overflow-y: auto; }
        .dept-item { padding: 0.75rem 1rem; border-radius: 6px; border: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s; font-size: 0.95rem; color: var(--text-main); }
        .dept-item:hover { background-color: #f8fafc; border-color: #cbd5e1; }
        .dept-item.selected { border-color: var(--primary-color); background-color: #fff5f2; color: var(--primary-color); font-weight: 600; }
    </style>
</head>
<body>

    <header class="mobile-header">
        <div class="sidebar-brand">
            <span class="brand-icon"><i class="ph-fill ph-clock-user"></i></span>
            <span class="brand-text">FlowTime</span>
        </div>
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle Sidebar"><i class="ph ph-list"></i></button>
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
                        <p class="subtitle">Manage staff accounts, roles, and schedules.</p>
                    </div>
                    <button class="btn-primary" id="openAddModalBtn">
                        <i class="ph ph-plus"></i> Add Employee
                    </button>
                </div>
            </header>

            <?php if(!empty($message)): ?>
                <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-danger'; ?>" style="<?php echo $message_type === 'error' ? 'background-color:#fff5f5; color:#c53030; border:1px solid #feb2b2;' : ''; ?>">
                    <i class="ph ph-info"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="action-bar">
                <form method="GET" class="search-filter-group" id="filterForm">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="search-btn" title="Search"><i class="ph ph-magnifying-glass"></i></button>
                    </div>
                    
                    <input type="hidden" name="dept_id" id="filter_dept_id" value="<?php echo htmlspecialchars($filter_dept_id); ?>">
                    <button type="button" class="filter-dropdown dept-select-btn" onclick="openDeptModal()">
                        <span id="filter_dept_text"><?php echo htmlspecialchars($selected_dept_name); ?></span>
                        <i class="ph ph-caret-down"></i>
                    </button>

                    <?php if(!empty($search) || $filter_dept_id !== ''): ?>
                        <a href="hremployees.php" class="btn-secondary" style="display:flex; align-items:center; text-decoration:none; padding: 0.55rem 1rem;">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee Details</th>
                            <th>Department & Role</th>
                            <th>Weekly Schedule</th>
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
                                            <div class="avatar-sm"><?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?></div>
                                            <div class="emp-info">
                                                <span class="emp-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                                <span class="emp-email" style="display:block; font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="role-info">
                                            <span class="dept-badge" style="display:inline-block; margin-bottom:0.2rem; font-weight:600; color:var(--text-main);"><?php echo htmlspecialchars($row['department_name'] ?? 'Unassigned'); ?></span>
                                            <span class="role-text" style="display:block; font-size:0.85rem; color:var(--text-muted);"><?php echo htmlspecialchars($row['role_name'] ?? 'No Role'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="schedule-summary-badges">
                                            <?php 
                                            $short_days = ['Monday'=>'M', 'Tuesday'=>'T', 'Wednesday'=>'W', 'Thursday'=>'Th', 'Friday'=>'F', 'Saturday'=>'S', 'Sunday'=>'Su'];
                                            foreach($days_of_week as $day) {
                                                $sch = isset($schedules[$row['user_id']][$day]) ? $schedules[$row['user_id']][$day] : null;
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
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons" style="display:flex; gap:0.5rem;">
                                            <button type="button" class="btn-icon" title="Edit Profile" 
                                                onclick="openEditModal(
                                                    <?php echo $row['user_id']; ?>, 
                                                    '<?php echo htmlspecialchars(addslashes($row['first_name'])); ?>', 
                                                    '<?php echo htmlspecialchars(addslashes($row['last_name'])); ?>', 
                                                    '<?php echo htmlspecialchars(addslashes($row['email'])); ?>', 
                                                    '<?php echo $row['department_id']; ?>', 
                                                    '<?php echo $row['role_id']; ?>'
                                                )">
                                                <i class="ph ph-pencil-simple"></i>
                                            </button>
                                            
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to deactivate this employee? This action cannot be undone.');">
                                                <input type="hidden" name="delete_user_id" value="<?php echo $row['user_id']; ?>">
                                                <button type="submit" class="btn-icon" title="Deactivate/Delete" style="color: #e53e3e;"><i class="ph ph-user-minus"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="ph ph-users"></i>
                                    <p>No employees found matching your criteria.</p>
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
                        <select name="department_id" class="filter-dropdown" style="width: 100%;" required>
                            <option value="">Select Department</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>System Role</label>
                        <select name="role_id" class="filter-dropdown" style="width: 100%;" required>
                            <option value="">Select Role</option>
                            <?php foreach($roles_array as $role): ?>
                                <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelAddModalBtn">Cancel</button>
                    <button type="submit" name="add_employee" class="btn-primary" style="width: auto;">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="editEmployeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Employee</h2>
                <button class="close-modal" type="button" id="closeEditModalBtn"><i class="ph ph-x"></i></button>
            </div>
            <form method="POST" action="hremployees.php">
                <input type="hidden" name="edit_user_id" id="edit_user_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Email Address</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" id="edit_department_id" class="filter-dropdown" style="width: 100%;" required>
                            <option value="">Select Department</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>System Role</label>
                        <select name="role_id" id="edit_role_id" class="filter-dropdown" style="width: 100%;" required>
                            <option value="">Select Role</option>
                            <?php foreach($roles_array as $role): ?>
                                <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelEditModalBtn">Cancel</button>
                    <button type="submit" name="edit_employee" class="btn-primary" style="width: auto;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="deptModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>Filter by Department</h2>
                <button type="button" class="close-modal" onclick="closeDeptModal()"><i class="ph ph-x"></i></button>
            </div>
            <div class="dept-list">
                <div class="dept-item <?php echo ($filter_dept_id === '') ? 'selected' : ''; ?>" onclick="selectDept('', 'All Departments')">
                    All Departments
                </div>
                <?php foreach($departments as $dept): ?>
                    <div class="dept-item <?php echo ($filter_dept_id == $dept['department_id']) ? 'selected' : ''; ?>" 
                         onclick="selectDept('<?php echo $dept['department_id']; ?>', '<?php echo htmlspecialchars(addslashes($dept['department_name'])); ?>')">
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
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

        // Add Employee Modal Logic
        const addModal = document.getElementById('addEmployeeModal');
        document.getElementById('openAddModalBtn').addEventListener('click', () => addModal.classList.add('active'));
        document.getElementById('closeAddModalBtn').addEventListener('click', () => addModal.classList.remove('active'));
        document.getElementById('cancelAddModalBtn').addEventListener('click', () => addModal.classList.remove('active'));
        addModal.addEventListener('click', (e) => { if (e.target === addModal) addModal.classList.remove('active'); });

        // Edit Employee Modal Logic
        const editModal = document.getElementById('editEmployeeModal');
        
        function openEditModal(userId, firstName, lastName, email, deptId, roleId) {
            // Populate the fields
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_department_id').value = deptId;
            document.getElementById('edit_role_id').value = roleId;
            
            // Show the modal
            editModal.classList.add('active');
        }

        document.getElementById('closeEditModalBtn').addEventListener('click', () => editModal.classList.remove('active'));
        document.getElementById('cancelEditModalBtn').addEventListener('click', () => editModal.classList.remove('active'));
        editModal.addEventListener('click', (e) => { if (e.target === editModal) editModal.classList.remove('active'); });

        // Department Filter Modal Logic
        const deptModal = document.getElementById('deptModal');
        function openDeptModal() { deptModal.classList.add('active'); }
        function closeDeptModal() { deptModal.classList.remove('active'); }
        
        function selectDept(id, name) {
            document.getElementById('filter_dept_id').value = id;
            document.getElementById('filter_dept_text').innerText = name;
            closeDeptModal();
            // Automatically submit the form to apply the filter
            document.getElementById('filterForm').submit();
        }
        
        deptModal.addEventListener('click', (e) => { if (e.target === deptModal) closeDeptModal(); });
    </script>
</body>
</html>