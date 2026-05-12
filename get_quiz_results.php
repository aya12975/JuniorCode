<?php
session_start();
require_once "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"], ["admin", "teacher"])) {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$quizId = (int)($_GET["quiz_id"] ?? 0);
if ($quizId <= 0) {
    echo json_encode(['error' => 'Invalid quiz id']); exit();
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

$stmt = $conn->prepare(
    "SELECT student_username, score, total, completed_at
     FROM quiz_results WHERE quiz_id = ? ORDER BY completed_at DESC"
);
if (!$stmt) {
    echo json_encode(['error' => $conn->error]); exit();
}
$stmt->bind_param("i", $quizId);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'student'      => $row['student_username'],
        'score'        => (int)$row['score'],
        'total'        => (int)$row['total'],
        'pct'          => $row['total'] > 0 ? round(($row['score'] / $row['total']) * 100) : 0,
        'completed_at' => $row['completed_at'],
    ];
}

echo json_encode(['results' => $results]);
