<?php
// Include the authentication function from the same directory
require_once __DIR__ . '/functions.php'; // Change extension to .php if you rename it!
check_admin_login();

// Get admin's name
$admin_name = isset($_SESSION["username"]) ? $_SESSION["username"] : "Admin";
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
            <span class="brand-icon"><i class="ph-fill ph-shield-admin"></i></span>
            <span class="brand-text">Admin Panel</span>
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
                <span class="brand-icon"><i class="ph-fill ph-shield-admin"></i></span>
                <span class="brand-text">Admin Panel</span>
            </div>

            <div class="sidebar-menu-wrapper">
                <ul class="sidebar-links">
                    <li><a href="../../pages/user-admin/admin_dashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li class="active"><a href="../../pages/user-admin/adminusers.php"><i class="ph ph-users"></i> Master Users</a></li>
                    <li><a href="#"><i class="ph ph-gear"></i> Settings</a></li>
                    <li><a href="#"><i class="ph ph-file-text"></i> Reports</a></li>
                </ul>
            </div>
            
            <div class="sidebar-footer"></div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>Master User Management</h1>
            </header>

            <section class="users-section">
                <div class="section-header">
                    <h2>User List</h2>
                    <button class="btn-primary add-user-btn"><i class="ph ph-plus"></i> Add New User</button>
                </div>
                
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
                            <tr>
                                <td>#001</td>
                                <td>John Doe</td>
                                <td>john@example.com</td>
                                <td><span class="badge badge-admin">Admin</span></td>
                                <td><span class="status-badge status-active">Active</span></td>
                                <td class="action-buttons">
                                    <button class="btn-icon edit-btn" title="Edit"><i class="ph ph-pencil"></i></button>
                                    <button class="btn-icon delete-btn" title="Delete"><i class="ph ph-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>#002</td>
                                <td>Jane Smith</td>
                                <td>jane@example.com</td>
                                <td><span class="badge badge-hr">HR</span></td>
                                <td><span class="status-badge status-active">Active</span></td>
                                <td class="action-buttons">
                                    <button class="btn-icon edit-btn" title="Edit"><i class="ph ph-pencil"></i></button>
                                    <button class="btn-icon delete-btn" title="Delete"><i class="ph ph-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>#003</td>
                                <td>Michael Johnson</td>
                                <td>michael@example.com</td>
                                <td><span class="badge badge-employee">Employee</span></td>
                                <td><span class="status-badge status-inactive">Inactive</span></td>
                                <td class="action-buttons">
                                    <button class="btn-icon edit-btn" title="Edit"><i class="ph ph-pencil"></i></button>
                                    <button class="btn-icon delete-btn" title="Delete"><i class="ph ph-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

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
    </script>
</body>
</html>
