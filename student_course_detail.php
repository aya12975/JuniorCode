<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";
$courseId    = (int)($_GET["course_id"] ?? 0);

if ($courseId === 0) {
    header("Location: student_courses.php");
    exit();
}

// Verify the student is enrolled in this course
$check = $conn->prepare("SELECT id FROM student_enrollments WHERE student_name = ? AND course_id = ?");
$check->bind_param("si", $studentName, $courseId);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    header("Location: student_courses.php");
    exit();
}
$check->close();

// Fetch course info
$cStmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$cStmt->bind_param("i", $courseId);
$cStmt->execute();
$course = $cStmt->get_result()->fetch_assoc();
$cStmt->close();

if (!$course) {
    header("Location: student_courses.php");
    exit();
}

// Fetch projects for this course
$pStmt = $conn->prepare("SELECT * FROM course_projects WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
$pStmt->bind_param("i", $courseId);
$pStmt->execute();
$projects = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pStmt->close();

$gradients = [
    "#7a5b35,#6aa35c","#18b6d4,#79d9ef","#0f86d6,#45c1ec",
    "#7c3aed,#a78bfa","#059669,#34d399","#dc2626,#f87171",
    "#d97706,#fbbf24","#1d4ed8,#60a5fa",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($course["course_name"]) ?> | JuniorCode</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; }
* { box-sizing:border-box; }
body { margin:0; font-family:Arial,sans-serif; background:#eef2f7; color:#27344f; }

/* ── App shell ── */
.app-shell { display:flex; min-height:100vh; }

/* ── Sidebar ── */
.sidebar { width:285px; flex-shrink:0; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:#fff; padding:0; position:sticky; top:0; height:100vh; display:flex; flex-direction:column; transition:width .3s,padding .3s,min-width .3s; overflow-y:auto; }
body.sidebar-collapsed .sidebar { width:0; padding:0; min-width:0; overflow:hidden; }
.sidebar-top-area { padding:0 18px 18px; }
.brand { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
.brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
.brand-title { font-weight:900; font-size:1.1rem; color:#fff; line-height:1.2; }
.brand-subtitle { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
.nav-custom { display:flex; flex-direction:column; gap:4px; }
.student-box { display:flex; align-items:center; gap:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:14px; margin-bottom:18px; }
.student-avatar { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; font-weight:bold; font-size:18px; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; }
.student-avatar img { width:100%; height:100%; object-fit:cover; }
.student-name { font-weight:800; margin:0; color:#fff; }
.student-role { margin:0; color:rgba(255,255,255,0.55); font-size:.85rem; }
.nav-link-custom { display:flex; align-items:center; gap:12px; text-decoration:none; color:rgba(255,255,255,0.78); padding:12px 14px; border-radius:14px; font-weight:700; transition:all .22s; }
.nav-link-custom:hover { background:rgba(255,255,255,0.09); color:#fff; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 8px 20px rgba(30,50,100,0.35); }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.nav-link-custom.active .nav-icon { background:rgba(255,255,255,0.18); }
.sidebar-bottom { padding:16px 18px; margin-top:auto; }

/* ── Main ── */
.main-content { flex:1; min-width:0; }
.page { padding:28px 32px 44px; }

/* ── Hamburger ── */
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background .2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

/* ── Topbar ── */
.topbar { background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; padding:22px 26px; margin-bottom:26px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; color:#fff; box-shadow:0 12px 28px rgba(37,99,235,0.3); }
.topbar h1 { font-size:1.6rem; font-weight:900; margin:0; }
.topbar p  { margin:4px 0 0; opacity:.88; font-size:.95rem; }
.back-btn { background:rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.3); color:#fff; border-radius:10px; padding:9px 16px; font-weight:700; font-size:.85rem; text-decoration:none; display:inline-flex; align-items:center; gap:7px; transition:background .2s; white-space:nowrap; }
.back-btn:hover { background:rgba(255,255,255,0.28); color:#fff; }

/* ── Content area (teacher style) ── */
.content-area { background:white; border-radius:8px; padding:28px 30px; min-height:380px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.content-title { font-size:24px; font-weight:800; margin:0 0 0; }
.content-divider { border:none; border-top:2px solid #d7dce7; margin:14px 0 26px; }

/* ── Course block ── */
.course-block-title {
    font-size:17px; font-weight:800; color:#1f2f63;
    padding:12px 16px; background:#f0f4ff;
    border-left:4px solid #1f2f63; border-radius:0 6px 6px 0;
    margin-bottom:0;
}
.course-block-title span { font-size:13px; font-weight:600; color:#647596; margin-left:8px; }

/* ── Project rows ── */
.projects-section { background:#fff; border:1px solid #d8dce6; border-radius:0 0 8px 8px; }

.project-row {
    min-height:120px;
    display:grid;
    grid-template-columns: 230px 1fr auto;
    align-items:center;
    gap:28px;
    padding:18px 20px;
    border-bottom:1px solid #d8dce6;
}
.project-row:last-child { border-bottom:none; }

.project-row img {
    width:220px; height:120px;
    object-fit:cover; border-radius:4px; display:block;
}
.proj-placeholder {
    width:220px; height:120px; border-radius:4px;
    background:linear-gradient(135deg,#3e5077,#143674);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:1.8rem;
}

.project-row h3 { margin:0; color:#33415f; font-size:20px; font-weight:700; }

.proj-actions { display:flex; gap:12px; align-items:center; }
.btn-view-proj {
    border:none; color:white; font-weight:700;
    padding:12px 18px; border-radius:4px; cursor:pointer;
    font-size:.9rem; text-decoration:none; display:inline-block;
    transition:background .2s; white-space:nowrap;
}
.btn-view-proj.green  { background:#36a66f; }
.btn-view-proj.green:hover  { background:#2d925f; color:#fff; }
.btn-view-proj.purple { background:#5d3db3; }
.btn-view-proj.purple:hover { background:#4d3299; color:#fff; }
.btn-view-proj.disabled { background:#c8cdd9; cursor:default; }

.no-projects { padding:24px 20px; color:#9aabc0; font-style:italic; font-size:14px; text-align:center; border:1px solid #d8dce6; border-radius:0 0 8px 8px; }

/* ── Empty state ── */
.empty-msg { height:300px; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#647596; gap:12px; font-style:italic; font-size:16px; }
.empty-msg i { font-size:2.2rem; opacity:.28; }

@media (max-width:900px) {
    .project-row { grid-template-columns:1fr; gap:14px; }
    .project-row img, .proj-placeholder { width:100%; height:auto; min-height:100px; }
    .proj-actions { flex-wrap:wrap; }
    .page { padding:16px; }
}
</style>
</head>
<body>
<div class="app-shell">

<!-- ── Sidebar ── -->
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
      <a href="student_dashboard.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
      <a href="student_courses.php" class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>My Courses</span></a>
      <a href="student_projects.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-folder-open"></i></span><span>My Projects</span></a>
      <a href="student_classes.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span></a>
      <a href="student_assignments.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span></a>
      <a href="student_quizzes.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span></a>
      <a href="student_certificates.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
      <a href="student_chat.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span></a>
      <a href="student_contact.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-envelope"></i></span><span>Contact</span></a>
    </div>
  </div>

  <div class="sidebar-bottom">
    <a href="student_profile.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
  </div>
</aside>

<!-- ── Main Content ── -->
<div class="main-content">
  <div class="page">

    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
    </div>

    <div class="topbar">
      <div>
        <h1><i class="fas fa-graduation-cap me-2"></i><?= htmlspecialchars($course["course_name"]) ?></h1>
        <p>Your enrolled course projects</p>
      </div>
      <a href="student_courses.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Courses
      </a>
    </div>

    <div class="content-area">
      <div class="content-title"><?= htmlspecialchars($course["course_name"]) ?>
        <?php if (!empty($course["level"])): ?>
          <span style="font-size:15px;font-weight:600;color:#647596;margin-left:8px;"><?= ucfirst($course["level"]) ?> level</span>
        <?php endif; ?>
      </div>
      <hr class="content-divider">

      <?php if (empty($projects)): ?>
        <div class="empty-msg">
          <i class="fas fa-folder-open"></i>
          No projects have been added to this course yet.
        </div>
      <?php else: ?>

        <div class="course-block-title">
          Projects <span>(<?= count($projects) ?>)</span>
        </div>
        <div class="projects-section">
          <?php foreach ($projects as $i => $p):
            $pdfH = !empty($p["pdf_url"])
              ? (strpos($p["pdf_url"], 'http') === 0 ? $p["pdf_url"] : 'uploads/pdfs/'.$p["pdf_url"])
              : '';
          ?>
          <div class="project-row">
            <?php if (!empty($p["image"])): ?>
              <img src="<?= htmlspecialchars($p["image"]) ?>" alt="<?= htmlspecialchars($p["title"]) ?>">
            <?php else: ?>
              <div class="proj-placeholder"><i class="fas fa-gamepad"></i></div>
            <?php endif; ?>

            <h3>Project <?= $i + 1 ?>: <?= htmlspecialchars($p["title"]) ?></h3>

            <div class="proj-actions">
              <?php if (!empty($p["url"])): ?>
                <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" class="btn-view-proj green">
                  <i class="fas fa-arrow-up-right-from-square me-1"></i>View Project
                </a>
              <?php else: ?>
                <span class="btn-view-proj disabled">No Link</span>
              <?php endif; ?>

              <?php if ($pdfH): ?>
                <a href="<?= htmlspecialchars($pdfH) ?>" target="_blank" class="btn-view-proj purple">
                  <i class="fas fa-file-pdf me-1"></i>Check Course
                </a>
              <?php else: ?>
                <span class="btn-view-proj disabled">No PDF</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      <?php endif; ?>
    </div>

  </div>
</div>

</div><!-- /.app-shell -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
