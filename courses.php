<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName = $_SESSION["username"] ?? "Admin";

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

$kidsGame   = fetchKidsByCategory($conn, 'Game Development',     $search, $filterType);
$kidsPython = fetchKidsByCategory($conn, 'Python Introduction',  $search, $filterType);
$kidsVM     = fetchKidsByCategory($conn, 'Virtual Machine',      $search, $filterType);

function fetchJuniorByCategory($conn, $category, $search, $filterType) {
    $sql    = "SELECT * FROM courses WHERE section = 'junior' AND category = ?";
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

$juniorGame   = fetchJuniorByCategory($conn, 'Game Development',    $search, $filterType);
$juniorPython = fetchJuniorByCategory($conn, 'Python Introduction', $search, $filterType);
$juniorVM     = fetchJuniorByCategory($conn, 'Virtual Machine',     $search, $filterType);

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}

function renderCourseTable($result) {
    if (!$result || $result->num_rows === 0) {
        return '<div class="empty-box">No courses found.</div>';
    }
    ob_start(); ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>ID</th><th>Image</th><th>Course Name</th><th>Category</th>
            <th>Age Group</th><th>Level</th><th>Price</th><th>Type</th>
            <th>Status</th><th>Duration</th><th style="width:180px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($course = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($course["id"]) ?></td>
            <td>
              <?php if (!empty($course["image"])): ?>
                <img src="<?= htmlspecialchars($course["image"]) ?>" alt="Course" class="course-img">
              <?php else: ?>
                <div class="course-img d-flex align-items-center justify-content-center text-muted" style="font-size:.75rem;">No Img</div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($course["course_name"]) ?></td>
            <td><?= htmlspecialchars($course["category"]) ?></td>
            <td><?= htmlspecialchars($course["age_group"]) ?></td>
            <td><?= htmlspecialchars($course["level"]) ?></td>
            <td>$<?= number_format((float)$course["price"], 2) ?></td>
            <td><span class="type-badge <?= $course["course_type"] === "demo" ? "type-demo" : "type-paid" ?>"><?= ucfirst(htmlspecialchars($course["course_type"])) ?></span></td>
            <td><span class="status-badge <?= $course["status"] === "active" ? "status-active" : "status-inactive" ?>"><?= ucfirst(htmlspecialchars($course["status"])) ?></span></td>
            <td><?= htmlspecialchars($course["duration"]) ?></td>
            <td>
              <a href="edit_course.php?id=<?= $course["id"] ?>" class="action-btn edit-btn">Edit</a>
              <a href="delete_course.php?id=<?= $course["id"] ?>" class="action-btn delete-btn" onclick="return confirm('Delete this course?')">Delete</a>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
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
      padding: 24px 18px;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
    }

    .brand-box {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 28px;
      padding: 10px 12px;
      border-radius: 18px;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.08);
    }

    .logo-img {
      width: 55px;
      height: 55px;
      object-fit: contain;
      border-radius: 12px;
      background: none;
      padding: 6px;
      flex-shrink: 0;
    }

    .brand-title {
      font-weight: 900;
      font-size: 1.1rem;
      line-height: 1.15;
    }

    .brand-sub {
      font-size: 0.78rem;
      color: rgba(255,255,255,0.75);
      letter-spacing: 1px;
      margin-top: 3px;
    }

    .nav-title {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 1.3px;
      color: rgba(255,255,255,0.55);
      margin: 18px 10px 10px;
      font-weight: 700;
    }

    .nav-custom {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .nav-link-custom {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(255,255,255,0.88);
      text-decoration: none;
      padding: 13px 14px;
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
      width: 34px;
      height: 34px;
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
      border-radius: 999px;
      padding: 10px 16px;
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

    .empty-box {
      text-align: center;
      padding: 26px 18px;
      border-radius: 18px;
      background: #f8fbff;
      color: var(--muted);
      border: 1px dashed #d9e9ff;
      font-weight: 700;
    }

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

        <a href="settings.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-gear"></i></span>
          <span>Settings</span>
        </a>

        <a href="logout.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <main class="main-content">
      <div class="topbar">
        <div>
          <h1>Courses</h1>
          <p>Manage academy courses, demo classes, and paid programs.</p>
        </div>
        <div class="admin-badge">
          Hello, <?php echo htmlspecialchars($adminName); ?>
        </div>
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

        <!-- Kids sub-category selector -->
        <div style="position:relative;display:inline-block;margin-bottom:16px;">
          <button class="btn-main" onclick="toggleKidsMenu(event)" type="button">
            Select Kids Module <i class="fas fa-chevron-down ms-2" id="kids-chevron"></i>
          </button>
          <div id="kids-dropdown" style="
            display:none; position:absolute; top:calc(100% + 8px); left:0;
            background:white; border:1px solid #dbeafe; border-radius:16px;
            box-shadow:0 12px 32px rgba(15,23,42,0.12); min-width:220px; z-index:100;
            overflow:hidden;
          ">
            <button class="kids-drop-item active" onclick="switchKidsCat('game',   this)" type="button">Game Development</button>
            <button class="kids-drop-item"        onclick="switchKidsCat('python', this)" type="button">Python Introduction</button>
            <button class="kids-drop-item"        onclick="switchKidsCat('vm',     this)" type="button">Virtual Machine</button>
          </div>
        </div>

        <div id="kids-game" class="kids-cat-section active">
          <section class="panel-card">
            <div class="panel-header">
              <h2 class="panel-title">Game Development</h2>
              <a href="add_course.php?section=kids&category=Game+Development" class="btn-main">+ Add Course</a>
            </div>
            <?= renderCourseTable($kidsGame) ?>
          </section>
        </div>

        <div id="kids-python" class="kids-cat-section">
          <section class="panel-card">
            <div class="panel-header">
              <h2 class="panel-title">Python Introduction</h2>
              <a href="add_course.php?section=kids&category=Python+Introduction" class="btn-main">+ Add Course</a>
            </div>
            <?= renderCourseTable($kidsPython) ?>
          </section>
        </div>

        <div id="kids-vm" class="kids-cat-section">
          <section class="panel-card">
            <div class="panel-header">
              <h2 class="panel-title">Virtual Machine</h2>
              <a href="add_course.php?section=kids&category=Virtual+Machine" class="btn-main">+ Add Course</a>
            </div>
            <?= renderCourseTable($kidsVM) ?>
          </section>
        </div>

      </div>

      <!-- Junior Section -->
      <div id="tab-junior" class="tab-section <?= $activeTab === 'junior' ? 'active' : '' ?>">

        <div style="position:relative;display:inline-block;margin-bottom:16px;">
          <button class="btn-main" onclick="toggleJuniorMenu(event)" type="button">
            Select Junior Module <i class="fas fa-chevron-down ms-2" id="junior-chevron"></i>
          </button>
          <div id="junior-dropdown" style="
            display:none; position:absolute; top:calc(100% + 8px); left:0;
            background:white; border:1px solid #dbeafe; border-radius:16px;
            box-shadow:0 12px 32px rgba(15,23,42,0.12); min-width:220px; z-index:100;
            overflow:hidden;
          ">
            <button class="kids-drop-item active" onclick="switchJuniorCat('game',   this)" type="button">Game Development</button>
            <button class="kids-drop-item"        onclick="switchJuniorCat('python', this)" type="button">Python Introduction</button>
            <button class="kids-drop-item"        onclick="switchJuniorCat('vm',     this)" type="button">Virtual Machine</button>
          </div>
        </div>

        <div id="junior-game" class="junior-cat-section active">
          <section class="panel-card">
            <div class="panel-header">
              <h2 class="panel-title">Game Development</h2>
              <a href="add_course.php?section=junior&category=Game+Development" class="btn-main">+ Add Course</a>
            </div>
            <?= renderCourseTable($juniorGame) ?>
          </section>
        </div>

        <div id="junior-python" class="junior-cat-section">
          <section class="panel-card">
            <div class="panel-header">
              <h2 class="panel-title">Python Introduction</h2>
              <a href="add_course.php?section=junior&category=Python+Introduction" class="btn-main">+ Add Course</a>
            </div>
            <?= renderCourseTable($juniorPython) ?>
          </section>
        </div>

        <div id="junior-vm" class="junior-cat-section">
          <section class="panel-card">
            <div class="panel-header">
              <h2 class="panel-title">Virtual Machine</h2>
              <a href="add_course.php?section=junior&category=Virtual+Machine" class="btn-main">+ Add Course</a>
            </div>
            <?= renderCourseTable($juniorVM) ?>
          </section>
        </div>

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
<script>
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
</body>
</html>