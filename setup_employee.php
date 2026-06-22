<?php
// Run this script once in your browser to insert a standard employee user into the database
require_once __DIR__ . '/includes/db_connect.php';

$first_name = "Regular";
$last_name = "Employee";
$email = "employee@flowtime.com"; // Login username
$password = "employee123"; // Plain text password

// Securely hash the password
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$qr_identifier = 'flowtime-' . bin2hex(random_bytes(16));

// Set fallback foreign keys
$department_id = 1; 
$role_id = 3; // Role 3 is Employee
$shift_id = 1;

// Ensure foreign key dependencies exist to prevent constraints from failing
$conn->query("INSERT IGNORE INTO roles (role_id, role_name) VALUES (1, 'admin'), (2, 'hr'), (3, 'employee')");
$conn->query("INSERT IGNORE INTO departments (department_id, department_name) VALUES (1, 'General')");
$conn->query("INSERT IGNORE INTO shifts (shift_id, shift_name, start_time, end_time) VALUES (1, 'Default Shift', '09:00:00', '17:00:00')");

// Insert into the users table
$stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, department_id, role_id, shift_id, qr_identifier) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssiiis", $first_name, $last_name, $email, $password_hash, $department_id, $role_id, $shift_id, $qr_identifier);

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Database Setup: Add Employee Account</h2>";

if ($stmt->execute()) {
    echo "<div style='color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h3 style='margin-top:0;'>✅ Employee successfully added to the database!</h3>";
    echo "<p><strong>User / Email:</strong> " . $email . "</p>";
    echo "<p><strong>Password:</strong> " . $password . "</p>";
    echo "</div>";
    echo "<br><a href='pages/public/login.php' style='display:inline-block; padding: 10px 15px; background: #007bff; color: #fff; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
} else {
    echo "<div style='color: red;'><strong>Error:</strong> " . $stmt->error . "</div>";
}
echo "</div>";

$stmt->close();
$conn->close();
?>