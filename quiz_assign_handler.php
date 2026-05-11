<?php
session_start();
require_once "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"], ["admin", "teacher"])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$assignedBy = $_SESSION["username"] ?? '';
$input    = json_decode(file_get_contents('php://input'), true);
$quizId   = (int)($input['quiz_id'] ?? 0);
$students = $input['students'] ?? [];

if ($quizId <= 0) {
    echo json_encode(['error' => 'Invalid quiz.']);
    exit();
}

$conn->query("CREATE TABLE IF NOT EXISTS quiz_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_username VARCHAR(255) NOT NULL,
    assigned_by VARCHAR(255) NOT NULL DEFAULT '',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assign (quiz_id, student_username),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!is_array($students) || empty($students)) {
    echo json_encode(['error' => 'Please select at least one student.']);
    exit();
}

$stmt  = $conn->prepare("INSERT IGNORE INTO quiz_assignments (quiz_id, student_username, assigned_by) VALUES (?, ?, ?)");
$count = 0;
foreach ($students as $username) {
    $username = trim((string)$username);
    if ($username === '') continue;
    $stmt->bind_param("iss", $quizId, $username, $assignedBy);
    $stmt->execute();
    $count++;
}

echo json_encode(['success' => true, 'assigned' => $count]);
