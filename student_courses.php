<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";

/* ── Ensure enrollments table exists ── */
$conn->query("CREATE TABLE IF NOT EXISTS student_enrollments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(255) NOT NULL,
    course_id    INT          NOT NULL,
    enrolled_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment (student_name, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Enrolled course IDs for this student ── */
$enrolledIds = [];
$eStmt = $conn->prepare("SELECT course_id FROM student_enrollments WHERE student_name = ?");
if ($eStmt) {
    $eStmt->bind_param("s", $studentName);
    $eStmt->execute();
    $enrolledIds = array_column($eStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'course_id');
    $eStmt->close();
}

/* ── Ensure is_unlocked column ── */
$chk = $conn->query("SHOW COLUMNS FROM courses LIKE 'is_unlocked'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE courses ADD COLUMN is_unlocked TINYINT(1) NOT NULL DEFAULT 0");
}

/* ── Ensure course_id column on course_projects ── */
$chk2 = $conn->query("SHOW COLUMNS FROM course_projects LIKE 'course_id'");
if ($chk2 && $chk2->num_rows === 0) {
    $conn->query("ALTER TABLE course_projects ADD COLUMN course_id INT DEFAULT NULL");
}

/* ── Backfill: link orphan projects to courses by section+category ── */
$conn->query("UPDATE course_projects cp
    JOIN courses c ON cp.section = c.section
        AND (c.category = '' OR c.category = cp.category)
    SET cp.course_id = c.id
    WHERE cp.course_id IS NULL");

/* ── Helpers ── */
function fetchAllBySection($conn, string $section): array {
    $s = $conn->prepare("SELECT * FROM courses WHERE section=? AND status='active' ORDER BY category ASC, id ASC");
    if (!$s) return [];
    $s->bind_param("s", $section); $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
function fetchCourseProjects($conn, int $courseId, string $section = '', string $category = ''): array {
    /* Primary: match by course_id */
    $s = $conn->prepare("SELECT * FROM course_projects WHERE course_id=? ORDER BY sort_order ASC, id ASC");
    if (!$s) return [];
    $s->bind_param("i", $courseId); $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    if (!empty($rows)) return $rows;

    /* Fallback: match by section+category (for projects not yet backfilled) */
    if ($section !== '') {
        if ($category !== '') {
            $s2 = $conn->prepare("SELECT * FROM course_projects WHERE section=? AND (category=? OR category='') ORDER BY sort_order ASC, id ASC");
            if (!$s2) return [];
            $s2->bind_param("ss", $section, $category);
        } else {
            $s2 = $conn->prepare("SELECT * FROM course_projects WHERE section=? ORDER BY sort_order ASC, id ASC");
            if (!$s2) return [];
            $s2->bind_param("s", $section);
        }
        $s2->execute();
        return $s2->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

$kidsCourses   = fetchAllBySection($conn, 'kids');
$juniorCourses = fetchAllBySection($conn, 'junior');
$enrolledCount = count($enrolledIds);
$totalCourses  = count($kidsCourses) + count($juniorCourses);

$kidsCourseProjects = [];
foreach ($kidsCourses as $kc) {
    $kidsCourseProjects[$kc['id']] = fetchCourseProjects($conn, (int)$kc['id'], 'kids', $kc['category'] ?? '');
}
$juniorCourseProjects = [];
foreach ($juniorCourses as $jc) {
    $juniorCourseProjects[$jc['id']] = fetchCourseProjects($conn, (int)$jc['id'], 'junior', $jc['category'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Courses | JuniorCode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #3e5077; --secondary: #143674;
      --dark: #0f172a; --muted: #64748b;
      --shadow: 0 18px 45px rgba(37,99,235,0.08);
      --border: #dbeafe;
    }
    * { box-sizing: border-box; }
    body { margin:0; font-family:Arial,sans-serif; color:var(--dark);
      background: radial-gradient(circle at top left, rgba(37,99,235,0.08), transparent 22%),
                  radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
                  linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%);
    }

    .app-shell { min-height: 100vh; display: flex; }

    .sidebar {
      width: 285px; flex-shrink: 0;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: #fff; padding: 0;
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

    .student-box {
      display: flex; align-items: center; gap: 12px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 16px; padding: 14px; margin-bottom: 18px;
    }

    .student-avatar {
      width: 44px; height: 44px; border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; font-weight: bold; font-size: 18px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      overflow: hidden;
    }
    .student-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .student-name { font-weight: 800; margin: 0; color: #fff; }
    .student-role { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }

    .nav-link-custom {
      display: flex; align-items: center; gap: 12px;
      text-decoration: none; color: rgba(255,255,255,0.78);
      padding: 12px 14px; border-radius: 14px; margin: 4px 0;
      font-weight: 700; transition: all 0.22s ease;
    }
    .nav-link-custom:hover { background: rgba(255,255,255,0.09); color: #fff; }
    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; box-shadow: 0 8px 20px rgba(30,50,100,0.35);
    }
    .nav-icon {
      width: 32px; height: 32px; border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0;
    }
    .nav-link-custom.active .nav-icon { background: rgba(255,255,255,0.18); }

    .sidebar-bottom { padding: 16px 18px; }

    .main { flex: 1; padding: 28px; min-height: 100vh; overflow-x: hidden; }

    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff;
      border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px;
      width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .topbar {
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      border-radius:22px; padding:22px 26px; margin-bottom:24px;
      display:flex; justify-content:space-between; align-items:center;
      flex-wrap:wrap; gap:14px; color:#fff;
      box-shadow:0 12px 28px rgba(37,99,235,0.28);
    }
    .topbar h1 { font-size:1.7rem; font-weight:900; margin:0; }
    .topbar p  { margin:4px 0 0; opacity:0.85; }
    .topbar-badge {
      background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.2);
      border-radius:12px; padding:10px 18px; font-weight:700; font-size:0.9rem;
    }

    /* ── Stat bar ── */
    .stat-bar {
      display:grid; grid-template-columns:repeat(3,1fr);
      gap:14px; margin-bottom:22px;
    }
    .stat-box {
      background:#fff; border:1px solid var(--border); border-radius:18px;
      padding:18px 20px; text-align:center; box-shadow:var(--shadow);
      position:relative; overflow:hidden;
    }
    .stat-box::before { content:''; display:block; height:4px;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      position:absolute; top:0; left:0; right:0; }
    .stat-icon { width:42px; height:42px; border-radius:12px; margin:6px auto 10px;
      background:#f1f5f9; color:var(--primary);
      display:flex; align-items:center; justify-content:center; font-size:1.15rem; }
    .stat-num   { font-size:1.8rem; font-weight:900; color:var(--primary); line-height:1; }
    .stat-label { font-size:0.8rem; color:var(--muted); font-weight:600; margin-top:3px; }

    /* ── Tabs ── */
    .tab-bar { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
    .tab-btn {
      padding:11px 26px; border-radius:14px; border:2px solid #e2e8f0;
      font-weight:800; font-size:0.97rem; cursor:pointer;
      background:#fff; color:var(--muted); transition:all 0.2s;
    }
    .tab-btn:hover { border-color:var(--primary); color:var(--primary); }
    .tab-btn.active {
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      color:#fff; border-color:transparent;
      box-shadow:0 6px 18px rgba(62,80,119,0.25);
    }
    .tab-section { display:none; }
    .tab-section.active { display:block; }

    /* ── Category dropdown ── */
    .btn-module {
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      border:none; color:#fff; font-weight:800; border-radius:14px;
      padding:10px 16px; cursor:pointer; display:inline-flex; align-items:center; gap:8px;
    }
    .kids-cat-section   { display:none; }
    .kids-cat-section.active   { display:block; }
    .junior-cat-section { display:none; }
    .junior-cat-section.active { display:block; }
    .drop-item {
      display:block; width:100%; padding:13px 18px; border:none; background:none;
      text-align:left; font-weight:700; font-size:0.95rem; color:#0f172a;
      cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background 0.15s;
    }
    .drop-item:last-child { border-bottom:none; }
    .drop-item:hover  { background:#f0f7ff; color:var(--primary); }
    .drop-item.active { background:#eff6ff; color:var(--primary); font-weight:900; }

    /* ── Panel card ── */
    .panel-card {
      background:#fff; border:1px solid var(--border); border-radius:22px;
      padding:22px; margin-bottom:22px; box-shadow:var(--shadow);
      position:relative; overflow:hidden;
    }
    .panel-card::before { content:''; display:block; height:5px;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      position:absolute; top:0; left:0; right:0; }
    .panel-title { font-size:1.1rem; font-weight:900; color:var(--primary); margin-bottom:16px; }

    /* ── Course cards ── */
    .course-list { display:flex; flex-direction:column; gap:12px; }

    .course-card {
      display:flex; align-items:center; gap:14px;
      border-radius:16px; padding:14px 16px;
      position:relative; transition:transform 0.2s;
    }
    .course-card:hover { transform:translateY(-2px); }

    /* Unlocked */
    .course-card.unlocked {
      background:#f0f7ff; border:1px solid #bfdbfe;
    }
    /* Locked */
    .course-card.locked {
      background:#f8f8f8; border:1px solid #e2e8f0;
      opacity:0.75;
    }

    .course-thumb { flex-shrink:0; }
    .course-thumb img {
      width:56px; height:56px; object-fit:cover;
      border-radius:12px; border:1px solid #e5edf9;
    }
    .thumb-fallback {
      width:56px; height:56px; border-radius:12px;
      display:flex; align-items:center; justify-content:center; font-size:1.3rem;
    }
    .unlocked .thumb-fallback {
      background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff;
    }
    .locked .thumb-fallback { background:#e2e8f0; color:#94a3b8; }

    .course-body { flex:1; min-width:0; }
    .course-name { font-weight:900; font-size:0.97rem; color:#0f172a; margin-bottom:6px; }
    .locked .course-name { color:#94a3b8; }
    .course-meta { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .meta-chip {
      font-size:0.76rem; background:#e2e8f0; color:#475569;
      border-radius:999px; padding:3px 9px; font-weight:600;
    }
    .locked .meta-chip { background:#f1f1f1; color:#bbb; }
    .type-badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:0.78rem; font-weight:800; }
    .type-demo { background:#dbeafe; color:#1d4ed8; }
    .type-paid { background:#dcfce7; color:#166534; }
    .locked .type-badge { background:#e5e5e5; color:#aaa; }

    /* Lock / unlock badge */
    .status-badge-lock {
      display:inline-flex; align-items:center; gap:5px;
      padding:5px 12px; border-radius:999px; font-size:0.78rem; font-weight:800;
    }
    .badge-unlocked { background:#dcfce7; color:#166534; }
    .badge-locked   { background:#fee2e2; color:#991b1b; }

    /* Projects section */
    .proj-section { margin-top:14px; padding-top:14px; border-top:1px solid #dbeafe; }
    .proj-section-title { font-size:0.82rem; font-weight:800; color:#0f172a; margin-bottom:10px; }
    .proj-list { display:flex; flex-direction:column; gap:8px; }
    .proj-item {
      display:flex; align-items:center; gap:10px;
      background:#fff; border:1px solid #bfdbfe; border-radius:12px; padding:10px 13px;
    }
    .proj-item.proj-locked {
      background:#f8f8f8; border-color:#e2e8f0; opacity:0.72; filter:grayscale(0.3);
    }
    .proj-item img { max-height:60px; max-width:90px; border-radius:8px; }
    .proj-icon-fb {
      width:38px; height:38px; border-radius:8px;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-size:0.9rem; flex-shrink:0;
    }
    .proj-locked .proj-icon-fb { background:#e2e8f0; color:#94a3b8; }
    .proj-title { font-weight:800; font-size:0.85rem; color:#0f172a; flex:1; min-width:0; }
    .proj-locked .proj-title { color:#94a3b8; }
    .proj-actions { display:flex; gap:6px; flex-shrink:0; }
    .proj-btn {
      display:flex; align-items:center; gap:5px; font-size:0.78rem; font-weight:800;
      text-decoration:none; padding:7px 12px; border-radius:8px; color:#fff; white-space:nowrap;
    }
    .proj-btn:hover { color:#fff; filter:brightness(1.1); }
    .proj-pdf  { background:linear-gradient(135deg,#f97316,#ea580c); }
    .proj-link { background:linear-gradient(135deg,#3b82f6,#1d4ed8); }
    .proj-btn-locked {
      display:flex; align-items:center; gap:5px; font-size:0.78rem; font-weight:800;
      padding:7px 12px; border-radius:8px; background:#e2e8f0; color:#94a3b8; white-space:nowrap; cursor:default;
    }

    /* Lock overlay icon */
    .lock-icon {
      flex-shrink:0; width:40px; height:40px; border-radius:10px;
      background:#f1f5f9; display:flex; align-items:center; justify-content:center;
      font-size:1rem; color:#94a3b8;
    }

    .empty-box {
      text-align:center; padding:26px; border-radius:18px;
      background:#f8fbff; color:var(--muted);
      border:1px dashed #d9e9ff; font-weight:700;
    }

    /* ── Course row (teacher-style) ── */
    .kc-group-label { font-size:0.78rem; font-weight:900; text-transform:uppercase; letter-spacing:1px; color:var(--primary); padding:12px 4px 6px; }
    .kc-row { display:flex; align-items:center; gap:14px; width:100%; background:#f8fbff; border:1.5px solid #dbeafe; border-radius:14px; padding:14px 16px; margin-bottom:8px; cursor:pointer; text-align:left; transition:all 0.18s; }
    .kc-row:hover { background:#eff6ff; border-color:#93c5fd; transform:translateX(4px); }
    .kc-row.kc-locked { background:#f8f8f8; border-color:#e2e8f0; opacity:0.82; }
    .kc-row-icon { width:40px; height:40px; border-radius:11px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
    .kc-row.kc-locked .kc-row-icon { background:#e2e8f0; color:#94a3b8; }
    .kc-row-body { flex:1; min-width:0; }
    .kc-row-name { font-weight:900; font-size:0.97rem; color:#0f172a; margin-bottom:5px; }
    .kc-row.kc-locked .kc-row-name { color:#64748b; }
    .kc-row-meta { display:flex; gap:6px; flex-wrap:wrap; }
    .kc-arrow { color:#94a3b8; font-size:0.85rem; flex-shrink:0; }
    .kc-chip { font-size:0.73rem; font-weight:700; padding:3px 9px; border-radius:999px; }
    .kc-chip-blue   { background:#dbeafe; color:#1d4ed8; }
    .kc-chip-green  { background:#dcfce7; color:#166534; }
    .kc-chip-red    { background:#fee2e2; color:#991b1b; }
    .kc-chip-gray   { background:#f1f5f9; color:#64748b; }
    .kc-chip-purple { background:#ede9fe; color:#5b21b6; }
    .kc-back-btn { display:inline-flex; align-items:center; gap:8px; background:#fff; border:1.5px solid #dbeafe; border-radius:12px; padding:10px 18px; font-weight:800; font-size:0.9rem; color:#334155; cursor:pointer; margin-bottom:16px; transition:background 0.18s; }
    .kc-back-btn:hover { background:#f1f5f9; }

    @media (max-width:991px) {
      .app-shell { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: relative; }
      .stat-bar { grid-template-columns:1fr 1fr; }
    }
    @media (max-width:575px) {
      .stat-bar { grid-template-columns:1fr; }
      .proj-actions { flex-wrap:wrap; }
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
        <div class="brand-subtitle">STUDENT PANEL</div>
      </div>
    </div>

    <div class="student-box">
      <div class="student-avatar">
        <?php if (!empty($_SESSION["profile_picture"])): ?>
          <img src="uploads/profiles/<?= htmlspecialchars($_SESSION["profile_picture"]) ?>" alt="Profile">
        <?php else: ?>
          <?= strtoupper(substr($studentName, 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <p class="student-name"><?= htmlspecialchars($studentName) ?></p>
        <p class="student-role">Student</p>
      </div>
    </div>

    <div class="nav-title">MAIN</div>
    <div class="nav-custom">
      <a href="student_dashboard.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
      </a>
      <a href="student_courses.php" class="nav-link-custom active">
        <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>My Courses</span>
      </a>
      <a href="student_projects.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-folder-open"></i></span><span>My Projects</span></a>
      <a href="student_classes.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span>
      </a>
      <a href="student_assignments.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span>
      </a>
      <a href="student_quizzes.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span>
      </a>
      <a href="student_certificates.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span>
      </a>
      <a href="student_chat.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span>
      </a>
      <a href="student_contact.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-envelope"></i></span><span>Contact</span>
      </a>
    </div>
  </div>

  <div class="sidebar-bottom">
    <a href="student_profile.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span>
    </a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span>
    </a>
  </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">
  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
  </div>

  <div class="topbar">
    <div>
      <h1>My Courses</h1>
      <p>Your enrolled courses are unlocked — others are locked.</p>
    </div>
    <div class="topbar-badge"><i class="fas fa-user-graduate me-2"></i><?= htmlspecialchars($studentName) ?></div>
  </div>

  <!-- Stats -->
  <div class="stat-bar">
    <div class="stat-box">
      <div class="stat-icon"><i class="fas fa-lock-open"></i></div>
      <div class="stat-num"><?= $enrolledCount ?></div>
      <div class="stat-label">Enrolled Courses</div>
    </div>
    <div class="stat-box">
      <div class="stat-icon"><i class="fas fa-book"></i></div>
      <div class="stat-num"><?= $totalCourses ?></div>
      <div class="stat-label">Total Courses</div>
    </div>
    <div class="stat-box">
      <div class="stat-icon"><i class="fas fa-lock"></i></div>
      <div class="stat-num"><?= $totalCourses - $enrolledCount ?></div>
      <div class="stat-label">Locked Courses</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('kids',   this)"><i class="fas fa-child me-1"></i> Kids</button>
    <button class="tab-btn"        onclick="switchTab('junior', this)"><i class="fas fa-code me-1"></i> Junior</button>
  </div>

  <!-- ── Kids ── -->
  <div id="tab-kids" class="tab-section active">

    <!-- List view -->
    <div id="kids-list-view">
      <div class="panel-card">
        <div class="panel-title"><i class="fas fa-child me-2"></i>Kids Courses <span style="font-size:0.82rem;font-weight:600;color:var(--muted);">(<?= count($kidsCourses) ?>)</span></div>
        <?php if (empty($kidsCourses)): ?>
          <div class="empty-box">No Kids courses available yet.</div>
        <?php else: ?>
          <?php
            $kidsGrouped = [];
            foreach ($kidsCourses as $kc) { $kidsGrouped[$kc['category'] ?: ''][] = $kc; }
            foreach ($kidsGrouped as $grpLabel => $grpItems):
          ?>
            <?php if ($grpLabel !== ''): ?>
              <div class="kc-group-label"><i class="fas fa-folder me-2"></i><?= htmlspecialchars($grpLabel) ?></div>
            <?php endif; ?>
            <?php foreach ($grpItems as $kc):
              $kEnrolled  = in_array($kc['id'], $enrolledIds);
              $kProjCount = count($kidsCourseProjects[$kc['id']] ?? []);
            ?>
              <button class="kc-row <?= $kEnrolled ? '' : 'kc-locked' ?>" onclick="openKidsCourse(<?= $kc['id'] ?>)" type="button">
                <div class="kc-row-icon"><i class="fas fa-<?= $kEnrolled ? 'graduation-cap' : 'lock' ?>"></i></div>
                <div class="kc-row-body">
                  <div class="kc-row-name"><?= htmlspecialchars($kc['course_name']) ?></div>
                  <div class="kc-row-meta">
                    <?php if ($kEnrolled): ?>
                      <span class="kc-chip kc-chip-green"><i class="fas fa-lock-open me-1"></i>Enrolled</span>
                    <?php else: ?>
                      <span class="kc-chip kc-chip-red"><i class="fas fa-lock me-1"></i>Locked</span>
                    <?php endif; ?>
                    <?php if ($kProjCount > 0): ?>
                      <span class="kc-chip kc-chip-blue"><i class="fas fa-folder-open me-1"></i><?= $kProjCount ?> project<?= $kProjCount !== 1 ? 's' : '' ?></span>
                    <?php else: ?>
                      <span class="kc-chip kc-chip-gray"><i class="fas fa-folder me-1"></i>No projects</span>
                    <?php endif; ?>
                  </div>
                </div>
                <i class="fas fa-chevron-right kc-arrow"></i>
              </button>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Project view -->
    <div id="kids-proj-view" style="display:none;">
      <button class="kc-back-btn" onclick="closeKidsCourse()" type="button"><i class="fas fa-arrow-left"></i> Back to Courses</button>
      <?php foreach ($kidsCourses as $kc):
        $kEnrolled = in_array($kc['id'], $enrolledIds);
        $kProjects = $kidsCourseProjects[$kc['id']] ?? [];
      ?>
        <div id="kids-proj-<?= $kc['id'] ?>" class="kids-proj-panel" style="display:none;">
          <div class="panel-card">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
              <div style="width:44px;height:44px;border-radius:12px;background:<?= $kEnrolled ? 'linear-gradient(135deg,var(--primary),var(--secondary))' : '#e2e8f0' ?>;color:<?= $kEnrolled ? '#fff' : '#94a3b8' ?>;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                <i class="fas fa-<?= $kEnrolled ? 'graduation-cap' : 'lock' ?>"></i>
              </div>
              <div>
                <div style="font-size:1.1rem;font-weight:900;color:#0f172a;"><?= htmlspecialchars($kc['course_name']) ?></div>
                <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap;">
                  <?php if (!empty($kc['category'])): ?>
                    <span class="kc-chip kc-chip-purple"><i class="fas fa-folder me-1"></i><?= htmlspecialchars($kc['category']) ?></span>
                  <?php endif; ?>
                  <?php if ($kEnrolled): ?>
                    <span class="kc-chip kc-chip-green"><i class="fas fa-lock-open me-1"></i>Enrolled</span>
                  <?php else: ?>
                    <span class="kc-chip kc-chip-red"><i class="fas fa-lock me-1"></i>Locked</span>
                  <?php endif; ?>
                  <span class="kc-chip kc-chip-blue"><i class="fas fa-folder-open me-1"></i><?= count($kProjects) ?> project<?= count($kProjects)!==1?'s':'' ?></span>
                </div>
              </div>
            </div>
            <?php if (empty($kProjects)): ?>
              <div class="empty-box"><i class="fas fa-folder-open" style="font-size:2rem;color:#dbeafe;display:block;margin-bottom:10px;"></i>No projects for this course yet.</div>
            <?php else: ?>
              <div class="proj-list">
                <?php foreach ($kProjects as $p):
                  $ph = !empty($p['pdf_url']) ? (strpos($p['pdf_url'],'http')===0 ? $p['pdf_url'] : 'uploads/pdfs/'.$p['pdf_url']) : '';
                ?>
                  <div class="proj-item <?= !$kEnrolled ? 'proj-locked' : '' ?>">
                    <?php if (!empty($p['image'])): ?>
                      <img src="<?= htmlspecialchars($p['image']) ?>" alt="" style="max-height:48px;max-width:72px;border-radius:8px;<?= !$kEnrolled ? 'filter:grayscale(1);opacity:0.5;' : '' ?>">
                    <?php else: ?>
                      <div class="proj-icon-fb <?= !$kEnrolled ? 'proj-locked' : '' ?>"><i class="fas fa-gamepad"></i></div>
                    <?php endif; ?>
                    <div class="proj-title"><?= htmlspecialchars($p['title']) ?></div>
                    <div class="proj-actions">
                      <?php if ($kEnrolled): ?>
                        <?php if ($ph): ?><a href="<?= htmlspecialchars($ph) ?>" target="_blank" class="proj-btn proj-pdf"><i class="fas fa-file-pdf"></i> PDF</a><?php endif; ?>
                        <?php if (!empty($p['url'])): ?><a href="<?= htmlspecialchars($p['url']) ?>" target="_blank" class="proj-btn proj-link"><i class="fas fa-arrow-up-right-from-square"></i> Open</a><?php endif; ?>
                      <?php else: ?>
                        <span class="proj-btn-locked"><i class="fas fa-file-pdf"></i> PDF</span>
                        <span class="proj-btn-locked"><i class="fas fa-lock"></i> Locked</span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- ── Junior ── -->
  <div id="tab-junior" class="tab-section">

    <!-- List view -->
    <div id="junior-list-view">
      <div class="panel-card">
        <div class="panel-title"><i class="fas fa-code me-2"></i>Junior Courses <span style="font-size:0.82rem;font-weight:600;color:var(--muted);">(<?= count($juniorCourses) ?>)</span></div>
        <?php if (empty($juniorCourses)): ?>
          <div class="empty-box">No Junior courses available yet.</div>
        <?php else: ?>
          <?php
            $juniorGrouped = [];
            foreach ($juniorCourses as $jc) { $juniorGrouped[$jc['category'] ?: ''][] = $jc; }
            foreach ($juniorGrouped as $grpLabel => $grpItems):
          ?>
            <?php if ($grpLabel !== ''): ?>
              <div class="kc-group-label"><i class="fas fa-folder me-2"></i><?= htmlspecialchars($grpLabel) ?></div>
            <?php endif; ?>
            <?php foreach ($grpItems as $jc):
              $jEnrolled  = in_array($jc['id'], $enrolledIds);
              $jProjCount = count($juniorCourseProjects[$jc['id']] ?? []);
            ?>
              <button class="kc-row <?= $jEnrolled ? '' : 'kc-locked' ?>" onclick="openJuniorCourse(<?= $jc['id'] ?>)" type="button">
                <div class="kc-row-icon"><i class="fas fa-<?= $jEnrolled ? 'code' : 'lock' ?>"></i></div>
                <div class="kc-row-body">
                  <div class="kc-row-name"><?= htmlspecialchars($jc['course_name']) ?></div>
                  <div class="kc-row-meta">
                    <?php if ($jEnrolled): ?>
                      <span class="kc-chip kc-chip-green"><i class="fas fa-lock-open me-1"></i>Enrolled</span>
                    <?php else: ?>
                      <span class="kc-chip kc-chip-red"><i class="fas fa-lock me-1"></i>Locked</span>
                    <?php endif; ?>
                    <?php if ($jProjCount > 0): ?>
                      <span class="kc-chip kc-chip-blue"><i class="fas fa-folder-open me-1"></i><?= $jProjCount ?> project<?= $jProjCount !== 1 ? 's' : '' ?></span>
                    <?php else: ?>
                      <span class="kc-chip kc-chip-gray"><i class="fas fa-folder me-1"></i>No projects</span>
                    <?php endif; ?>
                  </div>
                </div>
                <i class="fas fa-chevron-right kc-arrow"></i>
              </button>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Project view -->
    <div id="junior-proj-view" style="display:none;">
      <button class="kc-back-btn" onclick="closeJuniorCourse()" type="button"><i class="fas fa-arrow-left"></i> Back to Courses</button>
      <?php foreach ($juniorCourses as $jc):
        $jEnrolled = in_array($jc['id'], $enrolledIds);
        $jProjects = $juniorCourseProjects[$jc['id']] ?? [];
      ?>
        <div id="junior-proj-<?= $jc['id'] ?>" class="junior-proj-panel" style="display:none;">
          <div class="panel-card">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
              <div style="width:44px;height:44px;border-radius:12px;background:<?= $jEnrolled ? 'linear-gradient(135deg,var(--primary),var(--secondary))' : '#e2e8f0' ?>;color:<?= $jEnrolled ? '#fff' : '#94a3b8' ?>;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                <i class="fas fa-<?= $jEnrolled ? 'code' : 'lock' ?>"></i>
              </div>
              <div>
                <div style="font-size:1.1rem;font-weight:900;color:#0f172a;"><?= htmlspecialchars($jc['course_name']) ?></div>
                <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap;">
                  <?php if (!empty($jc['category'])): ?>
                    <span class="kc-chip kc-chip-purple"><i class="fas fa-folder me-1"></i><?= htmlspecialchars($jc['category']) ?></span>
                  <?php endif; ?>
                  <?php if ($jEnrolled): ?>
                    <span class="kc-chip kc-chip-green"><i class="fas fa-lock-open me-1"></i>Enrolled</span>
                  <?php else: ?>
                    <span class="kc-chip kc-chip-red"><i class="fas fa-lock me-1"></i>Locked</span>
                  <?php endif; ?>
                  <span class="kc-chip kc-chip-blue"><i class="fas fa-folder-open me-1"></i><?= count($jProjects) ?> project<?= count($jProjects)!==1?'s':'' ?></span>
                </div>
              </div>
            </div>
            <?php if (empty($jProjects)): ?>
              <div class="empty-box"><i class="fas fa-folder-open" style="font-size:2rem;color:#dbeafe;display:block;margin-bottom:10px;"></i>No projects for this course yet.</div>
            <?php else: ?>
              <div class="proj-list">
                <?php foreach ($jProjects as $p):
                  $ph = !empty($p['pdf_url']) ? (strpos($p['pdf_url'],'http')===0 ? $p['pdf_url'] : 'uploads/pdfs/'.$p['pdf_url']) : '';
                ?>
                  <div class="proj-item <?= !$jEnrolled ? 'proj-locked' : '' ?>">
                    <?php if (!empty($p['image'])): ?>
                      <img src="<?= htmlspecialchars($p['image']) ?>" alt="" style="max-height:48px;max-width:72px;border-radius:8px;<?= !$jEnrolled ? 'filter:grayscale(1);opacity:0.5;' : '' ?>">
                    <?php else: ?>
                      <div class="proj-icon-fb <?= !$jEnrolled ? 'proj-locked' : '' ?>"><i class="fas fa-gamepad"></i></div>
                    <?php endif; ?>
                    <div class="proj-title"><?= htmlspecialchars($p['title']) ?></div>
                    <div class="proj-actions">
                      <?php if ($jEnrolled): ?>
                        <?php if ($ph): ?><a href="<?= htmlspecialchars($ph) ?>" target="_blank" class="proj-btn proj-pdf"><i class="fas fa-file-pdf"></i> PDF</a><?php endif; ?>
                        <?php if (!empty($p['url'])): ?><a href="<?= htmlspecialchars($p['url']) ?>" target="_blank" class="proj-btn proj-link"><i class="fas fa-arrow-up-right-from-square"></i> Open</a><?php endif; ?>
                      <?php else: ?>
                        <span class="proj-btn-locked"><i class="fas fa-file-pdf"></i> PDF</span>
                        <span class="proj-btn-locked"><i class="fas fa-lock"></i> Locked</span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>


</div>
</div><!-- /.app-shell -->

<?php
function renderCourseList(array $courses, array $enrolledIds, $conn): string {
    if (empty($courses)) return '<div class="empty-box">No courses in this module yet.</div>';
    ob_start();
    echo '<div class="course-list">';
    foreach ($courses as $c):
        $enrolled = in_array($c["id"], $enrolledIds);
        $cls      = $enrolled ? "unlocked" : "locked";
        $projects = fetchCourseProjects($conn, (int)$c["id"], (string)($c["section"] ?? ''), (string)($c["category"] ?? ''));

        if ($enrolled) {
            $icon = "fa-lock-open"; $badgeCls = "badge-unlocked"; $badgeTxt = "Enrolled";
        } else {
            $icon = "fa-lock";      $badgeCls = "badge-locked";   $badgeTxt = "Locked";
        }
        ?>
        <div class="course-card <?= $cls ?>" style="flex-direction:column;align-items:stretch;">
          <!-- Course header row -->
          <div style="display:flex;align-items:center;gap:14px;">
            <div class="course-thumb">
              <?php if (!empty($c["image"])): ?>
                <img src="<?= htmlspecialchars($c["image"]) ?>" alt="">
              <?php else: ?>
                <div class="thumb-fallback"><i class="fas fa-graduation-cap"></i></div>
              <?php endif; ?>
            </div>
            <div class="course-body">
              <div class="course-name"><?= htmlspecialchars($c["course_name"]) ?></div>
              <div class="course-meta">
                <?php if (!empty($c["category"])): ?>
                  <span class="meta-chip" style="background:#ede9fe;color:#5b21b6;"><i class="fas fa-folder"></i> <?= htmlspecialchars($c["category"]) ?></span>
                <?php endif; ?>
                <span class="status-badge-lock <?= $badgeCls ?>">
                  <i class="fas <?= $icon ?>"></i> <?= $badgeTxt ?>
                </span>
                <?php if (!empty($projects)): ?>
                  <span class="meta-chip" style="background:#f1f5f9;color:#64748b;">
                    <i class="fas fa-folder me-1"></i><?= count($projects) ?> project<?= count($projects) !== 1 ? 's' : '' ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
            <?php if (!$enrolled): ?>
              <div class="lock-icon"><i class="fas fa-lock"></i></div>
            <?php endif; ?>
          </div>

          <!-- Projects section (always shown) -->
          <?php if (!empty($projects)): ?>
          <div class="proj-section">
            <div class="proj-section-title">
              <i class="fas fa-folder-open me-2" style="color:<?= $enrolled ? 'var(--primary)' : '#94a3b8' ?>"></i>
              Projects (<?= count($projects) ?>)
              <?php if (!$enrolled): ?>
                <span style="font-size:0.75rem;font-weight:700;background:#fee2e2;color:#991b1b;border-radius:999px;padding:2px 10px;margin-left:8px;"><i class="fas fa-lock me-1"></i>Enroll to access</span>
              <?php endif; ?>
            </div>
            <div class="proj-list">
              <?php foreach ($projects as $p): ?>
                <div class="proj-item <?= !$enrolled ? 'proj-locked' : '' ?>">
                  <?php if (!empty($p["image"])): ?>
                    <img src="<?= htmlspecialchars($p["image"]) ?>" alt="" style="max-height:55px;max-width:80px;border-radius:8px;<?= !$enrolled ? 'filter:grayscale(1);opacity:0.5;' : '' ?>">
                  <?php else: ?>
                    <div class="proj-icon-fb"><i class="fas fa-gamepad"></i></div>
                  <?php endif; ?>
                  <div class="proj-title"><?= htmlspecialchars($p["title"]) ?></div>
                  <div class="proj-actions">
                    <?php if ($enrolled): ?>
                      <?php
                        $ph = !empty($p["pdf_url"]) ? (strpos($p["pdf_url"],'http')===0 ? $p["pdf_url"] : 'uploads/pdfs/'.$p["pdf_url"]) : '';
                      ?>
                      <?php if ($ph): ?>
                        <a href="<?= htmlspecialchars($ph) ?>" target="_blank" class="proj-btn proj-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                      <?php endif; ?>
                      <?php if (!empty($p["url"])): ?>
                        <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" class="proj-btn proj-link"><i class="fas fa-arrow-up-right-from-square"></i> Open</a>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="proj-btn-locked"><i class="fas fa-file-pdf"></i> PDF</span>
                      <span class="proj-btn-locked"><i class="fas fa-lock"></i> Locked</span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
    <?php endforeach;
    echo '</div>';
    return ob_get_clean();
}
?>

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

function openKidsCourse(id) {
  document.querySelectorAll('.kids-proj-panel').forEach(p => p.style.display = 'none');
  const panel = document.getElementById('kids-proj-' + id);
  if (panel) panel.style.display = 'block';
  document.getElementById('kids-list-view').style.display = 'none';
  document.getElementById('kids-proj-view').style.display = 'block';
}
function closeKidsCourse() {
  document.getElementById('kids-proj-view').style.display = 'none';
  document.getElementById('kids-list-view').style.display = 'block';
}

function openJuniorCourse(id) {
  document.querySelectorAll('.junior-proj-panel').forEach(p => p.style.display = 'none');
  const panel = document.getElementById('junior-proj-' + id);
  if (panel) panel.style.display = 'block';
  document.getElementById('junior-list-view').style.display = 'none';
  document.getElementById('junior-proj-view').style.display = 'block';
}
function closeJuniorCourse() {
  document.getElementById('junior-proj-view').style.display = 'none';
  document.getElementById('junior-list-view').style.display = 'block';
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>


