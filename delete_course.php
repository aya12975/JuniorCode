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
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    header("Location: courses.php?error=1");
    exit();
}

$section = $row["section"];

$del = $conn->prepare("DELETE FROM courses WHERE id = ?");
$del->bind_param("i", $id);

if ($del->execute()) {
    header("Location: courses.php?tab=" . urlencode($section) . "&success=1");
} else {
    header("Location: courses.php?tab=" . urlencode($section) . "&error=1");
}
exit();
