<?php
session_start();
require_once "db.php";
require_once "admin_prefs.php";
require_once "mailer.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php"); exit();
}

$adminName   = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

function isActive($p, $c) { return $p === $c ? "active" : ""; }

$saved      = false;
$testResult = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // Always save whatever SMTP fields were submitted (non-empty values only)
    $smtpFields = ["smtp_host", "smtp_port", "smtp_user", "smtp_pass", "smtp_from_name"];
    foreach ($smtpFields as $f) {
        $v = trim($_POST[$f] ?? "");
        if ($v !== "") saveAdminSetting($conn, $f, $v);
    }

    if ($action === "save") {
        $saved = true;
    }

    // Send test — reads credentials from POST first, falls back to DB
    if ($action === "test") {
        $host = trim($_POST["smtp_host"]      ?? "") ?: getAdminSetting($conn, "smtp_host",      "");
        $port = (int)(trim($_POST["smtp_port"] ?? "") ?: getAdminSetting($conn, "smtp_port",     "587"));
        $user = trim($_POST["smtp_user"]      ?? "") ?: getAdminSetting($conn, "smtp_user",      "");
        $pass = trim($_POST["smtp_pass"]      ?? "") ?: getAdminSetting($conn, "smtp_pass",      "");
        $name = trim($_POST["smtp_from_name"] ?? "") ?: getAdminSetting($conn, "smtp_from_name", "JuniorCode");
        $to   = trim($_POST["test_email"]     ?? "");

        if (!$host || !$user || !$pass) {
            $testResult = "error:Fill in the SMTP fields above and click Send Test.";
        } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $testResult = "error:Please enter a valid recipient email address.";
        } else {
            $saved = true; // credentials were just saved above
            $sampleClasses = [
                ['class_time' => '09:00:00', 'student_name' => 'Ahmed', 'type' => 'online',  'zoom_link' => ''],
                ['class_time' => '11:00:00', 'student_name' => 'Sara',  'type' => 'on-site', 'zoom_link' => ''],
            ];
            $html   = buildReminderEmail("Test Teacher", date("l, d F Y"), 2, $sampleClasses);
            $mailer = new Mailer($host, $port, $user, $pass, $name);
            $ok     = $mailer->send($to, "Test Teacher", "Test — JuniorCode Class Reminder", $html);
            $testResult = $ok
                ? "ok:Test email sent to $to! Settings have been saved."
                : "error:" . $mailer->lastError();
        }
    }
}

// ── Load current values ───────────────────────────────────────────────────────
$curHost = getAdminSetting($conn, "smtp_host",      "smtp.gmail.com");
$curPort = getAdminSetting($conn, "smtp_port",      "587");
$curUser = getAdminSetting($conn, "smtp_user",      "");
$curPass = getAdminSetting($conn, "smtp_pass",      "");
$curName = getAdminSetting($conn, "smtp_from_name", "JuniorCode");

function mask(string $v): string {
    if (!$v) return "";
    if (strlen($v) <= 6) return str_repeat("•", strlen($v));
    return substr($v, 0, 3) . str_repeat("•", max(0, strlen($v) - 6)) . substr($v, -3);
}
?>
<!DOCTYPE html>
<html lang="<?= $adminLang ?>" dir="<?= $adminDir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Email Notifications | JuniorCode</title>
<link rel="icon" type="image/png" href="images/robot2.png.png">
<?= darkModeCSS() ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --border:#edf4ff; --shadow:0 18px 45px rgba(37,99,235,0.08); }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,Helvetica,sans-serif; background:radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); color:var(--dark); }
.app-shell { min-height:100vh; display:flex; }
.sidebar { width:285px; flex-shrink:0; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:#fff; position:sticky; top:0; height:100vh; display:flex; flex-direction:column; transition:width 0.3s; overflow:hidden; }
body.sidebar-collapsed .sidebar { width:0; }
.sidebar-top-area { padding:0 18px 18px; flex:1; overflow-y:auto; }
.brand { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
.brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
.brand-title { font-weight:900; font-size:1.1rem; color:#fff; line-height:1.2; }
.brand-sub { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
.nav-custom { display:flex; flex-direction:column; gap:4px; }
.nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all 0.22s; font-weight:700; }
.nav-link-custom:hover { background:rgba(255,255,255,0.08); color:#fff; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 8px 20px rgba(30,50,100,0.35); }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.nav-link-custom.active .nav-icon { background:rgba(255,255,255,0.18); }
.sidebar-bottom { padding:16px 18px; border-top:1px solid rgba(255,255,255,0.1); }
.main-content { flex:1; padding:28px; min-width:0; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }
.topbar { background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; padding:22px 26px; margin-bottom:26px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; color:#fff; box-shadow:0 12px 28px rgba(37,99,235,0.3); }
.topbar h1 { font-size:1.7rem; font-weight:900; }
.topbar p { margin:4px 0 0; opacity:0.82; font-size:0.97rem; }
.admin-badge { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.2); border-radius:12px; padding:10px 18px; font-weight:800; font-size:0.92rem; }
.card { background:#fff; border-radius:22px; border:1px solid var(--border); box-shadow:var(--shadow); padding:28px 32px; margin-bottom:22px; }
.card-title { font-size:1.05rem; font-weight:900; color:var(--primary); margin-bottom:20px; display:flex; align-items:center; gap:8px; }
.form-label { font-weight:800; color:#334155; font-size:0.9rem; margin-bottom:6px; display:block; }
.form-hint { font-size:0.78rem; color:var(--muted); margin-top:5px; }
.form-control, .form-select { border-radius:12px; padding:11px 14px; border:1.5px solid #dbe4f0; font-size:0.93rem; width:100%; outline:none; font-family:inherit; transition:border-color .2s; }
.form-control:focus, .form-select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(62,80,119,0.1); }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
.form-grid-3 { display:grid; grid-template-columns:2fr 1fr 1fr; gap:22px; }
@media(max-width:768px) { .form-grid,.form-grid-3 { grid-template-columns:1fr; } }
.key-row { position:relative; }
.key-row .form-control { padding-right:44px; }
.toggle-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--muted); cursor:pointer; font-size:1rem; }
.toggle-eye:hover { color:var(--primary); }
.current-val { background:#f8fbff; border:1px solid #e2eaf8; border-radius:10px; padding:8px 14px; font-family:monospace; font-size:0.85rem; color:#475569; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; gap:12px; }
.status-ok { color:#16a34a; font-weight:800; font-size:0.78rem; white-space:nowrap; }
.status-none { color:#dc2626; font-weight:800; font-size:0.78rem; white-space:nowrap; }
.btn-save { padding:12px 28px; border:none; border-radius:14px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; font-weight:900; font-size:0.95rem; cursor:pointer; margin-top:22px; display:inline-flex; align-items:center; gap:8px; }
.btn-save:hover { opacity:.9; }
.btn-test { padding:11px 22px; border:none; border-radius:14px; background:linear-gradient(135deg,#0ea5e9,#0284c7); color:#fff; font-weight:800; font-size:0.9rem; cursor:pointer; display:inline-flex; align-items:center; gap:7px; }
.btn-test:hover { opacity:.9; }
.alert-ok { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; border-radius:12px; padding:12px 16px; font-weight:700; margin-bottom:20px; font-size:0.9rem; }
.alert-err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:12px; padding:12px 16px; font-weight:700; margin-bottom:20px; font-size:0.9rem; word-break:break-all; }
.step-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; }
.step-box { background:#f8fbff; border:1px solid #dbeafe; border-radius:14px; padding:14px 16px; font-size:0.84rem; color:#475569; line-height:1.7; }
.step-box strong { color:var(--primary); display:block; margin-bottom:4px; }
.step-box code { background:#e0eaff; border-radius:5px; padding:1px 5px; font-size:0.82rem; }
.cmd-box { background:#0f172a; border-radius:14px; padding:16px 20px; font-family:monospace; font-size:0.85rem; color:#e2e8f0; margin-top:12px; position:relative; white-space:pre-wrap; line-height:1.8; }
.copy-cmd { position:absolute; top:10px; right:12px; background:rgba(255,255,255,0.1); border:none; color:#e2e8f0; border-radius:8px; padding:5px 10px; cursor:pointer; font-size:0.78rem; font-weight:700; }
.copy-cmd:hover { background:rgba(255,255,255,0.2); }
.divider { border:none; border-top:1px solid #f1f5f9; margin:22px 0; }
.provider-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; margin-top:14px; }
.provider-card { background:#f8fbff; border:2px solid #e2e8f0; border-radius:14px; padding:16px; cursor:pointer; transition:all 0.18s; text-align:center; }
.provider-card:hover { border-color:var(--primary); background:#eff6ff; }
.provider-card .pname { font-weight:900; font-size:0.9rem; color:#0f172a; margin-top:8px; }
.provider-card .pval { font-size:0.78rem; color:var(--muted); font-weight:700; margin-top:3px; }
</style>
</head>
<body>
<div class="app-shell">

  <aside class="sidebar">
    <div class="sidebar-top-area">
      <div class="brand">
        <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
        <div>
          <div class="brand-title">JuniorCode</div>
          <div class="brand-sub"><?= t('admin_panel') ?></div>
        </div>
      </div>
      <div class="nav-title"><?= t('main_label') ?></div>
      <div class="nav-custom">
        <a href="admin_dashboard.php"           class="nav-link-custom <?= isActive('admin_dashboard.php',           $currentPage) ?>"><span class="nav-icon"><i class="fas fa-house"></i></span><span><?= t('nav_dashboard') ?></span></a>
        <a href="manage_users.php"              class="nav-link-custom <?= isActive('manage_users.php',              $currentPage) ?>"><span class="nav-icon"><i class="fas fa-users"></i></span><span><?= t('nav_users') ?></span></a>
        <a href="admin_teacher_students.php"    class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span></a>
          <a href="admin_enrollments.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span>
          </a>
        <a href="manage_classes.php"            class="nav-link-custom <?= isActive('manage_classes.php',            $currentPage) ?>"><span class="nav-icon"><i class="fas fa-book"></i></span><span><?= t('nav_classes') ?></span></a>
        <a href="teacher_earnings.php"          class="nav-link-custom <?= isActive('teacher_earnings.php',          $currentPage) ?>"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span><?= t('nav_earnings') ?></span></a>
        <a href="available_slots.php"           class="nav-link-custom <?= isActive('available_slots.php',           $currentPage) ?>"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span><?= t('nav_slots') ?></span></a>
        <a href="courses.php"                   class="nav-link-custom <?= isActive('courses.php',                   $currentPage) ?>"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span><?= t('nav_courses') ?></span></a>
        <a href="reports.php"                   class="nav-link-custom <?= isActive('reports.php',                   $currentPage) ?>"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span><?= t('nav_reports') ?></span></a>
        <a href="admin_certificates.php"        class="nav-link-custom <?= isActive('admin_certificates.php',        $currentPage) ?>"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
<a href="admin_email_notifications.php" class="nav-link-custom <?= isActive('admin_email_notifications.php', $currentPage) ?>"><span class="nav-icon"><i class="fas fa-envelope"></i></span><span>Email Notifications</span></a>
      </div>
    </div>
    <div class="sidebar-bottom">
      <a href="settings.php" class="nav-link-custom <?= isActive('settings.php', $currentPage) ?>"><span class="nav-icon"><i class="fas fa-gear"></i></span><span><?= t('nav_settings') ?></span></a>
      <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
      <a href="logout.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span><?= t('nav_logout') ?></span></a>
    </div>
  </aside>

  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
    </div>

    <div class="topbar">
      <div>
        <h1><i class="fas fa-envelope me-2"></i>Email Notifications</h1>
        <p>Teachers receive their daily class schedule automatically every morning at 8:00 AM</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?></div>
    </div>

    <?php
    if ($saved) echo '<div class="alert-ok"><i class="fas fa-check-circle me-2"></i>SMTP settings saved successfully.</div>';
    [$tStatus, $tMsg] = $testResult ? explode(":", $testResult, 2) : ["",""];
    if ($tStatus === "ok")    echo '<div class="alert-ok"><i class="fas fa-paper-plane me-2"></i>'          . htmlspecialchars($tMsg) . '</div>';
    if ($tStatus === "error") echo '<div class="alert-err"><i class="fas fa-triangle-exclamation me-2"></i>' . htmlspecialchars($tMsg) . '</div>';
    ?>

    <!-- ── SMTP Settings ── -->
    <div class="card">
      <div class="card-title"><i class="fas fa-server" style="color:#7c3aed;"></i> SMTP Configuration</div>

      <!-- Quick-fill provider buttons -->
      <p style="font-size:0.88rem;font-weight:800;color:#334155;margin-bottom:10px;">Quick fill — click your provider:</p>
      <div class="provider-grid">
        <div class="provider-card" onclick="fillProvider('smtp.gmail.com','587')">
          <i class="fab fa-google" style="font-size:1.5rem;color:#ea4335;"></i>
          <div class="pname">Gmail</div>
          <div class="pval">smtp.gmail.com : 587</div>
        </div>
        <div class="provider-card" onclick="fillProvider('smtp.office365.com','587')">
          <i class="fab fa-microsoft" style="font-size:1.5rem;color:#0078d4;"></i>
          <div class="pname">Outlook / Office 365</div>
          <div class="pval">smtp.office365.com : 587</div>
        </div>
        <div class="provider-card" onclick="fillProvider('smtp.mail.yahoo.com','587')">
          <i class="fab fa-yahoo" style="font-size:1.5rem;color:#6001d2;"></i>
          <div class="pname">Yahoo Mail</div>
          <div class="pval">smtp.mail.yahoo.com : 587</div>
        </div>
        <div class="provider-card" onclick="fillProvider('','587')">
          <i class="fas fa-server" style="font-size:1.5rem;color:#64748b;"></i>
          <div class="pname">Custom SMTP</div>
          <div class="pval">Enter manually below</div>
        </div>
      </div>

      <hr class="divider">

      <form method="POST">
        <input type="hidden" name="action" value="save">

        <div class="form-grid-3">
          <div>
            <label class="form-label">SMTP Host</label>
            <input type="text" name="smtp_host" id="smtp_host" class="form-control"
                   value="<?= htmlspecialchars($curHost) ?>" placeholder="smtp.gmail.com">
          </div>
          <div>
            <label class="form-label">Port</label>
            <select name="smtp_port" id="smtp_port" class="form-select">
              <option value="587" <?= $curPort === '587' ? 'selected' : '' ?>>587 — STARTTLS</option>
              <option value="465" <?= $curPort === '465' ? 'selected' : '' ?>>465 — SSL</option>
            </select>
          </div>
          <div>
            <label class="form-label">Sender Name</label>
            <input type="text" name="smtp_from_name" class="form-control"
                   value="<?= htmlspecialchars($curName) ?>" placeholder="JuniorCode">
          </div>
        </div>

        <div class="form-grid" style="margin-top:20px;">
          <div>
            <label class="form-label">Email Address</label>
            <?php if ($curUser): ?>
              <div class="current-val"><span><?= htmlspecialchars($curUser) ?></span><span class="status-ok"><i class="fas fa-check-circle me-1"></i>Set</span></div>
            <?php endif; ?>
            <input type="email" name="smtp_user" class="form-control"
                   value="<?= htmlspecialchars($curUser) ?>" placeholder="your@gmail.com">
            <div class="form-hint">The Gmail / email address you send from</div>
          </div>
          <div>
            <label class="form-label">
              Password / App Password
              <?php if ($curPass): ?><span class="status-ok" style="margin-left:6px;"><i class="fas fa-check-circle me-1"></i>Set</span><?php endif; ?>
            </label>
            <div class="key-row">
              <input type="password" name="smtp_pass" id="smtpPass" class="form-control" placeholder="••••••••••••">
              <button type="button" class="toggle-eye" onclick="togglePass()"><i class="fas fa-eye" id="passEye"></i></button>
            </div>
            <div class="form-hint">For Gmail: use an <strong>App Password</strong> (not your normal password)</div>
          </div>
        </div>

        <!-- Test row — inside the same form so SMTP fields are always submitted -->
        <hr class="divider" style="margin-top:24px;">
        <p style="font-size:0.95rem;font-weight:900;color:#0f172a;margin-bottom:12px;"><i class="fas fa-paper-plane me-2" style="color:#0ea5e9;"></i>Send a Test Email</p>
        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
          <div style="flex:1;min-width:220px;">
            <label class="form-label">Recipient email</label>
            <input type="email" name="test_email" id="test_email" class="form-control" placeholder="teacher@example.com">
            <div class="form-hint">Sends a sample reminder using the credentials above — no need to save first</div>
          </div>
          <button type="submit" name="action" value="test" class="btn-test" style="margin-bottom:0;" onclick="return validateTest()">
            <i class="fas fa-paper-plane"></i> Send Test
          </button>
          <button type="submit" name="action" value="save" class="btn-save" style="margin-top:0;">
            <i class="fas fa-save"></i> Save Settings
          </button>
        </div>
      </form>
    </div>

    <!-- ── Gmail App Password guide ── -->
    <div class="card">
      <div class="card-title"><i class="fab fa-google" style="color:#ea4335;"></i> Gmail: How to create an App Password</div>
      <p style="font-size:0.88rem;color:var(--muted);font-weight:700;margin-bottom:14px;">
        Gmail blocks regular passwords for SMTP — you need a 16-character App Password instead.
      </p>
      <div class="step-grid">
        <div class="step-box"><strong>Step 1</strong>Go to <code>myaccount.google.com</code></div>
        <div class="step-box"><strong>Step 2</strong>Security → 2-Step Verification → <strong>enable it</strong> if not already</div>
        <div class="step-box"><strong>Step 3</strong>Search for <strong>"App passwords"</strong> in your Google account search bar</div>
        <div class="step-box"><strong>Step 4</strong>Create a new app password → name it "JuniorCode" → copy the 16-char code</div>
        <div class="step-box"><strong>Step 5</strong>Paste that code in the <strong>Password</strong> field above (no spaces)</div>
        <div class="step-box"><strong>Step 6</strong>Click <strong>Save Settings</strong>, then <strong>Send Test</strong> to confirm it works</div>
      </div>
    </div>

    <!-- ── Task Scheduler ── -->
    <div class="card">
      <div class="card-title"><i class="fas fa-clock" style="color:#22c55e;"></i> Schedule Daily at 8:00 AM (Windows)</div>
      <p style="font-size:0.88rem;color:var(--muted);font-weight:700;margin-bottom:12px;">
        Run this <strong>once</strong> in PowerShell as Administrator — Windows will then send emails automatically every day at 8:00 AM:
      </p>

      <div class="cmd-box" id="ps-cmd">$action   = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" `
            -Argument "C:\xampp\htdocs\JuniorCode\send_class_reminders.php"
$trigger  = New-ScheduledTaskTrigger -Daily -At "08:00AM"
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable
Register-ScheduledTask -TaskName "JuniorCode Reminders" `
  -Action $action -Trigger $trigger -Settings $settings -RunLevel Highest -Force<button class="copy-cmd" onclick="copyCmd('ps-cmd')">Copy</button></div>

      <hr class="divider">

      <p style="font-size:0.85rem;font-weight:800;color:#334155;margin-bottom:8px;">
        To test right now without waiting for 8 AM, run this in PowerShell:
      </p>
      <div class="cmd-box" id="test-cmd">C:\xampp\php\php.exe C:\xampp\htdocs\JuniorCode\send_class_reminders.php<button class="copy-cmd" onclick="copyCmd('test-cmd')">Copy</button></div>

      <hr class="divider">

      <p style="font-size:0.85rem;font-weight:800;color:#334155;margin-bottom:8px;">To remove the scheduled task:</p>
      <div class="cmd-box">Unregister-ScheduledTask -TaskName "JuniorCode Reminders" -Confirm:$false</div>

      <div style="margin-top:16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 18px;font-size:0.84rem;color:#9a3412;font-weight:700;">
        <i class="fas fa-triangle-exclamation me-2"></i>
        Make sure XAMPP is running when the task fires. Each teacher must have their email address saved in Manage Users.
      </div>
    </div>

  </main>
</div>

<script>
function fillProvider(host, port) {
  if (host) document.getElementById('smtp_host').value = host;
  document.getElementById('smtp_port').value = port;
}
function togglePass() {
  const inp = document.getElementById('smtpPass');
  const ico = document.getElementById('passEye');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}
function validateTest() {
  const to = document.getElementById('test_email').value.trim();
  if (!to) { alert('Please enter a recipient email address for the test.'); return false; }
  return true;
}
function copyCmd(id) {
  const raw = document.getElementById(id).innerText.replace(/Copy\s*$/, '').trim();
  navigator.clipboard.writeText(raw).then(() => {
    const btn = document.querySelector('#' + id + ' .copy-cmd');
    btn.textContent = 'Copied!';
    setTimeout(() => btn.textContent = 'Copy', 2000);
  });
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>
