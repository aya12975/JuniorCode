<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";
$today       = date("Y-m-d");

/* ── Ensure tables exist ── */
$conn->query("CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT DEFAULT NULL,
    teacher_name VARCHAR(255) NOT NULL DEFAULT '', student_name VARCHAR(255) NOT NULL DEFAULT '',
    class_date DATE DEFAULT NULL, class_time TIME DEFAULT NULL,
    type VARCHAR(100) NOT NULL DEFAULT '', details TEXT NOT NULL DEFAULT '',
    zoom_link TEXT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Stats ── */
$totalClasses = 0; $upcomingClasses = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS total, SUM(class_date >= CURDATE()) AS upcoming FROM classes WHERE student_name = ?");
if ($stmt) {
    $stmt->bind_param("s", $studentName);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $totalClasses    = (int)($stats["total"]    ?? 0);
    $upcomingClasses = (int)($stats["upcoming"] ?? 0);
}

/* ── Today's classes ── */
$todaysClasses = [];
$stmt2 = $conn->prepare("SELECT teacher_name, class_date, class_time, type, details, zoom_link FROM classes WHERE student_name = ? AND class_date = ? ORDER BY class_time ASC");
if ($stmt2) {
    $stmt2->bind_param("ss", $studentName, $today);
    $stmt2->execute();
    $todaysClasses = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();
}

/* ── Assignments for this student ── */
$assignments = [];
$connCheck = $conn->query("SHOW TABLES LIKE 'assignments'");
if ($connCheck && $connCheck->num_rows > 0) {
    $stmtA = $conn->prepare("SELECT * FROM assignments WHERE student_name = ? ORDER BY created_at DESC");
    if ($stmtA) {
        $stmtA->bind_param("s", $studentName);
        $stmtA->execute();
        $assignments = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtA->close();
    }
}

/* ── Certificates ── */
$certificates = [];
if ($conn->query("SHOW TABLES LIKE 'certificates'")->num_rows > 0) {
    $stmtC = $conn->prepare("SELECT * FROM certificates WHERE student_name = ? ORDER BY issued_date DESC LIMIT 6");
    $stmtC->bind_param("s", $studentName);
    $stmtC->execute();
    $certificates = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtC->close();
}

/* ── WhatsApp number from settings ── */
require_once "admin_prefs.php";
$whatsapp = getAdminSetting($conn, 'whatsapp', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Dashboard | JuniorCode</title>
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
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: Arial, Helvetica, sans-serif;
  color: var(--dark);
  background:
    radial-gradient(circle at top left,  rgba(37,99,235,0.08), transparent 22%),
    radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
    linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
}

/* ── Sidebar ── */
.sidebar {
  position: fixed; top: 0; left: 0;
  width: 255px; height: 100vh;
  background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
  display: flex; flex-direction: column;
  z-index: 1000; overflow-y: auto;
  transition: transform 0.3s ease;
}
body.sidebar-collapsed .sidebar { transform: translateX(-255px); }
.sidebar-top { padding: 20px 16px; }
.brand {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 10px 18px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  margin-bottom: 16px;
}
.brand-logo-img { width: 55px; height: 55px; object-fit: contain; border-radius: 0; background: none; padding: 0; flex-shrink: 0; }
.brand-title    { font-size: 1.05rem; font-weight: 900; margin: 0; color: #fff; line-height: 1.2; }
.brand-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.55); margin: 3px 0 0; letter-spacing: 1px; }

.student-box {
  display: flex; align-items: center; gap: 12px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 16px; padding: 14px; margin-bottom: 18px;
}
.student-avatar {
  width: 44px; height: 44px; border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: #fff; font-weight: bold; font-size: 18px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  overflow: hidden;
}
.student-avatar img { width: 100%; height: 100%; object-fit: cover; }
.student-name { font-weight: 800; margin: 0; color: #fff; }
.student-role { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }

.nav-link-custom {
  display: flex; align-items: center; gap: 12px;
  text-decoration: none; color: rgba(255,255,255,0.78);
  padding: 12px 14px; border-radius: 14px; margin: 4px 0;
  font-weight: 700; transition: all 0.22s ease;
}
.nav-link-custom:hover { background: rgba(255,255,255,0.09); color: #fff; }
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
.sidebar-bottom { padding: 16px; }

/* ── Main ── */
.main { margin-left: 255px; padding: 28px; min-height: 100vh; transition: margin-left 0.3s ease; }
body.sidebar-collapsed .main { margin-left: 0; }

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
  box-shadow: 0 12px 28px rgba(37,99,235,0.28);
}
.topbar h1 { font-size: 1.7rem; font-weight: 900; margin: 0; }
.topbar p  { margin: 4px 0 0; opacity: 0.85; font-size: 0.95rem; }
.topbar-date {
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2);
  border-radius: 12px; padding: 10px 18px; font-weight: 700; font-size: 0.9rem;
}

/* ── Stat cards ── */
.stat-grid {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 16px; margin-bottom: 26px;
}
.stat-card {
  background: #fff; border: 1px solid var(--border);
  border-radius: 22px; padding: 22px; text-align: center;
  box-shadow: var(--shadow); position: relative; overflow: hidden;
}
.stat-card::before {
  content: ''; display: block; height: 5px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  position: absolute; top: 0; left: 0; right: 0;
  border-radius: 22px 22px 0 0;
}
.stat-icon {
  width: 48px; height: 48px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; margin: 0 auto 12px;
}
.stat-num   { font-size: 2rem; font-weight: 900; line-height: 1; }
.stat-label { font-size: 0.85rem; color: var(--muted); font-weight: 600; margin-top: 4px; }

/* ── Panel card ── */
.panel-card {
  background: #fff; border: 1px solid var(--border);
  border-radius: 22px; padding: 24px 26px; margin-bottom: 22px;
  box-shadow: var(--shadow); position: relative; overflow: hidden;
  width: 100%;
}
.panel-card::before {
  content: ''; display: block; height: 5px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  position: absolute; top: 0; left: 0; right: 0;
  border-radius: 22px 22px 0 0;
}
.panel-title {
  font-size: 1.05rem; font-weight: 800;
  color: var(--primary); margin-bottom: 18px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--border);
}

/* ── Table ── */
.table thead th {
  background: #f8fbff; font-weight: 800;
  color: var(--dark); border-bottom: 1px solid #e6eefb;
  font-size: 0.88rem;
}
.table td { font-size: 0.9rem; vertical-align: middle; }
.row-today { background: #f0f9ff !important; }

/* ── Type badges ── */
.badge-type {
  display: inline-block; border-radius: 999px;
  padding: 4px 12px; font-size: 0.78rem; font-weight: 700;
}
.t-paid    { background: #dcfce7; color: #166534; }
.t-demo    { background: #fef3c7; color: #92400e; }
.t-other   { background: #e0e7ff; color: #3730a3; }

/* ── Zoom button ── */
.btn-zoom {
  display: inline-flex; align-items: center; gap: 6px;
  background: #2D8CFF; color: #fff; font-weight: 700;
  border-radius: 10px; padding: 7px 14px; font-size: 0.85rem;
  text-decoration: none; white-space: nowrap; transition: all 0.2s;
  border: none; cursor: pointer;
}
.btn-zoom:hover {
  background: #1a6fd4; color: #fff;
  transform: translateY(-1px); box-shadow: 0 6px 16px rgba(45,140,255,0.3);
}
.no-zoom { color: #cbd5e1; font-size: 0.85rem; }
.zoom-locked {
  display: inline-flex; align-items: center; gap: 6px;
  color: #94a3b8; font-size: 0.82rem; font-weight: 600;
  background: #f8fafc; border: 1px solid #e2e8f0;
  border-radius: 10px; padding: 6px 12px; white-space: nowrap;
}

/* ── Today schedule list ── */
.schedule-list { list-style: none; margin: 0; padding: 0; }
.schedule-item {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
  padding: 14px 0; border-bottom: 1px solid #f1f5f9;
}
.schedule-item:last-child { border-bottom: none; }
.sched-left { display: flex; align-items: center; gap: 14px; }
.sched-avatar {
  width: 42px; height: 42px; border-radius: 50%;
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  color: #1d4ed8; font-weight: 900; font-size: 1.1rem;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sched-teacher { font-weight: 800; font-size: 0.97rem; }
.sched-time    { font-size: 0.82rem; color: var(--muted); margin-top: 2px; }

/* ── Empty state ── */
.empty-state {
  text-align: center; padding: 40px 20px;
  color: var(--muted);
}
.empty-state i { font-size: 2.5rem; margin-bottom: 12px; display: block; opacity: 0.4; }
.empty-state p { font-weight: 700; margin: 0; }

/* ── Contact ── */
.contact-row {
  display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
}
.contact-wa-icon {
  width: 56px; height: 56px; border-radius: 16px;
  background: linear-gradient(135deg, #25D366, #128C7E);
  color: #fff; font-size: 1.5rem;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  box-shadow: 0 6px 18px rgba(37,211,102,0.28);
}
.contact-text { flex: 1; }
.contact-text strong { font-size: 1rem; color: var(--dark); }
.contact-text p { margin: 4px 0 14px; color: var(--muted); font-size: 0.9rem; line-height: 1.5; }
.btn-whatsapp {
  display: inline-flex; align-items: center; gap: 8px;
  background: #25D366; color: #fff; font-weight: 800;
  border-radius: 14px; padding: 11px 22px; font-size: 0.92rem;
  text-decoration: none; transition: all 0.2s; border: none;
  box-shadow: 0 6px 16px rgba(37,211,102,0.3);
}
.btn-whatsapp:hover { background: #128C7E; color: #fff; transform: translateY(-1px); }

@media (max-width: 991px) {
  .sidebar { position: static; width: 100%; height: auto; }
  .main    { margin-left: 0; padding: 16px; }
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 575px) {
  .stat-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">STUDENT PANEL</p>
      </div>
    </div>

    <div class="student-box">
      <div class="student-avatar">
        <?php if (!empty($_SESSION["profile_picture"])): ?>
          <img src="uploads/profiles/<?= htmlspecialchars($_SESSION["profile_picture"]) ?>" alt="Profile">
        <?php else: ?>
          <?= strtoupper(substr($studentName, 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <p class="student-name"><?= htmlspecialchars($studentName) ?></p>
        <p class="student-role">Student</p>
      </div>
    </div>

    <a href="student_dashboard.php" class="nav-link-custom active">
      <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
    </a>
    <a href="student_courses.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>My Courses</span>
    </a>
    <a href="student_classes.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span>
    </a>
    <a href="student_assignments.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span>
    </a>
    <a href="student_quizzes.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span></a>
        <a href="student_certificates.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
<a href="student_chat.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span>
    </a>
  </div>

  <div class="sidebar-bottom">
    <a href="student_profile.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span>
    </a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span>
    </a>
  </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main">

  <!-- Hamburger toggle -->
  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <!-- Topbar -->
  <div class="topbar" id="dashboard">
    <div>
      <h1>Hello, <?= htmlspecialchars($studentName) ?></h1>
      <p>Welcome to your learning dashboard</p>
    </div>
    <div class="topbar-date"><?= date("l, d F Y") ?></div>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#f1f5f9;color:var(--primary);"><i class="fas fa-book-open"></i></div>
      <div class="stat-num" style="color:var(--primary);"><?= $totalClasses ?></div>
      <div class="stat-label">Total Classes</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#f1f5f9;color:var(--primary);"><i class="fas fa-calendar-check"></i></div>
      <div class="stat-num" style="color:var(--primary);"><?= $upcomingClasses ?></div>
      <div class="stat-label">Upcoming Classes</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#f1f5f9;color:var(--primary);"><i class="fas fa-star"></i></div>
      <div class="stat-num" style="color:var(--primary);"><?= count($todaysClasses) ?></div>
      <div class="stat-label">Today's Classes</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#f1f5f9;color:var(--primary);"><i class="fas fa-award"></i></div>
      <div class="stat-num" style="color:var(--primary);"><?= count($certificates) ?></div>
      <div class="stat-label">Certificates</div>
    </div>
  </div>

  <!-- Today's Schedule -->
  <div class="panel-card">
    <div class="panel-title"><i class="fas fa-calendar-day me-2"></i>Today's Schedule</div>
    <?php if (!empty($todaysClasses)): ?>
      <ul class="schedule-list">
        <?php foreach ($todaysClasses as $c): ?>
          <li class="schedule-item">
            <div class="sched-left">
              <div class="sched-avatar"><?= strtoupper(substr($c['teacher_name'], 0, 1)) ?></div>
              <div>
                <div class="sched-teacher"><?= htmlspecialchars($c['teacher_name']) ?></div>
                <div class="sched-time">
                  <i class="fas fa-clock me-1"></i><?= date("h:i A", strtotime($c['class_time'])) ?>
                  <?php if (!empty($c['details'])): ?>
                    &nbsp;·&nbsp; <?= htmlspecialchars($c['details']) ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <?php
                $t = strtolower(trim($c['type']));
                $tc = $t === 'paid' ? 't-paid' : (strpos($t,'demo') !== false ? 't-demo' : 't-other');
              ?>
              <span class="badge-type <?= $tc ?>"><?= htmlspecialchars($c['type']) ?></span>
              <?php if (!empty($c['zoom_link'])): ?>
                <span class="zoom-slot"
                  data-url="<?= htmlspecialchars($c['zoom_link']) ?>"
                  data-date="<?= $c['class_date'] ?>"
                  data-time="<?= $c['class_time'] ?>"></span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-mug-hot"></i>
        <p>No classes today — enjoy your day!</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Assignments -->
  <div class="panel-card" id="assignments">
    <div class="panel-title"><i class="fas fa-clipboard-list me-2"></i>My Assignments</div>
    <?php if (empty($assignments)): ?>
      <div class="empty-state">
        <i class="fas fa-clipboard-list"></i>
        <p>No assignments yet.</p>
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:14px;">
        <?php foreach ($assignments as $a): ?>
        <div style="display:flex;align-items:flex-start;gap:14px;background:#f8fbff;border:1px solid #dbeafe;border-radius:16px;padding:16px 18px;">
          <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#3e5077,#143674);color:white;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
            <i class="fas fa-file-pen"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:900;font-size:1rem;color:#0f172a;margin-bottom:3px;"><?= htmlspecialchars($a["title"]) ?></div>
            <div style="font-size:0.82rem;color:#64748b;margin-bottom:2px;"><i class="fas fa-chalkboard-user me-1"></i>From: <strong><?= htmlspecialchars($a["teacher_name"]) ?></strong></div>
            <?php if (!empty($a["description"])): ?>
              <div style="font-size:0.87rem;color:#334155;margin:6px 0;white-space:pre-wrap;"><?= htmlspecialchars($a["description"]) ?></div>
            <?php endif; ?>
            <?php if (!empty($a["due_date"])): ?>
              <div style="font-size:0.82rem;font-weight:700;color:#f97316;margin-top:4px;"><i class="fas fa-clock me-1"></i>Due: <?= date("D, d M Y", strtotime($a["due_date"])) ?></div>
            <?php endif; ?>
            <?php if (!empty($a["file_name"]) || !empty($a["link"])): ?>
              <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
                <?php if (!empty($a["file_name"])): ?>
                  <a href="uploads/assignments/<?= urlencode($a["file_name"]) ?>" download
                     style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#3e5077,#143674);color:white;font-weight:800;border-radius:10px;padding:7px 14px;font-size:0.82rem;text-decoration:none;">
                    <i class="fas fa-paperclip"></i> Download File
                  </a>
                <?php endif; ?>
                <?php if (!empty($a["link"])): ?>
                  <a href="<?= htmlspecialchars($a["link"]) ?>" target="_blank" rel="noopener"
                     style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:white;font-weight:800;border-radius:10px;padding:7px 14px;font-size:0.82rem;text-decoration:none;">
                    <i class="fas fa-link"></i> Open Link
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">Received: <?= date("d M Y", strtotime($a["created_at"])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>


  <!-- Certificates -->
  <div class="panel-card" style="margin-top:22px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--border);">
      <div class="panel-title" style="margin:0;padding:0;border:none;"><i class="fas fa-award me-2" style="color:#f59e0b;"></i>My Certificates</div>
      <a href="student_certificates.php" style="text-decoration:none;color:var(--primary);font-weight:800;font-size:0.85rem;background:#eff6ff;border-radius:999px;padding:5px 14px;">View all</a>
    </div>
    <?php if (empty($certificates)): ?>
      <div class="empty-state">
        <i class="fas fa-award"></i>
        <p>No certificates yet — complete a course to earn one!</p>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;">
        <?php foreach ($certificates as $cert): ?>
          <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:16px;padding:18px;color:#fff;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-14px;right:-14px;width:70px;height:70px;border-radius:50%;background:rgba(245,158,11,0.12);"></div>
            <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#b45309);display:flex;align-items:center;justify-content:center;margin-bottom:12px;">
              <i class="fas fa-medal" style="color:#fff;font-size:1rem;"></i>
            </div>
            <div style="font-weight:800;font-size:0.95rem;margin-bottom:4px;line-height:1.3;"><?= htmlspecialchars($cert['course_name']) ?></div>
            <div style="font-size:0.78rem;color:rgba(255,255,255,0.6);margin-bottom:2px;"><i class="fas fa-chalkboard-user me-1"></i><?= htmlspecialchars($cert['teacher_name']) ?></div>
            <div style="font-size:0.75rem;color:#f59e0b;margin-top:8px;font-weight:700;"><i class="fas fa-calendar me-1"></i><?= date("M j, Y", strtotime($cert['issued_date'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
function renderZoomSlots() {
  document.querySelectorAll('.zoom-slot').forEach(function(slot) {
    var url  = slot.dataset.url;
    if (!url) return;
    var classAt  = new Date(slot.dataset.date + 'T' + slot.dataset.time);
    var now      = new Date();
    var diffMin  = (classAt - now) / 60000;

    if (diffMin <= 10) {
      var a = document.createElement('a');
      a.href = url; a.target = '_blank'; a.rel = 'noopener';
      a.className = 'btn-zoom';
      a.innerHTML = '<i class="fas fa-video"></i> Join Class';
      slot.innerHTML = '';
      slot.appendChild(a);
    } else {
      var h = classAt.getHours().toString().padStart(2,'0');
      var m = classAt.getMinutes().toString().padStart(2,'0');
      slot.innerHTML = '<span class="zoom-locked"><i class="fas fa-lock"></i> Opens at ' + h + ':' + m + '</span>';
      if (!slot.dataset.timerSet) {
        slot.dataset.timerSet = '1';
        var delay = (classAt - now) - 10 * 60000;
        if (delay > 0) setTimeout(renderZoomSlots, delay);
      }
    }
  });
}
renderZoomSlots();
setInterval(renderZoomSlots, 30000);
</script>
<script src="logout-modal.js"></script>
</body>
</html>


