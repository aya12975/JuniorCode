<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";

// Fetch all non-demo courses
$courses = [];
$res = $conn->query("SELECT * FROM courses WHERE section != 'demo' AND status = 'active' ORDER BY section ASC, category ASC, course_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) $courses[] = $r;
}

// Fetch enrolled course IDs for this student
$enrolledIds = [];
$enStmt = $conn->prepare("SELECT course_id FROM student_enrollments WHERE student_name = ?");
if ($enStmt) {
    $enStmt->bind_param("s", $studentName);
    $enStmt->execute();
    $enRes = $enStmt->get_result();
    while ($row = $enRes->fetch_assoc()) $enrolledIds[] = (int)$row["course_id"];
    $enStmt->close();
}

// Fetch projects for all enrolled courses
$projectsByCourse = [];
if (!empty($enrolledIds)) {
    $pIn   = implode(',', array_fill(0, count($enrolledIds), '?'));
    $pStmt = $conn->prepare("SELECT * FROM course_projects WHERE course_id IN ($pIn) ORDER BY sort_order ASC, id ASC");
    $types = str_repeat('i', count($enrolledIds));
    $pStmt->bind_param($types, ...$enrolledIds);
    $pStmt->execute();
    foreach ($pStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $pr) {
        $projectsByCourse[(int)$pr['course_id']][] = $pr;
    }
    $pStmt->close();
}

// Group by section then category
$kidsCourses   = array_filter($courses, fn($c) => $c["section"] === "kids");
$juniorCourses = array_filter($courses, fn($c) => $c["section"] === "junior");

function groupByCategory(array $courses): array {
    $g = [];
    foreach ($courses as $c) { $g[$c["category"] ?: "General"][] = $c; }
    return $g;
}
$kidsGroups   = groupByCategory($kidsCourses);
$juniorGroups = groupByCategory($juniorCourses);

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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Courses | JuniorCode</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
  --primary:   #3e5077;
  --secondary: #143674;
  --dark:      #0f172a;
  --muted:     #64748b;
  --border:    #edf4ff;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: Arial, Helvetica, sans-serif;
  color: var(--dark);
  background:
    radial-gradient(circle at top left,  rgba(37,99,235,0.08), transparent 22%),
    radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
    linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
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

/* ── Main ── */
.main { flex:1; padding:28px; min-height:100vh; overflow-x:hidden; }

/* ── Hamburger ── */
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background .2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

/* ── Topbar ── */
.topbar { background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; padding:22px 26px; margin-bottom:26px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; color:#fff; box-shadow:0 12px 28px rgba(37,99,235,0.28); }
.topbar h1 { font-size:1.7rem; font-weight:900; margin:0; }
.topbar p  { margin:4px 0 0; opacity:.85; font-size:.95rem; }
.topbar-date { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.2); border-radius:12px; padding:10px 18px; font-weight:700; font-size:.9rem; }

/* ── Tab bar ── */
.tab-bar { background:#fff; border-radius:14px; padding:6px; display:inline-flex; gap:4px; margin-bottom:26px; box-shadow:0 2px 8px rgba(0,0,0,0.07); }
.tab-btn { padding:10px 24px; border:none; background:none; border-radius:10px; font-weight:700; color:var(--muted); cursor:pointer; font-size:.92rem; transition:all .2s; }
.tab-btn.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 4px 12px rgba(62,80,119,0.3); }

/* ── Cards grid ── */
.cards-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:20px; }

/* ── Course card ── */
.course-card { background:#fff; border:1px solid #dce1ec; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06); position:relative; transition:box-shadow .2s,border-color .2s; }
.course-card.selected { border-color:var(--primary); border-width:2px; box-shadow:0 4px 18px rgba(62,80,119,0.18); }
.card-thumb { height:150px; background-size:cover; background-position:center; position:relative; }

/* ── Lock overlay ── */
.lock-overlay { position:absolute; inset:0; background:rgba(15,23,42,0.52); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; }
.lock-icon { width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,0.18); border:2px solid rgba(255,255,255,0.4); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1rem; }
.lock-text { font-size:.72rem; font-weight:700; color:rgba(255,255,255,0.85); letter-spacing:.5px; }

.card-body-inner { padding:14px 15px 16px; }
.card-name { font-size:14px; font-weight:800; margin-bottom:4px; }
.card-meta { font-size:11px; color:var(--muted); margin-bottom:12px; min-height:28px; }
.card-locked-btn { width:100%; background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; padding:10px; border-radius:8px; font-weight:700; cursor:default; font-size:.82rem; display:flex; align-items:center; justify-content:center; gap:6px; }
.unlock-badge { position:absolute; top:10px; right:10px; background:linear-gradient(135deg,#059669,#34d399); color:#fff; font-size:.68rem; font-weight:800; padding:4px 10px; border-radius:20px; letter-spacing:.4px; }
.card-unlocked-btn { width:100%; background:linear-gradient(135deg,#059669,#047857); color:#fff; border:none; padding:10px; border-radius:8px; font-weight:700; font-size:.82rem; display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; transition:opacity .2s; }
.card-unlocked-btn:hover { opacity:.88; }
.card-unlocked-btn.open { background:linear-gradient(135deg,var(--primary),var(--secondary)); }
.course-card.unlocked { border-color:#a7f3d0; box-shadow:0 2px 12px rgba(5,150,105,0.12); }
.course-card.unlocked.selected { border-color:var(--primary); }

/* ── Projects panel ── */
.projects-panel {
  display:none;
  background:#fff;
  border:1.5px solid var(--border);
  border-radius:18px;
  margin-bottom:26px;
  overflow:hidden;
  box-shadow:0 4px 18px rgba(37,99,235,0.08);
  animation:slideDown .22s ease;
}
.projects-panel.visible { display:block; }
@keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }

.panel-header {
  display:flex; align-items:center; gap:14px;
  padding:18px 22px;
  background:linear-gradient(135deg,rgba(62,80,119,0.06),rgba(20,54,116,0.03));
  border-bottom:1.5px solid var(--border);
}
.panel-course-icon {
  width:46px; height:46px; border-radius:13px;
  background:linear-gradient(135deg,var(--primary),var(--secondary));
  color:#fff; display:flex; align-items:center; justify-content:center;
  font-size:1.2rem; flex-shrink:0;
}
.panel-course-name { font-weight:900; font-size:1.05rem; color:#0f172a; }
.panel-course-meta { font-size:0.8rem; color:var(--muted); margin-top:2px; }
.panel-close {
  margin-left:auto; background:none; border:none; cursor:pointer;
  color:var(--muted); font-size:1.1rem; padding:6px; border-radius:8px;
  transition:background .15s;
}
.panel-close:hover { background:#f1f5f9; color:#334155; }

.proj-list { padding:16px 22px; display:flex; flex-direction:column; gap:10px; }
.proj-item { display:flex; align-items:center; gap:14px; background:#f8fbff; border:1px solid var(--border); border-radius:14px; padding:13px 16px; transition:box-shadow .2s; }
.proj-item:hover { box-shadow:0 4px 16px rgba(62,80,119,0.1); }
.proj-thumb { width:50px; height:50px; border-radius:11px; object-fit:cover; flex-shrink:0; border:1px solid #dbeafe; }
.proj-thumb-fb { width:50px; height:50px; border-radius:11px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.proj-title { flex:1; font-weight:800; font-size:0.92rem; color:#0f172a; }
.proj-btns { display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap; }
.proj-btn { display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; font-weight:800; padding:8px 16px; border-radius:10px; text-decoration:none; white-space:nowrap; transition:filter .2s; }
.proj-btn:hover { filter:brightness(1.1); color:#fff; }
.proj-btn-link { background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:#fff; }
.proj-btn-pdf  { background:linear-gradient(135deg,#f97316,#ea580c); color:#fff; }
.proj-btn-none { background:#f1f5f9; color:#94a3b8; cursor:default; pointer-events:none; }
.no-projects { text-align:center; padding:24px; color:var(--muted); font-size:0.88rem; font-weight:600; }
.no-projects i { display:block; font-size:1.6rem; color:#dbeafe; margin-bottom:8px; }

/* ── Category header ── */
.cat-header { font-size:.78rem; text-transform:uppercase; letter-spacing:1.2px; color:var(--muted); font-weight:700; margin:0 0 14px; padding-bottom:8px; border-bottom:1px solid #edf4ff; }

/* ── Tab panels ── */
.tab-panel { display:none; }
.tab-panel.active { display:block; }

/* ── Empty state ── */
.empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
.empty-state i { font-size:2.5rem; display:block; margin-bottom:12px; opacity:.3; }
.empty-state p { font-weight:700; margin:0; }

@media (max-width:900px) { .cards-grid { grid-template-columns:1fr 1fr; } }
@media (max-width:575px) { .cards-grid { grid-template-columns:1fr; } }
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
      <a href="student_dashboard.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
      </a>
      <a href="student_courses.php" class="nav-link-custom active">
        <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>My Courses</span>
      </a>
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

<!-- ── Main ── -->
<div class="main">

  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <div class="topbar">
    <div>
      <h1><i class="fas fa-graduation-cap me-2"></i>My Courses</h1>
      <p>Click an unlocked course to view its projects</p>
    </div>
    <div class="topbar-date"><?= date("l, d F Y") ?></div>
  </div>

  <!-- Tab bar -->
  <div class="tab-bar">
    <button class="tab-btn active" id="tab-btn-kids"   onclick="switchTab('kids')">Little Courses</button>
    <button class="tab-btn"        id="tab-btn-junior" onclick="switchTab('junior')">Junior Courses</button>
  </div>

  <!-- Projects panel (shared, shown below active tab) -->
  <div class="projects-panel" id="projectsPanel">
    <div class="panel-header">
      <div class="panel-course-icon" id="panelIcon"><i class="fas fa-graduation-cap"></i></div>
      <div>
        <div class="panel-course-name" id="panelName"></div>
        <div class="panel-course-meta" id="panelMeta"></div>
      </div>
      <button class="panel-close" onclick="closePanel()" title="Close"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="proj-list" id="panelProjList"></div>
  </div>

  <!-- Little (Kids) tab -->
  <div class="tab-panel active" id="tab-kids">
    <?php if (empty($kidsGroups)): ?>
      <div class="empty-state">
        <i class="fas fa-graduation-cap"></i>
        <p>No Little courses available yet.</p>
      </div>
    <?php else: ?>
      <?php foreach ($kidsGroups as $cat => $catCourses): ?>
        <div class="cat-header"><?= htmlspecialchars($cat) ?></div>
        <div class="cards-grid">
          <?php foreach ($catCourses as $c):
            $grad = $gradients[$gi++ % count($gradients)];
            $img  = !empty($c["image"])
              ? 'background-image:url('.htmlspecialchars($c["image"]).');background-size:cover;background-position:center;'
              : 'background:linear-gradient(135deg,'.$grad.');';
            $meta = htmlspecialchars(implode(' · ', array_filter([ucfirst($c["level"] ?? ""), $c["age_group"] ?? ""])));
            $unlocked = in_array((int)$c["id"], $enrolledIds);
            $projs = $projectsByCourse[(int)$c["id"]] ?? [];
            $projsJson = htmlspecialchars(json_encode($projs), ENT_QUOTES);
          ?>
          <div class="course-card <?= $unlocked ? 'unlocked' : '' ?>" id="card-<?= $c['id'] ?>">
            <div class="card-thumb" style="<?= $img ?>">
              <?php if ($unlocked): ?>
                <div class="unlock-badge"><i class="fas fa-unlock me-1"></i>Unlocked</div>
              <?php else: ?>
                <div class="lock-overlay">
                  <div class="lock-icon"><i class="fas fa-lock"></i></div>
                  <div class="lock-text">LOCKED</div>
                </div>
              <?php endif; ?>
            </div>
            <div class="card-body-inner">
              <div class="card-name"><?= htmlspecialchars($c["course_name"]) ?></div>
              <div class="card-meta"><?= $meta ?></div>
              <?php if ($unlocked): ?>
                <button class="card-unlocked-btn" id="btn-<?= $c['id'] ?>"
                  onclick="toggleProjects(<?= $c['id'] ?>, <?= $projsJson ?>, '<?= htmlspecialchars($c['course_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($meta, ENT_QUOTES) ?>', 'fa-child')">
                  <i class="fas fa-folder-open"></i> View Projects
                </button>
              <?php else: ?>
                <div class="card-locked-btn">
                  <i class="fas fa-lock"></i> Contact us to unlock
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Junior tab -->
  <div class="tab-panel" id="tab-junior">
    <?php if (empty($juniorGroups)): ?>
      <div class="empty-state">
        <i class="fas fa-graduation-cap"></i>
        <p>No Junior courses available yet.</p>
      </div>
    <?php else: ?>
      <?php foreach ($juniorGroups as $cat => $catCourses): ?>
        <div class="cat-header"><?= htmlspecialchars($cat) ?></div>
        <div class="cards-grid">
          <?php foreach ($catCourses as $c):
            $grad = $gradients[$gi++ % count($gradients)];
            $img  = !empty($c["image"])
              ? 'background-image:url('.htmlspecialchars($c["image"]).');background-size:cover;background-position:center;'
              : 'background:linear-gradient(135deg,'.$grad.');';
            $meta = htmlspecialchars(implode(' · ', array_filter([ucfirst($c["level"] ?? ""), $c["age_group"] ?? ""])));
            $unlocked = in_array((int)$c["id"], $enrolledIds);
            $projs = $projectsByCourse[(int)$c["id"]] ?? [];
            $projsJson = htmlspecialchars(json_encode($projs), ENT_QUOTES);
          ?>
          <div class="course-card <?= $unlocked ? 'unlocked' : '' ?>" id="card-<?= $c['id'] ?>">
            <div class="card-thumb" style="<?= $img ?>">
              <?php if ($unlocked): ?>
                <div class="unlock-badge"><i class="fas fa-unlock me-1"></i>Unlocked</div>
              <?php else: ?>
                <div class="lock-overlay">
                  <div class="lock-icon"><i class="fas fa-lock"></i></div>
                  <div class="lock-text">LOCKED</div>
                </div>
              <?php endif; ?>
            </div>
            <div class="card-body-inner">
              <div class="card-name"><?= htmlspecialchars($c["course_name"]) ?></div>
              <div class="card-meta"><?= $meta ?></div>
              <?php if ($unlocked): ?>
                <button class="card-unlocked-btn" id="btn-<?= $c['id'] ?>"
                  onclick="toggleProjects(<?= $c['id'] ?>, <?= $projsJson ?>, '<?= htmlspecialchars($c['course_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($meta, ENT_QUOTES) ?>', 'fa-code')">
                  <i class="fas fa-folder-open"></i> View Projects
                </button>
              <?php else: ?>
                <div class="card-locked-btn">
                  <i class="fas fa-lock"></i> Contact us to unlock
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div><!-- /.main -->
</div><!-- /.app-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var openCardId = null;

function toggleProjects(courseId, projects, courseName, courseMeta, iconClass) {
  var panel = document.getElementById('projectsPanel');
  var btn   = document.getElementById('btn-' + courseId);
  var card  = document.getElementById('card-' + courseId);

  // Close if same card clicked again
  if (openCardId === courseId) {
    closePanel();
    return;
  }

  // Reset previously open card
  if (openCardId !== null) {
    var prevBtn  = document.getElementById('btn-'  + openCardId);
    var prevCard = document.getElementById('card-' + openCardId);
    if (prevBtn)  { prevBtn.classList.remove('open');  prevBtn.innerHTML = '<i class="fas fa-folder-open"></i> View Projects'; }
    if (prevCard) { prevCard.classList.remove('selected'); }
  }

  openCardId = courseId;
  card.classList.add('selected');
  btn.classList.add('open');
  btn.innerHTML = '<i class="fas fa-folder-open"></i> Hide Projects';

  // Set header
  document.getElementById('panelIcon').innerHTML = '<i class="fas ' + iconClass + '"></i>';
  document.getElementById('panelName').textContent = courseName;
  document.getElementById('panelMeta').textContent = courseMeta || 'Course Projects';

  // Build project list
  var list = document.getElementById('panelProjList');
  list.innerHTML = '';

  if (!projects || projects.length === 0) {
    list.innerHTML = '<div class="no-projects"><i class="fas fa-folder"></i>No projects added to this course yet.</div>';
  } else {
    projects.forEach(function(p, i) {
      var pdfHref = '';
      if (p.pdf_url) {
        pdfHref = p.pdf_url.startsWith('http') ? p.pdf_url : 'uploads/pdfs/' + p.pdf_url;
      }

      var thumbHtml = p.image
        ? '<img src="' + escHtml(p.image) + '" class="proj-thumb" alt="">'
        : '<div class="proj-thumb-fb"><i class="fas ' + iconClass + '"></i></div>';

      var linkBtn = p.url
        ? '<a href="' + escHtml(p.url) + '" target="_blank" class="proj-btn proj-btn-link"><i class="fas fa-arrow-up-right-from-square"></i> Project</a>'
        : '<span class="proj-btn proj-btn-none"><i class="fas fa-arrow-up-right-from-square"></i> Project</span>';

      var pdfBtn = pdfHref
        ? '<a href="' + escHtml(pdfHref) + '" target="_blank" class="proj-btn proj-btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>'
        : '<span class="proj-btn proj-btn-none"><i class="fas fa-file-pdf"></i> PDF</span>';

      var item = document.createElement('div');
      item.className = 'proj-item';
      item.innerHTML = thumbHtml +
        '<div class="proj-title">Project ' + (i + 1) + ': ' + escHtml(p.title) + '</div>' +
        '<div class="proj-btns">' + linkBtn + pdfBtn + '</div>';
      list.appendChild(item);
    });
  }

  // Move panel below active tab and show
  var activeTab = document.querySelector('.tab-panel.active');
  activeTab.parentNode.insertBefore(panel, activeTab.nextSibling);
  panel.classList.remove('visible');
  void panel.offsetWidth; // force reflow for animation
  panel.classList.add('visible');
  setTimeout(function() { panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 50);
}

function closePanel() {
  var panel = document.getElementById('projectsPanel');
  panel.classList.remove('visible');
  if (openCardId !== null) {
    var prevBtn  = document.getElementById('btn-'  + openCardId);
    var prevCard = document.getElementById('card-' + openCardId);
    if (prevBtn)  { prevBtn.classList.remove('open');  prevBtn.innerHTML = '<i class="fas fa-folder-open"></i> View Projects'; }
    if (prevCard) { prevCard.classList.remove('selected'); }
  }
  openCardId = null;
}

function switchTab(name) {
  closePanel();
  ['kids','junior'].forEach(function(t) {
    document.getElementById('tab-'+t).classList.remove('active');
    document.getElementById('tab-btn-'+t).classList.remove('active');
  });
  document.getElementById('tab-'+name).classList.add('active');
  document.getElementById('tab-btn-'+name).classList.add('active');
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
