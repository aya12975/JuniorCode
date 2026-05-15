
<?php
session_start();
require_once "db.php";
require_once "admin_prefs.php";

header('Content-Type: application/json');

if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"], ["admin", "teacher"])) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$input      = json_decode(file_get_contents('php://input'), true);
$topic      = trim($input['topic']      ?? '');
$difficulty = trim($input['difficulty'] ?? 'beginner');
$section    = trim($input['section']    ?? 'kids');
$count      = max(3, min(10, (int)($input['count'] ?? 5)));

if ($topic === '') {
    echo json_encode(['error' => 'Please enter a topic.']);
    exit();
}

$apiKey = getAdminSetting($conn, "claude_api_key", "");
$model  = getAdminSetting($conn, "claude_model",   "gemini-2.5-flash");

if ($apiKey === '') {
    echo json_encode(['error' => 'API key not configured. Set it in AI Tutor Settings first.']);
    exit();
}

$sectionLabel = $section === 'kids' ? 'Kids (ages 8–12)' : ($section === 'junior' ? 'Junior (ages 12–16)' : 'Demo/Beginner');

$prompt =
    "You are a quiz generator for JuniorCode, a coding school for children.\n" .
    "Generate exactly {$count} multiple-choice questions about \"{$topic}\" for {$sectionLabel} students at {$difficulty} difficulty.\n" .
    "Rules:\n" .
    "- Each question has exactly 4 choices: A, B, C, D.\n" .
    "- The correct answer is one of A, B, C, or D.\n" .
    "- Keep language simple and age-appropriate.\n" .
    "- Return ONLY a raw JSON array — no markdown, no code fences, no explanation.\n" .
    "JSON format:\n" .
    "[\n" .
    "  {\"question\": \"...\", \"choices\": {\"A\": \"...\", \"B\": \"...\", \"C\": \"...\", \"D\": \"...\"}, \"answer\": \"A\", \"explanation\": \"...\"}\n" .
    "]\n";

$payload = json_encode([
    'contents'           => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
    'generationConfig'   => ['maxOutputTokens' => 2048, 'temperature' => 0.7],
]);

function callGemini(string $model, string $payload, string $apiKey): array {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 45,
    ]);
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$raw, $httpCode];
}

[$raw, $httpCode] = callGemini($model, $payload, $apiKey);

if ($raw === false) {
    echo json_encode(['error' => 'Connection failed. Check your internet and try again.']);
    exit();
}

$data = json_decode($raw, true);

if ($httpCode !== 200) {
    $errMsg = $data['error']['message'] ?? '';

    // Quota exceeded — no point retrying with another model on the same key
    if ($httpCode === 429 || stripos($errMsg, 'quota') !== false || stripos($errMsg, 'exceeded') !== false) {
        $retry = '';
        if (preg_match('/retry in ([\d.]+)s/i', $errMsg, $m)) {
            $secs = (int)ceil((float)$m[1]);
            $retry = " Please wait {$secs} seconds and try again.";
        }
        echo json_encode(['error' => "API quota exceeded.{$retry} You may need to upgrade your Gemini API plan at ai.google.dev."]);
        exit();
    }

    // Overloaded — retry once with fallback model
    $isOverloaded = stripos($errMsg, 'high demand') !== false
                 || stripos($errMsg, 'overloaded') !== false
                 || $httpCode === 503;
    if ($isOverloaded && $model !== 'gemini-2.0-flash') {
        [$raw, $httpCode] = callGemini('gemini-2.0-flash', $payload, $apiKey);
        $data = json_decode($raw, true);
    }
}

if ($httpCode !== 200) {
    $msg = $data['error']['message'] ?? "HTTP {$httpCode}";
    echo json_encode(['error' => 'Gemini API error: ' . $msg]);
    exit();
}

$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
if ($text === '') {
    echo json_encode(['error' => 'AI returned an empty response. Please try again.']);
    exit();
}

// Strip markdown fences in case Gemini adds them anyway
$text = trim($text);
$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
$text = preg_replace('/\s*```\s*$/i', '', $text);
$text = trim($text);

// Extract the JSON array even if Gemini adds surrounding text
$start = strpos($text, '[');
$end   = strrpos($text, ']');
if ($start !== false && $end !== false && $end > $start) {
    $text = substr($text, $start, $end - $start + 1);
}

$questions = json_decode($text, true);
if (!is_array($questions) || empty($questions)) {
    echo json_encode(['error' => 'AI returned an unexpected format. Please try again.']);
    exit();
}

// Validate and sanitize each question
$clean = [];
foreach ($questions as $q) {
    if (!isset($q['question'], $q['choices'], $q['answer'])) continue;
    if (!is_array($q['choices'])) continue;
    $clean[] = [
        'question'    => (string)$q['question'],
        'choices'     => [
            'A' => (string)($q['choices']['A'] ?? ''),
            'B' => (string)($q['choices']['B'] ?? ''),
            'C' => (string)($q['choices']['C'] ?? ''),
            'D' => (string)($q['choices']['D'] ?? ''),
        ],
        'answer'      => strtoupper(substr((string)$q['answer'], 0, 1)),
        'explanation' => (string)($q['explanation'] ?? ''),
    ];
}

if (empty($clean)) {
    echo json_encode(['error' => 'No valid questions generated. Please try again.']);
    exit();
}

echo json_encode(['questions' => $clean]);
