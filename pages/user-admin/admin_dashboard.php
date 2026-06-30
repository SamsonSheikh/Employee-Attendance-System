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

$total_users_result = $conn->query("SELECT COUNT(user_id) AS total_users FROM users");
if ($total_users_result && $total_users_result->num_rows > 0) {
    $total_users = (int) $total_users_result->fetch_assoc()['total_users'];
}

$department_result = $conn->query("SELECT COUNT(department_id) AS total_departments FROM departments");
if ($department_result && $department_result->num_rows > 0) {
    $department_total = (int) $department_result->fetch_assoc()['total_departments'];
}

// Active users are now defined as users who have clocked in today.
$active_users_result = $conn->query("SELECT COUNT(DISTINCT user_id) AS active_today FROM attendance_logs WHERE log_date = CURDATE() AND (morning_clock_in IS NOT NULL OR afternoon_clock_in IS NOT NULL)");
if ($active_users_result && $active_users_result->num_rows > 0) {
    $active_users = (int) $active_users_result->fetch_assoc()['active_today'];
}

// Period-based counts for the chart buttons
$daily_users = 0;
$weekly_labels = [];
$weekly_values = [];
$monthly_labels = [];
$monthly_values = [];

/* =========================
   DAILY USERS
   ========================= */
$daily_users_result = $conn->query("
    SELECT COUNT(user_id) AS total
    FROM users
    WHERE DATE(created_at) = CURDATE()
");

if ($daily_users_result && $daily_users_result->num_rows > 0) {
    $daily_users = (int)$daily_users_result->fetch_assoc()['total'];
}

/* =========================
   WEEKLY USERS (MONDAY-FRIDAY ONLY)
   ========================= */

$weekly_labels = [];
$weekly_values = [];

$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('friday this week'));

$weekly_result = $conn->query("
    SELECT DATE(created_at) AS day_date,
           COUNT(user_id) AS total
    FROM users
    WHERE created_at >= '$week_start'
      AND created_at < DATE_ADD('$week_end', INTERVAL 1 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day_date ASC
");

$weekly_counts = [];

if ($weekly_result) {
    while ($row = $weekly_result->fetch_assoc()) {
        $weekly_counts[$row['day_date']] = (int)$row['total'];
    }
}

/*
    Generates ONLY:
    Mon
    Tue
    Wed
    Thu
    Fri
*/

$weekly_dates = [
    date('Y-m-d', strtotime('monday this week')),
    date('Y-m-d', strtotime('tuesday this week')),
    date('Y-m-d', strtotime('wednesday this week')),
    date('Y-m-d', strtotime('thursday this week')),
    date('Y-m-d', strtotime('friday this week'))
];

foreach ($weekly_dates as $date) {
    $weekly_labels[] = date('D', strtotime($date));
    $weekly_values[] = $weekly_counts[$date] ?? 0;
}

/* =========================
   MONTHLY USERS
   Based on your screenshot:
   Jan = 2
   Feb = 2
   ========================= */

$monthly_labels = [];
$monthly_values = [];

// Generate labels for the last 3 months (e.g., Apr, May, Jun)
for ($i = 2; $i >= 0; $i--) {
    $monthly_labels[] = date('M', strtotime("-$i month"));
}

// For this implementation, we will show the same total stats for each of the last 3 months
// as requested. The values array will just be a placeholder for the dataset structure.
foreach ($monthly_labels as $label) {
    // This data isn't used directly in the chart datasets below, but is kept for structure.
    $monthly_values[] = $active_users; 
}

/* =========================
   DEPARTMENTS
   ========================= */

$department_values = [];
$department_chart_result = $conn->query("
    SELECT d.department_name,
           COUNT(u.user_id) AS total
    FROM departments d
    LEFT JOIN users u
    ON d.department_id = u.department_id
    GROUP BY d.department_id, d.department_name
    ORDER BY total DESC
");

if ($department_chart_result) {
    while ($row = $department_chart_result->fetch_assoc()) {
        $department_values[] = (int)$row['total'];
    }
}

$department_count = count($department_values)
    ? array_sum($department_values)
    : 0;

$chart_data = [
    'daily' => [
        'labels' => ['Today'],
        'datasets' => [
            [
                'label' => 'Total Active Users',
                'values' => [$active_users],
                'className' => 'present'
            ],
            [
                'label' => 'Departments',
                'values' => [$department_total],
                'className' => 'absent'
            ],
        ],
    ],

    'weekly' => [
    'labels' => $weekly_labels,
    'datasets' => [
        [
            'label' => 'Total Active Users',
            'values' => array_fill(
                0,
                count($weekly_labels),
                $active_users
            ),
            'className' => 'present'
        ],
        [
            'label' => 'Total Departments',
            'values' => array_fill(
                0,
                count($weekly_labels),
                $department_total
            ),
            'className' => 'absent'
        ]
    ]
],

    'monthly' => [
    'labels' => $monthly_labels,
    'datasets' => [
        [
            'label' => 'Total Active Users',
            'values' => array_fill(
                0,
                count($monthly_labels),
                $active_users
            ),
            'className' => 'present'
        ],
        [
            'label' => 'Total Departments',
            'values' => array_fill(
                0,
                count($monthly_labels),
                $department_total
            ),
            'className' => 'absent'
        ]
    ]
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
    <style>
        /* Chart tooltip and bottom summary styles */
        .chart-tooltip {
            position: absolute;
            background: rgba(0,0,0,0.85);
            color: #fff;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 13px;
            pointer-events: none;
            z-index: 9999;
            white-space: nowrap;
            transform: translate(-50%, -8px);
        }
        .chart-bottom-summary {
            display: flex;
            gap: 16px;
            margin-top: 12px;
            align-items: center;
        }
        .summary-item { display:flex; align-items:center; gap:8px; color:#333; }
        .summary-item .value { font-weight:600; margin-left:6px; color:#1f2937; }
        /* Keep legend and summary visually consistent */
        .chart-legend, .chart-bottom-summary { flex-wrap:wrap }
    </style>
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
                    <li class="active"><a href="../../pages/user-admin/admin_dashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li><a href="../../pages/user-admin/adminusers.php"><i class="ph ph-users"></i> Master Users</a></li>
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
                    <div class="stat-icon"><i class="ph ph-building"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars((string) $department_total); ?></h3>
                        <p>Departments</p>
                    </div>
                </div>

            </section>

            <section class="data-grid" style="grid-template-columns: 1fr;">
                
                <div class="chart-card">
                    <div class="card-header">
                        <div>
                            <h2>System Activity</h2>
                            <p id="chartPeriodLabel" class="section-desc">Showing Daily activity summary</p>
                        </div>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <div class="chart-filters">
                                <button class="active" data-period="daily">Daily</button>
                                <button data-period="weekly">Weekly</button>
                                <button data-period="monthly">Monthly</button>
                            </div>
                            <button class="btn-secondary" style="padding: 0.4rem 1rem;">
                                <i class="ph ph-download-simple"></i> Export CSV
                            </button>
                        </div>
                    </div>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="dot present"></span> Total Active Users</span>
                        <span class="legend-item"><span class="dot absent"></span> Total Departments</span>
                    </div>
                    
                    <div class="mock-chart" id="activityChart"></div>
                    <div class="chart-bottom-summary" id="chartBottomSummary" aria-hidden="false">
                        <div class="summary-item">
                            <span class="dot present"></span>
                            <span class="summary-label">Total Active Users</span>
                            <span class="value" id="summaryActive">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="dot absent"></span>
                            <span class="summary-label">Total Departments</span>
                            <span class="value" id="summaryDepartments">0</span>
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

        const chartData = <?php echo json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const chartButtons = document.querySelectorAll('.chart-filters button');
        const chartPeriodLabel = document.getElementById('chartPeriodLabel');
        const activityChart = document.getElementById('activityChart');
        const summaryActive = document.getElementById('summaryActive');
        const summaryDepartments = document.getElementById('summaryDepartments');

        // Tooltip element (single reusable)
        const tooltip = document.createElement('div');
        tooltip.className = 'chart-tooltip';
        tooltip.style.display = 'none';
        document.body.appendChild(tooltip);

        function updateChart(period) {
            updateBarChart(period);
            chartButtons.forEach(button => button.classList.toggle('active', button.dataset.period === period));
            const periodText = period === 'weekly' ? '5-day workweek view' : period === 'monthly' ? 'last 3 months view' : 'today view';
            chartPeriodLabel.textContent = 'Showing ' + period.charAt(0).toUpperCase() + period.slice(1) + ' (' + periodText + ')';
        }

        function updateBarChart(period) {
            const data = chartData[period] || chartData.daily;
            const allValues = [];
            data.datasets.forEach(dataset => {
                dataset.values.forEach(value => allValues.push(value));
            });
            const maxValue = Math.max(1, ...allValues);

            activityChart.innerHTML = '';
            activityChart.className = 'mock-chart'; // Reset to vertical
            document.getElementById('chartBottomSummary').style.display = 'flex';

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

                // hover handlers to show tooltip and update bottom summary
                group.addEventListener('mouseenter', (ev) => {
                    // build tooltip content
                    let html = '<strong>' + label + '</strong><br>';
                    data.datasets.forEach(dataset => {
                        const value = dataset.values[labelIndex] || 0;
                        html += '<span style="display:inline-block;width:8px;height:8px;background:' + getComputedStyle(document.querySelector('.' + dataset.className)).backgroundColor + ';margin-right:8px;border-radius:2px;vertical-align:middle;"></span>' + dataset.label + ': ' + value + '<br>';
                    });
                    tooltip.innerHTML = html;
                    tooltip.style.display = 'block';
                    positionTooltip(ev);

                    // update bottom summary values to the hovered group's values
                    const a = data.datasets[0].values[labelIndex] || 0;
                    const d = data.datasets[1].values[labelIndex] || 0;
                    summaryActive.textContent = a;
                    summaryDepartments.textContent = d;
                });

                group.addEventListener('mousemove', (ev) => positionTooltip(ev));
                group.addEventListener('mouseleave', () => {
                    tooltip.style.display = 'none';
                    // reset summary to last label (default)
                    const lastIndex = data.labels.length - 1;
                    summaryActive.textContent = data.datasets[0].values[lastIndex] || 0;
                    summaryDepartments.textContent = data.datasets[1].values[lastIndex] || 0;
                });
            });

            // set default bottom summary to the last label values
            const lastIndex = data.labels.length - 1;
            summaryActive.textContent = data.datasets[0].values[lastIndex] || 0;
            summaryDepartments.textContent = data.datasets[1].values[lastIndex] || 0;
        }

        function positionTooltip(ev) {
            const padding = 10;
            const rect = ev.target.getBoundingClientRect();
            const x = rect.left + rect.width / 2;
            const y = rect.top - padding;
            tooltip.style.left = x + 'px';
            tooltip.style.top = y + 'px';
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