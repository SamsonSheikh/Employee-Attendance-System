<?php
require_once __DIR__ . '/functions.php';
check_admin_login();

$admin_name = isset($_SESSION["username"]) ? $_SESSION["username"] : "Admin";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Configuration | EAS</title>
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
                    <li><a href="../../pages/user-admin/adminusers.php"><i class="ph ph-users"></i> Master Users</a></li>
                    <li><a href="../../pages/user-admin/adminreports.php"><i class="ph ph-file-text"></i> Reports</a></li>
                    <li class="active"><a href="../../pages/user-admin/adminorg.php"><i class="ph ph-buildings"></i> Organization</a></li>
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
                <h1>Organization Configuration</h1>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Manage company structure and leave categories from this admin area.</p>
            </header>

            <section class="security-controls-section">
                <div class="section-header">
                    <h2>Department Manager</h2>
                    <p class="section-desc">Add, rename, or remove departments from the departments table when the database setup is ready.</p>
                </div>

                <div class="security-grid">
                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon password-reset-icon"><i class="ph ph-plus-circle"></i></div>
                            <div class="security-info">
                                <h3>Add Department</h3>
                                <p>Create a new department entry for the organization structure.</p>
                            </div>
                        </div>
                        <form class="security-form">
                            <div class="form-group">
                                <label for="departmentName">Department Name</label>
                                <input type="text" id="departmentName" class="form-control" placeholder="e.g. Human Resources" />
                            </div>
                            <button type="button" class="btn-primary"><i class="ph ph-plus"></i> Add Department</button>
                        </form>
                    </article>

                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon session-icon"><i class="ph ph-pencil-simple"></i></div>
                            <div class="security-info">
                                <h3>Rename Department</h3>
                                <p>Update an existing department name in the organization list.</p>
                            </div>
                        </div>
                        <form class="security-form">
                            <div class="form-group">
                                <label for="departmentSelect">Select Department</label>
                                <select id="departmentSelect" class="form-control">
                                    <option value="">-- Choose a department --</option>
                                    <option value="hr">Human Resources</option>
                                    <option value="it">Information Technology</option>
                                    <option value="finance">Finance</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="departmentNewName">New Department Name</label>
                                <input type="text" id="departmentNewName" class="form-control" placeholder="e.g. People Operations" />
                            </div>
                            <button type="button" class="btn-warning"><i class="ph ph-pencil-line"></i> Rename Department</button>
                        </form>
                    </article>

                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon deactivate-icon"><i class="ph ph-trash"></i></div>
                            <div class="security-info">
                                <h3>Remove Department</h3>
                                <p>Remove a department entry from the configuration list.</p>
                            </div>
                        </div>
                        <form class="security-form">
                            <div class="form-group">
                                <label for="departmentRemove">Select Department</label>
                                <select id="departmentRemove" class="form-control">
                                    <option value="">-- Choose a department --</option>
                                    <option value="hr">Human Resources</option>
                                    <option value="it">Information Technology</option>
                                    <option value="finance">Finance</option>
                                </select>
                            </div>
                            <button type="button" class="btn-danger"><i class="ph ph-prohibit"></i> Remove Department</button>
                        </form>
                    </article>
                </div>
            </section>

            <section class="security-controls-section">
                <div class="section-header">
                    <h2>Leave Type Manager</h2>
                    <p class="section-desc">Define the company’s official time-off categories in the leave_types table when backend support is added.</p>
                </div>

                <div class="security-grid">
                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon password-reset-icon"><i class="ph ph-calendar-plus"></i></div>
                            <div class="security-info">
                                <h3>Add Leave Type</h3>
                                <p>Define official leave categories such as Maternity Leave or Bereavement.</p>
                            </div>
                        </div>
                        <form class="security-form">
                            <div class="form-group">
                                <label for="leaveTypeName">Leave Type Name</label>
                                <input type="text" id="leaveTypeName" class="form-control" placeholder="e.g. Maternity Leave" />
                            </div>
                            <button type="button" class="btn-primary"><i class="ph ph-plus"></i> Add Leave Type</button>
                        </form>
                    </article>

                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon session-icon"><i class="ph ph-list-checks"></i></div>
                            <div class="security-info">
                                <h3>Leave Type Preview</h3>
                                <p>Preview the categories you plan to configure for HR and employee leave workflows.</p>
                            </div>
                        </div>
                        <ul style="padding-left: 1rem; color: var(--text-main); line-height: 1.6;">
                            <li>Annual Leave</li>
                            <li>Sick Leave</li>
                            <li>Maternity Leave</li>
                            <li>Bereavement Leave</li>
                        </ul>
                    </article>
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
