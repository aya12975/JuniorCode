<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    exit("error");
}

$teacherId = (int)($_SESSION["user_id"] ?? 0);
$date = $_POST["date"] ?? "";
$time = $_POST["time"] ?? "";
$status = $_POST["status"] ?? "";

if ($teacherId <= 0 || $date === "" || $time === "" || $status === "") {
    exit("error");
}

if ($status === "available") {
    $check = $conn->prepare("
        SELECT id
        FROM teacher_availability
        WHERE teacher_id = ? AND available_date = ? AND available_time = ?
    ");

    if (!$check) {
        exit("error");
    }

    $check->bind_param("iss", $teacherId, $date, $time);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $update = $conn->prepare("
            UPDATE teacher_availability
            SET status = 'available'
            WHERE teacher_id = ? AND available_date = ? AND available_time = ?
        ");

        if (!$update) {
            exit("error");
        }

        $update->bind_param("iss", $teacherId, $date, $time);
        $update->execute();
        $update->close();
    } else {
        $insert = $conn->prepare("
            INSERT INTO teacher_availability (teacher_id, available_date, available_time, status)
            VALUES (?, ?, ?, 'available')
        ");

        if (!$insert) {
            exit("error");
        }

        $insert->bind_param("iss", $teacherId, $date, $time);
        $insert->execute();
        $insert->close();
    }

    $check->close();
    echo "success";
    exit();
}

if ($status === "remove") {
    $delete = $conn->prepare("
        DELETE FROM teacher_availability
        WHERE teacher_id = ? AND available_date = ? AND available_time = ?
    ");

    if (!$delete) {
        exit("error");
    }

    $delete->bind_param("iss", $teacherId, $date, $time);
    $delete->execute();
    $delete->close();

    echo "success";
    exit();
}

echo "error";