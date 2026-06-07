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
    <title>Admin Dashboard | EAS</title>
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
                    <li class="active"><a href="../../pages/user-admin/admin_dashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li><a href="../../pages/user-admin/adminusers.php"><i class="ph ph-users"></i> Master Users</a></li>
                    <li><a href="#"><i class="ph ph-gear"></i> Settings</a></li>
                    <li><a href="#"><i class="ph ph-file-text"></i> Reports</a></li>
                </ul>
            </div>
            
            <div class="sidebar-footer"></div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>Welcome back <?php echo htmlspecialchars($admin_name); ?>!</h1>
            </header>

            <section class="stats-grid">
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-users"></i></div>
                    <div class="stat-info">
                        <h3>2,492</h3>
                        <p>Total Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-user-check"></i></div>
                    <div class="stat-info">
                        <h3>1,842</h3>
                        <p>Active Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-warning"></i></div>
                    <div class="stat-info">
                        <h3>28</h3>
                        <p>Pending Issues</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-building"></i></div>
                    <div class="stat-info">
                        <h3>12</h3>
                        <p>Departments</p>
                    </div>
                </div>

            </section>

            <section class="data-grid">
                
                <div class="chart-card">
                    <div class="card-header">
                        <h2>System Activity</h2>
                        <div class="chart-filters">
                            <button>Today</button>
                            <button>Month</button>
                            <button class="active">Weekly</button>
                        </div>
                    </div>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="dot present"></span> Total Active Users</span>
                        <span class="legend-item"><span class="dot absent"></span> Total Departments</span>
                        <span class="legend-item"><span class="dot leave"></span> Total Configured Shifts</span>
                    </div>
                    
                    <div class="mock-chart">
                        <div class="chart-group">
                            <div class="bar present" style="height: 60%;"></div>
                            <div class="bar absent" style="height: 80%;"></div>
                            <div class="bar leave" style="height: 40%;"></div>
                            <span class="label">Monday</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 50%;"></div>
                            <div class="bar absent" style="height: 90%;"></div>
                            <div class="bar leave" style="height: 60%;"></div>
                            <span class="label">Tuesday</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 30%;"></div>
                            <div class="bar absent" style="height: 60%;"></div>
                            <div class="bar leave" style="height: 70%;"></div>
                            <span class="label">Wednesday</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 75%;"></div>
                            <div class="bar absent" style="height: 95%;"></div>
                            <div class="bar leave" style="height: 45%;"></div>
                            <span class="label">Thursday</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 65%;"></div>
                            <div class="bar absent" style="height: 70%;"></div>
                            <div class="bar leave" style="height: 50%;"></div>
                            <span class="label">Friday</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 40%;"></div>
                            <div class="bar absent" style="height: 45%;"></div>
                            <div class="bar leave" style="height: 30%;"></div>
                            <span class="label">Saturday</span>
                        </div>
                    </div>
                </div>

                <div class="holidays-card">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <ul class="holiday-list">
                        <li>
                            <div class="holiday-date"><i class="ph ph-user-plus"></i> User Added</div>
                            <div class="holiday-name">2 hours ago</div>
                        </li>
                        <li>
                            <div class="holiday-date"><i class="ph ph-user-minus"></i> User Removed</div>
                            <div class="holiday-name">5 hours ago</div>
                        </li>
                        <li>
                            <div class="holiday-date"><i class="ph ph-key"></i> Password Reset</div>
                            <div class="holiday-name">12 hours ago</div>
                        </li>
                        <li>
                            <div class="holiday-date"><i class="ph ph-warning"></i> Security Alert</div>
                            <div class="holiday-name">1 day ago</div>
                        </li>
                        <li>
                            <div class="holiday-date"><i class="ph ph-backup"></i> System Backup</div>
                            <div class="holiday-name">2 days ago</div>
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