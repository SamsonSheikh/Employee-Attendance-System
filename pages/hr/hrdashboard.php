<?php
session_start();

// 1. Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/login.php");
    exit();
}

// 2. Database Connection
require_once '../../includes/db_connect.php';

// 3. Fetch HR User's Name
$hr_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : "Kevin";

// ==========================================================
// 4. DYNAMIC DATABASE QUERIES
// ==========================================================

// Card 1: Total Employees
$emp_query = $conn->query("SELECT COUNT(user_id) AS total FROM users");
$total_employees = $emp_query ? $emp_query->fetch_assoc()['total'] : 0;
$safe_total_employees = max(1, $total_employees); 

// Card 2: Present Today (Updated for 4-punch system)
$present_query = $conn->query("SELECT COUNT(log_id) AS total FROM attendance_logs WHERE log_date = CURDATE() AND (morning_clock_in IS NOT NULL OR afternoon_clock_in IS NOT NULL) AND status != 'Absent'");
$present_today = $present_query ? $present_query->fetch_assoc()['total'] : 0;

// Card 3: Late Arrivals Today
$late_query = $conn->query("SELECT COUNT(log_id) AS total FROM attendance_logs WHERE log_date = CURDATE() AND status = 'Late'");
$late_today = $late_query ? $late_query->fetch_assoc()['total'] : 0;


// ==========================================================
// 5. FETCH DATA FOR THE ATTENDANCE CALENDAR (Last 60 Days)
// ==========================================================
$calendar_data = [];

// Fetch detailed attendance to get names as well
$att_query = "
    SELECT a.log_date, a.status, a.morning_clock_in, a.afternoon_clock_in, u.first_name, u.last_name
    FROM attendance_logs a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.log_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
";
$att_res = $conn->query($att_query);

if ($att_res) {
    while($row = $att_res->fetch_assoc()) {
        $date = $row['log_date'];
        
        // Initialize the array for this date if it doesn't exist yet
        if (!isset($calendar_data[$date])) {
            $calendar_data[$date] = [
                'present' => 0, 
                'absent' => 0,
                'present_list' => [],
                'absent_list' => []
            ];
        }
        
        $name = $row['first_name'] . ' ' . $row['last_name'];
        $is_present = ($row['morning_clock_in'] !== null || $row['afternoon_clock_in'] !== null) && $row['status'] != 'Absent';
        $is_absent = ($row['status'] == 'Absent');
        
        if ($is_present) {
            $calendar_data[$date]['present']++;
            $calendar_data[$date]['present_list'][] = $name;
        } elseif ($is_absent) {
            $calendar_data[$date]['absent']++;
            $calendar_data[$date]['absent_list'][] = $name;
        }
    }
}

$calendar_data_json = json_encode($calendar_data);

// ==========================================================
// 6. FETCH LIVE STATUS FOR TODAY'S TABLE
// ==========================================================
$live_status_query = "
    SELECT u.user_id, u.first_name, u.last_name, d.department_name, 
           a.morning_clock_in, a.morning_clock_out, a.afternoon_clock_in, a.afternoon_clock_out
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN attendance_logs a ON u.user_id = a.user_id AND a.log_date = CURDATE()
    ORDER BY u.first_name ASC
";
$live_status_result = $conn->query($live_status_query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard | FlowTime</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/hrdashboard.css">
    <style>
        /* --- Modal Styles injected here for immediate effect --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex; justify-content: center; align-items: center;
            z-index: 1000;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content {
            background-color: var(--bg-card);
            width: 90%; max-width: 500px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-20px); transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .modal-header h2 { font-size: 1.15rem; color: var(--text-main); }
        .close-modal { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; transition: 0.2s; }
        .close-modal:hover { color: var(--text-main); }
        
        /* Make Calendar Cells Clickable */
        .cal-cell { cursor: pointer; transition: background-color 0.2s; }
        .cal-cell:hover { background-color: #f1f5f9; }
        .cal-cell.blank { cursor: default; }
        .cal-cell.blank:hover { background-color: #fbfbfc; }
    </style>
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
                    <li class="active"><a href="../../pages/hr/hrdashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
                    <li><a href="../../pages/hr/hrattendance.php"><i class="ph ph-user-focus"></i> Attendance</a></li>
                    <li><a href="../../pages/hr/hremployees.php"><i class="ph ph-users"></i> Employees</a></li>
                    <li><a href="../../pages/hr/hrsettings.php"><i class="ph ph-gear"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="sidebar-footer"></div>
        </aside>

        <main class="content">
            <header class="page-header">
                <h1>Welcome back <?php echo htmlspecialchars($hr_name); ?>!</h1>
            </header>

            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($total_employees); ?></h3>
                        <p>Total Employees</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-user-check"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($present_today); ?></h3>
                        <p>Present Today</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-clock"></i></div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($late_today); ?></h3>
                        <p>Late Arrivals</p>
                    </div>
                </div>
            </section>

            <section class="data-grid">
                
                <div class="chart-card">
                    <div class="card-header">
                        <h2>Attendance Overview</h2>
                        <div class="chart-legend">
                            <span class="legend-item"><span class="dot present"></span> Present</span>
                            <span class="legend-item"><span class="dot absent"></span> Absent</span>
                        </div>
                    </div>
                    
                    <div class="calendar-controls">
                        <button id="prevMonth"><i class="ph ph-caret-left"></i> Prev</button>
                        <h3 id="calendarMonth">Month Year</h3>
                        <button id="nextMonth">Next <i class="ph ph-caret-right"></i></button>
                    </div>

                    <div class="calendar-grid" id="calendarGrid">
                        </div>
                </div>

                <div class="holidays-card">
                    <div class="card-header">
                        <h2>Upcoming Holidays</h2>
                    </div>
                    <ul class="holiday-list">
                        <li>
                            <div class="holiday-date"><span class="dot holiday"></span> Monday 29 July</div>
                            <div class="holiday-name">Eid - Al - Ada</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot holiday"></span> Tuesday 15 August</div>
                            <div class="holiday-name">Independence Day</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot holiday"></span> Wednesday 16 August</div>
                            <div class="holiday-name">Parsi New Year</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot holiday"></span> Tuesday 29 August</div>
                            <div class="holiday-name">Onam</div>
                        </li>
                        <li>
                            <div class="holiday-date"><span class="dot holiday"></span> Wednesday 16 August</div>
                            <div class="holiday-name">Raksha Bandhan</div>
                        </li>
                    </ul>
                </div>

            </section>

            <section class="live-status-section" style="margin-top: 1.5rem;">
                <div class="chart-card" style="padding: 0; overflow: hidden;">
                    <div class="card-header" style="padding: 1.5rem 1.5rem 0 1.5rem; margin-bottom: 1rem;">
                        <h2>Today's Live Status</h2>
                    </div>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                            <thead>
                                <tr style="background-color: #f8fafc; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">
                                    <th style="padding: 1rem 1.5rem; text-align: left;">Employee</th>
                                    <th style="padding: 1rem 1.5rem; text-align: left;">Department</th>
                                    <th style="padding: 1rem 1.5rem; text-align: left;">Morning</th>
                                    <th style="padding: 1rem 1.5rem; text-align: left;">Afternoon</th>
                                    <th style="padding: 1rem 1.5rem; text-align: left;">Current Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($live_status_result && $live_status_result->num_rows > 0): ?>
                                    <?php while($row = $live_status_result->fetch_assoc()): 
                                        // Logic to determine live status
                                        $status_text = "Not Arrived";
                                        $status_color = "#718096"; // Gray
                                        $status_bg = "#edf2f7";

                                        if ($row['afternoon_clock_out']) {
                                            $status_text = "Clocked Out (Day End)";
                                            $status_color = "#c53030"; $status_bg = "#fff5f5"; // Red
                                        } elseif ($row['afternoon_clock_in']) {
                                            $status_text = "Clocked In (Afternoon)";
                                            $status_color = "#276749"; $status_bg = "#e6fffa"; // Green
                                        } elseif ($row['morning_clock_out']) {
                                            $status_text = "Clocked Out (Lunch)";
                                            $status_color = "#c05621"; $status_bg = "#fffaf0"; // Orange
                                        } elseif ($row['morning_clock_in']) {
                                            $status_text = "Clocked In (Morning)";
                                            $status_color = "#276749"; $status_bg = "#e6fffa"; // Green
                                        }
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--border-color); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#fbfbfc'" onmouseout="this.style.backgroundColor='transparent'">
                                        <td style="padding: 1rem 1.5rem; font-weight: 500; display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background-color: #edf2f7; color: var(--text-muted); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem;">
                                                <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </td>
                                        <td style="padding: 1rem 1.5rem; color: var(--text-muted); font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td style="padding: 1rem 1.5rem;">
                                            <div style="font-size: 0.85rem; color: var(--text-main); display: flex; flex-direction: column; gap: 0.2rem;">
                                                <span><span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">IN:</span> <?php echo $row['morning_clock_in'] ? date('h:i A', strtotime($row['morning_clock_in'])) : '--:--'; ?></span>
                                                <span><span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">OUT:</span> <?php echo $row['morning_clock_out'] ? date('h:i A', strtotime($row['morning_clock_out'])) : '--:--'; ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem;">
                                            <div style="font-size: 0.85rem; color: var(--text-main); display: flex; flex-direction: column; gap: 0.2rem;">
                                                <span><span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">IN:</span> <?php echo $row['afternoon_clock_in'] ? date('h:i A', strtotime($row['afternoon_clock_in'])) : '--:--'; ?></span>
                                                <span><span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">OUT:</span> <?php echo $row['afternoon_clock_out'] ? date('h:i A', strtotime($row['afternoon_clock_out'])) : '--:--'; ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem;">
                                            <span style="background-color: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted); font-style: italic;">
                                            No employees found in the database.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <div class="modal-overlay" id="dayDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalDateTitle">Date Details</h2>
                <button class="close-modal" id="closeDayModalBtn"><i class="ph ph-x"></i></button>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: flex; gap: 1.5rem;">
                    <div style="flex: 1; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; text-align: center;">
                        <h3 style="color: var(--color-present); font-size: 2rem; margin-bottom: 0.2rem;" id="modalPresentCount">0</h3>
                        <p style="color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">Present</p>
                        
                        <div id="modalPresentList" style="max-height: 180px; overflow-y: auto; text-align: left; font-size: 0.85rem; color: var(--text-main); display: flex; flex-direction: column; gap: 0.3rem;">
                            </div>
                    </div>
                    
                    <div style="flex: 1; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; text-align: center;">
                        <h3 style="color: var(--color-absent); font-size: 2rem; margin-bottom: 0.2rem;" id="modalAbsentCount">0</h3>
                        <p style="color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">Absent</p>
                        
                        <div id="modalAbsentList" style="max-height: 180px; overflow-y: auto; text-align: left; font-size: 0.85rem; color: var(--text-main); display: flex; flex-direction: column; gap: 0.3rem;">
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- Sidebar Logic ---
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


        // --- Modal Logic ---
        const dayModal = document.getElementById('dayDetailsModal');
        const closeDayModalBtn = document.getElementById('closeDayModalBtn');

        function openDayModal(dateStr, dayData) {
            const dateObj = new Date(dateStr);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('modalDateTitle').innerText = dateObj.toLocaleDateString(undefined, options);
            
            document.getElementById('modalPresentCount').innerText = dayData.present;
            document.getElementById('modalAbsentCount').innerText = dayData.absent;
            
            const presentListEl = document.getElementById('modalPresentList');
            const absentListEl = document.getElementById('modalAbsentList');
            
            presentListEl.innerHTML = '';
            absentListEl.innerHTML = '';
            
            if (dayData.present_list && dayData.present_list.length > 0) {
                dayData.present_list.forEach(name => {
                    presentListEl.innerHTML += `<div>• ${name}</div>`;
                });
            } else {
                presentListEl.innerHTML = `<div style="color: var(--text-muted); font-style: italic;">No records</div>`;
            }
            
            if (dayData.absent_list && dayData.absent_list.length > 0) {
                dayData.absent_list.forEach(name => {
                    absentListEl.innerHTML += `<div>• ${name}</div>`;
                });
            } else {
                absentListEl.innerHTML = `<div style="color: var(--text-muted); font-style: italic;">No records</div>`;
            }
            
            dayModal.classList.add('active');
        }

        function closeDayModal() { dayModal.classList.remove('active'); }
        closeDayModalBtn.addEventListener('click', closeDayModal);
        dayModal.addEventListener('click', (e) => { if (e.target === dayModal) closeDayModal(); });


        // --- Dynamic Calendar Logic ---
        const attendanceData = <?php echo $calendar_data_json; ?>;
        let currentDate = new Date();

        function renderCalendar(date) {
            const grid = document.getElementById('calendarGrid');
            grid.innerHTML = ''; 

            const month = date.getMonth();
            const year = date.getFullYear();

            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            document.getElementById('calendarMonth').innerText = `${monthNames[month]} ${year}`;

            const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            daysOfWeek.forEach(d => {
                const el = document.createElement('div');
                el.className = 'cal-header';
                el.innerText = d;
                grid.appendChild(el);
            });

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            for (let i = 0; i < firstDay; i++) {
                const blank = document.createElement('div');
                blank.className = 'cal-cell blank';
                grid.appendChild(blank);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const cell = document.createElement('div');
                cell.className = 'cal-cell';
                
                const monthStr = String(month + 1).padStart(2, '0');
                const dayStr = String(day).padStart(2, '0');
                const dateKey = `${year}-${monthStr}-${dayStr}`;

                const dayData = attendanceData[dateKey] || { present: 0, absent: 0, present_list: [], absent_list: [] };
                
                let statsHtml = '';
                if (dayData.present > 0 || dayData.absent > 0) {
                    statsHtml = `<div class="cal-stats">`;
                    if (dayData.present > 0) {
                        statsHtml += `<span class="stat-badge present">${dayData.present} Present</span>`;
                    }
                    if (dayData.absent > 0) {
                        statsHtml += `<span class="stat-badge absent">${dayData.absent} Absent</span>`;
                    }
                    statsHtml += `</div>`;
                }

                const today = new Date();
                const isToday = (day === today.getDate() && month === today.getMonth() && year === today.getFullYear());
                
                cell.innerHTML = `
                    <span class="cal-day-num ${isToday ? 'today-num' : ''}">${day}</span>
                    ${statsHtml}
                `;
                
                cell.addEventListener('click', () => {
                    openDayModal(dateKey, dayData);
                });
                
                grid.appendChild(cell);
            }
        }

        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar(currentDate);
        });

        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar(currentDate);
        });

        document.addEventListener("DOMContentLoaded", () => {
            renderCalendar(currentDate);
        });
    </script>
</body>
</html>