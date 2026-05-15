<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";

function cleanFileName(string $f): string {
    return preg_replace('/^\d+_/', '', $f);
}

/* ── Ensure tables & upload dir ── */
$conn->query("CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL DEFAULT 0,
    teacher_name VARCHAR(255) NOT NULL DEFAULT '',
    student_name VARCHAR(255) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    due_date DATE DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS assignment_submissions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_name  VARCHAR(255) NOT NULL DEFAULT '',
    file_name     VARCHAR(255) NOT NULL DEFAULT '',
    link          VARCHAR(500) NOT NULL DEFAULT '',
    submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!is_dir("uploads/submissions")) {
    mkdir("uploads/submissions", 0755, true);
}

$success = $_GET["success"] ?? "";
$error   = $_GET["error"]   ?? "";

/* ── Handle submission POST ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "submit") {
    $assignmentId = (int)($_POST["assignment_id"] ?? 0);
    $link         = trim($_POST["submission_link"] ?? "");
    $fileName     = "";

    if ($assignmentId <= 0) {
        header("Location: student_assignments.php?error=invalid");
        exit();
    }

    // Handle optional PDF/file upload
    if (!empty($_FILES["submission_file"]["name"]) && $_FILES["submission_file"]["error"] === UPLOAD_ERR_OK) {
        $allowed  = ["pdf","doc","docx","jpg","jpeg","png","gif","zip","txt","ppt","pptx","mp4"];
        $origName = $_FILES["submission_file"]["name"];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            header("Location: student_assignments.php?error=filetype");
            exit();
        }
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
        $newName  = time() . "_" . $safeBase;
        if (move_uploaded_file($_FILES["submission_file"]["tmp_name"], "uploads/submissions/" . $newName)) {
            $fileName = $newName;
        }
    }

    if (!$fileName && !$link) {
        header("Location: student_assignments.php?error=empty");
        exit();
    }

    // Check if submission already exists
    $existing = $conn->prepare("SELECT id, file_name FROM assignment_submissions WHERE assignment_id = ? AND student_name = ?");
    $existing->bind_param("is", $assignmentId, $studentName);
    $existing->execute();
    $existingRow = $existing->get_result()->fetch_assoc();

    if ($existingRow) {
        // Delete old file if a new one was uploaded
        if ($fileName && !empty($existingRow["file_name"])) {
            @unlink("uploads/submissions/" . $existingRow["file_name"]);
        }
        $keepFile = $fileName ?: $existingRow["file_name"];
        $upd = $conn->prepare("UPDATE assignment_submissions SET file_name=?, link=?, submitted_at=NOW() WHERE id=?");
        if ($upd) {
            $upd->bind_param("ssi", $keepFile, $link, $existingRow["id"]);
            $upd->execute();
        }
    } else {
        $ins = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_name, file_name, link) VALUES (?, ?, ?, ?)");
        if ($ins) {
            $ins->bind_param("isss", $assignmentId, $studentName, $fileName, $link);
            $ins->execute();
        }
    }

    header("Location: student_assignments.php?success=submitted");
    exit();
}

/* ── Load assignments ── */
$assignments = [];
$stmt = $conn->prepare("SELECT * FROM assignments WHERE student_name = ? ORDER BY created_at DESC");
if ($stmt) {
    $stmt->bind_param("s", $studentName);
    $stmt->execute();
    $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ── Load all submissions for this student (keyed by assignment_id) ── */
$submissions = [];
$subStmt = $conn->prepare("SELECT * FROM assignment_submissions WHERE student_name = ?");
if ($subStmt) {
    $subStmt->bind_param("s", $studentName);
    $subStmt->execute();
    foreach ($subStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $submissions[$row["assignment_id"]] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Assignments | JuniorCode</title>
<link rel="icon" type="image/png" href="images/robot2.png.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
  --primary:   #3e5077;
  --secondary: #143674;
  --dark:      #0f172a;
  --muted:     #64748b;
  --border:    #edf4ff;
  --shadow:    0 18px 45px rgba(37,99,235,0.08);
}
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: Arial, Helvetica, sans-serif;
  color: var(--dark);
  background:
    radial-gradient(circle at top left,  rgba(37,99,235,0.08), transparent 22%),
    radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
    linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
}

    .app-shell { min-height: 100vh; display: flex; }

    .sidebar {
      width: 285px; flex-shrink: 0;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: #fff; padding: 0;
      position: sticky; top: 0;
      height: 100vh;
      display: flex; flex-direction: column;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease;
      overflow-y: auto;
    }
    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; }

    .sidebar-top-area { padding: 0 18px 18px; }

    .brand {
      display: flex; align-items: center; gap: 12px;
      padding: 0 4px 22px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 10px;
    }

    .brand-logo-img {
      width: 55px; height: 55px;
      object-fit: contain; flex-shrink: 0;
      background: none; border-radius: 0;
    }

    .brand-title { font-weight: 900; font-size: 1.1rem; color: #fff; line-height: 1.2; }
    .brand-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.55); letter-spacing: 1px; margin-top: 3px; }

    .nav-title {
      font-size: 0.78rem; text-transform: uppercase;
      letter-spacing: 1.3px; color: rgba(255,255,255,0.45);
      margin: 20px 10px 10px; font-weight: 700;
    }

    .nav-custom { display: flex; flex-direction: column; gap: 4px; }

    .student-box {
      display: flex; align-items: center; gap: 12px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 16px; padding: 14px; margin-bottom: 18px;
    }

    .student-avatar {
      width: 44px; height: 44px; border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; font-weight: bold; font-size: 18px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      overflow: hidden;
    }
    .student-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .student-name { font-weight: 800; margin: 0; color: #fff; }
    .student-role { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }

    .nav-link-custom {
      display: flex; align-items: center; gap: 12px;
      text-decoration: none; color: rgba(255,255,255,0.78);
      padding: 12px 14px; border-radius: 14px; margin: 4px 0;
      font-weight: 700; transition: all 0.22s ease;
    }
    .nav-link-custom:hover { background: rgba(255,255,255,0.09); color: #fff; }
    .nav-link-custom.active {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; box-shadow: 0 8px 20px rgba(30,50,100,0.35);
    }
    .nav-icon {
      width: 32px; height: 32px; border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0;
    }
    .nav-link-custom.active .nav-icon { background: rgba(255,255,255,0.18); }

    .sidebar-bottom { padding: 16px 18px; }

    .main { flex: 1; padding: 28px; min-height: 100vh; overflow-x: hidden; }

/* ── Hamburger ── */
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

/* ── Topbar ── */
.topbar {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  border-radius: 22px; padding: 22px 26px; margin-bottom: 26px;
  display: flex; justify-content: space-between; align-items: center;
  flex-wrap: wrap; gap: 14px; color: #fff;
  box-shadow: 0 12px 28px rgba(37,99,235,0.28);
}
.topbar h1 { font-size: 1.7rem; font-weight: 900; margin: 0; }
.topbar p  { margin: 4px 0 0; opacity: 0.85; font-size: 0.95rem; }
.topbar-badge {
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2);
  border-radius: 12px; padding: 10px 18px; font-weight: 700; font-size: 0.9rem;
}

/* ── Assignment cards ── */
.asgn-grid { display: flex; flex-direction: column; gap: 16px; }
.asgn-card {
  background: #fff;
  border: 1px solid var(--border);
  border-left: 5px solid var(--primary);
  border-radius: 20px;
  padding: 20px 22px;
  box-shadow: var(--shadow);
  display: flex; align-items: flex-start; gap: 16px;
}
.asgn-card.submitted { border-left-color: #16a34a; }
.asgn-icon {
  width: 46px; height: 46px; border-radius: 13px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: white; display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; flex-shrink: 0; margin-top: 2px;
}
.asgn-body { flex: 1; min-width: 0; }
.asgn-title { font-weight: 900; font-size: 1.05rem; color: var(--dark); margin-bottom: 2px; }
.asgn-from  { font-size: 0.82rem; color: #2563eb; font-weight: 700; margin-bottom: 8px; }
.asgn-desc  { font-size: 0.88rem; color: #475569; white-space: pre-wrap; margin-bottom: 10px; line-height: 1.55; background:#f8fbff; border-radius:10px; padding:10px 12px; }
.asgn-due   { display: inline-flex; align-items: center; gap: 5px; font-size: 0.82rem; font-weight: 700; color: #f97316; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 4px 10px; margin-bottom: 10px; }
.asgn-received { font-size: 0.75rem; color: var(--muted); margin-top: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9; }

.btn-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 4px; margin-bottom: 2px; }
.btn-download {
  display: inline-flex; align-items: center; gap: 7px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: #fff; font-weight: 800; border-radius: 10px;
  padding: 8px 16px; font-size: 0.84rem; text-decoration: none;
  transition: opacity 0.2s;
}
.btn-download:hover { color: #fff; opacity: 0.88; }
.btn-submit {
  display: inline-flex; align-items: center; gap: 7px;
  background: linear-gradient(135deg, #16a34a, #22c55e);
  color: #fff; font-weight: 800; border-radius: 10px;
  padding: 8px 16px; font-size: 0.84rem; border: none;
  cursor: pointer; transition: opacity 0.2s;
}
.btn-submit:hover { opacity: 0.88; }
.btn-resubmit {
  display: inline-flex; align-items: center; gap: 7px;
  background: #f0fdf4; color: #16a34a; border: 1.5px solid #86efac;
  font-weight: 800; border-radius: 10px; padding: 7px 14px;
  font-size: 0.82rem; cursor: pointer; transition: background 0.2s;
}
.btn-resubmit:hover { background: #dcfce7; }

/* ── Submission box ── */
.submission-box {
  margin-top: 12px; padding: 12px 14px;
  background: #f0fdf4; border: 1px solid #bbf7d0;
  border-radius: 12px;
}
.submission-box .sub-label {
  font-size: 0.78rem; font-weight: 800; color: #16a34a;
  text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;
}
.sub-link {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 0.84rem; font-weight: 700; color: #2563eb;
  text-decoration: none; word-break: break-all;
}
.sub-link:hover { text-decoration: underline; }
.sub-date { font-size: 0.75rem; color: var(--muted); margin-top: 4px; }

/* ── Status badge ── */
.badge-submitted {
  display: inline-flex; align-items: center; gap: 5px;
  background: #dcfce7; color: #15803d; border-radius: 999px;
  padding: 3px 10px; font-size: 0.75rem; font-weight: 800;
}
.badge-pending {
  display: inline-flex; align-items: center; gap: 5px;
  background: #fef9c3; color: #92400e; border-radius: 999px;
  padding: 3px 10px; font-size: 0.75rem; font-weight: 800;
}

/* ── Empty state ── */
.empty-state {
  text-align: center; padding: 60px 20px;
  color: var(--muted); background: #f8fbff;
  border: 1px dashed #d9e9ff; border-radius: 20px;
}
.empty-state i { font-size: 3rem; margin-bottom: 14px; display: block; opacity: 0.35; }
.empty-state p { font-weight: 700; font-size: 1rem; margin: 0; }

/* ── Stat pill ── */
.count-pill {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.25);
  border-radius: 999px; padding: 6px 14px; font-size: 0.88rem; font-weight: 800;
}

/* ── Submit modal ── */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(15,23,42,0.55); z-index: 2000;
  align-items: center; justify-content: center;
}
.modal-overlay.show { display: flex; }
.modal-box {
  background: #fff; border-radius: 22px; padding: 30px 28px;
  width: 100%; max-width: 480px; box-shadow: 0 24px 60px rgba(0,0,0,0.2);
  position: relative;
}
.modal-title { font-size: 1.15rem; font-weight: 900; color: var(--dark); margin-bottom: 6px; }
.modal-sub   { font-size: 0.85rem; color: var(--muted); margin-bottom: 22px; }
.modal-close {
  position: absolute; top: 16px; right: 18px;
  background: #f1f5f9; border: none; border-radius: 8px;
  width: 32px; height: 32px; font-size: 1rem;
  cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;
}
.modal-close:hover { background: #e2e8f0; }
.modal-label { font-weight: 800; font-size: 0.88rem; color: #334155; margin-bottom: 6px; display: block; }
.modal-input {
  width: 100%; border-radius: 12px; padding: 11px 14px;
  border: 1px solid #dbe4f0; font-size: 0.95rem;
  font-family: inherit; margin-bottom: 14px;
}
.modal-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(62,80,119,0.12); }
.modal-divider { text-align: center; color: var(--muted); font-weight: 800; font-size: 0.82rem; margin: 4px 0 14px; }
.btn-modal-submit {
  width: 100%; padding: 13px; border: none; border-radius: 12px;
  background: linear-gradient(135deg, #16a34a, #22c55e);
  color: #fff; font-weight: 900; font-size: 1rem; cursor: pointer;
  transition: opacity 0.2s;
}
.btn-modal-submit:hover { opacity: 0.9; }

    @media (max-width: 991px) {
      .app-shell { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: relative; }
    }
</style>
</head>
<body>

<div class="app-shell">

<aside class="sidebar">
  <div class="sidebar-top-area">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode Logo">
      <div>
        <div class="brand-title">JuniorCode</div>
        <div class="brand-subtitle">STUDENT PANEL</div>
      </div>
    </div>

    <div class="student-box">
      <div class="student-avatar">
        <?php if (!empty($_SESSION["profile_picture"])): ?>
          <img src="uploads/profiles/<?= htmlspecialchars($_SESSION["profile_picture"]) ?>" alt="Profile">
        <?php else: ?>
          <?= strtoupper(substr($studentName, 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <p class="student-name"><?= htmlspecialchars($studentName) ?></p>
        <p class="student-role">Student</p>
      </div>
    </div>

    <div class="nav-title">MAIN</div>
    <div class="nav-custom">
      <a href="student_dashboard.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
      </a>
      <a href="student_courses.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>My Courses</span>
      </a>
      <a href="student_projects.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-folder-open"></i></span><span>My Projects</span></a>
      <a href="student_classes.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span>
      </a>
      <a href="student_assignments.php" class="nav-link-custom active">
        <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span>
      </a>
      <a href="student_quizzes.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span>
      </a>
      <a href="student_certificates.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span>
      </a>
      <a href="student_chat.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span>
      </a>
      <a href="student_contact.php" class="nav-link-custom">
        <span class="nav-icon"><i class="fas fa-envelope"></i></span><span>Contact</span>
      </a>
    </div>
  </div>

  <div class="sidebar-bottom">
    <a href="student_profile.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span>
    </a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span>
    </a>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main">

  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <div class="topbar">
    <div>
      <h1><i class="fas fa-clipboard-list me-2"></i>My Assignments</h1>
      <p>Assignments sent to you by your teachers</p>
    </div>
    <div class="topbar-badge">
      <span class="count-pill"><i class="fas fa-file-pen"></i> <?= count($assignments) ?> assignment<?= count($assignments) !== 1 ? 's' : '' ?></span>
    </div>
  </div>

  <?php if ($success === "submitted"): ?>
    <div class="alert alert-success alert-dismissible">Work submitted successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>
  <?php if ($error === "empty"): ?>
    <div class="alert alert-danger alert-dismissible">Please upload a file or enter a link. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>
  <?php if ($error === "filetype"): ?>
    <div class="alert alert-danger alert-dismissible">File type not allowed. Use PDF, Word, image, or ZIP. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <?php if (empty($assignments)): ?>
    <div class="empty-state">
      <i class="fas fa-clipboard-list"></i>
      <p>No assignments yet. Check back after your next class!</p>
    </div>
  <?php else: ?>
    <div class="asgn-grid">
      <?php foreach ($assignments as $a):
        $sub = $submissions[$a["id"]] ?? null;
        $isSubmitted = !empty($sub);
      ?>
      <div class="asgn-card <?= $isSubmitted ? 'submitted' : '' ?>">
        <div class="asgn-icon"><i class="fas fa-file-pen"></i></div>
        <div class="asgn-body">

          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
            <div class="asgn-title" style="margin-bottom:0;"><?= htmlspecialchars($a["title"]) ?></div>
            <?php if ($isSubmitted): ?>
              <span class="badge-submitted"><i class="fas fa-check-circle"></i> Submitted</span>
            <?php else: ?>
              <span class="badge-pending"><i class="fas fa-hourglass-half"></i> Pending</span>
            <?php endif; ?>
          </div>

          <div class="asgn-from"><i class="fas fa-chalkboard-user me-1"></i>From: <?= htmlspecialchars($a["teacher_name"]) ?></div>

          <?php if (!empty($a["description"])): ?>
            <div class="asgn-desc"><?= htmlspecialchars($a["description"]) ?></div>
          <?php endif; ?>

          <?php if (!empty($a["due_date"])): ?>
            <div class="asgn-due"><i class="fas fa-clock"></i>Due: <?= date("D, d M Y", strtotime($a["due_date"])) ?></div>
          <?php endif; ?>

          <!-- Action buttons row -->
          <div class="btn-row">
            <?php if (!empty($a["file_name"])): ?>
              <a href="uploads/assignments/<?= urlencode($a["file_name"]) ?>" download class="btn-download">
                <i class="fas fa-paperclip"></i> Download Attachment
              </a>
            <?php endif; ?>
            <?php if (!empty($a["link"])): ?>
              <a href="<?= htmlspecialchars($a["link"]) ?>" target="_blank" rel="noopener" class="btn-download" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">
                <i class="fas fa-link"></i> Open Link
              </a>
            <?php endif; ?>
            <?php if ($isSubmitted): ?>
              <button class="btn-resubmit" onclick="openSubmitModal(<?= $a['id'] ?>, '<?= addslashes(htmlspecialchars($a['title'])) ?>')">
                <i class="fas fa-rotate-right"></i> Update Submission
              </button>
            <?php else: ?>
              <button class="btn-submit" onclick="openSubmitModal(<?= $a['id'] ?>, '<?= addslashes(htmlspecialchars($a['title'])) ?>')">
                <i class="fas fa-upload"></i> Submit Work
              </button>
            <?php endif; ?>
          </div>

          <!-- Submitted work display -->
          <?php if ($isSubmitted): ?>
            <div class="submission-box">
              <div class="sub-label"><i class="fas fa-check-circle me-1"></i>Your Submission</div>
              <?php if (!empty($sub["file_name"])): ?>
                <div>
                  <a href="uploads/submissions/<?= urlencode($sub["file_name"]) ?>" download class="sub-link">
                    <i class="fas fa-file-arrow-down"></i> <?= htmlspecialchars(cleanFileName($sub["file_name"])) ?>
                  </a>
                </div>
              <?php endif; ?>
              <?php if (!empty($sub["link"])): ?>
                <div style="margin-top:<?= !empty($sub["file_name"]) ? '6px' : '0' ?>;">
                  <a href="<?= htmlspecialchars($sub["link"]) ?>" target="_blank" rel="noopener" class="sub-link">
                    <i class="fas fa-link"></i> <?= htmlspecialchars($sub["link"]) ?>
                  </a>
                </div>
              <?php endif; ?>
              <div class="sub-date">Submitted: <?= date("d M Y, h:i A", strtotime($sub["submitted_at"])) ?></div>
            </div>
          <?php endif; ?>

          <div class="asgn-received"><i class="fas fa-calendar-check me-1"></i>Received: <?= date("d M Y, h:i A", strtotime($a["created_at"])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
</div><!-- /.app-shell -->

<!-- ══ SUBMIT MODAL ══ -->
<div class="modal-overlay" id="submitModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></button>
    <div class="modal-title"><i class="fas fa-upload me-2" style="color:#16a34a;"></i>Submit Your Work</div>
    <p class="modal-sub" id="modalAssignTitle">Assignment</p>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="submit">
      <input type="hidden" name="assignment_id" id="modalAssignId" value="">

      <label class="modal-label"><i class="fas fa-file-pdf me-1" style="color:#dc2626;"></i>Upload a file (PDF, Word, image…)</label>
      <input type="file" name="submission_file" class="modal-input"
             accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.zip,.txt,.ppt,.pptx,.mp4">

      <div class="modal-divider">— OR —</div>

      <label class="modal-label"><i class="fas fa-link me-1" style="color:#2563eb;"></i>Paste a link (Google Drive, GitHub, etc.)</label>
      <input type="url" name="submission_link" class="modal-input" id="modalLink"
             placeholder="https://drive.google.com/...">

      <button type="submit" class="btn-modal-submit"><i class="fas fa-paper-plane me-2"></i>Submit</button>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openSubmitModal(id, title) {
  document.getElementById('modalAssignId').value = id;
  document.getElementById('modalAssignTitle').textContent = title;
  document.getElementById('modalLink').value = '';
  document.getElementById('submitModal').classList.add('show');
}
function closeModal() {
  document.getElementById('submitModal').classList.remove('show');
}
document.getElementById('submitModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
<script src="logout-modal.js"></script>
</body>
</html>

