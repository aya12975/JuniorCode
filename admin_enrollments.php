<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";

$conn->query("CREATE TABLE IF NOT EXISTS student_enrollments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(255) NOT NULL,
    course_id    INT          NOT NULL,
    enrolled_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment (student_name, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Enroll ──────────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "enroll") {
    $studentName = trim($_POST["student_name"] ?? "");
    $courseId    = (int)($_POST["course_id"]   ?? 0);
    if ($studentName !== "" && $courseId > 0) {
        $ins = $conn->prepare("INSERT IGNORE INTO student_enrollments (student_name, course_id) VALUES (?, ?)");
        if ($ins) { $ins->bind_param("si", $studentName, $courseId); $ins->execute(); $ins->close(); }
    }
    header("Location: admin_enrollments.php?enrolled=1");
    exit();
}

// ── Remove enrollment ────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "remove") {
    $eid = (int)($_POST["enrollment_id"] ?? 0);
    if ($eid > 0) {
        $del = $conn->prepare("DELETE FROM student_enrollments WHERE id = ?");
        if ($del) { $del->bind_param("i", $eid); $del->execute(); $del->close(); }
    }
    header("Location: admin_enrollments.php?removed=1");
    exit();
}

// ── Data ─────────────────────────────────────────────────────────────────────
$students = [];
$sRes = $conn->query("SELECT username FROM users WHERE role='student' ORDER BY username ASC");
if ($sRes) while ($r = $sRes->fetch_assoc()) $students[] = $r["username"];

$courses = [];
$cRes = $conn->query("SELECT id, course_name, section, category FROM courses ORDER BY section ASC, category ASC, course_name ASC");
if ($cRes) while ($r = $cRes->fetch_assoc()) $courses[] = $r;

$enrollments = [];
$eRes = $conn->query("
    SELECT e.id, e.student_name, e.enrolled_at, c.course_name, c.section, c.category
    FROM student_enrollments e
    JOIN courses c ON c.id = e.course_id
    ORDER BY e.enrolled_at DESC
");
if ($eRes) while ($r = $eRes->fetch_assoc()) $enrollments[] = $r;

// group courses for select
$grouped = [];
foreach ($courses as $c) {
    $grouped[$c["section"]][] = $c;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Enrollments | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:Arial,Helvetica,sans-serif; color:var(--dark);
      background: radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),
                  radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),
                  linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); }
    .app-shell { min-height:100vh; display:flex; }

    /* Sidebar */
    .sidebar { width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:#fff; padding:0; position:sticky; top:0; height:100vh; overflow-y:auto; display:flex; flex-direction:column; transition:width .3s,padding .3s,min-width .3s; }
    body.sidebar-collapsed .sidebar { width:0; min-width:0; overflow:hidden; }
    .sidebar-top-area { padding:0 18px 18px; }
    .brand { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
    .brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
    .brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
    .brand-sub { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
    .nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
    .nav-custom { display:flex; flex-direction:column; gap:4px; }
    .nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all .25s; font-weight:700; }
    .nav-link-custom:hover { background:rgba(255,255,255,0.08); color:#fff; }
    .nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 10px 24px rgba(37,99,235,0.28); }
    .nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .sidebar-bottom { padding:16px 18px; margin-top:auto; }

    /* Main */
    .main-content { flex:1; padding:28px; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

    .topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:28px; padding:20px 24px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:24px; box-shadow:0 16px 40px rgba(62,80,119,0.3); }
    .topbar h1 { font-size:1.8rem; font-weight:900; margin:0; color:#fff; }
    .topbar p  { margin:4px 0 0; color:rgba(255,255,255,0.8); }
    .admin-badge { background:rgba(255,255,255,0.2); color:#fff; border-radius:12px; border:1px solid rgba(255,255,255,0.25); padding:10px 18px; font-weight:800; white-space:nowrap; }

    .card-box { background:#fff; border:1.5px solid #dbeafe; border-radius:20px; padding:28px; margin-bottom:24px; box-shadow:0 4px 18px rgba(62,80,119,0.07); }
    .card-title { font-size:1.05rem; font-weight:900; color:#1e3a5f; margin-bottom:22px; display:flex; align-items:center; gap:10px; }
    .card-title .ti { width:34px; height:34px; border-radius:9px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; }

    .field-label { font-size:0.82rem; font-weight:800; color:#64748b; margin-bottom:6px; display:block; }
    .form-control, .form-select { border-radius:12px; padding:13px 16px; border:1.5px solid #e2e8f0; font-size:0.95rem; width:100%; transition:border .2s; }
    .form-control:focus, .form-select:focus { border-color:var(--primary); outline:none; box-shadow:0 0 0 3px rgba(62,80,119,0.12); }

    .enroll-grid { display:grid; grid-template-columns:1fr 1fr auto; gap:16px; align-items:end; }
    @media(max-width:700px) { .enroll-grid { grid-template-columns:1fr; } }

    .btn-enroll { background:linear-gradient(135deg,var(--primary),var(--secondary)); border:none; color:#fff; font-weight:900; border-radius:12px; padding:13px 28px; cursor:pointer; font-size:0.95rem; white-space:nowrap; box-shadow:0 6px 18px rgba(62,80,119,0.3); transition:opacity .2s,transform .15s; display:inline-flex; align-items:center; gap:8px; }
    .btn-enroll:hover { opacity:.92; transform:translateY(-1px); }

    /* Enrollment list */
    .enroll-item { display:flex; align-items:center; gap:14px; background:#f8fbff; border:1.5px solid #dbeafe; border-radius:14px; padding:14px 18px; margin-bottom:8px; }
    .enroll-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; font-weight:900; font-size:0.95rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .enroll-body { flex:1; min-width:0; }
    .enroll-student { font-weight:900; font-size:0.95rem; color:#0f172a; }
    .enroll-course  { font-size:0.82rem; color:var(--muted); margin-top:3px; }
    .chip { font-size:0.72rem; font-weight:700; padding:2px 9px; border-radius:999px; }
    .chip-kids   { background:#fef3c7; color:#92400e; }
    .chip-junior { background:#dbeafe; color:#1d4ed8; }
    .enroll-date { font-size:0.78rem; color:#94a3b8; font-weight:600; white-space:nowrap; }
    .btn-remove { background:#fee2e2; color:#dc2626; border:none; border-radius:9px; width:32px; height:32px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.82rem; flex-shrink:0; transition:background .15s; }
    .btn-remove:hover { background:#fecaca; }

    .search-box { position:relative; margin-bottom:16px; }
    .search-box input { padding-left:40px; }
    .search-box .si { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94a3b8; }

    .stat-bar { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
    .stat-box { background:#fff; border:1px solid #dbeafe; border-radius:16px; padding:16px 18px; text-align:center; box-shadow:0 4px 14px rgba(62,80,119,0.06); }
    .stat-num  { font-size:1.7rem; font-weight:900; color:var(--primary); }
    .stat-lbl  { font-size:0.78rem; color:var(--muted); font-weight:700; margin-top:2px; }

    .empty-box { text-align:center; padding:36px; background:#f8fbff; border:1.5px dashed #dbeafe; border-radius:14px; color:var(--muted); font-weight:700; }

    @media(max-width:991px) { .app-shell{flex-direction:column;} .sidebar{width:100%;height:auto;position:relative;} .stat-bar{grid-template-columns:1fr 1fr;} }
    @media(max-width:500px)  { .stat-bar{grid-template-columns:1fr;} }
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
        <a href="admin_enrollments.php"      class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span></a>
        <a href="manage_classes.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
        <a href="teacher_earnings.php"       class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
        <a href="available_slots.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
        <a href="courses_home.php"           class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
        <a href="manage_projects.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-folder-open"></i></span><span>Projects</span></a>
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
        <h1><i class="fas fa-graduation-cap me-2"></i>Enrollments</h1>
        <p>Unlock courses for students by entering their name and selecting a course.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <?php if (isset($_GET["enrolled"])): ?>
      <div class="alert alert-success mb-3"><i class="fas fa-circle-check me-2"></i>Student enrolled successfully — course unlocked.</div>
    <?php endif; ?>
    <?php if (isset($_GET["removed"])): ?>
      <div class="alert alert-warning mb-3"><i class="fas fa-circle-minus me-2"></i>Enrollment removed.</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stat-bar">
      <div class="stat-box">
        <div class="stat-num"><?= count($enrollments) ?></div>
        <div class="stat-lbl">Total Enrollments</div>
      </div>
      <div class="stat-box">
        <div class="stat-num"><?= count(array_unique(array_column($enrollments, 'student_name'))) ?></div>
        <div class="stat-lbl">Enrolled Students</div>
      </div>
      <div class="stat-box">
        <div class="stat-num"><?= count($courses) ?></div>
        <div class="stat-lbl">Available Courses</div>
      </div>
    </div>

    <!-- Enroll form -->
    <div class="card-box">
      <div class="card-title">
        <span class="ti"><i class="fas fa-user-plus"></i></span>
        Unlock a Course for a Student
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="enroll">
        <div class="enroll-grid">
          <div>
            <label class="field-label">Student Name <span style="color:#dc2626;">*</span></label>
            <input type="text" name="student_name" class="form-control" placeholder="Type student name…"
              list="students-list" required autocomplete="off">
            <datalist id="students-list">
              <?php foreach ($students as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="field-label">Course to Unlock <span style="color:#dc2626;">*</span></label>
            <select name="course_id" class="form-select" required>
              <option value="">— Select a course —</option>
              <?php foreach ($grouped as $section => $sectionCourses): ?>
                <optgroup label="<?= ucfirst($section) ?>">
                  <?php foreach ($sectionCourses as $c): ?>
                    <option value="<?= $c['id'] ?>">
                      <?= htmlspecialchars($c['course_name']) ?>
                      <?= $c['category'] ? ' (' . htmlspecialchars($c['category']) . ')' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <button type="submit" class="btn-enroll"><i class="fas fa-lock-open"></i> Unlock</button>
          </div>
        </div>
      </form>
    </div>

    <!-- Enrollment list -->
    <div class="card-box">
      <div class="card-title">
        <span class="ti"><i class="fas fa-list-check"></i></span>
        Current Enrollments
        <span style="font-size:0.82rem;font-weight:600;color:var(--muted);margin-left:4px;">(<?= count($enrollments) ?>)</span>
      </div>

      <?php if (empty($enrollments)): ?>
        <div class="empty-box">
          <i class="fas fa-graduation-cap" style="font-size:2rem;color:#dbeafe;display:block;margin-bottom:10px;"></i>
          No enrollments yet. Use the form above to unlock a course for a student.
        </div>
      <?php else: ?>
        <div class="search-box">
          <i class="fas fa-search si"></i>
          <input type="text" class="form-control" id="enrollSearch" placeholder="Search by student or course…" oninput="filterEnrollments(this.value)">
        </div>
        <div id="enrollList">
          <?php foreach ($enrollments as $e): ?>
            <div class="enroll-item" data-search="<?= strtolower(htmlspecialchars($e['student_name'] . ' ' . $e['course_name'])) ?>">
              <div class="enroll-avatar"><?= strtoupper(substr($e['student_name'], 0, 1)) ?></div>
              <div class="enroll-body">
                <div class="enroll-student"><?= htmlspecialchars($e['student_name']) ?></div>
                <div class="enroll-course">
                  <span class="chip chip-<?= $e['section'] ?>"><?= ucfirst($e['section']) ?></span>
                  &nbsp;<?= htmlspecialchars($e['course_name']) ?>
                  <?= $e['category'] ? '<span style="color:#94a3b8;"> · ' . htmlspecialchars($e['category']) . '</span>' : '' ?>
                </div>
              </div>
              <div class="enroll-date"><?= date("d M Y", strtotime($e['enrolled_at'])) ?></div>
              <form method="POST" style="margin:0;">
                <input type="hidden" name="action"        value="remove">
                <input type="hidden" name="enrollment_id" value="<?= $e['id'] ?>">
                <button type="button" class="btn-remove" title="Remove enrollment"
                  onclick="confirmRemove(this, <?= htmlspecialchars(json_encode($e['student_name'])) ?>, <?= htmlspecialchars(json_encode($e['course_name'])) ?>)">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Remove confirmation modal -->
<div id="remove-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:22px;padding:36px;max-width:400px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,0.18);text-align:center;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
      <i class="fas fa-trash" style="font-size:1.5rem;color:#dc2626;"></i>
    </div>
    <div style="font-size:1.1rem;font-weight:900;margin-bottom:8px;">Remove Enrollment?</div>
    <p id="remove-msg" style="color:#64748b;font-size:0.92rem;margin-bottom:24px;"></p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button id="remove-confirm-btn" type="button" onclick="submitRemove()"
        style="background:linear-gradient(135deg,#dc2626,#b91c1c);border:none;color:#fff;font-weight:800;border-radius:12px;padding:12px 28px;cursor:pointer;">
        <i class="fas fa-trash me-1"></i> Yes, Remove
      </button>
      <button type="button" onclick="closeRemoveModal()"
        style="background:#64748b;color:#fff;border:none;border-radius:12px;padding:12px 24px;font-weight:800;cursor:pointer;">
        Cancel
      </button>
    </div>
  </div>
</div>

<script>
let pendingRemoveForm = null;

function confirmRemove(btn, student, course) {
  pendingRemoveForm = btn.closest('form');
  document.getElementById('remove-msg').textContent =
    'Remove "' + student + '" from "' + course + '"? They will lose access to this course.';
  document.getElementById('remove-modal').style.display = 'flex';
}
function submitRemove() {
  if (pendingRemoveForm) pendingRemoveForm.submit();
}
function closeRemoveModal() {
  document.getElementById('remove-modal').style.display = 'none';
  pendingRemoveForm = null;
}
document.getElementById('remove-modal').addEventListener('click', function(e) {
  if (e.target === this) closeRemoveModal();
});

function filterEnrollments(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#enrollList .enroll-item').forEach(item => {
    item.style.display = item.dataset.search.includes(q) ? '' : 'none';
  });
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>
