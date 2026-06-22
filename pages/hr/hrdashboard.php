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

<<<<<<< HEAD
// Card 2: Present Today
$present_query = $conn->query("SELECT COUNT(log_id) AS total FROM attendance_logs WHERE log_date = CURDATE() AND clock_in IS NOT NULL AND status != 'Absent'");
=======
// Card 2: Present Today (From `attendance_logs` table for CURDATE)
$present_query = $conn->query("SELECT COUNT(log_id) AS total FROM attendance_logs WHERE log_date = CURDATE() AND morning_clock_in IS NOT NULL AND status != 'Absent'");
>>>>>>> d57d342e4679f9986e53377aa6d3656172a33105
$present_today = $present_query ? $present_query->fetch_assoc()['total'] : 0;

// Card 3: Late Arrivals Today
$late_query = $conn->query("SELECT COUNT(log_id) AS total FROM attendance_logs WHERE log_date = CURDATE() AND status = 'Late'");
$late_today = $late_query ? $late_query->fetch_assoc()['total'] : 0;


// ==========================================================
// 5. FETCH DATA FOR THE ATTENDANCE CALENDAR (Last 60 Days)
// ==========================================================
$calendar_data = [];

// Fetch Present and Absent counts per day
$att_query = "
    SELECT log_date, 
           SUM(CASE WHEN morning_clock_in IS NOT NULL AND status != 'Absent' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance_logs 
    WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    GROUP BY log_date
";
$att_res = $conn->query($att_query);
if ($att_res) {
    while($row = $att_res->fetch_assoc()) {
        $calendar_data[$row['log_date']] = [
            'present' => $row['present_count'],
            'absent' => $row['absent_count']
        ];
    }
}

// Convert to JSON for the JavaScript calendar
$calendar_data_json = json_encode($calendar_data);

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
            width: 90%; max-width: 400px;
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
        </main>
    </div>

    <div class="modal-overlay" id="dayDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalDateTitle">Date Details</h2>
                <button class="close-modal" id="closeDayModalBtn"><i class="ph ph-x"></i></button>
            </div>
            <div style="padding: 2rem;">
                <div style="display: flex; justify-content: space-around; text-align: center;">
                    <div>
                        <h3 style="color: var(--color-present); font-size: 2.5rem; margin-bottom: 0.5rem;" id="modalPresentCount">0</h3>
                        <p style="color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.85rem;">Present</p>
                    </div>
                    <div style="width: 1px; background-color: var(--border-color);"></div>
                    <div>
                        <h3 style="color: var(--color-absent); font-size: 2.5rem; margin-bottom: 0.5rem;" id="modalAbsentCount">0</h3>
                        <p style="color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.85rem;">Absent</p>
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

        function openDayModal(dateStr, presentCount, absentCount) {
            // Format the date to look nice (e.g., "Monday, July 24, 2026")
            const dateObj = new Date(dateStr);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            
            document.getElementById('modalDateTitle').innerText = dateObj.toLocaleDateString(undefined, options);
            document.getElementById('modalPresentCount').innerText = presentCount;
            document.getElementById('modalAbsentCount').innerText = absentCount;
            
            dayModal.classList.add('active');
        }

        function closeDayModal() {
            dayModal.classList.remove('active');
        }

        closeDayModalBtn.addEventListener('click', closeDayModal);
        dayModal.addEventListener('click', (e) => {
            if (e.target === dayModal) closeDayModal();
        });


        // --- Dynamic Calendar Logic ---
        const attendanceData = <?php echo $calendar_data_json; ?>;
        let currentDate = new Date();

        function renderCalendar(date) {
            const grid = document.getElementById('calendarGrid');
            grid.innerHTML = ''; 

            const month = date.getMonth();
            const year = date.getFullYear();

            // Set Header Title
            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            document.getElementById('calendarMonth').innerText = `${monthNames[month]} ${year}`;

            // Add Days of Week Headers
            const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            daysOfWeek.forEach(d => {
                const el = document.createElement('div');
                el.className = 'cal-header';
                el.innerText = d;
                grid.appendChild(el);
            });

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            // Pad blank cells before the 1st of the month
            for (let i = 0; i < firstDay; i++) {
                const blank = document.createElement('div');
                blank.className = 'cal-cell blank';
                grid.appendChild(blank);
            }

            // Render Actual Days
            for (let day = 1; day <= daysInMonth; day++) {
                const cell = document.createElement('div');
                cell.className = 'cal-cell';
                
                // Construct format YYYY-MM-DD to match the PHP array keys
                const monthStr = String(month + 1).padStart(2, '0');
                const dayStr = String(day).padStart(2, '0');
                const dateKey = `${year}-${monthStr}-${dayStr}`;

                // Check if we have data for this date
                const dayData = attendanceData[dateKey] || { present: 0, absent: 0 };
                
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

                // Check if it's today's date for styling
                const today = new Date();
                const isToday = (day === today.getDate() && month === today.getMonth() && year === today.getFullYear());
                
                cell.innerHTML = `
                    <span class="cal-day-num ${isToday ? 'today-num' : ''}">${day}</span>
                    ${statsHtml}
                `;
                
                // Add Click Listener to trigger the Modal
                cell.addEventListener('click', () => {
                    openDayModal(dateKey, dayData.present, dayData.absent);
                });
                
                grid.appendChild(cell);
            }
        }

        // Navigation Listeners
        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar(currentDate);
        });

        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar(currentDate);
        });

        // Initial Load
        document.addEventListener("DOMContentLoaded", () => {
            renderCalendar(currentDate);
        });
    </script>
</body>
</html>