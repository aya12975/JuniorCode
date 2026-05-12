<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    exit("error");
}

/* ── Ensure table and columns exist ── */
$conn->query("CREATE TABLE IF NOT EXISTS teacher_availability (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id     INT          NOT NULL DEFAULT 0,
    teacher_name   VARCHAR(255) NOT NULL DEFAULT '',
    available_date DATE         NOT NULL,
    available_time TIME         NOT NULL,
    status         VARCHAR(50)  NOT NULL DEFAULT 'available',
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE teacher_availability ADD COLUMN IF NOT EXISTS teacher_id INT NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE teacher_availability ADD COLUMN IF NOT EXISTS teacher_name VARCHAR(255) NOT NULL DEFAULT ''");

$teacherId   = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "";
$date        = trim($_POST["date"]   ?? "");
$time        = trim($_POST["time"]   ?? "");
$status      = trim($_POST["status"] ?? "");

if ($teacherId <= 0 || $date === "" || $time === "" || $status === "") {
    exit("error");
}

if ($status === "available") {
    $check = $conn->prepare("SELECT id FROM teacher_availability WHERE teacher_id = ? AND available_date = ? AND available_time = ?");
    if (!$check) { exit("error"); }

    $check->bind_param("iss", $teacherId, $date, $time);
    $check->execute();
    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {
        $update = $conn->prepare("UPDATE teacher_availability SET status = 'available' WHERE teacher_id = ? AND available_date = ? AND available_time = ?");
        if (!$update) { exit("error"); }
        $update->bind_param("iss", $teacherId, $date, $time);
        $update->execute();
        $update->close();
    } else {
        $insert = $conn->prepare("INSERT INTO teacher_availability (teacher_id, teacher_name, available_date, available_time, status) VALUES (?, ?, ?, ?, 'available')");
        if (!$insert) { exit("error"); }
        $insert->bind_param("isss", $teacherId, $teacherName, $date, $time);
        $insert->execute();
        $insert->close();
    }

    $check->close();
    echo "success";
    exit();
}

if ($status === "remove") {
    $delete = $conn->prepare("DELETE FROM teacher_availability WHERE teacher_id = ? AND available_date = ? AND available_time = ?");
    if (!$delete) { exit("error"); }
    $delete->bind_param("iss", $teacherId, $date, $time);
    $delete->execute();
    $delete->close();
    echo "success";
    exit();
}

echo "error";
