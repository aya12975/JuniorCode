<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherName = $_SESSION["username"] ?? "Teacher";

// ── Fetch all courses ──────────────────────────────────────────────────────────
$demoCourses = $kidsCourses = $juniorCourses = [];
$res = $conn->query("SELECT * FROM courses ORDER BY category ASC, sub_section ASC, course_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        if     ($r["section"] === "demo")   $demoCourses[]   = $r;
        elseif ($r["section"] === "kids")   $kidsCourses[]   = $r;
        elseif ($r["section"] === "junior") $juniorCourses[] = $r;
    }
}

// ── Fetch all projects grouped by course_id ───────────────────────────────────
$allProjects = [];
$pRes = $conn->query("SELECT * FROM course_projects ORDER BY course_id ASC, sort_order ASC, id ASC");
if ($pRes) {
    while ($r = $pRes->fetch_assoc()) {
        $allProjects[(int)$r["course_id"]][] = $r;
    }
}

// ── Group courses ─────────────────────────────────────────────────────────────
function groupBy(array $courses, string $key): array {
    $g = [];
    foreach ($courses as $c) { $g[$c[$key] ?: "General"][] = $c; }
    return $g;
}
$kidsGroups   = groupBy($kidsCourses,   "category");
$juniorGroups = groupBy($juniorCourses, "category");

$gradients = [
    "#7a5b35,#6aa35c","#18b6d4,#79d9ef","#0f86d6,#45c1ec",
    "#7c3aed,#a78bfa","#059669,#34d399","#dc2626,#f87171",
    "#d97706,#fbbf24","#1d4ed8,#60a5fa",
];
$gi = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Courses | JuniorCode Teacher</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; }
* { box-sizing:border-box; }
body { margin:0; font-family:Arial,sans-serif; background:#eef2f7; color:#27344f; }

/* ── App shell ── */
.app-shell { display:flex; min-height:100vh; }

/* ── Sidebar ── */
.sidebar { width:285px; flex-shrink:0; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:#fff; padding:0; position:sticky; top:0; height:100vh; display:flex; flex-direction:column; transition:width 0.3s ease,padding 0.3s ease,min-width 0.3s ease; overflow-y:auto; }
body.sidebar-collapsed .sidebar { width:0; padding:0; min-width:0; overflow:hidden; }
.sidebar-top-area { padding:0 18px 18px; }
.brand { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
.brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; background:none; border-radius:0; }
.brand-title { font-weight:900; font-size:1.1rem; color:#fff; line-height:1.2; }
.brand-subtitle { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
.nav-custom { display:flex; flex-direction:column; gap:4px; }
.teacher-box { display:flex; align-items:center; gap:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:14px; margin-bottom:18px; }
.teacher-avatar { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; font-weight:bold; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; overflow:hidden; }
.teacher-avatar img { width:100%; height:100%; object-fit:cover; }
.teacher-name { font-weight:800; margin:0; color:#ffffff; }
.teacher-role { margin:0; color:rgba(255,255,255,0.55); font-size:0.85rem; }
.nav-link-custom { display:flex; align-items:center; gap:12px; text-decoration:none; color:rgba(255,255,255,0.78); padding:12px 14px; border-radius:14px; margin:4px 0; font-weight:700; transition:all 0.22s ease; }
.nav-link-custom:hover { background:rgba(255,255,255,0.09); color:#ffffff; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#ffffff; box-shadow:0 8px 20px rgba(30,50,100,0.35); }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.nav-link-custom.active .nav-icon { background:rgba(255,255,255,0.18); }
.sidebar-bottom { padding:16px 18px; }

/* ── Main ── */
.main-content { flex:1; min-width:0; }
.page { padding:28px 32px 44px; }

/* ── Hamburger ── */
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

/* ── Topbar ── */
.topbar { background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; padding:22px 26px; margin-bottom:26px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; color:#fff; box-shadow:0 12px 28px rgba(37,99,235,0.3); }
.topbar h1 { font-size:1.7rem; font-weight:900; margin:0; }
.topbar p  { margin:4px 0 0; opacity:0.88; font-size:0.97rem; }
.topbar-date { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.2); border-radius:12px; padding:10px 18px; font-weight:700; font-size:0.9rem; }

/* ── Nav wrap ── */
.nav-wrap {
    background:white; border-radius:8px; padding:0 30px;
    display:flex; justify-content:space-between; align-items:center;
    box-shadow:0 2px 8px rgba(0,0,0,0.1); margin-bottom:24px;
}
.tabs { display:flex; gap:54px; }
.tab-item {
    padding:26px 0 20px; font-weight:700; color:#34415f;
    background:none; border:none; cursor:pointer; position:relative;
    font-size:0.97rem; transition:color 0.2s; white-space:nowrap;
}
.tab-item:hover { color:#1f2f63; }
.tab-item.active { color:#1f2f63; }
.tab-item.active::after {
    content:""; position:absolute;
    left:15%; right:15%; bottom:10px;
    height:4px; background:#1f2f63; border-radius:6px;
}

/* ── Module selector ── */
.select-area { width:260px; }
.module-select {
    width:100%; padding:12px 14px; border:2px solid #2c4383;
    border-radius:6px; color:#2c4383; font-weight:700;
    background:white; font-size:0.9rem; cursor:pointer;
    appearance:none; -webkit-appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%232c4383' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 12px center; padding-right:36px;
}
.module-select:focus { outline:none; border-color:#1f2f63; }

/* ── Content area ── */
.content-area {
    background:white; border-radius:8px; padding:28px 30px;
    min-height:380px; box-shadow:0 2px 8px rgba(0,0,0,0.1);
}
.content-title { font-size:24px; font-weight:800; margin:0 0 0; }
.content-divider { border:none; border-top:2px solid #d7dce7; margin:14px 0 26px; }

/* ── Empty state ── */
.empty-msg { height:300px; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#647596; gap:12px; font-style:italic; font-size:16px; }
.empty-msg i { font-size:2.2rem; opacity:0.28; }

/* ── Section headers ── */
.section-heading { font-size:20px; font-weight:800; color:#27344f; margin:0 0 4px; }
.section-sub { font-size:13px; color:#647596; font-weight:600; margin-bottom:20px; }
.cat-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:1.2px; color:#647596; font-weight:700; margin:0 0 14px; }

/* ── Course block ── */
.course-block { margin-bottom:38px; }
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
    width:220px;
    height:120px;
    object-fit:cover;
    border-radius:4px;
    display:block;
}
.proj-placeholder {
    width:220px; height:120px; border-radius:4px;
    background:linear-gradient(135deg,#3e5077,#143674);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:1.8rem;
}

.project-row h3 {
    margin:0;
    color:#33415f;
    font-size:20px;
    font-weight:700;
}

.proj-actions { display:flex; gap:12px; align-items:center; }
.btn-view-proj {
    border:none; color:white; font-weight:700;
    padding:12px 18px; border-radius:4px; cursor:pointer;
    font-size:0.9rem; text-decoration:none; display:inline-block;
    transition:background 0.2s; white-space:nowrap;
}
.btn-view-proj.green  { background:#36a66f; }
.btn-view-proj.green:hover  { background:#2d925f; color:#fff; }
.btn-view-proj.purple { background:#5d3db3; }
.btn-view-proj.purple:hover { background:#4d3299; color:#fff; }
.btn-view-proj.disabled { background:#c8cdd9; cursor:default; }

.no-projects { padding:24px 20px; color:#9aabc0; font-style:italic; font-size:14px; text-align:center; border:1px solid #d8dce6; border-radius:0 0 8px 8px; }

/* ── Tab panels ── */
.tab-panel { display:none; }
.tab-panel.active { display:block; }

@media (max-width:900px) {
    .app-shell { flex-direction:column; }
    .sidebar { width:100%; height:auto; position:relative; }
    .nav-wrap { flex-direction:column; align-items:stretch; padding:0 20px 16px; gap:0; }
    .tabs { gap:24px; }
    .select-area { width:100%; }
    .page { padding:16px; }
    .project-row { grid-template-columns:1fr; gap:14px; }
    .project-row img, .proj-placeholder { width:100%; height:auto; min-height:100px; }
    .proj-actions { flex-wrap:wrap; }
    .cards-grid { grid-template-columns:1fr; }
}

/* ── Demo course cards ── */
.cards-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; margin-bottom:32px; }
.course-card { background:white; border:1px solid #dce1ec; border-radius:10px; overflow:hidden; box-shadow:0 2px 5px rgba(0,0,0,0.06); transition:transform 0.15s,box-shadow 0.15s; }
.course-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,0.1); }
.card-thumb { height:155px; background-size:cover; background-position:center; }
.card-body-inner { padding:14px 15px 17px; }
.card-name { font-size:15px; font-weight:800; margin-bottom:5px; }
.card-meta { font-size:12px; color:#647596; margin-bottom:14px; min-height:32px; }
.btn-main-card { width:100%; background:#1f2f63; color:white; border:none; padding:11px; border-radius:6px; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:block; text-align:center; margin-bottom:7px; transition:background 0.2s; }
.btn-main-card:hover { background:#2d4580; color:white; }
</style>
</head>
<body>
<div class="app-shell">

  <!-- ── Sidebar ─────────────────────────────────────────────────── -->
  <aside class="sidebar">
    <div class="sidebar-top-area">
      <div class="brand">
        <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
        <div>
          <div class="brand-title">JuniorCode</div>
          <div class="brand-subtitle">TEACHER PORTAL</div>
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
          <p class="teacher-name"><?= htmlspecialchars($teacherName) ?></p>
          <p class="teacher-role">Teacher</p>
        </div>
      </div>

      <div class="nav-title">MAIN</div>
      <div class="nav-custom">
        <a href="teacher_dashboard.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-house"></i></span>
          <span>Dashboard</span>
        </a>
        <a href="teacher_classes.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span>
          <span>My Classes</span>
        </a>
        <a href="teacher_schedule.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
          <span>My Schedule</span>
        </a>
        <a href="teacher_monthly_earnings.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
          <span>My Earnings</span>
        </a>
        <a href="teacher_students.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-user-graduate"></i></span>
          <span>My Students</span>
        </a>
        <a href="teacher_assignments.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span>
          <span>Assignments</span>
        </a>
        <a href="teacher_courses.php" class="nav-link-custom active">
          <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
          <span>Courses</span>
        </a>
        <a href="teacher_quizzes.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-circle-question"></i></span>
          <span>Quizzes</span>
        </a>
      </div>
    </div>

    <div class="sidebar-bottom">
      <a href="teacher_profile.php" class="nav-link-custom">
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

  <!-- ── Main Content ────────────────────────────────────────────── -->
  <div class="main-content">
    <div class="page">

      <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
      </div>

      <!-- Topbar -->
      <div class="topbar">
        <div>
          <h1>Courses</h1>
          <p>Demo sessions, Little &amp; Junior courses</p>
        </div>
        <div class="topbar-date">
          <?php echo date("l, d F Y"); ?>
        </div>
      </div>

      <!-- Tabs + selectors -->
      <div class="nav-wrap">
        <div class="tabs">
          <button class="tab-item" id="tab-btn-demo"   onclick="switchTab('demo')">Demo Sessions</button>
          <button class="tab-item" id="tab-btn-kids"   onclick="switchTab('kids')">Little Course</button>
          <button class="tab-item" id="tab-btn-junior" onclick="switchTab('junior')">Junior Course</button>
        </div>

        <div class="select-area" id="kids-selector" style="display:none;">
          <select class="module-select" onchange="filterModule('kids', this.value)">
            <option value="">Select Little Module</option>
            <?php foreach (array_keys($kidsGroups) as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="select-area" id="junior-selector" style="display:none;">
          <select class="module-select" onchange="filterModule('junior', this.value)">
            <option value="">Select Junior Module</option>
            <?php foreach (array_keys($juniorGroups) as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- ══ DEMO TAB ════════════════════════════════════════════════ -->
      <div id="tab-demo" class="tab-panel">
        <div class="content-area">
          <div class="content-title">Demo Sessions</div>
          <hr class="content-divider">

          <?php if (empty($demoCourses)): ?>
            <div class="empty-msg"><i class="fas fa-play-circle"></i>No demo courses available yet.</div>
          <?php else: ?>
            <div class="cards-grid">
              <?php foreach ($demoCourses as $c):
                $grad = $gradients[$gi++ % count($gradients)];
                $img  = !empty($c["image"])
                    ? 'background-image:url('.htmlspecialchars($c["image"]).');background-size:cover;background-position:center;'
                    : 'background:linear-gradient(135deg,'.$grad.');';
                $meta = htmlspecialchars(implode(' · ', array_filter([ucfirst($c["level"] ?? ""), $c["age_group"] ?? ""])));
              ?>
                <div class="course-card">
                  <div class="card-thumb" style="<?= $img ?>"></div>
                  <div class="card-body-inner">
                    <div class="card-name"><?= htmlspecialchars($c["course_name"]) ?></div>
                    <div class="card-meta"><?= $meta ?></div>
                    <?php if (!empty($c["link"])): ?>
                      <a href="<?= htmlspecialchars($c["link"]) ?>" target="_blank" class="btn-main-card">
                        <i class="fas fa-play me-1"></i> View Demo
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ══ LITTLE / KIDS TAB ═══════════════════════════════════════ -->
      <div id="tab-kids" class="tab-panel">
        <div class="content-area">
          <div class="content-title">Little Course <span style="font-size:15px;font-weight:600;color:#647596;">(Grade 1 – Grade 3)</span></div>
          <hr class="content-divider">

          <div id="kids-empty" class="empty-msg">
            <i class="fas fa-hand-pointer"></i>
            Please select a module to view courses.
          </div>

          <?php foreach ($kidsGroups as $cat => $courses): ?>
          <div class="kids-cat-section" data-cat="<?= htmlspecialchars($cat) ?>" style="display:none;">
            <div class="cat-label"><i class="fas fa-star me-1"></i><?= htmlspecialchars($cat) ?></div>

            <?php foreach ($courses as $c):
              $projects = $allProjects[(int)$c["id"]] ?? [];
            ?>
              <div class="course-block">
                <div class="course-block-title">
                  <?= htmlspecialchars($c["course_name"]) ?>
                  <?php if ($c["level"]): ?><span><?= ucfirst($c["level"]) ?> level</span><?php endif; ?>
                </div>
                <?php if (empty($projects)): ?>
                  <div class="no-projects"><i class="fas fa-folder-open me-2"></i>No projects added yet.</div>
                <?php else: ?>
                  <div class="projects-section">
                    <?php foreach ($projects as $i => $p):
                      $pdfH = !empty($p["pdf_url"]) ? (strpos($p["pdf_url"],'http')===0 ? $p["pdf_url"] : 'uploads/pdfs/'.$p["pdf_url"]) : '';
                    ?>
                    <div class="project-row">
                      <?php if (!empty($p["image"])): ?>
                        <img src="<?= htmlspecialchars($p["image"]) ?>" alt="<?= htmlspecialchars($p["title"]) ?>">
                      <?php else: ?>
                        <div class="proj-placeholder"><i class="fas fa-star"></i></div>
                      <?php endif; ?>
                      <h3>Project <?= $i + 1 ?>: <?= htmlspecialchars($p["title"]) ?></h3>
                      <div class="proj-actions">
                        <?php if (!empty($p["url"])): ?>
                          <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" class="btn-view-proj green">View Project</a>
                        <?php else: ?><span class="btn-view-proj disabled">No Link</span><?php endif; ?>
                        <?php if ($pdfH): ?>
                          <a href="<?= htmlspecialchars($pdfH) ?>" target="_blank" class="btn-view-proj purple">Check Course</a>
                        <?php else: ?><span class="btn-view-proj disabled">No PDF</span><?php endif; ?>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>

          <?php if (empty($kidsCourses)): ?>
            <div class="empty-msg"><i class="fas fa-star"></i>No Little courses available yet.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ══ JUNIOR TAB ══════════════════════════════════════════════ -->
      <div id="tab-junior" class="tab-panel">
        <div class="content-area">
          <div class="content-title">Junior Course <span style="font-size:15px;font-weight:600;color:#647596;">(Grade 4 – Grade 12)</span></div>
          <hr class="content-divider">

          <div id="junior-empty" class="empty-msg">
            <i class="fas fa-hand-pointer"></i>
            Please select a module to view courses.
          </div>

          <?php foreach ($juniorGroups as $cat => $courses): ?>
          <div class="junior-cat-section" data-cat="<?= htmlspecialchars($cat) ?>" style="display:none;">
            <div class="cat-label"><i class="fas fa-rocket me-1"></i><?= htmlspecialchars($cat) ?></div>

            <?php foreach ($courses as $c):
              $projects = $allProjects[(int)$c["id"]] ?? [];
            ?>
              <div class="course-block">
                <div class="course-block-title">
                  <?= htmlspecialchars($c["course_name"]) ?>
                  <?php if ($c["level"]): ?><span><?= ucfirst($c["level"]) ?> level</span><?php endif; ?>
                </div>
                <?php if (empty($projects)): ?>
                  <div class="no-projects"><i class="fas fa-folder-open me-2"></i>No projects added yet.</div>
                <?php else: ?>
                  <div class="projects-section">
                    <?php foreach ($projects as $i => $p):
                      $pdfH = !empty($p["pdf_url"]) ? (strpos($p["pdf_url"],'http')===0 ? $p["pdf_url"] : 'uploads/pdfs/'.$p["pdf_url"]) : '';
                    ?>
                    <div class="project-row">
                      <?php if (!empty($p["image"])): ?>
                        <img src="<?= htmlspecialchars($p["image"]) ?>" alt="<?= htmlspecialchars($p["title"]) ?>">
                      <?php else: ?>
                        <div class="proj-placeholder"><i class="fas fa-rocket"></i></div>
                      <?php endif; ?>
                      <h3>Project <?= $i + 1 ?>: <?= htmlspecialchars($p["title"]) ?></h3>
                      <div class="proj-actions">
                        <?php if (!empty($p["url"])): ?>
                          <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" class="btn-view-proj green">View Project</a>
                        <?php else: ?><span class="btn-view-proj disabled">No Link</span><?php endif; ?>
                        <?php if ($pdfH): ?>
                          <a href="<?= htmlspecialchars($pdfH) ?>" target="_blank" class="btn-view-proj purple">Check Course</a>
                        <?php else: ?><span class="btn-view-proj disabled">No PDF</span><?php endif; ?>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>

          <?php if (empty($juniorCourses)): ?>
            <div class="empty-msg"><i class="fas fa-rocket"></i>No Junior courses available yet.</div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /page -->
  </div><!-- /main-content -->
</div><!-- /app-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Tab switching ──────────────────────────────────────────────────
function switchTab(name) {
  ['demo','kids','junior'].forEach(t => {
    document.getElementById('tab-'+t).classList.remove('active');
    document.getElementById('tab-btn-'+t).classList.remove('active');
  });
  document.getElementById('tab-'+name).classList.add('active');
  document.getElementById('tab-btn-'+name).classList.add('active');
  document.getElementById('kids-selector').style.display   = name === 'kids'   ? 'block' : 'none';
  document.getElementById('junior-selector').style.display = name === 'junior' ? 'block' : 'none';
}

// ── Module filter ──────────────────────────────────────────────────
function filterModule(tab, cat) {
  const sections = document.querySelectorAll('.' + tab + '-cat-section');
  const emptyEl  = document.getElementById(tab + '-empty');
  if (!cat) {
    sections.forEach(s => s.style.display = 'none');
    if (emptyEl) emptyEl.style.display = 'flex';
    return;
  }
  if (emptyEl) emptyEl.style.display = 'none';
  sections.forEach(s => s.style.display = s.dataset.cat === cat ? 'block' : 'none');
}

// ── Init ───────────────────────────────────────────────────────────
switchTab('demo');
</script>
</body>
</html>
