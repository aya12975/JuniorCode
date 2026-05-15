<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";

/* ── Ensure tables/columns ── */
$conn->query("CREATE TABLE IF NOT EXISTS student_enrollments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(255) NOT NULL,
    course_id    INT          NOT NULL,
    enrolled_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment (student_name, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS course_projects (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    course_id  INT          DEFAULT NULL,
    section    VARCHAR(50)  NOT NULL DEFAULT 'kids',
    category   VARCHAR(100) NOT NULL DEFAULT '',
    title      VARCHAR(255) NOT NULL,
    url        TEXT         NOT NULL DEFAULT '',
    image      TEXT         NOT NULL DEFAULT '',
    pdf_url    TEXT         NOT NULL DEFAULT '',
    sort_order INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS course_id INT DEFAULT NULL");

/* ── Enrolled course IDs ── */
$enrolledIds = [];
$eStmt = $conn->prepare("SELECT course_id FROM student_enrollments WHERE student_name = ?");
if ($eStmt) {
    $eStmt->bind_param("s", $studentName);
    $eStmt->execute();
    $enrolledIds = array_column($eStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'course_id');
    $eStmt->close();
}

/* ── Fetch enrolled courses with their projects ── */
$coursesWithProjects = [];
foreach ($enrolledIds as $cid) {
    $cs = $conn->prepare("SELECT * FROM courses WHERE id = ? AND status = 'active'");
    if (!$cs) continue;
    $cs->bind_param("i", $cid);
    $cs->execute();
    $course = $cs->get_result()->fetch_assoc();
    $cs->close();
    if (!$course) continue;

    $ps = $conn->prepare("SELECT * FROM course_projects WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
    if (!$ps) continue;
    $ps->bind_param("i", $cid);
    $ps->execute();
    $projects = $ps->get_result()->fetch_all(MYSQLI_ASSOC);
    $ps->close();

    $course["projects"] = $projects;
    $coursesWithProjects[] = $course;
}

$totalProjects = array_sum(array_map(fn($c) => count($c["projects"]), $coursesWithProjects));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Projects | JuniorCode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --shadow:0 18px 45px rgba(37,99,235,0.08); --border:#dbeafe; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:Arial,sans-serif; color:var(--dark);
      background:radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),
                 radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),
                 linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); }
    .app-shell { min-height:100vh; display:flex; }

    /* Sidebar */
    .sidebar { width:285px; flex-shrink:0; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:#fff; padding:0; position:sticky; top:0; height:100vh; display:flex; flex-direction:column; transition:width .3s,padding .3s; overflow-y:auto; }
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
    .student-role { margin:0; color:rgba(255,255,255,0.55); font-size:0.85rem; }
    .nav-link-custom { display:flex; align-items:center; gap:12px; text-decoration:none; color:rgba(255,255,255,0.78); padding:12px 14px; border-radius:14px; margin:4px 0; font-weight:700; transition:all .22s; }
    .nav-link-custom:hover { background:rgba(255,255,255,0.09); color:#fff; }
    .nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 8px 20px rgba(30,50,100,0.35); }
    .nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
    .nav-link-custom.active .nav-icon { background:rgba(255,255,255,0.18); }
    .sidebar-bottom { padding:16px 18px; margin-top:auto; }

    /* Main */
    .main { flex:1; padding:28px; min-height:100vh; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .topbar { background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; padding:22px 26px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; color:#fff; box-shadow:0 12px 28px rgba(37,99,235,0.28); }
    .topbar h1 { font-size:1.7rem; font-weight:900; margin:0; }
    .topbar p  { margin:4px 0 0; opacity:.85; }
    .topbar-badge { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.2); border-radius:12px; padding:10px 18px; font-weight:700; font-size:0.9rem; }

    /* Stats */
    .stat-bar { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
    .stat-box { background:#fff; border:1px solid var(--border); border-radius:18px; padding:18px 20px; text-align:center; box-shadow:var(--shadow); position:relative; overflow:hidden; }
    .stat-box::before { content:''; display:block; height:4px; background:linear-gradient(135deg,var(--primary),var(--secondary)); position:absolute; top:0; left:0; right:0; }
    .stat-icon { width:42px; height:42px; border-radius:12px; margin:6px auto 10px; background:#f1f5f9; color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:1.15rem; }
    .stat-num   { font-size:1.8rem; font-weight:900; color:var(--primary); line-height:1; }
    .stat-label { font-size:0.8rem; color:var(--muted); font-weight:600; margin-top:3px; }

    /* Course block */
    .course-block { background:#fff; border:1.5px solid var(--border); border-radius:22px; margin-bottom:22px; overflow:hidden; box-shadow:var(--shadow); }
    .course-block-header { display:flex; align-items:center; gap:14px; padding:18px 22px; background:linear-gradient(135deg,rgba(62,80,119,0.06),rgba(20,54,116,0.04)); border-bottom:1.5px solid var(--border); }
    .course-block-icon { width:48px; height:48px; border-radius:13px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
    .course-block-name { font-weight:900; font-size:1.05rem; color:#0f172a; }
    .course-block-meta { font-size:0.8rem; color:var(--muted); margin-top:3px; }
    .section-chip { font-size:0.72rem; font-weight:700; padding:2px 10px; border-radius:999px; }
    .chip-kids   { background:#dbeafe; color:#1d4ed8; }
    .chip-junior { background:#ede9fe; color:#5b21b6; }
    .chip-demo   { background:#dcfce7; color:#166534; }

    /* Project items */
    .proj-list { padding:16px 22px; display:flex; flex-direction:column; gap:10px; }
    .proj-item { display:flex; align-items:center; gap:14px; background:#f8fbff; border:1px solid var(--border); border-radius:14px; padding:13px 16px; transition:box-shadow .2s; }
    .proj-item:hover { box-shadow:0 4px 16px rgba(62,80,119,0.1); }
    .proj-thumb { width:50px; height:50px; border-radius:11px; object-fit:cover; flex-shrink:0; border:1px solid #dbeafe; }
    .proj-thumb-fb { width:50px; height:50px; border-radius:11px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
    .proj-title { flex:1; font-weight:800; font-size:0.92rem; color:#0f172a; }
    .proj-btns { display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap; }
    .proj-btn { display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; font-weight:800; padding:8px 16px; border-radius:10px; text-decoration:none; white-space:nowrap; transition:filter .2s; }
    .proj-btn:hover { filter:brightness(1.1); }
    .proj-btn-link { background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:#fff; }
    .proj-btn-link:hover { color:#fff; }
    .proj-btn-pdf  { background:linear-gradient(135deg,#f97316,#ea580c); color:#fff; }
    .proj-btn-pdf:hover  { color:#fff; }
    .proj-btn-none { background:#f1f5f9; color:#94a3b8; cursor:default; }

    .no-projects { text-align:center; padding:20px; color:var(--muted); font-size:0.88rem; font-weight:600; }
    .no-projects i { display:block; font-size:1.6rem; color:#dbeafe; margin-bottom:8px; }

    .empty-state { text-align:center; padding:56px 24px; background:#fff; border:1.5px dashed var(--border); border-radius:22px; }
    .empty-state i { font-size:3rem; color:#dbeafe; display:block; margin-bottom:16px; }
    .empty-state h3 { font-weight:900; color:#334155; margin-bottom:8px; }
    .empty-state p { color:var(--muted); font-size:0.92rem; }

    @media (max-width:768px) { .stat-bar { grid-template-columns:1fr 1fr; } .app-shell { flex-direction:column; } .sidebar { width:100%; height:auto; position:relative; } }
    @media (max-width:480px) { .stat-bar { grid-template-columns:1fr; } .proj-btns { flex-direction:column; } }
  </style>
</head>
<body>
<div class="app-shell">

<aside class="sidebar">
  <div class="sidebar-top-area">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="Logo">
      <div>
        <div class="brand-title">JuniorCode</div>
        <div class="brand-subtitle">STUDENT PANEL</div>
      </div>
    </div>

    <div class="student-box">
      <div class="student-avatar">
        <?php if (!empty($_SESSION["profile_picture"])): ?>
          <img src="uploads/profiles/<?= htmlspecialchars($_SESSION["profile_picture"]) ?>" alt="">
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
      <a href="student_dashboard.php"    class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
      <a href="student_courses.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>My Courses</span></a>
      <a href="student_projects.php"     class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-folder-open"></i></span><span>My Projects</span></a>
      <a href="student_classes.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span></a>
      <a href="student_assignments.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span></a>
      <a href="student_quizzes.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span></a>
      <a href="student_certificates.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
      <a href="student_chat.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span></a>
      <a href="student_contact.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-envelope"></i></span><span>Contact</span></a>
    </div>
  </div>
  <div class="sidebar-bottom">
    <a href="student_profile.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
  </div>
</aside>

<div class="main">
  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
  </div>

  <div class="topbar">
    <div>
      <h1><i class="fas fa-folder-open me-2"></i>My Projects</h1>
      <p>Projects from all your enrolled courses.</p>
    </div>
    <div class="topbar-badge"><i class="fas fa-user-graduate me-2"></i><?= htmlspecialchars($studentName) ?></div>
  </div>

  <!-- Stats -->
  <div class="stat-bar">
    <div class="stat-box">
      <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
      <div class="stat-num"><?= count($enrolledIds) ?></div>
      <div class="stat-label">Enrolled Courses</div>
    </div>
    <div class="stat-box">
      <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
      <div class="stat-num"><?= count($coursesWithProjects) ?></div>
      <div class="stat-label">Courses with Projects</div>
    </div>
    <div class="stat-box">
      <div class="stat-icon"><i class="fas fa-gamepad"></i></div>
      <div class="stat-num"><?= $totalProjects ?></div>
      <div class="stat-label">Total Projects</div>
    </div>
  </div>

  <!-- Courses & Projects -->
  <?php if (empty($enrolledIds)): ?>
    <div class="empty-state">
      <i class="fas fa-graduation-cap"></i>
      <h3>No Enrolled Courses</h3>
      <p>You are not enrolled in any courses yet. Contact your teacher or admin to get enrolled.</p>
      <a href="student_courses.php" style="display:inline-flex;align-items:center;gap:8px;margin-top:14px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;border-radius:12px;padding:11px 22px;font-weight:800;text-decoration:none;">
        <i class="fas fa-graduation-cap"></i> Browse Courses
      </a>
    </div>

  <?php elseif (empty($coursesWithProjects)): ?>
    <div class="empty-state">
      <i class="fas fa-folder-open"></i>
      <h3>No Projects Yet</h3>
      <p>Your courses don't have any projects added yet. Check back soon!</p>
    </div>

  <?php else: ?>
    <?php foreach ($coursesWithProjects as $course): ?>
      <?php
        $sectionIcon = match($course["section"]) {
          "junior" => "fa-code",
          "demo"   => "fa-play-circle",
          default  => "fa-child",
        };
        $sectionChip = "chip-" . $course["section"];
        $sectionLabel = ucfirst($course["section"]);
      ?>
      <div class="course-block">
        <div class="course-block-header">
          <div class="course-block-icon"><i class="fas <?= $sectionIcon ?>"></i></div>
          <div style="flex:1;min-width:0;">
            <div class="course-block-name"><?= htmlspecialchars($course["course_name"]) ?></div>
            <div class="course-block-meta">
              <span class="section-chip <?= $sectionChip ?>"><?= $sectionLabel ?></span>
              <?php if (!empty($course["category"])): ?>
                &nbsp;<i class="fas fa-folder" style="font-size:0.75rem;color:var(--muted);"></i>
                <span style="font-size:0.8rem;color:var(--muted);margin-left:4px;"><?= htmlspecialchars($course["category"]) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div style="font-size:0.82rem;font-weight:800;color:var(--muted);flex-shrink:0;">
            <?= count($course["projects"]) ?> project<?= count($course["projects"]) !== 1 ? 's' : '' ?>
          </div>
        </div>

        <?php if (empty($course["projects"])): ?>
          <div class="no-projects">
            <i class="fas fa-folder"></i>
            No projects added to this course yet.
          </div>
        <?php else: ?>
          <div class="proj-list">
            <?php foreach ($course["projects"] as $p):
              $pdfHref = !empty($p["pdf_url"]) ? (strpos($p["pdf_url"], 'http') === 0 ? $p["pdf_url"] : 'uploads/pdfs/' . $p["pdf_url"]) : '';
            ?>
            <div class="proj-item">
              <?php if (!empty($p["image"])): ?>
                <img src="<?= htmlspecialchars($p["image"]) ?>" class="proj-thumb" alt="">
              <?php else: ?>
                <div class="proj-thumb-fb"><i class="fas <?= $sectionIcon ?>"></i></div>
              <?php endif; ?>

              <div class="proj-title"><?= htmlspecialchars($p["title"]) ?></div>

              <div class="proj-btns">
                <?php if (!empty($p["url"])): ?>
                  <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" class="proj-btn proj-btn-link">
                    <i class="fas fa-arrow-up-right-from-square"></i> Project
                  </a>
                <?php else: ?>
                  <span class="proj-btn proj-btn-none"><i class="fas fa-arrow-up-right-from-square"></i> Project</span>
                <?php endif; ?>

                <?php if ($pdfHref): ?>
                  <a href="<?= htmlspecialchars($pdfHref) ?>" target="_blank" class="proj-btn proj-btn-pdf">
                    <i class="fas fa-file-pdf"></i> PDF
                  </a>
                <?php else: ?>
                  <span class="proj-btn proj-btn-none"><i class="fas fa-file-pdf"></i> PDF</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
</div>

<script src="logout-modal.js"></script>
</body>
</html>
