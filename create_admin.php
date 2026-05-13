<?php
require_once "db.php";

$username = 'admin';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, password, plain_password, role, email, zoom_personal_link, profile_picture) VALUES (?, ?, ?, 'admin', '', '', '') ON DUPLICATE KEY UPDATE password=VALUES(password), plain_password=VALUES(plain_password), role='admin'");
$stmt->bind_param("sss", $username, $hash, $password);

if ($stmt->execute()) {
    echo "<h2 style='color:green'>Admin account created/updated successfully!</h2>";
    echo "<p>Username: <strong>admin</strong><br>Password: <strong>admin123</strong></p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
} else {
    echo "<h2 style='color:red'>Error: " . $stmt->error . "</h2>";
}
