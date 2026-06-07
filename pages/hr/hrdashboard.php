<?php
session_start();

// 1. Security Check: Ensure they are logged in and have HR/Admin privileges
if (!isset($_SESSION['user_id'])) {
    // header("Location: login.php");
    // exit();
}
// Uncomment the above in production!

// 2. Database Connection (Ready for when you pull real stats)
// require_once 'includes/db_connect.php';

// Mock variables for the dashboard
$hr_name = "John"; // You would pull this from $_SESSION['first_name']
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard | Vika de Luxe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hrdashboard.css">
</head>
<body>

    <nav class="top-navbar">
        <div class="logo">
            <span class="logo-icon"><i class="ph-fill ph-person-simple-walk"></i></span>
            <span class="logo-text">Vizitor</span>
        </div>
        
        <ul class="nav-links">
            <li><a href="#">Visitor</a></li>
            <li><a href="#">Employee</a></li>
            <li><a href="#" class="active">Attendance</a></li>
            <li><a href="#">Deliveries</a></li>
            <li><a href="#">Setting</a></li>
            <li><a href="#">FAQ?</a></li>
        </ul>

        <div class="user-controls">
            <i class="ph ph-gear"></i>
            <div class="notification">
                <i class="ph ph-bell"></i>
                <span class="badge"></span>
            </div>
            <div class="user-profile">
                <img src="https://ui-avatars.com/api/?name=John+Doe&background=random" alt="User Avatar">
                <span>John Doe <i class="ph ph-caret-down"></i></span>
            </div>
        </div>
    </nav>

    <div class="main-wrapper">
        
        <aside class="sidebar">
            <div class="location-selector">
                <i class="ph ph-map-pin"></i>
                <span>Novagems HQ</span>
                <i class="ph ph-caret-down"></i>
            </div>

            <ul class="sidebar-links">
                <li class="active"><a href="#"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                <li><a href="#"><i class="ph ph-user-focus"></i> Attendance</a></li>
                <li><a href="#"><i class="ph ph-arrows-clockwise"></i> Shifts</a></li>
                <li><a href="#"><i class="ph ph-calendar-blank"></i> Holiday</a></li>
            </ul>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>Welcome back <?php echo htmlspecialchars($hr_name); ?>!</h1>
            </header>

            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-users"></i></div>
                    <div class="stat-info">
                        <h3>254</h3>
                        <p>Total Employees</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-user-minus"></i></div>
                    <div class="stat-info">
                        <h3>10</h3>
                        <p>On Leave</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-house-line"></i></div>
                    <div class="stat-info">
                        <h3>20</h3>
                        <p>Working Remotely</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-check-circle"></i></div>
                    <div class="stat-info">
                        <h3>4</h3>
                        <p>Pending Tasks</p>
                    </div>
                </div>
            </section>

            <section class="data-grid">
                
                <div class="chart-card">
                    <div class="card-header">
                        <h2>Attendance Overview</h2>
                        <div class="chart-filters">
                            <button>Today</button>
                            <button>Month</button>
                            <button class="active">Weekly</button>
                        </div>
                    </div>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="dot present"></span> Present</span>
                        <span class="legend-item"><span class="dot absent"></span> Absents</span>
                        <span class="legend-item"><span class="dot leave"></span> Leave</span>
                    </div>
                    
                    <div class="mock-chart">
                        <div class="chart-group">
                            <div class="bar present" style="height: 60%;"></div>
                            <div class="bar absent" style="height: 80%;"></div>
                            <div class="bar leave" style="height: 40%;"></div>
                            <span class="label">July 4</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 50%;"></div>
                            <div class="bar absent" style="height: 90%;"></div>
                            <div class="bar leave" style="height: 60%;"></div>
                            <span class="label">July 5</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 30%;"></div>
                            <div class="bar absent" style="height: 60%;"></div>
                            <div class="bar leave" style="height: 70%;"></div>
                            <span class="label">July 6</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 75%;"></div>
                            <div class="bar absent" style="height: 95%;"></div>
                            <div class="bar leave" style="height: 45%;"></div>
                            <span class="label">July 7</span>
                        </div>
                    </div>
                </div>

                <div class="holidays-card">
                    <div class="card-header">
                        <h2>Upcoming Holidays</h2>
                    </div>
                    <ul class="holiday-list">
                        <li>
                            <div class="holiday-date"><span class="dot present"></span> Monday 29 July</div>
                            <div class="holiday-name">Eid - Al - Ada</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot leave"></span> Tuesday 15 August</div>
                            <div class="holiday-name">Independence Day</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot absent"></span> Wednesday 16 August</div>
                            <div class="holiday-name">Parsi New Year</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot absent"></span> Tuesday 29 August</div>
                            <div class="holiday-name">Onam</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot absent"></span> Wednesday 16 August</div>
                            <div class="holiday-name">Raksha Bandhan</div>
                        </li>
                    </ul>
                </div>

            </section>
        </main>
    </div>
</body>
</html>