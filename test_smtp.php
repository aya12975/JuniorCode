<?php
require_once "db.php";
require_once "admin_prefs.php";
require_once "mailer.php";

$host = getAdminSetting($conn, "smtp_host", "");
$port = (int)getAdminSetting($conn, "smtp_port", 587);
$user = getAdminSetting($conn, "smtp_user", "");
$pass = getAdminSetting($conn, "smtp_pass", "");
$from = getAdminSetting($conn, "smtp_from_name", "JuniorCode");

echo "<pre>";
echo "Host : $host\n";
echo "Port : $port\n";
echo "User : $user\n";
echo "Pass : " . (strlen($pass) > 0 ? str_repeat('*', strlen($pass)) : '(empty)') . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? '✅ enabled' : '❌ NOT enabled') . "\n\n";

if (!$host || !$user || !$pass) {
    die("❌ SMTP not configured. Go to Settings → SMTP and fill in host, user, and password.");
}

echo "Connecting to $host:$port ...\n";
$m  = new Mailer($host, $port, $user, $pass, $from);
$ok = $m->send($user, "SMTP Test", "JuniorCode SMTP Test", "<b>SMTP is working! 🎉</b>");
if ($ok) {
    echo "✅ Email sent successfully to $user\n";
} else {
    echo "❌ Failed:\n" . $m->lastError() . "\n";
}
echo "</pre>";
