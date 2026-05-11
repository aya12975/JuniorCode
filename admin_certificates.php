<?php
session_start();
require_once "db.php";
require_once "admin_prefs.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName   = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);
function isActive($page, $cur) { return $page === $cur ? "active" : ""; }

/* ── Auto-create certificates table ── */
$conn->query("CREATE TABLE IF NOT EXISTS certificates (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(255) NOT NULL,
    course_name  VARCHAR(255) NOT NULL,
    teacher_name VARCHAR(255) NOT NULL,
    issued_date  DATE NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$saved = false;
$error = "";

/* ── Issue certificate ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "issue") {
    $student = trim($_POST["student_name"] ?? "");
    $course  = trim($_POST["course_name"]  ?? "");
    $teacher = trim($_POST["teacher_name"] ?? "");
    $date    = trim($_POST["issued_date"]  ?? date("Y-m-d"));

    if ($student === "" || $course === "" || $teacher === "") {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO certificates (student_name, course_name, teacher_name, issued_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $student, $course, $teacher, $date);
        $stmt->execute();
        $saved = true;
    }
}

/* ── Delete certificate ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $id = (int)($_POST["cert_id"] ?? 0);
    $conn->prepare("DELETE FROM certificates WHERE id = ?")->bind_param("i", $id);
    $del = $conn->prepare("DELETE FROM certificates WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    header("Location: admin_certificates.php");
    exit();
}

/* ── Load all students ── */
$students = [];
$r = $conn->query("SELECT username FROM users WHERE role='student' ORDER BY username");
if ($r) while ($row = $r->fetch_assoc()) $students[] = $row["username"];

/* ── Load all teachers ── */
$teachers = [];
$r = $conn->query("SELECT username FROM users WHERE role='teacher' ORDER BY username");
if ($r) while ($row = $r->fetch_assoc()) $teachers[] = $row["username"];

/* ── Load all certificates ── */
$certs = [];
$r = $conn->query("SELECT * FROM certificates ORDER BY issued_date DESC, created_at DESC");
if ($r) while ($row = $r->fetch_assoc()) $certs[] = $row;
?>
<!DOCTYPE html>
<html lang="<?= $adminLang ?>" dir="<?= $adminDir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificates | JuniorCode</title>
<link rel="icon" type="image/png" href="images/robot2.png.png">
<?= darkModeCSS() ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --border:#edf4ff; --shadow:0 18px 45px rgba(37,99,235,0.08); }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,Helvetica,sans-serif; background:radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),linear-gradient(180deg,#f8fbff,#eef6ff); color:var(--dark); }

.app-shell { min-height:100vh; display:flex; }

/* Sidebar */
.sidebar { width:285px; flex-shrink:0; background:linear-gradient(180deg,#0f172a,#172554); color:#fff; padding:0; justify-content:space-between; position:sticky; top:0; height:100vh; overflow-y:auto; display:flex; flex-direction:column; transition:width .3s,padding .3s,min-width .3s; overflow:hidden; }
body.sidebar-collapsed .sidebar { width:0; padding:0; min-width:0; overflow:hidden; }
.sidebar-top-area { padding: 0 18px 18px; flex: 1; }
.brand { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
.brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
.brand-title { font-weight:900; font-size:1.1rem; color:#fff; line-height:1.2; }
.brand-sub   { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
.nav-custom { display:flex; flex-direction:column; gap:4px; }
.sidebar-bottom { padding:16px 18px; border-top:1px solid rgba(255,255,255,0.1); }
.nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all .22s; font-weight:700; }
.nav-link-custom:hover { background:rgba(255,255,255,0.08); color:#fff; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 8px 20px rgba(30,50,100,0.35); }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.nav-link-custom.active .nav-icon { background:rgba(255,255,255,0.18); }

/* Main */
.main-content { flex:1; padding:28px; min-width:0; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background .2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

/* Topbar */
.topbar { background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; padding:22px 26px; margin-bottom:26px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; color:#fff; box-shadow:0 12px 28px rgba(37,99,235,0.3); }
.topbar h1 { font-size:1.7rem; font-weight:900; }
.topbar p  { margin:4px 0 0; opacity:.82; font-size:0.97rem; }
.admin-badge { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.2); border-radius:12px; padding:10px 18px; font-weight:800; font-size:0.92rem; color:#fff; }

/* Cards */
.card { background:#fff; border-radius:22px; border:1px solid var(--border); box-shadow:var(--shadow); padding:28px; margin-bottom:22px; }
.card-title { font-size:1.05rem; font-weight:900; color:var(--primary); margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }

.form-label { font-weight:800; color:#334155; font-size:0.88rem; margin-bottom:5px; display:block; }
.form-hint  { font-size:0.78rem; color:var(--muted); margin-top:4px; }
.form-control, .form-select { border-radius:11px; padding:10px 14px; border:1.5px solid #dbe4f0; font-size:0.92rem; width:100%; outline:none; font-family:inherit; transition:border-color .2s; }
.form-control:focus, .form-select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(62,80,119,0.1); }

.btn-issue { padding:11px 28px; border:none; border-radius:12px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; font-weight:800; font-size:0.93rem; cursor:pointer; transition:opacity .2s; white-space:nowrap; }
.btn-issue:hover { opacity:.88; }

/* Cert grid */
.cert-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.cert-card { background:#fff; border:1px solid var(--border); border-radius:16px; padding:20px; box-shadow:0 4px 14px rgba(37,99,235,0.06); display:flex; flex-direction:column; gap:8px; transition:transform .2s,box-shadow .2s; }
.cert-card:hover { transform:translateY(-2px); box-shadow:0 12px 28px rgba(37,99,235,0.1); }
.cert-badge { width:46px; height:46px; border-radius:12px; background:linear-gradient(135deg,#fbbf24,#d97706); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.2rem; }
.cert-student { font-weight:900; font-size:1rem; color:var(--dark); }
.cert-course  { font-size:0.87rem; color:var(--primary); font-weight:700; }
.cert-meta    { font-size:0.8rem; color:var(--muted); }
.cert-actions { display:flex; gap:8px; margin-top:6px; }
.btn-preview  { flex:1; padding:8px; border:none; border-radius:10px; background:#eff6ff; color:#2563eb; font-weight:700; font-size:0.83rem; cursor:pointer; transition:background .2s; }
.btn-preview:hover { background:#dbeafe; }
.btn-del { padding:8px 14px; border:none; border-radius:10px; background:#fef2f2; color:#dc2626; font-weight:700; font-size:0.83rem; cursor:pointer; transition:background .2s; }
.btn-del:hover { background:#fee2e2; }

.alert-ok  { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; border-radius:12px; padding:12px 16px; font-weight:700; margin-bottom:18px; font-size:0.9rem; }
.alert-err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:12px; padding:12px 16px; font-weight:700; margin-bottom:18px; font-size:0.9rem; }
.empty-box { text-align:center; padding:40px; color:var(--muted); font-weight:700; background:#f8fafc; border-radius:14px; border:1px dashed #cbd5e1; }
</style>
</head>
<body>
<div class="app-shell">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-top-area">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="Logo">
      <div>
        <div class="brand-title">JuniorCode</div>
        <div class="brand-sub"><?= t('admin_panel') ?></div>
      </div>
    </div>
    <div class="nav-title"><?= t('main_label') ?></div>
    <div class="nav-custom">
      <a href="admin_dashboard.php"    class="nav-link-custom <?= isActive('admin_dashboard.php',   $currentPage) ?>"><span class="nav-icon"><i class="fas fa-house"></i></span><span><?= t('nav_dashboard') ?></span></a>
      <a href="manage_users.php"       class="nav-link-custom <?= isActive('manage_users.php',      $currentPage) ?>"><span class="nav-icon"><i class="fas fa-users"></i></span><span><?= t('nav_users') ?></span></a>
      <a href="manage_classes.php"     class="nav-link-custom <?= isActive('manage_classes.php',    $currentPage) ?>"><span class="nav-icon"><i class="fas fa-book"></i></span><span><?= t('nav_classes') ?></span></a>
      <a href="teacher_earnings.php"   class="nav-link-custom <?= isActive('teacher_earnings.php',  $currentPage) ?>"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span><?= t('nav_earnings') ?></span></a>
      <a href="available_slots.php"    class="nav-link-custom <?= isActive('available_slots.php',   $currentPage) ?>"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span><?= t('nav_slots') ?></span></a>
      <a href="courses.php"            class="nav-link-custom <?= isActive('courses.php',           $currentPage) ?>"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span><?= t('nav_courses') ?></span></a>
      <a href="reports.php"            class="nav-link-custom <?= isActive('reports.php',           $currentPage) ?>"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span><?= t('nav_reports') ?></span></a>
      <a href="admin_certificates.php" class="nav-link-custom <?= isActive('admin_certificates.php',$currentPage) ?>"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
      <a href="admin_ai_settings.php"  class="nav-link-custom <?= isActive('admin_ai_settings.php', $currentPage) ?>"><span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span></a>
      <a href="admin_quiz_generator.php" class="nav-link-custom <?= isActive('admin_quiz_generator.php', $currentPage) ?>"><span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>AI Quiz Generator</span></a>
    </div>
    </div>
    <div class="sidebar-bottom">
      <a href="settings.php"           class="nav-link-custom <?= isActive('settings.php',          $currentPage) ?>"><span class="nav-icon"><i class="fas fa-gear"></i></span><span><?= t('nav_settings') ?></span></a>
      <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
      <a href="logout.php"             class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span><?= t('nav_logout') ?></span></a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
    </div>

    <div class="topbar">
      <div>
        <h1><i class="fas fa-award me-2"></i>Certificates</h1>
        <p>Issue and manage course completion certificates for students</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?></div>
    </div>

    <?php if ($saved): ?>
      <div class="alert-ok"><i class="fas fa-check-circle me-2"></i>Certificate issued successfully!</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-err"><i class="fas fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Issue form -->
    <div class="card">
      <div class="card-title"><i class="fas fa-plus-circle"></i> Issue New Certificate</div>
      <form method="POST">
        <input type="hidden" name="action" value="issue">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:14px;align-items:flex-end;">
          <div>
            <label class="form-label">Student</label>
            <select name="student_name" class="form-select" required>
              <option value="">— Select student —</option>
              <?php foreach ($students as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Course / Achievement</label>
            <input type="text" name="course_name" class="form-control" placeholder="e.g. Python Basics" required>
          </div>
          <div>
            <label class="form-label">Teacher / Instructor</label>
            <select name="teacher_name" class="form-select" required>
              <option value="">— Select teacher —</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Date</label>
            <input type="date" name="issued_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div>
            <button type="submit" class="btn-issue"><i class="fas fa-paper-plane me-1"></i> Issue</button>
          </div>
        </div>
      </form>
    </div>

    <!-- All certificates -->
    <div class="card">
      <div class="card-title"><i class="fas fa-list"></i> All Certificates (<?= count($certs) ?>)</div>
      <?php if (empty($certs)): ?>
        <div class="empty-box"><i class="fas fa-award" style="font-size:2rem;margin-bottom:10px;display:block;color:#cbd5e1;"></i>No certificates issued yet.</div>
      <?php else: ?>
        <div class="cert-grid">
          <?php foreach ($certs as $c): ?>
            <div class="cert-card">
              <div class="cert-badge"><i class="fas fa-medal"></i></div>
              <div class="cert-student"><?= htmlspecialchars($c["student_name"]) ?></div>
              <div class="cert-course"><i class="fas fa-book-open me-1"></i><?= htmlspecialchars($c["course_name"]) ?></div>
              <div class="cert-meta"><i class="fas fa-chalkboard-user me-1"></i><?= htmlspecialchars($c["teacher_name"]) ?></div>
              <div class="cert-meta"><i class="fas fa-calendar me-1"></i><?= date("M j, Y", strtotime($c["issued_date"])) ?></div>
              <div class="cert-actions">
                <button class="btn-preview" onclick="previewCert(<?= htmlspecialchars(json_encode($c)) ?>)">
                  <i class="fas fa-eye me-1"></i> Preview
                </button>
                <form method="POST" style="margin:0;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="cert_id" value="<?= $c['id'] ?>">
                  <button type="submit" class="btn-del" onclick="return confirm('Delete this certificate?')"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Preview Modal -->
<div id="certModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,0.7);backdrop-filter:blur(6px);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;padding:24px;max-width:820px;width:95%;box-shadow:0 32px 80px rgba(0,0,0,0.35);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <strong style="font-size:1rem;color:#0f172a;">Certificate Preview</strong>
      <div style="display:flex;gap:10px;">
        <button onclick="downloadCert()" style="padding:9px 20px;border:none;border-radius:10px;background:linear-gradient(135deg,#3e5077,#143674);color:#fff;font-weight:800;cursor:pointer;font-size:0.88rem;"><i class="fas fa-download me-1"></i> Download PDF</button>
        <button onclick="document.getElementById('certModal').style.display='none'" style="padding:9px 14px;border:none;border-radius:10px;background:#f1f5f9;color:#334155;font-weight:800;cursor:pointer;"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <div id="certPreview" style="border-radius:12px;overflow:hidden;"></div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
let currentCert = null;

function buildCertHTML(c) {
  return `
  <div id="certDoc" style="width:800px;background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:48px;font-family:Georgia,serif;color:#fff;box-sizing:border-box;">
    <div style="border:3px solid #f59e0b;border-radius:16px;padding:38px;box-sizing:border-box;">
      <div style="border:1px solid rgba(245,158,11,0.35);border-radius:12px;padding:34px;text-align:center;box-sizing:border-box;">
        <div style="display:flex;justify-content:center;align-items:center;gap:14px;margin-bottom:20px;">
          <div style="width:44px;height:44px;border-radius:50%;background:#f59e0b;display:flex;align-items:center;justify-content:center;"><div style="width:18px;height:18px;background:#fff;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%);"></div></div>
          <div style="width:58px;height:58px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#b45309);display:flex;align-items:center;justify-content:center;"><div style="width:28px;height:28px;background:#fff;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%);"></div></div>
          <div style="width:44px;height:44px;border-radius:50%;background:#f59e0b;display:flex;align-items:center;justify-content:center;"><div style="width:18px;height:18px;background:#fff;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%);"></div></div>
        </div>
        <p style="font-family:Arial,sans-serif;font-size:0.8rem;letter-spacing:5px;color:#f59e0b;text-transform:uppercase;margin:0 0 8px;">JuniorCode Academy</p>
        <h1 style="font-size:2.5rem;font-weight:normal;color:#fff;margin:0 0 20px;letter-spacing:2px;">Certificate of Completion</h1>
        <div style="height:2px;background:linear-gradient(90deg,transparent,#f59e0b,transparent);margin:0 auto 20px;width:120px;"></div>
        <p style="font-size:0.95rem;color:rgba(255,255,255,0.65);margin:0 0 10px;font-family:Arial,sans-serif;">This is to certify that</p>
        <p style="font-size:2.4rem;color:#f59e0b;margin:0 0 14px;font-style:italic;font-weight:normal;">${c.student_name}</p>
        <p style="font-size:0.95rem;color:rgba(255,255,255,0.65);margin:0 0 10px;font-family:Arial,sans-serif;">has successfully completed the course</p>
        <p style="font-size:1.55rem;color:#fff;margin:0 0 22px;font-weight:normal;letter-spacing:1px;">${c.course_name}</p>
        <div style="height:2px;background:linear-gradient(90deg,transparent,#f59e0b,transparent);margin:0 auto 26px;width:120px;"></div>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;">
          <div style="text-align:center;width:180px;">
            <div style="height:1px;background:rgba(255,255,255,0.35);margin-bottom:8px;"></div>
            <p style="font-size:0.82rem;color:rgba(255,255,255,0.7);margin:0;font-family:Arial,sans-serif;">${c.teacher_name}</p>
            <p style="font-size:0.7rem;color:#f59e0b;margin:3px 0 0;font-family:Arial,sans-serif;letter-spacing:2px;">INSTRUCTOR</p>
          </div>
          <div style="text-align:center;">
            <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#b45309);border:3px solid rgba(255,255,255,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto;">
              <div style="width:30px;height:30px;background:#fff;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%);"></div>
            </div>
          </div>
          <div style="text-align:center;width:180px;">
            <div style="height:1px;background:rgba(255,255,255,0.35);margin-bottom:8px;"></div>
            <p style="font-size:0.82rem;color:rgba(255,255,255,0.7);margin:0;font-family:Arial,sans-serif;">${formatDate(c.issued_date)}</p>
            <p style="font-size:0.7rem;color:#f59e0b;margin:3px 0 0;font-family:Arial,sans-serif;letter-spacing:2px;">DATE ISSUED</p>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function formatDate(d) {
  const months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
  const p = d.split('-');
  return months[parseInt(p[1])-1] + ' ' + parseInt(p[2]) + ', ' + p[0];
}

function previewCert(cert) {
  currentCert = cert;
  document.getElementById('certPreview').innerHTML = buildCertHTML(cert);
  document.getElementById('certModal').style.display = 'flex';
}

async function downloadCert() {
  const wrap = document.createElement('div');
  wrap.style.cssText = 'position:fixed;left:-9999px;top:0;z-index:-1;';
  wrap.innerHTML = buildCertHTML(currentCert);
  document.body.appendChild(wrap);
  const el = wrap.querySelector('#certDoc');
  const { jsPDF } = window.jspdf;
  const canvas = await html2canvas(el, { scale:2, useCORS:true, logging:false });
  document.body.removeChild(wrap);
  const img = canvas.toDataURL('image/png');
  const pdf = new jsPDF({ orientation:'landscape', unit:'px', format:[canvas.width/2, canvas.height/2] });
  pdf.addImage(img,'PNG',0,0,canvas.width/2,canvas.height/2);
  pdf.save('Certificate_' + currentCert.student_name + '_' + currentCert.course_name + '.pdf');
}

document.getElementById('certModal').addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });
</script>
<script src="logout-modal.js"></script>
</body>
</html>
