<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";

$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_unlocked TINYINT(1) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS category VARCHAR(100) NOT NULL DEFAULT ''");
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
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS image   TEXT NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS pdf_url TEXT NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS course_id INT DEFAULT NULL");

// ── POST: add course ─────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_course") {
    $name     = trim($_POST["course_name"] ?? "");
    $category = trim($_POST["category"]    ?? "");
    $price    = (float)($_POST["price"]    ?? 0);
    $type     = trim($_POST["course_type"] ?? "paid");
    if ($name !== "") {
        $stmt = $conn->prepare("INSERT INTO courses (course_name, section, category, price, course_type, status) VALUES (?, 'junior', ?, ?, ?, 'active')");
        if ($stmt) { $stmt->bind_param("ssds", $name, $category, $price, $type); $stmt->execute(); $stmt->close(); }
    }
    header("Location: courses_junior.php?success=1");
    exit();
}

// ── POST: add project ────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_project") {
    $courseId = (int)($_POST["course_id"] ?? 0);
    $title    = trim($_POST["title"]    ?? "");
    $url      = trim($_POST["url"]      ?? "");
    $image    = trim($_POST["image"]    ?? "");
    $pdfUrl   = "";
    $pdfLink  = trim($_POST["pdf_link"] ?? "");

    if ($courseId > 0 && $title !== "") {
        $row = [];
        $cr = $conn->prepare("SELECT section, category FROM courses WHERE id = ?");
        if ($cr) { $cr->bind_param("i", $courseId); $cr->execute(); $row = $cr->get_result()->fetch_assoc() ?: []; $cr->close(); }
        $sec = $row["section"] ?? "junior";
        $cat = $row["category"] ?? "";

        if (!empty($_FILES["pdf_file"]["name"])) {
            if (!is_dir("uploads/pdfs")) mkdir("uploads/pdfs", 0755, true);
            $ext = strtolower(pathinfo($_FILES["pdf_file"]["name"], PATHINFO_EXTENSION));
            if ($ext === "pdf") {
                $fname = uniqid("proj_", true) . ".pdf";
                if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], "uploads/pdfs/" . $fname)) $pdfUrl = $fname;
            }
        } elseif ($pdfLink !== "") {
            $pdfUrl = $pdfLink;
        }

        $ins = $conn->prepare("INSERT INTO course_projects (course_id, section, category, title, url, image, pdf_url) VALUES (?,?,?,?,?,?,?)");
        if ($ins) { $ins->bind_param("issssss", $courseId, $sec, $cat, $title, $url, $image, $pdfUrl); $ins->execute(); }
    }
    $back = (int)($_POST["course_id"] ?? 0);
    header("Location: courses_junior.php?selected=" . $back . "&proj_added=1");
    exit();
}

// ── POST: edit project ───────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "edit_project") {
    $pid      = (int)($_POST["project_id"] ?? 0);
    $courseId = (int)($_POST["course_id"]  ?? 0);
    $title    = trim($_POST["title"]    ?? "");
    $url      = trim($_POST["url"]      ?? "");
    $image    = trim($_POST["image"]    ?? "");
    $pdfUrl   = trim($_POST["existing_pdf"] ?? "");
    $pdfLink  = trim($_POST["pdf_link"] ?? "");

    if ($pid > 0 && $title !== "") {
        if (!empty($_FILES["pdf_file"]["name"])) {
            if (!is_dir("uploads/pdfs")) mkdir("uploads/pdfs", 0755, true);
            $ext = strtolower(pathinfo($_FILES["pdf_file"]["name"], PATHINFO_EXTENSION));
            if ($ext === "pdf") {
                $fname = uniqid("proj_", true) . ".pdf";
                if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], "uploads/pdfs/" . $fname)) $pdfUrl = $fname;
            }
        } elseif ($pdfLink !== "") {
            $pdfUrl = $pdfLink;
        }
        $upd = $conn->prepare("UPDATE course_projects SET title=?, url=?, image=?, pdf_url=? WHERE id=?");
        if ($upd) { $upd->bind_param("ssssi", $title, $url, $image, $pdfUrl, $pid); $upd->execute(); }
    }
    header("Location: courses_junior.php?selected=" . $courseId . "&proj_edited=1");
    exit();
}

// ── POST: delete course ──────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_course") {
    $id = (int)($_POST["course_id"] ?? 0);
    if ($id > 0) {
        $conn->query("DELETE FROM student_enrollments WHERE course_id = $id");
        $conn->query("DELETE FROM course_projects WHERE course_id = $id");
        $del = $conn->prepare("DELETE FROM courses WHERE id = ? AND section = 'junior'");
        if ($del) { $del->bind_param("i", $id); $del->execute(); }
    }
    header("Location: courses_junior.php?success=1");
    exit();
}

// ── POST: delete project ─────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_project") {
    $pid      = (int)($_POST["project_id"] ?? 0);
    $courseId = (int)($_POST["course_id"]  ?? 0);
    if ($pid > 0) {
        $del = $conn->prepare("DELETE FROM course_projects WHERE id = ?");
        if ($del) { $del->bind_param("i", $pid); $del->execute(); }
    }
    header("Location: courses_junior.php?selected=" . $courseId);
    exit();
}

// ── Fetch ────────────────────────────────────────────────────────────────────
$allCourses = [];
$res = $conn->query("SELECT * FROM courses WHERE section='junior' ORDER BY category ASC, course_name ASC");
if ($res) $allCourses = $res->fetch_all(MYSQLI_ASSOC);

$existCats = [];
$cr = $conn->query("SELECT DISTINCT category FROM courses WHERE section='junior' AND category != '' ORDER BY category ASC");
if ($cr) $existCats = array_column($cr->fetch_all(MYSQLI_ASSOC), 'category');

$selectedId = (int)($_GET["selected"] ?? 0);
$selectedCourse = null;
$selectedProjects = [];

if ($selectedId > 0) {
    foreach ($allCourses as $c) {
        if ((int)$c["id"] === $selectedId) { $selectedCourse = $c; break; }
    }
    if ($selectedCourse) {
        $ps = $conn->prepare("SELECT * FROM course_projects WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
        if ($ps) { $ps->bind_param("i", $selectedId); $ps->execute(); $selectedProjects = $ps->get_result()->fetch_all(MYSQLI_ASSOC); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Junior Courses | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:Arial,Helvetica,sans-serif; background:radial-gradient(circle at top left,rgba(62,80,119,0.07),transparent 22%),radial-gradient(circle at bottom right,rgba(37,99,235,0.05),transparent 22%),linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); color:var(--dark); }
    .app-shell { min-height:100vh; display:flex; }

    /* Sidebar */
    .sidebar { width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:white; padding:0; position:sticky; top:0; height:100vh; overflow-y:auto; display:flex; flex-direction:column; transition:width .3s,padding .3s; }
    body.sidebar-collapsed .sidebar { width:0; min-width:0; overflow:hidden; }
    .sidebar-top-area { padding:0 18px 18px; }
    .brand { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
    .brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
    .brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
    .brand-sub { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
    .nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
    .nav-custom { display:flex; flex-direction:column; gap:4px; }
    .nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all .25s; font-weight:700; }
    .nav-link-custom:hover { background:rgba(255,255,255,0.08); color:white; }
    .nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; box-shadow:0 10px 24px rgba(37,99,235,0.28); }
    .nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .sidebar-bottom { padding:16px 18px; margin-top:auto; }

    /* Main */
    .main-content { flex:1; padding:28px; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:28px; padding:20px 24px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:24px; box-shadow:0 16px 40px rgba(62,80,119,0.3); }
    .topbar h1 { font-size:1.8rem; font-weight:900; margin:0; color:white; }
    .topbar p { margin:4px 0 0; color:rgba(255,255,255,0.8); }
    .admin-badge { background:rgba(255,255,255,0.2); color:white; border-radius:12px; border:1px solid rgba(255,255,255,0.25); padding:10px 18px; font-weight:800; white-space:nowrap; }

    .back-btn { display:inline-flex; align-items:center; gap:8px; background:white; border:1px solid #e2e8f0; border-radius:12px; padding:9px 16px; font-weight:800; font-size:0.88rem; color:#334155; text-decoration:none; margin-bottom:22px; box-shadow:0 2px 8px rgba(0,0,0,0.05); transition:background .2s; }
    .back-btn:hover { background:#f1f5f9; color:#0f172a; text-decoration:none; }

    /* Cards */
    .card-box { background:white; border:1.5px solid #dbeafe; border-radius:20px; padding:24px; margin-bottom:22px; box-shadow:0 4px 18px rgba(62,80,119,0.07); }
    .card-box-title { font-size:1rem; font-weight:900; color:#1e3a5f; margin-bottom:18px; display:flex; align-items:center; gap:10px; }
    .card-box-title .title-icon { width:34px; height:34px; border-radius:9px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; }

    /* Form elements */
    .input-row { display:grid; grid-template-columns:1fr 1fr auto; gap:12px; align-items:end; }
    .field-label { font-size:0.82rem; font-weight:800; color:#64748b; margin-bottom:6px; display:block; }
    .form-control, .form-select { border-radius:12px; padding:12px 14px; border:1.5px solid #e2e8f0; font-size:0.92rem; width:100%; transition:border .2s; }
    .form-control:focus, .form-select:focus { border-color:var(--primary); outline:none; box-shadow:0 0 0 3px rgba(62,80,119,0.12); }
    .btn-primary-custom { background:linear-gradient(135deg,var(--primary),var(--secondary)); border:none; color:white; font-weight:900; border-radius:12px; padding:12px 24px; cursor:pointer; font-size:0.9rem; white-space:nowrap; box-shadow:0 6px 18px rgba(62,80,119,0.3); transition:opacity .2s,transform .15s; }
    .btn-primary-custom:hover { opacity:.92; transform:translateY(-1px); }

    /* Course selector */
    .selector-wrap { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .course-select { flex:1; min-width:220px; font-size:0.95rem; font-weight:700; padding:13px 16px; border-radius:14px; border:2px solid #dbeafe; cursor:pointer; }
    .course-select:focus { border-color:var(--primary); }
    .btn-select { background:linear-gradient(135deg,var(--primary),var(--secondary)); border:none; color:white; font-weight:900; border-radius:14px; padding:13px 24px; cursor:pointer; font-size:0.9rem; white-space:nowrap; }

    /* Course list (compact) */
    .course-list { display:flex; flex-direction:column; gap:8px; }
    .course-item { display:flex; align-items:center; gap:14px; background:#f8fbff; border:1.5px solid #dbeafe; border-radius:14px; padding:13px 16px; }
    .course-num { width:32px; height:32px; border-radius:9px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:0.82rem; flex-shrink:0; }
    .course-name-text { flex:1; font-weight:800; font-size:0.92rem; color:#0f172a; }
    .chip { font-size:0.72rem; font-weight:700; padding:2px 9px; border-radius:999px; background:#eff6ff; color:#1e3a5f; }
    .chip-green { background:#dcfce7; color:#166534; }
    .chip-red   { background:#fee2e2; color:#991b1b; }
    .btn-icon { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:9px; border:none; cursor:pointer; font-size:0.82rem; transition:background .15s; flex-shrink:0; }
    .btn-edit { background:#dbeafe; color:#1d4ed8; }
    .btn-edit:hover { background:#bfdbfe; }
    .btn-del  { background:#fee2e2; color:#dc2626; }
    .btn-del:hover  { background:#fecaca; }
    .cat-heading { font-size:0.78rem; font-weight:900; text-transform:uppercase; letter-spacing:1px; color:#3e5077; padding:8px 2px 4px; }

    /* Projects panel */
    .proj-panel { background:white; border:2px solid #dbeafe; border-radius:20px; padding:24px; box-shadow:0 8px 24px rgba(62,80,119,0.09); }
    .proj-panel-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
    .proj-panel-title { font-size:1.1rem; font-weight:900; color:#0f172a; }
    .proj-panel-sub { font-size:0.82rem; color:var(--muted); font-weight:600; margin-top:2px; }

    .proj-item { display:flex; align-items:center; gap:12px; background:#f8fbff; border:1px solid #dbeafe; border-radius:12px; padding:12px 14px; margin-bottom:8px; }
    .proj-thumb { width:42px; height:42px; border-radius:10px; object-fit:cover; flex-shrink:0; border:1px solid #e5edf9; }
    .proj-thumb-fallback { width:42px; height:42px; border-radius:10px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
    .proj-title-text { flex:1; font-weight:800; font-size:0.88rem; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .proj-links { display:flex; gap:6px; flex-shrink:0; flex-wrap:wrap; }
    .proj-link-btn { font-size:0.75rem; font-weight:800; padding:5px 12px; border-radius:8px; text-decoration:none; white-space:nowrap; display:inline-flex; align-items:center; gap:5px; }
    .proj-link-btn:hover { filter:brightness(1.08); }
    .proj-link-view { background:#dbeafe; color:#1d4ed8; }
    .proj-link-pdf  { background:#ffedd5; color:#f97316; }
    .proj-link-disabled { background:#f1f5f9; color:#94a3b8; cursor:default; }
    .proj-edit-btn { background:#dbeafe; color:#1d4ed8; border:none; border-radius:8px; width:30px; height:30px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.8rem; }
    .proj-edit-btn:hover { background:#bfdbfe; }
    .proj-del-btn { background:#fee2e2; color:#dc2626; border:none; border-radius:8px; width:30px; height:30px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.8rem; }
    .proj-del-btn:hover { background:#fecaca; }

    .empty-proj { text-align:center; padding:32px 16px; background:#f8fbff; border:1.5px dashed #dbeafe; border-radius:14px; color:var(--muted); }
    .empty-proj i { font-size:2rem; color:#dbeafe; display:block; margin-bottom:10px; }

    .add-proj-form { border-top:1.5px solid #f1f5f9; margin-top:20px; padding-top:20px; }
    .add-proj-title { font-size:0.92rem; font-weight:900; color:#0f172a; margin-bottom:14px; }
    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

    .empty-state { text-align:center; padding:40px 24px; background:white; border:1.5px dashed #dbeafe; border-radius:20px; color:var(--muted); }
    .empty-state i { font-size:2.2rem; color:#dbeafe; display:block; margin-bottom:12px; }

    @media (max-width:800px) { .input-row,.form-grid-2 { grid-template-columns:1fr; } .app-shell { flex-direction:column; } .sidebar { width:100%; height:auto; position:relative; } }
  </style>
</head>
<body>
<div class="app-shell">

  <!-- Sidebar -->
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
        <a href="admin_enrollments.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span></a>
        <a href="manage_classes.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
        <a href="teacher_earnings.php"       class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
        <a href="available_slots.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
        <a href="courses_home.php"           class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
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

  <!-- Main -->
  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
    </div>

    <div class="topbar">
      <div>
        <h1><i class="fas fa-code me-2"></i>Junior Courses</h1>
        <p>Add courses and manage their projects.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <?php if (isset($_GET["success"])): ?>
      <div class="alert alert-success mb-3"><i class="fas fa-circle-check me-2"></i>Done successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET["proj_added"])): ?>
      <div class="alert alert-success mb-3"><i class="fas fa-circle-check me-2"></i>Project added successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET["proj_edited"])): ?>
      <div class="alert alert-success mb-3"><i class="fas fa-circle-check me-2"></i>Project updated successfully.</div>
    <?php endif; ?>

    <a href="courses_home.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Courses</a>

    <!-- ── Add Course ── -->
    <div class="card-box">
      <div class="card-box-title">
        <span class="title-icon"><i class="fas fa-plus"></i></span> Add New Junior Course
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_course">
        <div class="input-row">
          <div>
            <label class="field-label">Course Name <span style="color:#dc2626;">*</span></label>
            <input type="text" name="course_name" class="form-control" placeholder="e.g. Python Fundamentals" required>
          </div>
          <div>
            <label class="field-label">Module / Category</label>
            <input type="text" name="category" class="form-control" placeholder="e.g. Python Introduction" list="cat-list">
            <datalist id="cat-list">
              <?php foreach ($existCats as $ec): ?>
                <option value="<?= htmlspecialchars($ec) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div style="display:flex;align-items:flex-end;">
            <button type="submit" class="btn-primary-custom" style="width:100%;"><i class="fas fa-plus me-1"></i> Add Course</button>
          </div>
        </div>
      </form>
    </div>

    <!-- ── Select Course → Projects ── -->
    <div class="card-box">
      <div class="card-box-title">
        <span class="title-icon"><i class="fas fa-folder-open"></i></span> Select a Course to Manage Projects
      </div>

      <?php if (empty($allCourses)): ?>
        <div class="empty-state">
          <i class="fas fa-code"></i>
          <div style="font-weight:800;font-size:1rem;color:#334155;margin-bottom:6px;">No courses yet</div>
          <div style="font-size:0.85rem;">Add a course above first.</div>
        </div>
      <?php else: ?>
        <form method="GET" class="selector-wrap" style="margin-bottom:24px;">
          <select name="selected" class="form-select course-select">
            <option value="">— Select a Junior Course —</option>
            <?php
              $grouped = [];
              foreach ($allCourses as $c) {
                  $key = $c["category"] ?: "No Module";
                  $grouped[$key][] = $c;
              }
              foreach ($grouped as $grpLabel => $grpItems):
            ?>
              <optgroup label="<?= htmlspecialchars($grpLabel) ?>">
                <?php foreach ($grpItems as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= $selectedId === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['course_name']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-select"><i class="fas fa-arrow-right me-1"></i> Open</button>
        </form>

        <!-- ── Projects Panel ── -->
        <?php if ($selectedCourse): ?>
        <div class="proj-panel">
          <div class="proj-panel-header">
            <div>
              <div class="proj-panel-title"><i class="fas fa-folder-open me-2" style="color:var(--primary);"></i><?= htmlspecialchars($selectedCourse['course_name']) ?></div>
              <div class="proj-panel-sub"><?= count($selectedProjects) ?> project<?= count($selectedProjects) !== 1 ? 's' : '' ?> added</div>
            </div>
            <div style="display:flex;gap:8px;">
              <a href="edit_course.php?id=<?= $selectedCourse['id'] ?>" class="btn-icon btn-edit" title="Edit Course" style="width:auto;padding:0 14px;font-size:0.82rem;font-weight:800;gap:6px;"><i class="fas fa-pen"></i> Edit Course</a>
              <a href="admin_enrollments.php?course_id=<?= $selectedCourse['id'] ?>" class="btn-icon" title="Enroll Students" style="width:auto;padding:0 14px;font-size:0.82rem;font-weight:800;gap:6px;background:#dcfce7;color:#166534;border-radius:9px;text-decoration:none;display:inline-flex;align-items:center;"><i class="fas fa-user-plus me-1"></i> Enroll Students</a>
              <button type="button" class="btn-icon btn-del" style="width:auto;padding:0 14px;font-size:0.82rem;font-weight:800;gap:6px;" title="Delete Course"
                onclick="confirmDelete(<?= $selectedCourse['id'] ?>, <?= htmlspecialchars(json_encode($selectedCourse['course_name'])) ?>)">
                <i class="fas fa-trash"></i> Delete Course
              </button>
            </div>
          </div>

          <!-- Existing projects -->
          <?php if (empty($selectedProjects)): ?>
            <div class="empty-proj">
              <i class="fas fa-code"></i>
              <div style="font-weight:700;font-size:0.9rem;">No projects yet for this course.</div>
            </div>
          <?php else: ?>
            <?php foreach ($selectedProjects as $p):
              $pdfHref = !empty($p['pdf_url']) ? (strpos($p['pdf_url'], 'http') === 0 ? $p['pdf_url'] : 'uploads/pdfs/' . $p['pdf_url']) : '';
            ?>
            <div class="proj-item">
              <?php if (!empty($p['image'])): ?>
                <img src="<?= htmlspecialchars($p['image']) ?>" class="proj-thumb" alt="">
              <?php else: ?>
                <div class="proj-thumb-fallback"><i class="fas fa-code"></i></div>
              <?php endif; ?>
              <div class="proj-title-text"><?= htmlspecialchars($p['title']) ?></div>
              <div class="proj-links">
                <?php if (!empty($p['url'])): ?>
                  <a href="<?= htmlspecialchars($p['url']) ?>" target="_blank" class="proj-link-btn proj-link-view"><i class="fas fa-arrow-up-right-from-square"></i> Project</a>
                <?php else: ?>
                  <span class="proj-link-btn proj-link-disabled"><i class="fas fa-arrow-up-right-from-square"></i> Project</span>
                <?php endif; ?>
                <?php if ($pdfHref): ?>
                  <a href="<?= htmlspecialchars($pdfHref) ?>" target="_blank" class="proj-link-btn proj-link-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                <?php else: ?>
                  <span class="proj-link-btn proj-link-disabled"><i class="fas fa-file-pdf"></i> PDF</span>
                <?php endif; ?>
              </div>
              <button type="button" class="proj-edit-btn" title="Edit project"
                onclick="openEditProject(<?= $p['id'] ?>, <?= $selectedId ?>, <?= htmlspecialchars(json_encode($p['title'])) ?>, <?= htmlspecialchars(json_encode($p['url'] ?? '')) ?>, <?= htmlspecialchars(json_encode($p['image'] ?? '')) ?>, <?= htmlspecialchars(json_encode($p['pdf_url'] ?? '')) ?>)">
                <i class="fas fa-pen"></i>
              </button>
              <button type="button" class="proj-del-btn" title="Delete project"
                onclick="confirmDeleteProject(<?= $p['id'] ?>, <?= $selectedId ?>, <?= htmlspecialchars(json_encode($p['title'])) ?>)">
                <i class="fas fa-trash"></i>
              </button>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <!-- Add project form -->
          <div class="add-proj-form">
            <div class="add-proj-title"><i class="fas fa-plus-circle me-1" style="color:var(--primary);"></i> Add Project to this Course</div>
            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="action"    value="add_project">
              <input type="hidden" name="course_id" value="<?= $selectedId ?>">
              <div class="form-grid-2" style="margin-bottom:12px;">
                <div>
                  <label class="field-label">Project Title <span style="color:#dc2626;">*</span></label>
                  <input type="text" name="title" class="form-control" placeholder="e.g. Python Calculator" required>
                </div>
                <div>
                  <label class="field-label">Image URL <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                  <input type="text" name="image" class="form-control" placeholder="https://... or images/photo.png">
                </div>
              </div>
              <div class="form-grid-2" style="margin-bottom:12px;">
                <div>
                  <label class="field-label">Project Link <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                  <input type="text" name="url" class="form-control" placeholder="https://replit.com/...">
                </div>
                <div>
                  <label class="field-label">PDF Link <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                  <input type="text" name="pdf_link" class="form-control" placeholder="https://drive.google.com/...">
                </div>
              </div>
              <div style="margin-bottom:16px;">
                <label class="field-label">Or Upload PDF File</label>
                <input type="file" name="pdf_file" accept=".pdf" class="form-control">
              </div>
              <button type="submit" class="btn-primary-custom"><i class="fas fa-plus me-1"></i> Add Project</button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- All Courses List (compact) -->
        <div style="margin-top:28px;">
          <div style="font-size:0.82rem;font-weight:900;text-transform:uppercase;letter-spacing:1px;color:#3e5077;margin-bottom:12px;">All Junior Courses (<?= count($allCourses) ?>)</div>
          <div class="course-list">
            <?php $n=1; foreach ($grouped as $grpLabel => $grpItems): ?>
              <div class="cat-heading"><i class="fas fa-folder me-1"></i><?= htmlspecialchars($grpLabel) ?></div>
              <?php foreach ($grpItems as $c): ?>
              <div class="course-item">
                <div class="course-num"><?= $n++ ?></div>
                <div class="course-name-text"><?= htmlspecialchars($c['course_name']) ?></div>
                <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;flex-wrap:wrap;">
                  <span class="chip chip-<?= $c['status'] === 'active' ? 'green' : 'red' ?>"><?= ucfirst($c['status']) ?></span>
                  <a href="courses_junior.php?selected=<?= $c['id'] ?>" class="btn-icon btn-edit" style="width:auto;padding:0 12px;font-size:0.78rem;font-weight:800;gap:5px;text-decoration:none;"><i class="fas fa-folder-open"></i> Projects</a>
                  <a href="edit_course.php?id=<?= $c['id'] ?>" class="btn-icon btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                  <button type="button" class="btn-icon btn-del" onclick="confirmDelete(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['course_name'])) ?>)"><i class="fas fa-trash"></i></button>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Edit Project Modal -->
<div id="edit-proj-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:22px;padding:32px;max-width:520px;width:94%;box-shadow:0 24px 60px rgba(0,0,0,0.18);">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;">
      <div style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;"><i class="fas fa-pen"></i></div>
      <div style="font-size:1.05rem;font-weight:900;color:#0f172a;">Edit Project</div>
    </div>
    <form method="POST" enctype="multipart/form-data" id="edit-proj-form">
      <input type="hidden" name="action"       value="edit_project">
      <input type="hidden" name="project_id"   id="edit-proj-id">
      <input type="hidden" name="course_id"    id="edit-proj-course-id">
      <input type="hidden" name="existing_pdf" id="edit-existing-pdf">
      <div class="form-grid-2" style="margin-bottom:12px;">
        <div>
          <label class="field-label">Project Title <span style="color:#dc2626;">*</span></label>
          <input type="text" name="title" id="edit-proj-title" class="form-control" required>
        </div>
        <div>
          <label class="field-label">Image URL <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
          <input type="text" name="image" id="edit-proj-image" class="form-control" placeholder="https://...">
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:12px;">
        <div>
          <label class="field-label">Project Link <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
          <input type="text" name="url" id="edit-proj-url" class="form-control" placeholder="https://replit.com/...">
        </div>
        <div>
          <label class="field-label">PDF Link <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
          <input type="text" name="pdf_link" id="edit-proj-pdflink" class="form-control" placeholder="https://drive.google.com/...">
        </div>
      </div>
      <div style="margin-bottom:16px;">
        <label class="field-label">Or Upload New PDF File</label>
        <input type="file" name="pdf_file" accept=".pdf" class="form-control">
        <div id="edit-proj-current-pdf" style="font-size:0.78rem;color:#64748b;margin-top:6px;"></div>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-primary-custom"><i class="fas fa-floppy-disk me-1"></i> Save Changes</button>
        <button type="button" onclick="document.getElementById('edit-proj-modal').style.display='none'" style="background:#64748b;color:white;border:none;border-radius:12px;padding:12px 24px;font-weight:800;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Project Modal -->
<div id="del-proj-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:22px;padding:36px;max-width:400px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,0.18);text-align:center;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
      <i class="fas fa-trash" style="font-size:1.5rem;color:#dc2626;"></i>
    </div>
    <div style="font-size:1.1rem;font-weight:900;margin-bottom:8px;">Delete Project?</div>
    <p id="del-proj-msg" style="color:#64748b;font-size:0.92rem;margin-bottom:24px;"></p>
    <form method="POST" id="del-proj-form">
      <input type="hidden" name="action"     value="delete_project">
      <input type="hidden" name="project_id" id="del-proj-id">
      <input type="hidden" name="course_id"  id="del-proj-course-id">
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="submit" style="background:linear-gradient(135deg,#dc2626,#b91c1c);border:none;color:white;font-weight:800;border-radius:12px;padding:12px 28px;cursor:pointer;"><i class="fas fa-trash me-1"></i> Yes, Delete</button>
        <button type="button" onclick="document.getElementById('del-proj-modal').style.display='none'" style="background:#64748b;color:white;border:none;border-radius:12px;padding:12px 24px;font-weight:800;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="del-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:22px;padding:36px;max-width:400px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,0.18);text-align:center;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
      <i class="fas fa-trash" style="font-size:1.5rem;color:#dc2626;"></i>
    </div>
    <div style="font-size:1.1rem;font-weight:900;margin-bottom:8px;">Delete Course?</div>
    <p id="del-msg" style="color:#64748b;font-size:0.92rem;margin-bottom:24px;"></p>
    <form method="POST">
      <input type="hidden" name="action"    value="delete_course">
      <input type="hidden" name="course_id" id="del-id">
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="submit" style="background:linear-gradient(135deg,#dc2626,#b91c1c);border:none;color:white;font-weight:800;border-radius:12px;padding:12px 28px;cursor:pointer;"><i class="fas fa-trash me-1"></i> Yes, Delete</button>
        <button type="button" onclick="document.getElementById('del-modal').style.display='none'" style="background:#64748b;color:white;border:none;border-radius:12px;padding:12px 24px;font-weight:800;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditProject(pid, cid, title, url, image, pdfUrl) {
  document.getElementById('edit-proj-id').value        = pid;
  document.getElementById('edit-proj-course-id').value = cid;
  document.getElementById('edit-proj-title').value     = title;
  document.getElementById('edit-proj-url').value       = url   || '';
  document.getElementById('edit-proj-image').value     = image || '';
  document.getElementById('edit-existing-pdf').value   = pdfUrl || '';
  const pdfLinkField = document.getElementById('edit-proj-pdflink');
  pdfLinkField.value = (pdfUrl && pdfUrl.startsWith('http')) ? pdfUrl : '';
  const info = document.getElementById('edit-proj-current-pdf');
  if (pdfUrl) {
    const href = pdfUrl.startsWith('http') ? pdfUrl : 'uploads/pdfs/' + pdfUrl;
    info.innerHTML = 'Current PDF: <a href="' + href + '" target="_blank" style="color:#2563eb;">' + pdfUrl + '</a>';
  } else {
    info.textContent = 'No PDF currently set.';
  }
  document.getElementById('edit-proj-modal').style.display = 'flex';
}
document.getElementById('edit-proj-modal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});

function confirmDeleteProject(pid, cid, name) {
  document.getElementById('del-proj-id').value = pid;
  document.getElementById('del-proj-course-id').value = cid;
  document.getElementById('del-proj-msg').textContent = 'Are you sure you want to delete "' + name + '"? This cannot be undone.';
  document.getElementById('del-proj-modal').style.display = 'flex';
}
document.getElementById('del-proj-modal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});

function confirmDelete(id, name) {
  document.getElementById('del-id').value = id;
  document.getElementById('del-msg').textContent = 'Are you sure you want to delete "' + name + '"? This cannot be undone.';
  document.getElementById('del-modal').style.display = 'flex';
}
document.getElementById('del-modal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
<script src="logout-modal.js"></script>
</body>
</html>
