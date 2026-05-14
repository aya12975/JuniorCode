<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";

/* ── Ensure table + columns ── */
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

/* Add missing columns to old tables */
$checks = [
    "course_id" => "INT DEFAULT NULL",
    "image"     => "TEXT NOT NULL DEFAULT ''",
    "pdf_url"   => "TEXT NOT NULL DEFAULT ''",
];
foreach ($checks as $col => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM course_projects LIKE '$col'");
    if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE course_projects ADD COLUMN $col $def");
}

if (!is_dir("uploads/pdfs")) mkdir("uploads/pdfs", 0755, true);

/* ── Load selected course ── */
$courseId = (int)($_GET["course_id"] ?? 0);
$selectedCourse = null;
if ($courseId > 0) {
    $cs = $conn->prepare("SELECT id, course_name, section, category FROM courses WHERE id = ?");
    if ($cs) { $cs->bind_param("i", $courseId); $cs->execute(); $selectedCourse = $cs->get_result()->fetch_assoc(); $cs->close(); }
}

/* ── All courses for selector ── */
$allCourses = [];
$acRes = $conn->query("SELECT id, course_name, section, category FROM courses WHERE section != 'demo' ORDER BY section, category, id ASC");
if ($acRes) { while ($r = $acRes->fetch_assoc()) $allCourses[] = $r; }

$error   = "";
$success = "";
if (($_GET["msg"] ?? "") === "deleted") $success = "Project deleted.";

/* ── Handle POST ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action   = $_POST["action"]    ?? "";
    $postCid  = (int)($_POST["course_id"] ?? 0);

    if ($action === "add" && $postCid > 0) {
        /* Resolve section+category from the course */
        $courseRow = null;
        $cr = $conn->prepare("SELECT section, category FROM courses WHERE id = ?");
        if ($cr) { $cr->bind_param("i", $postCid); $cr->execute(); $courseRow = $cr->get_result()->fetch_assoc(); $cr->close(); }

        $title    = trim($_POST["title"]    ?? "");
        $url      = trim($_POST["url"]      ?? "");
        $image    = trim($_POST["image"]    ?? "");
        $pdf_url  = "";
        $pdf_link = trim($_POST["pdf_link"] ?? "");
        $sec      = $courseRow["section"]  ?? "";
        $cat      = $courseRow["category"] ?? "";

        if (!empty($_FILES["pdf_file"]["name"])) {
            $ext = strtolower(pathinfo($_FILES["pdf_file"]["name"], PATHINFO_EXTENSION));
            if ($ext !== "pdf") { $error = "Only PDF files are allowed."; }
            else {
                $fname = uniqid("proj_", true) . ".pdf";
                if (!move_uploaded_file($_FILES["pdf_file"]["tmp_name"], "uploads/pdfs/" . $fname)) $error = "Failed to save PDF file.";
                else $pdf_url = $fname;
            }
        } elseif ($pdf_link !== "") {
            $pdf_url = $pdf_link;
        }

        if ($error === "" && $title !== "") {
            $stmt = $conn->prepare("INSERT INTO course_projects (course_id, section, category, title, url, image, pdf_url) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("issssss", $postCid, $sec, $cat, $title, $url, $image, $pdf_url);
            $stmt->execute() ? $success = "Project added." : $error = "Failed to add.";
            $courseId = $postCid;
        } elseif ($error === "") {
            $error = "Title is required.";
        }
    }

    if ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);
        $retCid = (int)($_POST["course_id"] ?? 0);
        if ($id > 0) {
            $pdfStmt = $conn->prepare("SELECT pdf_url FROM course_projects WHERE id = ?");
            $pdfStmt->bind_param("i", $id); $pdfStmt->execute();
            $pdfRow  = $pdfStmt->get_result()->fetch_assoc();
            $stmt    = $conn->prepare("DELETE FROM course_projects WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                if (!empty($pdfRow["pdf_url"]) && strpos($pdfRow["pdf_url"], 'http') !== 0) @unlink("uploads/pdfs/" . $pdfRow["pdf_url"]);
                header("Location: manage_projects.php?course_id=" . $retCid . "&msg=deleted");
                exit();
            } else { $error = "Failed to delete."; }
        }
    }

    if ($action === "edit") {
        $id       = (int)($_POST["id"]  ?? 0);
        $title    = trim($_POST["title"]       ?? "");
        $url      = trim($_POST["url"]         ?? "");
        $image    = trim($_POST["image"]       ?? "");
        $pdf_url  = trim($_POST["existing_pdf"] ?? "");
        $pdf_link = trim($_POST["pdf_link"]    ?? "");
        $retCid   = (int)($_POST["course_id"]  ?? 0);

        if (!empty($_FILES["pdf_file"]["name"])) {
            $ext = strtolower(pathinfo($_FILES["pdf_file"]["name"], PATHINFO_EXTENSION));
            if ($ext !== "pdf") { $error = "Only PDF files are allowed."; }
            else {
                $fname = uniqid("proj_", true) . ".pdf";
                if (!move_uploaded_file($_FILES["pdf_file"]["tmp_name"], "uploads/pdfs/" . $fname)) { $error = "Failed to save PDF file."; }
                else { if ($pdf_url !== "" && strpos($pdf_url, 'http') !== 0) @unlink("uploads/pdfs/" . $pdf_url); $pdf_url = $fname; }
            }
        } elseif ($pdf_link !== "") {
            if ($pdf_url !== "" && strpos($pdf_url, 'http') !== 0) @unlink("uploads/pdfs/" . $pdf_url);
            $pdf_url = $pdf_link;
        }

        if ($error === "" && $id > 0 && $title !== "") {
            $stmt = $conn->prepare("UPDATE course_projects SET title=?, url=?, image=?, pdf_url=? WHERE id=?");
            $stmt->bind_param("ssssi", $title, $url, $image, $pdf_url, $id);
            $stmt->execute() ? $success = "Project updated." : $error = "Failed to update.";
            $courseId = $retCid;
        }
    }
}

/* ── Fetch projects for selected course ── */
$projects = [];
if ($courseId > 0) {
    $stmt = $conn->prepare("SELECT * FROM course_projects WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->bind_param("i", $courseId); $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    /* Refresh selectedCourse after POSTs */
    if ($selectedCourse === null) {
        $cs = $conn->prepare("SELECT id, course_name, section, category FROM courses WHERE id = ?");
        if ($cs) { $cs->bind_param("i", $courseId); $cs->execute(); $selectedCourse = $cs->get_result()->fetch_assoc(); $cs->close(); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Projects | JuniorCode Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --shadow:0 18px 45px rgba(37,99,235,0.08); }
* { box-sizing:border-box; }
body { margin:0; font-family:Arial,Helvetica,sans-serif; color:var(--dark);
  background: radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),
              radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),
              linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); }
.app-shell { min-height:100vh; display:flex; }
.sidebar { width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:#fff; padding:0;
  position:sticky; top:0; height:100vh; transition:width 0.3s,padding 0.3s,min-width 0.3s; overflow:hidden; display:flex; flex-direction:column; }
body.sidebar-collapsed .sidebar { width:0; padding:0; min-width:0; }
.sidebar-bottom { padding:16px 18px; border-top:1px solid rgba(255,255,255,0.1); }
.sidebar-top-area { padding:0 18px 18px; flex:1; overflow-y:auto; }
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
.topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:24px; padding:18px 20px;
  background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; box-shadow:var(--shadow); }
.topbar h1 { font-size:1.6rem; font-weight:900; margin:0; color:#fff; }
.topbar p  { margin:4px 0 0; color:rgba(255,255,255,0.8); }
.admin-badge { background:rgba(255,255,255,0.15); color:#fff; border-radius:12px; border:1px solid rgba(255,255,255,0.2); padding:10px 18px; font-weight:800; white-space:nowrap; }
.panel-card { background:#fff; border:1px solid #edf4ff; border-radius:22px; padding:24px; box-shadow:var(--shadow); margin-bottom:22px; }
.panel-title { font-size:1.05rem; font-weight:900; margin-bottom:18px; color:var(--dark); }
.form-control,.form-select { border-radius:12px; padding:11px 14px; border:1px solid #dbe4f0; font-size:0.95rem; }
.form-control:focus,.form-select:focus { border-color:var(--primary); box-shadow:0 0 0 0.2rem rgba(62,80,119,0.13); }
.btn-main { background:linear-gradient(135deg,var(--primary),var(--secondary)); border:none; color:#fff; font-weight:800; border-radius:12px; padding:10px 18px; cursor:pointer; text-decoration:none; display:inline-block; }
.btn-main:hover { color:#fff; opacity:0.92; }
.btn-back { background:#64748b; color:#fff; border:none; border-radius:12px; padding:10px 18px; font-weight:800; text-decoration:none; display:inline-block; cursor:pointer; }
.btn-back:hover { color:#fff; background:#475569; }
.btn-danger-soft { background:#fee2e2; color:#991b1b; border:none; border-radius:10px; padding:7px 14px; font-weight:700; cursor:pointer; font-size:0.85rem; }
.btn-danger-soft:hover { background:#fecaca; }
.btn-edit-soft { background:#dbeafe; color:#1d4ed8; border:none; border-radius:10px; padding:7px 14px; font-weight:700; cursor:pointer; font-size:0.85rem; }
.btn-edit-soft:hover { background:#bfdbfe; }

/* Course selector */
.course-selector { display:flex; flex-direction:column; gap:6px; margin-bottom:22px; }
.course-sel-group-label { font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:var(--muted); font-weight:700; padding:6px 4px 2px; }
.sel-btn { padding:10px 16px; border-radius:12px; border:2px solid #e2e8f0; font-weight:700; font-size:0.88rem; cursor:pointer; background:#fff; color:var(--dark); text-decoration:none; transition:all 0.18s; display:flex; align-items:center; gap:10px; }
.sel-btn:hover { border-color:var(--primary); color:var(--primary); }
.sel-btn.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; border-color:transparent; box-shadow:0 6px 18px rgba(62,80,119,0.22); }
.sel-icon { width:28px; height:28px; border-radius:8px; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.8rem; }
.sel-btn:not(.active) .sel-icon { background:#f1f5f9; color:var(--primary); }

.project-row { display:flex; align-items:center; gap:14px; padding:14px 16px; border-radius:14px; background:#f8fbff; border:1px solid #dbeafe; margin-bottom:10px; }
.project-row-info { flex:1; min-width:0; }
.project-title { font-weight:800; color:var(--dark); margin-bottom:2px; }
.proj-thumb { width:110px; height:80px; border-radius:10px; object-fit:contain; border:1px solid #dbeafe; flex-shrink:0; }
.proj-thumb-placeholder { width:110px; height:80px; border-radius:10px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.action-boxes { display:flex; flex-direction:column; gap:8px; flex-shrink:0; background:#f8fbff; border:1px solid #dbeafe; border-radius:14px; padding:10px 12px; min-width:140px; }
.action-box { display:flex; align-items:center; justify-content:center; gap:7px; border-radius:10px; padding:8px 12px; font-size:0.82rem; font-weight:800; text-decoration:none; white-space:nowrap; color:#fff; transition:filter 0.2s; }
.action-box:hover { filter:brightness(1.1); color:#fff; }
.action-box-pdf  { background:linear-gradient(135deg,#f97316,#ea580c); }
.action-box-link { background:linear-gradient(135deg,#3b82f6,#1d4ed8); }
.action-box-empty { display:flex; align-items:center; justify-content:center; gap:7px; border-radius:10px; padding:8px 12px; font-size:0.8rem; font-weight:600; color:#94a3b8; border:1.5px dashed #e2e8f0; white-space:nowrap; }
.empty-box { text-align:center; padding:26px; border-radius:16px; background:#f8fbff; color:var(--muted); border:1px dashed #d9e9ff; font-weight:700; }

.layout-grid { display:grid; grid-template-columns:260px 1fr; gap:22px; align-items:start; }
.course-list-panel { background:#fff; border:1px solid #edf4ff; border-radius:22px; padding:20px; box-shadow:var(--shadow); position:sticky; top:26px; }

/* Modals */
.modal-backdrop-custom { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:999; align-items:center; justify-content:center; }
.modal-backdrop-custom.show { display:flex; }
.modal-box { background:#fff; border-radius:22px; padding:28px; width:100%; max-width:480px; box-shadow:0 24px 60px rgba(15,23,42,0.2); }
.modal-title { font-size:1.05rem; font-weight:900; margin-bottom:18px; }

@media (max-width:991px) { .app-shell{flex-direction:column;} .sidebar{width:100%;height:auto;position:relative;} .layout-grid{grid-template-columns:1fr;} .course-list-panel{position:static;} }
</style>
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
        <a href="admin_enrollments.php"      class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span></a>
        <a href="manage_classes.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
        <a href="teacher_earnings.php"       class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
        <a href="available_slots.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
        <a href="courses.php"                class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
        <a href="reports.php"                class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
        <a href="admin_certificates.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
<a href="admin_email_notifications.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-envelope"></i></span><span>Email Notifications</span></a>
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
        <h1>Course Projects</h1>
        <p>Select a course and manage its projects.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><i class="fas fa-circle-check me-2"></i><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger  alert-dismissible fade show"><i class="fas fa-circle-xmark me-2"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <?php if (empty($allCourses)): ?>
      <div class="panel-card" style="text-align:center;padding:50px;">
        <i class="fas fa-book" style="font-size:2.5rem;color:#cbd5e1;display:block;margin-bottom:16px;"></i>
        <h5 style="font-weight:800;">No courses yet</h5>
        <p style="color:var(--muted);">Add courses first, then you can manage their projects.</p>
        <a href="courses.php" class="btn-main">Go to Courses</a>
      </div>
    <?php else: ?>

    <div class="layout-grid">

      <!-- Course list -->
      <div class="course-list-panel">
        <div style="font-weight:900;font-size:1rem;margin-bottom:14px;color:var(--dark);">
          <i class="fas fa-book-open me-2" style="color:var(--primary)"></i>Select Course
        </div>
        <?php
        $currentSection = "";
        foreach ($allCourses as $c):
          if ($c["section"] !== $currentSection) {
            if ($currentSection !== "") echo '<div style="height:8px;"></div>';
            $currentSection = $c["section"];
            $sLabel = $c["section"] === "kids" ? "Kids" : "Junior";
            $sIcon  = $c["section"] === "kids" ? "fa-star" : "fa-rocket";
            echo '<div class="course-sel-group-label"><i class="fas '.$sIcon.' me-1"></i>'.$sLabel.'</div>';
          }
          $isActive = ($c["id"] == $courseId);
          $cnt = 0;
          $cc = $conn->prepare("SELECT COUNT(*) c FROM course_projects WHERE course_id=?");
          if ($cc) { $cc->bind_param("i", $c["id"]); $cc->execute(); $cnt = (int)$cc->get_result()->fetch_assoc()["c"]; $cc->close(); }
        ?>
          <a href="?course_id=<?= $c["id"] ?>" class="sel-btn <?= $isActive ? 'active' : '' ?>">
            <div class="sel-icon"><i class="fas <?= $c["section"]==="kids"?"fa-star":"fa-rocket" ?>"></i></div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:0.85rem;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($c["course_name"]) ?></div>
              <div style="font-size:0.72rem;opacity:0.7;margin-top:1px;"><?= $cnt ?> project<?= $cnt!==1?'s':'' ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Right panel -->
      <div>
        <?php if ($selectedCourse === null): ?>
          <div class="panel-card" style="text-align:center;padding:50px;">
            <i class="fas fa-arrow-left" style="font-size:2rem;color:#cbd5e1;display:block;margin-bottom:14px;"></i>
            <h5 style="font-weight:800;color:#334155;">Select a course</h5>
            <p style="color:var(--muted);">Click a course on the left to manage its projects.</p>
          </div>
        <?php else: ?>

        <!-- Add project form -->
        <div class="panel-card">
          <div class="panel-title">
            <i class="fas fa-plus-circle me-2" style="color:var(--primary)"></i>
            Add Project — <span style="color:var(--primary)"><?= htmlspecialchars($selectedCourse["course_name"]) ?></span>
          </div>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action"    value="add">
            <input type="hidden" name="course_id" value="<?= $selectedCourse["id"] ?>">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label fw-bold">Project Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Dino Run" required>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-bold">Image URL <span style="font-weight:400;color:var(--muted);">(opt.)</span></label>
                <input type="text" name="image" id="addImageInput" class="form-control" placeholder="images/photo.png">
              </div>
              <div class="col-md-5">
                <label class="form-label fw-bold">View Project Link <span style="font-weight:400;color:var(--muted);">(opt.)</span></label>
                <input type="text" name="url" class="form-control" placeholder="https://scratch.mit.edu/...">
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-bold">Course PDF / Lesson File</label>
                <input type="file" name="pdf_file" accept=".pdf" class="form-control" style="margin-bottom:6px;">
                <div style="font-size:0.78rem;color:var(--muted);text-align:center;margin:4px 0;">— or paste a link —</div>
                <input type="text" name="pdf_link" class="form-control" placeholder="https://drive.google.com/...">
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <div id="addImagePreview" style="display:none;margin-bottom:8px;">
                  <img id="addImagePreviewImg" src="" alt="Preview" style="height:60px;border-radius:10px;border:1px solid #dbeafe;object-fit:cover;">
                </div>
              </div>
            </div>
            <button type="submit" class="btn-main"><i class="fas fa-plus me-1"></i> Add Project</button>
          </form>
        </div>

        <!-- Existing projects -->
        <div class="panel-card">
          <div class="panel-title">
            Projects in "<?= htmlspecialchars($selectedCourse["course_name"]) ?>"
            <span style="font-size:0.85rem;font-weight:600;color:var(--muted);margin-left:8px;">(<?= count($projects) ?>)</span>
          </div>

          <?php if (empty($projects)): ?>
            <div class="empty-box"><i class="fas fa-folder-open me-2"></i>No projects yet — add the first one above.</div>
          <?php else: ?>
            <?php foreach ($projects as $p): ?>
              <div class="project-row">
                <?php if (!empty($p["image"])): ?>
                  <img src="<?= htmlspecialchars($p["image"]) ?>" class="proj-thumb" alt="<?= htmlspecialchars($p["title"]) ?>">
                <?php else: ?>
                  <div class="proj-thumb-placeholder"><i class="fas fa-gamepad"></i></div>
                <?php endif; ?>
                <div class="project-row-info">
                  <div class="project-title"><?= htmlspecialchars($p["title"]) ?></div>
                </div>
                <div class="action-boxes">
                  <?php if (!empty($p["pdf_url"])): $pdfH = strpos($p["pdf_url"],'http')===0?$p["pdf_url"]:'uploads/pdfs/'.$p["pdf_url"]; ?>
                    <a href="<?= htmlspecialchars($pdfH) ?>" target="_blank" class="action-box action-box-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                  <?php else: ?>
                    <div class="action-box-empty"><i class="fas fa-file-pdf"></i> No PDF</div>
                  <?php endif; ?>
                  <?php if (!empty($p["url"])): ?>
                    <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" class="action-box action-box-link"><i class="fas fa-arrow-up-right-from-square"></i> View</a>
                  <?php else: ?>
                    <div class="action-box-empty"><i class="fas fa-link"></i> No link</div>
                  <?php endif; ?>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                  <button class="btn-edit-soft" onclick="openEdit(<?= $p['id'] ?>,<?= htmlspecialchars(json_encode($p['title'])) ?>,<?= htmlspecialchars(json_encode($p['url']??'')) ?>,<?= htmlspecialchars(json_encode($p['image']??'')) ?>,<?= htmlspecialchars(json_encode($p['pdf_url']??'')) ?>)">
                    <i class="fas fa-pen"></i> Edit
                  </button>
                  <button class="btn-danger-soft" onclick="openDeleteModal(<?= $p['id'] ?>,<?= htmlspecialchars(json_encode($p['title'])) ?>)">
                    <i class="fas fa-trash"></i> Delete
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <div style="margin-top:18px;">
            <a href="courses.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i> Back to Courses</a>
          </div>
        </div>

        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<!-- Delete modal -->
<div class="modal-backdrop-custom" id="deleteModal">
  <div class="modal-box" style="max-width:400px;text-align:center;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
      <i class="fas fa-trash" style="font-size:1.5rem;color:#dc2626;"></i>
    </div>
    <div class="modal-title" style="margin-bottom:8px;">Delete Project?</div>
    <p id="deleteModalMsg" style="color:#64748b;font-size:0.92rem;margin-bottom:24px;"></p>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="action"    value="delete">
      <input type="hidden" name="id"        id="deleteId">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="submit" style="background:linear-gradient(135deg,#dc2626,#b91c1c);border:none;color:#fff;font-weight:800;border-radius:12px;padding:12px 28px;cursor:pointer;">
          <i class="fas fa-trash me-1"></i> Yes, Delete
        </button>
        <button type="button" onclick="closeDeleteModal()" class="btn-back">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit modal -->
<div class="modal-backdrop-custom" id="editModal">
  <div class="modal-box">
    <div class="modal-title">Edit Project</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action"    value="edit">
      <input type="hidden" name="id"        id="editId">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <div class="mb-3">
        <label class="form-label fw-bold">Title</label>
        <input type="text" name="title" id="editTitle" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">Image URL <span style="font-weight:400;color:var(--muted);">(opt.)</span></label>
        <input type="text" name="image" id="editImage" class="form-control">
        <div id="editImagePreview" style="margin-top:8px;display:none;">
          <img id="editImagePreviewImg" src="" alt="Preview" style="height:60px;border-radius:10px;border:1px solid #dbeafe;object-fit:cover;">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">View Project Link <span style="font-weight:400;color:var(--muted);">(opt.)</span></label>
        <input type="text" name="url" id="editUrl" class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">Course PDF <span style="font-weight:400;color:var(--muted);">(opt.)</span></label>
        <input type="hidden" name="existing_pdf" id="editExistingPdf">
        <div id="editCurrentPdf" style="font-size:0.83rem;color:var(--muted);margin-bottom:6px;"></div>
        <input type="file" name="pdf_file" accept=".pdf" class="form-control" style="margin-bottom:6px;">
        <div style="font-size:0.78rem;color:var(--muted);text-align:center;margin:2px 0;">— or paste a link —</div>
        <input type="text" name="pdf_link" id="editPdfLink" class="form-control" placeholder="https://drive.google.com/...">
        <div style="font-size:0.78rem;color:var(--muted);margin-top:4px;">Leave blank to keep the current PDF.</div>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-main">Save Changes</button>
        <button type="button" class="btn-back" onclick="closeEdit()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openDeleteModal(id, title) {
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteModalMsg').textContent = 'Delete "' + title + '"? This cannot be undone.';
  document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('show'); }
document.getElementById('deleteModal').addEventListener('click', e => { if (e.target===document.getElementById('deleteModal')) closeDeleteModal(); });

function openEdit(id, title, url, image, pdfUrl) {
  document.getElementById('editId').value          = id;
  document.getElementById('editTitle').value       = title;
  document.getElementById('editUrl').value         = url   || '';
  document.getElementById('editImage').value       = image || '';
  document.getElementById('editExistingPdf').value = pdfUrl || '';
  document.getElementById('editPdfLink').value     = '';
  const cpd = document.getElementById('editCurrentPdf');
  if (pdfUrl) {
    const isLink = pdfUrl.startsWith('http');
    const href   = isLink ? pdfUrl : 'uploads/pdfs/' + pdfUrl;
    cpd.innerHTML = 'Current: <a href="' + href + '" target="_blank" style="color:#2563eb;">' + (isLink ? pdfUrl : pdfUrl) + '</a>';
    if (isLink) document.getElementById('editPdfLink').value = pdfUrl;
  } else { cpd.textContent = 'No PDF yet.'; }
  updatePreview('editImage', 'editImagePreview', 'editImagePreviewImg');
  document.getElementById('editModal').classList.add('show');
}
function closeEdit() { document.getElementById('editModal').classList.remove('show'); }
document.getElementById('editModal').addEventListener('click', e => { if (e.target===document.getElementById('editModal')) closeEdit(); });

function updatePreview(inp, wrap, img) {
  const v = document.getElementById(inp).value.trim();
  document.getElementById(wrap).style.display = v ? 'block' : 'none';
  if (v) document.getElementById(img).src = v;
}
document.getElementById('addImageInput').addEventListener('input', () => updatePreview('addImageInput', 'addImagePreview', 'addImagePreviewImg'));
document.getElementById('editImage').addEventListener('input', () => updatePreview('editImage', 'editImagePreview', 'editImagePreviewImg'));
</script>
<script src="logout-modal.js"></script>
</body>
</html>
