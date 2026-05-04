<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherId   = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "Teacher";

/* ── Per-student summary ── */
$students = [];
$stmt = $conn->prepare("
    SELECT
        student_name,
        COUNT(*)                                                        AS total_classes,
        SUM(CASE WHEN LOWER(type) = 'paid'     THEN 1 ELSE 0 END)     AS paid,
        SUM(CASE WHEN LOWER(type) = 'demo'     THEN 1 ELSE 0 END)     AS demo,
        SUM(CASE WHEN LOWER(type) = 'half pay' THEN 1 ELSE 0 END)     AS half_pay,
        SUM(CASE WHEN LOWER(type) = 'no pay'   THEN 1 ELSE 0 END)     AS no_pay,
        MAX(class_date)                                                 AS latest_class,
        MIN(CASE WHEN class_date >= CURDATE() THEN class_date END)     AS next_class
    FROM classes
    WHERE teacher_id = ? OR LOWER(teacher_name) = LOWER(?)
    GROUP BY student_name
    ORDER BY total_classes DESC
");
if ($stmt) {
    $stmt->bind_param("is", $teacherId, $teacherName);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}

$totalStudents  = count($students);
$totalClasses   = array_sum(array_column($students, "total_classes"));
$withUpcoming   = count(array_filter($students, fn($s) => !empty($s["next_class"])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Students | JuniorCode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:      #2563eb;
      --primary-dark: #1d4ed8;
      --secondary:    #0ea5e9;
      --dark:         #0f172a;
      --muted:        #64748b;
      --border:       #e5e7eb;
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
      width: 48px; height: 48px;
      border-radius: 14px;
      object-fit: contain;
      background: rgba(255,255,255,0.12);
      padding: 4px;
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
      font-size: 18px; flex-shrink: 0;
    }

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
    .main { margin-left: 260px; padding: 28px; }

    /* ── Topbar ── */
    .topbar {
      background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 50%, #0ea5e9 100%);
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

    /* ── Summary stat cards ── */
    .stat-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 26px;
    }

    .stat-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 22px 20px;
      text-align: center;
      box-shadow: 0 4px 14px rgba(15,23,42,0.04);
    }

    .stat-icon {
      width: 48px; height: 48px;
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem;
      margin: 0 auto 12px;
    }

    .icon-blue   { background: #eff6ff; color: #2563eb; }
    .icon-green  { background: #f0fdf4; color: #16a34a; }
    .icon-purple { background: #f5f3ff; color: #7c3aed; }

    .stat-num   { font-size: 2rem; font-weight: 900; line-height: 1; margin-bottom: 4px; }
    .stat-label { font-size: 0.85rem; color: var(--muted); font-weight: 600; }

    .c-blue   { color: #2563eb; }
    .c-green  { color: #16a34a; }
    .c-purple { color: #7c3aed; }

    /* ── Search bar ── */
    .search-wrap {
      margin-bottom: 20px;
    }

    .search-input {
      width: 100%;
      max-width: 360px;
      padding: 10px 16px 10px 42px;
      border: 2px solid var(--border);
      border-radius: 12px;
      font-size: 0.92rem;
      outline: none;
      transition: border-color 0.2s;
      background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398l3.85 3.85a1 1 0 0 0 1.415-1.415l-3.868-3.833zm-5.44 1.406a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 14px center;
    }

    .search-input:focus { border-color: var(--primary); }

    /* ── Student cards grid ── */
    .students-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 18px;
    }

    .student-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 22px;
      box-shadow: 0 4px 16px rgba(15,23,42,0.05);
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .student-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(15,23,42,0.1);
    }

    /* Card header */
    .card-head {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 18px;
    }

    .s-avatar {
      width: 52px; height: 52px;
      border-radius: 50%;
      background: linear-gradient(135deg, #dbeafe, #bfdbfe);
      color: #1d4ed8;
      font-weight: 900;
      font-size: 1.3rem;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .s-name  { font-size: 1.05rem; font-weight: 800; margin: 0 0 3px; }
    .s-total {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: #eff6ff;
      color: #1d4ed8;
      border-radius: 999px;
      padding: 3px 10px;
      font-size: 0.78rem;
      font-weight: 700;
    }

    /* Class type breakdown */
    .type-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 8px;
      margin-bottom: 16px;
    }

    .type-box {
      border-radius: 12px;
      padding: 10px 6px;
      text-align: center;
    }

    .type-box .t-num   { font-size: 1.3rem; font-weight: 900; line-height: 1; }
    .type-box .t-label { font-size: 0.68rem; font-weight: 700; margin-top: 3px; }

    .tb-paid     { background: #dcfce7; color: #166534; }
    .tb-demo     { background: #fef3c7; color: #92400e; }
    .tb-halfpay  { background: #e0e7ff; color: #3730a3; }
    .tb-nopay    { background: #fee2e2; color: #991b1b; }

    /* Date info rows */
    .date-row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.86rem;
      color: #475569;
      margin-bottom: 6px;
    }

    .date-icon {
      width: 26px; height: 26px;
      border-radius: 7px;
      background: #f1f5f9;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.8rem;
      flex-shrink: 0;
      color: var(--muted);
    }

    .next-badge {
      display: inline-block;
      background: #dcfce7;
      color: #166534;
      border-radius: 8px;
      padding: 2px 8px;
      font-size: 0.75rem;
      font-weight: 700;
    }

    .no-next {
      color: #94a3b8;
      font-style: italic;
      font-size: 0.82rem;
    }

    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
      grid-column: 1 / -1;
    }

    .empty-state .empty-icon { font-size: 3rem; margin-bottom: 14px; color: #cbd5e1; }
    .empty-state h5 { font-weight: 800; color: #334155; }

    @media (max-width: 991px) {
      .sidebar { position: static; width: 100%; height: auto; }
      .main { margin-left: 0; padding: 16px; }
      .stat-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 575px) {
      .stat-grid { grid-template-columns: 1fr 1fr; }
      .students-grid { grid-template-columns: 1fr; }
      .topbar h1 { font-size: 1.4rem; }
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
        <p class="brand-title">JuniorCode <span style="opacity:.7">&lt;/&gt;</span></p>
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

    <a href="teacher_dashboard.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
    </a>
    <a href="teacher_classes.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>My Classes</span>
    </a>
    <a href="teacher_schedule.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>My Schedule</span>
    </a>
    <a href="teacher_dashboard.php#earnings" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>My Earnings</span>
    </a>
    <a href="teacher_students.php" class="nav-link-custom active">
      <span class="nav-icon"><i class="fas fa-user-graduate"></i></span><span>My Students</span>
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
      <h1>My Students</h1>
      <p>All students assigned to your classes</p>
    </div>
    <div class="topbar-date">
      <?php echo date("l, d F Y"); ?>
    </div>
  </div>

  <!-- Summary stat cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon icon-blue"><i class="fas fa-users"></i></div>
      <div class="stat-num c-blue"><?php echo $totalStudents; ?></div>
      <div class="stat-label">Total Students</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon icon-green"><i class="fas fa-chalkboard-user"></i></div>
      <div class="stat-num c-green"><?php echo $totalClasses; ?></div>
      <div class="stat-label">Total Classes</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon icon-purple"><i class="fas fa-calendar-check"></i></div>
      <div class="stat-num c-purple"><?php echo $withUpcoming; ?></div>
      <div class="stat-label">With Upcoming Classes</div>
    </div>
  </div>

  <!-- Search -->
  <div class="search-wrap">
    <input
      type="text"
      id="searchInput"
      class="search-input"
      placeholder="Search student..."
      oninput="filterStudents(this.value)"
    >
  </div>

  <!-- Students grid -->
  <div class="students-grid" id="studentsGrid">

    <?php if (empty($students)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
        <h5>No students yet</h5>
        <p>Once the admin assigns classes to students for you, they will appear here.</p>
      </div>

    <?php else: foreach ($students as $s):
        $name     = $s["student_name"]  ?? "";
        $total    = (int)($s["total_classes"] ?? 0);
        $paid     = (int)($s["paid"]     ?? 0);
        $demo     = (int)($s["demo"]     ?? 0);
        $halfPay  = (int)($s["half_pay"] ?? 0);
        $noPay    = (int)($s["no_pay"]   ?? 0);
        $latest   = $s["latest_class"]   ?? "";
        $next     = $s["next_class"]     ?? "";
    ?>

      <div class="student-card" data-name="<?php echo strtolower(htmlspecialchars($name)); ?>">

        <!-- Header -->
        <div class="card-head">
          <div class="s-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
          <div>
            <p class="s-name"><?php echo htmlspecialchars($name); ?></p>
            <span class="s-total">
              <i class="fas fa-layer-group"></i>
              <?php echo $total; ?> class<?php echo $total !== 1 ? "es" : ""; ?>
            </span>
          </div>
        </div>

        <!-- Type breakdown -->
        <div class="type-row">
          <div class="type-box tb-paid">
            <div class="t-num"><?php echo $paid; ?></div>
            <div class="t-label">Paid</div>
          </div>
          <div class="type-box tb-demo">
            <div class="t-num"><?php echo $demo; ?></div>
            <div class="t-label">Demo</div>
          </div>
          <div class="type-box tb-halfpay">
            <div class="t-num"><?php echo $halfPay; ?></div>
            <div class="t-label">Half Pay</div>
          </div>
          <div class="type-box tb-nopay">
            <div class="t-num"><?php echo $noPay; ?></div>
            <div class="t-label">No Pay</div>
          </div>
        </div>

        <!-- Latest class -->
        <div class="date-row">
          <div class="date-icon"><i class="fas fa-clock-rotate-left"></i></div>
          <span>Last class:
            <strong><?php echo $latest ? date("d M Y", strtotime($latest)) : "—"; ?></strong>
          </span>
        </div>

        <!-- Next class -->
        <div class="date-row">
          <div class="date-icon"><i class="fas fa-calendar-days"></i></div>
          <?php if ($next): ?>
            <span>Next class: <span class="next-badge"><?php echo date("d M Y", strtotime($next)); ?></span></span>
          <?php else: ?>
            <span class="no-next">No upcoming classes</span>
          <?php endif; ?>
        </div>

      </div>

    <?php endforeach; endif; ?>
  </div>
</div>

<script>
  function filterStudents(query) {
    const q = query.toLowerCase().trim();
    let visible = 0;

    document.querySelectorAll(".student-card").forEach(card => {
      const name = card.dataset.name || "";
      const show = !q || name.includes(q);
      card.style.display = show ? "" : "none";
      if (show) visible++;
    });

    let empty = document.getElementById("emptySearch");
    if (visible === 0 && q) {
      if (!empty) {
        empty = document.createElement("div");
        empty.id = "emptySearch";
        empty.className = "empty-state";
        empty.innerHTML = `
          <div class="empty-icon"><i class="fas fa-magnifying-glass"></i></div>
          <h5>No students found</h5>
          <p>No students match "<strong>${query}</strong>".</p>
        `;
        document.getElementById("studentsGrid").appendChild(empty);
      }
      empty.style.display = "";
    } else if (empty) {
      empty.style.display = "none";
    }
  }
</script>

</body>
</html>
