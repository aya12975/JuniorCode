<?php
/*
 * send_class_reminders.php
 * Run daily at 08:00 via Windows Task Scheduler:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\JuniorCode\send_class_reminders.php
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_prefs.php';
require_once __DIR__ . '/mailer.php';

$smtpHost = getAdminSetting($conn, 'smtp_host',      '');
$smtpPort = (int)getAdminSetting($conn, 'smtp_port', '587');
$smtpUser = getAdminSetting($conn, 'smtp_user',      '');
$smtpPass = getAdminSetting($conn, 'smtp_pass',      '');
$fromName = getAdminSetting($conn, 'smtp_from_name', 'JuniorCode');

if (!$smtpHost || !$smtpUser || !$smtpPass) {
    echo "[ERROR] SMTP not configured. Go to Admin → Email Notifications to set it up.\n";
    exit(1);
}

$today     = date('Y-m-d');
$dateLabel = date('l, d F Y');

// Fetch today's classes
$stmt = $conn->prepare(
    "SELECT teacher_name, student_name, class_time, type, zoom_link, details
     FROM classes WHERE class_date = ? ORDER BY teacher_name ASC, class_time ASC"
);
if (!$stmt) { echo "[ERROR] " . $conn->error . "\n"; exit(1); }
$stmt->bind_param("s", $today);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($rows)) {
    echo "[INFO] No classes scheduled for $today. No emails sent.\n";
    exit(0);
}

// Group by teacher
$byTeacher = [];
foreach ($rows as $r) {
    $byTeacher[$r['teacher_name']][] = $r;
}

$sent = $failed = $skipped = 0;

foreach ($byTeacher as $teacherName => $classes) {
    // Get teacher email
    $eStmt = $conn->prepare("SELECT email FROM users WHERE username = ? LIMIT 1");
    if (!$eStmt) continue;
    $eStmt->bind_param("s", $teacherName);
    $eStmt->execute();
    $email = trim($eStmt->get_result()->fetch_assoc()['email'] ?? '');

    if (!$email) {
        echo "[SKIP] $teacherName — no email address on record.\n";
        $skipped++;
        continue;
    }

    $count   = count($classes);
    $subject = "Your classes today — $dateLabel";
    $html    = buildReminderEmail($teacherName, $dateLabel, $count, $classes);

    $mailer = new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName);
    $ok     = $mailer->send($email, $teacherName, $subject, $html);

    if ($ok) {
        echo "[OK]   Sent to $teacherName <$email> — $count class(es).\n";
        $sent++;
    } else {
        echo "[FAIL] $teacherName <$email> — " . $mailer->lastError() . "\n";
        $failed++;
    }
}

echo "\nDone: $sent sent, $failed failed, $skipped skipped (no email).\n";
