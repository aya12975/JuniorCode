<?php
session_start();
require_once "db.php";
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    echo json_encode(["ok" => false, "message" => "Unauthorized"]);
    exit();
}

$classId     = (int)($_POST["class_id"]     ?? 0);
$attendance  = in_array($_POST["attendance"] ?? "", ["present","absent"]) ? $_POST["attendance"] : "present";
$courseId    = (int)($_POST["course_id"]    ?? 0) ?: null;
$courseName  = trim($_POST["course_name"]   ?? "");
$projectId   = (int)($_POST["project_id"]   ?? 0) ?: null;
$projectName = trim($_POST["project_name"]  ?? "");
$notes       = trim($_POST["notes"]         ?? "");
$teacherId   = (int)($_SESSION["user_id"]   ?? 0);
$teacherName = $_SESSION["username"]        ?? "";

if ($classId <= 0) {
    echo json_encode(["ok" => false, "message" => "Invalid class."]);
    exit();
}

/* upsert */
$check = $conn->prepare("SELECT id FROM class_feedback WHERE class_id = ?");
$check->bind_param("i", $classId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if ($exists) {
    $stmt = $conn->prepare("UPDATE class_feedback SET attendance=?, course_id=?, course_name=?, project_id=?, project_name=?, notes=?, submitted_at=NOW() WHERE class_id=?");
    $stmt->bind_param("sisssssi", $attendance, $courseId, $courseName, $projectId, $projectName, $notes, $classId);
} else {
    $stmt = $conn->prepare("INSERT INTO class_feedback (class_id, teacher_id, teacher_name, student_name, attendance, course_id, course_name, project_id, project_name, notes) SELECT ?, ?, ?, student_name, ?, ?, ?, ?, ?, ? FROM classes WHERE id=?");
    $stmt->bind_param("iisssisssi", $classId, $teacherId, $teacherName, $attendance, $courseId, $courseName, $projectId, $projectName, $notes, $classId);
}

if (!$stmt) {
    echo json_encode(["ok" => false, "message" => $conn->error]);
    exit();
}

$stmt->execute();
if ($stmt->error) {
    echo json_encode(["ok" => false, "message" => $stmt->error]);
    $stmt->close();
    exit();
}
$stmt->close();

echo json_encode(["ok" => true]);
