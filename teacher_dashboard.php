<?php
session_start();
require_once "db.php";
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherId   = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "Teacher";

/* ── Ensure tables exist ── */
$conn->query("CREATE TABLE IF NOT EXISTS teacher_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT DEFAULT NULL,
    teacher_name VARCHAR(255) NOT NULL DEFAULT '', lesson_title VARCHAR(255) NOT NULL DEFAULT '',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0, lesson_date DATE DEFAULT NULL,
    notes TEXT NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT DEFAULT NULL,
    teacher_name VARCHAR(255) NOT NULL DEFAULT '', student_name VARCHAR(255) NOT NULL DEFAULT '',
    class_date DATE DEFAULT NULL, class_time TIME DEFAULT NULL,
    type VARCHAR(100) NOT NULL DEFAULT '', details TEXT NOT NULL DEFAULT '',
    zoom_link TEXT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS teacher_availability (
    id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT NOT NULL DEFAULT 0,
    teacher_name VARCHAR(255) NOT NULL DEFAULT '', available_date DATE NOT NULL,
    available_time TIME NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* =========================
   Earnings summary
========================= */
$totalEarnings = 0;
$totalPaidSessions = 0;
$totalLessons = 0;
$latestLessonDate = "-";

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_lessons,
        COALESCE(SUM(amount), 0) AS total_earnings
    FROM teacher_earnings
    WHERE teacher_id = ?
       OR (teacher_id IS NULL AND LOWER(teacher_name) = LOWER(?))
");

if (!$stmt) { $stmt = null; }

$stmt->bind_param("is", $teacherId, $teacherName);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $totalLessons = (int)$row["total_lessons"];
    $totalEarnings = (float)$row["total_earnings"];
    $totalPaidSessions = $totalLessons;
}

$stmt->close();

/* =========================
   Ensure teacher_id column exists
========================= */
$colCheck = $conn->query("SHOW COLUMNS FROM classes LIKE 'teacher_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN teacher_id INT DEFAULT NULL");
}

/* =========================
   All classes for this teacher
========================= */
$classSessions = [];
$stmt2 = $conn->prepare("
    SELECT * FROM classes
    WHERE teacher_id = ? OR LOWER(teacher_name) = LOWER(?)
    ORDER BY class_date ASC, class_time ASC
");
if ($stmt2) {
    $stmt2->bind_param("is", $teacherId, $teacherName);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $classSessions[] = $row;
    }
    $stmt2->close();
}

/* Yesterday / today / tomorrow — used for My Classes & My Schedule views */
$yesterday3 = date("Y-m-d", strtotime("-1 day"));
$tomorrow3  = date("Y-m-d", strtotime("+1 day"));
$upcomingClasses = array_values(array_filter($classSessions, function($c) use ($yesterday3, $tomorrow3) {
    $d = $c["class_date"] ?? "";
    return $d >= $yesterday3 && $d <= $tomorrow3;
}));
$dashYesterdayCount = 0; $dashTodayCount = 0; $dashTomorrowCount = 0;
foreach ($upcomingClasses as $c) {
    $d = $c["class_date"] ?? "";
    if ($d === $yesterday3) $dashYesterdayCount++;
    elseif ($d === date("Y-m-d")) $dashTodayCount++;
    elseif ($d === $tomorrow3)  $dashTomorrowCount++;
}

/* =========================
   Stats from real classes
========================= */
$fullPay = 0;
$halfPay = 0;
$noPay = 0;
$demoEnrolled = 0;
$demoPending = 0;
$demoOther = 0;

foreach ($classSessions as $c) {
    $t = strtolower(trim($c["type"] ?? ""));
    if ($t === "paid")                    $fullPay++;
    elseif ($t === "half pay")            $halfPay++;
    elseif ($t === "no pay")              $noPay++;
    elseif ($t === "demo enrolled")       $demoEnrolled++;
    elseif ($t === "demo pending")        $demoPending++;
    elseif (strpos($t, "demo") !== false) $demoOther++;
}

$totalDemos = $demoEnrolled + $demoPending + $demoOther;
$conversionRate = $totalDemos > 0 ? round(($demoEnrolled / $totalDemos) * 100) : 0;

/* =========================
   Today's classes
========================= */
$today = date("Y-m-d");
$todaysClasses = array_values(array_filter($classSessions, function($c) use ($today) {
    return ($c["class_date"] ?? "") === $today;
}));

/* =========================
   Unique students
========================= */
$students = array_values(array_unique(array_filter(array_column($classSessions, "student_name"))));

/* =========================
   Earnings table
========================= */
$earnings = [];

$stmt5 = $conn->prepare("
    SELECT id, amount, lesson_title, lesson_date, notes
    FROM teacher_earnings
    WHERE teacher_id = ?
       OR (teacher_id IS NULL AND LOWER(teacher_name) = LOWER(?))
    ORDER BY id DESC
");

if (!$stmt5) { $stmt5 = null; }

$stmt5->bind_param("is", $teacherId, $teacherName);
$stmt5->execute();
$result5 = $stmt5->get_result();

if ($result5) {
    while ($row = $result5->fetch_assoc()) {
        $earnings[] = [
            "id"           => $row["id"],
            "lesson_title" => $row["lesson_title"] ?: "Session #" . $row["id"],
            "amount"       => $row["amount"],
            "lesson_date"  => $row["lesson_date"] ?: "-",
            "notes"        => $row["notes"] ?: "-"
        ];
    }
}

$stmt5->close();

/* students list is built above from $classSessions */

/* =========================
   Optional availability count
========================= */
$totalAvailabilitySlots = 0;

$stmt6 = $conn->prepare("
    SELECT COUNT(*) AS total_slots
    FROM teacher_availability
    WHERE teacher_id = ?
");

if ($stmt6) {
    $stmt6->bind_param("i", $teacherId);
    $stmt6->execute();
    $result6 = $stmt6->get_result();

    if ($result6 && $row = $result6->fetch_assoc()) {
        $totalAvailabilitySlots = (int)$row["total_slots"];
    }

    $stmt6->close();
}

$currentMonth = date("F");
$currentYear = date("Y");



?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Teacher Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:      #3e5077;
      --primary-dark: #152c6b;
      --secondary:    #143674;
      --accent:       #38bdf8;
      --dark:         #0f172a;
      --muted:        #64748b;
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      color: var(--dark);
      background:
        radial-gradient(circle at top left,  rgba(29,78,216,0.07), transparent 25%),
        radial-gradient(circle at bottom right, rgba(14,165,233,0.07), transparent 25%),
        linear-gradient(180deg, #f8fbff 0%, #eaf4ff 100%);
    }

    .app-shell { min-height: 100vh; display: flex; }

    /* ── Sidebar ── */
    .sidebar {
      width: 285px; flex-shrink: 0;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: #fff;
      padding: 0;
      position: sticky; top: 0;
      height: 100vh;
      display: flex; flex-direction: column;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease;
      overflow-y: auto;
    }
    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; }

    .sidebar-top-area { padding: 0 18px 18px; }

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

    .brand-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.55); letter-spacing: 1px; margin-top: 3px; }

    .nav-title {
      font-size: 0.78rem; text-transform: uppercase;
      letter-spacing: 1.3px; color: rgba(255,255,255,0.45);
      margin: 20px 10px 10px; font-weight: 700;
    }

    .nav-custom { display: flex; flex-direction: column; gap: 4px; }

    .teacher-box {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 16px;
      padding: 14px;
      margin-bottom: 18px;
    }

    .teacher-avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
      overflow: hidden;
    }
    .teacher-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .teacher-name  { font-weight: 800; margin: 0; color: #ffffff; }
    .teacher-role  { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }

    .nav-link-custom {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: rgba(255,255,255,0.78);
      padding: 12px 14px;
      border-radius: 14px;
      margin: 4px 0;
      font-weight: 700;
      transition: all 0.22s ease;
    }

    .nav-link-custom:hover {
      background: rgba(255,255,255,0.09);
      color: #ffffff;
    }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #ffffff;
      box-shadow: 0 8px 20px rgba(30,50,100,0.35);
    }

    .nav-icon {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      flex-shrink: 0;
    }

    .nav-link-custom.active .nav-icon {
      background: rgba(255,255,255,0.18);
    }

    .sidebar-bottom { padding: 16px 18px; }

    /* ── Main content ── */
    .main {
      flex: 1;
      padding: 26px;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── Hamburger ── */
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .hero {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      border-radius: 22px;
      padding: 18px 20px;
      margin-bottom: 22px;
      box-shadow: 0 12px 28px rgba(37, 99, 235, 0.3);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }


    .hero h2 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 900;
    }

    .hero p {
      margin: 4px 0 0;
      color: rgba(255,255,255,0.8);
    }

    .month-box {
      background: rgba(255,255,255,0.15);
      color: white;
      border-radius: 999px;
      padding: 10px 16px;
      font-weight: 800;
      white-space: nowrap;
    }

    .panel-card {
      background: white;
      border: 1px solid #edf4ff;
      border-radius: 22px;
      padding: 22px;
      margin-bottom: 22px;
      box-shadow: 0 18px 45px rgba(37, 99, 235, 0.08);
      position: relative;
      overflow: hidden;
    }

    .panel-card::before {
      content: '';
      display: block;
      height: 5px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      position: absolute;
      top: 0; left: 0; right: 0;
      border-radius: 22px 22px 0 0;
    }

    .panel-title {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 14px;
    }

    .soft-stat {
      border-radius: 16px;
      padding: 18px;
      text-align: center;
      font-weight: bold;
    }

    .soft-green { background: #ecfeff; color: #0f766e; }
    .soft-orange { background: #fff7ed; color: #c2410c; }
    .soft-red { background: #fef2f2; color: #b91c1c; }
    .soft-blue { background: #eff6ff; color: var(--primary); }
    .soft-purple { background: #f5f3ff; color: #6d28d9; }

    .big-stat {
      font-size: 1.8rem;
      font-weight: bold;
      margin-top: 8px;
    }

    .table thead th {
      background: #f8fbff;
      color: var(--dark);
      font-weight: 800;
      font-size: 0.9rem;
      border-bottom: 1px solid #e6eefb;
    }

    .badge-paid {
      background: #dcfce7;
      color: #166534;
      border-radius: 999px;
      padding: 6px 12px;
      font-size: 0.8rem;
      font-weight: bold;
    }

    .badge-demo {
      background: #fef3c7;
      color: #92400e;
      border-radius: 999px;
      padding: 6px 12px;
      font-size: 0.8rem;
      font-weight: bold;
    }

    .money-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 999px;
      background: #ecfdf5;
      color: #065f46;
      font-weight: bold;
      font-size: 0.85rem;
    }

    .empty-box {
      text-align: center;
      color: #94a3b8;
      padding: 30px 10px;
    }

    .btn-zoom {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #2D8CFF;
      color: white;
      font-weight: 700;
      border-radius: 10px;
      padding: 6px 12px;
      font-size: 0.82rem;
      text-decoration: none;
      white-space: nowrap;
      transition: all 0.2s ease;
      border: none; cursor: pointer;
    }

    .btn-zoom:hover {
      background: #1a6fd4;
      color: white;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(45,140,255,0.3);
    }

    .zoom-locked {
      display: inline-flex; align-items: center; gap: 6px;
      color: #94a3b8; font-size: 0.82rem; font-weight: 600;
      background: #f8fafc; border: 1px solid #e2e8f0;
      border-radius: 10px; padding: 6px 12px; white-space: nowrap;
    }
    .zoom-none {
      color: #cbd5e1;
      font-size: 0.85rem;
    }

    .filter-btn-dash {
      border: 2px solid #e2e8f0; background: #fff;
      border-radius: 999px; padding: 5px 14px;
      font-weight: 700; font-size: 0.82rem; color: #64748b; cursor: pointer;
      transition: all 0.2s;
    }
    .filter-btn-dash:hover  { border-color: #3e5077; color: #3e5077; }
    .filter-btn-dash.active { background: #3e5077; border-color: #3e5077; color: #fff; }

    .badge-type {
      border-radius: 999px;
      padding: 5px 11px;
      font-size: 0.78rem;
      font-weight: 700;
    }

    .row-today { background: #f0f9ff !important; }

    .mini-list li {
      padding: 10px 0;
      border-bottom: 1px solid #eef2f7;
    }

    .mini-list li:last-child {
      border-bottom: none;
    }

    .info-note {
      background: #eff6ff;
      color: #1e40af;
      border: 1px solid #bfdbfe;
      border-radius: 12px;
      padding: 12px 14px;
      margin-bottom: 16px;
      font-size: 0.92rem;
    }

    .crs-tab-btn {
      padding: 11px 26px; border-radius: 14px;
      border: 2px solid #e2e8f0; font-weight: 800; font-size: 0.95rem;
      cursor: pointer; transition: all 0.2s;
      background: white; color: var(--muted);
    }
    .crs-tab-btn:hover { border-color: var(--primary); color: var(--primary); }
    .crs-tab-btn.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white; border-color: transparent;
      box-shadow: 0 6px 18px rgba(62,80,119,0.25);
    }

    .crs-drop-item {
      display: block; width: 100%; padding: 12px 18px;
      border: none; background: none; text-align: left;
      font-weight: 700; color: #0f172a; cursor: pointer;
      border-bottom: 1px solid #f1f5f9; transition: background 0.15s;
    }
    .crs-drop-item:last-child { border-bottom: none; }
    .crs-drop-item:hover  { background: #f0f7ff; color: var(--primary); }
    .crs-drop-item.active { background: #eff6ff; color: var(--primary); font-weight: 900; }

    .grade-link {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 0.85rem; font-weight: 700; color: #2563eb;
      text-decoration: none; background: #dbeafe;
      padding: 7px 13px; border-radius: 9px; transition: background 0.2s;
    }
    .grade-link:hover { background: #bfdbfe; color: #1d4ed8; }

    .t-proj-item { display:flex; align-items:center; gap:12px; background:#f0f7ff; border:1px solid #bfdbfe; border-radius:14px; padding:13px 16px; margin-bottom:10px; }
    .t-proj-icon { flex-shrink:0; display:flex; align-items:center; }
    .t-proj-icon img { max-height:100px; max-width:160px; width:auto; height:auto; border-radius:10px; display:block; }
    .t-proj-icon-fb { width:44px; height:44px; border-radius:10px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .t-proj-actions { display:flex; flex-direction:column; gap:6px; flex-shrink:0; }
    .t-proj-btn { display:flex; align-items:center; justify-content:center; gap:6px; font-size:0.8rem; font-weight:800; text-decoration:none; padding:8px 14px; border-radius:9px; white-space:nowrap; color:white; transition:filter 0.2s; }
    .t-proj-btn:hover { color:white; filter:brightness(1.1); }
    .t-proj-pdf  { background:linear-gradient(135deg,#f97316,#ea580c); box-shadow:0 4px 10px rgba(249,115,22,0.25); }
    .t-proj-link { background:linear-gradient(135deg,#3b82f6,#1d4ed8); box-shadow:0 4px 10px rgba(59,130,246,0.25); }

    html { scroll-behavior: smooth; }

    section[id] { scroll-margin-top: 20px; }

    @media (max-width: 991px) {
      .app-shell { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: relative; }
      .main { padding: 18px; }
    }
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
        <div class="brand-subtitle">TEACHER PORTAL</div>
      </div>
    </div>

    <div class="teacher-box">
      <div class="teacher-avatar">
        <?php if (!empty($_SESSION["profile_picture"])): ?>
          <img src="uploads/profiles/<?= htmlspecialchars($_SESSION["profile_picture"]) ?>" alt="Profile">
        <?php else: ?>
          <?= strtoupper(substr($teacherName, 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <p class="teacher-name"><?php echo htmlspecialchars($teacherName); ?></p>
        <p class="teacher-role">Teacher</p>
      </div>
    </div>

    <div class="nav-title">MAIN</div>
    <div class="nav-custom">
      <a href="teacher_dashboard.php" class="nav-link-custom active">
        <span class="nav-icon"><i class="fas fa-house"></i></span>
        <span>Dashboard</span>
      </a>

      <a href="teacher_classes.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span>
        <span>My Classes</span>
      </a>

      <a href="teacher_schedule.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
        <span>My Schedule</span>
      </a>

      <a href="teacher_monthly_earnings.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
        <span>My Earnings</span>
      </a>

      <a href="teacher_students.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-user-graduate"></i></span>
        <span>My Students</span>
      </a>

      <a href="teacher_assignments.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span>
        <span>Assignments</span>
      </a>

      <a href="teacher_courses.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
        <span>Courses</span>
      </a>

      <a href="teacher_quizzes.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-circle-question"></i></span>
        <span>Quizzes</span>
      </a>
    </div>
  </div>

  <div class="sidebar-bottom">
    <a href="teacher_profile.php" class="nav-link-custom">
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

<div class="main">

  <!-- Hamburger toggle -->
  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <!-- ══ VIEW: DASHBOARD ══ -->
  <div class="view" id="view-dashboard">
    <section class="hero">
      <div>
        <h2>Hello, <?php echo htmlspecialchars($teacherName); ?></h2>
        <p>Teaching Dashboard</p>
      </div>
      <div class="month-box">Viewing: <?php echo $currentMonth . " " . $currentYear; ?></div>
    </section>

    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <div class="panel-card">
          <div class="panel-title">Paid Classes</div>
          <div class="row g-3">
            <div class="col-4"><div class="soft-stat soft-green"><div><i class="fas fa-circle-check me-1"></i>Full Pay</div><div class="big-stat"><?php echo $fullPay; ?></div></div></div>
            <div class="col-4"><div class="soft-stat soft-orange"><div><i class="fas fa-circle-half-stroke me-1"></i>Half Pay</div><div class="big-stat"><?php echo $halfPay; ?></div></div></div>
            <div class="col-4"><div class="soft-stat soft-red"><div><i class="fas fa-circle-xmark me-1"></i>No Pay</div><div class="big-stat"><?php echo $noPay; ?></div></div></div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="panel-card">
          <div class="panel-title">Demo Classes</div>
          <div class="row g-3">
            <div class="col-4"><div class="soft-stat soft-green"><div><i class="fas fa-user-check me-1"></i>Enrolled</div><div class="big-stat"><?php echo $demoEnrolled; ?></div></div></div>
            <div class="col-4"><div class="soft-stat soft-blue"><div><i class="fas fa-clock me-1"></i>Pending</div><div class="big-stat"><?php echo $demoPending; ?></div></div></div>
            <div class="col-4"><div class="soft-stat soft-orange"><div><i class="fas fa-circle-question me-1"></i>Other</div><div class="big-stat"><?php echo $demoOther; ?></div></div></div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="panel-card">
          <div class="panel-title">Conversion Rate</div>
          <div class="big-stat text-primary"><?php echo $conversionRate; ?>%</div>
          <div class="text-muted"><i class="fas fa-arrow-trend-up me-1"></i>Success Rate</div>
          <div class="mt-2 text-muted"><i class="fas fa-flask me-1"></i><?php echo $demoEnrolled; ?> / <?php echo $totalDemos; ?> demos</div>
        </div>
      </div>
    </div>

    <div class="panel-card">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="panel-title mb-0">Today's Schedule</div>
        <span class="text-muted" style="font-size:0.9rem"><?php echo date("l, d F Y"); ?></span>
      </div>
      <?php if (!empty($todaysClasses)): ?>
        <ul class="mini-list list-unstyled mb-0">
          <?php foreach ($todaysClasses as $class): ?>
            <li class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <strong><?php echo htmlspecialchars($class["student_name"]); ?></strong>
                <span class="text-muted ms-2"><?php echo date("h:i A", strtotime($class["class_time"])); ?></span>
                <span class="ms-2" style="font-size:0.82rem;color:#64748b"><?php echo htmlspecialchars($class["type"]); ?></span>
              </div>
              <?php if (!empty($class["zoom_link"])): ?>
                <span class="zoom-slot"
                  data-url="<?= htmlspecialchars($class['zoom_link']) ?>"
                  data-date="<?= $class['class_date'] ?>"
                  data-time="<?= $class['class_time'] ?>"></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-box">No classes scheduled for today.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ VIEW: MY CLASSES ══ -->
  <div class="view" id="view-classes" style="display:none">
    <section class="hero">
      <div>
        <h2>My Classes</h2>
        <p>Yesterday, today and tomorrow's sessions</p>
      </div>
      <div class="month-box"><?php echo $dashTodayCount; ?> today</div>
    </section>
    <div class="panel-card">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="panel-title mb-0">My Classes</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <button class="filter-btn-dash" onclick="filterDashTable('classes','yesterday',this)">Yesterday (<?= $dashYesterdayCount ?>)</button>
          <button class="filter-btn-dash active" onclick="filterDashTable('classes','today',this)">Today (<?= $dashTodayCount ?>)</button>
          <button class="filter-btn-dash" onclick="filterDashTable('classes','tomorrow',this)">Tomorrow (<?= $dashTomorrowCount ?>)</button>
        </div>
      </div>
      <?php if (!empty($upcomingClasses)): ?>
        <div class="table-responsive">
          <table class="table align-middle" id="dash-classes-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Date</th>
                <th>Time</th>
                <th>Type</th>
                <th>Details</th>
                <th>Zoom</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($upcomingClasses as $class):
                $classWhen = ($class["class_date"] ?? "") === $yesterday3 ? "yesterday"
                           : (($class["class_date"] ?? "") === date("Y-m-d") ? "today" : "tomorrow");
                $t = strtolower(trim($class["type"] ?? ""));
                if ($t === "paid")                   $badgeClass = "badge-paid";
                elseif (strpos($t,"demo") !== false) $badgeClass = "badge-demo";
                else                                 $badgeClass = "badge-other";
              ?>
                <tr data-when="<?= $classWhen ?>">
                  <td>
                    <strong><?php echo htmlspecialchars($class["student_name"]); ?></strong>
                    <?php if ($classWhen === "today"): ?><span class="badge bg-success ms-1" style="font-size:0.7rem">Today</span><?php endif; ?>
                  </td>
                  <td><?php echo date("d M Y", strtotime($class["class_date"])); ?></td>
                  <td><?php echo date("h:i A", strtotime($class["class_time"])); ?></td>
                  <td><span class="badge-type <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($class["type"]); ?></span></td>
                  <td><?php echo htmlspecialchars($class["details"] ?? "—"); ?></td>
                  <td>
                    <?php if (!empty($class["zoom_link"])): ?>
                      <span class="zoom-slot"
                        data-url="<?= htmlspecialchars($class['zoom_link']) ?>"
                        data-date="<?= $class['class_date'] ?>"
                        data-time="<?= $class['class_time'] ?>"></span>
                    <?php else: ?>
                      <span class="zoom-none">— No link</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="dash-classes-empty" class="empty-box" style="display:none">No classes for this day.</div>
      <?php else: ?>
        <div class="empty-box">
          <div style="font-size:2rem;margin-bottom:8px"><i class="fas fa-book-open" style="color:#94a3b8"></i></div>
          No classes in this period.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ VIEW: MY SCHEDULE ══ -->
  <div class="view" id="view-schedule" style="display:none">
    <section class="hero">
      <div>
        <h2>My Schedule</h2>
        <p>Yesterday, today and tomorrow's sessions</p>
      </div>
      <div class="month-box"><?php echo $dashTodayCount; ?> today</div>
    </section>
    <div class="panel-card">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="panel-title mb-0">My Schedule</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <button class="filter-btn-dash" onclick="filterDashTable('schedule','yesterday',this)">Yesterday (<?= $dashYesterdayCount ?>)</button>
          <button class="filter-btn-dash active" onclick="filterDashTable('schedule','today',this)">Today (<?= $dashTodayCount ?>)</button>
          <button class="filter-btn-dash" onclick="filterDashTable('schedule','tomorrow',this)">Tomorrow (<?= $dashTomorrowCount ?>)</button>
        </div>
      </div>
      <?php if (!empty($upcomingClasses)): ?>
        <div class="table-responsive">
          <table class="table align-middle" id="dash-schedule-table">
            <thead>
              <tr><th>Student</th><th>Date</th><th>Time</th><th>Type</th><th>Zoom</th></tr>
            </thead>
            <tbody>
              <?php foreach ($upcomingClasses as $class):
                $classWhen = ($class["class_date"] ?? "") === $yesterday3 ? "yesterday"
                           : (($class["class_date"] ?? "") === date("Y-m-d") ? "today" : "tomorrow");
              ?>
                <tr data-when="<?= $classWhen ?>">
                  <td><strong><?php echo htmlspecialchars($class["student_name"]); ?></strong>
                    <?php if ($classWhen === "today"): ?><span class="badge bg-success ms-1" style="font-size:0.7rem">Today</span><?php endif; ?>
                  </td>
                  <td><?php echo date("d M Y", strtotime($class["class_date"])); ?></td>
                  <td><?php echo date("h:i A", strtotime($class["class_time"])); ?></td>
                  <td><?php echo htmlspecialchars($class["type"]); ?></td>
                  <td>
                    <?php if (!empty($class["zoom_link"])): ?>
                      <span class="zoom-slot"
                        data-url="<?= htmlspecialchars($class['zoom_link']) ?>"
                        data-date="<?= $class['class_date'] ?>"
                        data-time="<?= $class['class_time'] ?>"></span>
                    <?php else: ?>
                      <span class="zoom-none">— No link</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="dash-schedule-empty" class="empty-box" style="display:none">No classes for this day.</div>
      <?php else: ?>
        <div class="empty-box">No classes in this period.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ VIEW: MY EARNINGS ══ -->
  <div class="view" id="view-earnings" style="display:none">
    <section class="hero">
      <div>
        <h2>My Earnings</h2>
        <p>Your payment and session summary</p>
      </div>
      <div class="month-box"><?php echo $currentMonth . " " . $currentYear; ?></div>
    </section>
    <div class="panel-card">
      <div class="row g-4 mb-3">
        <div class="col-md-4"><div class="soft-stat soft-green"><div><i class="fas fa-dollar-sign me-1"></i>Total Earnings</div><div class="big-stat">$<?php echo number_format($totalEarnings, 2); ?></div></div></div>
        <div class="col-md-4"><div class="soft-stat soft-blue"><div><i class="fas fa-calendar-check me-1"></i>Paid Sessions</div><div class="big-stat"><?php echo $totalPaidSessions; ?></div></div></div>
        <div class="col-md-4"><div class="soft-stat soft-purple"><div><i class="fas fa-calendar-days me-1"></i>Latest Lesson</div><div class="big-stat" style="font-size:1.1rem"><?php echo htmlspecialchars($latestLessonDate); ?></div></div></div>
      </div>
      <?php if (!empty($earnings)): ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>ID</th><th>Lesson</th><th>Amount</th><th>Date</th><th>Notes</th></tr></thead>
            <tbody>
              <?php foreach ($earnings as $earning): ?>
                <tr>
                  <td><?php echo $earning["id"]; ?></td>
                  <td><?php echo htmlspecialchars($earning["lesson_title"]); ?></td>
                  <td><span class="money-badge">$<?php echo number_format($earning["amount"], 2); ?></span></td>
                  <td><?php echo htmlspecialchars($earning["lesson_date"]); ?></td>
                  <td><?php echo htmlspecialchars($earning["notes"]); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-box">No earnings found.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ VIEW: MY STUDENTS ══ -->
  <div class="view" id="view-students" style="display:none">
    <section class="hero">
      <div>
        <h2>My Students</h2>
        <p>All students assigned to you</p>
      </div>
      <div class="month-box"><?php echo count($students); ?> students</div>
    </section>
    <div class="panel-card">
      <div class="panel-title">My Students</div>
      <?php if (!empty($students)): ?>
        <ul class="mini-list list-unstyled mb-0">
          <?php foreach ($students as $student): ?>
            <li><?php echo htmlspecialchars($student); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-box">No students assigned yet.</div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.main -->
</div><!-- /.app-shell -->

<script>
  /* ── View map: anchor → view id ── */
  const VIEW_MAP = {
    '#dashboard': 'view-dashboard',
    '#schedule':  'view-schedule',
  };

  const navItems = document.querySelectorAll('.nav-item');
  const allViews = document.querySelectorAll('.view');

  function showView(viewId) {
    allViews.forEach(v => v.style.display = 'none');
    const target = document.getElementById(viewId);
    if (target) target.style.display = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  navItems.forEach(link => {
    link.addEventListener('click', e => {
      const href = link.getAttribute('href');
      if (href && VIEW_MAP[href]) {
        e.preventDefault();
        showView(VIEW_MAP[href]);
        navItems.forEach(n => n.classList.remove('active'));
        link.classList.add('active');
      }
      // External links (teacher_classes.php etc.) navigate normally
    });
  });

  /* ── Courses tab switching ── */
  function crsTab(name, btn) {
    document.querySelectorAll('.crs-tab-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.crs-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('crs-tab-' + name).style.display = '';
    btn.classList.add('active');
  }

  function crsToggleMenu(prefix, e) {
    e.stopPropagation();
    const d = document.getElementById('crs-' + prefix + '-dropdown');
    const open = d.style.display === 'block';
    d.style.display = open ? 'none' : 'block';
    document.getElementById('crs-' + prefix + '-chevron').className =
      (open ? 'fas fa-chevron-down' : 'fas fa-chevron-up') + ' ms-2';
  }

  function crsCat(prefix, cat, btn) {
    document.querySelectorAll('.crs-' + prefix + '-cat').forEach(s => s.style.display = 'none');
    document.querySelectorAll('#crs-' + prefix + '-dropdown .crs-drop-item').forEach(b => b.classList.remove('active'));
    document.getElementById('crs-' + prefix + '-' + cat).style.display = '';
    btn.classList.add('active');
    document.getElementById('crs-' + prefix + '-dropdown').style.display = 'none';
    document.getElementById('crs-' + prefix + '-chevron').className = 'fas fa-chevron-down ms-2';
  }

  document.addEventListener('click', function() {
    ['kids','junior'].forEach(p => {
      const d = document.getElementById('crs-' + p + '-dropdown');
      if (d) d.style.display = 'none';
      const c = document.getElementById('crs-' + p + '-chevron');
      if (c) c.className = 'fas fa-chevron-down ms-2';
    });
  });
</script>
<script>
function renderZoomSlots() {
  document.querySelectorAll('.zoom-slot').forEach(function(slot) {
    var url = slot.dataset.url;
    if (!url) return;
    var classAt = new Date(slot.dataset.date + 'T' + slot.dataset.time);
    var now     = new Date();
    var diffMin = (classAt - now) / 60000;

    if (diffMin <= 10) {
      var a = document.createElement('a');
      a.href = url; a.target = '_blank'; a.rel = 'noopener';
      a.className = 'btn-zoom';
      a.innerHTML = '<i class="fas fa-video"></i> Join Zoom';
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

function filterDashTable(view, when, btn) {
  var tableId = 'dash-' + view + '-table';
  var emptyId = 'dash-' + view + '-empty';
  var table   = document.getElementById(tableId);
  var emptyEl = document.getElementById(emptyId);
  if (!table) return;

  // Update active button within the same button group
  var group = btn.parentElement;
  group.querySelectorAll('.filter-btn-dash').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');

  var rows    = table.querySelectorAll('tbody tr');
  var visible = 0;
  rows.forEach(function(row) {
    var show = row.dataset.when === when;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  if (emptyEl) emptyEl.style.display = (visible === 0) ? '' : 'none';
}

// Pre-apply "today" filter for both views on page load
document.addEventListener('DOMContentLoaded', function() {
  ['classes', 'schedule'].forEach(function(view) {
    var todayBtn = document.querySelector('#view-' + view + ' .filter-btn-dash.active');
    if (todayBtn) filterDashTable(view, 'today', todayBtn);
  });
});
</script>
<script src="logout-modal.js"></script>

</body>
</html>

