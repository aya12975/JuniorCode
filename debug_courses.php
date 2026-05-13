<?php
session_start();
require_once "db.php";

header('Content-Type: text/plain');

$studentName = $_SESSION["username"] ?? "(not logged in)";
echo "=== SESSION ===\n";
echo "username: $studentName\n";
echo "role: " . ($_SESSION["role"] ?? "(none)") . "\n\n";

echo "=== ENROLLMENTS ===\n";
$r = $conn->query("SELECT * FROM student_enrollments WHERE student_name = '$studentName'");
if (!$r) { echo "ERROR: " . $conn->error . "\n"; }
else {
    $rows = $r->fetch_all(MYSQLI_ASSOC);
    echo count($rows) . " row(s)\n";
    foreach ($rows as $row) print_r($row);
}

echo "\n=== COURSES (active) ===\n";
$r = $conn->query("SELECT id, course_name, section, category, status, is_unlocked FROM courses WHERE status='active'");
if (!$r) { echo "ERROR: " . $conn->error . "\n"; }
else {
    $rows = $r->fetch_all(MYSQLI_ASSOC);
    echo count($rows) . " row(s)\n";
    foreach ($rows as $row) {
        echo "  id={$row['id']} section={$row['section']} category={$row['category']} unlocked={$row['is_unlocked']} name={$row['course_name']}\n";
    }
}

echo "\n=== COURSE_PROJECTS columns ===\n";
$r = $conn->query("SHOW COLUMNS FROM course_projects");
if (!$r) { echo "TABLE MISSING: " . $conn->error . "\n"; }
else {
    foreach ($r->fetch_all(MYSQLI_ASSOC) as $col) echo "  " . $col['Field'] . " " . $col['Type'] . "\n";
}

echo "\n=== COURSE_PROJECTS rows ===\n";
$r = $conn->query("SELECT id, course_id, section, category, title FROM course_projects LIMIT 50");
if (!$r) { echo "ERROR: " . $conn->error . "\n"; }
else {
    $rows = $r->fetch_all(MYSQLI_ASSOC);
    echo count($rows) . " row(s)\n";
    foreach ($rows as $row) {
        echo "  id={$row['id']} course_id=" . ($row['course_id'] ?? 'NULL') . " section={$row['section']} category={$row['category']} title={$row['title']}\n";
    }
}
