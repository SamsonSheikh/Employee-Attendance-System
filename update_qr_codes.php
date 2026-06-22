<?php
require_once __DIR__ . '/includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 50px auto;'>";
echo "<h2>Updating User QR Identifiers</h2>";

$result = $conn->query("SELECT user_id FROM users WHERE qr_identifier IS NULL");

if ($result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " users without a QR identifier. Updating now...</p>";
    echo "<ul style='line-height: 1.6;'>";
    while($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        // Generate a cryptographically secure-enough unique ID
        $qr_identifier = 'flowtime-' . bin2hex(random_bytes(16)); 
        
        $stmt = $conn->prepare("UPDATE users SET qr_identifier = ? WHERE user_id = ?");
        $stmt->bind_param("si", $qr_identifier, $user_id);
        if ($stmt->execute()) {
            echo "<li>User ID $user_id updated successfully.</li>";
        }
    }
    echo "</ul>";
    echo "<p style='color: #155724; font-weight: bold;'>✅ Update complete.</p>";
} else {
    echo "<p style='color: #065f46;'>All users already have a QR identifier. No action needed.</p>";
}
echo "</div>";
?>