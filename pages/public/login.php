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
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">

    <div class="login-card">
        <h2 style="text-align: center; margin-top: 0; margin-bottom: 1.5rem;">Admin Login</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo $username; ?>" required>
            </div>    
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
                <?php if(!empty($password_err)): ?>
                    <span class="error-text"><?php echo $password_err; ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <button type="submit" class="btn-primary">Login</button>
            </div>
        </form>
    </div>

</body>
</html>