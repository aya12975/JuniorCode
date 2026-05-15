<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName   = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
    header("Location: courses.php");
    exit();
}

// Load existing course
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    header("Location: courses.php");
    exit();
}

/* ── Ensure course_projects table & course_id column exist ── */
$conn->query("CREATE TABLE IF NOT EXISTS course_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT DEFAULT NULL,
    section VARCHAR(50) NOT NULL DEFAULT 'kids',
    category VARCHAR(100) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL DEFAULT '',
    image TEXT NOT NULL DEFAULT '',
    pdf_url TEXT NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS course_id INT DEFAULT NULL");

/* ── Handle project add ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_project") {
    $ptitle = trim($_POST["proj_title"] ?? "");
    $purl   = trim($_POST["proj_url"]   ?? "");
    $pimage = trim($_POST["proj_image"] ?? "");
    $ppdf   = trim($_POST["proj_pdf"]   ?? "");
    $sec    = $course["section"]   ?? "";
    $cat    = $course["category"]  ?? "";

    if ($ptitle !== "") {
        if (!empty($_FILES["proj_pdf_file"]["name"])) {
            if (!is_dir("uploads/pdfs")) mkdir("uploads/pdfs", 0755, true);
            $ext = strtolower(pathinfo($_FILES["proj_pdf_file"]["name"], PATHINFO_EXTENSION));
            if ($ext === "pdf" && $_FILES["proj_pdf_file"]["error"] === 0) {
                $fname = uniqid("proj_", true) . ".pdf";
                if (move_uploaded_file($_FILES["proj_pdf_file"]["tmp_name"], "uploads/pdfs/" . $fname)) $ppdf = $fname;
            }
        }
        $ins = $conn->prepare("INSERT INTO course_projects (course_id, section, category, title, url, image, pdf_url) VALUES (?,?,?,?,?,?,?)");
        if ($ins) { $ins->bind_param("issssss", $id, $sec, $cat, $ptitle, $purl, $pimage, $ppdf); $ins->execute(); $ins->close(); }
    }
    header("Location: edit_course.php?id=$id&proj_added=1");
    exit();
}

/* ── Handle project delete ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_project") {
    $projId = (int)($_POST["proj_id"] ?? 0);
    if ($projId > 0) {
        $del = $conn->prepare("DELETE FROM course_projects WHERE id = ? AND course_id = ?");
        if ($del) { $del->bind_param("ii", $projId, $id); $del->execute(); $del->close(); }
    }
    header("Location: edit_course.php?id=$id");
    exit();
}

/* ── Load existing projects for this course ── */
$projStmt = $conn->prepare("SELECT * FROM course_projects WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
$projStmt->bind_param("i", $id);
$projStmt->execute();
$existingProjects = $projStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$projStmt->close();

$formError = false;
$errors    = [];

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
    $section     = trim($_POST["section"]     ?? $course["section"]);
    $sub_section = trim($_POST["sub_section"] ?? "");

    if ($course_name === "") {
        $formError = true;
        $errors[]  = "Course name is required.";
    } else {
        $upd = $conn->prepare("UPDATE courses SET course_name=?, category=?, age_group=?, level=?, price=?, course_type=?, status=?, duration=?, image=?, section=?, sub_section=? WHERE id=?");
        $upd->bind_param("ssssdssssssi", $course_name, $category, $age_group, $level, $price, $course_type, $status, $duration, $image, $section, $sub_section, $id);

        if ($upd->execute()) {
            header("Location: courses.php?tab=" . urlencode($section) . "&success=1");
            exit();
        } else {
            $formError = true;
            $errors[]  = "Database error. Please try again.";
        }
    }

    // Keep form values on error
    $course["course_name"] = $course_name;
    $course["category"]    = $category;
    $course["age_group"]   = $age_group;
    $course["level"]       = $level;
    $course["price"]       = $price;
    $course["course_type"] = $course_type;
    $course["status"]      = $status;
    $course["duration"]    = $duration;
    $course["image"]       = $image;
    $course["section"]     = $section;
    $course["sub_section"] = $sub_section;
}

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="images/robot2.png.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Course | Admin</title>
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
  color:white; padding:0; position:sticky; top:0; height:100vh;
  transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow-y: auto;
}
body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow-y: auto; }
.sidebar-top-area { padding: 0 18px 18px; }
.brand-box { display: flex; align-items: center; gap: 12px; padding: 0 4px 22px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }
.logo-img { width:55px; height:55px; object-fit:contain; border-radius:12px; background:none; flex-shrink:0; }
.brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
.brand-sub { font-size:0.78rem; color:rgba(255,255,255,0.75); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.8rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.55); margin:18px 10px 10px; font-weight:700; }
.nav-custom { display:flex; flex-direction:column; gap:4px; }
.nav-link-custom {
  display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.88);
  text-decoration:none; padding:12px 14px; border-radius:14px; transition:all 0.25s; font-weight:700;
}
.nav-link-custom:hover { background:rgba(255,255,255,0.08); color:white; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.main-content { flex:1; padding:26px; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }
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
      <a href="admin_dashboard.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
      <a href="manage_users.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-users"></i></span><span>Manage Users</span></a>
      <a href="admin_teacher_students.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span></a>
          <a href="admin_enrollments.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span>
          </a>
      <a href="manage_classes.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
      <a href="teacher_earnings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
      <a href="available_slots.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
      <a href="courses.php"          class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
      <a href="reports.php"          class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
      <a href="admin_certificates.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
      <a href="settings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
      <a href="logout.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
    </div>
    </div>
  </aside>

  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
    </div>
    <div class="topbar">
      <div>
        <h1>Edit Course</h1>
        <p>Update details for: <?= htmlspecialchars($course["course_name"]) ?></p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <div class="card-box">

      <?php if ($formError): ?>
        <div class="alert alert-danger"><?= implode("<br>", array_map('htmlspecialchars', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="section" value="<?= htmlspecialchars($course["section"]) ?>">

        <?php if ($course["section"] === 'demo'): ?>
        <div class="sub-section-box">
          <label>Which demo section is this course for?</label>
          <div class="sub-section-options">
            <div class="sub-option <?= ($course["sub_section"] ?? '') === 'kids'   ? 'selected' : '' ?>"
                 onclick="selectSub('kids')" id="sub-kids">Kids</div>
            <div class="sub-option <?= ($course["sub_section"] ?? '') === 'junior' ? 'selected' : '' ?>"
                 onclick="selectSub('junior')" id="sub-junior">Junior</div>
          </div>
          <input type="hidden" name="sub_section" id="sub_section_input" value="<?= htmlspecialchars($course["sub_section"] ?? '') ?>">
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <label class="form-label">Course Name</label>
            <input type="text" name="course_name" class="form-control"
                   value="<?= htmlspecialchars($course["course_name"]) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control"
                   value="<?= htmlspecialchars($course["category"]) ?>">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Age Group</label>
            <input type="text" name="age_group" class="form-control" placeholder="e.g. 6–9"
                   value="<?= htmlspecialchars($course["age_group"]) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Level</label>
            <input type="text" name="level" class="form-control" placeholder="e.g. Beginner"
                   value="<?= htmlspecialchars($course["level"]) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Duration</label>
            <input type="text" name="duration" class="form-control" placeholder="e.g. 4 weeks"
                   value="<?= htmlspecialchars($course["duration"]) ?>">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Price ($)</label>
            <input type="number" name="price" class="form-control" step="0.01" min="0"
                   value="<?= htmlspecialchars($course["price"]) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="course_type" class="form-select">
              <option value="demo" <?= $course["course_type"] === "demo" ? "selected" : "" ?>>Demo</option>
              <option value="paid" <?= $course["course_type"] === "paid" ? "selected" : "" ?>>Paid</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active"   <?= $course["status"] === "active"   ? "selected" : "" ?>>Active</option>
              <option value="inactive" <?= $course["status"] === "inactive" ? "selected" : "" ?>>Inactive</option>
            </select>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label">Image URL</label>
          <input type="text" name="image" class="form-control" placeholder="https://..."
                 value="<?= htmlspecialchars($course["image"]) ?>">
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <button type="submit" class="btn-main">Save Changes</button>
          <a href="courses.php?tab=<?= urlencode($course["section"]) ?>" class="btn-back">Cancel</a>
        </div>
      </form>
    </div>

    <!-- ── Projects Section ── -->
    <div class="card-box" style="max-width:780px;margin-top:24px;">

      <?php if (isset($_GET["proj_added"])): ?>
        <div class="alert alert-success" style="border-radius:12px;font-weight:700;margin-bottom:20px;">
          <i class="fas fa-circle-check me-2"></i>Project added successfully.
        </div>
      <?php endif; ?>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <div>
          <div style="font-size:1.1rem;font-weight:900;color:#0f172a;"><i class="fas fa-folder-open me-2" style="color:#3e5077;"></i>Course Projects</div>
          <div style="font-size:0.82rem;color:#94a3b8;margin-top:2px;">Projects linked to this course</div>
        </div>
        <button onclick="document.getElementById('add-proj-form').style.display = document.getElementById('add-proj-form').style.display === 'none' ? 'block' : 'none'"
          style="background:linear-gradient(135deg,#3e5077,#143674);border:none;color:white;font-weight:800;border-radius:10px;padding:9px 16px;cursor:pointer;font-size:0.85rem;">
          <i class="fas fa-plus me-1"></i> Add Project
        </button>
      </div>

      <!-- Existing projects -->
      <?php if (empty($existingProjects)): ?>
        <div style="text-align:center;padding:22px;background:#f8fbff;border:1px dashed #dbeafe;border-radius:14px;color:#94a3b8;font-weight:700;margin-bottom:18px;">
          No projects yet — add the first one below.
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
          <?php foreach ($existingProjects as $p):
            $pdfHref = !empty($p["pdf_url"]) ? (str_starts_with($p["pdf_url"], "http") ? $p["pdf_url"] : "uploads/pdfs/" . $p["pdf_url"]) : "";
          ?>
          <div style="display:flex;align-items:center;gap:12px;background:#f8fbff;border:1px solid #dbeafe;border-radius:14px;padding:13px 16px;">
            <?php if (!empty($p["image"])): ?>
              <img src="<?= htmlspecialchars($p["image"]) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:10px;border:1px solid #e5edf9;flex-shrink:0;">
            <?php else: ?>
              <div style="width:48px;height:48px;border-radius:10px;background:linear-gradient(135deg,#3e5077,#143674);color:white;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-gamepad"></i></div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:800;color:#0f172a;font-size:0.95rem;"><?= htmlspecialchars($p["title"]) ?></div>
              <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;">
                <?php if (!empty($p["url"])): ?>
                  <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" style="font-size:0.78rem;font-weight:700;color:#2563eb;background:#dbeafe;border-radius:7px;padding:3px 10px;text-decoration:none;"><i class="fas fa-arrow-up-right-from-square me-1"></i>View</a>
                <?php endif; ?>
                <?php if ($pdfHref): ?>
                  <a href="<?= htmlspecialchars($pdfHref) ?>" target="_blank" style="font-size:0.78rem;font-weight:700;color:#f97316;background:#ffedd5;border-radius:7px;padding:3px 10px;text-decoration:none;"><i class="fas fa-file-pdf me-1"></i>PDF</a>
                <?php endif; ?>
              </div>
            </div>
            <form method="POST" onsubmit="return confirm('Delete this project?')">
              <input type="hidden" name="action"  value="delete_project">
              <input type="hidden" name="proj_id" value="<?= $p["id"] ?>">
              <button type="submit" style="background:#fee2e2;border:none;color:#dc2626;border-radius:10px;padding:8px 14px;font-weight:800;cursor:pointer;font-size:0.82rem;">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Add project form (hidden by default) -->
      <div id="add-proj-form" style="display:none;background:#f0f7ff;border:1px solid #bfdbfe;border-radius:16px;padding:20px;">
        <div style="font-weight:900;font-size:0.92rem;color:#0f172a;margin-bottom:14px;"><i class="fas fa-plus-circle me-1" style="color:#3e5077;"></i> New Project</div>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_project">
          <div class="row g-2">
            <div class="col-md-6">
              <label style="font-size:0.82rem;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Title <span style="color:#ef4444;">*</span></label>
              <input type="text" name="proj_title" class="form-control" style="height:auto;padding:10px 12px;" placeholder="e.g. Dino Run" required>
            </div>
            <div class="col-md-6">
              <label style="font-size:0.82rem;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Image URL</label>
              <input type="text" name="proj_image" class="form-control" style="height:auto;padding:10px 12px;" placeholder="images/photo.png or https://...">
            </div>
            <div class="col-md-6">
              <label style="font-size:0.82rem;font-weight:700;color:#334155;display:block;margin-bottom:4px;">View Project Link</label>
              <input type="text" name="proj_url" class="form-control" style="height:auto;padding:10px 12px;" placeholder="https://scratch.mit.edu/...">
            </div>
            <div class="col-md-6">
              <label style="font-size:0.82rem;font-weight:700;color:#334155;display:block;margin-bottom:4px;">PDF / Lesson File</label>
              <input type="file" name="proj_pdf_file" accept=".pdf" class="form-control" style="height:auto;padding:8px 12px;margin-bottom:4px;">
              <input type="text" name="proj_pdf" class="form-control" style="height:auto;padding:10px 12px;" placeholder="or paste PDF link">
            </div>
          </div>
          <div style="display:flex;gap:10px;margin-top:14px;">
            <button type="submit" style="background:linear-gradient(135deg,#3e5077,#143674);border:none;color:white;font-weight:800;border-radius:10px;padding:10px 20px;cursor:pointer;font-size:0.88rem;">
              <i class="fas fa-plus me-1"></i> Save Project
            </button>
            <button type="button" onclick="document.getElementById('add-proj-form').style.display='none'"
              style="background:#f1f5f9;border:none;color:#64748b;font-weight:800;border-radius:10px;padding:10px 16px;cursor:pointer;">Cancel</button>
          </div>
        </form>
      </div>

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
<script src="logout-modal.js"></script>
</body>
</html>


