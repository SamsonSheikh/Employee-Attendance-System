<?php
// Include the authentication function from the same directory
require_once __DIR__ . '/functions.php'; // Change extension to .php if you rename it!
require_once __DIR__ . '/../../includes/db_connect.php';
check_admin_login();

// Get admin's name
$admin_name = isset($_SESSION["username"]) ? $_SESSION["username"] : "Admin";

// Dashboard counters from the existing database tables
$total_users = 0;
$active_users = 0;
$department_total = 0;
$shift_total = 0;

$total_users_result = $conn->query("SELECT COUNT(user_id) AS total_users FROM users");
if ($total_users_result && $total_users_result->num_rows > 0) {
    $total_users = (int) $total_users_result->fetch_assoc()['total_users'];
}

$department_result = $conn->query("SELECT COUNT(department_id) AS total_departments FROM departments");
if ($department_result && $department_result->num_rows > 0) {
    $department_total = (int) $department_result->fetch_assoc()['total_departments'];
}

$shift_result = $conn->query("SELECT COUNT(shift_id) AS total_shifts FROM shifts");
if ($shift_result && $shift_result->num_rows > 0) {
    $shift_total = (int) $shift_result->fetch_assoc()['total_shifts'];
}

// The current users table does not have a separate status flag, so active users
// are counted from the same user records currently present in the system.
$active_users = $total_users;

// Period-based counts for the chart buttons
$daily_users = 0;
$weekly_labels = [];
$weekly_values = [];
$monthly_labels = [];
$monthly_values = [];
$test_floor = 3;

$daily_users_result = $conn->query("SELECT COUNT(user_id) AS total FROM users WHERE created_at >= CURDATE()");
if ($daily_users_result && $daily_users_result->num_rows > 0) {
    $daily_users = (int) $daily_users_result->fetch_assoc()['total'];
}
$daily_users = max($daily_users, $test_floor);

$week_start = (new DateTime('sunday this week'))->format('Y-m-d');
$week_end = (new DateTime('saturday this week'))->format('Y-m-d');

$weekly_result = $conn->query("SELECT DATE(created_at) AS day_date, COUNT(user_id) AS total FROM users WHERE created_at >= '$week_start' AND created_at < DATE_ADD('$week_end', INTERVAL 1 DAY) GROUP BY DATE(created_at) ORDER BY day_date ASC");
$weekly_counts = [];
if ($weekly_result) {
    while ($row = $weekly_result->fetch_assoc()) {
        $weekly_counts[date('Y-m-d', strtotime($row['day_date']))] = (int) $row['total'];
    }
}

for ($i = 0; $i <= 6; $i++) {
    $date = date('Y-m-d', strtotime($week_start . ' + ' . $i . ' days'));
    $weekly_labels[] = date('D', strtotime($date));
    $weekly_values[] = max($weekly_counts[$date] ?? 0, $test_floor - 1);
}

$monthly_result = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(user_id) AS total FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month_key ASC");
$monthly_counts = [];
if ($monthly_result) {
    while ($row = $monthly_result->fetch_assoc()) {
        $monthly_counts[$row['month_key']] = (int) $row['total'];
    }
}

$month_cursor = new DateTime('first day of this month');
for ($offset = 2; $offset >= 0; $offset--) {
    $month = clone $month_cursor;
    $month->modify('-' . $offset . ' month');
    $month_key = $month->format('Y-m');
    $monthly_labels[] = $month->format('M');
    $monthly_values[] = max($monthly_counts[$month_key] ?? 0, $test_floor - 1);
}

$department_test_value = max($department_total, $test_floor - 1);
$shift_test_value = max($shift_total, $test_floor - 1);
$weekly_departments = array_fill(0, count($weekly_labels), $department_test_value);
$weekly_shifts = array_fill(0, count($weekly_labels), $shift_test_value);
$monthly_departments = array_fill(0, count($monthly_labels), $department_test_value);
$monthly_shifts = array_fill(0, count($monthly_labels), $shift_test_value);

$chart_data = [
    'daily' => [
        'labels' => ['Today'],
        'datasets' => [
            ['label' => 'Active Users', 'values' => [$daily_users], 'className' => 'present'],
            ['label' => 'Departments', 'values' => [$department_total], 'className' => 'absent'],
            ['label' => 'Configured Shifts', 'values' => [$shift_total], 'className' => 'leave'],
        ],
    ],
    'weekly' => [
        'labels' => $weekly_labels,
        'datasets' => [
            ['label' => 'Active Users', 'values' => $weekly_values, 'className' => 'present'],
            ['label' => 'Departments', 'values' => $weekly_departments, 'className' => 'absent'],
            ['label' => 'Configured Shifts', 'values' => $weekly_shifts, 'className' => 'leave'],
        ],
    ],
    'monthly' => [
        'labels' => $monthly_labels,
        'datasets' => [
            ['label' => 'Active Users', 'values' => $monthly_values, 'className' => 'present'],
            ['label' => 'Departments', 'values' => $monthly_departments, 'className' => 'absent'],
            ['label' => 'Configured Shifts', 'values' => $monthly_shifts, 'className' => 'leave'],
        ],
    ],
];
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
                <h1>Welcome back <?php echo htmlspecialchars($admin_name); ?>!</h1>
            </header>

            <section class="stats-grid">
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars((string) $total_users); ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-user-check"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars((string) $active_users); ?></h3>
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
                        <h3><?php echo htmlspecialchars((string) $department_total); ?></h3>
                        <p>Departments</p>
                    </div>
                </div>

            </section>

            <section class="data-grid">
                
                <div class="chart-card">
                    <div class="card-header">
                        <div>
                            <h2>System Activity</h2>
                            <p id="chartPeriodLabel" class="section-desc">Showing Daily activity summary</p>
                        </div>
                        <div class="chart-filters">
                            <button class="active" data-period="daily">Daily</button>
                            <button data-period="weekly">Weekly</button>
                            <button data-period="monthly">Monthly</button>
                        </div>
                    </div>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="dot present"></span> Total Active Users</span>
                        <span class="legend-item"><span class="dot absent"></span> Total Departments</span>
                        <span class="legend-item"><span class="dot leave"></span> Total Configured Shifts</span>
                    </div>
                    
                    <div class="mock-chart" id="activityChart"></div>
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

        const chartData = <?php echo json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const chartButtons = document.querySelectorAll('.chart-filters button');
        const chartPeriodLabel = document.getElementById('chartPeriodLabel');
        const activityChart = document.getElementById('activityChart');

        function updateChart(period) {
            const data = chartData[period] || chartData.daily;
            const allValues = [];
            data.datasets.forEach(dataset => {
                dataset.values.forEach(value => allValues.push(value));
            });
            const maxValue = Math.max(1, ...allValues);

            activityChart.innerHTML = '';

            data.labels.forEach((label, labelIndex) => {
                const group = document.createElement('div');
                group.className = 'chart-group';

                const bars = document.createElement('div');
                bars.className = 'chart-bars';

                data.datasets.forEach((dataset, datasetIndex) => {
                    const bar = document.createElement('div');
                    bar.className = 'bar ' + dataset.className;
                    const value = dataset.values[labelIndex] || 0;
                    bar.style.height = Math.max(8, (value / maxValue) * 100) + '%';
                    bar.title = dataset.label + ': ' + value;
                    bars.appendChild(bar);
                });

                const labelEl = document.createElement('span');
                labelEl.className = 'label';
                labelEl.textContent = label;

                group.appendChild(bars);
                group.appendChild(labelEl);
                activityChart.appendChild(group);
            });

            chartButtons.forEach(button => button.classList.toggle('active', button.dataset.period === period));
            const periodText = period === 'weekly' ? '7-day week view' : period === 'monthly' ? 'last 3 months view' : 'today view';
            chartPeriodLabel.textContent = 'Showing ' + period.charAt(0).toUpperCase() + period.slice(1) + ' (' + periodText + ')';
        }

        chartButtons.forEach(button => {
            button.addEventListener('click', function () {
                updateChart(this.dataset.period);
            });
        });

        updateChart('daily');
    </script>
</body>
</html>