<?php
session_start();
require_once "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    echo json_encode([]);
    exit();
}

$courseId = (int)($_GET["course_id"] ?? 0);
if (!$courseId) { echo json_encode([]); exit(); }

$stmt = $conn->prepare("SELECT id, title, url, image, pdf_url FROM course_projects WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
if (!$stmt) { echo json_encode([]); exit(); }

$stmt->bind_param("i", $courseId);
$stmt->execute();
echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
