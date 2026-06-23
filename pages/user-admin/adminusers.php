<?php
// Include the authentication function from the same directory
require_once __DIR__ . '/functions.php';
check_admin_login();

require_once __DIR__ . '/../../includes/db_connect.php';

// Get admin's name
$admin_name = isset($_SESSION["username"]) ? $_SESSION["username"] : "Admin";

$flash = [
    'type' => null,
    'message' => null,
];

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$action = $_GET['action'] ?? null;

// --- Add User backend ---
if ($action === 'add_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = (string)($_POST['password'] ?? '');
        $role_id    = $_POST['role_id'] ?? null;
        $department_id = $_POST['department_id'] ?? null;
        $shift_id       = $_POST['shift_id'] ?? null;

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $role_id === null || $role_id === '') {
            throw new Exception('Missing required fields.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address.');
        }

        // Ensure role exists
        $role_id_int = (int)$role_id;
        $stmt = $conn->prepare('SELECT role_id FROM roles WHERE role_id = ?');
        $stmt->bind_param('i', $role_id_int);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception('Selected role is invalid.');
        }
        $stmt->close();

        $department_id_int = null;
        if ($department_id !== null && $department_id !== '') {
            $department_id_int = (int)$department_id;
        }

        $shift_id_int = null;
        if ($shift_id !== null && $shift_id !== '') {
            $shift_id_int = (int)$shift_id;
        }

        // Email uniqueness
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception('Email already exists.');
        }
        $stmt->close();

        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters.');
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, department_id, role_id, shift_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $department_param = $department_id_int;
        $shift_param = $shift_id_int;

        $stmt->bind_param(
            'ssssiii',
            $first_name,
            $last_name,
            $email,
            $password_hash,
            $department_param,
            $role_id_int,
            $shift_param
        );

        $stmt->execute();
        $stmt->close();

        $flash['type'] = 'success';
        $flash['message'] = 'User added successfully.';
        header('Location: adminusers.php?flash=1');
        exit;
    } catch (Throwable $e) {
        $flash['type'] = 'error';
        $flash['message'] = $e->getMessage();
        // Fall through to show flash; re-render page below
    }

// --- Bulk Add Users backend ---
} elseif ($action === 'bulk_add_users' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csvText = (string)($_POST['bulk_csv'] ?? '');
        $defaultRoleId = $_POST['role_id'] ?? '';
        $defaultDepartmentId = $_POST['department_id'] ?? '';
        $defaultShiftId = $_POST['shift_id'] ?? '';

        $csvText = trim($csvText);
        if ($csvText === '') {
            throw new Exception('Please paste CSV/TSV data to bulk add users.');
        }

        $role_id_int = (int)$defaultRoleId;
        if ($defaultRoleId === '' || $defaultRoleId === null || $role_id_int <= 0) {
            throw new Exception('Default Role is required for bulk add.');
        }

        // Ensure default role exists
        $stmt = $conn->prepare('SELECT role_id FROM roles WHERE role_id = ?');
        $stmt->bind_param('i', $role_id_int);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception('Selected default role is invalid.');
        }
        $stmt->close();

        $department_id_int_default = null;
        if ($defaultDepartmentId !== null && $defaultDepartmentId !== '') {
            $department_id_int_default = (int)$defaultDepartmentId;
        }

        $shift_id_int_default = null;
        if ($defaultShiftId !== null && $defaultShiftId !== '') {
            $shift_id_int_default = (int)$defaultShiftId;
        }

        // Split lines (support both CSV and TSV by detecting delimiter per line)
        $lines = preg_split('/\r\n|\r|\n/', $csvText);
        if ($lines === false) {
            throw new Exception('Failed to parse bulk input.');
        }

        // If the first row looks like a header, skip it
        $lineIndex = 0;
        if (!empty($lines[0])) {
            $headerCandidate = strtolower(trim($lines[0]));
            if (str_contains($headerCandidate, 'first') && str_contains($headerCandidate, 'email')) {
                $lineIndex = 1;
            }
        }

        $added = 0;
        $errors = [];

        $conn->begin_transaction();

        for ($i = $lineIndex; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            // Choose delimiter
            $delimiter = str_contains($line, '\t') ? "\t" : ',';
            $parts = str_getcsv($line, $delimiter);
            if ($parts === false) {
                $errors[] = 'Row ' . ($i + 1) . ': Unable to parse line.';
                continue;
            }

            // Expected columns (8) OR subset with defaults:
            // first_name,last_name,email,password,role_id,department_id,shift_id,status(optional)
            // If role_id/department_id/shift_id missing, use defaults.
            $first_name = trim($parts[0] ?? '');
            $last_name  = trim($parts[1] ?? '');
            $email      = trim($parts[2] ?? '');
            $password   = (string)trim($parts[3] ?? '');

            $rowRoleId = trim($parts[4] ?? '');
            $rowDepartmentId = trim($parts[5] ?? '');
            $rowShiftId = trim($parts[6] ?? '');

            if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
                $errors[] = 'Row ' . ($i + 1) . ': Missing required fields (first_name,last_name,email,password).';
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Row ' . ($i + 1) . ': Invalid email address.';
                continue;
            }

            $passwordLen = strlen($password);
            if ($passwordLen < 8) {
                $errors[] = 'Row ' . ($i + 1) . ': Password must be at least 8 characters.';
                continue;
            }

            $rowRoleIdInt = $rowRoleId !== '' ? (int)$rowRoleId : $role_id_int;
            if ($rowRoleId !== '' && $rowRoleIdInt <= 0) {
                $errors[] = 'Row ' . ($i + 1) . ': Invalid role_id.';
                continue;
            }

            $rowDepartmentIdInt = null;
            if ($rowDepartmentId !== '') {
                $rowDepartmentIdInt = (int)$rowDepartmentId;
            } else {
                $rowDepartmentIdInt = $department_id_int_default;
            }

            $rowShiftIdInt = null;
            if ($rowShiftId !== '') {
                $rowShiftIdInt = (int)$rowShiftId;
            } else {
                $rowShiftIdInt = $shift_id_int_default;
            }

            // Role exists check (only if role_id provided or default might be valid; still safe)
            $stmt = $conn->prepare('SELECT role_id FROM roles WHERE role_id = ?');
            $stmt->bind_param('i', $rowRoleIdInt);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $errors[] = 'Row ' . ($i + 1) . ': role_id does not exist.';
                $stmt->close();
                continue;
            }
            $stmt->close();

            // Email uniqueness
            $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = 'Row ' . ($i + 1) . ': Email already exists (' . $email . ').';
                $stmt->close();
                continue;
            }
            $stmt->close();

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                'INSERT INTO users (first_name, last_name, email, password_hash, department_id, role_id, shift_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $department_param = $rowDepartmentIdInt;
            $shift_param = $rowShiftIdInt;

            $stmt->bind_param(
                'ssssiii',
                $first_name,
                $last_name,
                $email,
                $password_hash,
                $department_param,
                $rowRoleIdInt,
                $shift_param
            );

            $stmt->execute();
            $stmt->close();
            $added++;
        }

        if (!empty($errors) && $added === 0) {
            $conn->rollback();
            $flash['type'] = 'error';
            $flash['message'] = 'Bulk add failed: ' . htmlspecialchars($errors[0], ENT_QUOTES, 'UTF-8');
        } else {
            // Commit even if some rows failed (partial success)
            $conn->commit();
            $flash['type'] = empty($errors) ? 'success' : 'error';
            $flash['message'] = 'Bulk add complete. Added: ' . $added . '. Failed: ' . count($errors) . '.';
            if (!empty($errors)) {
                $flash['errors'] = array_slice($errors, 0, 5);
            }
        }

        // Redirect back to show flash; preserve message via session not implemented, so use query string.
        // Keep it simple: redirect and show generic message. (Still updates DB for added rows.)
        header('Location: adminusers.php?flash=bulk');
        exit;

    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        $flash['type'] = 'error';
        $flash['message'] = $e->getMessage();
    }
}

    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = (string)($_POST['password'] ?? '');
        $role_id    = $_POST['role_id'] ?? null;
        $department_id = $_POST['department_id'] ?? null;
        $shift_id       = $_POST['shift_id'] ?? null;

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $role_id === null || $role_id === '') {
            throw new Exception('Missing required fields.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address.');
        }

        // Ensure role exists
        $role_id_int = (int)$role_id;
        $stmt = $conn->prepare('SELECT role_id FROM roles WHERE role_id = ?');
        $stmt->bind_param('i', $role_id_int);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception('Selected role is invalid.');
        }
        $stmt->close();

        $department_id_int = null;
        if ($department_id !== null && $department_id !== '') {
            $department_id_int = (int)$department_id;
        }

        $shift_id_int = null;
        if ($shift_id !== null && $shift_id !== '') {
            $shift_id_int = (int)$shift_id;
        }

        // Email uniqueness
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception('Email already exists.');
        }
        $stmt->close();

        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters.');
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, department_id, role_id, shift_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $department_param = $department_id_int;
        $shift_param = $shift_id_int;

        $stmt->bind_param(
            'ssssiii',
            $first_name,
            $last_name,
            $email,
            $password_hash,
            $department_param,
            $role_id_int,
            $shift_param
        );

        $stmt->execute();
        $stmt->close();


        $flash['type'] = 'success';
        $flash['message'] = 'User added successfully.';
        header('Location: adminusers.php?flash=1');
        exit;
    } catch (Throwable $e) {
        $flash['type'] = 'error';
        $flash['message'] = $e->getMessage();
        // Fall through to show flash; re-render page below
    }


// Simple flash via redirect
if (isset($_GET['flash']) && $_GET['flash'] === '1') {
    $flash['type'] = 'success';
    $flash['message'] = 'User added successfully.';
}
if (isset($_GET['flash']) && $_GET['flash'] === 'bulk') {
    $flash['type'] = 'success';
    $flash['message'] = 'Bulk add processed. Please check the user list.';
}
if (isset($_GET['error']) && $_GET['error'] === '1') {
    $flash['type'] = 'error';
    $flash['message'] = 'Failed to add user.';
}

// --- Load select options ---
$roles = [];
$stmt = $conn->query('SELECT role_id, role_name FROM roles ORDER BY role_name ASC');
while ($row = $stmt->fetch_assoc()) {
    $roles[] = $row;
}

$departments = [];
$stmt = $conn->query('SELECT department_id, department_name FROM departments ORDER BY department_name ASC');
while ($row = $stmt->fetch_assoc()) {
    $departments[] = $row;
}

$shifts = [];
$stmt = $conn->query('SELECT shift_id, shift_name FROM shifts ORDER BY shift_name ASC');
while ($row = $stmt->fetch_assoc()) {
    $shifts[] = $row;
}

// --- Load user list ---
$users = [];
$sql = 'SELECT u.user_id, u.first_name, u.last_name, u.email,
               r.role_name,
               u.department_id,
               d.department_name,
               s.shift_name,
               u.created_at
        FROM users u
        LEFT JOIN roles r ON r.role_id = u.role_id
        LEFT JOIN departments d ON d.department_id = u.department_id
        LEFT JOIN shifts s ON s.shift_id = u.shift_id
        ORDER BY u.user_id DESC';

$stmt = $conn->query($sql);
while ($row = $stmt->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Users | EAS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>

    <header class="mobile-header">
        <div class="admin-brand">
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

            <div class="admin-brand desktop-brand">
                <span class="brand-icon"><i class="ph-fill ph-clock-user"></i></span>
                <span class="brand-text">FlowTime</span>
            </div>

            <div class="sidebar-menu-wrapper">
                <ul class="sidebar-links">
                    <li><a href="../../pages/user-admin/admin_dashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li class="active"><a href="../../pages/user-admin/adminusers.php"><i class="ph ph-users"></i> Master Users</a></li>
                    <li><a href="../../pages/user-admin/adminreports.php"><i class="ph ph-file-text"></i> Reports</a></li>
                    <li><a href="../../pages/user-admin/adminorg.php"><i class="ph ph-buildings"></i> Organization</a></li>
                    <li><a href="#"><i class="ph ph-gear"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <a href="../../pages/public/logout.php" class="sidebar-logout">
                    <i class="ph ph-sign-out"></i> Logout
                </a>
            </div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>Master User Management</h1>
            </header>

            <section class="users-section">
                <div class="section-header">
                    <h2>User List</h2>
                    <button class="btn-primary add-user-btn" type="button" onclick="document.getElementById('addUserModal').style.display='block'">
                        <i class="ph ph-plus"></i> Add New User
                    </button>
                </div>

                <?php if (!empty($flash['type']) && !empty($flash['message'])): ?>
                    <div style="margin: 12px 0; padding: 10px 12px; border-radius: 10px; background: <?php echo $flash['type'] === 'success' ? '#dcfce7' : '#fee2e2'; ?>; color: #111; border: 1px solid <?php echo $flash['type'] === 'success' ? '#86efac' : '#fca5a5'; ?>">
                        <?php echo h($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>#<?php echo h($u['user_id']); ?></td>
                                        <td><?php echo h($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                        <td><?php echo h($u['email']); ?></td>
                                        <td>
                                            <?php
                                                $roleName = $u['role_name'] ?? '';
                                                $badgeClass = 'badge-employee';
                                                $badgeText = 'Employee';
                                                if (strcasecmp($roleName, 'Administrator') === 0) {
                                                    $badgeClass = 'badge-admin';
                                                    $badgeText = 'Admin';
                                                } elseif (stripos($roleName, 'Manager') !== false || strcasecmp($roleName, 'Supervisor') === 0) {
                                                    $badgeClass = 'badge-hr';
                                                    $badgeText = 'HR';
                                                }
                                            ?>
                                            <span class="badge <?php echo h($badgeClass); ?>"><?php echo h($badgeText); ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-active">Active</span>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn-icon edit-btn" title="Edit" type="button"><i class="ph ph-pencil"></i></button>
                                            <button class="btn-icon delete-btn" title="Delete" type="button"><i class="ph ph-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Add User Modal -->
            <div id="addUserModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999;">
                <div style="background:#fff; max-width:640px; margin:60px auto; padding:18px 18px; border-radius:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h2 style="margin:0; font-size:18px;">Add New User</h2>
                        <button type="button" class="btn-secondary" onclick="document.getElementById('addUserModal').style.display='none'">Close</button>
                    </div>

                    <form method="POST" action="adminusers.php?action=add_user">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input id="first_name" name="first_name" class="form-control" required />
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input id="last_name" name="last_name" class="form-control" required />
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="email">Email <span class="required">*</span></label>
                                <input id="email" name="email" type="email" class="form-control" required />
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="password">Password <span class="required">*</span></label>
                                <input id="password" name="password" type="password" class="form-control" required minlength="8" />
                                <small style="display:block; margin-top:6px; color:#666;">Minimum 8 characters.</small>
                            </div>
                            <div class="form-group">
                                <label for="role_id">Role <span class="required">*</span></label>
                                <select id="role_id" name="role_id" class="form-control" required>
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo h($r['role_id']); ?>"><?php echo h($r['role_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="department_id">Department</label>
                                <select id="department_id" name="department_id" class="form-control">
                                    <option value="">-- Optional --</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?php echo h($d['department_id']); ?>"><?php echo h($d['department_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="shift_id">Shift</label>
                                <select id="shift_id" name="shift_id" class="form-control">
                                    <option value="">-- Optional --</option>
                                    <?php foreach ($shifts as $s): ?>
                                        <option value="<?php echo h($s['shift_id']); ?>"><?php echo h($s['shift_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                            <button type="button" class="btn-secondary" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
                            <button type="submit" class="btn-primary"><i class="ph ph-plus"></i> Create User</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Role Assignment Section -->
            <section class="role-assignment-section">
                <div class="section-header">
                    <h2>Role Assignment</h2>
                    <p class="section-desc">Securely assign or modify user roles. Changes are logged for audit purposes.</p>
                </div>

                <div class="role-assignment-card">
                    <form class="role-assignment-form" id="roleAssignmentForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="userSelect">Select User <span class="required">*</span></label>
                                <select id="userSelect" name="user_id" class="form-control" required>
                                    <option value="">-- Choose a user --</option>
                                    <option value="001">John Doe (Admin)</option>
                                    <option value="002">Jane Smith (HR)</option>
                                    <option value="003">Michael Johnson (Employee)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="newRole">New Role <span class="required">*</span></label>
                                <select id="newRole" name="new_role" class="form-control" required>
                                    <option value="">-- Select role --</option>
                                    <option value="admin">Admin</option>
                                    <option value="hr">HR Manager</option>
                                    <option value="employee">Employee</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="reason">Reason for Change <span class="required">*</span></label>
                                <textarea id="reason" name="reason" class="form-control" rows="2" placeholder="Enter reason for role change..." required></textarea>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary"><i class="ph ph-check"></i> Assign Role</button>
                            <button type="reset" class="btn-secondary"><i class="ph ph-x"></i> Clear</button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Security Controls Section -->
            <section class="security-controls-section">
                <div class="section-header">
                    <h2>Security Controls</h2>
                    <p class="section-desc">Emergency actions for user management and security breach response.</p>
                </div>

                <div class="security-grid">
                    <!-- Force Password Reset -->
                    <div class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon password-reset-icon"><i class="ph ph-key"></i></div>
                            <div class="security-info">
                                <h3>Force Password Reset</h3>
                                <p>Require user to reset password on next login</p>
                            </div>
                        </div>
                        <form class="security-form" id="forcePasswordResetForm">
                            <div class="form-group">
                                <label for="passwordResetUser">Select User <span class="required">*</span></label>
                                <select id="passwordResetUser" name="user_id" class="form-control" required>
                                    <option value="">-- Choose a user --</option>
                                    <option value="001">John Doe</option>
                                    <option value="002">Jane Smith</option>
                                    <option value="003">Michael Johnson</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-warning"><i class="ph ph-warning"></i> Force Reset</button>
                        </form>
                    </div>

                    <!-- Instant User Deactivation -->
                    <div class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon deactivate-icon"><i class="ph ph-lock"></i></div>
                            <div class="security-info">
                                <h3>Instant User Deactivation</h3>
                                <p>Immediately deactivate account (Emergency mode)</p>
                            </div>
                        </div>
                        <form class="security-form" id="deactivateUserForm">
                            <div class="form-group">
                                <label for="deactivateUser">Select User <span class="required">*</span></label>
                                <select id="deactivateUser" name="user_id" class="form-control" required>
                                    <option value="">-- Choose a user --</option>
                                    <option value="001">John Doe</option>
                                    <option value="002">Jane Smith</option>
                                    <option value="003">Michael Johnson</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="deactivationReason">Security Incident Note <span class="required">*</span></label>
                                <textarea id="deactivationReason" name="reason" class="form-control" rows="2" placeholder="Document the security breach or reason..." required></textarea>
                            </div>
                            <button type="submit" class="btn-danger" onclick="return confirm('⚠️ WARNING: This will immediately deactivate the user account. Proceed?');"><i class="ph ph-prohibit"></i> Deactivate Account</button>
                        </form>
                    </div>

                    <!-- Session Termination -->
                    <div class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon session-icon"><i class="ph ph-sign-out"></i></div>
                            <div class="security-info">
                                <h3>Terminate All Sessions</h3>
                                <p>Force logout user from all devices</p>
                            </div>
                        </div>
                        <form class="security-form" id="terminateSessionForm">
                            <div class="form-group">
                                <label for="sessionTerminateUser">Select User <span class="required">*</span></label>
                                <select id="sessionTerminateUser" name="user_id" class="form-control" required>
                                    <option value="">-- Choose a user --</option>
                                    <option value="001">John Doe</option>
                                    <option value="002">Jane Smith</option>
                                    <option value="003">Michael Johnson</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-danger"><i class="ph ph-sign-out"></i> Terminate Sessions</button>
                        </form>
                    </div>
                </div>

                <!-- Audit Log -->
                <div class="audit-log-section">
                    <h3>Recent Security Actions</h3>
                    <div class="audit-log">
                        <div class="audit-entry">
                            <div class="audit-timestamp">2 hours ago</div>
                            <div class="audit-action">User deactivated: Jane Smith (#002)</div>
                            <div class="audit-user">Admin: John Doe</div>
                        </div>
                        <div class="audit-entry">
                            <div class="audit-timestamp">5 hours ago</div>
                            <div class="audit-action">Password reset forced: Michael Johnson (#003)</div>
                            <div class="audit-user">Admin: John Doe</div>
                        </div>
                        <div class="audit-entry">
                            <div class="audit-timestamp">1 day ago</div>
                            <div class="audit-action">Role changed: Michael Johnson - Employee → HR Manager</div>
                            <div class="audit-user">Admin: John Doe</div>
                        </div>
                    </div>
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

        // Role Assignment Form Handler
        document.getElementById('roleAssignmentForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('userSelect').value;
            const newRole = document.getElementById('newRole').value;
            const reason = document.getElementById('reason').value;

            if (!userId || !newRole) {
                alert('Please select both a user and a new role');
                return;
            }

            const confirmation = confirm(`Confirm: Change this user's role to "${newRole}"?\n\nReason: ${reason}`);
            if (confirmation) {
                alert('Role assignment submitted! (Integration needed with backend)');
                this.reset();
            }
        });

        // Force Password Reset Form Handler
        document.getElementById('forcePasswordResetForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('passwordResetUser').value;

            if (!userId) {
                alert('Please select a user');
                return;
            }

            if (confirm('Force this user to reset their password on next login?')) {
                alert('Password reset enforced! (Integration needed with backend)');
                this.reset();
            }
        });

        // Deactivate User Form Handler
        document.getElementById('deactivateUserForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('deactivateUser').value;
            const reason = document.getElementById('deactivationReason').value;

            if (!userId || !reason) {
                alert('Please select a user and provide a reason');
                return;
            }
        });

        // Terminate Sessions Form Handler
        document.getElementById('terminateSessionForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('sessionTerminateUser').value;

            if (!userId) {
                alert('Please select a user');
                return;
            }

            if (confirm('Terminate all active sessions for this user?')) {
                alert('All sessions terminated! (Integration needed with backend)');
                this.reset();
            }
        });

        // Add User Modal close on outside click
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('addUserModal');
            if (modal && e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // (placeholder) end
    </script>
</body>
</html>
