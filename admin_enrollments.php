<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";

/* ── Ensure enrollments table ── */
$conn->query("CREATE TABLE IF NOT EXISTS student_enrollments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(255) NOT NULL,
    course_id    INT          NOT NULL,
    enrolled_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment (student_name, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Ensure courses table has required columns ── */
foreach ([
    "section"     => "VARCHAR(50)  NOT NULL DEFAULT 'kids'",
    "category"    => "VARCHAR(100) NOT NULL DEFAULT ''",
    "course_type" => "VARCHAR(20)  NOT NULL DEFAULT 'paid'",
    "status"      => "VARCHAR(20)  NOT NULL DEFAULT 'active'",
    "is_unlocked" => "TINYINT(1)   NOT NULL DEFAULT 0",
] as $col => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM courses LIKE '$col'");
    if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE courses ADD COLUMN $col $def");
}

/* ── Handle delete course ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_course"])) {
    $delId = (int)($_POST["course_id"] ?? 0);
    if ($delId > 0) {
        $d1 = $conn->prepare("DELETE FROM student_enrollments WHERE course_id = ?");
        if ($d1) { $d1->bind_param("i", $delId); $d1->execute(); $d1->close(); }
        $d2 = $conn->prepare("DELETE FROM course_projects WHERE course_id = ?");
        if ($d2) { $d2->bind_param("i", $delId); $d2->execute(); $d2->close(); }
        $d3 = $conn->prepare("DELETE FROM courses WHERE id = ?");
        if ($d3) { $d3->bind_param("i", $delId); $d3->execute(); $d3->close(); }
    }
    header("Location: admin_enrollments.php?deleted=1");
    exit();
}

/* ── Messages ── */
$msg = "";
if (isset($_GET["saved"]))   $msg = "Enrollments saved for course <strong>" . htmlspecialchars($_GET["saved"]) . "</strong>.";
if (isset($_GET["deleted"])) $msg = "Course deleted successfully.";

/* ── Handle save course → students ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_course_students"])) {
    $courseId      = (int)($_POST["course_id"] ?? 0);
    $courseDisplay = trim($_POST["course_name_display"] ?? "");
    $selStudents   = $_POST["student_names"] ?? [];

    if ($courseId > 0) {
        $del = $conn->prepare("DELETE FROM student_enrollments WHERE course_id = ?");
        if ($del) { $del->bind_param("i", $courseId); $del->execute(); $del->close(); }

        if (!empty($selStudents)) {
            $ins = $conn->prepare("INSERT IGNORE INTO student_enrollments (student_name, course_id) VALUES (?,?)");
            if ($ins) {
                foreach ($selStudents as $sName) {
                    $sName = trim($sName);
                    if ($sName !== "") { $ins->bind_param("si", $sName, $courseId); $ins->execute(); }
                }
                $ins->close();
            }
            /* Unlock the course so enrolled students can see its content */
            $conn->query("UPDATE courses SET is_unlocked = 1 WHERE id = $courseId");
        } else {
            /* No students selected — lock the course back */
            $conn->query("UPDATE courses SET is_unlocked = 0 WHERE id = $courseId");
        }

        header("Location: admin_enrollments.php?course_id=" . $courseId . "&saved=" . urlencode($courseDisplay));
        exit();
    }
}

/* ── Handle select existing course ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["select_course"])) {
    $goId = (int)($_POST["selected_course_id"] ?? 0);
    if ($goId > 0) {
        header("Location: admin_enrollments.php?course_id=" . $goId);
        exit();
    }
}

/* ── Load all students ── */
$students = [];
$sRes = $conn->query("SELECT username FROM users WHERE role='student' ORDER BY username ASC");
if ($sRes) { while ($r = $sRes->fetch_assoc()) $students[] = $r["username"]; }

/* ── Load all courses grouped section → category ── */
$allCourses = [];
$cRes = $conn->query("SELECT id, course_name, section, category, course_type, status FROM courses WHERE section != 'demo' ORDER BY section, category, id ASC");
if ($cRes) {
    while ($r = $cRes->fetch_assoc()) $allCourses[$r["section"]][$r["category"]][] = $r;
}

/* ── Selected course ── */
$selectedCourseId = (int)($_GET["course_id"] ?? 0);
$selectedCourse   = null;
if ($selectedCourseId > 0) {
    $scStmt = $conn->prepare("SELECT id, course_name, section, category, course_type FROM courses WHERE id = ?");
    if ($scStmt) {
        $scStmt->bind_param("i", $selectedCourseId);
        $scStmt->execute();
        $selectedCourse = $scStmt->get_result()->fetch_assoc();
        $scStmt->close();
    }
}

/* ── Enrolled students for selected course ── */
$enrolledStudents = [];
if ($selectedCourseId > 0) {
    $esStmt = $conn->prepare("SELECT student_name FROM student_enrollments WHERE course_id = ?");
    if ($esStmt) {
        $esStmt->bind_param("i", $selectedCourseId);
        $esStmt->execute();
        $enrolledStudents = array_column($esStmt->get_result()->fetch_all(MYSQLI_ASSOC), "student_name");
        $esStmt->close();
    }
}

/* ── Enrolled count per course (for sidebar badges) ── */
$courseEnrollCounts = [];
$ecRes = $conn->query("SELECT course_id, COUNT(*) cnt FROM student_enrollments GROUP BY course_id");
if ($ecRes) { while ($r = $ecRes->fetch_assoc()) $courseEnrollCounts[(int)$r["course_id"]] = (int)$r["cnt"]; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Course Enrollments | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --shadow:0 18px 45px rgba(37,99,235,0.08); }
    *{ box-sizing:border-box; }
    body {
      margin:0; font-family:Arial,Helvetica,sans-serif; color:var(--dark);
      background: radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),
                  radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),
                  linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%);
    }
    .app-shell { min-height:100vh; display:flex; }

    .sidebar {
      width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%);
      color:#fff; padding:0; position:sticky; top:0; height:100vh;
      transition:width 0.3s,padding 0.3s,min-width 0.3s; overflow-y: auto;
      display:flex; flex-direction:column;
    }
    .sidebar-bottom { padding:16px 18px; }
    body.sidebar-collapsed .sidebar { width:0; padding:0; min-width:0; }
    .sidebar-top-area { padding:0 18px 18px; }
    .brand-box { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
    .logo-img { width:55px; height:55px; object-fit:contain; border-radius:12px; flex-shrink:0; }
    .brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
    .brand-sub { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
    .nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
    .nav-custom { display:flex; flex-direction:column; gap:4px; }
    .nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all 0.25s; font-weight:700; }
    .nav-link-custom:hover { background:rgba(255,255,255,0.08); color:#fff; }
    .nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 10px 24px rgba(37,99,235,0.28); }
    .nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }

    .main-content { flex:1; padding:26px; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:24px; padding:18px 20px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; box-shadow:var(--shadow); }
    .topbar h1 { font-size:1.8rem; font-weight:900; margin:0; color:#fff; }
    .topbar p  { margin:4px 0 0; color:rgba(255,255,255,0.8); }
    .admin-badge { background:rgba(255,255,255,0.15); color:#f6f8fc; border-radius:12px; border:1px solid rgba(255,255,255,0.2); padding:10px 18px; font-weight:800; white-space:nowrap; }

    /* Layout */
    .enroll-layout { display:grid; grid-template-columns:300px 1fr; gap:22px; align-items:start; }

    /* Course picker sidebar */
    .course-picker {
      background:#fff; border:1px solid #edf4ff; border-radius:22px;
      padding:22px; box-shadow:var(--shadow); position:sticky; top:26px; max-height:calc(100vh - 80px); display:flex; flex-direction:column;
    }
    .picker-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-shrink:0; }
    .picker-title { font-size:1rem; font-weight:900; color:var(--dark); margin:0; }
    .picker-scroll { overflow-y:auto; flex:1; }

    .section-group-pick { margin-bottom:16px; }
    .section-pick-label {
      font-size:0.75rem; text-transform:uppercase; letter-spacing:1.2px;
      font-weight:800; color:var(--muted); margin-bottom:8px; padding-left:4px;
      display:flex; align-items:center; gap:6px;
    }
    .cat-pick-label { font-size:0.8rem; font-weight:700; color:#475569; padding-left:6px; margin-bottom:5px; }

    .course-item {
      display:flex; align-items:center; gap:10px;
      padding:10px 12px; border-radius:12px; cursor:pointer;
      border:2px solid transparent; text-decoration:none;
      color:var(--dark); font-weight:700; transition:all 0.18s; margin-bottom:4px;
    }
    .course-item:hover { background:#f0f7ff; border-color:#bfdbfe; color:var(--dark); }
    .course-item.active { background:#eff6ff; border-color:var(--primary); color:var(--primary); }
    .ci-icon { width:34px; height:34px; border-radius:10px; flex-shrink:0;
      display:flex; align-items:center; justify-content:center; font-size:0.85rem; color:#fff; }
    .ci-icon.kids   { background:linear-gradient(135deg,#f59e0b,#ef4444); }
    .ci-icon.junior { background:linear-gradient(135deg,#3b82f6,#8b5cf6); }
    .ci-name { font-size:0.88rem; flex:1; line-height:1.3; }
    .ci-count { font-size:0.72rem; color:var(--muted); font-weight:600; white-space:nowrap;
      background:#f1f5f9; border-radius:999px; padding:2px 8px; }

    .course-item-wrap { display:flex; align-items:center; gap:4px; margin-bottom:4px; }
    .course-item-wrap .course-item { flex:1; margin-bottom:0; }
    .ci-del-btn {
      width:30px; height:30px; border-radius:8px; border:none; cursor:pointer;
      background:#fee2e2; color:#dc2626; font-size:0.75rem; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      opacity:0; transition:opacity 0.18s;
    }
    .course-item-wrap:hover .ci-del-btn { opacity:1; }

    /* Student assignment panel */
    .assign-panel {
      background:#fff; border:1px solid #edf4ff; border-radius:22px;
      padding:22px; box-shadow:var(--shadow);
    }
    .panel-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
    .panel-title { font-size:1.1rem; font-weight:900; margin:0; color:var(--dark); }

    /* Student rows */
    .student-row {
      display:flex; align-items:center; gap:14px;
      padding:12px 16px; border-radius:14px; border:1.5px solid #e2e8f0;
      margin-bottom:8px; cursor:pointer; transition:all 0.15s;
    }
    .student-row:hover { border-color:#bfdbfe; background:#f8fbff; }
    .student-row.checked { border-color:#3b82f6; background:#eff6ff; }
    .student-row input[type=checkbox] { width:18px; height:18px; cursor:pointer; accent-color:var(--primary); flex-shrink:0; }
    .s-av {
      width:38px; height:38px; border-radius:50%; flex-shrink:0;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      color:#fff; font-weight:900; font-size:0.9rem;
      display:flex; align-items:center; justify-content:center;
    }
    .s-name { font-weight:800; font-size:0.93rem; color:var(--dark); flex:1; }
    .s-badge { font-size:0.75rem; font-weight:700; padding:3px 10px; border-radius:999px; }
    .s-enrolled { background:#dcfce7; color:#166534; }

    .btn-save {
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      border:none; color:#fff; font-weight:900; border-radius:14px;
      padding:12px 28px; cursor:pointer; font-size:0.97rem;
    }
    .btn-save:hover { opacity:0.9; }
    .btn-add-course {
      background:none; border:1.5px solid var(--primary); color:var(--primary);
      border-radius:10px; padding:7px 14px; font-weight:700; font-size:0.82rem; cursor:pointer;
    }
    .btn-add-course:hover { background:#eff6ff; }
    .select-all-btn {
      background:none; border:1.5px solid #e2e8f0; color:var(--muted);
      border-radius:10px; padding:7px 14px; font-weight:700; font-size:0.82rem; cursor:pointer;
    }
    .select-all-btn:hover { border-color:var(--primary); color:var(--primary); }

    .empty-state { text-align:center; padding:50px 20px; color:var(--muted); }
    .empty-state i { font-size:2.5rem; color:#cbd5e1; margin-bottom:14px; display:block; }

    .course-meta-bar {
      display:flex; align-items:center; gap:10px; padding:12px 16px;
      background:#f8fbff; border-radius:12px; margin-bottom:20px; flex-wrap:wrap;
    }
    .meta-chip {
      font-size:0.78rem; font-weight:700; padding:4px 12px; border-radius:999px;
      display:flex; align-items:center; gap:5px;
    }
    .chip-section { background:#dbeafe; color:#1e40af; }
    .chip-type   { background:#dcfce7; color:#166534; }
    .chip-count  { background:#fef3c7; color:#92400e; }

    .student-search { width:100%; padding:9px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.88rem; margin-bottom:16px; }
    .student-search:focus { outline:none; border-color:var(--primary); }

    @media (max-width:991px) {
      .app-shell { flex-direction:column; }
      .sidebar { width:100%; height:auto; position:relative; }
      .enroll-layout { grid-template-columns:1fr; }
      .course-picker { position:static; max-height:none; }
    }
  </style>
  <script>(function(){var t=localStorage.getItem("jc-theme");if(t==="dark")document.documentElement.classList.add("dark");})();</script>
  <style>html.dark body{background:#0f172a!important;color:#e2e8f0!important}html.dark .sidebar{background:linear-gradient(180deg,#020817 0%,#0c1226 100%)!important}html.dark .assign-panel,html.dark .course-picker{background:#1e293b!important;border-color:#334155!important;color:#e2e8f0}html.dark .student-row{background:#0f172a!important;border-color:#334155!important}html.dark .topbar{background:linear-gradient(135deg,#1e3a6e 0%,#0f2456 100%)!important}html.dark .course-meta-bar{background:#0f172a!important}</style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="sidebar-top-area">
      <div class="brand-box">
        <img src="images/robot2.png.png" class="logo-img" alt="Logo">
        <div><div class="brand-title">JuniorCode</div><div class="brand-sub">Admin Panel</div></div>
      </div>
      <div class="nav-title">Main</div>
      <div class="nav-custom">
        <a href="admin_dashboard.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
        <a href="manage_users.php"           class="nav-link-custom"><span class="nav-icon"><i class="fas fa-users"></i></span><span>Manage Users</span></a>
        <a href="admin_teacher_students.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span></a>
        <a href="admin_enrollments.php"      class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span></a>
        <a href="manage_classes.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
        <a href="teacher_earnings.php"       class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
        <a href="available_slots.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
        <a href="courses.php"                class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
        <a href="reports.php"                class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
        <a href="admin_certificates.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
      </div>
    </div>
    <div class="sidebar-bottom">
      <a href="settings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
      <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
      <a href="logout.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
    </div>
  </aside>

  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
    </div>

    <div class="topbar">
      <div>
        <h1>Course Enrollments</h1>
        <p>Pick a course, then choose which students can access it.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-success alert-dismissible fade show mb-3">
        <i class="fas fa-circle-check me-2"></i><?= $msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="enroll-layout">

      <!-- Course picker -->
      <div class="course-picker">
        <div class="picker-header">
          <p class="picker-title"><i class="fas fa-book-open me-2" style="color:var(--primary)"></i>Courses</p>
          <button type="button" class="btn-add-course" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="fas fa-plus me-1"></i> New
          </button>
        </div>

        <?php if (empty($allCourses)): ?>
          <div style="text-align:center;padding:30px 10px;color:var(--muted);font-size:0.9rem;">
            <i class="fas fa-book" style="font-size:2rem;color:#cbd5e1;display:block;margin-bottom:10px;"></i>
            No courses yet.<br>
            <a href="courses.php" class="btn-save mt-3 d-inline-block" style="font-size:0.82rem;padding:9px 18px;text-decoration:none;">
              <i class="fas fa-arrow-right me-1"></i> Go to Courses
            </a>
          </div>
        <?php else: ?>
          <div class="picker-scroll">
            <?php
            $sectionLabels = ['kids'=>'Kids','junior'=>'Junior'];
            $sectionIcons  = ['kids'=>'fa-star','junior'=>'fa-rocket'];
            foreach ($allCourses as $section => $categories):
            ?>
              <div class="section-group-pick">
                <div class="section-pick-label">
                  <i class="fas <?= $sectionIcons[$section] ?? 'fa-book' ?>"></i>
                  <?= $sectionLabels[$section] ?? ucfirst($section) ?>
                </div>
                <?php foreach ($categories as $cat => $courses): ?>
                  <?php if ($cat !== ''): ?>
                    <div class="cat-pick-label"><?= htmlspecialchars($cat) ?></div>
                  <?php endif; ?>
                  <?php foreach ($courses as $c):
                    $cnt = $courseEnrollCounts[$c["id"]] ?? 0;
                    $isActive = ($c["id"] == $selectedCourseId);
                  ?>
                    <div class="course-item-wrap">
                      <a href="?course_id=<?= $c["id"] ?>" class="course-item <?= $isActive ? 'active' : '' ?>">
                        <div class="ci-icon <?= $section ?>">
                          <i class="fas <?= $sectionIcons[$section] ?? 'fa-book' ?>"></i>
                        </div>
                        <span class="ci-name"><?= htmlspecialchars($c["course_name"]) ?></span>
                        <span class="ci-count"><?= $cnt ?> student<?= $cnt !== 1 ? 's' : '' ?></span>
                      </a>
                      <button class="ci-del-btn" type="button"
                        onclick="openDelModal(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['course_name'])) ?>)"
                        title="Delete course">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Student assignment panel -->
      <div class="assign-panel">
        <?php if ($selectedCourse === null): ?>
          <div class="empty-state">
            <i class="fas fa-arrow-left"></i>
            <h5 style="font-weight:800;color:#334155;">Select a course</h5>
            <p>Click a course on the left to manage which students are enrolled in it.</p>
          </div>

        <?php else: ?>
          <form method="POST" id="assignForm">
            <input type="hidden" name="save_course_students" value="1">
            <input type="hidden" name="course_id" value="<?= $selectedCourse["id"] ?>">
            <input type="hidden" name="course_name_display" value="<?= htmlspecialchars($selectedCourse["course_name"]) ?>">

            <div class="panel-header">
              <div>
                <p class="panel-title">
                  <i class="fas fa-graduation-cap me-2" style="color:var(--primary)"></i>
                  <?= htmlspecialchars($selectedCourse["course_name"]) ?>
                </p>
                <p style="margin:2px 0 0;font-size:0.85rem;color:var(--muted);">
                  <?= count($enrolledStudents) ?> student<?= count($enrolledStudents) !== 1 ? 's' : '' ?> enrolled
                </p>
              </div>
              <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="button" class="select-all-btn" onclick="toggleAll(true)">Select All</button>
                <button type="button" class="select-all-btn" onclick="toggleAll(false)">Clear All</button>
                <button type="submit" class="btn-save"><i class="fas fa-save me-1"></i> Save</button>
              </div>
            </div>

            <!-- Course meta -->
            <div class="course-meta-bar">
              <span class="meta-chip chip-section">
                <i class="fas <?= $selectedCourse["section"] === "kids" ? "fa-star" : "fa-rocket" ?>"></i>
                <?= ucfirst($selectedCourse["section"]) ?>
              </span>
              <span class="meta-chip chip-type">
                <i class="fas fa-tag"></i>
                <?= ucfirst($selectedCourse["course_type"]) ?>
              </span>
              <?php if ($selectedCourse["category"]): ?>
              <span class="meta-chip" style="background:#f3e8ff;color:#6b21a8;">
                <i class="fas fa-folder"></i>
                <?= htmlspecialchars($selectedCourse["category"]) ?>
              </span>
              <?php endif; ?>
              <span class="meta-chip chip-count">
                <i class="fas fa-users"></i>
                <?= count($enrolledStudents) ?> / <?= count($students) ?> students enrolled
              </span>
            </div>

            <?php if (empty($students)): ?>
              <div class="empty-state" style="padding:30px;">
                <i class="fas fa-user-slash"></i>
                <p>No students found. <a href="manage_users.php">Add students</a> first.</p>
              </div>
            <?php else: ?>
              <input type="text" class="student-search" id="studentSearch" placeholder="Search students..." oninput="filterStudents(this.value)">

              <div id="studentList">
                <?php foreach ($students as $s):
                  $checked = in_array($s, $enrolledStudents);
                ?>
                  <label class="student-row <?= $checked ? 'checked' : '' ?>" onclick="this.classList.toggle('checked')">
                    <input type="checkbox" name="student_names[]" value="<?= htmlspecialchars($s) ?>"
                      <?= $checked ? 'checked' : '' ?>
                      onchange="this.closest('.student-row').classList.toggle('checked',this.checked)">
                    <div class="s-av"><?= strtoupper(substr($s, 0, 1)) ?></div>
                    <span class="s-name"><?= htmlspecialchars($s) ?></span>
                    <?php if ($checked): ?>
                      <span class="s-badge s-enrolled"><i class="fas fa-circle-check me-1"></i>Enrolled</span>
                    <?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>

              <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;">
                <button type="submit" class="btn-save"><i class="fas fa-save me-1"></i> Save Enrollments</button>
              </div>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<!-- Select Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow-lg">
      <div class="modal-header border-0 pt-4 pb-2 px-4">
        <h5 class="modal-title fw-bold" id="addCourseModalLabel">
          <i class="fas fa-graduation-cap me-2" style="color:var(--primary)"></i>Select a Course
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <?php if (empty($allCourses)): ?>
        <div class="modal-body px-4 py-4 text-center">
          <i class="fas fa-book" style="font-size:2rem;color:#cbd5e1;display:block;margin-bottom:12px;"></i>
          <p style="color:var(--muted);">No courses yet. Add courses first in the <a href="courses.php" class="fw-bold">Courses</a> page, then come back to assign students.</p>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-0 justify-content-center">
          <a href="courses.php" class="btn-save rounded-3"><i class="fas fa-arrow-right me-1"></i> Go to Courses</a>
        </div>
      <?php else: ?>
      <form method="POST" action="admin_enrollments.php">
        <input type="hidden" name="select_course" value="1">
        <div class="modal-body px-4 py-3">
          <p style="font-size:0.88rem;color:var(--muted);margin-bottom:16px;">Choose a course to open and assign students to it.</p>
          <div class="mb-3">
            <label class="form-label fw-bold small">Course <span class="text-danger">*</span></label>
            <select name="selected_course_id" class="form-select rounded-3" required>
              <option value="">— pick a course —</option>
              <?php
              $currentSec = "";
              foreach ($allCourses as $c):
                if ($c["section"] !== $currentSec) {
                    $currentSec = $c["section"];
                    echo '<optgroup label="' . ($c["section"] === "kids" ? "Kids" : "Junior") . '">';
                }
              ?>
                <option value="<?= $c["id"] ?>" <?= $c["id"] == $selectedCourseId ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c["course_name"]) ?>
                  <?php if ($c["category"] !== ""): ?>(<?= htmlspecialchars($c["category"]) ?>)<?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-1">
          <button type="button" class="btn btn-light rounded-3 fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-save rounded-3"><i class="fas fa-arrow-right me-1"></i> Open Course</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Delete Course Modal -->
<div id="delCourseModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:22px;padding:36px;max-width:400px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,0.18);text-align:center;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
      <i class="fas fa-trash" style="font-size:1.5rem;color:#dc2626;"></i>
    </div>
    <div style="font-size:1.1rem;font-weight:900;margin-bottom:8px;">Delete Course?</div>
    <p id="delCourseMsg" style="color:#64748b;font-size:0.92rem;margin-bottom:24px;"></p>
    <form method="POST" id="delCourseForm">
      <input type="hidden" name="delete_course" value="1">
      <input type="hidden" name="course_id" id="delCourseId">
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="submit" style="background:linear-gradient(135deg,#dc2626,#b91c1c);border:none;color:#fff;font-weight:800;border-radius:12px;padding:12px 28px;cursor:pointer;">
          <i class="fas fa-trash me-1"></i> Yes, Delete
        </button>
        <button type="button" onclick="closeDelModal()" style="background:#64748b;color:#fff;border:none;border-radius:12px;padding:12px 24px;font-weight:800;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openDelModal(id, name) {
  document.getElementById('delCourseId').value = id;
  document.getElementById('delCourseMsg').textContent = 'Delete "' + name + '" and all its enrollments? This cannot be undone.';
  document.getElementById('delCourseModal').style.display = 'flex';
}
function closeDelModal() {
  document.getElementById('delCourseModal').style.display = 'none';
}
document.getElementById('delCourseModal').addEventListener('click', function(e) {
  if (e.target === this) closeDelModal();
});

function toggleAll(check) {
  document.querySelectorAll('#assignForm input[type=checkbox]').forEach(cb => {
    cb.checked = check;
    cb.closest('.student-row').classList.toggle('checked', check);
  });
}

function filterStudents(query) {
  const q = query.toLowerCase();
  document.querySelectorAll('#studentList .student-row').forEach(row => {
    const name = row.querySelector('.s-name').textContent.toLowerCase();
    row.style.display = name.includes(q) ? '' : 'none';
  });
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>


