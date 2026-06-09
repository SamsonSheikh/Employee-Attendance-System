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
    <title>Reports | EAS</title>
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
                    <li class="active"><a href="../../pages/user-admin/adminreports.php"><i class="ph ph-file-text"></i> Reports</a></li>
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
                <h1>Reports</h1>
            </header>

            <section class="data-grid" style="margin-bottom: 1.5rem;">
                <article class="chart-card">
                    <div class="card-header">
                        <div>
                            <h2>Report Volume Overview</h2>
                            <p class="section-desc">Daily, weekly, and monthly report demand to support urgent review meetings.</p>
                        </div>
                        <div class="report-kpi-actions">
                            <button type="button" class="btn-secondary" id="downloadKpiBtn"><i class="ph ph-download-simple"></i> Download File</button>
                        </div>
                    </div>

                    <div class="chart-legend">
                        <span class="legend-item"><span class="dot present"></span> Daily Reports</span>
                        <span class="legend-item"><span class="dot absent"></span> Weekly Reports</span>
                        <span class="legend-item"><span class="dot leave"></span> Monthly Reports</span>
                    </div>

                    <div class="mock-chart" aria-label="Report volume graph">
                        <div class="chart-group">
                            <div class="bar present" style="height: 68%;"></div>
                            <div class="bar absent" style="height: 82%;"></div>
                            <div class="bar leave" style="height: 94%;"></div>
                            <span class="label">Daily</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 54%;"></div>
                            <div class="bar absent" style="height: 70%;"></div>
                            <div class="bar leave" style="height: 88%;"></div>
                            <span class="label">Weekly</span>
                        </div>
                        <div class="chart-group">
                            <div class="bar present" style="height: 44%;"></div>
                            <div class="bar absent" style="height: 58%;"></div>
                            <div class="bar leave" style="height: 76%;"></div>
                            <span class="label">Monthly</span>
                        </div>
                    </div>
                </article>

                <article class="holidays-card report-kpi-card">
                    <div class="card-header">
                        <div>
                            <h2>KPI Snapshot</h2>
                            <p class="section-desc">Current urgency indicators for report readiness.</p>
                        </div>
                    </div>
                    <ul class="holiday-list">
                        <li><span>Daily reports</span><strong>18</strong></li>
                        <li><span>Weekly reports</span><strong>96</strong></li>
                        <li><span>Monthly reports</span><strong>340</strong></li>
                        <li><span>Urgent review queue</span><strong>7 items</strong></li>
                    </ul>
                </article>
            </section>

            <section class="security-controls-section">
                <div class="section-header">
                    <h2>Reports Review Queue</h2>
                    <p class="section-desc">Review pending reports, assign urgency, and confirm which investigations need immediate follow-up.</p>
                </div>

                <div class="security-grid">
                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon deactivate-icon"><i class="ph ph-eye"></i></div>
                            <div class="security-info">
                                <h3>Pending Security Review</h3>
                                <p>3 breach-related reports awaiting admin evaluation.</p>
                            </div>
                        </div>
                        <ul style="padding-left: 1rem; color: var(--text-main); line-height: 1.6;">
                            <li>Unauthorized login attempts</li>
                            <li>Shared device access flagged</li>
                            <li>Privilege escalation alert</li>
                        </ul>
                        <button type="button" class="btn-warning" style="margin-top: 1rem;"><i class="ph ph-check-circle"></i> Review Now</button>
                    </article>

                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon password-reset-icon"><i class="ph ph-clipboard-text"></i></div>
                            <div class="security-info">
                                <h3>Compliance Check</h3>
                                <p>2 reports require policy confirmation before closure.</p>
                            </div>
                        </div>
                        <ul style="padding-left: 1rem; color: var(--text-main); line-height: 1.6;">
                            <li>Access policy review</li>
                            <li>Monthly audit readiness</li>
                        </ul>
                        <button type="button" class="btn-secondary" style="margin-top: 1rem;"><i class="ph ph-list-checks"></i> Open Checklist</button>
                    </article>

                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon session-icon"><i class="ph ph-fire"></i></div>
                            <div class="security-info">
                                <h3>Urgent Escalations</h3>
                                <p>1 high-priority report needs immediate manager attention.</p>
                            </div>
                        </div>
                        <ul style="padding-left: 1rem; color: var(--text-main); line-height: 1.6;">
                            <li>High severity incident</li>
                            <li>Executive briefing required</li>
                        </ul>
                        <button type="button" class="btn-danger" style="margin-top: 1rem;"><i class="ph ph-warning"></i> Escalate</button>
                    </article>
                </div>
            </section>

            <section class="security-controls-section">
                <div class="section-header">
                    <h2>Security & Compliance Reports</h2>
                    <p class="section-desc">A quick view of available reports for breach investigation, access monitoring, and policy review.</p>
                </div>

                <div class="security-grid">
                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon deactivate-icon"><i class="ph ph-warning-octagon"></i></div>
                            <div class="security-info">
                                <h3>Security Breach Reports</h3>
                                <p>Incident logs, flagged activity, and breach summaries.</p>
                            </div>
                        </div>
                        <ul style="padding-left: 1rem; color: var(--text-main); line-height: 1.6;">
                            <li>Unauthorized login attempts</li>
                            <li>Suspicious device access alerts</li>
                            <li>Privilege escalation incidents</li>
                        </ul>
                    </article>

                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon password-reset-icon"><i class="ph ph-key"></i></div>
                            <div class="security-info">
                                <h3>Access & Authentication Reports</h3>
                                <p>Reports for password resets, session review, and login patterns.</p>
                            </div>
                        </div>
                        <ul style="padding-left: 1rem; color: var(--text-main); line-height: 1.6;">
                            <li>Failed sign-in attempts by user</li>
                            <li>Session duration and location trends</li>
                            <li>Forced password reset activity</li>
                        </ul>
                    </article>

                    <article class="security-card">
                        <div class="security-card-header">
                            <div class="security-icon session-icon"><i class="ph ph-file-text"></i></div>
                            <div class="security-info">
                                <h3>Compliance & Audit Reports</h3>
                                <p>Monthly reviews, policy checks, and admin audit trails.</p>
                            </div>
                        </div>
                        <ul style="padding-left: 1rem; color: var(--text-main); line-height: 1.6;">
                            <li>Policy compliance summaries</li>
                            <li>Role assignment history</li>
                            <li>Admin action and review logs</li>
                        </ul>
                    </article>
                </div>

                <div class="audit-log-section">
                    <h3>Recent Breach & Report Activity</h3>
                    <div class="audit-log">
                        <div class="audit-entry">
                            <div class="audit-timestamp">1 hr ago</div>
                            <div class="audit-action">Multiple failed login attempts detected from a shared device.</div>
                            <div class="audit-user">Severity: High</div>
                        </div>
                        <div class="audit-entry">
                            <div class="audit-timestamp">6 hrs ago</div>
                            <div class="audit-action">Suspicious access to employee records from an unrecognized IP range.</div>
                            <div class="audit-user">Severity: Medium</div>
                        </div>
                        <div class="audit-entry">
                            <div class="audit-timestamp">Yesterday</div>
                            <div class="audit-action">Compliance review completed for access control policy enforcement.</div>
                            <div class="audit-user">Status: Reviewed</div>
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

        document.getElementById('downloadKpiBtn')?.addEventListener('click', function () {
            const summary = [
                'EAS Admin KPI Summary',
                '====================',
                'Daily Reports: 18',
                'Weekly Reports: 96',
                'Monthly Reports: 340',
                'Urgent Review Queue: 7 items',
                'Generated from Reports tab'
            ].join('\n');

            const blob = new Blob([summary], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'eas-report-kpi-summary.txt';
            link.click();
            URL.revokeObjectURL(url);
        });
    </script>
</body>
</html>
