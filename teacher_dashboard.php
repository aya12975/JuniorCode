<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherId = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "Teacher";

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
");

if (!$stmt) {
    die("Prepare failed (earnings summary): " . $conn->error);
}

$stmt->bind_param("i", $teacherId);
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
    SELECT id, amount
    FROM teacher_earnings
    WHERE teacher_id = ?
    ORDER BY id DESC
");

if (!$stmt5) {
    die("Prepare failed (earnings table): " . $conn->error);
}

$stmt5->bind_param("i", $teacherId);
$stmt5->execute();
$result5 = $stmt5->get_result();

if ($result5) {
    while ($row = $result5->fetch_assoc()) {
        $earnings[] = [
            "id" => $row["id"],
            "lesson_title" => "Session #" . $row["id"],
            "amount" => $row["amount"],
            "lesson_date" => "-",
            "notes" => "-"
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
      --primary:      #1d4ed8;
      --primary-dark: #1e3a8a;
      --secondary:    #0ea5e9;
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

    /* ── Sidebar ── */
    .sidebar {
      position: fixed;
      top: 0; left: 0;
      width: 260px;
      height: 100vh;
      background: linear-gradient(180deg, #0f172a 0%, #1e3a8a 55%, #0c4a8a 100%);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      z-index: 1000;
      overflow-y: auto;
    }

    .sidebar-top { padding: 20px 16px; }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 10px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 16px;
    }

    .brand-logo-img {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      object-fit: contain;
      background: rgba(255,255,255,0.12);
      padding: 4px;
      flex-shrink: 0;
    }

    .brand-title {
      font-size: 1.05rem;
      font-weight: 900;
      margin: 0;
      color: #ffffff;
      line-height: 1.2;
    }

    .brand-subtitle {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.55);
      margin: 3px 0 0;
      letter-spacing: 1px;
    }

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
    }

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
      box-shadow: 0 8px 20px rgba(29,78,216,0.35);
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

    .sidebar-bottom {
      padding: 16px;
      border-top: 1px solid rgba(255,255,255,0.1);
    }

    /* ── Main content ── */
    .main {
      margin-left: 260px;
      padding: 26px;
      min-height: 100vh;
    }

    .hero {
      background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--secondary) 100%);
      color: white;
      border-radius: 24px;
      padding: 26px 28px;
      margin-bottom: 22px;
      box-shadow: 0 16px 40px rgba(29,78,216,0.22);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: "";
      position: absolute;
      width: 260px; height: 260px;
      border-radius: 50%;
      background: rgba(255,255,255,0.06);
      top: -80px; right: -60px;
      pointer-events: none;
    }

    .hero::after {
      content: "";
      position: absolute;
      width: 180px; height: 180px;
      border-radius: 50%;
      background: rgba(255,255,255,0.05);
      bottom: -60px; left: -40px;
      pointer-events: none;
    }

    .hero h2 {
      margin: 0;
      font-size: 1.7rem;
      font-weight: 900;
      position: relative;
      z-index: 1;
    }

    .hero p {
      margin: 5px 0 0;
      opacity: 0.88;
      position: relative;
      z-index: 1;
    }

    .month-box {
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.22);
      padding: 10px 18px;
      border-radius: 14px;
      font-weight: 700;
      position: relative;
      z-index: 1;
      backdrop-filter: blur(4px);
    }

    .panel-card {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 20px;
      padding: 20px;
      margin-bottom: 22px;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
    }

    .panel-title {
      font-size: 1.05rem;
      font-weight: bold;
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
      background: #f8fafc;
      color: #475569;
      font-size: 0.9rem;
      border-bottom: 1px solid #e5e7eb;
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
    }

    .btn-zoom:hover {
      background: #1a6fd4;
      color: white;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(45,140,255,0.3);
    }

    .zoom-none {
      color: #cbd5e1;
      font-size: 0.85rem;
    }

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

    html { scroll-behavior: smooth; }

    section[id] { scroll-margin-top: 20px; }

    @media (max-width: 991px) {
      .sidebar { position: static; width: 100%; height: auto; }
      .main    { margin-left: 0; }
    }
  </style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
      <div>
        <p class="brand-title">JuniorCode <span style="color:var(--secondary)">&lt;/&gt;</span></p>
        <p class="brand-subtitle">TEACHER PORTAL</p>
      </div>
    </div>

    <div class="teacher-box">
      <div class="teacher-avatar"><?php echo strtoupper(substr($teacherName, 0, 1)); ?></div>
      <div>
        <p class="teacher-name"><?php echo htmlspecialchars($teacherName); ?></p>
        <p class="teacher-role">Teacher</p>
      </div>
    </div>

    <a href="#dashboard" class="nav-link-custom nav-item active">
      <span class="nav-icon"><i class="fas fa-house"></i></span>
      <span>Dashboard</span>
    </a>

    <a href="teacher_classes.php" class="nav-link-custom nav-item">
      <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span>
      <span>My Classes</span>
    </a>

    <a href="#schedule" class="nav-link-custom nav-item">
      <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
      <span>My Schedule</span>
    </a>

    <a href="#earnings" class="nav-link-custom nav-item">
      <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
      <span>My Earnings</span>
    </a>

    <a href="teacher_students.php" class="nav-link-custom nav-item">
      <span class="nav-icon"><i class="fas fa-user-graduate"></i></span>
      <span>My Students</span>
    </a>
  </div>

  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
      <span>Logout</span>
    </a>
  </div>
</div>

<div class="main">

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
            <div class="col-4"><div class="soft-stat soft-green"><div>Full Pay</div><div class="big-stat"><?php echo $fullPay; ?></div></div></div>
            <div class="col-4"><div class="soft-stat soft-orange"><div>Half Pay</div><div class="big-stat"><?php echo $halfPay; ?></div></div></div>
            <div class="col-4"><div class="soft-stat soft-red"><div>No Pay</div><div class="big-stat"><?php echo $noPay; ?></div></div></div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="panel-card">
          <div class="panel-title">Demo Classes</div>
          <div class="row g-3">
            <div class="col-4"><div class="soft-stat soft-green"><div>Enrolled</div><div class="big-stat"><?php echo $demoEnrolled; ?></div></div></div>
            <div class="col-4"><div class="soft-stat soft-blue"><div>Pending</div><div class="big-stat"><?php echo $demoPending; ?></div></div></div>
            <div class="col-4"><div class="soft-stat soft-orange"><div>Other</div><div class="big-stat"><?php echo $demoOther; ?></div></div></div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="panel-card">
          <div class="panel-title">Conversion Rate</div>
          <div class="big-stat text-primary"><?php echo $conversionRate; ?>%</div>
          <div class="text-muted">Success Rate</div>
          <div class="mt-2 text-muted"><?php echo $demoEnrolled; ?> / <?php echo $totalDemos; ?> demos</div>
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
                <a href="<?php echo htmlspecialchars($class["zoom_link"]); ?>" target="_blank" rel="noopener" class="btn-zoom"><i class="fas fa-video"></i> Start Class</a>
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
    <div class="panel-card">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="panel-title mb-0">My Classes</div>
        <span class="badge bg-primary rounded-pill"><?php echo count($classSessions); ?> total</span>
      </div>
      <?php if (!empty($classSessions)): ?>
        <div class="table-responsive">
          <table class="table align-middle">
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
              <?php foreach ($classSessions as $class):
                $isToday = ($class["class_date"] ?? "") === date("Y-m-d");
                $t = strtolower(trim($class["type"] ?? ""));
                if ($t === "paid")                   $badgeClass = "badge-paid";
                elseif (strpos($t,"demo") !== false) $badgeClass = "badge-demo";
                else                                 $badgeClass = "badge-other";
              ?>
                <tr class="<?php echo $isToday ? 'row-today' : ''; ?>">
                  <td>
                    <strong><?php echo htmlspecialchars($class["student_name"]); ?></strong>
                    <?php if ($isToday): ?><span class="badge bg-success ms-1" style="font-size:0.7rem">Today</span><?php endif; ?>
                  </td>
                  <td><?php echo date("d M Y", strtotime($class["class_date"])); ?></td>
                  <td><?php echo date("h:i A", strtotime($class["class_time"])); ?></td>
                  <td><span class="badge-type <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($class["type"]); ?></span></td>
                  <td><?php echo htmlspecialchars($class["details"] ?? "—"); ?></td>
                  <td>
                    <?php if (!empty($class["zoom_link"])): ?>
                      <a href="<?php echo htmlspecialchars($class["zoom_link"]); ?>" target="_blank" rel="noopener" class="btn-zoom"><i class="fas fa-video"></i> Start Class</a>
                    <?php else: ?>
                      <span class="zoom-none">— No link</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-box">
          <div style="font-size:2rem;margin-bottom:8px"><i class="fas fa-book-open" style="color:#94a3b8"></i></div>
          No classes assigned yet. The admin will add your classes here.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ VIEW: MY SCHEDULE ══ -->
  <div class="view" id="view-schedule" style="display:none">
    <div class="panel-card">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="panel-title mb-0">My Schedule</div>
        <span class="text-muted" style="font-size:0.9rem"><?php echo date("l, d F Y"); ?></span>
      </div>
      <?php if (!empty($classSessions)): ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr><th>Student</th><th>Date</th><th>Time</th><th>Type</th><th>Zoom</th></tr>
            </thead>
            <tbody>
              <?php foreach ($classSessions as $class):
                $isToday = ($class["class_date"] ?? "") === date("Y-m-d");
              ?>
                <tr class="<?php echo $isToday ? 'row-today' : ''; ?>">
                  <td><strong><?php echo htmlspecialchars($class["student_name"]); ?></strong>
                    <?php if ($isToday): ?><span class="badge bg-success ms-1" style="font-size:0.7rem">Today</span><?php endif; ?>
                  </td>
                  <td><?php echo date("d M Y", strtotime($class["class_date"])); ?></td>
                  <td><?php echo date("h:i A", strtotime($class["class_time"])); ?></td>
                  <td><?php echo htmlspecialchars($class["type"]); ?></td>
                  <td>
                    <?php if (!empty($class["zoom_link"])): ?>
                      <a href="<?php echo htmlspecialchars($class["zoom_link"]); ?>" target="_blank" rel="noopener" class="btn-zoom"><i class="fas fa-video"></i> Start Class</a>
                    <?php else: ?>
                      <span class="zoom-none">— No link</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-box">No scheduled classes yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ VIEW: MY EARNINGS ══ -->
  <div class="view" id="view-earnings" style="display:none">
    <div class="panel-card">
      <div class="panel-title">My Earnings</div>
      <div class="row g-4 mb-3">
        <div class="col-md-4"><div class="soft-stat soft-green"><div>Total Earnings</div><div class="big-stat">$<?php echo number_format($totalEarnings, 2); ?></div></div></div>
        <div class="col-md-4"><div class="soft-stat soft-blue"><div>Paid Sessions</div><div class="big-stat"><?php echo $totalPaidSessions; ?></div></div></div>
        <div class="col-md-4"><div class="soft-stat soft-purple"><div>Latest Lesson</div><div class="big-stat" style="font-size:1.1rem"><?php echo htmlspecialchars($latestLessonDate); ?></div></div></div>
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
</div>

<script>
  const navItems = document.querySelectorAll('.nav-item');

  navItems.forEach(link => {
    link.addEventListener('click', e => {
      const href = link.getAttribute('href');
      if (href && href.startsWith('#')) {
        e.preventDefault();
        const target = document.getElementById(href.slice(1));
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
      navItems.forEach(n => n.classList.remove('active'));
      link.classList.add('active');
    });
  });

  // Highlight nav based on scroll position
  const sections = ['dashboard','classes','schedule','earnings','students'];
  window.addEventListener('scroll', () => {
    let current = 'dashboard';
    sections.forEach(id => {
      const el = document.getElementById(id);
      if (el && window.scrollY >= el.offsetTop - 120) current = id;
    });
    navItems.forEach(n => {
      n.classList.toggle('active', n.getAttribute('href') === '#' + current);
    });
  });
</script>
</body>
</html>