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

}

$admin_email = getAdminSetting($conn, "admin_email", "admin@juniorcode.com");

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
      padding: 24px 18px;
      position: sticky; top: 0;
      height: 100vh; overflow-y: auto; flex-shrink: 0;
    }
    .brand-box {
      display: flex; align-items: center; gap: 12px;
      margin-bottom: 28px; padding: 10px 12px;
      border-radius: 18px;
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.08);
    }
    .logo-img { width:55px; height:55px; object-fit:contain; border-radius:12px; flex-shrink:0; }
    .brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
    .brand-sub   { font-size:.78rem; color:rgba(255,255,255,.75); letter-spacing:1px; margin-top:3px; }
    .nav-title   { font-size:.8rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,.55); margin:18px 10px 10px; font-weight:700; }
    .nav-custom  { display:flex; flex-direction:column; gap:8px; }
    .nav-link-custom {
      display:flex; align-items:center; gap:12px;
      color:rgba(255,255,255,.88); text-decoration:none;
      padding:13px 14px; border-radius:14px;
      transition:all .25s; font-weight:700;
    }
    .nav-link-custom:hover { background:rgba(255,255,255,.08); color:white; }
    .nav-link-custom.active {
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      color:white; box-shadow:0 10px 24px rgba(37,99,235,.28);
    }
    .nav-icon {
      width:34px; height:34px; border-radius:10px;
      background:rgba(255,255,255,.08);
      display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }

    /* ── Main ── */
    .main-content { flex:1; padding:26px; }

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
    .lang-opt .lang-flag { font-size:1.6rem; margin-bottom:6px; display:block; }

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
  </style>
</head>
<body>
<div class="app-shell">

  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar">
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
      <a href="courses.php" class="nav-link-custom <?= isActive('courses.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
        <span><?= t('nav_courses') ?></span>
      </a>
      <a href="reports.php" class="nav-link-custom <?= isActive('reports.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
        <span><?= t('nav_reports') ?></span>
      </a>
      <a href="settings.php" class="nav-link-custom <?= isActive('settings.php',$currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-gear"></i></span>
        <span><?= t('nav_settings') ?></span>
      </a>
      <a href="logout.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
        <span><?= t('nav_logout') ?></span>
      </a>
    </div>
  </aside>

  <!-- ══ MAIN ══ -->
  <main class="main-content">
    <div class="topbar">
      <div>
        <h1><?= t('settings_title') ?></h1>
        <p><?= t('settings_sub') ?></p>
      </div>
      <div class="admin-badge"><?= t('hello') ?>, <?= htmlspecialchars($adminName) ?></div>
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
              <span class="lang-flag">🇬🇧</span>
              <?= t('lang_en') ?>
            </label>
            <label class="lang-opt <?= $adminLang === 'ar' ? 'selected' : '' ?>">
              <input type="radio" name="lang" value="ar" style="display:none" <?= $adminLang === 'ar' ? 'checked' : '' ?> onchange="selectLangOpt(this)">
              <span class="lang-flag">🇸🇦</span>
              <?= t('lang_ar') ?>
            </label>
            <label class="lang-opt <?= $adminLang === 'fr' ? 'selected' : '' ?>">
              <input type="radio" name="lang" value="fr" style="display:none" <?= $adminLang === 'fr' ? 'checked' : '' ?> onchange="selectLangOpt(this)">
              <span class="lang-flag">🇫🇷</span>
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

  </main>
</div>

<script>
// ── Tab switching ──────────────────────────────────────────────
function showTab(id, btn) {
  ['appearance','account'].forEach(function(t) {
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

// ── Password eye toggle ────────────────────────────────────────
function togglePwd(id, btn) {
  var input = document.getElementById(id);
  var showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  btn.querySelector('i').className = showing ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// ── Sync localStorage on page load ────────────────────────────
(function() {
  var savedTheme = '<?= $adminTheme ?>';
  localStorage.setItem('jc-theme', savedTheme);
})();
</script>
</body>
</html>
