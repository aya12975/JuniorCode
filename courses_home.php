<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";

$kidsCount   = (int)(($conn->query("SELECT COUNT(*) FROM courses WHERE section='kids'")  ->fetch_row()[0]) ?? 0);
$juniorCount = (int)(($conn->query("SELECT COUNT(*) FROM courses WHERE section='junior'")->fetch_row()[0]) ?? 0);
$demoCount   = (int)(($conn->query("SELECT COUNT(*) FROM courses WHERE section='demo'")  ->fetch_row()[0]) ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Courses | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --shadow:0 18px 45px rgba(37,99,235,0.08); }
    * { box-sizing:border-box; }
    body {
      margin:0; font-family:Arial,Helvetica,sans-serif;
      background: radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),
                  radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),
                  linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%);
      color:var(--dark);
    }
    .app-shell { min-height:100vh; display:flex; }

    /* ── Sidebar ── */
    .sidebar { width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:white; padding:0; position:sticky; top:0; height:100vh; overflow-y:auto; display:flex; flex-direction:column; transition:width .3s,padding .3s; }
    body.sidebar-collapsed .sidebar { width:0; min-width:0; overflow:hidden; }
    .sidebar-top-area { padding:0 18px 18px; }
    .brand { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
    .brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
    .brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
    .brand-sub { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
    .nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
    .nav-custom { display:flex; flex-direction:column; gap:4px; }
    .nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all .25s; font-weight:700; }
    .nav-link-custom:hover { background:rgba(255,255,255,0.08); color:white; }
    .nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; box-shadow:0 10px 24px rgba(37,99,235,0.28); }
    .nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .sidebar-bottom { padding:16px 18px; margin-top:auto; }

    /* ── Main ── */
    .main-content { flex:1; padding:32px; display:flex; flex-direction:column; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:24px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:48px; padding:20px 24px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:24px; box-shadow:var(--shadow); }
    .topbar h1 { font-size:1.9rem; font-weight:900; margin:0; color:white; }
    .topbar p { margin:5px 0 0; color:rgba(255,255,255,0.75); font-size:0.95rem; }
    .admin-badge { background:rgba(255,255,255,0.15); color:#f6f8fc; border-radius:12px; border:1px solid rgba(255,255,255,0.2); padding:10px 18px; font-weight:800; white-space:nowrap; }

    /* ── 3 Buttons ── */
    .courses-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
      max-width: 1000px;
      margin: 0 auto;
      width: 100%;
    }

    .course-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 20px;
      padding: 52px 32px;
      border-radius: 30px;
      text-decoration: none;
      transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .22s;
      position: relative;
      overflow: hidden;
    }
    .course-card:hover { transform: translateY(-8px); text-decoration: none; }
    .course-card::before {
      content: '';
      position: absolute;
      top: -40%; left: -30%;
      width: 70%; height: 70%;
      background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 65%);
      pointer-events: none;
    }

    .card-kids   { background:linear-gradient(145deg,#3e5077,#143674); box-shadow:0 20px 48px rgba(62,80,119,0.38); }
    .card-kids:hover   { box-shadow:0 28px 60px rgba(62,80,119,0.52); }
    .card-junior { background:linear-gradient(145deg,#7c3aed,#5b21b6); box-shadow:0 20px 48px rgba(124,58,237,0.38); }
    .card-junior:hover { box-shadow:0 28px 60px rgba(124,58,237,0.52); }
    .card-demo   { background:linear-gradient(145deg,#16a34a,#15803d); box-shadow:0 20px 48px rgba(22,163,74,0.38); }
    .card-demo:hover   { box-shadow:0 28px 60px rgba(22,163,74,0.52); }

    .card-icon {
      width: 84px; height: 84px; border-radius: 26px;
      background: rgba(255,255,255,0.22);
      display: flex; align-items: center; justify-content: center;
      font-size: 2.4rem; color: white;
    }
    .card-label { font-size: 1.5rem; font-weight: 900; color: white; letter-spacing: -0.3px; }
    .card-count { font-size: 3rem; font-weight: 900; color: white; line-height: 1; }
    .card-sub   { font-size: 0.9rem; font-weight: 600; color: rgba(255,255,255,0.72); }

    @media (max-width:900px) {
      .courses-grid { grid-template-columns:1fr; max-width:380px; }
      .app-shell { flex-direction:column; }
      .sidebar { width:100%; height:auto; position:relative; }
    }
  </style>
</head>
<body>
<div class="app-shell">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-top-area">
      <div class="brand">
        <img src="images/robot2.png.png" class="brand-logo-img" alt="Logo">
        <div><div class="brand-title">JuniorCode</div><div class="brand-sub">Admin Panel</div></div>
      </div>
      <div class="nav-title">Main</div>
      <div class="nav-custom">
        <a href="admin_dashboard.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
        <a href="manage_users.php"           class="nav-link-custom"><span class="nav-icon"><i class="fas fa-users"></i></span><span>Manage Users</span></a>
        <a href="admin_teacher_students.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span></a>
        <a href="admin_enrollments.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span></a>
        <a href="manage_classes.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
        <a href="teacher_earnings.php"       class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
        <a href="available_slots.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
        <a href="courses_home.php"           class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
        <a href="reports.php"               class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
        <a href="admin_certificates.php"    class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
      </div>
    </div>
    <div class="sidebar-bottom">
      <a href="settings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
      <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
      <a href="logout.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
    </div>
  </aside>

  <!-- Main -->
  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
    </div>

    <div class="topbar">
      <div>
        <h1><i class="fas fa-graduation-cap me-2"></i>Courses</h1>
        <p>Select a section to manage its courses.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <div class="courses-grid">

      <a href="courses_kids.php" class="course-card card-kids">
        <div class="card-icon"><i class="fas fa-child"></i></div>
        <div class="card-label">Kids</div>
        <div class="card-count"><?= $kidsCount ?></div>
        <div class="card-sub">courses &nbsp;·&nbsp; Ages 6 – 11</div>
      </a>

      <a href="courses_junior.php" class="course-card card-junior">
        <div class="card-icon"><i class="fas fa-code"></i></div>
        <div class="card-label">Junior</div>
        <div class="card-count"><?= $juniorCount ?></div>
        <div class="card-sub">courses &nbsp;·&nbsp; Ages 12+</div>
      </a>

      <a href="courses_demo.php" class="course-card card-demo">
        <div class="card-icon"><i class="fas fa-play-circle"></i></div>
        <div class="card-label">Demo</div>
        <div class="card-count"><?= $demoCount ?></div>
        <div class="card-sub">free trial sessions</div>
      </a>

    </div>
  </main>
</div>
<script src="logout-modal.js"></script>
</body>
</html>
