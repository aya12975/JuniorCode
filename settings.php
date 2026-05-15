<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName   = $_SESSION["username"] ?? "Admin";

// Load prefs before POST so $adminTheme/$adminLang are set
require_once "admin_prefs.php";
require_once "mailer.php";

$message     = "";
$messageType = "success";

/* ──────────────────────────────────────────────────────────────
   POST handlers
────────────────────────────────────────────────────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    // ── Appearance & Language ──────────────────────────────────
    if ($action === 'appearance') {
        $theme = in_array($_POST['theme'] ?? '', ['light','dark']) ? $_POST['theme'] : 'light';
        $lang  = in_array($_POST['lang']  ?? '', ['en','ar','fr']) ? $_POST['lang']  : 'en';

        saveAdminSetting($conn, 'theme', $theme);
        saveAdminSetting($conn, 'lang',  $lang);

        $_SESSION['admin_theme'] = $theme;
        $_SESSION['admin_lang']  = $lang;

        // Reload vars so page renders with new values immediately
        $adminTheme = $theme;
        $adminLang  = $lang;
        $adminDir   = ($lang === 'ar') ? 'rtl' : 'ltr';

        $message = ($lang === 'ar') ? 'تم حفظ إعدادات المظهر.' : (($lang === 'fr') ? 'Apparence enregistrée.' : 'Appearance settings saved.');
    }

    // ── Account & Security ─────────────────────────────────────
    elseif ($action === 'account') {
        $newEmail   = trim($_POST['admin_email']       ?? '');
        $currentPwd = $_POST['current_password']       ?? '';
        $newPwd     = $_POST['new_password']            ?? '';
        $confirmPwd = $_POST['confirm_password']        ?? '';
        $pwdChanged = false;

        if ($newEmail !== '') {
            saveAdminSetting($conn, 'admin_email', $newEmail);
        }

        if ($newPwd !== '') {
            if ($newPwd !== $confirmPwd) {
                $message     = t('confirm_pwd') . ' mismatch.';
                $messageType = 'danger';
            } else {
                $stmt = $conn->prepare("SELECT password FROM users WHERE username = ? LIMIT 1");
                $stmt->bind_param("s", $adminName);
                $stmt->execute();
                $r = $stmt->get_result();
                if ($r && ($row = $r->fetch_assoc())) {
                    if (password_verify($currentPwd, $row['password'])) {
                        $hashed = password_hash($newPwd, PASSWORD_DEFAULT);
                        $upd    = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
                        $upd->bind_param("ss", $hashed, $adminName);
                        if ($upd->execute()) {
                            $pwdChanged = true;
                        } else {
                            $message     = 'Failed to update password.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message     = ($adminLang === 'ar') ? 'كلمة المرور الحالية غير صحيحة.' : 'Current password is incorrect.';
                        $messageType = 'danger';
                    }
                }
            }
        }

        if ($message === '') {
            $message = ($adminLang === 'ar')
                ? ($pwdChanged ? 'تم تحديث البريد الإلكتروني وكلمة المرور.' : 'تم حفظ بيانات الحساب.')
                : ($adminLang === 'fr'
                    ? ($pwdChanged ? 'E-mail et mot de passe mis à jour.' : 'Compte enregistré.')
                    : ($pwdChanged ? 'Email and password updated.' : 'Account settings saved.'));
        }
    }

    // ── AI Tutor ───────────────────────────────────────────────
    elseif ($action === 'ai_tutor') {
        $newKey = trim($_POST["claude_api_key"] ?? "");
        $model  = trim($_POST["claude_model"]   ?? "gemini-2.5-flash");
        $limit  = max(1, (int)($_POST["daily_limit"] ?? 30));

        if ($newKey && !str_starts_with($newKey, "AIza")) {
            $message     = "That doesn't look like a valid Google Gemini API key (should start with AIza).";
            $messageType = "danger";
        } else {
            if ($newKey) saveAdminSetting($conn, "claude_api_key", $newKey);
            saveAdminSetting($conn, "claude_model",     $model);
            saveAdminSetting($conn, "chat_daily_limit", (string)$limit);
            $message     = "AI Tutor settings saved.";
            $messageType = "success";
        }
    }

    // ── Zoom API Credentials ───────────────────────────────────
    elseif ($action === 'zoom') {
        saveAdminSetting($conn, 'zoom_account_id',    trim($_POST['zoom_account_id']    ?? ''));
        saveAdminSetting($conn, 'zoom_client_id',     trim($_POST['zoom_client_id']     ?? ''));
        saveAdminSetting($conn, 'zoom_client_secret', trim($_POST['zoom_client_secret'] ?? ''));
        saveAdminSetting($conn, 'zoom_timezone',      trim($_POST['zoom_timezone']      ?? 'UTC'));
        $message     = 'Zoom credentials saved.';
        $messageType = 'success';
    }

    // ── SMTP — save ────────────────────────────────────────────
    elseif ($action === 'smtp') {
        foreach (["smtp_host","smtp_port","smtp_user","smtp_pass","smtp_from_name"] as $f) {
            $v = trim($_POST[$f] ?? "");
            if ($v !== "") saveAdminSetting($conn, $f, $v);
        }
        $message     = "SMTP settings saved.";
        $messageType = "success";
    }

    // ── SMTP — test send ───────────────────────────────────────
    elseif ($action === 'smtp_test') {
        foreach (["smtp_host","smtp_port","smtp_user","smtp_pass","smtp_from_name"] as $f) {
            $v = trim($_POST[$f] ?? "");
            if ($v !== "") saveAdminSetting($conn, $f, $v);
        }
        $host = trim($_POST["smtp_host"]      ?? "") ?: getAdminSetting($conn, "smtp_host",      "");
        $port = (int)(trim($_POST["smtp_port"] ?? "") ?: getAdminSetting($conn, "smtp_port",     "587"));
        $user = trim($_POST["smtp_user"]      ?? "") ?: getAdminSetting($conn, "smtp_user",      "");
        $pass = trim($_POST["smtp_pass"]      ?? "") ?: getAdminSetting($conn, "smtp_pass",      "");
        $name = trim($_POST["smtp_from_name"] ?? "") ?: getAdminSetting($conn, "smtp_from_name", "JuniorCode");
        $to   = trim($_POST["test_email"]     ?? "");

        if (!$host || !$user || !$pass) {
            $message     = "Fill in the SMTP fields first.";
            $messageType = "danger";
        } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $message     = "Please enter a valid recipient email.";
            $messageType = "danger";
        } else {
            $sampleClasses = [
                ['class_time'=>'09:00:00','student_name'=>'Ahmed','type'=>'online','zoom_link'=>''],
                ['class_time'=>'11:00:00','student_name'=>'Sara', 'type'=>'on-site','zoom_link'=>''],
            ];
            $html   = buildReminderEmail("Test Teacher", date("l, d F Y"), 2, $sampleClasses);
            $mailer = new Mailer($host, $port, $user, $pass, $name);
            $ok     = $mailer->send($to, "Test Teacher", "Test — JuniorCode Class Reminder", $html);
            $message     = $ok ? "Test email sent to $to successfully!" : $mailer->lastError();
            $messageType = $ok ? "success" : "danger";
        }
    }

}

$admin_email       = getAdminSetting($conn, "admin_email",       "admin@juniorcode.com");
$ai_api_key        = getAdminSetting($conn, "claude_api_key",    "");
$ai_model          = getAdminSetting($conn, "claude_model",      "gemini-2.5-flash");
$ai_daily_limit    = (int)getAdminSetting($conn, "chat_daily_limit", "30");
$curHost  = getAdminSetting($conn, "smtp_host",      "smtp.gmail.com");
$curPort  = getAdminSetting($conn, "smtp_port",      "587");
$curUser  = getAdminSetting($conn, "smtp_user",      "");
$curPass  = getAdminSetting($conn, "smtp_pass",      "");
$curName  = getAdminSetting($conn, "smtp_from_name", "JuniorCode");
function maskSmtp(string $v): string {
    if (!$v) return "";
    if (strlen($v) <= 6) return str_repeat("•", strlen($v));
    return substr($v,0,3) . str_repeat("•", max(0,strlen($v)-6)) . substr($v,-3);
}
function maskKey(string $k): string {
    if (strlen($k) < 12) return $k ? "AIza••••••••" : "";
    return substr($k, 0, 8) . str_repeat("•", max(0, strlen($k) - 12)) . substr($k, -4);
}
$zoom_account_id   = getAdminSetting($conn, 'zoom_account_id',   '');
$zoom_client_id    = getAdminSetting($conn, 'zoom_client_id',    '');
$zoom_client_secret= getAdminSetting($conn, 'zoom_client_secret','');
$zoom_timezone     = getAdminSetting($conn, 'zoom_timezone',     'UTC');

function isActive($page, $cur) { return $page === $cur ? "active" : ""; }
?>
<!DOCTYPE html>
<html lang="<?= $adminLang ?>" dir="<?= $adminDir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('settings_title') ?> | JuniorCode Admin</title>
  <?= darkModeCSS() ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:   #3e5077;
      --secondary: #143674;
      --dark:      #0f172a;
      --muted:     #64748b;
      --soft:      #eff6ff;
      --border:    #dbeafe;
      --shadow:    0 18px 45px rgba(37,99,235,.08);
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background: radial-gradient(circle at top left, rgba(37,99,235,.08), transparent 22%),
                  radial-gradient(circle at bottom right, rgba(56,189,248,.08), transparent 22%),
                  linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%);
      color: var(--dark);
    }

    .app-shell { min-height: 100vh; display: flex; }

    /* ── Sidebar ── */
    .sidebar {
      width: 285px;
      background: linear-gradient(180deg,#0f172a 0%,#172554 100%);
      color: white;
      padding:  0;
      position: sticky; top: 0;
      height: 100vh; flex-shrink: 0;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow-y: auto;
      display: flex; flex-direction: column;
    }
    .sidebar-bottom { padding: 16px 18px; }
    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow-y: auto; }
    .sidebar-top-area { padding: 0 18px 18px; }
    .brand-box { display: flex; align-items: center; gap: 12px; padding: 0 4px 22px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }
    .logo-img { width:55px; height:55px; object-fit:contain; border-radius:12px; background:none; flex-shrink:0; }
    .brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
    .brand-sub   { font-size:.78rem; color:rgba(255,255,255,.75); letter-spacing:1px; margin-top:3px; }
    .nav-title   { font-size:.8rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,.55); margin:18px 10px 10px; font-weight:700; }
    .nav-custom  { display:flex; flex-direction:column; gap:4px; }
    .nav-link-custom {
      display:flex; align-items:center; gap:12px;
      color:rgba(255,255,255,.88); text-decoration:none;
      padding:12px 14px; border-radius:14px;
      transition:all .25s; font-weight:700;
    }
    .nav-link-custom:hover { background:rgba(255,255,255,.08); color:white; }
    .nav-link-custom.active {
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      color:white; box-shadow:0 10px 24px rgba(37,99,235,.28);
    }
    .nav-icon {
      width:32px; height:32px; border-radius:10px;
      background:rgba(255,255,255,.08);
      display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }

    /* ── Main ── */
    .main-content { flex:1; padding:26px; }

    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .topbar {
      display:flex; justify-content:space-between; align-items:center;
      gap:16px; margin-bottom:24px; padding:18px 20px;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      border-radius:22px; box-shadow:var(--shadow);
    }
    .topbar h1 { font-size:1.8rem; font-weight:900; margin:0; color:white; }
    .topbar p  { margin:4px 0 0; color:rgba(255,255,255,.85); }
    .admin-badge {
      background:rgba(255,255,255,.15); color:white;
      border-radius:999px; padding:10px 16px; font-weight:800;
    }

    /* ── Tabs ── */
    .settings-tabs { display:flex; gap:8px; margin-bottom:22px; flex-wrap:wrap; }
    .settings-tab-btn {
      padding:10px 20px; border-radius:12px; border:1.5px solid var(--border);
      background:white; font-weight:700; cursor:pointer;
      color:var(--muted); transition:all .2s; font-size:.92rem;
    }
    .settings-tab-btn.active {
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      color:white; border-color:transparent;
      box-shadow:0 6px 18px rgba(37,99,235,.22);
    }

    /* ── Panel cards ── */
    .panel-card {
      background:white; border-radius:22px; padding:24px;
      box-shadow:var(--shadow); border:1px solid #edf4ff;
      margin-bottom:22px;
    }
    .panel-title { font-size:1.1rem; font-weight:900; margin:0 0 6px; }
    .panel-sub   { color:var(--muted); font-size:.88rem; margin-bottom:20px; }
    .section-divider { border:none; border-top:1px solid var(--border); margin:20px 0; }

    /* ── Form elements ── */
    .form-control, .form-select, textarea {
      border-radius:12px; padding:11px 14px;
      border:1.5px solid #dbe4f0; font-size:.93rem;
    }
    .form-control:focus, .form-select:focus, textarea:focus {
      border-color:var(--primary); box-shadow:0 0 0 3px rgba(62,80,119,.12);
    }
    textarea { min-height:100px; }

    /* ── Buttons ── */
    .btn-main {
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      border:none; color:white; font-weight:800;
      border-radius:12px; padding:11px 22px;
      cursor:pointer; font-size:.93rem; transition:opacity .2s;
    }
    .btn-main:hover { opacity:.9; }

    /* ── Theme options ── */
    .theme-options { display:flex; gap:12px; flex-wrap:wrap; }
    .theme-opt {
      flex:1; min-width:140px; padding:16px;
      border:2px solid var(--border); border-radius:16px;
      background:white; cursor:pointer; text-align:center;
      transition:all .2s; font-weight:700;
    }
    .theme-opt:hover { border-color:#94a3b8; }
    .theme-opt.selected {
      border-color:var(--primary);
      background:rgba(62,80,119,.06);
      box-shadow:0 4px 14px rgba(62,80,119,.15);
    }
    .theme-opt .theme-icon { font-size:1.8rem; margin-bottom:8px; }
    .theme-opt .theme-label { font-size:.9rem; }

    /* ── Language options ── */
    .lang-options { display:flex; gap:10px; flex-wrap:wrap; }
    .lang-opt {
      flex:1; min-width:120px; padding:14px 12px;
      border:2px solid var(--border); border-radius:14px;
      background:white; cursor:pointer; text-align:center;
      transition:all .2s; font-weight:700; font-size:.9rem;
    }
    .lang-opt:hover { border-color:#94a3b8; }
    .lang-opt.selected {
      border-color:var(--primary);
      background:rgba(62,80,119,.06);
      box-shadow:0 4px 14px rgba(62,80,119,.15);
    }
    .lang-opt .lang-flag { font-size:1.2rem; margin-bottom:6px; display:flex; align-items:center; justify-content:center; gap:6px; }
    .lang-opt .lang-flag i { font-size:1.1rem; color:var(--primary); }

    /* ── Alert ── */
    .settings-alert {
      display:flex; align-items:center; gap:10px;
      padding:14px 18px; border-radius:14px;
      font-weight:700; margin-bottom:20px;
    }
    .settings-alert.success { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
    .settings-alert.danger  { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
    .settings-alert.warning { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }

    @media(max-width:991px){
      .app-shell { flex-direction:column; }
      .sidebar   { width:100%; height:auto; position:relative; }
      .main-content { padding:18px; }
      .topbar { flex-direction:column; align-items:flex-start; }
      .theme-opt { min-width:120px; }
    }
    @media(max-width:768px){
      .smtp-grid  { grid-template-columns:1fr !important; }
      .smtp-grid2 { grid-template-columns:1fr !important; }
    }
  </style>
</head>
<body>
<div class="app-shell">

  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar">
    <div class="sidebar-top-area">
    <div class="brand-box">
      <img src="images/robot2.png.png" class="logo-img" alt="Logo">
      <div>
        <div class="brand-title">JuniorCode</div>
        <div class="brand-sub"><?= t('admin_panel') ?></div>
      </div>
    </div>

    <div class="nav-title"><?= t('main_label') ?></div>
    <div class="nav-custom">
      <a href="admin_dashboard.php" class="nav-link-custom <?= isActive('admin_dashboard.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-house"></i></span>
        <span><?= t('nav_dashboard') ?></span>
      </a>
      <a href="manage_users.php" class="nav-link-custom <?= isActive('manage_users.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-users"></i></span>
        <span><?= t('nav_users') ?></span>
      </a>
      <a href="admin_teacher_students.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span>
      </a>
          <a href="admin_enrollments.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span>
          </a>
      <a href="manage_classes.php" class="nav-link-custom <?= isActive('manage_classes.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-book"></i></span>
        <span><?= t('nav_classes') ?></span>
      </a>
      <a href="teacher_earnings.php" class="nav-link-custom <?= isActive('teacher_earnings.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
        <span><?= t('nav_earnings') ?></span>
      </a>
      <a href="available_slots.php" class="nav-link-custom <?= isActive('available_slots.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
        <span><?= t('nav_slots') ?></span>
      </a>
      <a href="courses_home.php" class="nav-link-custom <?= isActive('courses.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
        <span><?= t('nav_courses') ?></span>
      </a>
      <a href="reports.php" class="nav-link-custom <?= isActive('reports.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
        <span><?= t('nav_reports') ?></span>
      </a>
      <a href="admin_certificates.php" class="nav-link-custom <?= isActive('admin_certificates.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-award"></i></span>
        <span>Certificates</span>
      </a>
    </div>
    </div>
    <div class="sidebar-bottom">
      <a href="settings.php" class="nav-link-custom <?= isActive('settings.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-gear"></i></span>
        <span><?= t('nav_settings') ?></span>
      </a>
      <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
      <a href="logout.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
        <span><?= t('nav_logout') ?></span>
      </a>
    </div>
  </aside>

  <!-- ══ MAIN ══ -->
  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
    </div>
    <div class="topbar">
      <div>
        <h1><?= t('settings_title') ?></h1>
        <p><?= t('settings_sub') ?></p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i><?= t('hello') ?>, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <?php if ($message !== ''): ?>
      <div class="settings-alert <?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'circle-check' : ($messageType === 'danger' ? 'circle-xmark' : 'triangle-exclamation') ?>"></i>
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- ── Tabs ── -->
    <div class="settings-tabs">
      <button class="settings-tab-btn active" onclick="showTab('appearance',this)">
        <i class="fas fa-palette me-1"></i><?= t('tab_appearance') ?>
      </button>
      <button class="settings-tab-btn" onclick="showTab('account',this)">
        <i class="fas fa-shield-halved me-1"></i><?= t('tab_account') ?>
      </button>
      <button class="settings-tab-btn" onclick="showTab('zoom',this)" id="zoom-tab-btn">
        <i class="fab fa-zoom me-1"></i> Zoom API
      </button>
      <button class="settings-tab-btn" onclick="showTab('ai_tutor',this)">
        <i class="fas fa-robot me-1"></i> AI Tutor
      </button>
      <button class="settings-tab-btn" onclick="showTab('smtp',this)">
        <i class="fas fa-envelope me-1"></i> Email / SMTP
      </button>
    </div>

    <!-- ════════════════════════════════════
         TAB 1 — Appearance & Language
    ════════════════════════════════════ -->
    <div id="tab-appearance">
      <form method="POST">
        <input type="hidden" name="action" value="appearance">

        <div class="panel-card">
          <h2 class="panel-title"><i class="fas fa-sun me-2" style="color:#f59e0b"></i><?= t('theme_label') ?></h2>
          <p class="panel-sub"><?= ($adminLang === 'ar') ? 'اختر مظهر لوحة التحكم' : (($adminLang === 'fr') ? "Choisissez l'apparence du tableau de bord" : 'Choose how the admin dashboard looks') ?></p>

          <div class="theme-options">
            <label class="theme-opt <?= $adminTheme === 'light' ? 'selected' : '' ?>">
              <input type="radio" name="theme" value="light" style="display:none" <?= $adminTheme === 'light' ? 'checked' : '' ?> onchange="selectThemeOpt(this)">
              <div class="theme-icon"><i class="fas fa-sun" style="color:#f59e0b"></i></div>
              <div class="theme-label"><?= t('light_mode') ?></div>
            </label>
            <label class="theme-opt <?= $adminTheme === 'dark' ? 'selected' : '' ?>">
              <input type="radio" name="theme" value="dark" style="display:none" <?= $adminTheme === 'dark' ? 'checked' : '' ?> onchange="selectThemeOpt(this)">
              <div class="theme-icon"><i class="fas fa-moon" style="color:#818cf8"></i></div>
              <div class="theme-label"><?= t('dark_mode') ?></div>
            </label>
          </div>
        </div>

        <div class="panel-card">
          <h2 class="panel-title"><i class="fas fa-language me-2" style="color:#0ea5e9"></i><?= t('language') ?></h2>
          <p class="panel-sub"><?= ($adminLang === 'ar') ? 'اختر لغة واجهة لوحة التحكم' : (($adminLang === 'fr') ? "Choisissez la langue de l'interface" : 'Choose the admin panel interface language') ?></p>

          <div class="lang-options">
            <label class="lang-opt <?= $adminLang === 'en' ? 'selected' : '' ?>">
              <input type="radio" name="lang" value="en" style="display:none" <?= $adminLang === 'en' ? 'checked' : '' ?> onchange="selectLangOpt(this)">
              <span class="lang-flag"><i class="fas fa-globe"></i> EN</span>
              <?= t('lang_en') ?>
            </label>
            <label class="lang-opt <?= $adminLang === 'ar' ? 'selected' : '' ?>">
              <input type="radio" name="lang" value="ar" style="display:none" <?= $adminLang === 'ar' ? 'checked' : '' ?> onchange="selectLangOpt(this)">
              <span class="lang-flag"><i class="fas fa-globe"></i> AR</span>
              <?= t('lang_ar') ?>
            </label>
            <label class="lang-opt <?= $adminLang === 'fr' ? 'selected' : '' ?>">
              <input type="radio" name="lang" value="fr" style="display:none" <?= $adminLang === 'fr' ? 'checked' : '' ?> onchange="selectLangOpt(this)">
              <span class="lang-flag"><i class="fas fa-globe"></i> FR</span>
              <?= t('lang_fr') ?>
            </label>
          </div>
        </div>

      </form>
    </div>

    <!-- ════════════════════════════════════
         TAB 2 — Account & Security
    ════════════════════════════════════ -->
    <div id="tab-account" style="display:none">
      <form method="POST">
        <input type="hidden" name="action" value="account">

        <div class="panel-card">
          <h2 class="panel-title"><i class="fas fa-envelope me-2" style="color:#3b82f6"></i><?= t('admin_email') ?></h2>
          <p class="panel-sub"><?= ($adminLang === 'ar') ? 'البريد الإلكتروني الإداري للنظام' : (($adminLang === 'fr') ? "E-mail administrateur de la plateforme" : 'Platform contact email for the admin') ?></p>
          <div class="mb-3">
            <label class="form-label"><?= t('admin_email') ?></label>
            <input type="email" name="admin_email" class="form-control"
                   value="<?= htmlspecialchars($admin_email) ?>"
                   placeholder="admin@juniorcode.com">
          </div>
          <button type="submit" class="btn-main">
            <i class="fas fa-floppy-disk me-1"></i><?= t('save_changes') ?>
          </button>
        </div>
      </form>

      <form method="POST">
        <input type="hidden" name="action" value="account">
        <div class="panel-card">
          <h2 class="panel-title"><i class="fas fa-lock me-2" style="color:#8b5cf6"></i><?= t('change_pwd') ?? 'Change Password' ?></h2>
          <p class="panel-sub"><?= t('leave_blank') ?></p>

          <div class="mb-3">
            <label class="form-label"><?= t('current_pwd') ?></label>
            <div class="input-group">
              <input type="password" name="current_password" id="cpwd" class="form-control" placeholder="••••••••">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('cpwd',this)">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label"><?= t('new_pwd') ?></label>
            <div class="input-group">
              <input type="password" name="new_password" id="npwd" class="form-control" placeholder="••••••••">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('npwd',this)">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label"><?= t('confirm_pwd') ?></label>
            <div class="input-group">
              <input type="password" name="confirm_password" id="cfpwd" class="form-control" placeholder="••••••••">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('cfpwd',this)">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-main">
            <i class="fas fa-key me-1"></i><?= ($adminLang === 'ar') ? 'تغيير كلمة المرور' : (($adminLang === 'fr') ? 'Changer le mot de passe' : 'Update Password') ?>
          </button>
        </div>
      </form>
    </div>

    <!-- ════════════════════════════════════
         TAB 3 — Zoom API
    ════════════════════════════════════ -->
    <div id="tab-zoom" style="display:none">
      <form method="POST">
        <input type="hidden" name="action" value="zoom">

        <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:18px;padding:22px 24px;margin-bottom:22px;">
          <h5 style="font-weight:900;color:#15803d;margin-bottom:6px;"><i class="fas fa-video me-2"></i>Zoom Server-to-Server OAuth</h5>
          <p style="color:#166534;font-size:0.9rem;margin:0;line-height:1.7;">
            These credentials are used to automatically create Zoom meetings when a class is scheduled.
            Get them from <strong>Zoom Marketplace → Develop → Build App → Server-to-Server OAuth</strong>.
          </p>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-bold">Account ID</label>
            <input type="text" name="zoom_account_id" class="form-control"
                   value="<?= htmlspecialchars($zoom_account_id) ?>" placeholder="xxxxxxxxxxxxxxxxxx">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Client ID</label>
            <input type="text" name="zoom_client_id" class="form-control"
                   value="<?= htmlspecialchars($zoom_client_id) ?>" placeholder="xxxxxxxxxxxxxxxxxx">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-bold">Client Secret</label>
            <input type="password" name="zoom_client_secret" class="form-control"
                   value="<?= htmlspecialchars($zoom_client_secret) ?>" placeholder="••••••••••••••••••">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Timezone</label>
            <select name="zoom_timezone" class="form-select">
              <?php
              $timezones = ['UTC','Asia/Beirut','Asia/Riyadh','Asia/Dubai','Europe/London','Europe/Paris','America/New_York','America/Los_Angeles'];
              foreach ($timezones as $tz):
              ?>
                <option value="<?= $tz ?>" <?= $zoom_timezone === $tz ? 'selected' : '' ?>><?= $tz ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <?php if ($zoom_account_id && $zoom_client_id && $zoom_client_secret): ?>
          <div style="background:#dcfce7;border:1px solid #86efac;border-radius:12px;padding:12px 16px;margin-bottom:16px;font-weight:700;color:#15803d;font-size:0.9rem;">
            <i class="fas fa-circle-check me-1"></i> Zoom credentials are configured. Meetings will be auto-created when scheduling classes.
          </div>
        <?php else: ?>
          <div style="background:#fef9c3;border:1px solid #fde047;border-radius:12px;padding:12px 16px;margin-bottom:16px;font-weight:700;color:#854d0e;font-size:0.9rem;">
            <i class="fas fa-triangle-exclamation me-1"></i> Zoom credentials not set. Fill in all fields above to enable auto-generation.
          </div>
        <?php endif; ?>

        <button type="submit" class="btn-main">Save Zoom Settings</button>
      </form>
    </div>

    <!-- ════════════════════════════════════
         TAB 4 — AI Tutor
    ════════════════════════════════════ -->
    <div id="tab-ai_tutor" style="display:none">
      <form method="POST">
        <input type="hidden" name="action" value="ai_tutor">

        <div class="panel-card">
          <h2 class="panel-title"><i class="fas fa-robot me-2" style="color:#7c3aed"></i>AI Tutor — Gemini API</h2>
          <p class="panel-sub">Configure the Gemini API key that powers the student coding tutor chat.</p>

          <div class="row g-4">
            <!-- API Key -->
            <div class="col-md-6">
              <label class="form-label">Current API Key</label>
              <div style="background:#f8fbff;border:1px solid #e2eaf8;border-radius:10px;padding:10px 14px;font-family:monospace;font-size:0.88rem;color:#334155;display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <span><?= $ai_api_key ? maskKey($ai_api_key) : "Not set" ?></span>
                <?php if ($ai_api_key): ?>
                  <span style="color:#16a34a;font-weight:800;font-size:0.78rem;"><i class="fas fa-check-circle me-1"></i>Active</span>
                <?php else: ?>
                  <span style="color:#dc2626;font-weight:800;font-size:0.78rem;"><i class="fas fa-times-circle me-1"></i>Missing</span>
                <?php endif; ?>
              </div>
              <label class="form-label">
                <?= $ai_api_key ? "Replace API Key" : "Enter API Key" ?>
                <span class="fw-normal text-muted">(<?= $ai_api_key ? "leave blank to keep current" : "required" ?>)</span>
              </label>
              <div class="input-group">
                <input type="password" name="claude_api_key" id="aiKeyInput" class="form-control"
                       placeholder="AIzaSy..." autocomplete="off" style="font-family:monospace;">
                <button type="button" class="btn btn-outline-secondary" onclick="toggleAiKey()">
                  <i class="fas fa-eye" id="aiEyeIcon"></i>
                </button>
              </div>
              <div class="form-text">Get your free key at <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a></div>
            </div>

            <!-- Model + Limit -->
            <div class="col-md-6">
              <label class="form-label">AI Model</label>
              <select name="claude_model" class="form-select mb-1">
                <option value="gemini-2.5-flash" <?= $ai_model === "gemini-2.5-flash" ? "selected" : "" ?>>Gemini 2.5 Flash — Fast &amp; free (recommended)</option>
                <option value="gemini-2.0-flash" <?= $ai_model === "gemini-2.0-flash" ? "selected" : "" ?>>Gemini 2.0 Flash — Stable</option>
                <option value="gemini-2.5-pro"   <?= $ai_model === "gemini-2.5-pro"   ? "selected" : "" ?>>Gemini 2.5 Pro — Most powerful</option>
              </select>
              <div class="form-text mb-3">Gemini 2.5 Flash is best for tutoring — fast replies with a generous free quota.</div>

              <label class="form-label">Daily Message Limit per Student</label>
              <input type="number" name="daily_limit" class="form-control" min="1" max="500"
                     value="<?= $ai_daily_limit ?>">
              <div class="form-text">Students can send this many messages per day. Recommended: 20–50.</div>
            </div>
          </div>

          <button type="submit" class="btn-main mt-4">
            <i class="fas fa-floppy-disk me-1"></i>Save AI Tutor Settings
          </button>
        </div>

        <!-- How-to -->
        <div class="panel-card">
          <h2 class="panel-title" style="font-size:0.95rem;"><i class="fas fa-circle-info me-1" style="color:#3b82f6"></i>How to get your Gemini API key</h2>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:12px;">
            <div style="background:#f8fbff;border:1px solid #dbeafe;border-radius:10px;padding:12px 14px;font-size:0.84rem;color:#475569;"><strong style="color:var(--primary);display:block;margin-bottom:4px;">Step 1</strong>Go to <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a> and sign in with Google</div>
            <div style="background:#f8fbff;border:1px solid #dbeafe;border-radius:10px;padding:12px 14px;font-size:0.84rem;color:#475569;"><strong style="color:var(--primary);display:block;margin-bottom:4px;">Step 2</strong>Click <strong>Create API Key</strong></div>
            <div style="background:#f8fbff;border:1px solid #dbeafe;border-radius:10px;padding:12px 14px;font-size:0.84rem;color:#475569;"><strong style="color:var(--primary);display:block;margin-bottom:4px;">Step 3</strong>Copy the key (starts with <code>AIza</code>)</div>
            <div style="background:#f8fbff;border:1px solid #dbeafe;border-radius:10px;padding:12px 14px;font-size:0.84rem;color:#475569;"><strong style="color:var(--primary);display:block;margin-bottom:4px;">Step 4</strong>Paste it above and click Save</div>
          </div>
        </div>

      </form>
    </div>

    <!-- ════════════════════════════════════
         TAB 5 — Email / SMTP
    ════════════════════════════════════ -->
    <div id="tab-smtp" style="display:none">
      <div class="panel-card">
        <h2 class="panel-title"><i class="fas fa-envelope me-2" style="color:#7c3aed;"></i>Email (SMTP)</h2>
        <p class="panel-sub">Used to send teachers their class notifications and reminders.</p>

        <form method="POST">
          <input type="hidden" name="action" value="smtp">

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">SMTP Host</label>
              <input type="text" name="smtp_host" id="smtp_host" class="form-control"
                     value="<?= htmlspecialchars($curHost) ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Port</label>
              <select name="smtp_port" id="smtp_port" class="form-select">
                <option value="587" <?= $curPort === '587' ? 'selected' : '' ?>>587 (STARTTLS)</option>
                <option value="465" <?= $curPort === '465' ? 'selected' : '' ?>>465 (SSL)</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Sender Name</label>
              <input type="text" name="smtp_from_name" class="form-control"
                     value="<?= htmlspecialchars($curName) ?>" placeholder="JuniorCode">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Gmail Address</label>
              <input type="email" name="smtp_user" class="form-control"
                     value="<?= htmlspecialchars($curUser) ?>" placeholder="your@gmail.com">
              <?php if ($curUser): ?>
                <div class="form-text" style="color:#16a34a;font-weight:700;"><i class="fas fa-check-circle me-1"></i>Currently: <?= htmlspecialchars($curUser) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">
                App Password
                <?php if ($curPass): ?><span style="color:#16a34a;font-size:0.78rem;font-weight:700;margin-left:6px;"><i class="fas fa-check-circle me-1"></i>Set</span><?php endif; ?>
              </label>
              <div class="input-group">
                <input type="password" name="smtp_pass" id="smtpPass" class="form-control"
                       placeholder="<?= $curPass ? '(leave blank to keep current)' : '16-char app password' ?>">
                <button type="button" class="btn btn-outline-secondary" onclick="toggleSmtpPass()">
                  <i class="fas fa-eye" id="smtpPassEye"></i>
                </button>
              </div>
              <div class="form-text">Use a Gmail <strong>App Password</strong> — not your regular password</div>
            </div>
          </div>

          <hr style="border:none;border-top:1px solid #f1f5f9;margin:18px 0;">

          <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
              <label class="form-label fw-bold">Test — send a sample email to:</label>
              <input type="email" name="test_email" id="smtp_test_email" class="form-control" placeholder="someone@example.com">
            </div>
            <button type="submit" onclick="this.form.querySelector('[name=action]').value='smtp_test';return validateSmtpTest()"
                    style="padding:11px 22px;border:none;border-radius:12px;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;font-weight:800;cursor:pointer;">
              <i class="fas fa-paper-plane me-1"></i> Send Test
            </button>
            <button type="submit" class="btn-main" style="padding:11px 22px;">
              <i class="fas fa-floppy-disk me-1"></i> Save
            </button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<script>
// ── Tab switching ──────────────────────────────────────────────
function showTab(id, btn) {
  ['appearance','account','zoom','ai_tutor','smtp'].forEach(function(t) {
    document.getElementById('tab-' + t).style.display = (t === id) ? '' : 'none';
  });
  document.querySelectorAll('.settings-tab-btn').forEach(function(b) {
    b.classList.remove('active');
  });
  btn.classList.add('active');
}

// ── Theme option visual toggle ─────────────────────────────────
function selectThemeOpt(radio) {
  document.querySelectorAll('.theme-opt').forEach(function(el) {
    el.classList.remove('selected');
  });
  radio.closest('.theme-opt').classList.add('selected');

  // Instant visual preview, then save via form submit
  if (radio.value === 'dark') {
    document.documentElement.classList.add('dark');
    localStorage.setItem('jc-theme', 'dark');
  } else {
    document.documentElement.classList.remove('dark');
    localStorage.setItem('jc-theme', 'light');
  }
  radio.closest('form').submit();
}

// ── Language option visual toggle — auto-submits immediately ──
function selectLangOpt(radio) {
  document.querySelectorAll('.lang-opt').forEach(function(el) {
    el.classList.remove('selected');
  });
  radio.closest('.lang-opt').classList.add('selected');
  radio.closest('form').submit();
}

// ── AI key eye toggle ─────────────────────────────────────────
function toggleAiKey() {
  var inp = document.getElementById('aiKeyInput');
  var ico = document.getElementById('aiEyeIcon');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}

// ── Password eye toggle ────────────────────────────────────────
function togglePwd(id, btn) {
  var input = document.getElementById(id);
  var showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  btn.querySelector('i').className = showing ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// ── SMTP helpers ───────────────────────────────────────────────
function toggleSmtpPass() {
  var inp = document.getElementById('smtpPass');
  var ico = document.getElementById('smtpPassEye');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}
function validateSmtpTest() {
  var to = document.getElementById('smtp_test_email').value.trim();
  if (!to) { alert('Please enter a recipient email address for the test.'); return false; }
  return true;
}
// ── Sync localStorage on page load ────────────────────────────
(function() {
  var savedTheme = '<?= $adminTheme ?>';
  localStorage.setItem('jc-theme', savedTheme);
})();
</script>
<script src="logout-modal.js"></script>
</body>
</html>


