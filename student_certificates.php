<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";

/* ── Load this student's certificates ── */
$certs = [];
if ($conn->query("SHOW TABLES LIKE 'certificates'")->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM certificates WHERE student_name = ? ORDER BY issued_date DESC");
    $stmt->bind_param("s", $studentName);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $certs[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Certificates | JuniorCode</title>
<link rel="icon" type="image/png" href="images/robot2.png.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --border:#edf4ff; --shadow:0 18px 45px rgba(37,99,235,0.08); }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,Helvetica,sans-serif; color:var(--dark); background:radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),linear-gradient(180deg,#f8fbff,#eef6ff); min-height:100vh; }

/* ── Sidebar ── */
.sidebar { position:fixed; top:0; left:0; width:255px; height:100vh; background:linear-gradient(180deg,#0f172a,#172554); display:flex; flex-direction:column; justify-content:space-between; z-index:1000; overflow-y:auto; transition:transform .3s; }
body.sidebar-collapsed .sidebar { transform:translateX(-255px); }
.sidebar-top { padding:20px 16px; }
.brand { display:flex; align-items:center; gap:12px; padding:10px 10px 18px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:16px; }
.brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
.brand-title    { font-size:1.05rem; font-weight:900; margin:0; color:#fff; }
.brand-subtitle { font-size:0.75rem; color:rgba(255,255,255,0.55); margin:3px 0 0; letter-spacing:1px; }
.student-box { display:flex; align-items:center; gap:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:14px; margin-bottom:18px; }
.student-avatar { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; font-weight:bold; font-size:18px; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; }
.student-avatar img { width:100%; height:100%; object-fit:cover; }
.student-name { font-weight:800; margin:0; color:#fff; }
.student-role { margin:0; color:rgba(255,255,255,0.55); font-size:0.85rem; }
.nav-link-custom { display:flex; align-items:center; gap:12px; text-decoration:none; color:rgba(255,255,255,0.78); padding:12px 14px; border-radius:14px; margin:4px 0; font-weight:700; transition:all .22s; }
.nav-link-custom:hover { background:rgba(255,255,255,0.09); color:#fff; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 8px 20px rgba(30,50,100,0.35); }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.nav-link-custom.active .nav-icon { background:rgba(255,255,255,0.18); }
.sidebar-bottom { padding:16px; }

/* ── Main ── */
.main { margin-left:255px; padding:26px; transition:margin-left .3s; min-height:100vh; }
body.sidebar-collapsed .main { margin-left:0; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

/* ── Topbar ── */
.topbar { background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:20px; padding:22px 26px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; gap:14px; color:#fff; box-shadow:0 12px 28px rgba(37,99,235,0.28); flex-wrap:wrap; }
.topbar h1 { font-size:1.6rem; font-weight:900; }
.topbar p  { margin:4px 0 0; opacity:.8; font-size:0.93rem; }

/* ── Cert grid ── */
.cert-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:18px; }
.cert-card {
  background:#fff; border:1px solid var(--border); border-radius:18px;
  padding:22px; box-shadow:var(--shadow);
  display:flex; flex-direction:column; gap:10px;
  transition:transform .2s, box-shadow .2s;
}
.cert-card:hover { transform:translateY(-3px); box-shadow:0 24px 50px rgba(37,99,235,0.12); }
.cert-badge { width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg,#fbbf24,#d97706); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.4rem; }
.cert-course  { font-weight:900; font-size:1.05rem; color:var(--dark); }
.cert-teacher { font-size:0.85rem; color:var(--primary); font-weight:700; }
.cert-date    { font-size:0.8rem; color:var(--muted); }
.btn-download { width:100%; padding:10px; border:none; border-radius:11px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; font-weight:800; font-size:0.88rem; cursor:pointer; transition:opacity .2s; margin-top:4px; }
.btn-download:hover { opacity:.88; }
.btn-preview  { width:100%; padding:10px; border:1.5px solid var(--border); border-radius:11px; background:#fff; color:var(--primary); font-weight:800; font-size:0.88rem; cursor:pointer; transition:background .2s; }
.btn-preview:hover { background:#eff6ff; }

.empty-box { text-align:center; padding:60px 20px; color:var(--muted); background:#fff; border-radius:20px; border:1px solid var(--border); box-shadow:var(--shadow); }
.empty-box i { font-size:3rem; margin-bottom:14px; display:block; color:#cbd5e1; }
</style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="Logo">
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">STUDENT PANEL</p>
      </div>
    </div>
    <div class="student-box">
      <div class="student-avatar">
        <?php if (!empty($_SESSION["profile_picture"])): ?>
          <img src="uploads/profiles/<?= htmlspecialchars($_SESSION["profile_picture"]) ?>" alt="Profile">
        <?php else: ?>
          <?= strtoupper(substr($studentName,0,1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <p class="student-name"><?= htmlspecialchars($studentName) ?></p>
        <p class="student-role">Student</p>
      </div>
    </div>
    <a href="student_dashboard.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
    <a href="student_courses.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>My Courses</span>
    </a>
    <a href="student_classes.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span></a>
    <a href="student_assignments.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span></a>
    <a href="student_quizzes.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span></a>
    <a href="student_certificates.php" class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
    <a href="student_chat.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span></a>
  </div>
  <div class="sidebar-bottom">
    <a href="student_profile.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
  </div>
</div>

<!-- ── MAIN ── -->
<div class="main">
  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
  </div>

  <div class="topbar">
    <div>
      <h1><i class="fas fa-award me-2"></i>My Certificates</h1>
      <p>Your course completion certificates — download anytime</p>
    </div>
    <div style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2);border-radius:12px;padding:10px 18px;font-weight:800;font-size:0.92rem;">
      <i class="fas fa-medal me-1"></i> <?= count($certs) ?> certificate<?= count($certs) !== 1 ? 's' : '' ?>
    </div>
  </div>

  <?php if (empty($certs)): ?>
    <div class="empty-box">
      <i class="fas fa-award"></i>
      <p style="font-size:1.1rem;font-weight:800;color:#334155;margin-bottom:6px;">No certificates yet</p>
      <p style="font-size:0.9rem;">Complete a course and your teacher will issue you a certificate here.</p>
    </div>
  <?php else: ?>
    <div class="cert-grid">
      <?php foreach ($certs as $c): ?>
        <div class="cert-card">
          <div class="cert-badge"><i class="fas fa-medal"></i></div>
          <div class="cert-course"><?= htmlspecialchars($c["course_name"]) ?></div>
          <div class="cert-teacher"><i class="fas fa-chalkboard-user me-1"></i><?= htmlspecialchars($c["teacher_name"]) ?></div>
          <div class="cert-date"><i class="fas fa-calendar me-1"></i><?= date("F j, Y", strtotime($c["issued_date"])) ?></div>
          <button class="btn-preview" onclick="previewCert(<?= htmlspecialchars(json_encode($c)) ?>)">
            <i class="fas fa-eye me-1"></i> Preview
          </button>
          <button class="btn-download" onclick="downloadCert(<?= htmlspecialchars(json_encode($c)) ?>)">
            <i class="fas fa-download me-1"></i> Download PDF
          </button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ── Preview Modal ── -->
<div id="certModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,0.7);backdrop-filter:blur(6px);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;padding:24px;max-width:820px;width:95%;box-shadow:0 32px 80px rgba(0,0,0,0.35);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <strong style="font-size:1rem;color:#0f172a;">Certificate Preview</strong>
      <div style="display:flex;gap:10px;">
        <button id="modalDownloadBtn" style="padding:9px 20px;border:none;border-radius:10px;background:linear-gradient(135deg,#3e5077,#143674);color:#fff;font-weight:800;cursor:pointer;font-size:0.88rem;"><i class="fas fa-download me-1"></i> Download PDF</button>
        <button onclick="document.getElementById('certModal').style.display='none'" style="padding:9px 14px;border:none;border-radius:10px;background:#f1f5f9;color:#334155;font-weight:800;cursor:pointer;"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <div id="certPreview" style="border-radius:12px;overflow:hidden;"></div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
let activeCert = null;

function buildCertHTML(c) {
  return `
  <div id="certDoc" style="width:800px;background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:48px;font-family:Georgia,serif;color:#fff;box-sizing:border-box;">
    <div style="border:3px solid #f59e0b;border-radius:16px;padding:38px;box-sizing:border-box;">
      <div style="border:1px solid rgba(245,158,11,0.35);border-radius:12px;padding:34px;text-align:center;box-sizing:border-box;">

        <!-- Decorative circles -->
        <div style="display:flex;justify-content:center;align-items:center;gap:14px;margin-bottom:20px;">
          <div style="width:44px;height:44px;border-radius:50%;background:#f59e0b;display:flex;align-items:center;justify-content:center;">
            <div style="width:18px;height:18px;background:#fff;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%);"></div>
          </div>
          <div style="width:58px;height:58px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#b45309);display:flex;align-items:center;justify-content:center;">
            <div style="width:28px;height:28px;background:#fff;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%);"></div>
          </div>
          <div style="width:44px;height:44px;border-radius:50%;background:#f59e0b;display:flex;align-items:center;justify-content:center;">
            <div style="width:18px;height:18px;background:#fff;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%);"></div>
          </div>
        </div>

        <!-- Academy name -->
        <p style="font-family:Arial,sans-serif;font-size:0.8rem;letter-spacing:5px;color:#f59e0b;text-transform:uppercase;margin:0 0 8px;">JuniorCode Academy</p>

        <!-- Title -->
        <h1 style="font-size:2.5rem;font-weight:normal;color:#fff;margin:0 0 20px;letter-spacing:2px;">Certificate of Completion</h1>

        <!-- Gold line -->
        <div style="height:2px;background:linear-gradient(90deg,transparent,#f59e0b,transparent);margin:0 auto 20px;width:120px;"></div>

        <p style="font-size:0.95rem;color:rgba(255,255,255,0.65);margin:0 0 10px;font-family:Arial,sans-serif;">This is to certify that</p>

        <!-- Student name -->
        <p style="font-size:2.4rem;color:#f59e0b;margin:0 0 14px;font-style:italic;font-weight:normal;">${c.student_name}</p>

        <p style="font-size:0.95rem;color:rgba(255,255,255,0.65);margin:0 0 10px;font-family:Arial,sans-serif;">has successfully completed the course</p>

        <!-- Course name -->
        <p style="font-size:1.55rem;color:#fff;margin:0 0 22px;font-weight:normal;letter-spacing:1px;">${c.course_name}</p>

        <!-- Gold line -->
        <div style="height:2px;background:linear-gradient(90deg,transparent,#f59e0b,transparent);margin:0 auto 26px;width:120px;"></div>

        <!-- Footer row -->
        <div style="display:flex;justify-content:space-between;align-items:flex-end;">
          <div style="text-align:center;width:180px;">
            <div style="height:1px;background:rgba(255,255,255,0.35);margin-bottom:8px;"></div>
            <p style="font-size:0.82rem;color:rgba(255,255,255,0.7);margin:0;font-family:Arial,sans-serif;">${c.teacher_name}</p>
            <p style="font-size:0.7rem;color:#f59e0b;margin:3px 0 0;font-family:Arial,sans-serif;letter-spacing:2px;">INSTRUCTOR</p>
          </div>

          <!-- Center seal -->
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
  const parts = d.split('-');
  return months[parseInt(parts[1])-1] + ' ' + parseInt(parts[2]) + ', ' + parts[0];
}

function previewCert(cert) {
  activeCert = cert;
  document.getElementById('certPreview').innerHTML = buildCertHTML(cert);
  document.getElementById('certModal').style.display = 'flex';
  document.getElementById('modalDownloadBtn').onclick = () => renderAndDownload(cert);
}

async function downloadCert(cert) {
  await renderAndDownload(cert);
}

async function renderAndDownload(cert) {
  // Render off-screen so html2canvas works regardless of modal state
  const wrap = document.createElement('div');
  wrap.style.cssText = 'position:fixed;left:-9999px;top:0;z-index:-1;';
  wrap.innerHTML = buildCertHTML(cert);
  document.body.appendChild(wrap);

  const el = wrap.querySelector('#certDoc');
  const { jsPDF } = window.jspdf;
  const canvas = await html2canvas(el, { scale:2, useCORS:true, logging:false });
  document.body.removeChild(wrap);

  const img = canvas.toDataURL('image/png');
  const pdf = new jsPDF({ orientation:'landscape', unit:'px', format:[canvas.width/2, canvas.height/2] });
  pdf.addImage(img, 'PNG', 0, 0, canvas.width/2, canvas.height/2);
  pdf.save('Certificate_' + cert.student_name + '_' + cert.course_name + '.pdf');
}

document.getElementById('certModal').addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });
</script>
<script src="logout-modal.js"></script>
</body>
</html>

