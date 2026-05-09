<?php
session_start();
require_once "db.php";
require_once "admin_prefs.php";

header('Content-Type: application/json');

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";
$today       = date("Y-m-d");

/* ── Load settings from DB ── */
$apiKey     = getAdminSetting($conn, "claude_api_key",    "");
$model      = getAdminSetting($conn, "claude_model",      "gemini-2.5-flash");
$dailyLimit = (int)getAdminSetting($conn, "chat_daily_limit", "30");

/* ── Ensure usage-tracking table exists ── */
$conn->query("CREATE TABLE IF NOT EXISTS ai_chat_usage (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_name  VARCHAR(255) NOT NULL,
    usage_date    DATE         NOT NULL,
    message_count INT          NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_student_date (student_name, usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── API key guard ── */
if ($apiKey === '') {
    echo json_encode(['error' => 'The AI tutor is not configured yet. Please ask the admin to add the API key at admin_ai_settings.php.']);
    exit();
}

/* ── Daily rate limit ── */
$usageStmt = $conn->prepare("SELECT message_count FROM ai_chat_usage WHERE student_name = ? AND usage_date = ?");
$usageStmt->bind_param("ss", $studentName, $today);
$usageStmt->execute();
$usageRow = $usageStmt->get_result()->fetch_assoc();
$used     = (int)($usageRow["message_count"] ?? 0);

if ($used >= $dailyLimit) {
    echo json_encode([
        'error'     => 'daily_limit',
        'message'   => "You've used all $dailyLimit messages for today. Come back tomorrow — keep up the great work! 🌟",
        'remaining' => 0,
    ]);
    exit();
}

/* ── Read request body ── */
$input       = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');
$history     = $input['history']  ?? [];

if ($userMessage === '') {
    echo json_encode(['error' => 'Please type a message.']);
    exit();
}

/* ── System prompt ── */
$systemPrompt =
    "You are Codi, a warm and enthusiastic AI coding tutor for kids aged 8–16 at JuniorCode, a coding school. " .
    "You help students with Python, Scratch, game development, HTML/CSS, and general programming concepts. " .
    "Always be encouraging, patient, and positive. Use simple language and short, relatable examples. " .
    "When you show code, keep it brief and explain each line. Use emojis occasionally to keep the tone fun. " .
    "If a student seems confused or frustrated, be extra supportive and try a different explanation. " .
    "End every response with a short encouraging note or a question to keep the student curious and engaged.";

/* ── Build Gemini contents array (cap history at last 60 entries) ── */
$contents = [];
foreach (array_slice($history, -60) as $h) {
    if (!isset($h['role'], $h['content'])) continue;
    // Gemini uses "model" instead of "assistant"
    $role = $h['role'] === 'assistant' ? 'model' : 'user';
    if (!in_array($role, ['user', 'model'], true)) continue;
    $contents[] = ['role' => $role, 'parts' => [['text' => (string)$h['content']]]];
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

/* ── Build Gemini payload ── */
$payload = json_encode([
    'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
    'contents'           => $contents,
    'generationConfig'   => ['maxOutputTokens' => 1024],
]);

/* ── Call Gemini API ── */
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
]);

$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false) {
    echo json_encode(['error' => 'Connection failed. Please check your internet and try again.']);
    exit();
}

$data = json_decode($raw, true);

if ($httpCode !== 200) {
    $apiMsg = $data['error']['message'] ?? '';
    $status = $data['error']['status']  ?? '';
    if (str_contains($apiMsg, 'quota') || str_contains($apiMsg, 'billing') || str_contains($status, 'RESOURCE_EXHAUSTED')) {
        echo json_encode(['error' => 'The AI tutor is out of credits. Please ask the admin to top up the Google AI account at aistudio.google.com.']);
    } elseif ($httpCode === 400 && str_contains($apiMsg, 'API key')) {
        echo json_encode(['error' => 'Invalid API key. Please ask the admin to check the key at admin_ai_settings.php.']);
    } elseif ($httpCode === 403) {
        echo json_encode(['error' => 'API key does not have permission. Please ask the admin to check the key.']);
    } elseif ($httpCode === 429) {
        echo json_encode(['error' => 'Too many requests. Please wait a moment and try again.']);
    } else {
        echo json_encode(['error' => 'The AI could not respond right now (error ' . $httpCode . '). Please try again.']);
    }
    exit();
}

$reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

if ($reply === '') {
    echo json_encode(['error' => 'The AI returned an empty response. Please try again.']);
    exit();
}

/* ── Update usage count ── */
$upsert = $conn->prepare(
    "INSERT INTO ai_chat_usage (student_name, usage_date, message_count)
     VALUES (?, ?, 1)
     ON DUPLICATE KEY UPDATE message_count = message_count + 1"
);
$upsert->bind_param("ss", $studentName, $today);
$upsert->execute();

$remaining = max(0, $dailyLimit - $used - 1);
echo json_encode(['reply' => $reply, 'remaining' => $remaining]);
