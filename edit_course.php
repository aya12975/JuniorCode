<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";
$id = (int)($_GET["id"] ?? $_POST["course_id"] ?? 0);

if ($id <= 0) {
    header("Location: courses_home.php");
    exit();
}

// ── POST: save changes ───────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "edit_course") {
    $name     = trim($_POST["course_name"] ?? "");
    $category = trim($_POST["category"]    ?? "");
    $price    = (float)($_POST["price"]    ?? 0);
    $type     = trim($_POST["course_type"] ?? "paid");
    $status   = trim($_POST["status"]      ?? "active");
    $section  = trim($_POST["section"]     ?? "kids");

    if (!in_array($section, ["kids", "junior", "demo"])) $section = "kids";
    if (!in_array($status,  ["active", "inactive"]))     $status  = "active";
    if (!in_array($type,    ["paid", "free", "demo"]))   $type    = "paid";

    if ($name !== "") {
        $upd = $conn->prepare("UPDATE courses SET course_name=?, section=?, category=?, price=?, course_type=?, status=? WHERE id=?");
        if ($upd) { $upd->bind_param("sssdssi", $name, $section, $category, $price, $type, $status, $id); $upd->execute(); }
    }

    $redirect = match($section) {
        "junior" => "courses_junior.php",
        "demo"   => "courses_demo.php",
        default  => "courses_kids.php",
    };
    header("Location: $redirect?success=1");
    exit();
}

// ── Fetch course ─────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
if (!$stmt) { header("Location: courses_home.php"); exit(); }
$stmt->bind_param("i", $id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) { header("Location: courses_home.php"); exit(); }

$backUrl = match($course["section"]) {
    "junior" => "courses_junior.php",
    "demo"   => "courses_demo.php",
    default  => "courses_kids.php",
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Course | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:Arial,Helvetica,sans-serif; background:radial-gradient(circle at top left,rgba(62,80,119,0.07),transparent 22%),radial-gradient(circle at bottom right,rgba(37,99,235,0.05),transparent 22%),linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); color:var(--dark); }
    .app-shell { min-height:100vh; display:flex; }

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

    .edit-card { background:white; border:1.5px solid #dbeafe; border-radius:22px; padding:32px; max-width:680px; box-shadow:0 6px 24px rgba(62,80,119,0.09); }
    .edit-card-title { font-size:1.05rem; font-weight:900; color:#1e3a5f; margin-bottom:24px; display:flex; align-items:center; gap:10px; }
    .edit-card-title .title-icon { width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }

    .field-label { font-size:0.82rem; font-weight:800; color:#64748b; margin-bottom:6px; display:block; }
    .form-control, .form-select { border-radius:12px; padding:12px 14px; border:1.5px solid #e2e8f0; font-size:0.92rem; width:100%; transition:border .2s; background:white; }
    .form-control:focus, .form-select:focus { border-color:var(--primary); outline:none; box-shadow:0 0 0 3px rgba(62,80,119,0.12); }
    .field-group { margin-bottom:18px; }

    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

    .btn-save { background:linear-gradient(135deg,var(--primary),var(--secondary)); border:none; color:white; font-weight:900; border-radius:12px; padding:13px 32px; cursor:pointer; font-size:0.95rem; box-shadow:0 6px 18px rgba(62,80,119,0.3); transition:opacity .2s,transform .15s; }
    .btn-save:hover { opacity:.92; transform:translateY(-1px); }
    .btn-cancel { background:white; border:1.5px solid #e2e8f0; color:#334155; font-weight:800; border-radius:12px; padding:13px 24px; cursor:pointer; font-size:0.92rem; text-decoration:none; display:inline-flex; align-items:center; transition:background .2s; }
    .btn-cancel:hover { background:#f1f5f9; text-decoration:none; color:#0f172a; }

    @media (max-width:700px) { .form-grid-2 { grid-template-columns:1fr; } .app-shell { flex-direction:column; } .sidebar { width:100%; height:auto; position:relative; } }
  </style>
</head>
<body>
<div class="app-shell">

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

  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
    </div>

    <div class="topbar">
      <div>
        <h1><i class="fas fa-pen me-2"></i>Edit Course</h1>
        <p>Update the details for this course.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <a href="<?= $backUrl ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>

    <div class="edit-card">
      <div class="edit-card-title">
        <span class="title-icon"><i class="fas fa-pen"></i></span>
        Edit: <?= htmlspecialchars($course["course_name"]) ?>
      </div>

      <form method="POST">
        <input type="hidden" name="action"    value="edit_course">
        <input type="hidden" name="course_id" value="<?= $id ?>">

        <div class="field-group">
          <label class="field-label">Course Name <span style="color:#dc2626;">*</span></label>
          <input type="text" name="course_name" class="form-control"
                 value="<?= htmlspecialchars($course["course_name"]) ?>" required>
        </div>

        <div class="form-grid-2">
          <div class="field-group">
            <label class="field-label">Section</label>
            <select name="section" class="form-select">
              <option value="kids"   <?= $course["section"] === "kids"   ? "selected" : "" ?>>Kids (Ages 6–11)</option>
              <option value="junior" <?= $course["section"] === "junior" ? "selected" : "" ?>>Junior (Ages 12+)</option>
              <option value="demo"   <?= $course["section"] === "demo"   ? "selected" : "" ?>>Demo (Free Trial)</option>
            </select>
          </div>
          <div class="field-group">
            <label class="field-label">Module / Category</label>
            <input type="text" name="category" class="form-control"
                   value="<?= htmlspecialchars($course["category"] ?? "") ?>"
                   placeholder="e.g. Python Introduction">
          </div>
        </div>

        <div class="form-grid-2">
          <div class="field-group">
            <label class="field-label">Course Type</label>
            <select name="course_type" class="form-select">
              <option value="paid" <?= ($course["course_type"] ?? "") === "paid" ? "selected" : "" ?>>Paid</option>
              <option value="free" <?= ($course["course_type"] ?? "") === "free" ? "selected" : "" ?>>Free</option>
              <option value="demo" <?= ($course["course_type"] ?? "") === "demo" ? "selected" : "" ?>>Demo</option>
            </select>
          </div>
          <div class="field-group">
            <label class="field-label">Price</label>
            <input type="number" name="price" class="form-control" min="0" step="0.01"
                   value="<?= number_format((float)($course["price"] ?? 0), 2, '.', '') ?>"
                   placeholder="0.00">
          </div>
        </div>

        <div class="field-group">
          <label class="field-label">Status</label>
          <select name="status" class="form-select">
            <option value="active"   <?= ($course["status"] ?? "") === "active"   ? "selected" : "" ?>>Active</option>
            <option value="inactive" <?= ($course["status"] ?? "") === "inactive" ? "selected" : "" ?>>Inactive</option>
          </select>
        </div>

        <div style="display:flex;gap:12px;align-items:center;margin-top:8px;">
          <button type="submit" class="btn-save"><i class="fas fa-floppy-disk me-2"></i>Save Changes</button>
          <a href="<?= $backUrl ?>" class="btn-cancel"><i class="fas fa-xmark me-1"></i> Cancel</a>
        </div>
      </form>
    </div>
  </main>
</div>
<script src="logout-modal.js"></script>
</body>
</html>
