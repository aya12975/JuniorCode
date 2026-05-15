<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

/* ── Month selection ── */
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) $selectedMonth = date('Y-m');
$monthStart  = $selectedMonth . '-01';
$monthEnd    = date('Y-m-t', strtotime($monthStart));
$prevMonth   = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth   = date('Y-m', strtotime($monthStart . ' +1 month'));
$monthLabel  = date('F Y', strtotime($monthStart));

/* ── All-time counts ── */
$totalUsers = 0; $totalStudents = 0; $totalTeachers = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM users"); if ($r) $totalUsers = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='student'"); if ($r) $totalStudents = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='teacher'"); if ($r) $totalTeachers = (int)$r->fetch_assoc()['c'];

/* ── Month-filtered stats ── */
$monthClasses = 0; $monthEarnings = 0.0; $monthSlots = 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM classes WHERE class_date BETWEEN ? AND ?");
if ($stmt) { $stmt->bind_param("ss", $monthStart, $monthEnd); $stmt->execute(); $monthClasses = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close(); }

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM teacher_earnings WHERE lesson_date BETWEEN ? AND ?");
if ($stmt) { $stmt->bind_param("ss", $monthStart, $monthEnd); $stmt->execute(); $monthEarnings = (float)$stmt->get_result()->fetch_assoc()['s']; $stmt->close(); }

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM teacher_availability WHERE available_date BETWEEN ? AND ?");
if ($stmt) { $stmt->bind_param("ss", $monthStart, $monthEnd); $stmt->execute(); $monthSlots = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close(); }

/* ── Month-filtered tables ── */
$recentUsers = []; $recentClasses = []; $recentEarnings = []; $recentAvailability = [];

$r = $conn->query("SELECT id, username, role FROM users ORDER BY id DESC LIMIT 5");
if ($r) while ($row = $r->fetch_assoc()) $recentUsers[] = $row;

$stmt = $conn->prepare("SELECT id, student_name, class_date, class_time, type FROM classes WHERE class_date BETWEEN ? AND ? ORDER BY class_date DESC, class_time DESC LIMIT 10");
if ($stmt) { $stmt->bind_param("ss", $monthStart, $monthEnd); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $recentClasses[] = $row; $stmt->close(); }

$stmt = $conn->prepare("SELECT id, teacher_name, lesson_title, amount, lesson_date FROM teacher_earnings WHERE lesson_date BETWEEN ? AND ? ORDER BY lesson_date DESC LIMIT 10");
if ($stmt) { $stmt->bind_param("ss", $monthStart, $monthEnd); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $recentEarnings[] = $row; $stmt->close(); }

$stmt = $conn->prepare("SELECT id, teacher_name, available_date, available_time FROM teacher_availability WHERE available_date BETWEEN ? AND ? ORDER BY available_date DESC LIMIT 10");
if ($stmt) { $stmt->bind_param("ss", $monthStart, $monthEnd); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $recentAvailability[] = $row; $stmt->close(); }

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #3e5077;
      --primary-dark: #1d4ed8;
      --secondary: #143674;
      --dark: #0f172a;
      --muted: #64748b;
      --soft: #eff6ff;
      --soft-2: #f8fbff;
      --border: #dbeafe;
      --shadow: 0 18px 45px rgba(37, 99, 235, 0.08);
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background:
        radial-gradient(circle at top left, rgba(37, 99, 235, 0.08), transparent 22%),
        radial-gradient(circle at bottom right, rgba(56, 189, 248, 0.08), transparent 22%),
        linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
      color: var(--dark);
    }

    .app-shell {
      min-height: 100vh;
      display: flex;
    }

    .sidebar {
      width: 285px;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: white;
      padding:  0;
      position: sticky;
      top: 0;
      height: 100vh;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow-y: auto;
      display: flex; flex-direction: column;
    }
    .sidebar-bottom { padding: 16px 18px; }

    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow-y: auto; }

    .sidebar-top-area { padding: 0 18px 18px; }
    .brand { display: flex; align-items: center; gap: 12px; padding: 0 4px 22px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }

    .brand-logo-img {
      width: 55px; height: 55px;
      object-fit: contain; flex-shrink: 0;
      background: none; border-radius: 0;
    }

    .brand-title {
      font-weight: 900;
      font-size: 1.1rem;
      line-height: 1.15;
    }

    .brand-sub {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.55);
      letter-spacing: 1px;
      margin-top: 3px;
    }

    .nav-title {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 1.3px;
      color: rgba(255,255,255,0.45);
      margin: 20px 10px 10px;
      font-weight: 700;
    }

    .nav-custom {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .nav-link-custom {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(255,255,255,0.78);
      text-decoration: none;
      padding: 12px 14px;
      border-radius: 14px;
      transition: all 0.25s ease;
      font-weight: 700;
    }

    .nav-link-custom:hover {
      background: rgba(255,255,255,0.08);
      color: white;
    }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      box-shadow: 0 10px 24px rgba(37, 99, 235, 0.28);
    }

    .nav-icon {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .main-content {
      flex: 1;
      padding: 26px;
    }

    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px;
      padding: 18px 20px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 22px;
      box-shadow: var(--shadow);
    }

    .topbar h1 {
      font-size: 1.8rem;
      font-weight: 900;
      margin: 0;
      color: white;
    }

    .topbar p {
      margin: 4px 0 0;
      color: rgba(255,255,255,0.8);
    }

    .admin-badge {
      background: rgba(255,255,255,0.15);
      color: #f6f8fc;
      border-radius: 12px; border: 1px solid rgba(255,255,255,0.2);
      padding: 10px 18px;
      font-weight: 800;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 18px;
      margin-bottom: 24px;
    }

    .stat-card, .panel-card {
      background: white;
      border-radius: 22px;
      padding: 22px;
      box-shadow: var(--shadow);
      border: 1px solid #edf4ff;
    }

    .stat-label {
      color: var(--muted);
      font-weight: 700;
      margin-bottom: 8px;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 900;
    }

    .content-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .panel-title {
      font-size: 1.15rem;
      font-weight: 900;
      margin-bottom: 16px;
    }

    .table {
      margin-bottom: 0;
    }

    .table thead th {
      background: #f8fbff;
      font-weight: 800;
    }

    .month-switcher {
      display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,0.15); border-radius: 14px; padding: 8px 14px;
    }
    .month-switcher a {
      color: #fff; text-decoration: none; font-weight: 900; font-size: 1.1rem;
      width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
      border-radius: 8px; transition: background 0.2s;
    }
    .month-switcher a:hover { background: rgba(255,255,255,0.2); }
    .month-switcher span { font-weight: 800; font-size: 1rem; color: #fff; min-width: 130px; text-align: center; }

    .section-label {
      font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1.2px;
      font-weight: 800; color: var(--muted); margin: 24px 0 12px;
    }

    @media (max-width: 1100px) {
      .stats-grid, .content-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 991px) {
      .app-shell {
        flex-direction: column;
      }

      .sidebar {
        width: 100%;
        height: auto;
        position: relative;
      }
    }
  </style>
<script>(function(){var t=localStorage.getItem("jc-theme");if(t==="dark")document.documentElement.classList.add("dark");})();</script><style>html.dark body{background:#0f172a!important;color:#e2e8f0!important}html.dark .sidebar{background:linear-gradient(180deg,#020817 0%,#0c1226 100%)!important}html.dark .panel-card,html.dark .stat-card{background:#1e293b!important;border-color:#334155!important;color:#e2e8f0}html.dark .stat-label{color:#94a3b8!important}html.dark .stat-value{color:#f1f5f9!important}html.dark .form-control,html.dark .form-select,html.dark textarea{background:#1e293b!important;border-color:#475569!important;color:#e2e8f0!important}html.dark .topbar{background:linear-gradient(135deg,#1e3a6e 0%,#0f2456 100%)!important}html.dark .table thead th{background:#1e293b!important;color:#94a3b8!important;border-color:#334155!important}html.dark .table td{color:#cbd5e1!important;border-color:#334155!important}html.dark .panel-title{color:#f1f5f9!important}</style>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="sidebar-top-area">
      <div class="brand">
        <img src="images/robot2.png.png" class="brand-logo-img" alt="Logo">
        <div>
          <div class="brand-title">JuniorCode</div>
          <div class="brand-sub">Admin Panel</div>
        </div>
      </div>

      <div class="nav-title">Main</div>
      <div class="nav-custom">
        <a href="admin_dashboard.php" class="nav-link-custom <?php echo isActive('admin_dashboard.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-house"></i></span>
          <span>Dashboard</span>
        </a>

        <a href="manage_users.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-users"></i></span>
          <span>Manage Users</span>
        </a>

        <a href="admin_teacher_students.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span>
        </a>
          <a href="admin_enrollments.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span>
          </a>

        <a href="manage_classes.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-book"></i></span>
          <span>Manage Classes</span>
        </a>

        <a href="teacher_earnings.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
          <span>Teacher Earnings</span>
        </a>

        <a href="available_slots.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
          <span>Available Slots</span>
        </a>

        <a href="courses_home.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
          <span>Courses</span>
        </a>

        <a href="reports.php" class="nav-link-custom active">
          <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
          <span>Reports</span>
        </a>
        <a href="admin_certificates.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-award"></i></span>
          <span>Certificates</span>
        </a>

      </div>
      </div>
      <div class="sidebar-bottom">
        <a href="settings.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-gear"></i></span>
          <span>Settings</span>
        </a>
        <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
        <a href="logout.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <main class="main-content">
      <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
      </div>
      <div class="topbar">
        <div>
          <h1>Reports</h1>
          <p>Monthly activity overview.</p>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <div class="month-switcher">
            <a href="?month=<?= htmlspecialchars($prevMonth) ?>"><i class="fas fa-chevron-left"></i></a>
            <span><?= htmlspecialchars($monthLabel) ?></span>
            <a href="?month=<?= htmlspecialchars($nextMonth) ?>"><i class="fas fa-chevron-right"></i></a>
          </div>
          <div class="admin-badge"><i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?></div>
        </div>
      </div>

      <div class="section-label">All-time totals</div>
      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total Users</div>
          <div class="stat-value"><?= $totalUsers ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Students</div>
          <div class="stat-value"><?= $totalStudents ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Teachers</div>
          <div class="stat-value"><?= $totalTeachers ?></div>
        </div>
      </section>

      <div class="section-label">In <?= htmlspecialchars($monthLabel) ?></div>
      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Classes</div>
          <div class="stat-value"><?= $monthClasses ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Earnings</div>
          <div class="stat-value">$<?= number_format($monthEarnings, 2) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Available Slots</div>
          <div class="stat-value"><?= $monthSlots ?></div>
        </div>
      </section>

      <section class="content-grid">
        <div class="panel-card">
          <div class="panel-title">Classes — <?= htmlspecialchars($monthLabel) ?></div>
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Student</th><th>Date</th><th>Time</th><th>Type</th></tr></thead>
              <tbody>
                <?php if (!empty($recentClasses)): ?>
                  <?php foreach ($recentClasses as $class): ?>
                    <tr>
                      <td><?= htmlspecialchars($class["student_name"]) ?></td>
                      <td><?= htmlspecialchars($class["class_date"]) ?></td>
                      <td><?= htmlspecialchars($class["class_time"]) ?></td>
                      <td><?= htmlspecialchars($class["type"]) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">No classes in <?= htmlspecialchars($monthLabel) ?>.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel-card">
          <div class="panel-title">Earnings — <?= htmlspecialchars($monthLabel) ?></div>
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Teacher</th><th>Lesson</th><th>Amount</th><th>Date</th></tr></thead>
              <tbody>
                <?php if (!empty($recentEarnings)): ?>
                  <?php foreach ($recentEarnings as $earning): ?>
                    <tr>
                      <td><?= htmlspecialchars($earning["teacher_name"]) ?></td>
                      <td><?= htmlspecialchars($earning["lesson_title"]) ?></td>
                      <td>$<?= number_format((float)$earning["amount"], 2) ?></td>
                      <td><?= htmlspecialchars($earning["lesson_date"]) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">No earnings in <?= htmlspecialchars($monthLabel) ?>.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel-card">
          <div class="panel-title">Available Slots — <?= htmlspecialchars($monthLabel) ?></div>
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Teacher</th><th>Date</th><th>Time</th></tr></thead>
              <tbody>
                <?php if (!empty($recentAvailability)): ?>
                  <?php foreach ($recentAvailability as $slot): ?>
                    <tr>
                      <td><?= htmlspecialchars($slot["teacher_name"]) ?></td>
                      <td><?= htmlspecialchars($slot["available_date"]) ?></td>
                      <td><?= htmlspecialchars($slot["available_time"]) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="3" class="text-center text-muted py-3">No slots in <?= htmlspecialchars($monthLabel) ?>.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel-card">
          <div class="panel-title">Recent Users (All-time)</div>
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>ID</th><th>Username</th><th>Role</th></tr></thead>
              <tbody>
                <?php if (!empty($recentUsers)): ?>
                  <?php foreach ($recentUsers as $user): ?>
                    <tr>
                      <td><?= htmlspecialchars($user["id"]) ?></td>
                      <td><?= htmlspecialchars($user["username"]) ?></td>
                      <td><?= htmlspecialchars($user["role"]) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="3" class="text-center text-muted py-3">No users found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </main>
  </div>
<script src="logout-modal.js"></script>
</body>
</html>

