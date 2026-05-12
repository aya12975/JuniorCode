<?php
session_start();
require_once "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$conn->query("CREATE TABLE IF NOT EXISTS quiz_results (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id          INT          NOT NULL,
    student_username VARCHAR(255) NOT NULL,
    score            INT          NOT NULL DEFAULT 0,
    total            INT          NOT NULL DEFAULT 0,
    answers          TEXT         NOT NULL DEFAULT '{}',
    completed_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_result (quiz_id, student_username),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$input   = json_decode(file_get_contents('php://input'), true);
$quizId  = (int)($input['quiz_id'] ?? 0);
$score   = (int)($input['score']   ?? 0);
$total   = (int)($input['total']   ?? 0);
$answers = json_encode($input['answers'] ?? []);
$student = $_SESSION['username'] ?? '';

if ($quizId <= 0 || $student === '') {
    echo json_encode(['error' => 'Invalid data']); exit();
}

$stmt = $conn->prepare("INSERT IGNORE INTO quiz_results (quiz_id, student_username, score, total, answers) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("isiss", $quizId, $student, $score, $total, $answers);
    $stmt->execute();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => $conn->error]);
}
