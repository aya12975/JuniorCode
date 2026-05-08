<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";

// Auto-create table if missing
$conn->query("CREATE TABLE IF NOT EXISTS course_projects (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    section    VARCHAR(50)  NOT NULL DEFAULT 'kids',
    category   VARCHAR(100) NOT NULL DEFAULT 'Game Development',
    title      VARCHAR(255) NOT NULL,
    url        TEXT         NOT NULL,
    image      TEXT         NOT NULL DEFAULT '',
    sort_order INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Add image column if upgrading from old table
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS image   TEXT NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS pdf_url TEXT NOT NULL DEFAULT ''");

$section  = trim($_GET["section"]  ?? "kids");
$category = trim($_GET["category"] ?? "Game Development");

$error   = "";
$success = "";

/* Handle POST actions */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $title   = trim($_POST["title"]   ?? "");
        $url     = trim($_POST["url"]     ?? "");
        $image   = trim($_POST["image"]   ?? "");
        $pdf_url = trim($_POST["pdf_url"] ?? "");
        $sec     = trim($_POST["section"]   ?? $section);
        $cat     = trim($_POST["category"]  ?? $category);

        if ($title === "") {
            $error = "Title is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO course_projects (section, category, title, url, image, pdf_url) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $sec, $cat, $title, $url, $image, $pdf_url);
            $stmt->execute() ? $success = "Project added." : $error = "Failed to add.";
            $section  = $sec;
            $category = $cat;
        }
    }

    if ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM course_projects WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute() ? $success = "Project link deleted." : $error = "Failed to delete.";
        }
    }

    if ($action === "edit") {
        $id      = (int)($_POST["id"]      ?? 0);
        $title   = trim($_POST["title"]    ?? "");
        $url     = trim($_POST["url"]      ?? "");
        $image   = trim($_POST["image"]    ?? "");
        $pdf_url = trim($_POST["pdf_url"]  ?? "");
        if ($id > 0 && $title !== "") {
            $stmt = $conn->prepare("UPDATE course_projects SET title = ?, url = ?, image = ?, pdf_url = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $title, $url, $image, $pdf_url, $id);
            $stmt->execute() ? $success = "Project updated." : $error = "Failed to update.";
        }
    }
}

/* Fetch for current section/category */
$stmt = $conn->prepare("SELECT * FROM course_projects WHERE section = ? AND category = ? ORDER BY sort_order ASC, id ASC");
$stmt->bind_param("ss", $section, $category);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* All section/category combos for the selector */
$combos = [
    ["section" => "kids",   "category" => "Game Development"],
    ["section" => "kids",   "category" => "Python Introduction"],
    ["section" => "kids",   "category" => "Virtual Machine"],
    ["section" => "junior", "category" => "Game Development"],
    ["section" => "junior", "category" => "Python Introduction"],
    ["section" => "junior", "category" => "Virtual Machine"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Project Links | Admin</title>
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
  background:
    radial-gradient(circle at top left,  rgba(37,99,235,0.08), transparent 22%),
    radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
    linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
  color: var(--dark);
}
.app-shell { min-height: 100vh; display: flex; }
.sidebar {
  width: 285px;
  background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
  color: white; padding: 24px 18px;
  position: sticky; top: 0; height: 100vh; overflow-y: auto;
  transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow: hidden;
}
body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow: hidden; }
.brand-box {
  display: flex; align-items: center; gap: 12px; margin-bottom: 28px;
  padding: 10px 12px; border-radius: 18px;
  background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.08);
}
.logo-img { width: 55px; height: 55px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
.brand-title { font-weight: 900; font-size: 1.1rem; line-height: 1.15; }
.brand-sub { font-size: 0.78rem; color: rgba(255,255,255,0.75); letter-spacing: 1px; margin-top: 3px; }
.nav-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.3px; color: rgba(255,255,255,0.55); margin: 18px 10px 10px; font-weight: 700; }
.nav-custom { display: flex; flex-direction: column; gap: 8px; }
.nav-link-custom {
  display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.88);
  text-decoration: none; padding: 13px 14px; border-radius: 14px; transition: all 0.25s; font-weight: 700;
}
.nav-link-custom:hover { background: rgba(255,255,255,0.08); color: white; }
.nav-link-custom.active { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
.nav-icon { width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.main-content { flex: 1; padding: 26px; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }
.topbar {
  display: flex; justify-content: space-between; align-items: center; gap: 16px;
  margin-bottom: 24px; padding: 18px 20px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  border-radius: 22px; box-shadow: var(--shadow);
}
.topbar h1 { font-size: 1.6rem; font-weight: 900; margin: 0; color: white; }
.topbar p  { margin: 4px 0 0; color: rgba(255,255,255,0.8); }
.admin-badge { background: rgba(255,255,255,0.15); color: white; border-radius: 999px; padding: 10px 16px; font-weight: 800; white-space: nowrap; }
.panel-card { background: white; border: 1px solid #edf4ff; border-radius: 22px; padding: 24px; box-shadow: var(--shadow); margin-bottom: 22px; }
.panel-title { font-size: 1.15rem; font-weight: 900; margin-bottom: 18px; color: var(--dark); }
.form-control, .form-select { border-radius: 12px; padding: 11px 14px; border: 1px solid #dbe4f0; font-size: 0.95rem; }
.form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(62,80,119,0.13); }
.btn-main {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  border: none; color: white; font-weight: 800; border-radius: 12px;
  padding: 10px 18px; cursor: pointer; text-decoration: none; display: inline-block;
}
.btn-main:hover { color: white; opacity: 0.92; }
.btn-back { background: #64748b; color: white; border: none; border-radius: 12px; padding: 10px 18px; font-weight: 800; text-decoration: none; display: inline-block; }
.btn-back:hover { color: white; background: #475569; }
.btn-danger-soft { background: #fee2e2; color: #991b1b; border: none; border-radius: 10px; padding: 7px 14px; font-weight: 700; cursor: pointer; font-size: 0.85rem; }
.btn-danger-soft:hover { background: #fecaca; }
.btn-edit-soft { background: #dbeafe; color: #1d4ed8; border: none; border-radius: 10px; padding: 7px 14px; font-weight: 700; cursor: pointer; font-size: 0.85rem; }
.btn-edit-soft:hover { background: #bfdbfe; }
.project-row {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 16px; border-radius: 14px;
  background: #f8fbff; border: 1px solid #dbeafe;
  margin-bottom: 10px;
}
.project-row-info { flex: 1; min-width: 0; }
.project-title { font-weight: 800; color: var(--dark); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.project-url { font-size: 0.82rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.project-url a { color: #2563eb; text-decoration: none; }
.project-url a:hover { text-decoration: underline; }
.proj-thumb {
  width: 140px; height: 140px; border-radius: 10px;
  object-fit: contain; border: 1px solid #dbeafe; flex-shrink: 0;
}
.proj-thumb-placeholder {
  width: 140px; height: 140px; border-radius: 10px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: white; display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; flex-shrink: 0;
}
.selector-bar {
  display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;
}
.sel-btn {
  padding: 9px 18px; border-radius: 12px; border: 2px solid #e2e8f0;
  font-weight: 800; font-size: 0.9rem; cursor: pointer; background: white;
  color: var(--muted); text-decoration: none; transition: all 0.18s;
  white-space: nowrap;
}
.sel-btn:hover { border-color: var(--primary); color: var(--primary); }
.sel-btn.active {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: white; border-color: transparent;
  box-shadow: 0 6px 18px rgba(62,80,119,0.22);
}
.empty-box {
  text-align: center; padding: 26px; border-radius: 16px;
  background: #f8fbff; color: var(--muted); border: 1px dashed #d9e9ff; font-weight: 700;
}
.action-boxes {
  display: flex; flex-direction: column; gap: 8px; flex-shrink: 0;
  background: #f8fbff;
  border: 1px solid #dbeafe;
  border-radius: 14px;
  padding: 12px 14px;
  min-width: 148px;
}
.action-box {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  border-radius: 10px; padding: 9px 14px;
  font-size: 0.82rem; font-weight: 800; text-decoration: none;
  white-space: nowrap; color: white; text-align: center;
  transition: filter 0.2s;
}
.action-box:hover { filter: brightness(1.1); color: white; }
.action-box-pdf  { background: linear-gradient(135deg, #f97316, #ea580c); box-shadow: 0 4px 12px rgba(249,115,22,0.28); }
.action-box-link { background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 4px 12px rgba(59,130,246,0.28); }
.action-box-empty {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  border-radius: 10px; padding: 9px 14px;
  font-size: 0.8rem; font-weight: 600; color: #94a3b8;
  border: 1.5px dashed #e2e8f0; white-space: nowrap; text-align: center;
}
/* Edit modal */
.modal-backdrop-custom {
  display: none; position: fixed; inset: 0;
  background: rgba(15,23,42,0.45); z-index: 999;
  align-items: center; justify-content: center;
}
.modal-backdrop-custom.show { display: flex; }
.modal-box {
  background: white; border-radius: 22px; padding: 28px; width: 100%; max-width: 480px;
  box-shadow: 0 24px 60px rgba(15,23,42,0.2);
}
.modal-title { font-size: 1.1rem; font-weight: 900; margin-bottom: 18px; }
@media (max-width: 991px) {
  .app-shell { flex-direction: column; }
  .sidebar { width: 100%; height: auto; position: relative; }
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
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
    </div>
    <div class="topbar">
      <div>
        <h1>Project Links</h1>
        <p>Manage project links for course categories.</p>
      </div>
      <div class="admin-badge">Hello, <?= htmlspecialchars($adminName) ?></div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Section / Category selector -->
    <div class="selector-bar">
      <?php foreach ($combos as $c):
        $active = ($c["section"] === $section && $c["category"] === $category) ? "active" : "";
        $label  = ucfirst($c["section"]) . " — " . $c["category"];
        $href   = "manage_projects.php?section=" . urlencode($c["section"]) . "&category=" . urlencode($c["category"]);
      ?>
        <a href="<?= $href ?>" class="sel-btn <?= $active ?>"><?= htmlspecialchars($label) ?></a>
      <?php endforeach; ?>
    </div>

    <!-- Add new project link -->
    <div class="panel-card">
      <div class="panel-title">Add Project Link — <?= htmlspecialchars(ucfirst($section)) ?> &rsaquo; <?= htmlspecialchars($category) ?></div>
      <form method="POST">
        <input type="hidden" name="action"   value="add">
        <input type="hidden" name="section"  value="<?= htmlspecialchars($section) ?>">
        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label fw-bold">Title</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Dino Run" required>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-bold">Image URL <span style="font-weight:400;color:var(--muted);">(opt.)</span></label>
            <input type="text" name="image" id="addImageInput" class="form-control" placeholder="images/photo.png">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold">View Project Link</label>
            <input type="url" name="url" class="form-control" placeholder="https://scratch.mit.edu/...">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold">Check Course PDF</label>
            <input type="url" name="pdf_url" class="form-control" placeholder="https://drive.google.com/...">
          </div>
          <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn-main w-100">Add</button>
          </div>
        </div>
        <div id="addImagePreview" style="display:none;margin-bottom:8px;">
          <img id="addImagePreviewImg" src="" alt="Preview" style="height:60px;border-radius:10px;border:1px solid #dbeafe;object-fit:cover;">
        </div>
      </form>
    </div>

    <!-- Existing project links -->
    <div class="panel-card">
      <div class="panel-title">
        Current Links
        <span style="font-size:0.85rem;font-weight:600;color:var(--muted);margin-left:8px;">(<?= count($projects) ?>)</span>
      </div>

      <?php if (empty($projects)): ?>
        <div class="empty-box">No project links yet. Add the first one above.</div>
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

            <!-- Action boxes -->
            <div class="action-boxes">
              <?php if (!empty($p["pdf_url"])): ?>
                <a href="<?= htmlspecialchars($p["pdf_url"]) ?>" target="_blank" class="action-box action-box-pdf">
                  <i class="fas fa-file-pdf"></i> Check Course
                </a>
              <?php else: ?>
                <div class="action-box-empty"><i class="fas fa-file-pdf"></i> No PDF yet</div>
              <?php endif; ?>

              <?php if (!empty($p["url"])): ?>
                <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" class="action-box action-box-link">
                  <i class="fas fa-arrow-up-right-from-square"></i> View Project
                </a>
              <?php else: ?>
                <div class="action-box-empty"><i class="fas fa-arrow-up-right-from-square"></i> No link yet</div>
              <?php endif; ?>
            </div>

            <button class="btn-edit-soft" onclick="openEdit(<?= $p['id'] ?>, <?= htmlspecialchars(json_encode($p['title'])) ?>, <?= htmlspecialchars(json_encode($p['url'] ?? '')) ?>, <?= htmlspecialchars(json_encode($p['image'] ?? '')) ?>, <?= htmlspecialchars(json_encode($p['pdf_url'] ?? '')) ?>)">
              <i class="fas fa-pen"></i> Edit
            </button>
            <form method="POST" onsubmit="return confirm('Delete this project?')" style="margin:0;">
              <input type="hidden" name="action"   value="delete">
              <input type="hidden" name="id"       value="<?= $p['id'] ?>">
              <input type="hidden" name="section"  value="<?= htmlspecialchars($section) ?>">
              <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
              <button type="submit" class="btn-danger-soft"><i class="fas fa-trash"></i> Delete</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div style="margin-top:18px;">
        <a href="courses.php?tab=<?= urlencode($section) ?>" class="btn-back">
          <i class="fas fa-arrow-left me-1"></i> Back to Courses
        </a>
      </div>
    </div>
  </main>
</div>

<!-- Edit modal -->
<div class="modal-backdrop-custom" id="editModal">
  <div class="modal-box">
    <div class="modal-title">Edit Project Link</div>
    <form method="POST">
      <input type="hidden" name="action"   value="edit">
      <input type="hidden" name="id"       id="editId">
      <input type="hidden" name="section"  value="<?= htmlspecialchars($section) ?>">
      <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
      <div class="mb-3">
        <label class="form-label fw-bold">Link Title</label>
        <input type="text" name="title" id="editTitle" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">Image URL <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
        <input type="text" name="image" id="editImage" class="form-control" placeholder="images/photo.png or https://...">
        <div id="editImagePreview" style="margin-top:8px;display:none;">
          <img id="editImagePreviewImg" src="" alt="Preview" style="height:60px;border-radius:10px;border:1px solid #dbeafe;object-fit:cover;">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">View Project Link <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
        <input type="url" name="url" id="editUrl" class="form-control" placeholder="https://scratch.mit.edu/...">
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">Check Course PDF <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
        <input type="url" name="pdf_url" id="editPdfUrl" class="form-control" placeholder="https://drive.google.com/...">
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-main">Save Changes</button>
        <button type="button" class="btn-back" onclick="closeEdit()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id, title, url, image, pdfUrl) {
  document.getElementById('editId').value     = id;
  document.getElementById('editTitle').value  = title;
  document.getElementById('editUrl').value    = url    || '';
  document.getElementById('editImage').value  = image  || '';
  document.getElementById('editPdfUrl').value = pdfUrl || '';
  updatePreview('editImage', 'editImagePreview', 'editImagePreviewImg');
  document.getElementById('editModal').classList.add('show');
}
function closeEdit() {
  document.getElementById('editModal').classList.remove('show');
}
function updatePreview(inputId, wrapId, imgId) {
  const val = document.getElementById(inputId).value.trim();
  const wrap = document.getElementById(wrapId);
  const img  = document.getElementById(imgId);
  if (val) { img.src = val; wrap.style.display = 'block'; }
  else      { wrap.style.display = 'none'; }
}
document.getElementById('addImageInput').addEventListener('input', function() {
  updatePreview('addImageInput', 'addImagePreview', 'addImagePreviewImg');
});
document.getElementById('editImage').addEventListener('input', function() {
  updatePreview('editImage', 'editImagePreview', 'editImagePreviewImg');
});
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});
</script>
</body>
</html>
