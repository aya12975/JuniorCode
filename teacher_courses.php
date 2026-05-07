<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherName = $_SESSION["username"] ?? "Teacher";

// Ensure section column exists
$chk = $conn->query("SHOW COLUMNS FROM courses LIKE 'section'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE courses ADD COLUMN section VARCHAR(50) NOT NULL DEFAULT 'kids'");
}

function fetchCoursesByCategory($conn, $section, $category = null) {
    if ($category) {
        $sql = "SELECT * FROM courses WHERE section = ? AND category = ? ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("ss", $section, $category);
    } else {
        $sql = "SELECT * FROM courses WHERE section = ? ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("s", $section);
    }
    $stmt->execute();
    return $stmt->get_result();
}

$kidsGame    = fetchCoursesByCategory($conn, 'kids',   'Game Development');
$kidsPython  = fetchCoursesByCategory($conn, 'kids',   'Python Introduction');
$kidsVM      = fetchCoursesByCategory($conn, 'kids',   'Virtual Machine');
$juniorGame  = fetchCoursesByCategory($conn, 'junior', 'Game Development');
$juniorPython= fetchCoursesByCategory($conn, 'junior', 'Python Introduction');
$juniorVM    = fetchCoursesByCategory($conn, 'junior', 'Virtual Machine');
$demoResult  = fetchCoursesByCategory($conn, 'demo');

function renderReadOnlyTable($result) {
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
            <th>Status</th><th>Duration</th>
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
            <td><strong><?= htmlspecialchars($course["course_name"]) ?></strong></td>
            <td><?= htmlspecialchars($course["category"]) ?></td>
            <td><?= htmlspecialchars($course["age_group"]) ?></td>
            <td><?= htmlspecialchars($course["level"]) ?></td>
            <td>$<?= number_format((float)$course["price"], 2) ?></td>
            <td><span class="type-badge <?= $course["course_type"] === "demo" ? "type-demo" : "type-paid" ?>"><?= ucfirst(htmlspecialchars($course["course_type"])) ?></span></td>
            <td><span class="status-badge <?= $course["status"] === "active" ? "status-active" : "status-inactive" ?>"><?= ucfirst(htmlspecialchars($course["status"])) ?></span></td>
            <td><?= htmlspecialchars($course["duration"]) ?></td>
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Courses | Teacher</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:   #3e5077;
      --secondary: #143674;
      --dark:      #0f172a;
      --muted:     #64748b;
      --shadow:    0 18px 45px rgba(37,99,235,0.08);
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      color: var(--dark);
      background:
        radial-gradient(circle at top left,  rgba(29,78,216,0.07), transparent 25%),
        radial-gradient(circle at bottom right, rgba(14,165,233,0.07), transparent 25%),
        linear-gradient(180deg, #f8fbff 0%, #eaf4ff 100%);
    }

    .sidebar {
      position: fixed;
      top: 0; left: 0;
      width: 260px;
      height: 100vh;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      z-index: 1000;
      overflow-y: auto;
    }

    .sidebar-top { padding: 20px 16px; }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 10px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 16px;
    }

    .brand-logo-img {
      width: 55px; height: 55px;
      border-radius: 0;
      object-fit: contain;
      background: none;
      padding: 0;
      flex-shrink: 0;
    }

    .brand-title { font-size: 1.05rem; font-weight: 900; margin: 0; color: #ffffff; line-height: 1.2; }
    .brand-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.55); margin: 3px 0 0; letter-spacing: 1px; }

    .teacher-box {
      display: flex; align-items: center; gap: 12px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 16px; padding: 14px; margin-bottom: 18px;
    }

    .teacher-avatar {
      width: 44px; height: 44px; border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white; font-weight: bold;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; flex-shrink: 0;
    }

    .teacher-name { font-weight: 800; margin: 0; color: #ffffff; }
    .teacher-role { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }

    .nav-link-custom {
      display: flex; align-items: center; gap: 12px;
      text-decoration: none; color: rgba(255,255,255,0.78);
      padding: 12px 14px; border-radius: 14px; margin: 4px 0;
      font-weight: 700; transition: all 0.22s ease;
    }

    .nav-link-custom:hover { background: rgba(255,255,255,0.09); color: #ffffff; }

    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #ffffff;
      box-shadow: 0 8px 20px rgba(30,50,100,0.35);
    }

    .nav-icon {
      width: 32px; height: 32px; border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0;
    }

    .nav-link-custom.active .nav-icon { background: rgba(255,255,255,0.18); }

    .sidebar-bottom { padding: 16px; border-top: 1px solid rgba(255,255,255,0.1); }

    .main { margin-left: 260px; padding: 26px; min-height: 100vh; }

    .hero {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white; border-radius: 22px; padding: 18px 20px;
      margin-bottom: 22px; box-shadow: 0 12px 28px rgba(37,99,235,0.3);
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 16px;
    }

    .hero h2 { margin: 0; font-size: 1.5rem; font-weight: 900; }
    .hero p  { margin: 4px 0 0; color: rgba(255,255,255,0.8); }

    .panel-card {
      background: white;
      border: 1px solid #edf4ff;
      border-radius: 22px;
      padding: 22px;
      margin-bottom: 22px;
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
    }

    .panel-card::before {
      content: '';
      display: block;
      height: 5px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      position: absolute;
      top: 0; left: 0; right: 0;
      border-radius: 22px 22px 0 0;
    }

    .panel-title {
      font-size: 1.1rem;
      font-weight: 900;
      color: var(--primary);
      margin-bottom: 14px;
    }

    .panel-header {
      display: flex; justify-content: space-between;
      align-items: center; gap: 12px;
      margin-bottom: 18px; flex-wrap: wrap;
    }

    .panel-header .panel-title { margin-bottom: 0; }

    .tab-bar { display: flex; gap: 10px; margin-bottom: 20px; }

    .tab-btn {
      padding: 12px 28px; border-radius: 14px;
      border: 2px solid #e2e8f0; font-weight: 800; font-size: 1rem;
      cursor: pointer; transition: all 0.2s;
      background: white; color: var(--muted);
    }

    .tab-btn:hover { border-color: var(--primary); color: var(--primary); }

    .tab-btn.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white; border-color: transparent;
      box-shadow: 0 6px 18px rgba(62,80,119,0.25);
    }

    .tab-section { display: none; }
    .tab-section.active { display: block; }

    .kids-cat-section   { display: none; }
    .kids-cat-section.active   { display: block; }
    .junior-cat-section { display: none; }
    .junior-cat-section.active { display: block; }

    .kids-drop-item {
      display: block; width: 100%; padding: 13px 18px;
      border: none; background: none; text-align: left;
      font-weight: 700; font-size: 0.95rem; color: #0f172a;
      cursor: pointer; transition: background 0.15s;
      border-bottom: 1px solid #f1f5f9;
    }
    .kids-drop-item:last-child { border-bottom: none; }
    .kids-drop-item:hover  { background: #f0f7ff; color: var(--primary); }
    .kids-drop-item.active { background: #eff6ff; color: var(--primary); font-weight: 900; }

    .grade-group { margin-bottom: 24px; }

    .grade-group-label {
      font-size: 1rem; font-weight: 900; color: #0f172a;
      display: block; margin-bottom: 12px;
    }

    .grade-cards { display: flex; gap: 14px; flex-wrap: wrap; }

    .grade-card {
      background: #f8fbff; border: 1px solid #dbeafe;
      border-radius: 16px; padding: 18px 22px;
      min-width: 180px; display: flex; flex-direction: column; gap: 10px;
    }

    .grade-link {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 0.85rem; font-weight: 700; color: #2563eb;
      text-decoration: none; background: #dbeafe;
      padding: 6px 12px; border-radius: 8px; transition: background 0.2s;
    }
    .grade-link:hover { background: #bfdbfe; color: #1d4ed8; }

    .table thead th {
      background: #f8fbff; color: var(--dark);
      font-weight: 800; font-size: 0.9rem;
      border-bottom: 1px solid #e6eefb;
    }

    .course-img {
      width: 54px; height: 54px; object-fit: cover;
      border-radius: 12px; border: 1px solid #e5edf9; background: #f8fbff;
    }

    .type-badge, .status-badge {
      display: inline-block; padding: 6px 12px;
      border-radius: 999px; font-size: 0.82rem; font-weight: 800;
    }
    .type-demo   { background: #dbeafe; color: #1d4ed8; }
    .type-paid   { background: #dcfce7; color: #166534; }
    .status-active   { background: #dcfce7; color: #166534; }
    .status-inactive { background: #fee2e2; color: #991b1b; }

    .btn-module {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border: none; color: white; font-weight: 800;
      border-radius: 14px; padding: 10px 16px;
      cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
    }

    .empty-box {
      text-align: center; padding: 26px 18px;
      border-radius: 18px; background: #f8fbff;
      color: var(--muted); border: 1px dashed #d9e9ff; font-weight: 700;
    }

    @media (max-width: 991px) {
      .sidebar { position: static; width: 100%; height: auto; }
      .main    { margin-left: 0; }
    }
  </style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">TEACHER PORTAL</p>
      </div>
    </div>

    <div class="teacher-box">
      <div class="teacher-avatar"><?php echo strtoupper(substr($teacherName, 0, 1)); ?></div>
      <div>
        <p class="teacher-name"><?php echo htmlspecialchars($teacherName); ?></p>
        <p class="teacher-role">Teacher</p>
      </div>
    </div>

    <a href="teacher_dashboard.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-house"></i></span>
      <span>Dashboard</span>
    </a>

    <a href="teacher_classes.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span>
      <span>My Classes</span>
    </a>

    <a href="teacher_schedule.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
      <span>My Schedule</span>
    </a>

    <a href="teacher_monthly_earnings.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
      <span>My Earnings</span>
    </a>

    <a href="teacher_students.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-user-graduate"></i></span>
      <span>My Students</span>
    </a>

    <a href="teacher_courses.php" class="nav-link-custom active">
      <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
      <span>Courses</span>
    </a>
  </div>

  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
      <span>Logout</span>
    </a>
  </div>
</div>

<div class="main">
  <div class="hero">
    <div>
      <h2>Courses</h2>
      <p>Browse all academy courses and programs</p>
    </div>
    <div style="background:rgba(255,255,255,0.15);color:white;border-radius:999px;padding:10px 16px;font-weight:800;">
      <?php echo htmlspecialchars($teacherName); ?>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('kids',   this)">Kids</button>
    <button class="tab-btn"        onclick="switchTab('junior', this)">Junior</button>
    <button class="tab-btn"        onclick="switchTab('demo',   this)">Demo</button>
  </div>

  <!-- ── KIDS ── -->
  <div id="tab-kids" class="tab-section active">
    <div style="position:relative;display:inline-block;margin-bottom:16px;">
      <button class="btn-module" onclick="toggleMenu('kids', event)" type="button">
        Select Kids Module <i class="fas fa-chevron-down ms-2" id="kids-chevron"></i>
      </button>
      <div id="kids-dropdown" style="
        display:none; position:absolute; top:calc(100% + 8px); left:0;
        background:white; border:1px solid #dbeafe; border-radius:16px;
        box-shadow:0 12px 32px rgba(15,23,42,0.12); min-width:220px; z-index:100; overflow:hidden;">
        <button class="kids-drop-item active" onclick="switchCat('kids','game',  this)" type="button">Game Development</button>
        <button class="kids-drop-item"        onclick="switchCat('kids','python',this)" type="button">Python Introduction</button>
        <button class="kids-drop-item"        onclick="switchCat('kids','vm',    this)" type="button">Virtual Machine</button>
      </div>
    </div>

    <div id="kids-game" class="kids-cat-section active">
      <div class="panel-card">
        <div class="panel-header">
          <div class="panel-title">Kids — Game Development</div>
        </div>
        <?= renderReadOnlyTable($kidsGame) ?>
      </div>
    </div>
    <div id="kids-python" class="kids-cat-section">
      <div class="panel-card">
        <div class="panel-header">
          <div class="panel-title">Kids — Python Introduction</div>
        </div>
        <?= renderReadOnlyTable($kidsPython) ?>
      </div>
    </div>
    <div id="kids-vm" class="kids-cat-section">
      <div class="panel-card">
        <div class="panel-header">
          <div class="panel-title">Kids — Virtual Machine</div>
        </div>
        <?= renderReadOnlyTable($kidsVM) ?>
      </div>
    </div>
  </div>

  <!-- ── JUNIOR ── -->
  <div id="tab-junior" class="tab-section">
    <div style="position:relative;display:inline-block;margin-bottom:16px;">
      <button class="btn-module" onclick="toggleMenu('junior', event)" type="button">
        Select Junior Module <i class="fas fa-chevron-down ms-2" id="junior-chevron"></i>
      </button>
      <div id="junior-dropdown" style="
        display:none; position:absolute; top:calc(100% + 8px); left:0;
        background:white; border:1px solid #dbeafe; border-radius:16px;
        box-shadow:0 12px 32px rgba(15,23,42,0.12); min-width:220px; z-index:100; overflow:hidden;">
        <button class="kids-drop-item active" onclick="switchCat('junior','game',  this)" type="button">Game Development</button>
        <button class="kids-drop-item"        onclick="switchCat('junior','python',this)" type="button">Python Introduction</button>
        <button class="kids-drop-item"        onclick="switchCat('junior','vm',    this)" type="button">Virtual Machine</button>
      </div>
    </div>

    <div id="junior-game" class="junior-cat-section active">
      <div class="panel-card">
        <div class="panel-header">
          <div class="panel-title">Junior — Game Development</div>
        </div>
        <?= renderReadOnlyTable($juniorGame) ?>
      </div>
    </div>
    <div id="junior-python" class="junior-cat-section">
      <div class="panel-card">
        <div class="panel-header">
          <div class="panel-title">Junior — Python Introduction</div>
        </div>
        <?= renderReadOnlyTable($juniorPython) ?>
      </div>
    </div>
    <div id="junior-vm" class="junior-cat-section">
      <div class="panel-card">
        <div class="panel-header">
          <div class="panel-title">Junior — Virtual Machine</div>
        </div>
        <?= renderReadOnlyTable($juniorVM) ?>
      </div>
    </div>
  </div>

  <!-- ── DEMO ── -->
  <div id="tab-demo" class="tab-section">
    <div class="panel-card">
      <div class="panel-title">Demo Courses</div>

      <div class="grade-group">
        <span class="grade-group-label">Little <span style="font-weight:400;color:#64748b;">Grade 1 – Grade 3</span></span>
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

      <div class="grade-group">
        <span class="grade-group-label">Junior</span>
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

      <?= renderReadOnlyTable($demoResult) ?>
    </div>
  </div>
</div>

<script>
const tabBtns = document.querySelectorAll('.tab-btn');

function switchTab(name, btn) {
  document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
  tabBtns.forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

function toggleMenu(prefix, e) {
  e.stopPropagation();
  const d = document.getElementById(prefix + '-dropdown');
  const open = d.style.display === 'block';
  d.style.display = open ? 'none' : 'block';
  document.getElementById(prefix + '-chevron').className =
    (open ? 'fas fa-chevron-down' : 'fas fa-chevron-up') + ' ms-2';
}

function switchCat(prefix, cat, btn) {
  document.querySelectorAll('.' + prefix + '-cat-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('#' + prefix + '-dropdown .kids-drop-item').forEach(b => b.classList.remove('active'));
  document.getElementById(prefix + '-' + cat).classList.add('active');
  btn.classList.add('active');
  document.getElementById(prefix + '-dropdown').style.display = 'none';
  document.getElementById(prefix + '-chevron').className = 'fas fa-chevron-down ms-2';
}

document.addEventListener('click', function() {
  ['kids','junior'].forEach(p => {
    const d = document.getElementById(p + '-dropdown');
    if (d) d.style.display = 'none';
    const c = document.getElementById(p + '-chevron');
    if (c) c.className = 'fas fa-chevron-down ms-2';
  });
});
</script>
</body>
</html>
