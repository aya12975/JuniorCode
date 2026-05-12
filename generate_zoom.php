<?php
session_start();
require_once "db.php";
require_once "zoom_helper.php";

header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$topic     = trim($_POST["topic"]      ?? "JuniorCode Class");
$startDate = trim($_POST["start_date"] ?? "");
$startTime = trim($_POST["start_time"] ?? "");
$duration  = (int)($_POST["duration"]  ?? 60);

if (!$startDate || !$startTime) {
    echo json_encode(["success" => false, "message" => "Date and time are required."]);
    exit();
}

if (!zoomCredentialsSet($conn)) {
    echo json_encode(["success" => false, "message" => "Zoom API credentials not configured. Go to Settings → Zoom."]);
    exit();
}

$zoomError = null;
$joinUrl   = createZoomMeeting($conn, $topic, $startDate, $startTime, $duration, $zoomError);

if ($joinUrl) {
    echo json_encode(["success" => true, "join_url" => $joinUrl]);
} else {
    echo json_encode(["success" => false, "message" => $zoomError ?: "Failed to create Zoom meeting."]);
}
exit();
?>
