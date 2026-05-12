<?php
session_start();
require_once "db.php";
require_once "admin_prefs.php";
require_once "mailer.php";
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

// Fetch quiz details for the email
$quizRow = $conn->prepare("SELECT title, topic, difficulty, section FROM quizzes WHERE id = ? LIMIT 1");
$quizData = [];
if ($quizRow) {
    $quizRow->bind_param("i", $quizId);
    $quizRow->execute();
    $quizData = $quizRow->get_result()->fetch_assoc() ?? [];
}

$stmt  = $conn->prepare("INSERT IGNORE INTO quiz_assignments (quiz_id, student_username, assigned_by) VALUES (?, ?, ?)");
$count = 0;
foreach ($students as $username) {
    $username = trim((string)$username);
    if ($username === '') continue;
    $stmt->bind_param("iss", $quizId, $username, $assignedBy);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $count++;
}

// Send email notifications
if ($count > 0 && !empty($quizData)) {
    $smtpHost  = getAdminSetting($conn, "smtp_host",      "");
    $smtpPort  = (int)getAdminSetting($conn, "smtp_port", 587);
    $smtpUser  = getAdminSetting($conn, "smtp_user",      "");
    $smtpPass  = getAdminSetting($conn, "smtp_pass",      "");
    $fromName  = getAdminSetting($conn, "smtp_from_name", "JuniorCode");

    if ($smtpHost && $smtpUser && $smtpPass) {
        $quizTitle  = $quizData["title"]      ?? "New Quiz";
        $quizTopic  = $quizData["topic"]      ?? "";
        $difficulty = ucfirst($quizData["difficulty"] ?? "");
        $section    = ucfirst($quizData["section"]    ?? "");
        $quizUrl    = "http://" . ($_SERVER["HTTP_HOST"] ?? "localhost") . "/JuniorCode/quiz_take.php?id=$quizId";

        $eStmt = $conn->prepare("SELECT email FROM users WHERE username = ? AND role = 'student' LIMIT 1");

        foreach ($students as $username) {
            $username = trim((string)$username);
            if ($username === '') continue;

            if ($eStmt) {
                $eStmt->bind_param("s", $username);
                $eStmt->execute();
                $email = trim($eStmt->get_result()->fetch_assoc()["email"] ?? "");
                if (!$email) continue;
            } else continue;

            $html = buildQuizAssignmentEmail($username, $assignedBy, $quizTitle, $quizTopic, $difficulty, $section, $quizUrl);
            (new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName))
                ->send($email, $username, "New quiz assigned: $quizTitle", $html);
        }
    }
}

echo json_encode(['success' => true, 'assigned' => $count]);
