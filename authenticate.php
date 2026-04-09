<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "student") {
                header("Location: student_dashboard.php");
                exit();
            } elseif ($user["role"] === "teacher") {
                header("Location: teacher_dashboard.php");
                exit();
            } elseif ($user["role"] === "admin") {
                header("Location: admin_dashboard.php");
                exit();
            }
        }
    }

    header("Location: login.php?error=1");
    exit();
}
?>