<?php
session_start();
require_once "db.php";
require_once "zoom_helper.php";

header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$classId = (int)($_POST["class_id"] ?? 0);
if (!$classId) {
    echo json_encode(["success" => false, "message" => "Invalid class ID"]);
    exit();
}

if (!zoomCredentialsSet($conn)) {
    echo json_encode(["success" => false, "message" => "Zoom API not configured"]);
    exit();
}

$stmt = $conn->prepare("SELECT teacher_name, student_name, class_date, class_time FROM classes WHERE id = ?");
$stmt->bind_param("i", $classId);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    echo json_encode(["success" => false, "message" => "Class not found"]);
    exit();
}

$topic   = "JuniorCode — " . $class["teacher_name"] . " & " . $class["student_name"];
$joinUrl = createZoomMeeting($conn, $topic, $class["class_date"], $class["class_time"]);

if (!$joinUrl) {
    echo json_encode(["success" => false, "message" => "Failed to create Zoom meeting"]);
    exit();
}

$upd = $conn->prepare("UPDATE classes SET zoom_link = ? WHERE id = ?");
$upd->bind_param("si", $joinUrl, $classId);
$upd->execute();
$upd->close();

echo json_encode(["success" => true, "join_url" => $joinUrl]);
exit();
?>
