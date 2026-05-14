<?php
session_start();
require_once "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    echo json_encode(["ok" => false, "error" => "unauthorized"]);
    exit();
}

$classId     = (int)($_POST["class_id"] ?? 0);
$notes       = trim($_POST["notes"] ?? "");
$teacherId   = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "";

if (!$classId) {
    echo json_encode(["ok" => false, "error" => "missing class_id"]);
    exit();
}

$conn->query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS teacher_notes TEXT DEFAULT NULL");

$stmt = $conn->prepare("
    UPDATE classes SET teacher_notes = ?
    WHERE id = ? AND (teacher_id = ? OR LOWER(teacher_name) = LOWER(?))
");
if (!$stmt) {
    echo json_encode(["ok" => false, "error" => "db error"]);
    exit();
}
$stmt->bind_param("siis", $notes, $classId, $teacherId, $teacherName);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

echo json_encode(["ok" => $affected >= 0]);
