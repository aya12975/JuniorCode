<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName   = $_SESSION["username"] ?? "Admin";

/* ── Handle Add Student POST ── */
$successMsg = "";
$errorMsg   = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_student"])) {
    $teacherId   = (int)($_POST["teacher_id"]   ?? 0);
    $teacherName = trim($_POST["teacher_name"]  ?? "");
    $studentName = trim($_POST["student_name"]  ?? "");
    $classDate   = date("Y-m-d");
    $classTime   = date("H:i:s");
    $type        = trim($_POST["type"]          ?? "Paid");
    $details     = trim($_POST["details"]       ?? "");

    if ($studentName !== "" && $teacherName !== "") {
        $ins = $conn->prepare("INSERT INTO classes (teacher_id, teacher_name, student_name, class_date, class_time, type, details, zoom_link) VALUES (?,?,?,?,?,?,?,'')");
        if ($ins) {
            $ins->bind_param("issssss", $teacherId, $teacherName, $studentName, $classDate, $classTime, $type, $details);
            $ins->execute();
            $ins->close();
            $successMsg = "Student assigned successfully!";
        } else {
            $errorMsg = "Database error. Please try again.";
        }
    } else {
        $errorMsg = "Please fill in all required fields.";
    }
}

/* ── Fetch student usernames for dropdown ── */
$allStudents = [];
$sRes = $conn->query("SELECT username FROM users WHERE role='student' ORDER BY username ASC");
if ($sRes) {
    while ($sRow = $sRes->fetch_assoc()) {
        $allStudents[] = $sRow["username"];
    }
}

/* ── Get all teachers with their distinct students ── */
$teachers = [];
$stmt = $conn->prepare("
    SELECT
        u.id,
        u.username AS teacher_name,
        COUNT(DISTINCT c.student_name)                             AS student_count,
        GROUP_CONCAT(DISTINCT c.student_name ORDER BY c.student_name SEPARATOR '||') AS student_names,
        COUNT(c.id)                                                AS total_classes,
        MAX(c.class_date)                                          AS latest_class
    FROM users u
    LEFT JOIN classes c ON (c.teacher_id = u.id OR LOWER(c.teacher_name) = LOWER(u.username))
    WHERE u.role = 'teacher'
    GROUP BY u.id, u.username
    ORDER BY student_count DESC, u.username ASC
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row["students_list"] = $row["student_names"] ? explode("||", $row["student_names"]) : [];
        $teachers[] = $row;
    }
    $stmt->close();
}

$totalTeachers = count($teachers);
$totalStudents = array_sum(array_column($teachers, "student_count"));
$totalClasses  = array_sum(array_column($teachers, "total_classes"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Students | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #3e5077;
      --secondary: #143674;
      --dark: #0f172a;
      --muted: #64748b;
      --border: #dbeafe;
      --shadow: 0 18px 45px rgba(37,99,235,0.08);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background:
        radial-gradient(circle at top left, rgba(37,99,235,0.08), transparent 22%),
        radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
        linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
      color: var(--dark);
    }

    .app-shell { min-height: 100vh; display: flex; }

    .sidebar {
      width: 285px;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: white;
      padding: 0;
      position: sticky;
      top: 0;
      height: 100vh;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .sidebar-bottom { padding: 16px 18px; border-top: 1px solid rgba(255,255,255,0.1); }
    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow: hidden; }
    .sidebar-top-area { padding: 0 18px 18px; flex: 1; overflow-y: auto; }

    .brand-box {
      display: flex; align-items: center; gap: 12px;
      padding: 0 4px 22px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 10px;
    }
    .logo-img { width: 55px; height: 55px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
    .brand-title { font-weight: 900; font-size: 1.1rem; line-height: 1.15; }
    .brand-sub { font-size: 0.75rem; color: rgba(255,255,255,0.55); letter-spacing: 1px; margin-top: 3px; }
    .nav-title {
      font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1.3px;
      color: rgba(255,255,255,0.45); margin: 20px 10px 10px; font-weight: 700;
    }
    .nav-custom { display: flex; flex-direction: column; gap: 4px; }
    .nav-link-custom {
      display: flex; align-items: center; gap: 12px;
      color: rgba(255,255,255,0.78); text-decoration: none;
      padding: 12px 14px; border-radius: 14px;
      transition: all 0.25s ease; font-weight: 700;
    }
    .nav-link-custom:hover { background: rgba(255,255,255,0.08); color: white; }
    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      box-shadow: 0 10px 24px rgba(37,99,235,0.28);
    }
    .nav-icon {
      width: 32px; height: 32px; border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }

    .main-content { flex: 1; padding: 26px; }

    .hamburger-btn {
      display: flex; flex-direction: column; gap: 5px; cursor: pointer;
      background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
      padding: 10px 12px; margin-bottom: 18px; width: fit-content;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: background 0.2s;
    }
    .hamburger-btn:hover { background: #f1f5f9; }
    .hamburger-line { width: 22px; height: 2.5px; background: #334155; border-radius: 2px; }

    .topbar {
      display: flex; justify-content: space-between; align-items: center;
      gap: 16px; margin-bottom: 24px; padding: 18px 20px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 22px; box-shadow: var(--shadow);
    }
    .topbar h1 { font-size: 1.8rem; font-weight: 900; margin: 0; color: white; }
    .topbar p  { margin: 4px 0 0; color: rgba(255,255,255,0.8); }
    .admin-badge {
      background: rgba(255,255,255,0.15); color: #f6f8fc;
      border-radius: 12px; border: 1px solid rgba(255,255,255,0.2);
      padding: 10px 18px; font-weight: 800; white-space: nowrap;
    }

    /* ── Summary cards ── */
    .stat-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 26px;
    }
    .stat-box {
      background: #fff; border: 1px solid #edf4ff; border-radius: 20px;
      padding: 20px; text-align: center; box-shadow: var(--shadow);
      position: relative; overflow: hidden;
    }
    .stat-box::before {
      content: ''; display: block; height: 4px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      position: absolute; top: 0; left: 0; right: 0;
    }
    .stat-box-icon {
      width: 46px; height: 46px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.25rem; margin: 8px auto 12px;
      background: #f1f5f9; color: var(--primary);
    }
    .stat-box-num   { font-size: 2rem; font-weight: 900; color: var(--primary); line-height: 1; }
    .stat-box-label { font-size: 0.82rem; color: var(--muted); font-weight: 600; margin-top: 4px; }

    /* ── Search + filter bar ── */
    .search-bar {
      display: flex; gap: 12px; margin-bottom: 22px; flex-wrap: wrap; align-items: center;
    }
    .search-input {
      flex: 1; min-width: 220px; max-width: 380px;
      padding: 12px 16px 12px 42px;
      border: 2px solid #dbeafe; border-radius: 14px;
      font-size: 0.92rem; outline: none;
      background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398l3.85 3.85a1 1 0 0 0 1.415-1.415l-3.868-3.833zm-5.44 1.406a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 14px center;
      transition: border-color 0.2s;
    }
    .search-input:focus { border-color: var(--primary); }

    /* ── Teacher cards grid ── */
    .teachers-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 20px;
    }

    .teacher-card {
      background: #fff; border: 1px solid #edf4ff; border-radius: 22px;
      box-shadow: var(--shadow); overflow: hidden;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .teacher-card:hover { transform: translateY(-3px); box-shadow: 0 14px 36px rgba(15,23,42,0.1); }

    .tc-header {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      padding: 20px 22px;
      display: flex; align-items: center; gap: 14px;
    }
    .tc-avatar {
      width: 52px; height: 52px; border-radius: 50%;
      background: rgba(255,255,255,0.2);
      color: #fff; font-weight: 900; font-size: 1.4rem;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; border: 2px solid rgba(255,255,255,0.35);
    }
    .tc-name  { font-size: 1.1rem; font-weight: 900; color: #fff; margin: 0; }
    .tc-meta  { font-size: 0.82rem; color: rgba(255,255,255,0.75); margin-top: 3px; }

    .tc-body { padding: 18px 22px; }

    .tc-stats {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 10px; margin-bottom: 16px;
    }
    .tc-stat {
      background: #f8fbff; border: 1px solid #dbeafe;
      border-radius: 14px; padding: 12px 14px; text-align: center;
    }
    .tc-stat-num   { font-size: 1.5rem; font-weight: 900; color: var(--primary); line-height: 1; }
    .tc-stat-label { font-size: 0.75rem; color: var(--muted); font-weight: 600; margin-top: 3px; }

    /* Student tags */
    .students-label {
      font-size: 0.78rem; font-weight: 800; text-transform: uppercase;
      letter-spacing: 0.8px; color: var(--muted); margin-bottom: 10px;
    }
    .student-tags {
      display: flex; flex-wrap: wrap; gap: 7px;
    }
    .student-tag {
      display: inline-flex; align-items: center; gap: 6px;
      background: #eff6ff; color: #1d4ed8;
      border: 1px solid #bfdbfe;
      border-radius: 999px; padding: 5px 12px;
      font-size: 0.82rem; font-weight: 700;
    }
    .student-tag i { font-size: 0.7rem; opacity: 0.7; }

    .no-students {
      color: var(--muted); font-style: italic; font-size: 0.88rem;
      padding: 10px 0;
    }

    /* Add student button */
    .btn-add-student {
      display: flex; align-items: center; justify-content: center; gap: 7px;
      width: 100%; margin-top: 14px;
      padding: 10px 16px; border: none; border-radius: 12px; cursor: pointer;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; font-weight: 800; font-size: 0.88rem;
      transition: opacity 0.2s;
    }
    .btn-add-student:hover { opacity: 0.88; }

    /* Modal */
    .modal-overlay {
      display: none; position: fixed; inset: 0; z-index: 9999;
      background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);
      align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: #fff; border-radius: 24px; padding: 36px 32px;
      width: 100%; max-width: 500px; margin: 16px;
      box-shadow: 0 24px 60px rgba(15,23,42,0.2);
      animation: modalIn .22s ease;
    }
    @keyframes modalIn {
      from { opacity:0; transform:translateY(-14px) scale(.97); }
      to   { opacity:1; transform:translateY(0) scale(1); }
    }
    .modal-title {
      font-size: 1.25rem; font-weight: 900; color: #0f172a; margin: 0 0 6px;
    }
    .modal-sub { font-size: 0.88rem; color: var(--muted); margin: 0 0 24px; }
    .modal-label { font-weight: 800; color: #334155; display: block; margin-bottom: 7px; font-size: 0.9rem; }
    .modal-field { margin-bottom: 16px; }
    .modal-input, .modal-select {
      width: 100%; padding: 12px 14px; border: 1.5px solid #dbeafe;
      border-radius: 12px; font-size: 0.95rem; outline: none;
      transition: border-color 0.2s;
    }
    .modal-input:focus, .modal-select:focus { border-color: var(--primary); }
    .modal-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .modal-actions { display: flex; gap: 10px; margin-top: 24px; }
    .btn-modal-save {
      flex: 1; padding: 13px; border: none; border-radius: 14px; cursor: pointer;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; font-weight: 900; font-size: 0.97rem;
    }
    .btn-modal-cancel {
      padding: 13px 22px; border: 2px solid #e2e8f0; border-radius: 14px;
      background: #fff; color: #64748b; font-weight: 700; cursor: pointer;
    }

    /* Empty state */
    .empty-state {
      grid-column: 1 / -1; text-align: center; padding: 60px 20px;
      color: var(--muted);
    }
    .empty-state .empty-icon { font-size: 3rem; color: #cbd5e1; margin-bottom: 14px; }
    .empty-state h5 { font-weight: 800; color: #334155; }

    @media (max-width: 991px) {
      .app-shell { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: relative; }
      .stat-row { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 575px) {
      .stat-row { grid-template-columns: 1fr; }
      .teachers-grid { grid-template-columns: 1fr; }
    }
  </style>
  <script>(function(){var t=localStorage.getItem("jc-theme");if(t==="dark")document.documentElement.classList.add("dark");})();</script>
  <style>html.dark body{background:#0f172a!important;color:#e2e8f0!important}html.dark .sidebar{background:linear-gradient(180deg,#020817 0%,#0c1226 100%)!important}html.dark .teacher-card,html.dark .stat-box{background:#1e293b!important;border-color:#334155!important;color:#e2e8f0}html.dark .tc-body{background:#1e293b!important}html.dark .tc-stat{background:#0f172a!important;border-color:#334155!important}html.dark .student-tag{background:#1e3a5f!important;border-color:#1d4ed8!important;color:#93c5fd!important}html.dark .topbar{background:linear-gradient(135deg,#1e3a6e 0%,#0f2456 100%)!important}</style>
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
          <a href="admin_dashboard.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
          </a>
          <a href="manage_users.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-users"></i></span><span>Manage Users</span>
          </a>
          <a href="admin_teacher_students.php" class="nav-link-custom active">
            <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span>
          </a>
          <a href="admin_enrollments.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span>
          </a>
          <a href="manage_classes.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span>
          </a>
          <a href="teacher_earnings.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span>
          </a>
          <a href="available_slots.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span>
          </a>
          <a href="courses.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span>
          </a>
          <a href="reports.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span>
          </a>
          <a href="admin_certificates.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span>
          </a>
          <a href="admin_ai_settings.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span>
          </a>
          <a href="admin_quiz_generator.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>AI Quiz Generator</span>
          </a>
          <a href="admin_email_notifications.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-envelope"></i></span><span>Email Notifications</span>
          </a>
        </div>
      </div>
      <div class="sidebar-bottom">
        <a href="settings.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span>
        </a>
        <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
        <a href="logout.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span>
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
          <h1>Teacher Students</h1>
          <p>See how many students each teacher has and their names.</p>
        </div>
        <div class="admin-badge">
          <i class="fas fa-user-shield me-2"></i>Hello, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?>
        </div>
      </div>

      <!-- Summary -->
      <div class="stat-row">
        <div class="stat-box">
          <div class="stat-box-icon"><i class="fas fa-chalkboard-user"></i></div>
          <div class="stat-box-num"><?= $totalTeachers ?></div>
          <div class="stat-box-label">Total Teachers</div>
        </div>
        <div class="stat-box">
          <div class="stat-box-icon"><i class="fas fa-user-graduate"></i></div>
          <div class="stat-box-num"><?= $totalStudents ?></div>
          <div class="stat-box-label">Total Students</div>
        </div>
        <div class="stat-box">
          <div class="stat-box-icon"><i class="fas fa-book-open"></i></div>
          <div class="stat-box-num"><?= $totalClasses ?></div>
          <div class="stat-box-label">Total Classes</div>
        </div>
      </div>

      <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="fas fa-circle-check me-2"></i><?= htmlspecialchars($successMsg) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="fas fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Search -->
      <div class="search-bar">
        <input type="text" id="searchInput" class="search-input"
          placeholder="Search teacher or student name..."
          oninput="filterCards(this.value)">
      </div>

      <!-- Teacher Cards -->
      <div class="teachers-grid" id="teachersGrid">
        <?php if (empty($teachers)): ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-chalkboard-user"></i></div>
            <h5>No teachers found</h5>
            <p>Add teachers via <a href="manage_users.php">Manage Users</a>.</p>
          </div>
        <?php else: foreach ($teachers as $t):
          $tName    = $t["teacher_name"] ?? "";
          $sCnt     = (int)$t["student_count"];
          $cCnt     = (int)$t["total_classes"];
          $latest   = $t["latest_class"] ?? "";
          $students = $t["students_list"];
          $searchData = strtolower($tName . " " . implode(" ", $students));
        ?>
          <div class="teacher-card" data-search="<?= htmlspecialchars($searchData) ?>">
            <div class="tc-header">
              <div class="tc-avatar"><?= strtoupper(substr($tName, 0, 1)) ?></div>
              <div>
                <p class="tc-name"><?= htmlspecialchars($tName) ?></p>
                <div class="tc-meta">
                  <?php if ($latest): ?>
                    Last class: <?= date("d M Y", strtotime($latest)) ?>
                  <?php else: ?>
                    No classes yet
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="tc-body">
              <div class="tc-stats">
                <div class="tc-stat">
                  <div class="tc-stat-num"><?= $sCnt ?></div>
                  <div class="tc-stat-label">Students</div>
                </div>
                <div class="tc-stat">
                  <div class="tc-stat-num"><?= $cCnt ?></div>
                  <div class="tc-stat-label">Classes</div>
                </div>
              </div>

              <div class="students-label">Students</div>
              <?php if (empty($students)): ?>
                <div class="no-students">No students assigned yet.</div>
              <?php else: ?>
                <div class="student-tags">
                  <?php foreach ($students as $s): ?>
                    <span class="student-tag">
                      <i class="fas fa-user"></i>
                      <?= htmlspecialchars($s) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <button type="button" class="btn-add-student"
                onclick="openAddModal(<?= $t['id'] ?>, <?= htmlspecialchars(json_encode($tName)) ?>)">
                <i class="fas fa-plus"></i> Add Student
              </button>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </main>
  </div>

  <!-- Add Student Modal -->
  <div class="modal-overlay" id="addModal">
    <div class="modal-box">
      <div class="modal-title"><i class="fas fa-user-plus me-2" style="color:var(--primary)"></i>Add Student</div>
      <p class="modal-sub" id="modalSub">Assign a student to this teacher.</p>
      <form method="POST">
        <input type="hidden" name="add_student" value="1">
        <input type="hidden" name="teacher_id"   id="modal_teacher_id">
        <input type="hidden" name="teacher_name" id="modal_teacher_name">

        <div class="modal-field">
          <label class="modal-label">Student <span style="color:#dc2626">*</span></label>
          <?php if (!empty($allStudents)): ?>
            <select name="student_name" class="modal-select" required>
              <option value="">— Select student —</option>
              <?php foreach ($allStudents as $sn): ?>
                <option value="<?= htmlspecialchars($sn) ?>"><?= htmlspecialchars($sn) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="text" name="student_name" class="modal-input" placeholder="Student name" required>
          <?php endif; ?>
        </div>

        <div class="modal-field">
          <label class="modal-label">Class Type</label>
          <select name="type" class="modal-select">
            <option value="Paid">Paid</option>
            <option value="Demo">Demo</option>
            <option value="Half Pay">Half Pay</option>
            <option value="No Pay">No Pay</option>
          </select>
        </div>

        <div class="modal-field">
          <label class="modal-label">Details <span style="color:var(--muted);font-weight:400">(optional)</span></label>
          <textarea name="details" class="modal-input" rows="2" placeholder="Notes about this class…" style="height:auto;resize:vertical;"></textarea>
        </div>

        <div class="modal-actions">
          <button type="submit" class="btn-modal-save"><i class="fas fa-plus me-1"></i> Add Student</button>
          <button type="button" class="btn-modal-cancel" onclick="closeAddModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function openAddModal(teacherId, teacherName) {
      document.getElementById('modal_teacher_id').value   = teacherId;
      document.getElementById('modal_teacher_name').value = teacherName;
      document.getElementById('modalSub').textContent     = 'Assigning a student to: ' + teacherName;
      document.getElementById('addModal').classList.add('open');
    }
    function closeAddModal() {
      document.getElementById('addModal').classList.remove('open');
    }
    document.getElementById('addModal').addEventListener('click', function(e) {
      if (e.target === this) closeAddModal();
    });
  </script>

  <script>
    function filterCards(query) {
      const q = query.toLowerCase().trim();
      let visible = 0;

      document.querySelectorAll(".teacher-card").forEach(card => {
        const data = card.dataset.search || "";
        const show = !q || data.includes(q);
        card.style.display = show ? "" : "none";
        if (show) visible++;
      });

      let empty = document.getElementById("emptySearch");
      if (visible === 0 && q) {
        if (!empty) {
          empty = document.createElement("div");
          empty.id = "emptySearch";
          empty.className = "empty-state";
          empty.innerHTML = `<div class="empty-icon"><i class="fas fa-magnifying-glass"></i></div>
            <h5>No results</h5><p>No teachers or students match "<strong>${query}</strong>".</p>`;
          document.getElementById("teachersGrid").appendChild(empty);
        }
        empty.style.display = "";
      } else if (empty) {
        empty.style.display = "none";
      }
    }
  </script>
  <script src="logout-modal.js"></script>
</body>
</html>
