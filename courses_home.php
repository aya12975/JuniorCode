<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";

$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS sub_section VARCHAR(50) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_unlocked TINYINT(1) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS link TEXT NOT NULL DEFAULT ''");

$error   = "";
$success = "";

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $name        = trim($_POST["course_name"]  ?? "");
        $category    = trim($_POST["category"]     ?? "");
        $section     = trim($_POST["section"]      ?? "kids");
        $sub_section = trim($_POST["sub_section"]  ?? "");
        $age_group   = trim($_POST["age_group"]    ?? "");
        $level       = trim($_POST["level"]        ?? "beginner");
        $price       = (float)($_POST["price"]     ?? 0);
        $link        = trim($_POST["link"]         ?? "");
        $duration    = trim($_POST["duration"]     ?? "");
        $image       = "";
        if (!empty($_FILES["image_file"]["tmp_name"])) {
            $ext   = strtolower(pathinfo($_FILES["image_file"]["name"], PATHINFO_EXTENSION));
            $allowed = ["jpg","jpeg","png","gif","webp"];
            if (in_array($ext, $allowed)) {
                $fname = uniqid("course_", true) . "." . $ext;
                if (move_uploaded_file($_FILES["image_file"]["tmp_name"], "uploads/courses/" . $fname))
                    $image = "uploads/courses/" . $fname;
                else $error = "Failed to save image.";
            } else { $error = "Invalid image type."; }
        }

        if ($error === "" && $name === "") $error = "Course name is required.";

        if ($error === "") {
            $stmt = $conn->prepare("INSERT INTO courses (course_name,category,section,sub_section,age_group,level,price,image,link,duration,course_type,status) VALUES (?,?,?,?,?,?,?,?,?,?,'paid','active')");
            $stmt->bind_param("ssssssdsss", $name, $category, $section, $sub_section, $age_group, $level, $price, $image, $link, $duration);
            if ($stmt->execute()) {
                header("Location: courses_home.php?msg=added&tab=" . urlencode($section));
                exit();
            } else { $error = "Failed to add course."; }
        }
    }

    if ($action === "edit") {
        $id          = (int)($_POST["id"]         ?? 0);
        $name        = trim($_POST["course_name"] ?? "");
        $category    = trim($_POST["category"]    ?? "");
        $sub_section = trim($_POST["sub_section"] ?? "");
        $age_group   = trim($_POST["age_group"]   ?? "");
        $level       = trim($_POST["level"]       ?? "beginner");
        $price       = (float)($_POST["price"]    ?? 0);
        $image       = trim($_POST["image_keep"]  ?? "");
        $link        = trim($_POST["link"]        ?? "");
        $duration    = trim($_POST["duration"]    ?? "");
        if (!empty($_FILES["image_file"]["tmp_name"])) {
            $ext   = strtolower(pathinfo($_FILES["image_file"]["name"], PATHINFO_EXTENSION));
            $allowed = ["jpg","jpeg","png","gif","webp"];
            if (in_array($ext, $allowed)) {
                $fname = uniqid("course_", true) . "." . $ext;
                if (move_uploaded_file($_FILES["image_file"]["tmp_name"], "uploads/courses/" . $fname)) {
                    if ($image !== "" && strpos($image, "uploads/courses/") === 0) @unlink($image);
                    $image = "uploads/courses/" . $fname;
                } else { $error = "Failed to save image."; }
            } else { $error = "Invalid image type."; }
        }

        if ($error === "" && $id > 0 && $name !== "") {
            $stmt = $conn->prepare("UPDATE courses SET course_name=?,category=?,sub_section=?,age_group=?,level=?,price=?,image=?,link=?,duration=? WHERE id=?");
            $stmt->bind_param("sssssdsssi", $name, $category, $sub_section, $age_group, $level, $price, $image, $link, $duration, $id);
            if ($stmt->execute()) {
                $sec2 = $conn->query("SELECT section FROM courses WHERE id=$id")->fetch_assoc()["section"] ?? "kids";
                header("Location: courses_home.php?msg=updated&tab=" . urlencode($sec2));
                exit();
            } else { $error = "Failed to update."; }
        }
    }

    if ($action === "delete") {
        $id  = (int)($_POST["id"] ?? 0);
        $sec = $_POST["section"] ?? "kids";
        if ($id > 0) {
            $conn->query("DELETE FROM student_enrollments WHERE course_id = $id");
            $conn->query("DELETE FROM course_projects    WHERE course_id = $id");
            $stmt = $conn->prepare("DELETE FROM courses WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                header("Location: courses_home.php?msg=deleted&tab=" . urlencode($sec));
                exit();
            } else { $error = "Failed to delete."; }
        }
    }
}

// ── Flash messages from redirect ──────────────────────────────────────────────
$msgMap = ["added" => "Course added successfully.", "updated" => "Course updated.", "deleted" => "Course deleted."];
if (isset($_GET["msg"]) && isset($msgMap[$_GET["msg"]])) $success = $msgMap[$_GET["msg"]];

// ── Fetch & group courses ──────────────────────────────────────────────────────
$demoCourses = $kidsCourses = $juniorCourses = [];
$res = $conn->query("SELECT * FROM courses ORDER BY category ASC, sub_section ASC, course_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        if     ($r["section"] === "demo")   $demoCourses[]   = $r;
        elseif ($r["section"] === "kids")   $kidsCourses[]   = $r;
        elseif ($r["section"] === "junior") $juniorCourses[] = $r;
    }
}

function groupCourses(array $courses, string $key): array {
    $g = [];
    foreach ($courses as $c) { $g[$c[$key] ?: "General"][] = $c; }
    return $g;
}
$demoGroups   = groupCourses($demoCourses,   "sub_section");
$kidsGroups   = groupCourses($kidsCourses,   "category");
$juniorGroups = groupCourses($juniorCourses, "category");

$subLabels = [
    "little" => ["label" => "Little",  "grade" => "Grade 1 – Grade 3",  "age" => "6 to 8 Years"],
    "junior" => ["label" => "Junior",  "grade" => "Grade 4 – Grade 12", "age" => "9 to 17 Years"],
];

$gradients = [
    "#7a5b35,#6aa35c","#18b6d4,#79d9ef","#0f86d6,#45c1ec",
    "#7c3aed,#a78bfa","#059669,#34d399","#dc2626,#f87171",
    "#d97706,#fbbf24","#1d4ed8,#60a5fa",
];
$gi = 0;

// Determine active tab (from redirect or GET param)
$allowed_tabs = ["demo", "kids", "junior"];
$activeTab = in_array($_GET["tab"] ?? "", $allowed_tabs) ? $_GET["tab"] : "kids";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Courses | JuniorCode Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --accent:#2c4383; --muted:#647596; --border:#d7dce7; }
* { box-sizing:border-box; }
body { margin:0; font-family:Arial,sans-serif; background:#eef2f7; color:#27344f; }

/* ── App shell ── */
.app-shell { display:flex; min-height:100vh; }

/* ── Sidebar ── */
.sidebar { width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:#fff; padding:0; position:sticky; top:0; height:100vh; overflow-y:auto; display:flex; flex-direction:column; transition:width .3s,padding .3s,min-width .3s; flex-shrink:0; }
body.sidebar-collapsed .sidebar { width:0; min-width:0; overflow:hidden; }
.sidebar-top-area { padding:0 18px 18px; }
.brand { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
.brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
.brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
.brand-sub { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
.nav-custom { display:flex; flex-direction:column; gap:4px; }
.nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all .25s; font-weight:700; }
.nav-link-custom:hover { background:rgba(255,255,255,0.08); color:#fff; }
.nav-link-custom.active { background:linear-gradient(135deg,#3e5077,#143674); color:#fff; box-shadow:0 10px 24px rgba(37,99,235,0.28); }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.sidebar-bottom { padding:16px 18px; margin-top:auto; }

/* ── Main ── */
.main-content { flex:1; display:flex; flex-direction:column; min-width:0; }
.page { padding:28px 32px 44px; flex:1; }

/* ── Top bar ── */
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }
.topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:28px; padding:20px 24px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:24px; box-shadow:0 16px 40px rgba(62,80,119,0.3); }
.topbar h1 { font-size:1.8rem; font-weight:900; margin:0; color:#fff; }
.topbar p { margin:4px 0 0; color:rgba(255,255,255,0.8); }
.admin-badge { background:rgba(255,255,255,0.2); color:#fff; border-radius:12px; border:1px solid rgba(255,255,255,0.25); padding:10px 18px; font-weight:800; white-space:nowrap; }

/* ── Nav wrap (tabs + selector) ── */
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
.select-area { width:260px; position:relative; }
.module-select {
    width:100%; padding:12px 14px; border:2px solid #2c4383;
    border-radius:6px; color:#2c4383; font-weight:700;
    background:white; font-size:0.9rem; cursor:pointer;
    appearance:none; -webkit-appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%232c4383' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 12px center;
    padding-right:36px;
}
.module-select:focus { outline:none; border-color:#1f2f63; }

/* ── Content area ── */
.content-area {
    background:white; border-radius:8px; padding:28px 30px;
    min-height:380px; box-shadow:0 2px 8px rgba(0,0,0,0.1);
}
.content-area h1 {
    margin:0 0 6px; font-size:24px; font-weight:800;
    padding-bottom:14px; border-bottom:2px solid var(--border);
}
.content-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
.content-title { font-size:24px; font-weight:800; }
.content-divider { border:none; border-top:2px solid var(--border); margin:0 0 26px; }

/* ── Empty state ── */
.empty-msg { height:300px; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--muted); gap:12px; }
.empty-msg i { font-size:2.4rem; opacity:0.3; }

/* ── Category label ── */
.cat-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:1.2px; color:var(--muted); font-weight:700; margin:0 0 16px; }

/* ── Cards grid ── */
.cards-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; margin-bottom:32px; }

/* ── Course card ── */
.course-card { background:white; border:1px solid #dce1ec; border-radius:10px; overflow:hidden; box-shadow:0 2px 5px rgba(0,0,0,0.06); transition:transform 0.15s,box-shadow 0.15s; }
.course-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,0.1); }
.card-thumb { height:155px; background-size:cover; background-position:center; }
.card-body-inner { padding:14px 15px 17px; }
.card-name { font-size:15px; font-weight:800; margin-bottom:5px; }
.card-meta { font-size:12px; color:var(--muted); margin-bottom:14px; min-height:32px; }
.btn-main-card { width:100%; background:#1f2f63; color:white; border:none; padding:11px; border-radius:6px; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:block; text-align:center; margin-bottom:7px; transition:background 0.2s; }
.btn-main-card:hover { background:#2d4580; color:white; }
.btn-main-card.enroll { background:#166534; }
.btn-main-card.enroll:hover { background:#14532d; }
.card-sub-row { display:flex; gap:6px; }
.btn-sm { flex:1; padding:7px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; border:none; text-align:center; }
.btn-edit   { background:#dbeafe; color:#1d4ed8; }
.btn-edit:hover   { background:#bfdbfe; }
.btn-del    { background:#fee2e2; color:#991b1b; }
.btn-del:hover    { background:#fecaca; }
.btn-proj   { background:#f0fdf4; color:#166534; }
.btn-proj:hover   { background:#dcfce7; }

/* ── Add button ── */
.btn-add { background:#1f2f63; color:white; border:none; padding:10px 20px; border-radius:7px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:7px; font-size:0.88rem; }
.btn-add:hover { background:#2d4580; }

/* ── Modal ── */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:1000; align-items:center; justify-content:center; padding:16px; }
.modal-overlay.show { display:flex; }
.modal-box { background:white; border-radius:14px; padding:26px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 50px rgba(0,0,0,0.2); }
.modal-title { font-size:1.05rem; font-weight:900; margin-bottom:18px; }
.form-lbl { font-weight:700; font-size:0.85rem; margin-bottom:4px; display:block; }
.form-ctrl { border-radius:7px; padding:10px 12px; border:1px solid #ccd5e8; font-size:0.9rem; width:100%; }
.form-ctrl:focus { border-color:#2c4383; outline:none; box-shadow:0 0 0 3px rgba(44,67,131,0.1); }
.img-upload-wrap { border:2px dashed #ccd5e8; border-radius:7px; padding:14px 12px; text-align:center; cursor:pointer; transition:border-color .2s; }
.img-upload-wrap:hover { border-color:#2c4383; }
.img-upload-wrap input[type=file] { display:none; }
.img-upload-label { font-size:0.85rem; color:var(--muted); cursor:pointer; }
.img-preview-box { margin-top:8px; display:none; }
.img-preview-box img { height:70px; border-radius:7px; border:1px solid #dbeafe; object-fit:cover; }
.form-row2 { display:grid; grid-template-columns:1fr 1fr; gap:13px; margin-bottom:13px; }
.form-grp  { margin-bottom:13px; }
.btn-submit { background:#1f2f63; color:white; border:none; padding:11px 22px; border-radius:7px; font-weight:800; cursor:pointer; }
.btn-submit:hover { background:#2d4580; }
.btn-cancel { background:#e2e8f0; color:#334155; border:none; padding:11px 22px; border-radius:7px; font-weight:700; cursor:pointer; }
.btn-cancel:hover { background:#cbd5e1; }
.section-radios { display:flex; gap:8px; }
.sec-opt { flex:1; border:2px solid #dce1ec; border-radius:7px; padding:9px; text-align:center; cursor:pointer; font-weight:700; font-size:0.83rem; transition:all 0.15s; }
.sec-opt input { display:none; }
.sec-opt.selected { border-color:#1f2f63; background:#eef3ff; color:#1f2f63; }
.hint { font-size:0.75rem; color:var(--muted); }

/* ── Alerts ── */
.alert-wrap { margin-bottom:18px; }

/* ── Tab panels ── */
.tab-panel { display:none; }
.tab-panel.active { display:block; }

@media (max-width:900px) {
    .cards-grid { grid-template-columns:1fr; }
    .nav-wrap { flex-direction:column; align-items:stretch; gap:0; padding:0 20px 16px; }
    .select-area { width:100%; }
    .page { padding:16px; }
}
</style>
</head>
<body>
<div class="app-shell">

  <!-- ── Sidebar ─────────────────────────────────────────────────── -->
  <aside class="sidebar">
    <div class="sidebar-top-area">
      <div class="brand">
        <img src="images/robot2.png.png" class="brand-logo-img" alt="Logo">
        <div><div class="brand-title">JuniorCode</div><div class="brand-sub">Admin Panel</div></div>
      </div>
      <div class="nav-title">Main</div>
      <div class="nav-custom">
        <a href="admin_dashboard.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
        <a href="manage_users.php"           class="nav-link-custom"><span class="nav-icon"><i class="fas fa-users"></i></span><span>Manage Users</span></a>
        <a href="admin_teacher_students.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span></a>
        <a href="admin_enrollments.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-user-check"></i></span><span>Enrollments</span></a>
        <a href="manage_classes.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
        <a href="teacher_earnings.php"       class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
        <a href="available_slots.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
        <a href="courses_home.php"           class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
        <a href="manage_projects.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-folder-open"></i></span><span>Projects</span></a>
        <a href="reports.php"               class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
        <a href="admin_certificates.php"    class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
      </div>
    </div>
    <div class="sidebar-bottom">
      <a href="settings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
      <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
      <a href="logout.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
    </div>
  </aside>

  <!-- ── Main Content ────────────────────────────────────────────── -->
  <div class="main-content">
    <div class="page">

      <!-- Alerts -->
      <?php if ($success || $error): ?>
      <div class="alert-wrap">
        <?php if ($success): ?>
          <div class="alert alert-success alert-dismissible fade show mb-0" style="border-radius:8px;">
            <i class="fas fa-circle-check me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger alert-dismissible fade show mb-0" style="border-radius:8px;">
            <i class="fas fa-circle-xmark me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Top bar -->
      <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
      </div>
      <div class="topbar">
        <div>
          <h1><i class="fas fa-graduation-cap me-2"></i>Courses</h1>
          <p>Teaching Curriculum &amp; Resources</p>
        </div>
        <div class="admin-badge"><?= htmlspecialchars($adminName) ?></div>
      </div>

      <!-- Nav wrap: tabs + module selector -->
      <div class="nav-wrap">
        <div class="tabs">
          <button class="tab-item" id="tab-btn-demo"   onclick="switchTab('demo')">Demo Sessions</button>
          <button class="tab-item" id="tab-btn-kids"   onclick="switchTab('kids')">Little Course</button>
          <button class="tab-item" id="tab-btn-junior" onclick="switchTab('junior')">Junior Course</button>
        </div>

        <!-- Kids module selector -->
        <div class="select-area" id="kids-selector" style="display:none;">
          <select class="module-select" id="kids-module-select" onchange="filterModule('kids', this.value)">
            <option value="">Select Little Module</option>
            <?php foreach (array_keys($kidsGroups) as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Junior module selector -->
        <div class="select-area" id="junior-selector" style="display:none;">
          <select class="module-select" id="junior-module-select" onchange="filterModule('junior', this.value)">
            <option value="">Select Junior Module</option>
            <?php foreach (array_keys($juniorGroups) as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- ══ CONTENT AREA ══════════════════════════════════════════ -->

      <!-- DEMO TAB -->
      <div id="tab-demo" class="tab-panel">
        <div class="content-area">
          <div class="content-header">
            <div class="content-title">Demo Sessions</div>
            <button class="btn-add" onclick="openAddModal('demo')"><i class="fas fa-plus"></i> Add Demo Course</button>
          </div>
          <hr class="content-divider">

          <?php if (empty($demoCourses)): ?>
            <div class="empty-msg"><i class="fas fa-play-circle"></i>No demo courses yet. Click "Add Demo Course" to get started.</div>
          <?php else:
            $subOrder = ["little","junior",""];
            $done = [];
            foreach ($subOrder as $sub):
              if (!isset($demoGroups[$sub])) continue;
              $done[] = $sub;
              $info = $subLabels[$sub] ?? ["label"=>ucfirst($sub),"grade"=>"","age"=>""];
          ?>
            <h2 style="font-size:18px;font-weight:800;margin:0 0 4px;"><?= $info["label"] ?></h2>
            <?php if ($info["grade"]): ?><div style="font-size:13px;color:var(--muted);font-weight:600;margin-bottom:18px;"><?= $info["grade"] ?> &nbsp;|&nbsp; <?= $info["age"] ?></div><?php endif; ?>
            <div class="cards-grid">
              <?php foreach ($demoGroups[$sub] as $c):
                $grad = $gradients[$gi++ % count($gradients)]; ?>
                <?= renderCard($c, $grad) ?>
              <?php endforeach; ?>
            </div>
          <?php endforeach;
            foreach ($demoGroups as $sub => $courses):
              if (in_array($sub, $done)) continue; ?>
              <h2 style="font-size:18px;font-weight:800;margin:24px 0 18px;"><?= htmlspecialchars($sub ?: "Other") ?></h2>
              <div class="cards-grid">
                <?php foreach ($courses as $c): $grad = $gradients[$gi++ % count($gradients)]; ?>
                  <?= renderCard($c, $grad) ?>
                <?php endforeach; ?>
              </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- KIDS TAB -->
      <div id="tab-kids" class="tab-panel">
        <div class="content-area">
          <div class="content-header">
            <div class="content-title" id="kids-title">Little Course <span style="font-size:15px;font-weight:600;color:var(--muted);">(Grade 1 – Grade 3)</span></div>
            <button class="btn-add" onclick="openAddModal('kids')"><i class="fas fa-plus"></i> Add Course</button>
          </div>
          <hr class="content-divider">

          <!-- Default: no module selected -->
          <div id="kids-empty" class="empty-msg">
            <i class="fas fa-hand-pointer"></i>
            Please select a module to view courses.
          </div>

          <!-- Course sections per category -->
          <?php foreach ($kidsGroups as $cat => $courses): ?>
          <div class="kids-cat-section" data-cat="<?= htmlspecialchars($cat) ?>" style="display:none;">
            <div class="cat-label"><i class="fas fa-star me-1"></i><?= htmlspecialchars($cat) ?></div>
            <div class="cards-grid">
              <?php foreach ($courses as $c): $grad = $gradients[$gi++ % count($gradients)]; ?>
                <?= renderCard($c, $grad) ?>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <?php if (empty($kidsCourses)): ?>
            <div class="empty-msg"><i class="fas fa-star"></i>No Little courses yet. Click "Add Course" to get started.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- JUNIOR TAB -->
      <div id="tab-junior" class="tab-panel">
        <div class="content-area">
          <div class="content-header">
            <div class="content-title" id="junior-title">Junior Course <span style="font-size:15px;font-weight:600;color:var(--muted);">(Grade 4 – Grade 12)</span></div>
            <button class="btn-add" onclick="openAddModal('junior')"><i class="fas fa-plus"></i> Add Course</button>
          </div>
          <hr class="content-divider">

          <div id="junior-empty" class="empty-msg">
            <i class="fas fa-hand-pointer"></i>
            Please select a module to view courses.
          </div>

          <?php foreach ($juniorGroups as $cat => $courses): ?>
          <div class="junior-cat-section" data-cat="<?= htmlspecialchars($cat) ?>" style="display:none;">
            <div class="cat-label"><i class="fas fa-rocket me-1"></i><?= htmlspecialchars($cat) ?></div>
            <div class="cards-grid">
              <?php foreach ($courses as $c): $grad = $gradients[$gi++ % count($gradients)]; ?>
                <?= renderCard($c, $grad) ?>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <?php if (empty($juniorCourses)): ?>
            <div class="empty-msg"><i class="fas fa-rocket"></i>No Junior courses yet. Click "Add Course" to get started.</div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /page -->
  </div><!-- /main-content -->
</div><!-- /app-shell -->

<?php
function renderCard(array $c, string $grad): string {
    $id    = (int)$c["id"];
    $name  = htmlspecialchars($c["course_name"]);
    $meta  = htmlspecialchars(implode(" · ", array_filter([ucfirst($c["level"] ?? ""), $c["age_group"] ?? ""])));
    $img   = $c["image"] ? 'background-image:url('.htmlspecialchars($c["image"]).');background-size:cover;' : "background:linear-gradient(135deg,{$grad});";
    $link  = $c["link"]  ? '<a href="'.htmlspecialchars($c["link"]).'" target="_blank" class="btn-main-card"><i class="fas fa-play me-1"></i> View Demo</a>' : '';
    $cJson = htmlspecialchars(json_encode($c), ENT_QUOTES);
    $cName = addslashes($c["course_name"]);
    $isDemo = $c["section"] === "demo";

    $html  = '<div class="course-card">';
    $html .= '  <div class="card-thumb" style="'.$img.'"></div>';
    $html .= '  <div class="card-body-inner">';
    $html .= '    <div class="card-name">'.$name.'</div>';
    $html .= '    <div class="card-meta">'.$meta.'</div>';
    if ($isDemo && $link) $html .= $link;
    $html .= '    <a href="manage_projects.php?course_id='.$id.'" class="btn-main-card"><i class="fas fa-folder-open me-1"></i> Manage Projects</a>';
    if (!$isDemo) $html .= '    <a href="admin_enrollments.php?course_id='.$id.'" class="btn-main-card enroll"><i class="fas fa-user-plus me-1"></i> Enroll Students</a>';
    $html .= '    <div class="card-sub-row">';
    $html .= '      <button class="btn-sm btn-edit" onclick="openEditModal('.$cJson.')"><i class="fas fa-pen"></i> Edit</button>';
    $html .= '      <button class="btn-sm btn-del"  onclick="openDeleteModal('.$id.',\''.$cName.'\',\''.htmlspecialchars($c['section']).'\')"><i class="fas fa-trash"></i> Delete</button>';
    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';
    return $html;
}
?>

<!-- ── ADD MODAL ───────────────────────────────────────────────────── -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-title"><i class="fas fa-plus-circle me-2" style="color:#1f2f63"></i>Add New Course</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action"  value="add">
      <input type="hidden" name="section" id="addSection" value="kids">

      <div class="form-grp">
        <label class="form-lbl">Section</label>
        <div class="section-radios">
          <label class="sec-opt" id="opt-demo"   onclick="setSection('demo')">  <input type="radio" name="_sec" value="demo">   Demo</label>
          <label class="sec-opt" id="opt-kids"   onclick="setSection('kids')">  <input type="radio" name="_sec" value="kids">   Little</label>
          <label class="sec-opt" id="opt-junior" onclick="setSection('junior')"><input type="radio" name="_sec" value="junior"> Junior</label>
        </div>
      </div>

      <div class="form-row2">
        <div>
          <label class="form-lbl">Course Name <span style="color:red">*</span></label>
          <input type="text" name="course_name" class="form-ctrl" placeholder="e.g. Angry Bird" required>
        </div>
        <div>
          <label class="form-lbl">Category / Module</label>
          <input type="text" name="category" class="form-ctrl" placeholder="e.g. Game Development">
        </div>
      </div>

      <div class="form-grp" id="addSubGroup" style="display:none;">
        <label class="form-lbl">Age Group (Demo)</label>
        <select name="sub_section" class="form-ctrl">
          <option value="little">Little — Grade 1 to 3</option>
          <option value="junior">Junior — Grade 4 to 12</option>
        </select>
      </div>

      <div id="addExtraFields">
        <div class="form-row2">
          <div>
            <label class="form-lbl">Level</label>
            <select name="level" class="form-ctrl">
              <option value="beginner">Beginner</option>
              <option value="intermediate">Intermediate</option>
              <option value="advanced">Advanced</option>
            </select>
          </div>
          <div>
            <label class="form-lbl">Age Group <span class="hint">(opt.)</span></label>
            <input type="text" name="age_group" class="form-ctrl" placeholder="e.g. 8 to 12 Years">
          </div>
        </div>
        <div class="form-row2">
          <div>
            <label class="form-lbl">Price ($) <span class="hint">0 = free</span></label>
            <input type="number" name="price" class="form-ctrl" value="0" min="0" step="0.01">
          </div>
          <div>
            <label class="form-lbl">Duration <span class="hint">(opt.)</span></label>
            <input type="text" name="duration" class="form-ctrl" placeholder="e.g. 8 weeks">
          </div>
        </div>
      </div>

      <div class="form-grp">
        <label class="form-lbl">Course Image <span class="hint">(opt.)</span></label>
        <div class="img-upload-wrap" onclick="document.getElementById('addImgFile').click()">
          <label class="img-upload-label"><i class="fas fa-upload me-1"></i>Click to upload a photo</label>
          <input type="file" name="image_file" id="addImgFile" accept="image/*" onchange="previewFile(this,'addImgPreview','addImgPreviewImg')">
        </div>
        <div class="img-preview-box" id="addImgPreview"><img id="addImgPreviewImg" src=""></div>
      </div>

      <div class="form-grp" id="addLinkGroup">
        <label class="form-lbl">Demo Link <span class="hint">(opt. — shown as "View Demo" button)</span></label>
        <input type="text" name="link" class="form-ctrl" placeholder="https://scratch.mit.edu/...">
      </div>

      <div style="display:flex;gap:10px;margin-top:4px;">
        <button type="submit" class="btn-submit"><i class="fas fa-plus me-1"></i> Add Course</button>
        <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ── EDIT MODAL ──────────────────────────────────────────────────── -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-title"><i class="fas fa-pen me-2" style="color:#1f2f63"></i>Edit Course</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action"     value="edit">
      <input type="hidden" name="id"         id="editId">
      <input type="hidden" name="image_keep" id="editImageKeep">
      <div class="form-row2">
        <div>
          <label class="form-lbl">Course Name <span style="color:red">*</span></label>
          <input type="text" name="course_name" id="editName" class="form-ctrl" required>
        </div>
        <div>
          <label class="form-lbl">Category / Module</label>
          <input type="text" name="category" id="editCategory" class="form-ctrl">
        </div>
      </div>
      <div class="form-grp" id="editSubGroup">
        <label class="form-lbl">Age Group (Demo)</label>
        <select name="sub_section" id="editSubSection" class="form-ctrl">
          <option value="little">Little — Grade 1 to 3</option>
          <option value="junior">Junior — Grade 4 to 12</option>
        </select>
      </div>
      <div class="form-row2">
        <div>
          <label class="form-lbl">Level</label>
          <select name="level" id="editLevel" class="form-ctrl">
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>
        <div>
          <label class="form-lbl">Age Group</label>
          <input type="text" name="age_group" id="editAgeGroup" class="form-ctrl">
        </div>
      </div>
      <div class="form-row2">
        <div>
          <label class="form-lbl">Price ($)</label>
          <input type="number" name="price" id="editPrice" class="form-ctrl" min="0" step="0.01">
        </div>
        <div>
          <label class="form-lbl">Duration</label>
          <input type="text" name="duration" id="editDuration" class="form-ctrl">
        </div>
      </div>
      <div class="form-grp">
        <label class="form-lbl">Course Image <span class="hint">(opt.)</span></label>
        <div class="img-preview-box" id="editCurrentImgWrap" style="margin-bottom:8px;">
          <img id="editCurrentImg" src="" style="height:70px;border-radius:7px;border:1px solid #dbeafe;object-fit:cover;">
          <div style="font-size:0.75rem;color:var(--muted);margin-top:3px;">Current image — upload a new one to replace it</div>
        </div>
        <div class="img-upload-wrap" onclick="document.getElementById('editImgFile').click()">
          <label class="img-upload-label"><i class="fas fa-upload me-1"></i>Click to upload a new photo</label>
          <input type="file" name="image_file" id="editImgFile" accept="image/*" onchange="previewFile(this,'editImgPreview','editImgPreviewImg')">
        </div>
        <div class="img-preview-box" id="editImgPreview"><img id="editImgPreviewImg" src=""></div>
      </div>
      <div class="form-grp">
        <label class="form-lbl">Demo Link <span class="hint">(opt.)</span></label>
        <input type="text" name="link" id="editLink" class="form-ctrl">
      </div>
      <div style="display:flex;gap:10px;margin-top:4px;">
        <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i> Save Changes</button>
        <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ── DELETE MODAL ────────────────────────────────────────────────── -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box" style="max-width:380px;text-align:center;">
    <div style="width:56px;height:56px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
      <i class="fas fa-trash" style="color:#dc2626;font-size:1.3rem;"></i>
    </div>
    <div class="modal-title" style="margin-bottom:8px;">Delete Course?</div>
    <p id="deleteMsg" style="color:var(--muted);font-size:0.88rem;margin-bottom:20px;"></p>
    <form method="POST">
      <input type="hidden" name="action"  value="delete">
      <input type="hidden" name="id"      id="deleteId">
      <input type="hidden" name="section" id="deleteSection">
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="submit" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:11px 24px;border-radius:7px;font-weight:800;cursor:pointer;">
          <i class="fas fa-trash me-1"></i> Yes, Delete
        </button>
        <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Tab switching ─────────────────────────────────────────────────────
function switchTab(name) {
  ['demo','kids','junior'].forEach(t => {
    document.getElementById('tab-'+t).classList.remove('active');
    document.getElementById('tab-btn-'+t).classList.remove('active');
  });
  document.getElementById('tab-'+name).classList.add('active');
  document.getElementById('tab-btn-'+name).classList.add('active');

  // Show/hide module selectors
  document.getElementById('kids-selector').style.display   = name === 'kids'   ? 'block' : 'none';
  document.getElementById('junior-selector').style.display = name === 'junior' ? 'block' : 'none';
}

// ── Module filter ─────────────────────────────────────────────────────
function filterModule(tab, cat) {
  const sections = document.querySelectorAll('.' + tab + '-cat-section');
  const emptyEl  = document.getElementById(tab + '-empty');

  if (!cat) {
    sections.forEach(s => s.style.display = 'none');
    if (emptyEl) emptyEl.style.display = 'flex';
    return;
  }
  if (emptyEl) emptyEl.style.display = 'none';
  sections.forEach(s => {
    s.style.display = s.dataset.cat === cat ? 'block' : 'none';
  });
}

// ── Add modal ─────────────────────────────────────────────────────────
function setSection(sec) {
  document.getElementById('addSection').value = sec;
  ['demo','kids','junior'].forEach(s => document.getElementById('opt-'+s).classList.remove('selected'));
  document.getElementById('opt-'+sec).classList.add('selected');
  document.getElementById('addSubGroup').style.display    = sec === 'demo' ? 'block' : 'none';
  document.getElementById('addExtraFields').style.display = sec === 'demo' ? 'none'  : 'block';
  document.getElementById('addLinkGroup').style.display   = sec === 'demo' ? 'block' : 'none';
}
function openAddModal(sec) {
  setSection(sec || 'kids');
  document.getElementById('addModal').classList.add('show');
}
function closeAddModal() {
  document.getElementById('addModal').classList.remove('show');
  document.getElementById('addImgFile').value = '';
  document.getElementById('addImgPreview').style.display = 'none';
}
document.getElementById('addModal').addEventListener('click', e => { if (e.target === document.getElementById('addModal')) closeAddModal(); });

// ── Edit modal ────────────────────────────────────────────────────────
function openEditModal(c) {
  document.getElementById('editId').value         = c.id;
  document.getElementById('editName').value       = c.course_name    || '';
  document.getElementById('editCategory').value   = c.category       || '';
  document.getElementById('editLevel').value      = c.level          || 'beginner';
  document.getElementById('editAgeGroup').value   = c.age_group      || '';
  document.getElementById('editPrice').value      = c.price          || 0;
  document.getElementById('editDuration').value   = c.duration       || '';
  document.getElementById('editImageKeep').value  = c.image          || '';
  document.getElementById('editLink').value       = c.link           || '';
  document.getElementById('editSubSection').value = c.sub_section    || 'little';
  document.getElementById('editSubGroup').style.display = c.section === 'demo' ? 'block' : 'none';
  // Show current image if exists
  const wrap = document.getElementById('editCurrentImgWrap');
  const curImg = document.getElementById('editCurrentImg');
  if (c.image) { curImg.src = c.image; wrap.style.display = 'block'; }
  else { wrap.style.display = 'none'; }
  // Reset new file preview
  document.getElementById('editImgFile').value = '';
  document.getElementById('editImgPreview').style.display = 'none';
  document.getElementById('editModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
document.getElementById('editModal').addEventListener('click', e => { if (e.target === document.getElementById('editModal')) closeEditModal(); });

// ── Delete modal ──────────────────────────────────────────────────────
function openDeleteModal(id, name, section) {
  document.getElementById('deleteId').value      = id;
  document.getElementById('deleteSection').value = section || 'kids';
  document.getElementById('deleteMsg').textContent = 'Delete "' + name + '"? All projects and enrollments will also be removed.';
  document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('show'); }
document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === document.getElementById('deleteModal')) closeDeleteModal(); });

// ── Image preview ─────────────────────────────────────────────────────
function previewFile(input, wrapId, imgId) {
  const wrap = document.getElementById(wrapId);
  const img  = document.getElementById(imgId);
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; wrap.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  } else {
    wrap.style.display = 'none';
  }
}

// ── Init: restore active tab after POST ───────────────────────────────
switchTab('<?= $activeTab ?>');
</script>
</body>
</html>
