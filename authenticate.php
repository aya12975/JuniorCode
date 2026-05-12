<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit();
}

$username = trim($_POST["username"] ?? "");
$password = trim($_POST["password"] ?? "");

if ($username === "" || $password === "") {
    echo json_encode(["success" => false, "message" => "Please enter your username and password."]);
    exit();
}

$stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Server error. Please try again."]);
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user["password"])) {
        $_SESSION["user_id"]  = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"]     = $user["role"];
        $_SESSION["profile_picture"] = "";

        $redirects = [
            "admin"   => "admin_dashboard.php",
            "teacher" => "teacher_dashboard.php",
            "student" => "student_dashboard.php",
        ];
        $redirect = $redirects[$user["role"]] ?? "login.php";

        echo json_encode(["success" => true, "redirect" => $redirect]);
    } else {
        echo json_encode(["success" => false, "message" => "Incorrect password. Please try again."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "No account found with that username."]);
}
exit();
?>