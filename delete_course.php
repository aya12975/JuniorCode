
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
    header("Location: courses.php");
    exit();
}

// Fetch section so we can redirect back to the right tab
$stmt = $conn->prepare("SELECT section FROM courses WHERE id = ?");
if (!$stmt) {
    header("Location: courses.php?error=1");
    exit();
}
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: courses.php?error=1");
    exit();
}

$section = $row["section"] ?: "kids";

// Delete related enrollments and projects first to avoid constraint issues
$conn->query("DELETE FROM student_enrollments WHERE course_id = $id");
$conn->query("DELETE FROM course_projects WHERE course_id = $id");

$del = $conn->prepare("DELETE FROM courses WHERE id = ?");
if (!$del) {
    header("Location: courses.php?tab=" . urlencode($section) . "&error=1");
    exit();
}
$del->bind_param("i", $id);

if ($del->execute()) {
    $del->close();
    header("Location: courses.php?tab=" . urlencode($section) . "&success=1");
} else {
    $del->close();
    header("Location: courses.php?tab=" . urlencode($section) . "&error=1");
}
exit();
