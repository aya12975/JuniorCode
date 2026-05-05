<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName   = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);
$section     = trim($_GET["section"] ?? "kids");

$newCourse = null;
$formError = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $course_name = trim($_POST["course_name"] ?? "");
    $category    = trim($_POST["category"]    ?? "");
    $age_group   = trim($_POST["age_group"]   ?? "");
    $level       = trim($_POST["level"]       ?? "");
    $price       = trim($_POST["price"]       ?? "0");
    $course_type = trim($_POST["course_type"] ?? "demo");
    $status      = trim($_POST["status"]      ?? "active");
    $duration    = trim($_POST["duration"]    ?? "");
    $image       = trim($_POST["image"]       ?? "");
    $section     = trim($_POST["section"]     ?? "kids");
    $sub_section = trim($_POST["sub_section"] ?? "");

    if ($course_name === "") {
        $formError = true;
    } else {
        // Ensure section column exists
        $chk = $conn->query("SHOW COLUMNS FROM courses LIKE 'section'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE courses ADD COLUMN section VARCHAR(50) NOT NULL DEFAULT 'kids'");
        }
        // Ensure sub_section column exists
        $chk2 = $conn->query("SHOW COLUMNS FROM courses LIKE 'sub_section'");
        if ($chk2 && $chk2->num_rows === 0) {
            $conn->query("ALTER TABLE courses ADD COLUMN sub_section VARCHAR(50) NOT NULL DEFAULT ''");
        }

        $stmt = $conn->prepare("INSERT INTO courses (course_name, category, age_group, level, price, course_type, status, duration, image, section, sub_section) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdssssss", $course_name, $category, $age_group, $level, $price, $course_type, $status, $duration, $image, $section, $sub_section);

        if ($stmt->execute()) {
            header("Location: courses.php?tab=" . urlencode($section) . "&success=1");
            exit();
        } else {
            $formError = true;
        }
    }
}

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Course | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
  --primary: #3e5077;
  --secondary: #143674;
  --dark: #0f172a;
  --muted: #64748b;
  --shadow: 0 18px 45px rgba(37,99,235,0.08);
}
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: Arial, Helvetica, sans-serif;
  background: radial-gradient(circle at top left, rgba(37,99,235,0.08), transparent 22%),
              radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
              linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%);
  color: var(--dark);
}
.app-shell { min-height:100vh; display:flex; }
.sidebar {
  width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%);
  color:white; padding:24px 18px; position:sticky; top:0; height:100vh; overflow-y:auto;
}
.brand-box {
  display:flex; align-items:center; gap:12px; margin-bottom:28px;
  padding:10px 12px; border-radius:18px;
  background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.08);
}
.logo-img { width:55px; height:55px; object-fit:contain; border-radius:12px; background:none; padding:6px; flex-shrink:0; }
.brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
.brand-sub { font-size:0.78rem; color:rgba(255,255,255,0.75); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.8rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.55); margin:18px 10px 10px; font-weight:700; }
.nav-custom { display:flex; flex-direction:column; gap:8px; }
.nav-link-custom {
  display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.88);
  text-decoration:none; padding:13px 14px; border-radius:14px; transition:all 0.25s; font-weight:700;
}
.nav-link-custom:hover { background:rgba(255,255,255,0.08); color:white; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; }
.nav-icon { width:34px; height:34px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.main-content { flex:1; padding:26px; }
.topbar {
  display:flex; justify-content:space-between; align-items:center; gap:16px;
  margin-bottom:24px; padding:18px 20px;
  background:linear-gradient(135deg,var(--primary),var(--secondary));
  border-radius:22px; box-shadow:var(--shadow);
}
.topbar h1 { color:white; margin:0; font-weight:900; }
.topbar p  { margin:4px 0 0; color:rgba(255,255,255,0.8); }
.admin-badge { background:rgba(255,255,255,0.15); color:white; border-radius:999px; padding:10px 16px; font-weight:800; white-space:nowrap; }
.card-box { background:white; padding:36px; border-radius:24px; box-shadow:var(--shadow); border:1px solid #edf4ff; max-width:780px; }
.form-label { font-weight:800; color:#334155; margin-bottom:8px; }
.form-control, .form-select { height:52px; border-radius:14px; font-size:1rem; padding:12px 16px; border:1px solid #dbeafe; }
.form-control:focus, .form-select:focus { border-color:var(--primary); box-shadow:0 0 0 0.2rem rgba(62,80,119,0.15); }
.btn-main {
  background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white;
  border:none; border-radius:14px; padding:13px 24px; font-weight:900; text-decoration:none; cursor:pointer;
}
.btn-main:hover { color:white; opacity:0.95; }
.btn-back { background:#64748b; color:white; border:none; border-radius:14px; padding:13px 24px; font-weight:900; text-decoration:none; }
.btn-back:hover { color:white; background:#475569; }

.sub-section-box {
  background:#f0fdf4; border:1px solid #86efac; border-radius:16px;
  padding:18px 20px; margin-bottom:20px;
}
.sub-section-box label { font-weight:800; color:#15803d; margin-bottom:10px; display:block; }
.sub-section-options { display:flex; gap:12px; }
.sub-option {
  flex:1; padding:14px; border-radius:14px; border:2px solid #bbf7d0;
  background:white; cursor:pointer; text-align:center; font-weight:800;
  color:#15803d; transition:all 0.2s;
}
.sub-option:hover, .sub-option.selected { background:#16a34a; color:white; border-color:#16a34a; }

@media (max-width:991px) {
  .app-shell { flex-direction:column; }
  .sidebar { width:100%; height:auto; position:relative; }
}
</style>
</head>
<body>
<div class="app-shell">

  <aside class="sidebar">
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
      <a href="manage_classes.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
      <a href="teacher_earnings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
      <a href="available_slots.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
      <a href="courses.php" class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
      <a href="reports.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
      <a href="settings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
      <a href="logout.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div>
        <h1>Add Course</h1>
        <p>Create a new course for the <?= ucfirst(htmlspecialchars($section)) ?> section.</p>
      </div>
      <div class="admin-badge">Hello, <?= htmlspecialchars($adminName) ?></div>
    </div>

    <div class="card-box">

      <?php if ($formError): ?>
        <div class="alert alert-danger">Please fill in the course name and try again.</div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">

        <?php if ($section === 'demo'): ?>
        <div class="sub-section-box">
          <label>Which demo section is this course for?</label>
          <div class="sub-section-options">
            <div class="sub-option <?= ($_POST['sub_section'] ?? '') === 'kids' ? 'selected' : '' ?>"
                 onclick="selectSub('kids')" id="sub-kids">Kids</div>
            <div class="sub-option <?= ($_POST['sub_section'] ?? '') === 'junior' ? 'selected' : '' ?>"
                 onclick="selectSub('junior')" id="sub-junior">Junior</div>
          </div>
          <input type="hidden" name="sub_section" id="sub_section_input" value="<?= htmlspecialchars($_POST['sub_section'] ?? '') ?>">
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <label class="form-label">Course Name</label>
            <input type="text" name="course_name" class="form-control" value="<?= htmlspecialchars($_POST['course_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Age Group</label>
            <input type="text" name="age_group" class="form-control" placeholder="e.g. 6–9" value="<?= htmlspecialchars($_POST['age_group'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Level</label>
            <input type="text" name="level" class="form-control" placeholder="e.g. Beginner" value="<?= htmlspecialchars($_POST['level'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Duration</label>
            <input type="text" name="duration" class="form-control" placeholder="e.g. 4 weeks" value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Price ($)</label>
            <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="course_type" class="form-select">
              <option value="demo" <?= ($_POST['course_type'] ?? 'demo') === 'demo' ? 'selected' : '' ?>>Demo</option>
              <option value="paid" <?= ($_POST['course_type'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active"   <?= ($_POST['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label">Image URL</label>
          <input type="text" name="image" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($_POST['image'] ?? '') ?>">
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <button type="submit" class="btn-main">Add Course</button>
          <a href="courses.php?tab=<?= urlencode($section) ?>" class="btn-back">Back</a>
        </div>
      </form>
    </div>
  </main>
</div>

<script>
function selectSub(val) {
  document.getElementById('sub_section_input').value = val;
  document.getElementById('sub-kids').classList.toggle('selected',   val === 'kids');
  document.getElementById('sub-junior').classList.toggle('selected', val === 'junior');
}
</script>
</body>
</html>
