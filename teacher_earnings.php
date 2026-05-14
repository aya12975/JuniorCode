<?php
ob_start();
session_start();
require_once "db.php";
require_once 'notifications.php';
$__teacherId     = (int)($_SESSION['user_id'] ?? 0);
$__notifCount    = $__teacherId ? getUnreadCount($conn, $__teacherId) : 0;
$__notifications = $__teacherId ? getNotifications($conn, $__teacherId) : [];

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

/* ── Ensure required columns exist ── */
foreach ([
    "teacher_id   INT DEFAULT NULL",
    "class_id     INT DEFAULT NULL",
    "teacher_name VARCHAR(255) DEFAULT NULL",
    "lesson_title VARCHAR(500) DEFAULT NULL",
    "lesson_date  DATE DEFAULT NULL",
    "notes        TEXT DEFAULT NULL",
    "amount       DECIMAL(10,2) DEFAULT 0"
] as $col) {
    $colName = explode(" ", $col)[0];
    $chk = $conn->query("SHOW COLUMNS FROM teacher_earnings LIKE '$colName'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE teacher_earnings ADD COLUMN $col");
    }
}

/* ── Delete earning ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_earning") {
    $delId = (int)($_POST["earning_id"] ?? 0);
    $month = preg_match('/^\d{4}-\d{2}$/', $_POST["month"] ?? "") ? $_POST["month"] : date("Y-m");
    if ($delId > 0) {
        $d = $conn->prepare("DELETE FROM teacher_earnings WHERE id = ?");
        $d->bind_param("i", $delId);
        $d->execute();
        $d->close();
    }
    header("Location: teacher_earnings.php?month=" . urlencode($month) . "&deleted=1");
    exit();
}

/* ── AJAX: edit earning ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "edit_earning") {
    ob_end_clean();
    header("Content-Type: application/json");
    $editId      = (int)($_POST["earning_id"]  ?? 0);
    $teacherName = trim($_POST["teacher_name"] ?? "");
    $lessonTitle = trim($_POST["lesson_title"] ?? "");
    $amount      = (float)($_POST["amount"]    ?? 0);
    $lessonDate  = trim($_POST["lesson_date"]  ?? "");
    $notes       = trim($_POST["notes"]        ?? "");
    if ($editId <= 0 || $amount <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid data"]);
        exit();
    }
    $upd = $conn->prepare("UPDATE teacher_earnings SET teacher_name=?, lesson_title=?, amount=?, lesson_date=?, notes=? WHERE id=?");
    if (!$upd) { echo json_encode(["success" => false, "message" => $conn->error]); exit(); }
    $upd->bind_param("ssdssi", $teacherName, $lessonTitle, $amount, $lessonDate, $notes, $editId);
    if (!$upd->execute()) { echo json_encode(["success" => false, "message" => $upd->error]); exit(); }
    $upd->close();
    $lessonMonth = strlen($lessonDate) >= 7 ? substr($lessonDate, 0, 7) : date('Y-m');
    echo json_encode(["success" => true, "month" => $lessonMonth]);
    exit();
}

/* ── AJAX: add earning from a class ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_earning") {
    ob_end_clean();
    header("Content-Type: application/json");
    $classId    = (int)($_POST["class_id"]    ?? 0);
    $teacherId  = (int)($_POST["teacher_id"]  ?? 0);
    $teacherName = trim($_POST["teacher_name"] ?? "");
    $lessonTitle = trim($_POST["lesson_title"] ?? "");
    $lessonDate  = trim($_POST["lesson_date"]  ?? "");
    $amount      = (float)($_POST["amount"]    ?? 0);
    $notes       = trim($_POST["notes"]        ?? "");

    if ($amount <= 0) {
        echo json_encode(["success" => false, "message" => "Amount must be greater than 0"]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO teacher_earnings (teacher_id, teacher_name, lesson_title, amount, lesson_date, notes, class_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) { echo json_encode(["success" => false, "message" => $conn->error]); exit(); }
    $stmt->bind_param("issdssi", $teacherId, $teacherName, $lessonTitle, $amount, $lessonDate, $notes, $classId);
    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => $stmt->error]);
        exit();
    }
    $newId = $conn->insert_id;
    $stmt->close();

    // Return the lesson month so JS can redirect to it
    $lessonMonth = strlen($lessonDate) >= 7 ? substr($lessonDate, 0, 7) : date('Y-m');
    echo json_encode(["success" => true, "id" => $newId, "amount" => number_format($amount, 2), "month" => $lessonMonth]);
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName = $_SESSION["username"] ?? "Admin";

/* ── Month switching ── */
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) $selectedMonth = date('Y-m');
[$selYear, $selMonthNum] = array_map('intval', explode('-', $selectedMonth));
$prevMonth  = date('Y-m', mktime(0,0,0,$selMonthNum-1,1,$selYear));
$nextMonth  = date('Y-m', mktime(0,0,0,$selMonthNum+1,1,$selYear));
$monthLabel = date('F Y',  mktime(0,0,0,$selMonthNum,  1,$selYear));
$isFuture   = $selectedMonth > date('Y-m');

$earnings = [];
$stmtE = $conn->prepare("SELECT * FROM teacher_earnings WHERE YEAR(lesson_date)=? AND MONTH(lesson_date)=? ORDER BY lesson_date DESC, id DESC");
$stmtE->bind_param("ii", $selYear, $selMonthNum);
$stmtE->execute();
$resE = $stmtE->get_result();
if ($resE) while ($row = $resE->fetch_assoc()) $earnings[] = $row;
$stmtE->close();

/* per-teacher totals */
$teacherTotals = [];
$grandTotal = 0.0;
foreach ($earnings as $e) {
    $n = trim($e['teacher_name']) ?: 'Unknown';
    $teacherTotals[$n] = ($teacherTotals[$n] ?? 0.0) + (float)$e['amount'];
    $grandTotal += (float)$e['amount'];
}
arsort($teacherTotals);

/* ── Load all classes for the "Add Earning" panel ── */
$classes = [];
$result2 = $conn->query("
    SELECT c.id, c.teacher_id, c.teacher_name, c.student_name, c.class_date, c.class_time, c.type
    FROM classes c
    WHERE NOT EXISTS (SELECT 1 FROM teacher_earnings te WHERE te.class_id = c.id)
    ORDER BY c.class_date DESC, c.class_time ASC
");
if ($result2) {
    while ($row = $result2->fetch_assoc()) $classes[] = $row;
}

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Earnings | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #3e5077;
      --secondary: #143674;
      --dark: #0f172a;
      --muted: #64748b;
      --soft: #eff6ff;
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
      flex-shrink: 0;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow: hidden;
      display: flex; flex-direction: column;
    }
    .sidebar-bottom { padding: 16px 18px; border-top: 1px solid rgba(255,255,255,0.1); }

    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow: hidden; }

    .sidebar-top-area { padding: 0 18px 18px; flex: 1; overflow-y: auto; }
    .brand-box { display: flex; align-items: center; gap: 12px; padding: 0 4px 22px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }

    .logo-img {
      width: 55px;
      height: 55px;
      object-fit: contain;
      border-radius: 12px;
      background: none;
      flex-shrink: 0;
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

    .topbar,
    .panel-card {
      background: white;
      border: 1px solid #edf4ff;
      border-radius: 22px;
      box-shadow: var(--shadow);
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px;
      padding: 18px 20px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
    }

    .topbar h1 {
      font-size: 1.8rem;
      font-weight: 900;
      margin: 0;
      color: white;
    }

    .topbar p {
      margin: 4px 0 0;
      color: rgba(255,255,255,0.85);
    }

    .admin-badge {
      background: rgba(255,255,255,0.15);
      color: white;
      border-radius: 12px; border: 1px solid rgba(255,255,255,0.2);
      padding: 10px 18px;
      font-weight: 800;
    }

    .panel-card {
      padding: 22px;
      border-top: 3px solid var(--primary);
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 18px;
      flex-wrap: wrap;
    }

    .panel-title {
      font-size: 1.2rem;
      font-weight: 900;
      color: var(--primary);
      margin: 0;
    }

    .btn-main {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border: none;
      color: white;
      font-weight: 800;
      border-radius: 14px;
      padding: 10px 16px;
      text-decoration: none;
      display: inline-block;
    }

    .btn-main:hover {
      color: white;
      opacity: 0.95;
    }

    .table-responsive {
      border-radius: 18px;
      overflow: hidden;
    }

    .table {
      margin-bottom: 0;
      background: white;
    }

    .table thead th {
      background: #f8fbff;
      font-weight: 800;
      color: var(--dark);
      border-bottom: 1px solid #e6eefb;
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

    .action-btn {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 7px 13px; border-radius: 10px; font-weight: 800;
      font-size: 0.85rem; text-decoration: none; border: none; cursor: pointer;
      transition: background 0.2s;
    }

    .edit-btn {
      background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;
    }
    .edit-btn:hover { background: #dbeafe; color: #1d4ed8; }

    .delete-btn {
      background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;
    }
    .delete-btn:hover { background: #fee2e2; color: #b91c1c; }

    .badge-paid  { background:#dcfce7;color:#166534;padding:5px 11px;border-radius:999px;font-size:0.8rem;font-weight:800; }
    .badge-demo  { background:#fef3c7;color:#92400e;padding:5px 11px;border-radius:999px;font-size:0.8rem;font-weight:800; }
    .badge-other { background:#e0e7ff;color:#3730a3;padding:5px 11px;border-radius:999px;font-size:0.8rem;font-weight:800; }

    .modal-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(15,23,42,0.55);
      backdrop-filter: blur(4px);
      z-index: 9000;
      align-items: center;
      justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: white;
      border-radius: 24px;
      padding: 32px;
      width: 100%;
      max-width: 460px;
      box-shadow: 0 32px 80px rgba(15,23,42,0.22);
    }
    .modal-title { font-size: 1.2rem; font-weight: 900; color: var(--primary); margin: 0 0 20px; }
    .info-row { background: #f8fbff; border-radius: 12px; padding: 10px 14px; margin-bottom: 10px; font-size: 0.9rem; }
    .info-row strong { color: var(--primary); }
    .modal-input { border-radius: 14px; padding: 12px 14px; border: 1px solid #dbe4f0; width: 100%; font-size: 1rem; margin-bottom: 12px; box-sizing: border-box; }
    .modal-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(62,80,119,0.12); }
    .modal-btns { display: flex; gap: 10px; margin-top: 4px; }
    .btn-cancel { background: #f1f5f9; color: #334155; border: none; border-radius: 14px; padding: 11px 20px; font-weight: 800; cursor: pointer; }
    .btn-cancel:hover { background: #e2e8f0; }

    .empty-box {
      text-align: center;
      padding: 26px 18px;
      border-radius: 18px;
      background: #f8fbff;
      color: var(--muted);
      border: 1px dashed #d9e9ff;
      font-weight: 700;
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

      .main-content {
        padding: 18px;
      }

      .topbar {
        flex-direction: column;
        align-items: flex-start;
      }
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
<script>(function(){var t=localStorage.getItem("jc-theme");if(t==="dark")document.documentElement.classList.add("dark");})();</script><style>html.dark body{background:#0f172a!important;color:#e2e8f0!important}html.dark .sidebar{background:linear-gradient(180deg,#020817 0%,#0c1226 100%)!important}html.dark .panel-card,html.dark .stat-card{background:#1e293b!important;border-color:#334155!important;color:#e2e8f0}html.dark .stat-label{color:#94a3b8!important}html.dark .stat-value{color:#f1f5f9!important}html.dark .form-control,html.dark .form-select,html.dark textarea{background:#1e293b!important;border-color:#475569!important;color:#e2e8f0!important}html.dark .topbar{background:linear-gradient(135deg,#1e3a6e 0%,#0f2456 100%)!important}html.dark .table thead th{background:#1e293b!important;color:#94a3b8!important;border-color:#334155!important}html.dark .table td{color:#cbd5e1!important;border-color:#334155!important}html.dark .panel-title{color:#f1f5f9!important}</style>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="sidebar-top-area">
      <div class="brand-box">
        <img src="images/robot2.png.png" class="logo-img" alt="Logo">
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

        <a href="manage_users.php" class="nav-link-custom <?php echo isActive('manage_users.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-users"></i></span>
          <span>Manage Users</span>
        </a>

        <a href="admin_teacher_students.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span>
        </a>

          <a href="admin_enrollments.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span>
          </a>

        <a href="manage_classes.php" class="nav-link-custom <?php echo isActive('manage_classes.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-book"></i></span>
          <span>Manage Classes</span>
        </a>

        <a href="teacher_earnings.php" class="nav-link-custom <?php echo isActive('teacher_earnings.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
          <span>Teacher Earnings</span>
        </a>

        <a href="available_slots.php" class="nav-link-custom <?php echo isActive('available_slots.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
          <span>Available Slots</span>
        </a>

        <a href="courses.php" class="nav-link-custom <?php echo isActive('courses.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
          <span>Courses</span>
        </a>

        <a href="reports.php" class="nav-link-custom <?php echo isActive('reports.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
          <span>Reports</span>
        </a>
        <a href="admin_certificates.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-award"></i></span>
          <span>Certificates</span>
        </a>
<a href="admin_email_notifications.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-envelope"></i></span>
          <span>Email Notifications</span>
        </a>

      </div>
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
        <a href="settings.php" class="nav-link-custom <?php echo isActive('settings.php', $currentPage); ?>">
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
          <h1>Teacher Earnings</h1>
          <p>Admin can view and manage all teacher earning records here.</p>
        </div>
        <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?php echo htmlspecialchars($adminName); ?> &nbsp;·&nbsp; <?php echo date("d M Y"); ?></div>
      </div>

      <?php if (isset($_GET["updated"])): ?>
        <div class="alert alert-success">Earning updated successfully.</div>
      <?php endif; ?>
      <?php if (isset($_GET["deleted"])): ?>
        <div class="alert alert-success">Earning deleted successfully.</div>
      <?php endif; ?>
      <?php if (isset($_GET["added"])): ?>
        <div class="alert alert-success">Earning added successfully.</div>
      <?php endif; ?>

      <!-- Month switcher -->
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <a href="teacher_earnings.php?month=<?= urlencode($prevMonth) ?>" style="display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;background:#fff;border:1px solid #dbeafe;color:var(--primary);text-decoration:none;font-size:1.1rem;transition:background .2s;" title="Previous month">
            <i class="fas fa-chevron-left"></i>
          </a>
          <div style="font-size:1.2rem;font-weight:900;color:var(--dark);min-width:160px;text-align:center;"><?= htmlspecialchars($monthLabel) ?></div>
          <a href="teacher_earnings.php?month=<?= urlencode($nextMonth) ?>" style="display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;background:#fff;border:1px solid #dbeafe;color:var(--primary);text-decoration:none;font-size:1.1rem;transition:background .2s;<?= $isFuture ? 'opacity:.35;pointer-events:none;' : '' ?>" title="Next month">
            <i class="fas fa-chevron-right"></i>
          </a>
          <?php if ($selectedMonth !== date('Y-m')): ?>
            <a href="teacher_earnings.php" style="font-size:0.82rem;font-weight:700;color:var(--primary);background:#eff6ff;border-radius:999px;padding:5px 12px;text-decoration:none;">Today</a>
          <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <?php if (!empty($teacherTotals)): ?>
            <?php foreach ($teacherTotals as $tname => $tamt): ?>
              <div style="background:#fff;border:1px solid #dbeafe;border-radius:12px;padding:8px 14px;font-size:0.85rem;">
                <span style="color:var(--muted);font-weight:700;"><?= htmlspecialchars($tname) ?></span>
                <span style="color:#065f46;font-weight:900;margin-left:6px;">$<?= number_format($tamt, 2) ?></span>
              </div>
            <?php endforeach; ?>
            <div style="background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:12px;padding:8px 16px;font-size:0.9rem;color:#fff;font-weight:900;">
              Total: $<?= number_format($grandTotal, 2) ?>
            </div>
          <?php else: ?>
            <div style="color:var(--muted);font-size:0.9rem;font-weight:700;">No earnings for this month.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Classes panel: add earning from a class -->
      <section class="panel-card" style="margin-bottom:24px;">
        <div class="panel-header">
          <h2 class="panel-title">Add Earning from Class</h2>
          <span class="text-muted" style="font-size:0.9rem"><?php echo count($classes); ?> classes</span>
        </div>
        <?php if (!empty($classes)): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Teacher</th>
                  <th>Student</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Type</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($classes as $cls): ?>
                  <tr id="class-row-<?php echo $cls['id']; ?>">
                    <td><strong><?php echo htmlspecialchars($cls['teacher_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($cls['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($cls['class_date']); ?></td>
                    <td><?php echo htmlspecialchars($cls['class_time']); ?></td>
                    <td>
                      <?php
                        $t = strtolower($cls['type']);
                        $bc = $t === 'paid' ? 'badge-paid' : ($t === 'demo' ? 'badge-demo' : 'badge-other');
                        echo '<span class="' . $bc . '">' . htmlspecialchars($cls['type']) . '</span>';
                      ?>
                    </td>
                    <td>
                      <button class="btn-main" style="padding:7px 14px;font-size:0.85rem"
                        onclick="openEarningModal(
                          <?php echo $cls['id']; ?>,
                          <?php echo (int)$cls['teacher_id']; ?>,
                          '<?php echo addslashes($cls['teacher_name']); ?>',
                          '<?php echo addslashes($cls['student_name']); ?>',
                          '<?php echo $cls['class_date']; ?>',
                          '<?php echo addslashes($cls['type']); ?>'
                        )">
                        <i class="fas fa-plus"></i> Add Earning
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-box">No classes found. Add classes in Manage Classes first.</div>
        <?php endif; ?>
      </section>

      <section class="panel-card">
        <div class="panel-header">
          <h2 class="panel-title">Earnings — <?= htmlspecialchars($monthLabel) ?></h2>
          <span class="text-muted" style="font-size:0.9rem"><?= count($earnings) ?> record<?= count($earnings) !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (!empty($earnings)): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Teacher Name</th>
                  <th>Lesson Title</th>
                  <th>Amount</th>
                  <th>Lesson Date</th>
                  <th>Notes</th>
                  <th style="width: 180px;">Actions</th>
                </tr>
              </thead>
              <tbody id="earnings-tbody">
                <?php foreach ($earnings as $earning): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($earning["id"]); ?></td>
                    <td><?php echo htmlspecialchars($earning["teacher_name"]); ?></td>
                    <td><?php echo htmlspecialchars($earning["lesson_title"]); ?></td>
                    <td>
                      <span class="money-badge">
                        $<?php echo number_format((float)$earning["amount"], 2); ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars($earning["lesson_date"]); ?></td>
                    <td><?php echo htmlspecialchars($earning["notes"]); ?></td>
                    <td style="white-space:nowrap">
                      <button class="action-btn edit-btn" onclick="openEditModal(
                        <?= $earning['id'] ?>,
                        <?= htmlspecialchars(json_encode($earning['teacher_name']), ENT_QUOTES) ?>,
                        <?= htmlspecialchars(json_encode($earning['lesson_title']), ENT_QUOTES) ?>,
                        <?= (float)$earning['amount'] ?>,
                        <?= htmlspecialchars(json_encode($earning['lesson_date']), ENT_QUOTES) ?>,
                        <?= htmlspecialchars(json_encode($earning['notes'] ?? ''), ENT_QUOTES) ?>
                      )">
                        <i class="fas fa-pen"></i> Edit
                      </button>
                      <button class="action-btn delete-btn" onclick="openDelModal(<?= $earning['id'] ?>, <?= htmlspecialchars(json_encode($earning['teacher_name']), ENT_QUOTES) ?>, '<?= urlencode($selectedMonth) ?>')">
                        <i class="fas fa-trash"></i> Delete
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-box">No earning records for <?= htmlspecialchars($monthLabel) ?>.</div>
        <?php endif; ?>
      </section>
    </main>
  </div>
<!-- Add Earning Modal -->
<div class="modal-overlay" id="earningModal" onclick="if(event.target===this)closeEarningModal()">
  <div class="modal-box">
    <div class="modal-title"><i class="fas fa-dollar-sign"></i> Add Earning</div>
    <div class="info-row"><strong>Teacher:</strong> <span id="m-teacher"></span></div>
    <div class="info-row"><strong>Student:</strong> <span id="m-student"></span></div>
    <div class="info-row"><strong>Date:</strong> <span id="m-date"></span> &nbsp;|&nbsp; <strong>Type:</strong> <span id="m-type"></span></div>
    <input type="hidden" id="m-class-id">
    <input type="hidden" id="m-teacher-id">
    <label style="font-weight:800;color:#334155;display:block;margin-bottom:6px">Amount ($) <span style="color:#ef4444">*</span></label>
    <input type="number" id="m-amount" class="modal-input" step="0.01" min="0.01" placeholder="e.g. 25.00">
    <label style="font-weight:800;color:#334155;display:block;margin-bottom:6px">Notes <span style="font-weight:400;color:#64748b">(optional)</span></label>
    <input type="text" id="m-notes" class="modal-input" placeholder="e.g. Paid class - Python basics">
    <div id="m-error" style="color:#ef4444;font-size:0.88rem;margin-bottom:8px;display:none"></div>
    <div class="modal-btns">
      <button class="btn-main" id="m-save-btn" onclick="saveEarning()"><i class="fas fa-check"></i> Save Earning</button>
      <button class="btn-cancel" onclick="closeEarningModal()">Cancel</button>
    </div>
  </div>
</div>

<!-- Edit Earning Modal -->
<div class="modal-overlay" id="editModal" onclick="if(event.target===this)closeEditModal()">
  <div class="modal-box">
    <div class="modal-title"><i class="fas fa-pen me-2"></i>Edit Earning</div>
    <input type="hidden" id="edit-earning-id">
    <label style="font-weight:800;color:#334155;display:block;margin-bottom:6px">Teacher Name</label>
    <input type="text" id="edit-teacher-name" class="modal-input" placeholder="Teacher name">
    <label style="font-weight:800;color:#334155;display:block;margin-bottom:6px">Lesson Title</label>
    <input type="text" id="edit-lesson-title" class="modal-input" placeholder="Lesson title">
    <div style="display:flex;gap:12px;margin-bottom:0">
      <div style="flex:1">
        <label style="font-weight:800;color:#334155;display:block;margin-bottom:6px">Amount ($) <span style="color:#ef4444">*</span></label>
        <input type="number" id="edit-amount" class="modal-input" step="0.01" min="0.01" placeholder="e.g. 25.00">
      </div>
      <div style="flex:1">
        <label style="font-weight:800;color:#334155;display:block;margin-bottom:6px">Lesson Date</label>
        <input type="date" id="edit-lesson-date" class="modal-input">
      </div>
    </div>
    <label style="font-weight:800;color:#334155;display:block;margin-bottom:6px">Notes <span style="font-weight:400;color:#64748b">(optional)</span></label>
    <input type="text" id="edit-notes" class="modal-input" placeholder="Optional notes">
    <div id="edit-error" style="color:#ef4444;font-size:0.88rem;margin-bottom:8px;display:none"></div>
    <div class="modal-btns">
      <button class="btn-main" id="edit-save-btn" onclick="saveEditEarning()"><i class="fas fa-check"></i> Save Changes</button>
      <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
    </div>
  </div>
</div>

<!-- Delete Earning Modal -->
<div class="modal-overlay" id="delModal" onclick="if(event.target===this)closeDelModal()">
  <div class="modal-box" style="max-width:420px;">
    <div class="modal-title" style="color:#dc2626;"><i class="fas fa-triangle-exclamation me-2"></i>Delete Earning</div>
    <p style="color:#64748b;font-size:0.95rem;margin-bottom:16px;">You are about to permanently delete this earning record.</p>
    <div class="info-row"><strong>Teacher:</strong> <span id="del-teacher"></span></div>
    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:12px 16px;color:#9a3412;font-weight:700;font-size:0.88rem;margin:14px 0;">
      <i class="fas fa-exclamation-circle me-1"></i> This action cannot be undone.
    </div>
    <form id="del-form" method="POST" action="teacher_earnings.php">
      <input type="hidden" name="action" value="delete_earning">
      <input type="hidden" name="earning_id" id="del-earning-id">
      <input type="hidden" name="month" id="del-month">
      <div class="modal-btns">
        <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:11px 22px;background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff;border:none;border-radius:14px;font-weight:800;font-size:0.95rem;cursor:pointer;box-shadow:0 4px 14px rgba(220,38,38,0.28);">
          <i class="fas fa-trash"></i> Yes, Delete
        </button>
        <button type="button" class="btn-cancel" onclick="closeDelModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, teacherName, lessonTitle, amount, lessonDate, notes) {
  document.getElementById('edit-earning-id').value      = id;
  document.getElementById('edit-teacher-name').value   = teacherName;
  document.getElementById('edit-lesson-title').value   = lessonTitle;
  document.getElementById('edit-amount').value         = amount;
  document.getElementById('edit-lesson-date').value    = lessonDate;
  document.getElementById('edit-notes').value          = notes;
  document.getElementById('edit-error').style.display  = 'none';
  document.getElementById('editModal').classList.add('open');
  setTimeout(() => document.getElementById('edit-amount').focus(), 100);
}
function closeEditModal() {
  document.getElementById('editModal').classList.remove('open');
}
function saveEditEarning() {
  const id          = document.getElementById('edit-earning-id').value;
  const teacherName = document.getElementById('edit-teacher-name').value.trim();
  const lessonTitle = document.getElementById('edit-lesson-title').value.trim();
  const amount      = parseFloat(document.getElementById('edit-amount').value);
  const lessonDate  = document.getElementById('edit-lesson-date').value;
  const notes       = document.getElementById('edit-notes').value.trim();
  const errEl       = document.getElementById('edit-error');

  if (!amount || amount <= 0) {
    errEl.textContent = 'Please enter a valid amount.';
    errEl.style.display = 'block';
    return;
  }

  const btn = document.getElementById('edit-save-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  fetch('teacher_earnings.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      action: 'edit_earning',
      earning_id: id,
      teacher_name: teacherName,
      lesson_title: lessonTitle,
      amount: amount,
      lesson_date: lessonDate,
      notes: notes
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeEditModal();
      window.location.href = 'teacher_earnings.php?month=' + encodeURIComponent(data.month) + '&updated=1';
    } else {
      errEl.textContent = data.message || 'Error saving changes.';
      errEl.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check"></i> Save Changes';
    }
  })
  .catch(() => {
    errEl.textContent = 'Request failed. Please try again.';
    errEl.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Save Changes';
  });
}

function openDelModal(id, teacherName, month) {
  document.getElementById('del-earning-id').value = id;
  document.getElementById('del-month').value = decodeURIComponent(month);
  document.getElementById('del-teacher').textContent = teacherName;
  document.getElementById('delModal').classList.add('open');
}
function closeDelModal() {
  document.getElementById('delModal').classList.remove('open');
}
</script>

<script>
function openEarningModal(classId, teacherId, teacherName, studentName, classDate, classType) {
  document.getElementById('m-class-id').value   = classId;
  document.getElementById('m-teacher-id').value = teacherId;
  document.getElementById('m-teacher').textContent = teacherName;
  document.getElementById('m-student').textContent = studentName;
  document.getElementById('m-date').textContent    = classDate;
  document.getElementById('m-type').textContent    = classType;
  document.getElementById('m-amount').value = '';
  document.getElementById('m-notes').value  = '';
  document.getElementById('m-error').style.display = 'none';
  document.getElementById('earningModal').classList.add('open');
  setTimeout(() => document.getElementById('m-amount').focus(), 100);
}

function closeEarningModal() {
  document.getElementById('earningModal').classList.remove('open');
}

function saveEarning() {
  const classId    = document.getElementById('m-class-id').value;
  const teacherId  = document.getElementById('m-teacher-id').value;
  const teacherName = document.getElementById('m-teacher').textContent;
  const amount     = parseFloat(document.getElementById('m-amount').value);
  const notes      = document.getElementById('m-notes').value;
  const date       = document.getElementById('m-date').textContent;
  const type       = document.getElementById('m-type').textContent;
  const errEl      = document.getElementById('m-error');

  if (!amount || amount <= 0) {
    errEl.textContent = 'Please enter a valid amount.';
    errEl.style.display = 'block';
    return;
  }

  const btn = document.getElementById('m-save-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  const lessonTitle = teacherName + ' — ' + type + ' (' + date + ')';

  fetch('teacher_earnings.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      action: 'add_earning',
      class_id: classId,
      teacher_id: teacherId,
      teacher_name: teacherName,
      lesson_title: lessonTitle,
      lesson_date: date,
      amount: amount,
      notes: notes
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeEarningModal();
      // Redirect to the month of the lesson so the new earning is visible
      window.location.href = 'teacher_earnings.php?month=' + encodeURIComponent(data.month);
    } else {
      errEl.textContent = data.message || 'Error saving earning.';
      errEl.style.display = 'block';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Save Earning';
  })
  .catch(() => {
    errEl.textContent = 'Request failed. Please try again.';
    errEl.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Save Earning';
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