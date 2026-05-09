<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName   = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

/* ── Statistics ── */
$totalUsers     = (int)($conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()["c"] ?? 0);
$totalStudents  = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role='student'")->fetch_assoc()["c"] ?? 0);
$totalTeachers  = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role='teacher'")->fetch_assoc()["c"] ?? 0);
$totalClasses   = (int)($conn->query("SELECT COUNT(*) AS c FROM classes")->fetch_assoc()["c"] ?? 0);
$totalEarnings  = (float)($conn->query("SELECT COALESCE(SUM(amount),0) AS c FROM teacher_earnings")->fetch_assoc()["c"] ?? 0);
$availableSlots = (int)($conn->query("SELECT COUNT(*) AS c FROM teacher_availability WHERE status='available'")->fetch_assoc()["c"] ?? 0);

$today = date("Y-m-d");
$stmt  = $conn->prepare("SELECT COUNT(*) AS c FROM teacher_availability WHERE status='available' AND available_date=?");
$stmt->bind_param("s", $today);
$stmt->execute();
$todaySlots = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
$stmt->close();

/* ── Recent Users ── */
$recentUsers = [];
$r = $conn->query("SELECT username, role FROM users ORDER BY id DESC LIMIT 6");
if ($r) while ($row = $r->fetch_assoc()) $recentUsers[] = $row;

/* ── Recent Earnings ── */
$recentEarnings = [];
$r = $conn->query("SELECT lesson_title, amount FROM teacher_earnings ORDER BY id DESC LIMIT 6");
if ($r) while ($row = $r->fetch_assoc()) $recentEarnings[] = $row;

/* ── Upcoming Slots ── */
$recentAvailability = [];
$r = $conn->query("SELECT teacher_name, available_date, available_time FROM teacher_availability WHERE status='available' ORDER BY available_date ASC, available_time ASC LIMIT 8");
if ($r) while ($row = $r->fetch_assoc()) $recentAvailability[] = $row;

function isActive($page, $cur) { return $page === $cur ? "active" : ""; }

require_once "admin_prefs.php";
?>
<!DOCTYPE html>
<html lang="<?= $adminLang ?>" dir="<?= $adminDir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('dash_title') ?> | JuniorCode Academy</title>
  <?= darkModeCSS() ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:   #3e5077;
      --secondary: #143674;
      --dark:      #0f172a;
      --muted:     #64748b;
      --border:    #edf4ff;
      --shadow:    0 18px 45px rgba(37,99,235,0.08);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      background:
        radial-gradient(circle at top left,  rgba(37,99,235,0.08), transparent 22%),
        radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
        linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
      color: var(--dark);
    }

    .app-shell { min-height: 100vh; display: flex; }

    /* ── Sidebar ── */
    .sidebar {
      width: 285px; flex-shrink: 0;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: #fff;
      padding: 24px 18px;
      position: sticky; top: 0;
      height: 100vh; overflow-y: auto;
      display: flex; flex-direction: column;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease;
      overflow: hidden;
    }
    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; }

    .brand {
      display: flex; align-items: center; gap: 12px;
      padding: 0 4px 22px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 10px;
    }

    .brand-logo-img {
      width: 55px; height: 55px;
      object-fit: contain; flex-shrink: 0;
      background: none; border-radius: 0;
    }

    .brand-title { font-weight: 900; font-size: 1.1rem; color: #fff; line-height: 1.2; }
    .brand-sub   { font-size: 0.75rem; color: rgba(255,255,255,0.55); letter-spacing: 1px; margin-top: 3px; }

    .nav-title {
      font-size: 0.78rem; text-transform: uppercase;
      letter-spacing: 1.3px; color: rgba(255,255,255,0.45);
      margin: 20px 10px 10px; font-weight: 700;
    }

    .nav-custom { display: flex; flex-direction: column; gap: 4px; }

    .nav-link-custom {
      display: flex; align-items: center; gap: 12px;
      color: rgba(255,255,255,0.78); text-decoration: none;
      padding: 12px 14px; border-radius: 14px;
      transition: all 0.22s ease; font-weight: 700;
    }

    .nav-link-custom:hover { background: rgba(255,255,255,0.08); color: #fff; }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; box-shadow: 0 8px 20px rgba(30,50,100,0.35);
    }

    .nav-icon {
      width: 32px; height: 32px; border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0;
    }

    .nav-link-custom.active .nav-icon { background: rgba(255,255,255,0.18); }

    /* ── Main ── */
    .main-content { flex: 1; padding: 28px; min-width: 0; }

    /* ── Hamburger ── */
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    /* ── Topbar ── */
    .topbar {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 22px; padding: 22px 26px; margin-bottom: 26px;
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 14px; color: #fff;
      box-shadow: 0 12px 28px rgba(37,99,235,0.3);
    }

    .topbar h1 { font-size: 1.7rem; font-weight: 900; }
    .topbar p  { margin: 4px 0 0; opacity: 0.82; font-size: 0.97rem; }

    .admin-badge {
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 12px; padding: 10px 18px;
      font-weight: 800; font-size: 0.92rem; color: #fff;
    }

    /* ── Stats grid ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 18px; margin-bottom: 26px;
    }

    .stat-card {
      background: #fff; border: 1px solid var(--border);
      border-radius: 22px; padding: 22px;
      box-shadow: var(--shadow);
      position: relative; overflow: hidden;
    }

    .stat-card::before { display: none; }

    .stat-head {
      display: flex; justify-content: space-between;
      align-items: center; margin-bottom: 14px;
    }

    .stat-label { font-weight: 700; color: var(--muted); font-size: 0.9rem; }

    .stat-icon {
      width: 44px; height: 44px; border-radius: 14px;
      background: #f1f5f9; color: var(--primary);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
    }

    .stat-value { font-size: 2.2rem; font-weight: 900; line-height: 1; margin-bottom: 6px; color: var(--dark); }
    .stat-note  { color: var(--muted); font-size: 0.88rem; }

    /* ── Content grid ── */
    .content-grid {
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      gap: 20px; margin-bottom: 20px;
    }

    .panel-card {
      background: #fff; border: 1px solid var(--border);
      border-radius: 22px; padding: 22px 24px;
      box-shadow: var(--shadow);
      position: relative; overflow: hidden;
    }

    .panel-card::before { display: none; }

    .panel-header {
      display: flex; justify-content: space-between;
      align-items: center; margin-bottom: 18px;
      padding-bottom: 14px; border-bottom: 1px solid var(--border);
    }

    .panel-title { font-size: 1.05rem; font-weight: 900; color: var(--primary); }

    .panel-link {
      text-decoration: none; color: var(--primary);
      font-weight: 800; font-size: 0.88rem;
      background: #eff6ff; border-radius: 999px;
      padding: 5px 14px; transition: background 0.2s;
    }
    .panel-link:hover { background: #dbeafe; color: var(--primary); }

    /* ── Tables ── */
    .table { margin-bottom: 0; }

    .table thead th {
      background: #f8fbff; color: var(--dark);
      font-size: 0.85rem; font-weight: 800;
      border-bottom: 1px solid #e6eefb; padding: 10px 12px;
    }

    .table td { vertical-align: middle; font-size: 0.9rem; padding: 10px 12px; }

    .user-row-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; font-weight: 900; font-size: 0.9rem;
      display: inline-flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .role-badge {
      display: inline-block; border-radius: 999px;
      padding: 3px 11px; font-size: 0.76rem; font-weight: 700;
      background: #f1f5f9; color: var(--muted);
    }

    /* ── Slots table ── */
    .slot-card {
      background: #fff; border: 1px solid var(--border);
      border-radius: 22px; padding: 22px 24px;
      box-shadow: var(--shadow);
      position: relative; overflow: hidden;
    }

    .slot-card::before { display: none; }

    .empty-box {
      text-align: center; padding: 28px 18px;
      color: var(--muted); font-weight: 700; font-size: 0.92rem;
      background: #f8fafc; border-radius: 14px;
      border: 1px dashed #cbd5e1;
    }

    /* ── Responsive ── */
    @media (max-width: 1200px) {
      .stats-grid   { grid-template-columns: repeat(3, 1fr); }
      .content-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 991px) {
      .app-shell { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: relative; }
      .main-content { padding: 18px; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 575px) {
      .stats-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<div class="app-shell">

  <!-- ── SIDEBAR ── -->
  <aside class="sidebar">
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
      <a href="settings.php" class="nav-link-custom <?= isActive('settings.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-gear"></i></span><span><?= t('nav_settings') ?></span>
      </a>
      <a href="admin_certificates.php" class="nav-link-custom <?= isActive('admin_certificates.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span>
      </a>
      <a href="admin_ai_settings.php" class="nav-link-custom <?= isActive('admin_ai_settings.php', $currentPage) ?>">
        <span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span>
      </a>
      <a href="logout.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span><?= t('nav_logout') ?></span>
      </a>
    </div>
  </aside>

  <!-- ── MAIN ── -->
  <main class="main-content">

    <!-- Hamburger toggle -->
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
    </div>

    <!-- Topbar -->
    <div class="topbar">
      <div>
        <h1><?= t('dash_title') ?></h1>
        <p><?= t('dash_sub') ?></p>
      </div>
      <div class="admin-badge">
        <i class="fas fa-user-shield me-2"></i><?= t('hello') ?>, <?= htmlspecialchars($adminName) ?>
        &nbsp;·&nbsp; <?= date("d M Y") ?>
      </div>
    </div>

    <!-- Stats grid — 6 cards, 3 columns -->
    <div class="stats-grid">

      <div class="stat-card">
        <div class="stat-head">
          <div class="stat-label"><?= t('total_users') ?></div>
          <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-note"><?= t('all_accounts') ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-head">
          <div class="stat-label"><?= t('students') ?></div>
          <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        </div>
        <div class="stat-value"><?= $totalStudents ?></div>
        <div class="stat-note"><?= t('student_accs') ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-head">
          <div class="stat-label"><?= t('teachers') ?></div>
          <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
        </div>
        <div class="stat-value"><?= $totalTeachers ?></div>
        <div class="stat-note"><?= t('teacher_accs') ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-head">
          <div class="stat-label"><?= t('classes') ?></div>
          <div class="stat-icon"><i class="fas fa-book-open"></i></div>
        </div>
        <div class="stat-value"><?= $totalClasses ?></div>
        <div class="stat-note"><?= t('total_classes') ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-head">
          <div class="stat-label"><?= t('teacher_earn') ?></div>
          <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
        </div>
        <div class="stat-value" style="font-size:1.8rem;">$<?= number_format($totalEarnings, 0) ?></div>
        <div class="stat-note"><?= t('avail_slots') ?>: <?= $availableSlots ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-head">
          <div class="stat-label"><?= t('today_slots') ?></div>
          <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        </div>
        <div class="stat-value"><?= $todaySlots ?></div>
        <div class="stat-note"><?= t('avail_slots') ?> <?= date("d M") ?></div>
      </div>

    </div>

    <!-- Recent Users + Recent Earnings -->
    <div class="content-grid">

      <div class="panel-card">
        <div class="panel-header">
          <div class="panel-title"><i class="fas fa-users me-2"></i><?= t('recent_users') ?></div>
          <a href="manage_users.php" class="panel-link"><?= t('view_all') ?> &rarr;</a>
        </div>
        <?php if (!empty($recentUsers)): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th><?= t('username') ?></th>
                  <th><?= t('role') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentUsers as $u):
                  $rc = $u['role'] === 'student' ? 'role-badge' : ($u['role'] === 'teacher' ? 'role-badge' : 'role-badge');
                ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:10px;">
                        <div class="user-row-avatar"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                      </div>
                    </td>
                    <td><span class="role-badge <?= $rc ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-box"><i class="fas fa-users fa-lg mb-2 d-block opacity-25"></i>No users yet.</div>
        <?php endif; ?>
      </div>

      <div class="panel-card">
        <div class="panel-header">
          <div class="panel-title"><i class="fas fa-dollar-sign me-2"></i><?= t('recent_earn') ?></div>
          <a href="teacher_earnings.php" class="panel-link"><?= t('view_all') ?> &rarr;</a>
        </div>
        <?php if (!empty($recentEarnings)): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th><?= t('lesson') ?></th>
                  <th><?= t('amount') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentEarnings as $e): ?>
                  <tr>
                    <td><?= htmlspecialchars($e['lesson_title']) ?></td>
                    <td><strong>$<?= number_format((float)$e['amount'], 2) ?></strong></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-box"><i class="fas fa-dollar-sign fa-lg mb-2 d-block opacity-25"></i>No earnings yet.</div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Upcoming Available Slots -->
    <div class="slot-card">
      <div class="panel-header">
        <div class="panel-title"><i class="fas fa-calendar-days me-2"></i><?= t('upcoming_avail') ?></div>
        <a href="available_slots.php" class="panel-link"><?= t('view_all') ?> &rarr;</a>
      </div>
      <?php if (!empty($recentAvailability)): ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th><?= t('teacher') ?></th>
                <th><?= t('date') ?></th>
                <th><?= t('time') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentAvailability as $slot): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <div class="user-row-avatar"><?= strtoupper(substr($slot['teacher_name'], 0, 1)) ?></div>
                      <?= htmlspecialchars($slot['teacher_name']) ?>
                    </div>
                  </td>
                  <td><?= date("D, d M Y", strtotime($slot['available_date'])) ?></td>
                  <td><?= date("h:i A", strtotime($slot['available_time'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-box"><i class="fas fa-calendar-xmark fa-lg mb-2 d-block opacity-25"></i>No available slots.</div>
      <?php endif; ?>
    </div>

  </main>
</div>
<script src="logout-modal.js"></script>
</body>
</html>
