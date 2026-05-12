<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName = $_SESSION["username"] ?? "Admin";

// Ensure course_projects table exists before any POST handler runs
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
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS image   TEXT NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE course_projects ADD COLUMN IF NOT EXISTS pdf_url TEXT NOT NULL DEFAULT ''");

// Handle category rename / delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cat_action"])) {
    $catAction  = $_POST["cat_action"];
    $catSection = trim($_POST["cat_section"] ?? "");
    $catOld     = trim($_POST["cat_old"]     ?? "");
    $catNew     = trim($_POST["cat_new"]     ?? "");

    if ($catSection && $catOld) {
        if ($catAction === "rename" && $catNew && $catNew !== $catOld) {
            $s1 = $conn->prepare("UPDATE courses SET category=? WHERE section=? AND category=?");
            if ($s1) { $s1->bind_param("sss", $catNew, $catSection, $catOld); $s1->execute(); }
            $s2 = $conn->prepare("UPDATE course_projects SET category=? WHERE section=? AND category=?");
            if ($s2) { $s2->bind_param("sss", $catNew, $catSection, $catOld); $s2->execute(); }
        } elseif ($catAction === "delete") {
            $d1 = $conn->prepare("DELETE FROM courses WHERE section=? AND category=?");
            if ($d1) { $d1->bind_param("ss", $catSection, $catOld); $d1->execute(); }
            $d2 = $conn->prepare("DELETE FROM course_projects WHERE section=? AND category=?");
            if ($d2) { $d2->bind_param("ss", $catSection, $catOld); $d2->execute(); }
        }
    }
    header("Location: courses.php?tab=" . urlencode($catSection) . "&success=1");
    exit();
}

$search      = trim($_GET["search"] ?? "");
$filterType  = trim($_GET["type"] ?? "");
$activeTab   = $_GET["tab"] ?? "kids";

function fetchCourses($conn, $section, $search, $filterType) {
    $sql    = "SELECT * FROM courses WHERE section = ?";
    $params = [$section];
    $types  = "s";

    if ($search !== "") {
        $sql .= " AND course_name LIKE ?";
        $params[] = "%" . $search . "%";
        $types .= "s";
    }
    if ($filterType !== "") {
        $sql .= " AND course_type = ?";
        $params[] = $filterType;
        $types .= "s";
    }
    $sql .= " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

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

$juniorResult = fetchCourses($conn, 'junior', $search, $filterType);
$demoResult   = fetchCourses($conn, 'demo',   $search, $filterType);

function fetchKidsByCategory($conn, $category, $search, $filterType) {
    $sql    = "SELECT * FROM courses WHERE section = 'kids' AND category = ?";
    $params = [$category];
    $types  = "s";
    if ($search !== "") { $sql .= " AND course_name LIKE ?"; $params[] = "%$search%"; $types .= "s"; }
    if ($filterType !== "") { $sql .= " AND course_type = ?"; $params[] = $filterType; $types .= "s"; }
    $sql .= " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

function fetchProjects($conn, $section, $category) {
    $stmt = $conn->prepare("SELECT * FROM course_projects WHERE section = ? AND category = ? ORDER BY sort_order ASC, id ASC");
    if (!$stmt) return [];
    $stmt->bind_param("ss", $section, $category);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchCoursesByCategory($conn, $section, $category, $search, $filterType) {
    $sql    = "SELECT * FROM courses WHERE section = ? AND category = ?";
    $params = [$section, $category];
    $types  = "ss";
    if ($search !== "") { $sql .= " AND course_name LIKE ?"; $params[] = "%$search%"; $types .= "s"; }
    if ($filterType !== "") { $sql .= " AND course_type = ?"; $params[] = $filterType; $types .= "s"; }
    $sql .= " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

function getCategoriesForSection($conn, $section) {
    $defaults = ['Game Development', 'Python Introduction', 'Virtual Machine'];
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

    return $cats ?: $defaults;
}

$kidsCategories   = getCategoriesForSection($conn, 'kids');
$juniorCategories = getCategoriesForSection($conn, 'junior');

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}

function renderProjectLinks($projects, $section, $category) {
    $manageUrl = "manage_projects.php?section=" . urlencode($section) . "&category=" . urlencode($category);
    ob_start(); ?>
    <div class="proj-section-header">
      <span class="proj-section-label"><i class="fas fa-link" style="color:#2563eb;margin-right:6px;"></i>Project Links</span>
      <a href="<?= htmlspecialchars($manageUrl) ?>" class="btn-manage-proj">
        <i class="fas fa-pen-to-square"></i> Manage Links
      </a>
    </div>
    <?php if (empty($projects)): ?>
      <div class="empty-box" style="margin-bottom:18px;">
        No project links added yet.
        <a href="<?= htmlspecialchars($manageUrl) ?>" style="color:#2563eb;font-weight:700;margin-left:6px;">Add one</a>
      </div>
    <?php else: ?>
      <div class="proj-list">
        <?php foreach ($projects as $p): ?>
          <div class="proj-item">
            <?php if (!empty($p["image"])): ?>
              <div class="proj-icon">
                <img src="<?= htmlspecialchars($p["image"]) ?>" alt="<?= htmlspecialchars($p["title"]) ?>">
              </div>
            <?php else: ?>
              <div class="proj-icon-fallback"><i class="fas fa-gamepad"></i></div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
              <div class="proj-title"><?= htmlspecialchars($p["title"]) ?></div>
            </div>
            <div class="proj-action-boxes">
              <?php if (!empty($p["pdf_url"])): ?>
                <?php $pdfHref = (strpos($p["pdf_url"], 'http') === 0) ? $p["pdf_url"] : 'uploads/pdfs/' . $p["pdf_url"]; ?>
                <a href="<?= htmlspecialchars($pdfHref) ?>" target="_blank" class="proj-action-btn proj-action-pdf">
                  <i class="fas fa-file-pdf"></i> Check Course
                </a>
              <?php else: ?>
                <span class="proj-action-empty"><i class="fas fa-file-pdf"></i> No PDF</span>
              <?php endif; ?>
              <?php if (!empty($p["url"])): ?>
                <a href="<?= htmlspecialchars($p["url"]) ?>" target="_blank" class="proj-action-btn proj-action-link">
                  <i class="fas fa-arrow-up-right-from-square"></i> View Project
                </a>
              <?php else: ?>
                <span class="proj-action-empty"><i class="fas fa-arrow-up-right-from-square"></i> No Link</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php return ob_get_clean();
}

function renderCourseTable($result) {
    if (!$result || $result->num_rows === 0) {
        return '<div class="empty-box">No courses added yet.</div>';
    }
    ob_start(); ?>
    <div class="course-card-list">
      <?php while ($c = $result->fetch_assoc()): ?>
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
        <div class="course-card-actions">
          <a href="edit_course.php?id=<?= $c["id"] ?>" class="ca-btn ca-edit">
            <i class="fas fa-pen"></i> Edit
          </a>
          <button type="button" class="ca-btn ca-delete" style="border:none;cursor:pointer;" onclick="openCourseDelModal(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['course_name'])) ?>)">
            <i class="fas fa-trash"></i> Delete
          </button>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php return ob_get_clean();
}
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
    :root {
      --primary: #3e5077;
      --secondary: #143674;
      --dark: #0f172a;
      --muted: #64748b;
      --soft: #eff6ff;
      --border: #dbeafe;
      --shadow: 0 18px 45px rgba(37, 99, 235, 0.08);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background:
        radial-gradient(circle at top left, rgba(37, 99, 235, 0.08), transparent 22%),
        radial-gradient(circle at bottom right, rgba(56, 189, 248, 0.08), transparent 22%),
        linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
      color: var(--dark);
    }

    .app-shell {
      min-height: 100vh;
      display: flex;
    }

    .sidebar {
      width: 285px;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: white;
      padding:  0;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow: hidden;
      display: flex; flex-direction: column; justify-content: space-between;
    }
    .sidebar-bottom { padding: 16px 18px; border-top: 1px solid rgba(255,255,255,0.1); }

    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow: hidden; }

    .sidebar-top-area { padding: 0 18px 18px; flex: 1; }
    .brand-box { display: flex; align-items: center; gap: 12px; padding: 0 4px 22px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }

    .logo-img {
      width: 55px;
      height: 55px;
      object-fit: contain;
      border-radius: 12px;
      background: none;
      flex-shrink: 0;
    }

    .brand-title {
      font-weight: 900;
      font-size: 1.1rem;
      line-height: 1.15;
    }

    .brand-sub {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.55);
      letter-spacing: 1px;
      margin-top: 3px;
    }

    .nav-title {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 1.3px;
      color: rgba(255,255,255,0.45);
      margin: 20px 10px 10px;
      font-weight: 700;
    }

    .nav-custom {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .nav-link-custom {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(255,255,255,0.78);
      text-decoration: none;
      padding: 12px 14px;
      border-radius: 14px;
      transition: all 0.25s ease;
      font-weight: 700;
    }

    .nav-link-custom:hover {
      background: rgba(255,255,255,0.08);
      color: white;
    }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      box-shadow: 0 10px 24px rgba(37, 99, 235, 0.28);
    }

    .nav-icon {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .main-content {
      flex: 1;
      padding: 26px;
    }

    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .panel-card {
      background: rgba(255,255,255,0.9);
      border: 1px solid #edf4ff;
      border-radius: 22px;
      box-shadow: var(--shadow);
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px;
      padding: 18px 20px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 22px;
      box-shadow: var(--shadow);
    }

    .topbar h1 {
      font-size: 1.8rem;
      font-weight: 900;
      margin: 0;
      color: white;
    }

    .topbar p {
      margin: 4px 0 0;
      color: rgba(255,255,255,0.8);
    }

    .admin-badge {
      background: rgba(255,255,255,0.15);
      color: #f6f8fc;
      border-radius: 12px; border: 1px solid rgba(255,255,255,0.2);
      padding: 10px 18px;
      font-weight: 800;
      white-space: nowrap;
    }

    .panel-card {
      padding: 22px;
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 18px;
      flex-wrap: wrap;
    }

    .panel-title {
      font-size: 1.2rem;
      font-weight: 900;
      margin: 0;
    }

    .btn-main {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border: none;
      color: white;
      font-weight: 800;
      border-radius: 14px;
      padding: 10px 16px;
      text-decoration: none;
      display: inline-block;
    }

    .btn-main:hover {
      color: white;
      opacity: 0.95;
    }

    .filters {
      display: grid;
      grid-template-columns: 1fr 220px 180px 140px;
      gap: 12px;
      margin-bottom: 18px;
    }

    .form-control, .form-select {
      border-radius: 14px;
      padding: 12px 14px;
      border: 1px solid #dbe4f0;
    }

    .table-responsive {
      border-radius: 18px;
      overflow: hidden;
    }

    .table {
      margin-bottom: 0;
      background: white;
    }

    .table thead th {
      background: #f8fbff;
      font-weight: 800;
      color: var(--dark);
      border-bottom: 1px solid #e6eefb;
      white-space: nowrap;
    }

    .course-img {
      width: 54px;
      height: 54px;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid #e5edf9;
      background: #f8fbff;
    }

    .type-badge,
    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 800;
    }

    .type-demo {
      background: #dbeafe;
      color: #1d4ed8;
    }

    .type-paid {
      background: #dcfce7;
      color: #166534;
    }

    .status-active {
      background: #dcfce7;
      color: #166534;
    }

    .status-inactive {
      background: #fee2e2;
      color: #991b1b;
    }

    .action-btn {
      text-decoration: none;
      font-weight: 700;
      margin-right: 10px;
      white-space: nowrap;
    }

    .edit-btn {
      color: #2563eb;
    }

    .delete-btn {
      color: #dc2626;
    }

    .tab-bar {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }

    .tab-btn {
      padding: 12px 28px;
      border-radius: 14px;
      border: 2px solid transparent;
      font-weight: 800;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.2s;
      background: white;
      color: var(--muted);
      border-color: #e2e8f0;
    }

    .tab-btn:hover { border-color: var(--primary); color: var(--primary); }

    .tab-btn.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      border-color: transparent;
      box-shadow: 0 6px 18px rgba(62,80,119,0.25);
    }

    .tab-section { display: none; }
    .tab-section.active { display: block; }

    .section-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 6px;
    }

    .section-icon {
      width: 36px; height: 36px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
    }

    .kids-icon   { background: #fef9c3; color: #854d0e; }
    .junior-icon { background: #ede9fe; color: #5b21b6; }
    .demo-icon   { background: #dcfce7; color: #166534; }

    .kids-drop-item {
      display: block;
      width: 100%;
      padding: 13px 18px;
      border: none;
      background: none;
      text-align: left;
      font-weight: 700;
      font-size: 0.95rem;
      color: #0f172a;
      cursor: pointer;
      transition: background 0.15s;
      border-bottom: 1px solid #f1f5f9;
    }
    .kids-drop-item:last-child { border-bottom: none; }
    .kids-drop-item:hover  { background: #f0f7ff; color: var(--primary); }
    .kids-drop-item.active { background: #eff6ff; color: var(--primary); font-weight: 900; }

    .kids-cat-section   { display: none; }
    .kids-cat-section.active   { display: block; }
    .junior-cat-section { display: none; }
    .junior-cat-section.active { display: block; }

    .grade-group {
      margin-bottom: 24px;
    }

    .grade-group-header {
      margin-bottom: 12px;
    }

    .grade-group-label {
      font-size: 1rem;
      font-weight: 900;
      color: #0f172a;
    }

    .grade-cards {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
    }

    .grade-card {
      background: #f8fbff;
      border: 1px solid #dbeafe;
      border-radius: 16px;
      padding: 18px 22px;
      min-width: 180px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .grade-num {
      font-weight: 900;
      font-size: 1rem;
      color: #0f172a;
    }

    .grade-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.85rem;
      font-weight: 700;
      color: #2563eb;
      text-decoration: none;
      background: #dbeafe;
      padding: 6px 12px;
      border-radius: 8px;
      transition: background 0.2s;
    }
    .grade-link:hover { background: #bfdbfe; color: #1d4ed8; }

    .grade-link-empty {
      font-size: 0.82rem;
      color: #94a3b8;
      font-style: italic;
    }

    .proj-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 18px;
    }

    .proj-item {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #f0f7ff;
      border: 1px solid #bfdbfe;
      border-radius: 14px;
      padding: 13px 16px;
    }

    .proj-icon {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .proj-icon img {
      max-height: 140px;
      max-width: 200px;
      width: auto;
      height: auto;
      border-radius: 12px;
      display: block;
    }

    .proj-icon-fallback {
      width: 54px; height: 54px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
      flex-shrink: 0;
    }

    .proj-title {
      font-weight: 800;
      color: #0f172a;
      font-size: 0.92rem;
    }

    .proj-url {
      font-size: 0.8rem;
      color: var(--muted);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 320px;
    }

    .proj-action-boxes {
      display: flex;
      flex-direction: column;
      gap: 8px;
      flex-shrink: 0;
      background: #f8fbff;
      border: 1px solid #dbeafe;
      border-radius: 14px;
      padding: 12px 14px;
      min-width: 148px;
    }
    .proj-action-btn {
      display: flex; align-items: center; justify-content: center; gap: 7px;
      font-size: 0.82rem; font-weight: 800; text-decoration: none;
      padding: 9px 14px; border-radius: 10px; white-space: nowrap;
      transition: filter 0.2s; color: white; text-align: center;
    }
    .proj-action-btn:hover { filter: brightness(1.1); color: white; }
    .proj-action-pdf  { background: linear-gradient(135deg, #f97316, #ea580c); box-shadow: 0 4px 12px rgba(249,115,22,0.28); }
    .proj-action-link { background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 4px 12px rgba(59,130,246,0.28); }
    .proj-action-empty {
      display: flex; align-items: center; justify-content: center; gap: 7px;
      font-size: 0.8rem; font-weight: 600; color: #94a3b8;
      padding: 9px 14px; border-radius: 10px;
      border: 1.5px dashed #e2e8f0; white-space: nowrap; text-align: center;
    }

    .proj-section-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 12px; flex-wrap: wrap; gap: 8px;
    }

    .proj-section-label {
      font-size: 1rem; font-weight: 800; color: #0f172a;
    }

    .btn-manage-proj {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 0.82rem; font-weight: 800;
      color: #5b21b6; background: #ede9fe;
      border: none; border-radius: 10px;
      padding: 7px 14px; text-decoration: none;
      transition: background 0.15s;
    }
    .btn-manage-proj:hover { background: #ddd6fe; color: #4c1d95; }

    .empty-box {
      text-align: center;
      padding: 26px 18px;
      border-radius: 18px;
      background: #f8fbff;
      color: var(--muted);
      border: 1px dashed #d9e9ff;
      font-weight: 700;
    }

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
    .course-card-actions { display:flex; flex-direction:column; gap:8px; flex-shrink:0; }
    .ca-btn {
      display:flex; align-items:center; justify-content:center; gap:6px;
      padding:9px 18px; border-radius:10px; font-size:0.85rem;
      font-weight:800; text-decoration:none; white-space:nowrap;
    }
    .ca-edit  { background:#dbeafe; color:#1d4ed8; }
    .ca-edit:hover  { background:#bfdbfe; color:#1e40af; }
    .ca-delete { background:#fee2e2; color:#dc2626; }
    .ca-delete:hover { background:#fecaca; color:#b91c1c; }

    @media (max-width: 1100px) {
      .filters {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 991px) {
      .app-shell {
        flex-direction: column;
      }

      .sidebar {
        width: 100%;
        height: auto;
        position: relative;
      }
    }
  </style>
<script>(function(){var t=localStorage.getItem("jc-theme");if(t==="dark")document.documentElement.classList.add("dark");})();</script><style>html.dark body{background:#0f172a!important;color:#e2e8f0!important}html.dark .sidebar{background:linear-gradient(180deg,#020817 0%,#0c1226 100%)!important}html.dark .panel-card,html.dark .stat-card{background:#1e293b!important;border-color:#334155!important;color:#e2e8f0}html.dark .stat-label{color:#94a3b8!important}html.dark .stat-value{color:#f1f5f9!important}html.dark .form-control,html.dark .form-select,html.dark textarea{background:#1e293b!important;border-color:#475569!important;color:#e2e8f0!important}html.dark .topbar{background:linear-gradient(135deg,#1e3a6e 0%,#0f2456 100%)!important}html.dark .table thead th{background:#1e293b!important;color:#94a3b8!important;border-color:#334155!important}html.dark .table td{color:#cbd5e1!important;border-color:#334155!important}html.dark .panel-title{color:#f1f5f9!important}</style>
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
        <a href="admin_dashboard.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-house"></i></span>
          <span>Dashboard</span>
        </a>

        <a href="manage_users.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-users"></i></span>
          <span>Manage Users</span>
        </a>

        <a href="manage_classes.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-book"></i></span>
          <span>Manage Classes</span>
        </a>

        <a href="teacher_earnings.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
          <span>Teacher Earnings</span>
        </a>

        <a href="available_slots.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
          <span>Available Slots</span>
        </a>

        <a href="courses.php" class="nav-link-custom active">
          <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
          <span>Courses</span>
        </a>

        <a href="reports.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
          <span>Reports</span>
        </a>
        <a href="admin_certificates.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-award"></i></span>
          <span>Certificates</span>
        </a>
        <a href="admin_ai_settings.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-robot"></i></span>
          <span>AI Tutor</span>
        </a>
        <a href="admin_quiz_generator.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-circle-question"></i></span>
          <span>AI Quiz Generator</span>
        </a>
        <a href="admin_email_notifications.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-envelope"></i></span>
          <span>Email Notifications</span>
        </a>

      </div>
      </div>
      <div class="sidebar-bottom">
        <a href="settings.php" class="nav-link-custom">
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

    <main class="main-content">
      <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
      </div>
      <div class="topbar">
        <div>
          <h1>Courses</h1>
          <p>Manage academy courses, demo classes, and paid programs.</p>
        </div>
        <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?php echo htmlspecialchars($adminName); ?> &nbsp;·&nbsp; <?php echo date("d M Y"); ?></div>
      </div>

      <?php if (isset($_GET["success"])): ?>
        <div class="alert alert-success">Action completed successfully.</div>
      <?php endif; ?>

      <?php if (isset($_GET["error"])): ?>
        <div class="alert alert-danger">Something went wrong. Please try again.</div>
      <?php endif; ?>

      <!-- Filter bar -->
      <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
        <input type="hidden" name="tab" id="activeTabInput" value="<?= htmlspecialchars($activeTab) ?>">
        <input type="text" name="search" class="form-control" style="max-width:260px;" placeholder="Search by course name" value="<?= htmlspecialchars($search) ?>">
        <select name="type" class="form-select" style="max-width:160px;">
          <option value="">All Types</option>
          <option value="demo" <?= $filterType === "demo" ? "selected" : "" ?>>Demo</option>
          <option value="paid" <?= $filterType === "paid" ? "selected" : "" ?>>Paid</option>
        </select>
        <button type="submit" class="btn-main">Filter</button>
      </form>

      <!-- Tabs -->
      <div class="tab-bar">
        <button class="tab-btn <?= $activeTab === 'kids'   ? 'active' : '' ?>" onclick="switchTab('kids')">Kids</button>
        <button class="tab-btn <?= $activeTab === 'junior' ? 'active' : '' ?>" onclick="switchTab('junior')">Junior</button>
        <button class="tab-btn <?= $activeTab === 'demo'   ? 'active' : '' ?>" onclick="switchTab('demo')">Demo</button>
      </div>

      <!-- Kids Section -->
      <div id="tab-kids" class="tab-section <?= $activeTab === 'kids' ? 'active' : '' ?>">

        <?php
        // Dropdown
        if (!empty($kidsCategories)):
        ?>
        <div style="position:relative;display:inline-block;margin-bottom:16px;">
          <button class="btn-main" onclick="toggleKidsMenu(event)" type="button">
            Select Kids Module <i class="fas fa-chevron-down ms-2" id="kids-chevron"></i>
          </button>
          <div id="kids-dropdown" style="display:none;position:absolute;top:calc(100% + 8px);left:0;background:white;border:1px solid #dbeafe;border-radius:16px;box-shadow:0 12px 32px rgba(15,23,42,0.12);min-width:220px;z-index:100;overflow:hidden;">
            <?php foreach ($kidsCategories as $i => $cat):
              $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($cat)); ?>
              <button class="kids-drop-item <?= $i === 0 ? 'active' : '' ?>"
                      onclick="switchKidsCat('<?= htmlspecialchars($slug) ?>', this)" type="button">
                <?= htmlspecialchars($cat) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <?php foreach ($kidsCategories as $i => $cat):
          $slug   = preg_replace('/[^a-z0-9]+/', '-', strtolower($cat));
          $catJs  = htmlspecialchars(json_encode($cat), ENT_QUOTES);
          $catEnc = urlencode($cat);
          $projs  = fetchProjects($conn, 'kids', $cat);
          $crs    = fetchCoursesByCategory($conn, 'kids', $cat, $search, $filterType);
        ?>
        <div id="kids-<?= htmlspecialchars($slug) ?>" class="kids-cat-section <?= $i === 0 ? 'active' : '' ?>">
          <section class="panel-card">
            <div class="panel-header">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <h2 class="panel-title"><?= htmlspecialchars($cat) ?></h2>
                <button type="button" onclick="openRenameModal('kids', <?= $catJs ?>)" class="ca-btn ca-edit" style="padding:6px 14px;border:none;cursor:pointer;">
                  <i class="fas fa-pen"></i> Edit
                </button>
                <button type="button" onclick="openCatDelModal('kids', <?= $catJs ?>)" class="ca-btn ca-delete" style="padding:6px 14px;border:none;cursor:pointer;">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </div>
              <a href="add_course.php?section=kids&category=<?= $catEnc ?>" class="btn-main">+ Add Course</a>
            </div>
            <?= renderProjectLinks($projs, 'kids', $cat) ?>
            <?= renderCourseTable($crs) ?>
          </section>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

      </div>

      <!-- Junior Section -->
      <div id="tab-junior" class="tab-section <?= $activeTab === 'junior' ? 'active' : '' ?>">

        <?php if (!empty($juniorCategories)): ?>
        <div style="position:relative;display:inline-block;margin-bottom:16px;">
          <button class="btn-main" onclick="toggleJuniorMenu(event)" type="button">
            Select Junior Module <i class="fas fa-chevron-down ms-2" id="junior-chevron"></i>
          </button>
          <div id="junior-dropdown" style="display:none;position:absolute;top:calc(100% + 8px);left:0;background:white;border:1px solid #dbeafe;border-radius:16px;box-shadow:0 12px 32px rgba(15,23,42,0.12);min-width:220px;z-index:100;overflow:hidden;">
            <?php foreach ($juniorCategories as $i => $cat):
              $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($cat)); ?>
              <button class="kids-drop-item <?= $i === 0 ? 'active' : '' ?>"
                      onclick="switchJuniorCat('<?= htmlspecialchars($slug) ?>', this)" type="button">
                <?= htmlspecialchars($cat) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <?php foreach ($juniorCategories as $i => $cat):
          $slug   = preg_replace('/[^a-z0-9]+/', '-', strtolower($cat));
          $catJs  = htmlspecialchars(json_encode($cat), ENT_QUOTES);
          $catEnc = urlencode($cat);
          $projs  = fetchProjects($conn, 'junior', $cat);
          $crs    = fetchCoursesByCategory($conn, 'junior', $cat, $search, $filterType);
        ?>
        <div id="junior-<?= htmlspecialchars($slug) ?>" class="junior-cat-section <?= $i === 0 ? 'active' : '' ?>">
          <section class="panel-card">
            <div class="panel-header">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <h2 class="panel-title"><?= htmlspecialchars($cat) ?></h2>
                <button type="button" onclick="openRenameModal('junior', <?= $catJs ?>)" class="ca-btn ca-edit" style="padding:6px 14px;border:none;cursor:pointer;">
                  <i class="fas fa-pen"></i> Edit
                </button>
                <button type="button" onclick="openCatDelModal('junior', <?= $catJs ?>)" class="ca-btn ca-delete" style="padding:6px 14px;border:none;cursor:pointer;">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </div>
              <a href="add_course.php?section=junior&category=<?= $catEnc ?>" class="btn-main">+ Add Course</a>
            </div>
            <?= renderProjectLinks($projs, 'junior', $cat) ?>
            <?= renderCourseTable($crs) ?>
          </section>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

      </div>

      <!-- Demo Section -->
      <div id="tab-demo" class="tab-section <?= $activeTab === 'demo' ? 'active' : '' ?>">
        <section class="panel-card">
          <div class="panel-header">
            <h2 class="panel-title" style="color:#166634;">Demo Courses</h2>
            <a href="add_course.php?section=demo" class="btn-main">+ Add Demo Course</a>
          </div>

          <!-- Little -->
          <div class="grade-group">
            <div class="grade-group-header">
              <span class="grade-group-label">Little <span style="font-weight:400;color:#64748b;">Grade 1 – Grade 3</span></span>
            </div>
            <div class="grade-cards">
              <div class="grade-card">
                <a href="https://studio.code.org/courses/courseb-2025/units/1/lessons/3/levels/2" target="_blank" class="grade-link">
                  <i class="fas fa-external-link-alt"></i> Code.org — Course B
                </a>
              </div>
              <div class="grade-card">
                <a href="https://studio.code.org/flappy/1" target="_blank" class="grade-link">
                  <i class="fas fa-external-link-alt"></i> Code.org — Flappy
                </a>
              </div>
              <div class="grade-card">
                <a href="https://scratch.mit.edu/projects/889441020/" target="_blank" class="grade-link">
                  <i class="fas fa-external-link-alt"></i> Scratch Project
                </a>
              </div>
            </div>
          </div>

          <!-- Junior -->
          <div class="grade-group">
            <div class="grade-group-header">
              <span class="grade-group-label">Junior</span>
            </div>
            <div class="grade-cards">
              <div class="grade-card">
                <a href="https://scratch.mit.edu/projects/889441020/" target="_blank" class="grade-link">
                  <i class="fas fa-external-link-alt"></i> Scratch Project
                </a>
              </div>
              <div class="grade-card">
                <a href="https://x.thunkable.com/projectPage/65d61cf59f6fe10a3cc8ea2f" target="_blank" class="grade-link">
                  <i class="fas fa-external-link-alt"></i> Thunkable Project
                </a>
              </div>
              <div class="grade-card">
                <a href="https://www.onlinegdb.com/EIipF2SoF" target="_blank" class="grade-link">
                  <i class="fas fa-external-link-alt"></i> OnlineGDB
                </a>
              </div>
            </div>
          </div>

          <?= renderCourseTable($demoResult) ?>
        </section>
      </div>
    </main>
  </div>
<!-- Delete Confirmation Modal -->
<div id="del-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:22px;padding:36px;max-width:400px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,0.18);text-align:center;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
      <i class="fas fa-trash" style="font-size:1.5rem;color:#dc2626;"></i>
    </div>
    <div id="del-modal-title" style="font-size:1.1rem;font-weight:900;margin-bottom:8px;"></div>
    <p id="del-modal-msg" style="color:#64748b;font-size:0.92rem;margin-bottom:24px;"></p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button id="del-modal-confirm" style="background:linear-gradient(135deg,#dc2626,#b91c1c);border:none;color:white;font-weight:800;border-radius:12px;padding:12px 28px;cursor:pointer;font-size:0.95rem;">
        <i class="fas fa-trash me-1"></i> Yes, Delete
      </button>
      <button onclick="closeDelModal()" style="background:#64748b;color:white;border:none;border-radius:12px;padding:12px 24px;font-weight:800;cursor:pointer;">Cancel</button>
    </div>
  </div>
</div>

<!-- Hidden form for category delete (submitted by modal) -->
<form id="del-cat-form" method="POST" style="display:none;">
  <input type="hidden" name="cat_action" value="delete">
  <input type="hidden" name="cat_section" id="del-cat-section">
  <input type="hidden" name="cat_old"     id="del-cat-old">
</form>

<!-- Rename Category Modal -->
<div id="rename-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:22px;padding:36px;max-width:420px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,0.18);">
    <h3 style="font-weight:900;margin:0 0 6px;font-size:1.2rem;">Rename Course</h3>
    <p style="color:#64748b;font-size:0.88rem;margin:0 0 22px;">Enter the new name for this course category.</p>
    <form method="POST">
      <input type="hidden" name="cat_action" value="rename">
      <input type="hidden" name="cat_section" id="modal-section">
      <input type="hidden" name="cat_old" id="modal-old">
      <div style="margin-bottom:18px;">
        <label style="font-weight:800;color:#334155;display:block;margin-bottom:8px;">New Name</label>
        <input type="text" name="cat_new" id="modal-new" class="form-control" required>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-main">Save</button>
        <button type="button" onclick="closeRenameModal()" style="background:#64748b;color:white;border:none;border-radius:14px;padding:13px 24px;font-weight:900;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDelModal(title, msg, onConfirm) {
  document.getElementById('del-modal-title').textContent = title;
  document.getElementById('del-modal-msg').textContent   = msg;
  document.getElementById('del-modal-confirm').onclick   = function() { closeDelModal(); onConfirm(); };
  document.getElementById('del-modal').style.display     = 'flex';
}
function closeDelModal() {
  document.getElementById('del-modal').style.display = 'none';
}
document.getElementById('del-modal').addEventListener('click', function(e) {
  if (e.target === this) closeDelModal();
});
function openCatDelModal(section, cat) {
  openDelModal(
    'Delete "' + cat + '"?',
    'This will permanently delete the "' + cat + '" module and ALL its courses and project links. This cannot be undone.',
    function() {
      document.getElementById('del-cat-section').value = section;
      document.getElementById('del-cat-old').value     = cat;
      document.getElementById('del-cat-form').submit();
    }
  );
}
function openCourseDelModal(id, name) {
  openDelModal(
    'Delete Course?',
    'Are you sure you want to delete "' + name + '"? This cannot be undone.',
    function() { window.location.href = 'delete_course.php?id=' + id; }
  );
}

function openRenameModal(section, cat) {
  document.getElementById('modal-section').value = section;
  document.getElementById('modal-old').value     = cat;
  document.getElementById('modal-new').value     = cat;
  const m = document.getElementById('rename-modal');
  m.style.display = 'flex';
}
function closeRenameModal() {
  document.getElementById('rename-modal').style.display = 'none';
}
document.getElementById('rename-modal').addEventListener('click', function(e) {
  if (e.target === this) closeRenameModal();
});

function toggleJuniorMenu(e) {
  e.stopPropagation();
  const d = document.getElementById('junior-dropdown');
  const open = d.style.display === 'block';
  d.style.display = open ? 'none' : 'block';
  document.getElementById('junior-chevron').className = open ? 'fas fa-chevron-down ms-2' : 'fas fa-chevron-up ms-2';
}

function switchJuniorCat(cat, btn) {
  document.querySelectorAll('.junior-cat-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('#junior-dropdown .kids-drop-item').forEach(b => b.classList.remove('active'));
  document.getElementById('junior-' + cat).classList.add('active');
  btn.classList.add('active');
  document.getElementById('junior-dropdown').style.display = 'none';
  document.getElementById('junior-chevron').className = 'fas fa-chevron-down ms-2';
}

function toggleKidsMenu(e) {
  e.stopPropagation();
  const d = document.getElementById('kids-dropdown');
  const open = d.style.display === 'block';
  d.style.display = open ? 'none' : 'block';
  document.getElementById('kids-chevron').className = open ? 'fas fa-chevron-down ms-2' : 'fas fa-chevron-up ms-2';
}

document.addEventListener('click', function() {
  document.getElementById('kids-dropdown').style.display = 'none';
  document.getElementById('kids-chevron').className = 'fas fa-chevron-down ms-2';
  document.getElementById('junior-dropdown').style.display = 'none';
  document.getElementById('junior-chevron').className = 'fas fa-chevron-down ms-2';
});

function switchKidsCat(cat, btn) {
  document.querySelectorAll('.kids-cat-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.kids-drop-item').forEach(b => b.classList.remove('active'));
  document.getElementById('kids-' + cat).classList.add('active');
  btn.classList.add('active');
  document.getElementById('kids-dropdown').style.display = 'none';
  document.getElementById('kids-chevron').className = 'fas fa-chevron-down ms-2';
}

function switchTab(tab) {
  document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  document.getElementById('activeTabInput').value = tab;
  event.currentTarget.classList.add('active');
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>