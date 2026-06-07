<?php
// Include the authentication function from the same directory
require_once __DIR__ . '/functions.php'; // Change extension to .php if you rename it!
check_admin_login();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../../assets/admin.css">
</head>
<body>

    <div class="admin-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>Admin Panel</h1>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#">Dashboard</a></li>
                <li><a href="#">Manage Users</a></li>
                <li><a href="../public/logout.php" class="logout-btn">Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="content-header">
                <h1>Analytics Overview</h1>
            </div>

            <div class="card-grid">
                <div class="card">
                    <h5>Total Users</h5>
                    <p class="stat">2,492 <span class="trend-up">&#9650; 12%</span></p>
                </div>
                <div class="card">
                    <h5>Active Sessions</h5>
                    <p class="stat">142</p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>