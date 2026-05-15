<?php
session_start();
require_once "db.php";
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherName = $_SESSION["username"] ?? "Teacher";

// Ensure all course columns exist
foreach ([
    "section"     => "VARCHAR(50)   NOT NULL DEFAULT 'kids'",
    "sub_section" => "VARCHAR(50)   NOT NULL DEFAULT ''",
    "category"    => "VARCHAR(100)  NOT NULL DEFAULT ''",
    "age_group"   => "VARCHAR(50)   NOT NULL DEFAULT ''",
    "level"       => "VARCHAR(50)   NOT NULL DEFAULT ''",
    "price"       => "DECIMAL(10,2) NOT NULL DEFAULT 0",
    "course_type" => "VARCHAR(20)   NOT NULL DEFAULT 'demo'",
    "status"      => "VARCHAR(20)   NOT NULL DEFAULT 'active'",
    "duration"    => "VARCHAR(100)  NOT NULL DEFAULT ''",
    "image"       => "TEXT          NULL",
] as $col => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM courses LIKE '$col'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE courses ADD COLUMN $col $def");
    }
}

// Ensure course_projects table exists
$conn->query("CREATE TABLE IF NOT EXISTS course_projects (
    id INT AUTO_INCREMENT PRIMARY KEY, section VARCHAR(50) NOT NULL DEFAULT 'kids',
    category VARCHAR(100) NOT NULL DEFAULT '', title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL, image TEXT NOT NULL DEFAULT '', sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS pdf_url TEXT NOT NULL DEFAULT ''");

// Same logic as admin courses.php — pulls categories from both tables
function getCategoriesForSection($conn, $section) {
    $cats = [];
    $s1 = $conn->prepare("SELECT DISTINCT category FROM courses WHERE section=? AND category != '' ORDER BY category ASC");
    if ($s1) {
        $s1->bind_param("s", $section);
        $s1->execute();
        $cats = array_column($s1->get_result()->fetch_all(MYSQLI_ASSOC), 'category');
    }
    $s2 = $conn->prepare("SELECT DISTINCT category FROM course_projects WHERE section=? AND category != ''");
    if ($s2) {
        $s2->bind_param("s", $section);
        $s2->execute();
        foreach (array_column($s2->get_result()->fetch_all(MYSQLI_ASSOC), 'category') as $c) {
            if (!in_array($c, $cats)) $cats[] = $c;
        }
        sort($cats);
    }
    return $cats;
}

function fetchProjects($conn, $section, $category) {
    $stmt = $conn->prepare("SELECT * FROM course_projects WHERE section = ? AND category = ? ORDER BY sort_order ASC, id ASC");
    if (!$stmt) return [];
    $stmt->bind_param("ss", $section, $category);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchCoursesByCategory($conn, $section, $category) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE section = ? AND category = ? ORDER BY id DESC");
    if (!$stmt) return [];
    $stmt->bind_param("ss", $section, $category);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchDemoCourses($conn) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE section = 'demo' ORDER BY id DESC");
    if (!$stmt) return [];
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchDemoProjects($conn) {
    $stmt = $conn->prepare("SELECT * FROM course_projects WHERE section = 'demo' ORDER BY sort_order ASC, id ASC");
    if (!$stmt) return [];
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$demoCourses      = fetchDemoCourses($conn);
$demoProjects     = fetchDemoProjects($conn);

$allJuniorCourses = [];
$res2 = $conn->query("SELECT * FROM courses WHERE section='junior' AND status='active' ORDER BY category ASC, course_name ASC");
if ($res2) $allJuniorCourses = $res2->fetch_all(MYSQLI_ASSOC);

$juniorCourseProjects = [];
foreach ($allJuniorCourses as $jc) {
    $ps = $conn->prepare("SELECT * FROM course_projects WHERE course_id=? ORDER BY sort_order ASC, id ASC");
    if ($ps) { $ps->bind_param("i", $jc['id']); $ps->execute(); $juniorCourseProjects[$jc['id']] = $ps->get_result()->fetch_all(MYSQLI_ASSOC); $ps->close(); }
}

$allKidsCourses = [];
$res = $conn->query("SELECT * FROM courses WHERE section='kids' AND status='active' ORDER BY category ASC, course_name ASC");
if ($res) $allKidsCourses = $res->fetch_all(MYSQLI_ASSOC);

$kidsCourseProjects = [];
foreach ($allKidsCourses as $kc) {
    $ps = $conn->prepare("SELECT * FROM course_projects WHERE course_id=? ORDER BY sort_order ASC, id ASC");
    if ($ps) { $ps->bind_param("i", $kc['id']); $ps->execute(); $kidsCourseProjects[$kc['id']] = $ps->get_result()->fetch_all(MYSQLI_ASSOC); $ps->close(); }
}

function catSlug(string $cat): string {
    return preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($cat)));
}

function renderProjectLinks(array $projects): string {
    if (empty($projects)) return '';
    ob_start(); ?>
    <div style="margin-bottom:16px;">
      <?php foreach ($projects as $p): ?>
        <div class="tc-proj-item">
          <?php if (!empty($p["image"])): ?>
            <div class="tc-proj-icon"><img src="<?= htmlspecialchars($p["image"]) ?>" alt="<?= htmlspecialchars($p["title"]) ?>"></div>
          <?php else: ?>
            <div class="tc-proj-icon-fb"><i class="fas fa-gamepad"></i></div>
          <?php endif; ?>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:800;color:#0f172a;font-size:0.9rem;"><?= htmlspecialchars($p["title"]) ?></div>
          </div>
          <div class="tc-proj-actions">
            <?php if (!empty($p["pdf_url"])): ?>
              <?php $pdfHref = (strpos($p["pdf_url"], 'http') === 0) ? $p["pdf_url"] : 'uploads/pdfs/' . $p["pdf_url"]; ?>
              <a href="<?= htmlspecialchars($pdfHref) ?>" target="_blank" class="tc-proj-btn tc-proj-pdf">
                <i class="fas fa-file-pdf"></i> Check Course
              </a>
            <?php endif; ?>
            <?php if (!empty($p["url"])): ?>
              <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" class="tc-proj-btn tc-proj-link">
                <i class="fas fa-arrow-up-right-from-square"></i> View Project
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
}

function renderCourseCards(array $courses): string {
    if (empty($courses)) {
        return '<div class="empty-box">No courses found.</div>';
    }
    ob_start(); ?>
    <div class="course-card-list">
      <?php foreach ($courses as $c): ?>
      <div class="course-card-item">
        <div class="course-card-thumb">
          <?php if (!empty($c["image"])): ?>
            <img src="<?= htmlspecialchars($c["image"]) ?>" alt="">
          <?php else: ?>
            <div class="course-thumb-fallback"><i class="fas fa-graduation-cap"></i></div>
          <?php endif; ?>
        </div>
        <div class="course-card-body">
          <div class="course-card-name"><?= htmlspecialchars($c["course_name"]) ?></div>
          <div class="course-card-meta">
            <?php if (!empty($c["age_group"])): ?>
              <span class="meta-chip"><i class="fas fa-users"></i> <?= htmlspecialchars($c["age_group"]) ?></span>
            <?php endif; ?>
            <?php if (!empty($c["level"])): ?>
              <span class="meta-chip"><i class="fas fa-signal"></i> <?= htmlspecialchars($c["level"]) ?></span>
            <?php endif; ?>
            <?php if (!empty($c["duration"])): ?>
              <span class="meta-chip"><i class="fas fa-clock"></i> <?= htmlspecialchars($c["duration"]) ?></span>
            <?php endif; ?>
            <span class="meta-chip"><i class="fas fa-dollar-sign"></i> $<?= number_format((float)$c["price"], 2) ?></span>
            <span class="type-badge <?= $c["course_type"] === "demo" ? "type-demo" : "type-paid" ?>"><?= ucfirst(htmlspecialchars($c["course_type"])) ?></span>
            <span class="status-badge <?= $c["status"] === "active" ? "status-active" : "status-inactive" ?>"><?= ucfirst(htmlspecialchars($c["status"])) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Courses | Teacher</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:   #3e5077;
      --secondary: #143674;
      --dark:      #0f172a;
      --muted:     #64748b;
      --shadow:    0 18px 45px rgba(37,99,235,0.08);
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      color: var(--dark);
      background:
        radial-gradient(circle at top left,  rgba(29,78,216,0.07), transparent 25%),
        radial-gradient(circle at bottom right, rgba(14,165,233,0.07), transparent 25%),
        linear-gradient(180deg, #f8fbff 0%, #eaf4ff 100%);
    }

    .app-shell { min-height: 100vh; display: flex; }

    /* ── Sidebar ── */
    .sidebar {
      width: 285px; flex-shrink: 0;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: #fff;
      padding: 0;
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
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
      overflow: hidden;
    }
    .teacher-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .teacher-name  { font-weight: 800; margin: 0; color: #ffffff; }
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

    .nav-link-custom:hover {
      background: rgba(255,255,255,0.09);
      color: #ffffff;
    }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #ffffff;
      box-shadow: 0 8px 20px rgba(30,50,100,0.35);
    }

    .nav-icon {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      flex-shrink: 0;
    }

    .nav-link-custom.active .nav-icon {
      background: rgba(255,255,255,0.18);
    }

    .sidebar-bottom { padding: 16px 18px; }

    .main { flex: 1; padding: 26px; min-height: 100vh; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .hero {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white; border-radius: 22px; padding: 18px 20px;
      margin-bottom: 22px; box-shadow: 0 12px 28px rgba(37,99,235,0.3);
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 16px;
    }

    .hero h2 { margin: 0; font-size: 1.5rem; font-weight: 900; }
    .hero p  { margin: 4px 0 0; color: rgba(255,255,255,0.8); }

    .panel-card {
      background: white;
      border: 1px solid #edf4ff;
      border-radius: 22px;
      padding: 22px;
      margin-bottom: 22px;
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
    }

    .panel-card::before {
      content: '';
      display: block;
      height: 5px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      position: absolute;
      top: 0; left: 0; right: 0;
      border-radius: 22px 22px 0 0;
    }

    .panel-title {
      font-size: 1.1rem;
      font-weight: 900;
      color: var(--primary);
      margin-bottom: 14px;
    }

    .panel-header {
      display: flex; justify-content: space-between;
      align-items: center; gap: 12px;
      margin-bottom: 18px; flex-wrap: wrap;
    }

    .panel-header .panel-title { margin-bottom: 0; }

    .tab-bar { display: flex; gap: 10px; margin-bottom: 20px; }

    .tab-btn {
      padding: 12px 28px; border-radius: 14px;
      border: 2px solid #e2e8f0; font-weight: 800; font-size: 1rem;
      cursor: pointer; transition: all 0.2s;
      background: white; color: var(--muted);
    }

    .tab-btn:hover { border-color: var(--primary); color: var(--primary); }

    .tab-btn.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white; border-color: transparent;
      box-shadow: 0 6px 18px rgba(62,80,119,0.25);
    }

    .tab-section { display: none; }
    .tab-section.active { display: block; }

    .kids-cat-section   { display: none; }
    .kids-cat-section.active   { display: block; }
    .junior-cat-section { display: none; }
    .junior-cat-section.active { display: block; }
    .course-select { flex:1; min-width:220px; font-size:0.95rem; font-weight:700; padding:13px 16px; border-radius:14px; border:2px solid #dbeafe; cursor:pointer; background:#fff; }
    .course-select:focus { border-color:var(--primary); outline:none; box-shadow:0 0 0 3px rgba(62,80,119,0.12); }
    .field-label { font-size:0.82rem; font-weight:800; color:#64748b; display:block; margin-bottom:8px; }

    /* Kids course rows */
    .kc-group-label { font-size:0.78rem; font-weight:900; text-transform:uppercase; letter-spacing:1px; color:var(--primary); padding:12px 4px 6px; }
    .kc-row { display:flex; align-items:center; gap:14px; width:100%; background:#f8fbff; border:1.5px solid #dbeafe; border-radius:14px; padding:14px 16px; margin-bottom:8px; cursor:pointer; text-align:left; transition:all 0.18s; }
    .kc-row:hover { background:#eff6ff; border-color:#93c5fd; transform:translateX(4px); }
    .kc-row-icon { width:40px; height:40px; border-radius:11px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
    .kc-row-body { flex:1; min-width:0; }
    .kc-row-name { font-weight:900; font-size:0.97rem; color:#0f172a; margin-bottom:5px; }
    .kc-row-meta { display:flex; gap:6px; flex-wrap:wrap; }
    .kc-arrow { color:#94a3b8; font-size:0.85rem; flex-shrink:0; }
    .kc-row:hover .kc-arrow { color:var(--primary); }
    .kc-chip { font-size:0.73rem; font-weight:700; padding:3px 9px; border-radius:999px; }
    .kc-chip-blue   { background:#dbeafe; color:#1d4ed8; }
    .kc-chip-green  { background:#dcfce7; color:#166534; }
    .kc-chip-red    { background:#fee2e2; color:#991b1b; }
    .kc-chip-gray   { background:#f1f5f9; color:#64748b; }
    .kc-chip-purple { background:#ede9fe; color:#5b21b6; }
    .kc-back-btn { display:inline-flex; align-items:center; gap:8px; background:#fff; border:1.5px solid #dbeafe; border-radius:12px; padding:10px 18px; font-weight:800; font-size:0.9rem; color:#334155; cursor:pointer; margin-bottom:16px; transition:background 0.18s; }
    .kc-back-btn:hover { background:#f1f5f9; }

    .kids-drop-item {
      display: block; width: 100%; padding: 13px 18px;
      border: none; background: none; text-align: left;
      font-weight: 700; font-size: 0.95rem; color: #0f172a;
      cursor: pointer; transition: background 0.15s;
      border-bottom: 1px solid #f1f5f9;
    }
    .kids-drop-item:last-child { border-bottom: none; }
    .kids-drop-item:hover  { background: #f0f7ff; color: var(--primary); }
    .kids-drop-item.active { background: #eff6ff; color: var(--primary); font-weight: 900; }

    .btn-module {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border: none; color: white; font-weight: 800;
      border-radius: 14px; padding: 10px 16px;
      cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
    }

    .grade-group { margin-bottom: 24px; }

    .grade-group-label {
      font-size: 1rem; font-weight: 900; color: #0f172a;
      display: block; margin-bottom: 12px;
    }

    .grade-cards { display: flex; gap: 14px; flex-wrap: wrap; }

    .grade-card {
      background: #f8fbff; border: 1px solid #dbeafe;
      border-radius: 16px; padding: 18px 22px;
      min-width: 180px; display: flex; flex-direction: column; gap: 10px;
    }

    .grade-link {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 0.85rem; font-weight: 700; color: #2563eb;
      text-decoration: none; background: #dbeafe;
      padding: 6px 12px; border-radius: 8px; transition: background 0.2s;
    }
    .grade-link:hover { background: #bfdbfe; color: #1d4ed8; }

    /* Course cards — same style as admin */
    .course-card-list { display:flex; flex-direction:column; gap:10px; margin-bottom:6px; }
    .course-card-item {
      display:flex; align-items:center; gap:14px;
      background:#f8fbff; border:1px solid #dbeafe;
      border-radius:14px; padding:14px 16px;
    }
    .course-card-thumb { flex-shrink:0; }
    .course-card-thumb img {
      width:56px; height:56px; object-fit:cover;
      border-radius:12px; border:1px solid #e5edf9;
    }
    .course-thumb-fallback {
      width:56px; height:56px; border-radius:12px;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      color:white; display:flex; align-items:center; justify-content:center;
      font-size:1.3rem; flex-shrink:0;
    }
    .course-card-body { flex:1; min-width:0; }
    .course-card-name { font-weight:900; font-size:1rem; color:#0f172a; margin-bottom:7px; }
    .course-card-meta { display:flex; flex-wrap:wrap; gap:7px; align-items:center; }
    .meta-chip {
      font-size:0.78rem; color:#475569; background:#e2e8f0;
      border-radius:999px; padding:4px 10px; font-weight:600;
    }

    .type-badge, .status-badge {
      display: inline-block; padding: 6px 12px;
      border-radius: 999px; font-size: 0.82rem; font-weight: 800;
    }
    .type-demo   { background: #dbeafe; color: #1d4ed8; }
    .type-paid   { background: #dcfce7; color: #166534; }
    .status-active   { background: #dcfce7; color: #166534; }
    .status-inactive { background: #fee2e2; color: #991b1b; }

    .empty-box {
      text-align: center; padding: 26px 18px;
      border-radius: 18px; background: #f8fbff;
      color: var(--muted); border: 1px dashed #d9e9ff; font-weight: 700;
    }

    /* Project link items */
    .tc-proj-item { display:flex; align-items:center; gap:12px; background:#f0f7ff; border:1px solid #bfdbfe; border-radius:14px; padding:13px 16px; margin-bottom:10px; }
    .tc-proj-icon { flex-shrink:0; display:flex; align-items:center; }
    .tc-proj-icon img { max-height:100px; max-width:160px; width:auto; height:auto; border-radius:10px; display:block; }
    .tc-proj-icon-fb { width:44px; height:44px; border-radius:10px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .tc-proj-actions { display:flex; flex-direction:column; gap:6px; flex-shrink:0; }
    .tc-proj-btn { display:flex; align-items:center; justify-content:center; gap:6px; font-size:0.8rem; font-weight:800; text-decoration:none; padding:8px 14px; border-radius:9px; white-space:nowrap; color:white; transition:filter 0.2s; }
    .tc-proj-btn:hover { color:white; filter:brightness(1.1); }
    .tc-proj-pdf  { background:linear-gradient(135deg,#f97316,#ea580c); box-shadow:0 4px 10px rgba(249,115,22,0.25); }
    .tc-proj-link { background:linear-gradient(135deg,#3b82f6,#1d4ed8); box-shadow:0 4px 10px rgba(59,130,246,0.25); }

    @media (max-width: 991px) {
      .app-shell { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: relative; }
      .main    { padding: 16px; }
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
        <p class="teacher-name"><?php echo htmlspecialchars($teacherName); ?></p>
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

<div class="main">

  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <div class="hero">
    <div>
      <h2>Courses</h2>
      <p>Browse all academy courses and programs</p>
    </div>
    <div style="background:rgba(255,255,255,0.15);color:white;border-radius:999px;padding:10px 16px;font-weight:800;">
      <?php echo htmlspecialchars($teacherName); ?>
    </div>
  </div>

  <!-- Tab bar -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('kids',   this)">Kids</button>
    <button class="tab-btn"        onclick="switchTab('junior', this)">Junior</button>
    <button class="tab-btn"        onclick="switchTab('demo',   this)">Demo</button>
  </div>

  <!-- Kids -->
  <div id="tab-kids" class="tab-section active">

    <!-- View 1: Course list -->
    <div id="kids-list-view">
      <div class="panel-card">
        <div class="panel-title"><i class="fas fa-child me-2"></i>Kids Courses <span style="font-size:0.82rem;font-weight:600;color:var(--muted);">(<?= count($allKidsCourses) ?>)</span></div>
        <?php if (empty($allKidsCourses)): ?>
          <div class="empty-box">No Kids courses found.</div>
        <?php else: ?>
          <?php
            $kidsGrouped = [];
            foreach ($allKidsCourses as $kc) {
                $key = $kc['category'] ?: '';
                $kidsGrouped[$key][] = $kc;
            }
            foreach ($kidsGrouped as $grpLabel => $grpItems):
          ?>
            <?php if ($grpLabel !== ''): ?>
              <div class="kc-group-label"><i class="fas fa-folder me-2"></i><?= htmlspecialchars($grpLabel) ?></div>
            <?php endif; ?>
            <?php foreach ($grpItems as $kc):
              $projCount = count($kidsCourseProjects[$kc['id']] ?? []);
            ?>
              <button class="kc-row" onclick="openKidsCourse(<?= $kc['id'] ?>)" type="button">
                <div class="kc-row-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="kc-row-body">
                  <div class="kc-row-name"><?= htmlspecialchars($kc['course_name']) ?></div>
                  <div class="kc-row-meta">
                    <?php if ($projCount > 0): ?>
                      <span class="kc-chip kc-chip-blue"><i class="fas fa-folder-open me-1"></i><?= $projCount ?> project<?= $projCount !== 1 ? 's' : '' ?></span>
                    <?php else: ?>
                      <span class="kc-chip kc-chip-gray"><i class="fas fa-folder me-1"></i>No projects</span>
                    <?php endif; ?>
                    <span class="kc-chip <?= $kc['status']==='active' ? 'kc-chip-green' : 'kc-chip-red' ?>"><?= ucfirst($kc['status']) ?></span>
                  </div>
                </div>
                <i class="fas fa-chevron-right kc-arrow"></i>
              </button>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- View 2: Course projects (hidden by default) -->
    <div id="kids-proj-view" style="display:none;">
      <button class="kc-back-btn" onclick="closeKidsCourse()" type="button">
        <i class="fas fa-arrow-left"></i> Back to Courses
      </button>
      <?php foreach ($allKidsCourses as $kc):
        $kProjects = $kidsCourseProjects[$kc['id']] ?? [];
      ?>
        <div id="kids-proj-<?= $kc['id'] ?>" class="kids-proj-panel" style="display:none;">
          <div class="panel-card">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
              <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;"><i class="fas fa-graduation-cap"></i></div>
              <div>
                <div style="font-size:1.1rem;font-weight:900;color:#0f172a;"><?= htmlspecialchars($kc['course_name']) ?></div>
                <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap;">
                  <?php if (!empty($kc['category'])): ?>
                    <span class="kc-chip kc-chip-purple"><i class="fas fa-folder me-1"></i><?= htmlspecialchars($kc['category']) ?></span>
                  <?php endif; ?>
                  <span class="kc-chip <?= $kc['status']==='active'?'kc-chip-green':'kc-chip-red' ?>"><?= ucfirst($kc['status']) ?></span>
                  <span class="kc-chip kc-chip-blue"><i class="fas fa-folder-open me-1"></i><?= count($kProjects) ?> project<?= count($kProjects)!==1?'s':'' ?></span>
                </div>
              </div>
            </div>
            <?php if (empty($kProjects)): ?>
              <div class="empty-box">
                <i class="fas fa-folder-open" style="font-size:2rem;color:#dbeafe;display:block;margin-bottom:10px;"></i>
                No projects added for this course yet.
              </div>
            <?php else: ?>

              <?= renderProjectLinks($kProjects) ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- Junior -->
  <div id="tab-junior" class="tab-section">

    <!-- View 1: Course list -->
    <div id="junior-list-view">
      <div class="panel-card">
        <div class="panel-title"><i class="fas fa-code me-2"></i>Junior Courses <span style="font-size:0.82rem;font-weight:600;color:var(--muted);">(<?= count($allJuniorCourses) ?>)</span></div>
        <?php if (empty($allJuniorCourses)): ?>
          <div class="empty-box">No Junior courses found.</div>
        <?php else: ?>
          <?php
            $juniorGrouped = [];
            foreach ($allJuniorCourses as $jc) {
                $key = $jc['category'] ?: '';
                $juniorGrouped[$key][] = $jc;
            }
            foreach ($juniorGrouped as $grpLabel => $grpItems):
          ?>
            <?php if ($grpLabel !== ''): ?>
              <div class="kc-group-label"><i class="fas fa-folder me-2"></i><?= htmlspecialchars($grpLabel) ?></div>
            <?php endif; ?>
            <?php foreach ($grpItems as $jc):
              $projCount = count($juniorCourseProjects[$jc['id']] ?? []);
            ?>
              <button class="kc-row" onclick="openJuniorCourse(<?= $jc['id'] ?>)" type="button">
                <div class="kc-row-icon"><i class="fas fa-code"></i></div>
                <div class="kc-row-body">
                  <div class="kc-row-name"><?= htmlspecialchars($jc['course_name']) ?></div>
                  <div class="kc-row-meta">
                    <?php if ($projCount > 0): ?>
                      <span class="kc-chip kc-chip-blue"><i class="fas fa-folder-open me-1"></i><?= $projCount ?> project<?= $projCount !== 1 ? 's' : '' ?></span>
                    <?php else: ?>
                      <span class="kc-chip kc-chip-gray"><i class="fas fa-folder me-1"></i>No projects</span>
                    <?php endif; ?>
                    <span class="kc-chip <?= $jc['status']==='active' ? 'kc-chip-green' : 'kc-chip-red' ?>"><?= ucfirst($jc['status']) ?></span>
                  </div>
                </div>
                <i class="fas fa-chevron-right kc-arrow"></i>
              </button>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- View 2: Course projects -->
    <div id="junior-proj-view" style="display:none;">
      <button class="kc-back-btn" onclick="closeJuniorCourse()" type="button">
        <i class="fas fa-arrow-left"></i> Back to Courses
      </button>
      <?php foreach ($allJuniorCourses as $jc):
        $jProjects = $juniorCourseProjects[$jc['id']] ?? [];
      ?>
        <div id="junior-proj-<?= $jc['id'] ?>" class="junior-proj-panel" style="display:none;">
          <div class="panel-card">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
              <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;"><i class="fas fa-code"></i></div>
              <div>
                <div style="font-size:1.1rem;font-weight:900;color:#0f172a;"><?= htmlspecialchars($jc['course_name']) ?></div>
                <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap;">
                  <?php if (!empty($jc['category'])): ?>
                    <span class="kc-chip kc-chip-purple"><i class="fas fa-folder me-1"></i><?= htmlspecialchars($jc['category']) ?></span>
                  <?php endif; ?>
                  <span class="kc-chip <?= $jc['status']==='active'?'kc-chip-green':'kc-chip-red' ?>"><?= ucfirst($jc['status']) ?></span>
                  <span class="kc-chip kc-chip-blue"><i class="fas fa-folder-open me-1"></i><?= count($jProjects) ?> project<?= count($jProjects)!==1?'s':'' ?></span>
                </div>
              </div>
            </div>
            <?php if (empty($jProjects)): ?>
              <div class="empty-box">
                <i class="fas fa-folder-open" style="font-size:2rem;color:#dbeafe;display:block;margin-bottom:10px;"></i>
                No projects added for this course yet.
              </div>
            <?php else: ?>

              <?= renderProjectLinks($jProjects) ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- Demo -->
  <div id="tab-demo" class="tab-section">
    <div class="panel-card">
      <div class="panel-title"><i class="fas fa-play-circle me-2"></i>Demo Courses</div>
      <?= renderCourseCards($demoCourses) ?>
    </div>
  </div>

</div><!-- end .main -->
</div><!-- /.app-shell -->

<script>
const tabBtns = document.querySelectorAll('.tab-btn');

function openKidsCourse(id) {
  document.querySelectorAll('.kids-proj-panel').forEach(p => p.style.display = 'none');
  const panel = document.getElementById('kids-proj-' + id);
  if (panel) panel.style.display = 'block';
  document.getElementById('kids-list-view').style.display = 'none';
  document.getElementById('kids-proj-view').style.display  = 'block';
}

function closeKidsCourse() {
  document.getElementById('kids-proj-view').style.display  = 'none';
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

function switchTab(name, btn) {
  document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
  tabBtns.forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

function toggleMenu(prefix, e) {
  e.stopPropagation();
  const d = document.getElementById(prefix + '-dropdown');
  if (!d) return;
  const open = d.style.display === 'block';
  d.style.display = open ? 'none' : 'block';
  const chev = document.getElementById(prefix + '-chevron');
  if (chev) chev.className = (open ? 'fas fa-chevron-down' : 'fas fa-chevron-up') + ' ms-2';
}

function switchCat(prefix, cat, btn) {
  document.querySelectorAll('.' + prefix + '-cat-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('#' + prefix + '-dropdown .kids-drop-item').forEach(b => b.classList.remove('active'));
  document.getElementById(prefix + '-' + cat).classList.add('active');
  btn.classList.add('active');
  const d = document.getElementById(prefix + '-dropdown');
  if (d) d.style.display = 'none';
  const chev = document.getElementById(prefix + '-chevron');
  if (chev) chev.className = 'fas fa-chevron-down ms-2';
}

document.addEventListener('click', function() {
  ['kids','junior'].forEach(p => {
    const d = document.getElementById(p + '-dropdown');
    if (d) d.style.display = 'none';
    const c = document.getElementById(p + '-chevron');
    if (c) c.className = 'fas fa-chevron-down ms-2';
  });
});
</script>
<script src="logout-modal.js"></script>
</body>
</html>

