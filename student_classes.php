<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";

/* ── Fetch all classes for this student ── */
$classSessions = [];
$stmt = $conn->prepare("
    SELECT id, teacher_name, student_name, class_date, class_time, type, details, zoom_link
    FROM classes
    WHERE student_name = ?
    ORDER BY class_date ASC, class_time ASC
");
if ($stmt) {
    $stmt->bind_param("s", $studentName);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classSessions[] = $row;
    }
    $stmt->close();
}

$today         = date("Y-m-d");
$total         = count($classSessions);
$todayCount    = 0;
$upcomingCount = 0;
$pastCount     = 0;

foreach ($classSessions as $c) {
    $d = $c["class_date"] ?? "";
    if ($d === $today)      $todayCount++;
    elseif ($d > $today)    $upcomingCount++;
    else                    $pastCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Classes | JuniorCode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --secondary: #0ea5e9;
      --dark: #0f172a;
      --muted: #64748b;
      --border: #e5e7eb;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      background: #f4f7fb;
      font-family: Arial, sans-serif;
      color: var(--dark);
    }

    /* ── Sidebar ── */
    .sidebar {
      position: fixed;
      top: 0; left: 0;
      width: 255px;
      height: 100vh;
      background: #ffffff;
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      z-index: 1000;
    }

    .sidebar-top { padding: 18px; }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 6px 8px 18px 8px;
      border-bottom: 1px solid #eef2f7;
      margin-bottom: 18px;
    }

    .brand-logo {
      width: 44px; height: 44px;
      border-radius: 12px;
      background: linear-gradient(135deg, #2563eb, #4f46e5);
      color: white;
      display: flex; align-items: center; justify-content: center;
      font-weight: bold; font-size: 18px;
    }

    .brand-title    { font-size: 1.1rem; font-weight: bold; margin: 0; }
    .brand-subtitle { font-size: 0.85rem; color: #64748b; margin: 0; }

    .student-box {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 14px;
      margin-bottom: 18px;
    }

    .student-avatar {
      width: 46px; height: 46px;
      border-radius: 50%;
      background: #dbeafe;
      color: #1d4ed8;
      font-weight: bold;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
    }

    .student-name { font-weight: bold; margin: 0; }
    .student-role { margin: 0; color: #64748b; font-size: 0.9rem; }

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

    .nav-icon { width: 20px; text-align: center; font-size: 16px; }

    .sidebar-bottom {
      padding: 18px;
      border-top: 1px solid #eef2f7;
    }

    /* ── Main ── */
    .main { margin-left: 255px; padding: 28px; }

    /* ── Topbar ── */
    .topbar {
      background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
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

    /* ── Stat cards ── */
    .stat-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 26px;
    }

    .stat-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 4px 14px rgba(15,23,42,0.04);
    }

    .stat-num   { font-size: 2rem; font-weight: 900; line-height: 1; margin-bottom: 6px; }
    .stat-label { font-size: 0.84rem; color: var(--muted); font-weight: 600; }

    .c-blue   { color: #2563eb; }
    .c-green  { color: #16a34a; }
    .c-orange { color: #ea580c; }
    .c-gray   { color: #64748b; }

    /* ── Filter tabs ── */
    .filter-bar {
      display: flex;
      gap: 8px;
      margin-bottom: 22px;
      flex-wrap: wrap;
    }

    .filter-btn {
      border: 2px solid var(--border);
      background: #fff;
      border-radius: 999px;
      padding: 8px 20px;
      font-weight: 700;
      font-size: 0.88rem;
      color: var(--muted);
      cursor: pointer;
      transition: all 0.2s;
    }

    .filter-btn:hover  { border-color: var(--primary); color: var(--primary); }
    .filter-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }

    /* ── Classes grid ── */
    .classes-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 18px;
    }

    .class-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 22px;
      box-shadow: 0 4px 16px rgba(15,23,42,0.05);
      display: flex;
      flex-direction: column;
      gap: 14px;
      transition: transform 0.2s, box-shadow 0.2s;
      position: relative;
    }

    .class-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(15,23,42,0.1);
    }

    .class-card.is-today  { border-color: #2563eb; border-width: 2px; }
    .class-card.is-past   { opacity: 0.72; }

    .today-ribbon {
      position: absolute;
      top: 16px; right: 16px;
      background: #2563eb;
      color: #fff;
      font-size: 0.72rem;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 999px;
      letter-spacing: 0.5px;
    }

    .card-header-row {
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }

    .teacher-avatar {
      width: 46px; height: 46px;
      border-radius: 50%;
      background: linear-gradient(135deg, #dbeafe, #bfdbfe);
      color: #1d4ed8;
      font-weight: 900;
      font-size: 1.2rem;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .card-teacher-name {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--dark);
      margin: 0 0 4px;
    }

    .type-badge {
      display: inline-block;
      border-radius: 999px;
      padding: 3px 10px;
      font-size: 0.75rem;
      font-weight: 700;
    }

    .t-paid    { background: #dcfce7; color: #166534; }
    .t-demo    { background: #fef3c7; color: #92400e; }
    .t-halfpay { background: #e0e7ff; color: #3730a3; }
    .t-nopay   { background: #fee2e2; color: #991b1b; }
    .t-other   { background: #f1f5f9; color: #475569; }

    .info-row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
      color: #475569;
    }

    .info-icon {
      width: 28px; height: 28px;
      border-radius: 8px;
      background: #f1f5f9;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.85rem;
      flex-shrink: 0;
    }

    .details-text {
      color: var(--muted);
      font-size: 0.88rem;
      line-height: 1.6;
      border-top: 1px solid #f1f5f9;
      padding-top: 10px;
    }

    .btn-zoom {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: #2D8CFF;
      color: #fff;
      font-weight: 800;
      font-size: 0.95rem;
      border-radius: 14px;
      padding: 12px;
      text-decoration: none;
      transition: all 0.2s ease;
      border: none;
      width: 100%;
      margin-top: auto;
      box-shadow: 0 6px 18px rgba(45,140,255,0.25);
    }

    .btn-zoom:hover {
      background: #1a6fd4;
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(45,140,255,0.35);
    }

    .no-zoom {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: #f1f5f9;
      color: #94a3b8;
      font-weight: 700;
      font-size: 0.88rem;
      border-radius: 14px;
      padding: 12px;
      margin-top: auto;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
      grid-column: 1 / -1;
    }

    .empty-state .empty-icon { font-size: 3.5rem; margin-bottom: 14px; }
    .empty-state h5 { font-weight: 800; color: #334155; }
    .empty-state p  { font-size: 0.95rem; max-width: 340px; margin: 0 auto; }

    @media (max-width: 991px) {
      .sidebar { position: static; width: 100%; height: auto; }
      .main { margin-left: 0; padding: 16px; }
      .stat-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 575px) {
      .classes-grid { grid-template-columns: 1fr; }
      .topbar h1 { font-size: 1.4rem; }
    }
  </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <div class="brand-logo">JC</div>
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">Student Panel</p>
      </div>
    </div>

    <div class="student-box">
      <div class="student-avatar"><?php echo strtoupper(substr($studentName, 0, 1)); ?></div>
      <div>
        <p class="student-name"><?php echo htmlspecialchars($studentName); ?></p>
        <p class="student-role">Student</p>
      </div>
    </div>

    <a href="student_dashboard.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
    </a>
    <a href="student_classes.php" class="nav-link-custom active">
      <span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span>
    </a>
    <a href="student_dashboard.php#contact" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-comments"></i></span><span>Contact Admin</span>
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

  <!-- Topbar -->
  <div class="topbar">
    <div>
      <h1>My Classes</h1>
      <p>All classes assigned to you by the admin</p>
    </div>
    <div class="topbar-date">
      <?php echo date("l, d F Y"); ?>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-num c-blue"><?php echo $total; ?></div>
      <div class="stat-label">Total Classes</div>
    </div>
    <div class="stat-card">
      <div class="stat-num c-green"><?php echo $todayCount; ?></div>
      <div class="stat-label">Today</div>
    </div>
    <div class="stat-card">
      <div class="stat-num c-orange"><?php echo $upcomingCount; ?></div>
      <div class="stat-label">Upcoming</div>
    </div>
    <div class="stat-card">
      <div class="stat-num c-gray"><?php echo $pastCount; ?></div>
      <div class="stat-label">Past</div>
    </div>
  </div>

  <!-- Filter tabs -->
  <div class="filter-bar">
    <button class="filter-btn active" onclick="filterClasses('all', this)">All (<?php echo $total; ?>)</button>
    <button class="filter-btn" onclick="filterClasses('today', this)">Today (<?php echo $todayCount; ?>)</button>
    <button class="filter-btn" onclick="filterClasses('upcoming', this)">Upcoming (<?php echo $upcomingCount; ?>)</button>
    <button class="filter-btn" onclick="filterClasses('past', this)">Past (<?php echo $pastCount; ?>)</button>
  </div>

  <!-- Classes grid -->
  <div class="classes-grid" id="classesGrid">

    <?php if (empty($classSessions)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-book"></i></div>
        <h5>No classes yet</h5>
        <p>Once the admin assigns a class to you it will appear here with all the details and your Zoom link.</p>
      </div>

    <?php else: foreach ($classSessions as $c):
        $cDate    = $c["class_date"]   ?? "";
        $cTime    = $c["class_time"]   ?? "";
        $cType    = $c["type"]         ?? "";
        $cDetails = $c["details"]      ?? "";
        $cZoom    = $c["zoom_link"]    ?? "";
        $cTeacher = $c["teacher_name"] ?? "";

        if ($cDate === $today)   $when = "today";
        elseif ($cDate > $today) $when = "upcoming";
        else                     $when = "past";

        $t = strtolower(trim($cType));
        if ($t === "paid")              $tClass = "t-paid";
        elseif ($t === "demo")          $tClass = "t-demo";
        elseif (strpos($t,"demo") !== false) $tClass = "t-demo";
        elseif ($t === "half pay")      $tClass = "t-halfpay";
        elseif ($t === "no pay")        $tClass = "t-nopay";
        else                            $tClass = "t-other";
    ?>

      <div class="class-card <?php echo $when === 'today' ? 'is-today' : ($when === 'past' ? 'is-past' : ''); ?>"
           data-when="<?php echo $when; ?>">

        <?php if ($when === "today"): ?>
          <div class="today-ribbon">TODAY</div>
        <?php endif; ?>

        <!-- Teacher header -->
        <div class="card-header-row">
          <div class="teacher-avatar">
            <?php echo strtoupper(substr($cTeacher, 0, 1)); ?>
          </div>
          <div>
            <p class="card-teacher-name"><?php echo htmlspecialchars($cTeacher); ?></p>
            <span class="type-badge <?php echo $tClass; ?>">
              <?php echo htmlspecialchars($cType); ?>
            </span>
          </div>
        </div>

        <!-- Date -->
        <div class="info-row">
          <div class="info-icon"><i class="fas fa-calendar-days"></i></div>
          <span><?php echo date("l, d F Y", strtotime($cDate)); ?></span>
        </div>

        <!-- Time -->
        <div class="info-row">
          <div class="info-icon"><i class="fas fa-clock"></i></div>
          <span><?php echo date("h:i A", strtotime($cTime)); ?></span>
        </div>

        <!-- Details -->
        <?php if (!empty($cDetails)): ?>
          <div class="details-text">
            <i class="fas fa-note-sticky"></i> <?php echo htmlspecialchars($cDetails); ?>
          </div>
        <?php endif; ?>

        <!-- Zoom button -->
        <?php if (!empty($cZoom)): ?>
          <a href="<?php echo htmlspecialchars($cZoom); ?>"
             target="_blank" rel="noopener"
             class="btn-zoom">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11l-4 4z"/>
            </svg>
            Join Class on Zoom
          </a>
        <?php else: ?>
          <div class="no-zoom">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11l-4 4z"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
            No Zoom link added yet
          </div>
        <?php endif; ?>

      </div>

    <?php endforeach; endif; ?>
  </div>
</div>

<script>
  function filterClasses(filter, btn) {
    document.querySelectorAll(".filter-btn").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");

    const cards = document.querySelectorAll(".class-card");
    let visible = 0;

    cards.forEach(card => {
      const show = filter === "all" || card.dataset.when === filter;
      card.style.display = show ? "" : "none";
      if (show) visible++;
    });

    let empty = document.getElementById("emptyFiltered");
    if (visible === 0) {
      if (!empty) {
        empty = document.createElement("div");
        empty.id = "emptyFiltered";
        empty.className = "empty-state";
        empty.innerHTML = `
          <div class="empty-icon"><i class="fas fa-magnifying-glass"></i></div>
          <h5>No classes found</h5>
          <p>No classes match this filter.</p>
        `;
        document.getElementById("classesGrid").appendChild(empty);
      }
      empty.style.display = "";
    } else if (empty) {
      empty.style.display = "none";
    }
  }
</script>

</body>
</html>
