<?php
session_start();
require_once '../../includes/db_connect.php';

// If user is already logged in, redirect them to the dashboard inside private/
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: ../../pages/user-admin/admin_dashboard.php");
    exit;
}

$username_err = $password_err = "";
$username = $password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $db_login_success = false;

    // 1. Try to fetch from SQL database
    $stmt = $conn->prepare("SELECT user_id, first_name, email, password_hash, role_id FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($user_id, $first_name, $email, $password_hash, $role_id);
            if ($stmt->fetch() && password_verify($password, $password_hash)) {
                $db_login_success = true;
                $_SESSION["loggedin"] = true;
                $_SESSION["user_id"] = $user_id;
                $_SESSION["username"] = $email;
                $_SESSION["first_name"] = $first_name;
                
                // Redirect based on role (Assuming 1=Admin)
                if ($role_id == 1) {
                    header("location: ../../pages/user-admin/admin_dashboard.php");
                } else {
                    header("location: ../../pages/hr/hremployees.php");
                }
                exit;
            }
        }
        $stmt->close();
    }

    // 2. Fallback to hardcoded dummy accounts if DB login fails
    if (!$db_login_success) {
        if ($username === "admin" && $password === "password123") {
            $_SESSION["loggedin"] = true;
            $_SESSION["username"] = $username;
            header("location: ../../pages/user-admin/admin_dashboard.php");
            exit;
        } elseif ($username === "employee" && $password === "dummy123") {
            $_SESSION["loggedin"] = true;
            $_SESSION["username"] = $username;
            header("location: ../../pages/hr/hremployees.php");
            exit;
        } else {
            $password_err = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../../assets/css/login.css">
</head>
<body class="login-body">

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <span class="logo-icon"><i class="ph-fill ph-person-simple-walk"></i></span>
                    <span class="logo-text">Vizitor</span>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to continue to your dashboard</p>
            </div>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>Username or Email</label>
                    <div class="input-wrapper">
                        <i class="ph ph-envelope-simple"></i>
                        <input type="text" name="username" class="form-control" placeholder="Enter your username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                </div>    
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="ph ph-lock-key"></i>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                    <?php if(!empty($password_err)): ?>
                        <span class="error-text"><i class="ph-fill ph-warning-circle"></i> <?php echo $password_err; ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-primary">Sign In</button>
                </div>
                <div class="form-group" style="text-align: center; margin-top: 15px; color: #718096; font-size: 0.9rem;">
                    <p><strong>Admin Dummy Account</strong><br>User: admin | Pass: password123</p>
                    <p style="margin-top: 5px;"><strong>Employee Dummy Account</strong><br>User: employee | Pass: dummy123</p>
                    <p style="margin-top: 5px; color: #4a5568;"><strong>SQL Test Account</strong><br>User: test.employee@flowtime.com | Pass: SecurePass123!</p>
                </div>
            </form>
        </div>
    </div>

</body>
</html>