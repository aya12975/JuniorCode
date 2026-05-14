<?php
session_start();
require_once "db.php";
require_once 'notifications.php';
$__teacherId     = (int)($_SESSION['user_id'] ?? 0);
$__notifCount    = $__teacherId ? getUnreadCount($conn, $__teacherId) : 0;
$__notifications = $__teacherId ? getNotifications($conn, $__teacherId) : [];
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherId   = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "Teacher";

/* ── Ensure teacher_id column exists ── */
$colCheck = $conn->query("SHOW COLUMNS FROM classes LIKE 'teacher_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN teacher_id INT DEFAULT NULL");
}

/* ── Ensure session notes column exists ── */
$conn->query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS teacher_notes TEXT DEFAULT NULL");

/* ── Fetch yesterday / today / tomorrow classes for this teacher ── */
$classSessions = [];
$stmt = $conn->prepare("
    SELECT * FROM classes
    WHERE (teacher_id = ? OR LOWER(teacher_name) = LOWER(?))
      AND class_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                         AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ORDER BY class_date ASC, class_time ASC
");
if ($stmt) {
    $stmt->bind_param("is", $teacherId, $teacherName);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classSessions[] = $row;
    }
    $stmt->close();
}

$today         = date("Y-m-d");
$yesterday     = date("Y-m-d", strtotime("-1 day"));
$tomorrow      = date("Y-m-d", strtotime("+1 day"));
$total         = count($classSessions);
$yesterdayCount = 0;
$todayCount     = 0;
$tomorrowCount  = 0;

foreach ($classSessions as $c) {
    $d = $c["class_date"] ?? "";
    if ($d === $yesterday)   $yesterdayCount++;
    elseif ($d === $today)   $todayCount++;
    elseif ($d === $tomorrow) $tomorrowCount++;
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
      --primary:      #3e5077;
      --primary-dark: #152c6b;
      --secondary:    #143674;
      --dark:         #0f172a;
      --muted:        #64748b;
      --border:       #edf4ff;
      --soft:         #f8fbff;
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

    .teacher-name  { font-weight: 800; margin: 0; color: #fff; }
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

    .nav-link-custom:hover { background: rgba(255,255,255,0.09); color: #fff; }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff;
      box-shadow: 0 8px 20px rgba(30,50,100,0.35);
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
      border-radius: 22px;
      padding: 22px 26px;
      margin-bottom: 26px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 14px;
      color: #fff;
      box-shadow: 0 12px 28px rgba(37,99,235,0.3);
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
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 26px;
    }

    .stat-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 22px;
      padding: 20px;
      text-align: center;
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      display: block;
      height: 5px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      position: absolute;
      top: 0; left: 0; right: 0;
      border-radius: 22px 22px 0 0;
    }

    .stat-card .stat-num {
      font-size: 2rem;
      font-weight: 900;
      line-height: 1;
      margin-bottom: 6px;
    }

    .stat-card .stat-label {
      font-size: 0.84rem;
      color: var(--muted);
      font-weight: 600;
    }

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
    .filter-btn.active {
      background: var(--primary);
      border-color: var(--primary);
      color: #fff;
    }

    /* ── Class cards grid ── */
    .classes-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 18px;
    }

    .class-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 22px;
      padding: 22px;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      gap: 14px;
      transition: transform 0.2s, box-shadow 0.2s;
      position: relative;
      overflow: hidden;
    }
    .class-card::before {
      content: '';
      display: block;
      height: 5px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      position: absolute;
      top: 0; left: 0; right: 0;
      border-radius: 22px 22px 0 0;
    }

    .class-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(15,23,42,0.1);
    }

    .class-card.is-today {
      border-color: #2563eb;
      border-width: 2px;
    }

    .class-card.is-yesterday { opacity: 0.72; }
    .class-card.is-tomorrow  { border-color: #16a34a; border-width: 2px; }

    /* Today ribbon */
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

    /* Card header */
    .card-header-row {
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }

    .student-avatar {
      width: 46px; height: 46px;
      border-radius: 50%;
      background: linear-gradient(135deg, #dbeafe, #bfdbfe);
      color: #1d4ed8;
      font-weight: 900;
      font-size: 1.2rem;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .student-name {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--dark);
      margin: 0 0 4px;
    }

    /* Type badge */
    .type-badge {
      display: inline-block;
      border-radius: 999px;
      padding: 3px 10px;
      font-size: 0.75rem;
      font-weight: 700;
    }

    .t-paid         { background: #dcfce7; color: #166534; }
    .t-demo         { background: #fef3c7; color: #92400e; }
    .t-halfpay      { background: #e0e7ff; color: #3730a3; }
    .t-nopay        { background: #fee2e2; color: #991b1b; }
    .t-other        { background: #f1f5f9; color: #475569; }

    /* Info rows */
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

    /* Zoom button */
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

    .zoom-locked {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      color: #94a3b8; font-size: 0.88rem; font-weight: 700;
      background: #f8fafc; border: 1px solid #e2e8f0;
      border-radius: 14px; padding: 12px; width: 100%;
      margin-top: auto; box-sizing: border-box;
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

    /* ── Session notes ── */
    .notes-section {
      border-top: 1px solid #f1f5f9;
      padding-top: 12px;
      margin-top: 4px;
    }
    .notes-label {
      display: flex; align-items: center; gap: 6px;
      font-size: 0.8rem; font-weight: 800; color: #64748b;
      margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .notes-textarea {
      width: 100%; border: 1.5px solid #e2e8f0; border-radius: 12px;
      padding: 10px 12px; font-size: 0.85rem; color: #334155;
      background: #f8fafc; resize: none; outline: none;
      font-family: inherit; transition: border-color 0.2s, background 0.2s;
      min-height: 72px;
    }
    .notes-textarea:focus { border-color: var(--primary); background: #fff; }
    .notes-textarea::placeholder { color: #94a3b8; }
    .notes-save-row {
      display: flex; justify-content: flex-end; margin-top: 6px; gap: 8px; align-items: center;
    }
    .notes-saved-msg { font-size: 0.78rem; color: #16a34a; font-weight: 700; display:none; }
    .notes-save-btn {
      border: none; background: var(--primary); color: #fff;
      border-radius: 10px; padding: 6px 16px; font-size: 0.82rem;
      font-weight: 800; cursor: pointer; transition: background 0.2s;
    }
    .notes-save-btn:hover { background: var(--primary-dark); }
    .notes-save-btn:disabled { background: #94a3b8; cursor: default; }

    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
      grid-column: 1 / -1;
    }

    .empty-state .empty-icon { font-size: 3.5rem; margin-bottom: 14px; }
    .empty-state h5 { font-weight: 800; color: #334155; }
    .empty-state p  { font-size: 0.95rem; max-width: 340px; margin: 0 auto; }

    /* Responsive */
    @media (max-width: 991px) {
      .sidebar { position: static; width: 100%; height: auto; }
      .main { margin-left: 0; padding: 16px; }
      .stat-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 575px) {
      .stat-grid { grid-template-columns: repeat(2, 1fr); }
      .classes-grid { grid-template-columns: 1fr; }
      .topbar h1 { font-size: 1.4rem; }
    }
  
    /* ── Notification Bell ── */
    .notif-bell-wrap { position:relative; }
    .notif-bell-btn {
      width:100%; display:flex; align-items:center; gap:10px;
      background:rgba(255,255,255,0.08); border:none; color:rgba(255,255,255,0.85);
      border-radius:14px; padding:11px 14px; font-size:0.97rem; cursor:pointer;
      font-weight:700; transition:background 0.2s; position:relative;
    }
    .notif-bell-btn:hover { background:rgba(255,255,255,0.14); color:#fff; }
    .notif-badge {
      position:absolute; top:7px; right:10px;
      background:#ef4444; color:#fff; font-size:0.7rem; font-weight:900;
      border-radius:999px; padding:1px 6px; min-width:18px; text-align:center;
    }
    .notif-dropdown {
      display:none; position:absolute; left:calc(100% + 10px); top:0;
      width:320px; background:#fff; border-radius:18px;
      box-shadow:0 20px 50px rgba(0,0,0,0.18); border:1px solid #e2e8f0;
      z-index:9999; overflow:hidden;
    }
    .notif-dropdown.open { display:block; }
    .notif-header {
      display:flex; justify-content:space-between; align-items:center;
      padding:14px 18px; background:linear-gradient(135deg,#3e5077,#143674);
      color:#fff; font-weight:800; font-size:0.9rem;
    }
    .notif-mark-read {
      background:rgba(255,255,255,0.2); border:none; color:#fff;
      border-radius:8px; padding:4px 10px; font-size:0.75rem; font-weight:700; cursor:pointer;
    }
    .notif-mark-read:hover { background:rgba(255,255,255,0.3); }
    .notif-list { max-height:360px; overflow-y:auto; }
    .notif-item {
      display:flex; gap:12px; padding:13px 16px;
      border-bottom:1px solid #f1f5f9; transition:background 0.15s;
    }
    .notif-item:last-child { border-bottom:none; }
    .notif-item.unread { background:#f0f7ff; }
    .notif-item:hover { background:#f8fbff; }
    .notif-icon {
      width:36px; height:36px; border-radius:10px; flex-shrink:0;
      display:flex; align-items:center; justify-content:center; font-size:0.9rem;
    }
    .notif-icon.student { background:#dbeafe; color:#1d4ed8; }
    .notif-icon.info    { background:#f3e8ff; color:#7c3aed; }
    .notif-body { flex:1; min-width:0; }
    .notif-title { font-weight:800; font-size:0.84rem; color:#0f172a; }
    .notif-msg   { font-size:0.8rem; color:#475569; margin-top:2px; line-height:1.4; }
    .notif-time  { font-size:0.73rem; color:#94a3b8; margin-top:4px; }
    .notif-empty { padding:24px; text-align:center; color:#94a3b8; font-size:0.88rem; font-weight:700; }
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
    <a href="teacher_classes.php" class="nav-link-custom active">
      <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>My Classes</span>
    </a>
    <a href="teacher_schedule.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>My Schedule</span>
    </a>
    <a href="teacher_monthly_earnings.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>My Earnings</span>
    </a>
    <a href="teacher_students.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-user-graduate"></i></span><span>My Students</span>
    </a>
    <a href="teacher_assignments.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>Assignments</span>
    </a>
    <a href="teacher_courses.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span>
    </a>
    <a href="teacher_quizzes.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span>
    </a>
  </div>

      <!-- Notification Bell -->
  <div style="padding:0 16px 10px;">
    <div class="notif-bell-wrap" id="notifWrap">
      <button class="notif-bell-btn" id="notifBellBtn" onclick="toggleNotifDropdown()" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($__notifCount > 0): ?>
          <span class="notif-badge" id="notifBadge"><?= $__notifCount ?></span>
        <?php endif; ?>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
          <span><i class="fas fa-bell me-1"></i> Notifications</span>
          <?php if ($__notifCount > 0): ?>
            <button class="notif-mark-read" onclick="markAllRead()">Mark all read</button>
          <?php endif; ?>
        </div>
        <div class="notif-list" id="notifList">
          <?php if (empty($__notifications)): ?>
            <div class="notif-empty">No notifications yet.</div>
          <?php else: foreach ($__notifications as $__n): ?>
            <div class="notif-item <?= $__n['is_read'] ? '' : 'unread' ?>">
              <div class="notif-icon <?= $__n['type'] ?>">
                <?php if ($__n['type'] === 'student'): ?><i class="fas fa-user-plus"></i>
                <?php else: ?><i class="fas fa-bell"></i><?php endif; ?>
              </div>
              <div class="notif-body">
                <div class="notif-title"><?= htmlspecialchars($__n['title']) ?></div>
                <div class="notif-msg"><?= $__n['message'] ?></div>
                <div class="notif-time"><?= date('d M Y, h:i A', strtotime($__n['created_at'])) ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="sidebar-bottom">
    <a href="teacher_profile.php" class="nav-link-custom">
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

  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <!-- Topbar -->
  <div class="topbar">
    <div>
      <h1>My Classes</h1>
      <p>Yesterday, today and tomorrow's classes</p>
    </div>
    <div class="topbar-date">
      <?php echo date("l, d F Y"); ?>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-num c-gray"><?php echo $yesterdayCount; ?></div>
      <div class="stat-label">Yesterday</div>
    </div>
    <div class="stat-card">
      <div class="stat-num c-blue"><?php echo $todayCount; ?></div>
      <div class="stat-label">Today</div>
    </div>
    <div class="stat-card">
      <div class="stat-num c-green"><?php echo $tomorrowCount; ?></div>
      <div class="stat-label">Tomorrow</div>
    </div>
  </div>

  <!-- Filter tabs -->
  <div class="filter-bar">
    <button class="filter-btn" onclick="filterClasses('yesterday', this)">Yesterday (<?php echo $yesterdayCount; ?>)</button>
    <button class="filter-btn active" onclick="filterClasses('today', this)">Today (<?php echo $todayCount; ?>)</button>
    <button class="filter-btn" onclick="filterClasses('tomorrow', this)">Tomorrow (<?php echo $tomorrowCount; ?>)</button>
  </div>

  <!-- Classes grid -->
  <div class="classes-grid" id="classesGrid">

    <?php if (empty($classSessions)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-book-open"></i></div>
        <h5>No classes yet</h5>
        <p>Once the admin assigns a class to you it will appear here with all the details and your Zoom link.</p>
      </div>

    <?php else: foreach ($classSessions as $c):
        $cDate    = $c["class_date"] ?? "";
        $cTime    = $c["class_time"] ?? "";
        $cType    = $c["type"] ?? "";
        $cDetails = $c["details"] ?? "";
        $cZoom    = $c["zoom_link"] ?? "";
        $cStudent = $c["student_name"] ?? "";

        if ($cDate === $yesterday)     $when = "yesterday";
        elseif ($cDate === $today)    $when = "today";
        else                          $when = "tomorrow";

        $t = strtolower(trim($cType));
        if ($t === "paid")                   $tClass = "t-paid";
        elseif ($t === "demo")               $tClass = "t-demo";
        elseif (strpos($t,"demo") !== false) $tClass = "t-demo";
        elseif ($t === "half pay")           $tClass = "t-halfpay";
        elseif ($t === "no pay")             $tClass = "t-nopay";
        else                                 $tClass = "t-other";
    ?>

      <div class="class-card <?php
            if ($when === 'today')     echo 'is-today';
            elseif ($when === 'yesterday') echo 'is-yesterday';
            elseif ($when === 'tomorrow')  echo 'is-tomorrow';
          ?>" data-when="<?php echo $when; ?>">

        <?php if ($when === "today"): ?>
          <div class="today-ribbon">TODAY</div>
        <?php elseif ($when === "yesterday"): ?>
          <div class="today-ribbon" style="background:#64748b;">YESTERDAY</div>
        <?php elseif ($when === "tomorrow"): ?>
          <div class="today-ribbon" style="background:#16a34a;">TOMORROW</div>
        <?php endif; ?>

        <!-- Student header -->
        <div class="card-header-row">
          <div class="student-avatar">
            <?php echo strtoupper(substr($cStudent, 0, 1)); ?>
          </div>
          <div>
            <p class="student-name"><?php echo htmlspecialchars($cStudent); ?></p>
            <span class="type-badge <?php echo $tClass; ?>">
              <?php echo htmlspecialchars($cType); ?>
            </span>
          </div>
        </div>

        <!-- Date & Time -->
        <div class="info-row">
          <div class="info-icon"><i class="fas fa-calendar-days"></i></div>
          <span><?php echo date("l, d F Y", strtotime($cDate)); ?></span>
        </div>
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

        <!-- Zoom slot (timer-gated) -->
        <div style="margin-top:auto;">
          <?php if (!empty($cZoom)): ?>
            <span class="zoom-slot"
              data-url="<?= htmlspecialchars($cZoom) ?>"
              data-date="<?= $cDate ?>"
              data-time="<?= $cTime ?>"></span>
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

        <!-- Session notes -->
        <div class="notes-section">
          <div class="notes-label">
            <i class="fas fa-pen-to-square"></i> Session Notes
          </div>
          <textarea
            class="notes-textarea"
            data-class-id="<?= $c['id'] ?>"
            placeholder="What did you cover this session? Topics, homework, progress…"
          ><?= htmlspecialchars($c['teacher_notes'] ?? '') ?></textarea>
          <div class="notes-save-row">
            <span class="notes-saved-msg"><i class="fas fa-check"></i> Saved</span>
            <button class="notes-save-btn" onclick="saveNotes(this)">Save</button>
          </div>
        </div>

      </div>

    <?php endforeach; endif; ?>
  </div>
</div>

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
        a.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11l-4 4z"/></svg> Start Class on Zoom';
        slot.innerHTML = ''; slot.appendChild(a);
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

  function filterClasses(filter, btn) {
    document.querySelectorAll(".filter-btn").forEach(b => b.classList.remove("active"));
    if (btn) btn.classList.add("active");

    const cards = document.querySelectorAll(".class-card");
    let visible = 0;

    cards.forEach(card => {
      const show = card.dataset.when === filter;
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
          <h5>No classes for this day</h5>
          <p>No classes scheduled for this period.</p>
        `;
        document.getElementById("classesGrid").appendChild(empty);
      }
      empty.style.display = "";
    } else if (empty) {
      empty.style.display = "none";
    }
  }

  // Default: show today's classes on load
  (function() {
    var todayBtn = document.querySelector('.filter-btn.active');
    filterClasses('today', todayBtn);
  })();

  function saveNotes(btn) {
    var section  = btn.closest('.notes-section');
    var textarea = section.querySelector('.notes-textarea');
    var savedMsg = section.querySelector('.notes-saved-msg');
    var classId  = textarea.dataset.classId;
    var notes    = textarea.value;

    btn.disabled = true;
    btn.textContent = 'Saving…';

    var body = new FormData();
    body.append('class_id', classId);
    body.append('notes', notes);

    fetch('save_class_notes.php', { method: 'POST', body: body })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        btn.textContent = 'Save';
        btn.disabled = false;
        if (data.ok) {
          savedMsg.style.display = 'inline';
          setTimeout(function() { savedMsg.style.display = 'none'; }, 2500);
        }
      })
      .catch(function() {
        btn.textContent = 'Save';
        btn.disabled = false;
      });
  }
</script>

<script src="logout-modal.js"></script>

<script>
function toggleNotifDropdown() {
  var dd = document.getElementById('notifDropdown');
  dd.classList.toggle('open');
}
document.addEventListener('click', function(e) {
  var wrap = document.getElementById('notifWrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('notifDropdown').classList.remove('open');
  }
});
function markAllRead() {
  fetch('mark_notifications_read.php', { method:'POST' })
    .then(function() {
      document.querySelectorAll('.notif-item.unread').forEach(function(el) { el.classList.remove('unread'); });
      var badge = document.getElementById('notifBadge');
      if (badge) badge.remove();
      document.querySelector('.notif-mark-read') && document.querySelector('.notif-mark-read').remove();
    });
}
</script>
</body>
</html>

