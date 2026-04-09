<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherName = $_SESSION["username"];

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
        COALESCE(SUM(amount), 0) AS total_earnings,
        MAX(lesson_date) AS latest_lesson_date
    FROM teacher_earnings
    WHERE teacher_name = ?
");
$stmt->bind_param("s", $teacherName);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $totalLessons = (int)$row["total_lessons"];
    $totalEarnings = (float)$row["total_earnings"];
    $latestLessonDate = $row["latest_lesson_date"] ? $row["latest_lesson_date"] : "-";
    $totalPaidSessions = $totalLessons;
}

/* =========================
   Monthly class summary
========================= */
$fullPay = 0;
$halfPay = 0;
$noPay = 0;
$demoEnrolled = 0;
$demoPending = 0;
$demoOther = 0;

$stmt2 = $conn->prepare("
    SELECT type
    FROM classes
    WHERE teacher_name = ?
      AND MONTH(class_date) = MONTH(CURDATE())
      AND YEAR(class_date) = YEAR(CURDATE())
");
$stmt2->bind_param("s", $teacherName);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $type = strtolower(trim($row["type"]));

        if ($type === "paid") {
            $fullPay++;
        } elseif ($type === "half pay") {
            $halfPay++;
        } elseif ($type === "no pay") {
            $noPay++;
        } elseif ($type === "demo enrolled") {
            $demoEnrolled++;
        } elseif ($type === "demo pending" || $type === "demo") {
            $demoPending++;
        } elseif ($type === "demo other" || $type === "other") {
            $demoOther++;
        }
    }
}

$totalDemos = $demoEnrolled + $demoPending + $demoOther;
$conversionRate = $totalDemos > 0 ? round(($demoEnrolled / $totalDemos) * 100) : 0;

/* =========================
   All classes
========================= */
$classSessions = [];
$stmt3 = $conn->prepare("
    SELECT id, student_name, class_date, class_time, type, details
    FROM classes
    WHERE teacher_name = ?
    ORDER BY class_date DESC, class_time ASC
");
$stmt3->bind_param("s", $teacherName);
$stmt3->execute();
$result3 = $stmt3->get_result();

if ($result3) {
    while ($row = $result3->fetch_assoc()) {
        $classSessions[] = $row;
    }
}

/* =========================
   Today's classes
========================= */
$todaysClasses = [];
$stmt4 = $conn->prepare("
    SELECT id, student_name, class_time, type, details
    FROM classes
    WHERE teacher_name = ? AND class_date = CURDATE()
    ORDER BY class_time ASC
");
$stmt4->bind_param("s", $teacherName);
$stmt4->execute();
$result4 = $stmt4->get_result();

if ($result4) {
    while ($row = $result4->fetch_assoc()) {
        $todaysClasses[] = $row;
    }
}

/* =========================
   Earnings table
========================= */
$earnings = [];
$stmt5 = $conn->prepare("
    SELECT id, lesson_title, amount, lesson_date, notes
    FROM teacher_earnings
    WHERE teacher_name = ?
    ORDER BY lesson_date DESC, id DESC
");
$stmt5->bind_param("s", $teacherName);
$stmt5->execute();
$result5 = $stmt5->get_result();

if ($result5) {
    while ($row = $result5->fetch_assoc()) {
        $earnings[] = $row;
    }
}

/* =========================
   Teacher students list
========================= */
$students = [];
$stmt6 = $conn->prepare("
    SELECT DISTINCT student_name
    FROM classes
    WHERE teacher_name = ?
    ORDER BY student_name ASC
");
$stmt6->bind_param("s", $teacherName);
$stmt6->execute();
$result6 = $stmt6->get_result();

if ($result6) {
    while ($row = $result6->fetch_assoc()) {
        $students[] = $row["student_name"];
    }
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
  <style>
    body {
      margin: 0;
      background: #f4f7fb;
      font-family: Arial, sans-serif;
      color: #1e293b;
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 255px;
      height: 100vh;
      background: #ffffff;
      border-right: 1px solid #e5e7eb;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      z-index: 1000;
    }

    .sidebar-top {
      padding: 18px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 6px 8px 18px 8px;
      border-bottom: 1px solid #eef2f7;
      margin-bottom: 18px;
    }

    .brand-logo {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      background: linear-gradient(135deg, #2563eb, #4f46e5);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 18px;
    }

    .brand-title {
      font-size: 1.1rem;
      font-weight: bold;
      margin: 0;
    }

    .brand-subtitle {
      font-size: 0.85rem;
      color: #64748b;
      margin: 0;
    }

    .teacher-box {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #f8fafc;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 14px;
      margin-bottom: 18px;
    }

    .teacher-avatar {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      background: #dbeafe;
      color: #1d4ed8;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
    }

    .teacher-name {
      font-weight: bold;
      margin: 0;
    }

    .teacher-role {
      margin: 0;
      color: #64748b;
      font-size: 0.9rem;
    }

    .nav-link-custom {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #334155;
      padding: 13px 14px;
      border-radius: 12px;
      margin: 6px 4px;
      font-weight: 600;
      transition: 0.25s;
    }

    .nav-link-custom:hover,
    .nav-link-custom.active {
      background: #e8f0ff;
      color: #1d4ed8;
    }

    .nav-icon {
      width: 20px;
      text-align: center;
      font-size: 16px;
    }

    .sidebar-bottom {
      padding: 18px;
      border-top: 1px solid #eef2f7;
    }

    .main {
      margin-left: 255px;
      padding: 26px;
    }

    .topbar {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 18px;
      padding: 14px 18px;
      margin-bottom: 22px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .topbar-user {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: bold;
    }

    .small-avatar {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: #1d4ed8;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
    }

    .hero {
      background: linear-gradient(135deg, #0f4fd6, #1d4ed8);
      color: white;
      border-radius: 22px;
      padding: 22px 24px;
      margin-bottom: 22px;
      box-shadow: 0 12px 30px rgba(29, 78, 216, 0.18);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }

    .hero h2 {
      margin: 0;
      font-size: 1.6rem;
      font-weight: bold;
    }

    .hero p {
      margin: 4px 0 0;
      opacity: 0.92;
    }

    .month-box {
      background: rgba(255,255,255,0.14);
      border: 1px solid rgba(255,255,255,0.18);
      padding: 10px 16px;
      border-radius: 14px;
      font-weight: 600;
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
    .soft-blue { background: #eff6ff; color: #1d4ed8; }
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

    .mini-list li {
      padding: 10px 0;
      border-bottom: 1px solid #eef2f7;
    }

    .mini-list li:last-child {
      border-bottom: none;
    }

    @media (max-width: 991px) {
      .sidebar {
        position: static;
        width: 100%;
        height: auto;
      }

      .main {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <div class="brand-logo">JC</div>
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">Coding Education</p>
      </div>
    </div>

    <div class="teacher-box">
      <div class="teacher-avatar"><?php echo strtoupper(substr($teacherName, 0, 1)); ?></div>
      <div>
        <p class="teacher-name"><?php echo htmlspecialchars($teacherName); ?></p>
        <p class="teacher-role">Teacher</p>
      </div>
    </div>

    <a href="#dashboard" class="nav-link-custom active">
      <span class="nav-icon">🏠</span>
      <span>Dashboard</span>
    </a>

    <a href="#classes" class="nav-link-custom">
      <span class="nav-icon">🖥️</span>
      <span>My Classes</span>
    </a>

    <a href="teacher_schedule.php" class="nav-link-custom">
      <span class="nav-icon">📅</span>
      <span>My Schedule</span>
    </a>

    <a href="#curriculum" class="nav-link-custom">
      <span class="nav-icon">⚙️</span>
      <span>Curriculum</span>
    </a>

    <a href="#earnings" class="nav-link-custom">
      <span class="nav-icon">💵</span>
      <span>My Earnings</span>
    </a>

    <a href="#students" class="nav-link-custom">
      <span class="nav-icon">👤</span>
      <span>My Students</span>
    </a>
  </div>

  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon">↪</span>
      <span>Logout</span>
    </a>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <div class="topbar-user">
      <div class="small-avatar"><?php echo strtoupper(substr($teacherName, 0, 1)); ?></div>
      <span><?php echo htmlspecialchars($teacherName); ?></span>
    </div>
    <div class="text-muted fw-semibold"><?php echo $currentMonth . " " . $currentYear; ?></div>
  </div>

  <section id="dashboard" class="hero">
    <div>
      <h2>Hello, <?php echo htmlspecialchars($teacherName); ?></h2>
      <p>Teaching Dashboard</p>
    </div>
    <div class="month-box">Viewing: <?php echo $currentMonth . " " . $currentYear; ?></div>
  </section>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="panel-card">
        <div class="panel-title">Paid Classes</div>
        <div class="row g-3">
          <div class="col-4">
            <div class="soft-stat soft-green">
              <div>Full Pay</div>
              <div class="big-stat"><?php echo $fullPay; ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="soft-stat soft-orange">
              <div>Half Pay</div>
              <div class="big-stat"><?php echo $halfPay; ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="soft-stat soft-red">
              <div>No Pay</div>
              <div class="big-stat"><?php echo $noPay; ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="panel-card">
        <div class="panel-title">Demo Classes</div>
        <div class="row g-3">
          <div class="col-4">
            <div class="soft-stat soft-green">
              <div>Enrolled</div>
              <div class="big-stat"><?php echo $demoEnrolled; ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="soft-stat soft-blue">
              <div>Pending</div>
              <div class="big-stat"><?php echo $demoPending; ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="soft-stat soft-orange">
              <div>Other</div>
              <div class="big-stat"><?php echo $demoOther; ?></div>
            </div>
          </div>
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

  <section id="classes" class="panel-card">
    <div class="panel-title">My Classes</div>
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
            </tr>
          </thead>
          <tbody>
            <?php foreach ($classSessions as $class): ?>
              <tr>
                <td><?php echo htmlspecialchars($class["student_name"]); ?></td>
                <td><?php echo htmlspecialchars($class["class_date"]); ?></td>
                <td><?php echo htmlspecialchars($class["class_time"]); ?></td>
                <td>
                  <span class="<?php echo strtolower($class["type"]) === "paid" ? "badge-paid" : "badge-demo"; ?>">
                    <?php echo htmlspecialchars($class["type"]); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($class["details"]); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-box">No classes found.</div>
    <?php endif; ?>
  </section>

  <section id="schedule" class="panel-card">
    <div class="panel-title">My Schedule</div>
    <?php if (!empty($todaysClasses)): ?>
      <ul class="mini-list list-unstyled mb-0">
        <?php foreach ($todaysClasses as $class): ?>
          <li>
            <strong><?php echo htmlspecialchars($class["student_name"]); ?></strong>
            — <?php echo htmlspecialchars($class["class_time"]); ?>
            — <?php echo htmlspecialchars($class["type"]); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty-box">No scheduled classes for today.</div>
    <?php endif; ?>
  </section>

  <section id="curriculum" class="panel-card">
    <div class="panel-title">Curriculum</div>
    <div class="text-muted">
      Curriculum section is ready in the UI. Later you can connect it to a database table for lesson plans, course topics, and teaching materials.
    </div>
  </section>

  <section id="earnings" class="panel-card">
    <div class="panel-title">My Earnings</div>

    <div class="row g-4 mb-3">
      <div class="col-md-4">
        <div class="soft-stat soft-green">
          <div>Total Earnings</div>
          <div class="big-stat">$<?php echo number_format($totalEarnings, 2); ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="soft-stat soft-blue">
          <div>Paid Sessions Income</div>
          <div class="big-stat"><?php echo $totalPaidSessions; ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="soft-stat soft-purple">
          <div>Latest Lesson Date</div>
          <div class="big-stat" style="font-size: 1.1rem;"><?php echo htmlspecialchars($latestLessonDate); ?></div>
        </div>
      </div>
    </div>

    <?php if (!empty($earnings)): ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Lesson Title</th>
              <th>Amount</th>
              <th>Lesson Date</th>
              <th>Notes</th>
            </tr>
          </thead>
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
  </section>

  <section id="students" class="panel-card">
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
  </section>
</div>

</body>
</html>