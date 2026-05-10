<?php
session_start();
require_once "db.php";
require_once "admin_prefs.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName   = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

function isActive($page, $cur) { return $page === $cur ? "active" : ""; }

$saved = false;
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newKey = trim($_POST["claude_api_key"] ?? "");
    $model  = trim($_POST["claude_model"]   ?? "gemini-2.5-flash");
    $limit  = max(1, (int)($_POST["daily_limit"] ?? 30));

    if ($newKey && !str_starts_with($newKey, "AIza")) {
        $error = "That doesn't look like a valid Google Gemini API key (should start with AIza).";
    } else {
        if ($newKey) saveAdminSetting($conn, "claude_api_key", $newKey);
        saveAdminSetting($conn, "claude_model", $model);
        saveAdminSetting($conn, "chat_daily_limit", (string)$limit);
        $saved = true;
    }
}

$currentKey   = getAdminSetting($conn, "claude_api_key",    "");
$currentModel = getAdminSetting($conn, "claude_model",      "gemini-2.5-flash");
$currentLimit = (int)getAdminSetting($conn, "chat_daily_limit", "30");

function maskKey(string $k): string {
    if (strlen($k) < 12) return $k ? "AIza••••••••" : "";
    return substr($k, 0, 8) . str_repeat("•", max(0, strlen($k) - 12)) . substr($k, -4);
}
?>
<!DOCTYPE html>
<html lang="<?= $adminLang ?>" dir="<?= $adminDir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Tutor Settings | JuniorCode</title>
<link rel="icon" type="image/png" href="images/robot2.png.png">
<?= darkModeCSS() ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --border:#edf4ff; --shadow:0 18px 45px rgba(37,99,235,0.08); }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,Helvetica,sans-serif; background: radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%), radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%), linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); color:var(--dark); }

.app-shell { min-height:100vh; display:flex; }

/* ── Sidebar ── */
.sidebar { width:285px; flex-shrink:0; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:#fff; padding:0; justify-content:space-between; position:sticky; top:0; height:100vh; overflow-y:auto; display:flex; flex-direction:column; transition:width 0.3s ease,padding 0.3s ease,min-width 0.3s ease; overflow:hidden; }
body.sidebar-collapsed .sidebar { width:0; padding:0; min-width:0; }
.brand { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
.brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
.brand-title { font-weight:900; font-size:1.1rem; color:#fff; line-height:1.2; }
.brand-sub   { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
.nav-custom { display:flex; flex-direction:column; gap:4px; }
.sidebar-bottom { padding:16px 18px; border-top:1px solid rgba(255,255,255,0.1); }
.nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all 0.22s ease; font-weight:700; }
.nav-link-custom:hover { background:rgba(255,255,255,0.08); color:#fff; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 8px 20px rgba(30,50,100,0.35); }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.nav-link-custom.active .nav-icon { background:rgba(255,255,255,0.18); }

/* ── Main ── */
.main-content { flex:1; padding:28px; min-width:0; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

/* ── Topbar ── */
.topbar { background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; padding:22px 26px; margin-bottom:26px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; color:#fff; box-shadow:0 12px 28px rgba(37,99,235,0.3); }
.topbar h1 { font-size:1.7rem; font-weight:900; }
.topbar p  { margin:4px 0 0; opacity:0.82; font-size:0.97rem; }
.admin-badge { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.2); border-radius:12px; padding:10px 18px; font-weight:800; font-size:0.92rem; color:#fff; }

/* ── Settings card ── */
.settings-card { background:#fff; border-radius:22px; border:1px solid var(--border); box-shadow:var(--shadow); padding:32px; }
.form-label  { font-weight:800; color:#334155; font-size:0.9rem; margin-bottom:6px; display:block; }
.form-hint   { font-size:0.78rem; color:var(--muted); margin-top:4px; }
.form-control, .form-select { border-radius:12px; padding:11px 14px; border:1.5px solid #dbe4f0; font-size:0.93rem; width:100%; outline:none; font-family:inherit; transition:border-color .2s; }
.form-control:focus, .form-select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(62,80,119,0.1); }
.key-row { position:relative; }
.key-row .form-control { padding-right:44px; font-family:monospace; letter-spacing:0.03em; }
.toggle-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--muted); cursor:pointer; font-size:1rem; padding:4px; }
.toggle-eye:hover { color:var(--primary); }
.current-key { background:#f8fbff; border:1px solid #e2eaf8; border-radius:10px; padding:10px 14px; font-family:monospace; font-size:0.88rem; color:#334155; display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
.key-status-ok   { color:#16a34a; font-weight:800; font-size:0.78rem; }
.key-status-none { color:#dc2626; font-weight:800; font-size:0.78rem; }
.divider { border:none; border-top:1px solid #f1f5f9; margin:22px 0; }
.btn-save { padding:12px 28px; border:none; border-radius:14px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; font-weight:900; font-size:1rem; cursor:pointer; transition:opacity .2s; margin-top:22px; }
.btn-save:hover { opacity:.9; }
.alert-ok  { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; border-radius:12px; padding:12px 16px; font-weight:700; margin-bottom:20px; font-size:0.9rem; }
.alert-err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:12px; padding:12px 16px; font-weight:700; margin-bottom:20px; font-size:0.9rem; }
.how-to { background:#f8fbff; border:1px solid #dbeafe; border-radius:14px; padding:16px 18px; margin-top:22px; }
.how-to-title { font-weight:800; color:var(--primary); font-size:0.88rem; margin-bottom:10px; }
.how-to ol { margin:0; padding-left:18px; font-size:0.84rem; color:#475569; line-height:1.9; }
.how-to a  { color:#2563eb; font-weight:700; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
.form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:24px; margin-top:16px; }
@media(max-width:768px){ .form-grid,.form-grid-3{ grid-template-columns:1fr; } }
</style>
</head>
<body>

<div class="app-shell">

  <!-- ── SIDEBAR ── -->
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
      <a href="admin_dashboard.php" class="nav-link-custom <?= isActive('admin_dashboard.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-house"></i></span><span><?= t('nav_dashboard') ?></span>
      </a>
      <a href="manage_users.php" class="nav-link-custom <?= isActive('manage_users.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-users"></i></span><span><?= t('nav_users') ?></span>
      </a>
      <a href="manage_classes.php" class="nav-link-custom <?= isActive('manage_classes.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-book"></i></span><span><?= t('nav_classes') ?></span>
      </a>
      <a href="teacher_earnings.php" class="nav-link-custom <?= isActive('teacher_earnings.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span><?= t('nav_earnings') ?></span>
      </a>
      <a href="available_slots.php" class="nav-link-custom <?= isActive('available_slots.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span><?= t('nav_slots') ?></span>
      </a>
      <a href="courses.php" class="nav-link-custom <?= isActive('courses.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span><?= t('nav_courses') ?></span>
      </a>
      <a href="reports.php" class="nav-link-custom <?= isActive('reports.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span><?= t('nav_reports') ?></span>
      </a>
      <a href="admin_certificates.php" class="nav-link-custom <?= isActive('admin_certificates.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span>
      </a>
      <a href="admin_ai_settings.php" class="nav-link-custom <?= isActive('admin_ai_settings.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span>
      </a>
    </div>
    </div>
    <div class="sidebar-bottom">
      <a href="settings.php" class="nav-link-custom <?= isActive('settings.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-gear"></i></span><span><?= t('nav_settings') ?></span>
      </a>
      <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
      <a href="logout.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span><?= t('nav_logout') ?></span>
      </a>
    </div>
  </aside>

  <!-- ── MAIN ── -->
  <main class="main-content">

    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
    </div>

    <!-- Topbar -->
    <div class="topbar">
      <div>
        <h1><i class="fas fa-robot me-2"></i>AI Tutor Settings</h1>
        <p>Configure the Gemini API key that powers the student coding tutor</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?></div>
    </div>

    <div class="settings-card">

      <?php if ($saved): ?>
        <div class="alert-ok"><i class="fas fa-check-circle me-2"></i>Settings saved successfully.</div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert-err"><i class="fas fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">

        <div class="form-grid">
          <!-- Left: API Key -->
          <div>
            <label class="form-label">Current API Key</label>
            <div class="current-key">
              <span><?= $currentKey ? maskKey($currentKey) : "Not set" ?></span>
              <?php if ($currentKey): ?>
                <span class="key-status-ok"><i class="fas fa-check-circle me-1"></i>Active</span>
              <?php else: ?>
                <span class="key-status-none"><i class="fas fa-times-circle me-1"></i>Missing</span>
              <?php endif; ?>
            </div>
            <label class="form-label" style="margin-top:14px;">
              <?= $currentKey ? "Replace API Key" : "Enter API Key" ?>
              <span style="font-weight:400;color:var(--muted);">(<?= $currentKey ? "leave blank to keep current" : "required" ?>)</span>
            </label>
            <div class="key-row">
              <input type="password" name="claude_api_key" id="apiKeyInput" class="form-control"
                     placeholder="AIzaSy..." autocomplete="off">
              <button type="button" class="toggle-eye" onclick="toggleKey()"><i class="fas fa-eye" id="eyeIcon"></i></button>
            </div>
            <div class="form-hint">Get your free key at <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a></div>
          </div>

          <!-- Right: Model + Limit + How-to -->
          <div>
            <label class="form-label">AI Model</label>
            <select name="claude_model" class="form-select">
              <option value="gemini-2.5-flash" <?= $currentModel === "gemini-2.5-flash" ? "selected" : "" ?>>Gemini 2.5 Flash — Fast &amp; free (recommended)</option>
              <option value="gemini-2.0-flash" <?= $currentModel === "gemini-2.0-flash" ? "selected" : "" ?>>Gemini 2.0 Flash — Stable</option>
              <option value="gemini-2.5-pro"   <?= $currentModel === "gemini-2.5-pro"   ? "selected" : "" ?>>Gemini 2.5 Pro — Most powerful</option>
            </select>
            <div class="form-hint">Gemini 2.5 Flash is best for a tutoring chatbot — fast replies with a generous free quota.</div>

            <div style="margin-top:20px;">
              <label class="form-label">Daily Message Limit per Student</label>
              <input type="number" name="daily_limit" class="form-control" min="1" max="500"
                     value="<?= $currentLimit ?>">
              <div class="form-hint">Students can send this many messages per day. Recommended: 20–50.</div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save Settings</button>
      </form>

      <div class="how-to" style="margin-top:28px;">
        <div class="how-to-title"><i class="fas fa-circle-info me-1"></i>How to get your Gemini API key</div>
        <ol style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;list-style:none;padding:0;margin:0;">
          <li style="background:#fff;border:1px solid #dbeafe;border-radius:10px;padding:10px 14px;font-size:0.84rem;color:#475569;"><strong style="color:var(--primary);display:block;margin-bottom:4px;">Step 1</strong>Go to <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a> and sign in with Google</li>
          <li style="background:#fff;border:1px solid #dbeafe;border-radius:10px;padding:10px 14px;font-size:0.84rem;color:#475569;"><strong style="color:var(--primary);display:block;margin-bottom:4px;">Step 2</strong>Click <strong>Create API Key</strong></li>
          <li style="background:#fff;border:1px solid #dbeafe;border-radius:10px;padding:10px 14px;font-size:0.84rem;color:#475569;"><strong style="color:var(--primary);display:block;margin-bottom:4px;">Step 3</strong>Copy the key (starts with <code>AIza</code>)</li>
          <li style="background:#fff;border:1px solid #dbeafe;border-radius:10px;padding:10px 14px;font-size:0.84rem;color:#475569;"><strong style="color:var(--primary);display:block;margin-bottom:4px;">Step 4</strong>Paste it in the API Key field above and click Save</li>
        </ol>
      </div>

    </div>
  </main>
</div>

<script>
function toggleKey() {
  const inp = document.getElementById('apiKeyInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>
