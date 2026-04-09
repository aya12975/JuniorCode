<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    exit("error");
}

$teacherName = $_SESSION["username"];
$date = $_POST["date"] ?? "";
$time = $_POST["time"] ?? "";
$status = $_POST["status"] ?? "";

if ($date === "" || $time === "" || $status === "") {
    exit("error");
}

if ($status === "available") {
    $check = $conn->prepare("
        SELECT id FROM teacher_availability
        WHERE teacher_name = ? AND available_date = ? AND available_time = ?
    ");
    $check->bind_param("sss", $teacherName, $date, $time);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $update = $conn->prepare("
            UPDATE teacher_availability
            SET status = 'available'
            WHERE teacher_name = ? AND available_date = ? AND available_time = ?
        ");
        $update->bind_param("sss", $teacherName, $date, $time);
        $update->execute();
    } else {
        $insert = $conn->prepare("
            INSERT INTO teacher_availability (teacher_name, available_date, available_time, status)
            VALUES (?, ?, ?, 'available')
        ");
        $insert->bind_param("sss", $teacherName, $date, $time);
        $insert->execute();
    }

    echo "success";
    exit();
}

if ($status === "remove") {
    $delete = $conn->prepare("
        DELETE FROM teacher_availability
        WHERE teacher_name = ? AND available_date = ? AND available_time = ?
    ");
    $delete->bind_param("sss", $teacherName, $date, $time);
    $delete->execute();

    echo "success";
    exit();
}

echo "error";