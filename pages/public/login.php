<?php
session_start();

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

    if ($username === "admin" && $password === "password123") {
        $_SESSION["loggedin"] = true;
        $_SESSION["username"] = $username;
        // Redirect into the private dashboard folder
        header("location: ../../pages/user-admin/admin_dashboard.php");
        exit;
    } else {
        $password_err = "Invalid username or password.";
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
            </form>
        </div>
    </div>

</body>
</html>