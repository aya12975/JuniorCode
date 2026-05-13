<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "juniorcode_db2";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    // Return JSON-safe error if called from an AJAX endpoint
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database unavailable. Please contact the administrator.']);
  
        exit();
    }
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>