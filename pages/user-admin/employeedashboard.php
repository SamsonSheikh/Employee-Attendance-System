<?php
session_start();

// Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/login.php");
    exit();
}

$employee_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "Employee");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard | FlowTime</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/employeedashboard.css">
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
                    <li class="active"><a href="../../pages/user-admin/employeedashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li><a href="#"><i class="ph ph-calendar-check"></i> My Attendance</a></li>
                    <li><a href="#"><i class="ph ph-clock"></i> My Schedule</a></li>
                    <li><a href="#"><i class="ph ph-user"></i> Profile Details</a></li>
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
                <h1>Welcome back, <?php echo htmlspecialchars($employee_name); ?>!</h1>
                <p class="subtitle">This is your personal employee portal. Access your attendance and schedule here.</p>
            </header>

            <section class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="ph ph-calendar-check"></i></div>
                    <h3>My Attendance</h3>
                    <p>View your daily clock-ins and clock-outs.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="ph ph-clock"></i></div>
                    <h3>My Schedule</h3>
                    <p>Check your upcoming assigned shifts.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="ph ph-user"></i></div>
                    <h3>Profile Details</h3>
                    <p>Update your personal information.</p>
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