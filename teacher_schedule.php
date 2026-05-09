<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherId   = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "Teacher";

/* ── Week calculation ── */
$startDate      = isset($_GET["start"]) ? $_GET["start"] : date("Y-m-d");
$startTimestamp = strtotime($startDate);

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date("Y-m-d", strtotime("+$i day", $startTimestamp));
}

$times = [
    "08:00:00","09:00:00","10:00:00","11:00:00",
    "12:00:00","13:00:00","14:00:00","15:00:00",
    "16:00:00","17:00:00","18:00:00","19:00:00"
];

/* ── Load saved availability ── */
$availability = [];
$stmt = $conn->prepare("
    SELECT available_date, available_time, status
    FROM teacher_availability
    WHERE teacher_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $key = $row["available_date"] . "_" . $row["available_time"];
        $availability[$key] = $row["status"];
    }
    $stmt->close();
}

/* ── Load scheduled classes for this week ── */
$scheduledClasses = [];
$weekClasses      = [];

$stmt2 = $conn->prepare("
    SELECT id, student_name, class_date, class_time, type, zoom_link
    FROM classes
    WHERE (teacher_id = ? OR LOWER(teacher_name) = LOWER(?))
      AND class_date BETWEEN ? AND ?
    ORDER BY class_date ASC, class_time ASC
");
if ($stmt2) {
    $stmt2->bind_param("isss", $teacherId, $teacherName, $days[0], $days[6]);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    while ($row = $r2->fetch_assoc()) {
        $weekClasses[] = $row;
        // Round to nearest grid hour for cell lookup
        $hour = date("H:00:00", strtotime($row["class_time"]));
        $key  = $row["class_date"] . "_" . $hour;
        $scheduledClasses[$key] = $row;
    }
    $stmt2->close();
}

$prevWeek = date("Y-m-d", strtotime("-7 days", $startTimestamp));
$nextWeek = date("Y-m-d", strtotime("+7 days", $startTimestamp));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Schedule | JuniorCode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:      #3e5077;
      --primary-dark: #152c6b;
      --secondary:    #143674;
      --dark:         #0f172a;
      --muted:        #64748b;
      --border:       #edf4ff;
      --shadow:       0 18px 45px rgba(37,99,235,0.08);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: Arial, sans-serif;
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
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      z-index: 1000;
      overflow-y: auto;
      transition: transform 0.3s ease;
    }
    body.sidebar-collapsed .sidebar { transform: translateX(-260px); }

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
      width: 55px; height: 55px;
      border-radius: 0;
      object-fit: contain;
      background: none;
      padding: 0;
      flex-shrink: 0;
    }

    .brand-title    { font-size: 1.05rem; font-weight: 900; margin: 0; color: #fff; line-height: 1.2; }
    .brand-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.55); margin: 3px 0 0; letter-spacing: 1px; }

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
      width: 44px; height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff;
      font-weight: bold;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; flex-shrink: 0; overflow: hidden;
    }
    .teacher-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .teacher-name { font-weight: 800; margin: 0; color: #fff; }
    .teacher-role { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }

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

    .nav-link-custom:hover { background: rgba(255,255,255,0.09); color: #fff; }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff;
      box-shadow: 0 8px 20px rgba(29,78,216,0.35);
    }

    .nav-icon {
      width: 32px; height: 32px;
      border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0;
    }

    .nav-link-custom.active .nav-icon { background: rgba(255,255,255,0.18); }

    .sidebar-bottom {
      padding: 16px;
      border-top: 1px solid rgba(255,255,255,0.1);
    }

    /* ── Main ── */
    .main { margin-left: 260px; padding: 28px; transition: margin-left 0.3s ease; }
    body.sidebar-collapsed .main { margin-left: 0; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    /* ── Topbar ── */
    .topbar {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 20px;
      padding: 22px 26px;
      margin-bottom: 26px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 14px;
      color: #fff;
    }

    .topbar h1 { font-size: 1.7rem; font-weight: 900; margin: 0; }
    .topbar p  { margin: 4px 0 0; opacity: 0.88; font-size: 0.97rem; }

    .topbar-date {
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 12px;
      padding: 10px 18px;
      font-weight: 700;
      font-size: 0.9rem;
    }

    /* ── Card ── */
    .card-box {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 22px;
      padding: 24px;
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
    }
    .card-box::before {
      content: '';
      display: block;
      height: 5px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      position: absolute;
      top: 0; left: 0; right: 0;
      border-radius: 22px 22px 0 0;
    }

    /* ── Week nav ── */
    .week-nav {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      gap: 12px;
    }

    .week-nav h4 {
      font-size: 1.05rem;
      font-weight: 800;
      margin: 0;
      color: var(--dark);
    }

    .btn-nav {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 9px 18px;
      font-weight: 700;
      font-size: 0.88rem;
      text-decoration: none;
      transition: background 0.2s;
    }

    .btn-nav:hover { background: var(--primary-dark); color: #fff; }

    /* ── Schedule table ── */
    .schedule-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.87rem;
    }

    .schedule-table th,
    .schedule-table td {
      border: 1px solid #e5e7eb;
      text-align: center;
      padding: 10px 6px;
    }

    .schedule-table thead th {
      background: #f8fafc;
      color: #475569;
      font-weight: 700;
      font-size: 0.83rem;
    }

    .time-col {
      width: 100px;
      font-weight: 700;
      background: #f8fafc;
      color: #334155;
      white-space: nowrap;
    }

    .slot {
      cursor: pointer;
      min-height: 52px;
      transition: background 0.18s;
      background: #ffffff;
    }

    .slot:hover { background: #eef2ff; }

    .slot.available {
      background: #dcfce7;
      color: #166534;
      font-weight: 700;
      font-size: 0.82rem;
    }

    .today-col { background: #eff6ff !important; }

    /* ── Legend ── */
    .legend {
      display: flex;
      gap: 20px;
      margin-top: 18px;
      flex-wrap: wrap;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .legend-dot {
      width: 14px; height: 14px;
      border-radius: 4px;
      flex-shrink: 0;
    }

    .dot-available { background: #dcfce7; border: 1px solid #bbf7d0; }
    .dot-empty     { background: #ffffff; border: 1px solid #e5e7eb; }
    .dot-booked    { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; }

    .slot.booked {
      background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
      color: #fff;
      font-weight: 700;
      font-size: 0.78rem;
      cursor: default;
      padding: 6px 4px;
    }

    .slot.booked:hover { background: linear-gradient(135deg, var(--primary), var(--secondary)) !important; }

    .booked-student { font-weight: 800; display: block; font-size: 0.8rem; }
    .booked-time    { opacity: 0.85; font-size: 0.72rem; display: block; }
    .booked-type    { display: inline-block; background: rgba(255,255,255,0.2); border-radius: 999px; padding: 1px 7px; font-size: 0.7rem; margin-top: 3px; }

    .btn-zoom-sm {
      display: inline-flex; align-items: center; gap: 4px;
      background: rgba(255,255,255,0.25); color: #fff;
      border-radius: 6px; padding: 3px 8px; font-size: 0.7rem;
      font-weight: 700; text-decoration: none; margin-top: 4px;
      transition: background 0.2s;
    }
    .btn-zoom-sm:hover { background: rgba(255,255,255,0.4); color: #fff; }

    .week-classes-card {
      background: white; border: 1px solid var(--border);
      border-radius: 22px; padding: 22px; margin-bottom: 22px;
      box-shadow: var(--shadow); position: relative; overflow: hidden;
    }
    .week-classes-card::before {
      content: ''; display: block; height: 5px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      position: absolute; top: 0; left: 0; right: 0;
      border-radius: 22px 22px 0 0;
    }
    .week-classes-title {
      font-size: 1.05rem; font-weight: 800;
      color: var(--primary); margin-bottom: 14px;
    }

    @media (max-width: 991px) {
      .sidebar { position: static; width: 100%; height: auto; }
      .main { margin-left: 0; padding: 16px; }
    }

    @media (max-width: 767px) {
      .schedule-table { font-size: 0.75rem; }
      .schedule-table th, .schedule-table td { padding: 6px 3px; }
      .time-col { width: 70px; }
    }
  </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">TEACHER PORTAL</p>
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

    <a href="teacher_dashboard.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
    </a>
    <a href="teacher_classes.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>My Classes</span>
    </a>
    <a href="teacher_schedule.php" class="nav-link-custom active">
      <span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>My Schedule</span>
    </a>
    <a href="teacher_monthly_earnings.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>My Earnings</span>
    </a>
    <a href="teacher_students.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-user-graduate"></i></span><span>My Students</span>
    </a>
    <a href="teacher_courses.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span>
    </a>
    <a href="teacher_profile.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span>
    </a>
  </div>

  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span>
    </a>
  </div>
</div>

<!-- ── MAIN ── -->
<div class="main">

  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <!-- Topbar -->
  <div class="topbar">
    <div>
      <h1>My Schedule</h1>
      <p>Click any slot to mark yourself as available</p>
    </div>
    <div class="topbar-date">
      <?php echo date("l, d F Y"); ?>
    </div>
  </div>

  <!-- Scheduled classes this week -->
  <div class="week-classes-card">
    <div class="week-classes-title">
      <i class="fas fa-calendar-check me-2"></i>Scheduled Classes This Week
    </div>
    <?php if (!empty($weekClasses)): ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Student</th>
              <th>Date</th>
              <th>Time</th>
              <th>Type</th>
              <th>Zoom</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($weekClasses as $cls):
              $isToday = $cls["class_date"] === date("Y-m-d");
              $t = strtolower(trim($cls["type"] ?? ""));
              $typeStyle = $t === "paid"
                ? "background:#dcfce7;color:#166534"
                : ($t === "demo" || strpos($t,"demo") !== false
                  ? "background:#fef3c7;color:#92400e"
                  : "background:#e0e7ff;color:#3730a3");
            ?>
              <tr style="<?php echo $isToday ? 'background:#f0f9ff' : ''; ?>">
                <td>
                  <strong><?php echo htmlspecialchars($cls["student_name"]); ?></strong>
                  <?php if ($isToday): ?>
                    <span style="background:#2563eb;color:#fff;border-radius:999px;font-size:0.68rem;padding:2px 8px;font-weight:700;margin-left:6px">Today</span>
                  <?php endif; ?>
                </td>
                <td><?php echo date("D, d M Y", strtotime($cls["class_date"])); ?></td>
                <td><?php echo date("h:i A", strtotime($cls["class_time"])); ?></td>
                <td><span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:0.78rem;font-weight:700;<?php echo $typeStyle; ?>"><?php echo htmlspecialchars($cls["type"]); ?></span></td>
                <td>
                  <?php if (!empty($cls["zoom_link"])): ?>
                    <a href="<?php echo htmlspecialchars($cls["zoom_link"]); ?>" target="_blank" rel="noopener"
                       style="display:inline-flex;align-items:center;gap:6px;background:#2D8CFF;color:white;font-weight:700;border-radius:10px;padding:6px 12px;font-size:0.82rem;text-decoration:none;">
                      <i class="fas fa-video"></i> Join Zoom
                    </a>
                  <?php else: ?>
                    <span style="color:#cbd5e1;font-size:0.85rem">— No link</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div style="text-align:center;color:#94a3b8;padding:24px 10px">
        <i class="fas fa-calendar-xmark" style="font-size:1.8rem;margin-bottom:8px;display:block"></i>
        No classes scheduled this week.
      </div>
    <?php endif; ?>
  </div>

  <!-- Schedule card -->
  <div class="card-box">

    <!-- Week navigation -->
    <div class="week-nav">
      <a href="teacher_schedule.php?start=<?php echo $prevWeek; ?>" class="btn-nav">
        <i class="fas fa-chevron-left"></i> Prev
      </a>
      <h4>
        <i class="fas fa-calendar-week" style="color:var(--primary);margin-right:8px"></i>
        <?php echo date("d M", strtotime($days[0])); ?> &mdash; <?php echo date("d M Y", strtotime($days[6])); ?>
      </h4>
      <a href="teacher_schedule.php?start=<?php echo $nextWeek; ?>" class="btn-nav">
        Next <i class="fas fa-chevron-right"></i>
      </a>
    </div>

    <!-- Table -->
    <div class="table-responsive">
      <table class="schedule-table">
        <thead>
          <tr>
            <th class="time-col"><i class="fas fa-clock"></i></th>
            <?php foreach ($days as $day):
              $isToday = $day === date("Y-m-d");
            ?>
              <th class="<?php echo $isToday ? 'today-col' : ''; ?>">
                <?php echo date("D", strtotime($day)); ?><br>
                <span style="font-weight:500;font-size:0.78rem"><?php echo date("d M", strtotime($day)); ?></span>
                <?php if ($isToday): ?>
                  <br><span style="background:#2563eb;color:#fff;border-radius:999px;font-size:0.68rem;padding:1px 7px;font-weight:700">TODAY</span>
                <?php endif; ?>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($times as $time): ?>
            <tr>
              <td class="time-col"><?php echo date("g:i A", strtotime($time)); ?></td>
              <?php foreach ($days as $day):
                $key      = $day . "_" . $time;
                $status   = $availability[$key] ?? "";
                $isAvailable = $status === "available";
                $isToday  = $day === date("Y-m-d");
                $bookedClass = $scheduledClasses[$key] ?? null;
              ?>
                <td
                  class="slot <?php echo $bookedClass ? 'booked' : ($isAvailable ? 'available' : ''); ?> <?php echo $isToday ? 'today-col' : ''; ?>"
                  data-date="<?php echo $day; ?>"
                  data-time="<?php echo $time; ?>"
                  <?php echo $bookedClass ? 'title="Class with ' . htmlspecialchars($bookedClass['student_name']) . '"' : ''; ?>
                >
                  <?php if ($bookedClass): ?>
                    <span class="booked-student"><?php echo htmlspecialchars($bookedClass["student_name"]); ?></span>
                    <span class="booked-time"><?php echo date("h:i A", strtotime($bookedClass["class_time"])); ?></span>
                    <span class="booked-type"><?php echo htmlspecialchars($bookedClass["type"]); ?></span>
                    <?php if (!empty($bookedClass["zoom_link"])): ?>
                      <br><a href="<?php echo htmlspecialchars($bookedClass["zoom_link"]); ?>" target="_blank" rel="noopener" class="btn-zoom-sm" onclick="event.stopPropagation()"><i class="fas fa-video"></i> Zoom</a>
                    <?php endif; ?>
                  <?php elseif ($isAvailable): ?>
                    <i class="fas fa-check" style="margin-right:4px"></i>Available
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Legend -->
    <div class="legend">
      <div class="legend-item">
        <div class="legend-dot dot-booked"></div>
        <span>Scheduled class</span>
      </div>
      <div class="legend-item">
        <div class="legend-dot dot-available"></div>
        <span>Available — admin can book</span>
      </div>
      <div class="legend-item">
        <div class="legend-dot dot-empty"></div>
        <span>Click to mark as available</span>
      </div>
    </div>

  </div>
</div>

<script>
document.querySelectorAll(".slot").forEach(slot => {
  slot.addEventListener("click", function () {
    if (this.classList.contains("booked")) return;
    const date      = this.dataset.date;
    const time      = this.dataset.time;
    const isAvail   = this.classList.contains("available");
    const newStatus = isAvail ? "remove" : "available";
    const cell      = this;

    fetch("save_availability.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "date=" + encodeURIComponent(date) +
            "&time=" + encodeURIComponent(time) +
            "&status=" + encodeURIComponent(newStatus)
    })
    .then(r => r.text())
    .then(data => {
      if (data.trim() === "success") {
        if (newStatus === "available") {
          cell.classList.add("available");
          cell.innerHTML = '<i class="fas fa-check" style="margin-right:4px"></i>Available';
        } else {
          cell.classList.remove("available");
          cell.innerHTML = "";
        }
      } else {
        alert("Error saving availability");
      }
    })
    .catch(() => alert("Error saving availability"));
  });
});
</script>

<script src="logout-modal.js"></script>
</body>
</html>
