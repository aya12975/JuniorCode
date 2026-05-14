<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName       = $_SESSION["username"] ?? "Admin";
$section         = trim($_GET["section"]   ?? "kids");
$category_preset = trim($_GET["category"] ?? "");
$error           = "";
$success         = false;

/* ── Ensure all columns exist ── */
$needed = [
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
];
foreach ($needed as $col => $def) {
    $r = $conn->query("SHOW COLUMNS FROM courses LIKE '$col'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE courses ADD COLUMN $col $def");
    }
}

/* ── Handle POST ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $course_name = trim($_POST["course_name"] ?? "");
    $category    = trim($_POST["category"]    ?? "");
    $age_group   = trim($_POST["age_group"]   ?? "");
    $level       = trim($_POST["level"]       ?? "");
    $price       = (float)($_POST["price"]    ?? 0);
    $course_type = trim($_POST["course_type"] ?? "demo");
    $status      = trim($_POST["status"]      ?? "active");
    $duration    = trim($_POST["duration"]    ?? "");
    $image       = trim($_POST["image"]       ?? "");
    $section     = trim($_POST["section"]     ?? "kids");
    $sub_section = trim($_POST["sub_section"] ?? "");

    if ($course_name === "") {
        $error = "Course name is required.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO courses (course_name, category, age_group, level, price, course_type, status, duration, image, section, sub_section)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ssssdssssss",
                $course_name, $category, $age_group, $level, $price,
                $course_type, $status, $duration, $image, $section, $sub_section
            );
            if ($stmt->execute()) {
                header("Location: courses.php?tab=" . urlencode($section) . "&success=1");
                exit();
            } else {
                $error = "Failed to save: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Course | JuniorCode Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --shadow:0 18px 45px rgba(37,99,235,0.08); }
*{ box-sizing:border-box; }
body{ margin:0; font-family:Arial,Helvetica,sans-serif;
  background: radial-gradient(circle at top left,rgba(37,99,235,.08),transparent 22%),
              radial-gradient(circle at bottom right,rgba(56,189,248,.08),transparent 22%),
              linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); color:var(--dark); }
.app-shell{ min-height:100vh; display:flex; }
.sidebar{
  width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%);
  color:white; padding:0; position:sticky; top:0; height:100vh;
  flex-shrink:0; display:flex; flex-direction:column;
  transition:width .3s ease,padding .3s ease; overflow:hidden;
}
body.sidebar-collapsed .sidebar{ width:0; padding:0; min-width:0; }
.sidebar-top-area{ padding:0 18px 18px; flex:1; overflow-y:auto; }
.sidebar-bottom{ padding:16px 18px; border-top:1px solid rgba(255,255,255,.1); }
.brand-box{ display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,.1); margin-bottom:10px; }
.logo-img{ width:55px; height:55px; object-fit:contain; border-radius:12px; flex-shrink:0; }
.brand-title{ font-weight:900; font-size:1.1rem; line-height:1.15; }
.brand-sub{ font-size:.75rem; color:rgba(255,255,255,.55); letter-spacing:1px; margin-top:3px; }
.nav-title{ font-size:.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,.45); margin:20px 10px 10px; font-weight:700; }
.nav-custom{ display:flex; flex-direction:column; gap:4px; }
.nav-link-custom{ display:flex; align-items:center; gap:12px; color:rgba(255,255,255,.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all .25s; font-weight:700; }
.nav-link-custom:hover{ background:rgba(255,255,255,.08); color:white; }
.nav-link-custom.active{ background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; }
.nav-icon{ width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.main-content{ flex:1; padding:26px; }
.hamburger-btn{ display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.hamburger-btn:hover{ background:#f1f5f9; }
.hamburger-line{ width:22px; height:2.5px; background:#334155; border-radius:2px; }
.topbar{ display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:24px; padding:18px 20px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; box-shadow:var(--shadow); }
.topbar h1{ font-size:1.6rem; font-weight:900; margin:0; color:white; }
.topbar p{ margin:4px 0 0; color:rgba(255,255,255,.8); }
.admin-badge{ background:rgba(255,255,255,.15); color:white; border-radius:12px; border:1px solid rgba(255,255,255,.2); padding:10px 18px; font-weight:800; white-space:nowrap; }
.card-box{ background:white; border:1px solid #edf4ff; border-radius:24px; padding:32px; box-shadow:var(--shadow); max-width:760px; }
.form-label{ font-weight:800; color:#334155; margin-bottom:6px; display:block; font-size:.9rem; }
.form-control,.form-select{ border-radius:12px; padding:11px 14px; border:1px solid #dbeafe; font-size:.95rem; width:100%; }
.form-control:focus,.form-select:focus{ border-color:var(--primary); box-shadow:0 0 0 .2rem rgba(62,80,119,.13); outline:none; }
.section-badge{ display:inline-block; padding:5px 14px; border-radius:999px; font-weight:800; font-size:.82rem; background:#eff6ff; color:var(--primary); margin-bottom:20px; border:1px solid #bfdbfe; }
.btn-save{ background:linear-gradient(135deg,var(--primary),var(--secondary)); border:none; color:white; font-weight:800; border-radius:12px; padding:12px 28px; cursor:pointer; font-size:.95rem; }
.btn-save:hover{ opacity:.92; }
.btn-back{ background:#f1f5f9; color:#334155; border:none; border-radius:12px; padding:12px 24px; font-weight:800; text-decoration:none; display:inline-block; font-size:.95rem; }
.btn-back:hover{ background:#e2e8f0; color:#0f172a; }
@media(max-width:991px){ .app-shell{ flex-direction:column; } .sidebar{ width:100%; height:auto; position:relative; } }
</style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="sidebar-top-area">
      <div class="brand-box">
        <img src="images/robot2.png.png" class="logo-img" alt="Logo">
        <div>
          <div class="brand-title">JuniorCode</div>
          <div class="brand-sub">Admin Panel</div>
        </div>
      </div>
      <div class="nav-title">Main</div>
      <div class="nav-custom">
        <a href="admin_dashboard.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
        <a href="manage_users.php"    class="nav-link-custom"><span class="nav-icon"><i class="fas fa-users"></i></span><span>Manage Users</span></a>
        <a href="admin_teacher_students.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span></a>
          <a href="admin_enrollments.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span>
          </a>
        <a href="manage_classes.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
        <a href="teacher_earnings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
        <a href="available_slots.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
        <a href="courses.php"         class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
        <a href="reports.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
        <a href="admin_certificates.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
<a href="admin_email_notifications.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-envelope"></i></span><span>Email Notifications</span></a>
      </div>
    </div>
    <div class="sidebar-bottom">
      <a href="settings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
      <div style="height:1px;background:rgba(255,255,255,.1);margin:8px 0;"></div>
      <a href="logout.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
    </div>
  </aside>

  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
    </div>

    <div class="topbar">
      <div>
        <h1><i class="fas fa-plus me-2"></i>Add Course</h1>
        <p>Fill in the details to create a new course.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger mb-3" style="border-radius:14px;font-weight:700;">
        <i class="fas fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="card-box">
      <div class="section-badge"><i class="fas fa-tag me-1"></i>Section: <?= htmlspecialchars(ucfirst($section)) ?></div>

      <form method="POST">
        <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">

        <!-- Course Name -->
        <div class="mb-3">
          <label class="form-label">Course Name <span style="color:#ef4444">*</span></label>
          <input type="text" name="course_name" class="form-control"
                 value="<?= htmlspecialchars($_POST['course_name'] ?? '') ?>"
                 placeholder="e.g. Python for Kids" required autofocus>
        </div>

        <!-- Category + Age Group -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Category <?php if($section !== 'demo'): ?><span style="color:#ef4444">*</span><?php endif; ?></label>
            <input type="text" name="category" class="form-control"
                   value="<?= htmlspecialchars($_POST['category'] ?? $category_preset) ?>"
                   placeholder="e.g. Game Development"
                   <?= $section !== 'demo' ? 'required' : '' ?>>
            <?php if($section !== 'demo' && $category_preset): ?>
              <div style="font-size:.8rem;color:#64748b;margin-top:4px;">
                <i class="fas fa-info-circle"></i> This course will appear under <strong><?= htmlspecialchars($category_preset) ?></strong>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Age Group</label>
            <input type="text" name="age_group" class="form-control"
                   value="<?= htmlspecialchars($_POST['age_group'] ?? '') ?>"
                   placeholder="e.g. 6–9">
          </div>
        </div>

        <!-- Level + Duration -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Level</label>
            <input type="text" name="level" class="form-control"
                   value="<?= htmlspecialchars($_POST['level'] ?? '') ?>"
                   placeholder="e.g. Beginner">
          </div>
          <div class="col-md-6">
            <label class="form-label">Duration</label>
            <input type="text" name="duration" class="form-control"
                   value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>"
                   placeholder="e.g. 4 weeks">
          </div>
        </div>

        <!-- Price + Type + Status -->
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Price ($)</label>
            <input type="number" name="price" class="form-control" step="0.01" min="0"
                   value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="course_type" class="form-select">
              <option value="demo" <?= (($_POST['course_type'] ?? 'demo') === 'demo') ? 'selected' : '' ?>>Demo</option>
              <option value="paid" <?= (($_POST['course_type'] ?? '') === 'paid') ? 'selected' : '' ?>>Paid</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active"   <?= (($_POST['status'] ?? 'active') === 'active')   ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
        </div>

        <!-- Image URL -->
        <div class="mb-4">
          <label class="form-label">Image URL <span style="font-weight:400;color:#64748b">(optional)</span></label>
          <input type="text" name="image" class="form-control"
                 value="<?= htmlspecialchars($_POST['image'] ?? '') ?>"
                 placeholder="https://...">
        </div>

        <!-- Sub-section (only for demo) -->
        <?php if ($section === 'demo'): ?>
        <div class="mb-4 p-3" style="background:#f0fdf4;border:1px solid #86efac;border-radius:14px;">
          <label class="form-label" style="color:#15803d;">Demo sub-section</label>
          <div style="display:flex;gap:10px;">
            <label style="flex:1;cursor:pointer;">
              <input type="radio" name="sub_section" value="kids"
                     <?= (($_POST['sub_section'] ?? '') === 'kids') ? 'checked' : '' ?>>
              <span style="font-weight:700;margin-left:6px;">Kids</span>
            </label>
            <label style="flex:1;cursor:pointer;">
              <input type="radio" name="sub_section" value="junior"
                     <?= (($_POST['sub_section'] ?? '') === 'junior') ? 'checked' : '' ?>>
              <span style="font-weight:700;margin-left:6px;">Junior</span>
            </label>
          </div>
        </div>
        <?php else: ?>
          <input type="hidden" name="sub_section" value="">
        <?php endif; ?>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button type="submit" class="btn-save"><i class="fas fa-check me-1"></i> Add Course</button>
          <a href="courses.php?tab=<?= urlencode($section) ?>" class="btn-back"><i class="fas fa-arrow-left me-1"></i> Back</a>
        </div>
      </form>
    </div>
  </main>
</div>
<script src="logout-modal.js"></script>
</body>
</html>
