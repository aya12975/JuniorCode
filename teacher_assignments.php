<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherId   = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "Teacher";

// Auto-create assignments table
$conn->query("CREATE TABLE IF NOT EXISTS assignments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT          NOT NULL DEFAULT 0,
    teacher_name VARCHAR(255) NOT NULL DEFAULT '',
    student_name VARCHAR(255) NOT NULL DEFAULT '',
    title        VARCHAR(255) NOT NULL DEFAULT '',
    description  TEXT         NOT NULL DEFAULT '',
    due_date     DATE         DEFAULT NULL,
    file_name    VARCHAR(255) NOT NULL DEFAULT '',
    link         VARCHAR(500) NOT NULL DEFAULT '',
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns for existing installs
$conn->query("ALTER TABLE assignments ADD COLUMN IF NOT EXISTS file_name VARCHAR(255) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE assignments ADD COLUMN IF NOT EXISTS link VARCHAR(500) NOT NULL DEFAULT ''"  );

// Ensure upload directory exists
if (!is_dir("uploads/assignments")) {
    mkdir("uploads/assignments", 0755, true);
}

$success = $_GET["success"] ?? "";
$error   = $_GET["error"]   ?? "";

/* ── Handle POST ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $studentName = trim($_POST["student_name"] ?? "");
        $title       = trim($_POST["title"]        ?? "");
        $description = trim($_POST["description"]  ?? "");
        $dueDate     = trim($_POST["due_date"]     ?? "") ?: null;
        $link        = trim($_POST["assignment_link"] ?? "");
        $fileName    = "";

        if (!$studentName || !$title) {
            header("Location: teacher_assignments.php?error=missing");
            exit();
        }

        // Handle optional file upload
        if (!empty($_FILES["assignment_file"]["name"]) && $_FILES["assignment_file"]["error"] === UPLOAD_ERR_OK) {
            $allowed  = ["pdf","doc","docx","jpg","jpeg","png","gif","zip","txt","mp4","ppt","pptx"];
            $origName = $_FILES["assignment_file"]["name"];
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                header("Location: teacher_assignments.php?error=filetype");
                exit();
            }
            $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
            $fileName = time() . "_" . $safeBase;
            if (!move_uploaded_file($_FILES["assignment_file"]["tmp_name"], "uploads/assignments/" . $fileName)) {
                $fileName = "";
            }
        }

        $stmt = $conn->prepare("INSERT INTO assignments (teacher_id, teacher_name, student_name, title, description, due_date, file_name, link) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isssssss", $teacherId, $teacherName, $studentName, $title, $description, $dueDate, $fileName, $link);
            $stmt->execute();
        }
        header("Location: teacher_assignments.php?success=added");
        exit();
    }

    if ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);
        if ($id > 0) {
            $fileToDelete = "";
            $fStmt = $conn->prepare("SELECT file_name FROM assignments WHERE id = ?");
            if ($fStmt) {
                $fStmt->bind_param("i", $id);
                $fStmt->execute();
                $fRow = $fStmt->get_result()->fetch_assoc();
                $fileToDelete = $fRow["file_name"] ?? "";
            }
            $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ? AND (teacher_id = ? OR LOWER(teacher_name) = LOWER(?))");
            if ($stmt) {
                $stmt->bind_param("iis", $id, $teacherId, $teacherName);
                $stmt->execute();
                if ($fileToDelete && $stmt->affected_rows > 0) {
                    @unlink("uploads/assignments/" . $fileToDelete);
                }
            }
        }
        header("Location: teacher_assignments.php?success=deleted");
        exit();
    }
}

/* ── Load students from users table (role = student) ── */
$students = [];
$s = $conn->prepare("SELECT id, username FROM users WHERE role = 'student' ORDER BY username ASC");
if ($s) {
    $s->execute();
    $students = $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ── Load assignments created by this teacher ── */
$assignments = [];
$s2 = $conn->prepare("SELECT * FROM assignments WHERE teacher_id = ? OR (teacher_id = 0 AND LOWER(teacher_name) = LOWER(?)) ORDER BY created_at DESC");
if ($s2) {
    $s2->bind_param("is", $teacherId, $teacherName);
    $s2->execute();
    $assignments = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ── Load student submissions keyed by assignment_id ── */
$submissions = [];
$subCheck = $conn->query("SHOW TABLES LIKE 'assignment_submissions'");
if ($subCheck && $subCheck->num_rows > 0 && !empty($assignments)) {
    $ids = implode(",", array_map(fn($a) => (int)$a["id"], $assignments));
    $subRes = $conn->query("SELECT * FROM assignment_submissions WHERE assignment_id IN ($ids)");
    if ($subRes) {
        foreach ($subRes->fetch_all(MYSQLI_ASSOC) as $row) {
            $submissions[$row["assignment_id"]] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="images/robot2.png.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assignments | JuniorCode</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --shadow:0 18px 45px rgba(37,99,235,0.08); }
* { box-sizing:border-box; }
body { margin:0; font-family:Arial,Helvetica,sans-serif; background:radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%),radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%),linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); color:var(--dark); }
.app-shell { min-height:100vh; display:flex; }
.sidebar { width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:white; padding:0; justify-content:space-between; position:sticky; top:0; height:100vh; overflow-y:auto; transition:width .3s,padding .3s,min-width .3s; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; }
.sidebar-bottom { margin-top:auto; border-top:1px solid rgba(255,255,255,0.1); padding-top:12px; }
body.sidebar-collapsed .sidebar { width:0; padding:0; min-width:0; overflow:hidden; }
.sidebar-top-area { padding: 24px 18px; flex: 1; }
.brand-box { display: flex; align-items: center; gap: 12px; padding: 0 4px 22px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }
.logo-img { width:55px; height:55px; object-fit:contain; border-radius:12px; flex-shrink:0; }
.brand-title { font-weight:900; font-size:1.1rem; line-height:1.15; }
.brand-sub { font-size:0.78rem; color:rgba(255,255,255,0.75); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.8rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.55); margin:18px 10px 10px; font-weight:700; }
.nav-custom { display:flex; flex-direction:column; gap:4px; }
.nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.88); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all .25s; font-weight:700; }
.nav-link-custom:hover { background:rgba(255,255,255,0.08); color:white; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.main-content { flex:1; padding:26px; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }
.topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:24px; padding:18px 20px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; box-shadow:var(--shadow); }
.topbar h1 { font-size:1.6rem; font-weight:900; margin:0; color:white; }
.topbar p  { margin:4px 0 0; color:rgba(255,255,255,0.8); }
.teacher-badge { background:rgba(255,255,255,0.15); color:white; border-radius:999px; padding:10px 16px; font-weight:800; white-space:nowrap; }
.panel-card { background:white; border:1px solid #edf4ff; border-radius:22px; padding:24px; box-shadow:var(--shadow); margin-bottom:22px; }
.panel-title { font-size:1.1rem; font-weight:900; margin-bottom:18px; color:var(--dark); }
.form-label { font-weight:800; color:#334155; margin-bottom:6px; }
.form-control, .form-select { border-radius:12px; padding:11px 14px; border:1px solid #dbe4f0; font-size:0.95rem; height:auto; }
.form-control:focus, .form-select:focus { border-color:var(--primary); box-shadow:0 0 0 0.2rem rgba(62,80,119,0.13); }
.btn-main { background:linear-gradient(135deg,var(--primary),var(--secondary)); border:none; color:white; font-weight:800; border-radius:12px; padding:11px 20px; cursor:pointer; text-decoration:none; display:inline-block; font-size:0.95rem; }
.btn-main:hover { color:white; opacity:0.92; }

/* Assignment cards */
.asgn-list { display:flex; flex-direction:column; gap:14px; }
.asgn-card { display:flex; align-items:flex-start; gap:16px; background:#fff; border:1px solid #e8f0fb; border-left:5px solid var(--primary); border-radius:18px; padding:18px 20px; box-shadow:0 4px 14px rgba(37,99,235,0.06); }
.asgn-icon { width:46px; height:46px; border-radius:13px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; margin-top:2px; }
.asgn-body { flex:1; min-width:0; }
.asgn-header { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:3px; }
.asgn-title { font-weight:900; font-size:1rem; color:#0f172a; }
.asgn-student { font-size:0.83rem; font-weight:700; color:#2563eb; margin-bottom:6px; }
.asgn-student i { margin-right:4px; }
.asgn-desc { font-size:0.86rem; color:#475569; margin-bottom:8px; white-space:pre-wrap; background:#f8fbff; border-radius:8px; padding:8px 10px; }
.asgn-due { display:inline-flex; align-items:center; gap:5px; font-size:0.8rem; font-weight:700; color:#f97316; background:#fff7ed; border:1px solid #fed7aa; border-radius:7px; padding:3px 9px; margin-bottom:8px; }
.asgn-date { font-size:0.76rem; color:var(--muted); margin-top:8px; padding-top:8px; border-top:1px solid #f1f5f9; }
.btn-del { background:#fee2e2; color:#dc2626; border:none; border-radius:10px; padding:8px 14px; font-weight:800; font-size:0.82rem; cursor:pointer; white-space:nowrap; flex-shrink:0; }
.btn-del:hover { background:#fecaca; }
.empty-box { text-align:center; padding:28px; border-radius:16px; background:#f8fbff; color:var(--muted); border:1px dashed #d9e9ff; font-weight:700; }

/* Submission status */
.sub-box { margin-top:10px; padding:11px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; }
.sub-box-label { font-size:0.74rem; font-weight:800; color:#16a34a; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
.sub-file-link { display:inline-flex; align-items:center; gap:5px; font-size:0.83rem; font-weight:700; color:#2563eb; text-decoration:none; }
.sub-file-link:hover { text-decoration:underline; }
.sub-time { font-size:0.72rem; color:#64748b; margin-top:4px; }
.await-label { margin-top:8px; font-size:0.78rem; font-weight:700; color:#d97706; }
.badge-sub { display:inline-flex; align-items:center; gap:4px; background:#dcfce7; color:#15803d; border-radius:999px; padding:2px 9px; font-size:0.72rem; font-weight:800; }
.badge-await { display:inline-flex; align-items:center; gap:4px; background:#fef9c3; color:#92400e; border-radius:999px; padding:2px 9px; font-size:0.72rem; font-weight:800; }

/* Delete confirmation modal */
.del-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(15,23,42,0.55); z-index:3000;
  align-items:center; justify-content:center;
}
.del-overlay.show { display:flex; }
.del-box {
  background:#fff; border-radius:22px; padding:32px 28px 28px;
  width:100%; max-width:400px;
  box-shadow:0 24px 60px rgba(0,0,0,0.22);
  text-align:center; position:relative;
}
.del-icon-wrap {
  width:60px; height:60px; border-radius:50%;
  background:#fee2e2; color:#dc2626;
  font-size:1.4rem; display:flex; align-items:center; justify-content:center;
  margin:0 auto 16px;
}
.del-title { font-size:1.1rem; font-weight:900; color:#0f172a; margin-bottom:8px; }
.del-msg   { font-size:0.88rem; color:#64748b; margin-bottom:24px; line-height:1.5; }
.del-actions { display:flex; gap:10px; justify-content:center; }
.btn-cancel-del {
  flex:1; padding:11px; border:1.5px solid #e2e8f0; border-radius:12px;
  background:#fff; color:#334155; font-weight:800; font-size:0.9rem;
  cursor:pointer; transition:background 0.2s;
}
.btn-cancel-del:hover { background:#f1f5f9; }
.btn-confirm-del {
  flex:1; padding:11px; border:none; border-radius:12px;
  background:linear-gradient(135deg,#dc2626,#b91c1c);
  color:#fff; font-weight:900; font-size:0.9rem;
  cursor:pointer; transition:opacity 0.2s;
}
.btn-confirm-del:hover { opacity:0.88; }

@media(max-width:991px){.app-shell{flex-direction:column;}.sidebar{width:100%;height:auto;position:relative;}}
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
        <div class="brand-sub">Teacher Panel</div>
      </div>
    </div>
    <div class="nav-title">Menu</div>
    <div class="nav-custom">
      <a href="teacher_dashboard.php"       class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
      <a href="teacher_classes.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>My Classes</span></a>
      <a href="teacher_schedule.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>My Schedule</span></a>
      <a href="teacher_monthly_earnings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>My Earnings</span></a>
      <a href="teacher_students.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-user-graduate"></i></span><span>My Students</span></a>
      <a href="teacher_assignments.php"     class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>Assignments</span></a>
      <a href="teacher_courses.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
    </div>
    </div>
    <div class="sidebar-bottom">
      <a href="teacher_profile.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
      <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
      <a href="logout.php"                  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
    </div>
  </aside>

  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
    </div>

    <div class="topbar">
      <div>
        <h1><i class="fas fa-clipboard-list me-2"></i>Assignments</h1>
        <p>Create and manage assignments for your students</p>
      </div>
      <div class="teacher-badge">Hello, <?= htmlspecialchars($teacherName) ?></div>
    </div>

    <?php if ($success === "added"):    ?><div class="alert alert-success">Assignment added successfully.</div><?php endif; ?>
    <?php if ($success === "deleted"):  ?><div class="alert alert-success">Assignment deleted.</div><?php endif; ?>
    <?php if ($error   === "missing"):  ?><div class="alert alert-danger">Student and title are required.</div><?php endif; ?>
    <?php if ($error   === "filetype"): ?><div class="alert alert-danger">File type not allowed. Accepted: PDF, Word, JPG, PNG, ZIP, TXT, MP4, PPT.</div><?php endif; ?>

    <!-- Add assignment form -->
    <div class="panel-card">
      <div class="panel-title"><i class="fas fa-plus-circle me-2" style="color:#2563eb;"></i>New Assignment</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Student</label>
            <?php if (!empty($students)): ?>
              <select name="student_name" class="form-select" required>
                <option value="">— Select student —</option>
                <?php foreach ($students as $st): ?>
                  <option value="<?= htmlspecialchars($st['username']) ?>"><?= htmlspecialchars($st['username']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="text" name="student_name" class="form-control" placeholder="Student username" required>
            <?php endif; ?>
          </div>
          <div class="col-md-5">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Build a Scratch game" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Due Date <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
            <input type="date" name="due_date" class="form-control">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Description <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
          <textarea name="description" class="form-control" rows="3" placeholder="Instructions, links, or notes..."></textarea>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-paperclip me-1" style="color:#64748b;"></i>Attach File <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
            <input type="file" name="assignment_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.zip,.txt,.mp4,.ppt,.pptx">
          </div>
          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-link me-1" style="color:#64748b;"></i>Or Attach a Link <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
            <input type="url" name="assignment_link" class="form-control" placeholder="https://drive.google.com/... or YouTube, etc.">
          </div>
        </div>
        <button type="submit" class="btn-main"><i class="fas fa-paper-plane me-1"></i> Send Assignment</button>
      </form>
    </div>

    <!-- Existing assignments -->
    <div class="panel-card">
      <div class="panel-title"><i class="fas fa-list-check me-2" style="color:#2563eb;"></i>Sent Assignments <span style="font-size:0.85rem;font-weight:600;color:var(--muted);margin-left:6px;">(<?= count($assignments) ?>)</span></div>

      <?php if (empty($assignments)): ?>
        <div class="empty-box">No assignments sent yet.</div>
      <?php else: ?>
        <div class="asgn-list">
          <?php foreach ($assignments as $a): ?>
          <?php $sub = $submissions[$a["id"]] ?? null; ?>
          <div class="asgn-card">
            <div class="asgn-icon"><i class="fas fa-file-pen"></i></div>
            <div class="asgn-body">
              <div class="asgn-header">
                <div class="asgn-title"><?= htmlspecialchars($a["title"]) ?></div>
                <?php if ($sub): ?>
                  <span class="badge-sub"><i class="fas fa-check-circle"></i> Submitted</span>
                <?php else: ?>
                  <span class="badge-await"><i class="fas fa-hourglass-half"></i> Pending</span>
                <?php endif; ?>
              </div>
              <div class="asgn-student"><i class="fas fa-user-graduate"></i><?= htmlspecialchars($a["student_name"]) ?></div>
              <?php if (!empty($a["description"])): ?>
                <div class="asgn-desc"><?= htmlspecialchars($a["description"]) ?></div>
              <?php endif; ?>
              <?php if (!empty($a["due_date"])): ?>
                <div class="asgn-due"><i class="fas fa-clock"></i>Due: <?= date("D, d M Y", strtotime($a["due_date"])) ?></div>
              <?php endif; ?>
              <?php if (!empty($a["file_name"]) || !empty($a["link"])): ?>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                  <?php if (!empty($a["file_name"])): ?>
                    <a href="uploads/assignments/<?= urlencode($a["file_name"]) ?>" download class="btn-main" style="padding:6px 14px;font-size:0.82rem;">
                      <i class="fas fa-paperclip me-1"></i> Attachment
                    </a>
                  <?php endif; ?>
                  <?php if (!empty($a["link"])): ?>
                    <a href="<?= htmlspecialchars($a["link"]) ?>" target="_blank" rel="noopener" class="btn-main" style="padding:6px 14px;font-size:0.82rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);">
                      <i class="fas fa-link me-1"></i> Open Link
                    </a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($sub): ?>
                <div class="sub-box">
                  <div class="sub-box-label"><i class="fas fa-check-circle me-1"></i>Student's Work</div>
                  <?php if (!empty($sub["file_name"])): ?>
                    <a href="uploads/submissions/<?= urlencode($sub["file_name"]) ?>" download class="sub-file-link">
                      <i class="fas fa-file-arrow-down"></i> Download Submission
                    </a>
                  <?php endif; ?>
                  <?php if (!empty($sub["link"])): ?>
                    <div style="margin-top:<?= !empty($sub["file_name"]) ? '5px' : '0' ?>;">
                      <a href="<?= htmlspecialchars($sub["link"]) ?>" target="_blank" rel="noopener" class="sub-file-link" style="word-break:break-all;">
                        <i class="fas fa-link"></i> <?= htmlspecialchars($sub["link"]) ?>
                      </a>
                    </div>
                  <?php endif; ?>
                  <div class="sub-time">Submitted: <?= date("d M Y, h:i A", strtotime($sub["submitted_at"])) ?></div>
                </div>
              <?php endif; ?>
              <div class="asgn-date"><i class="fas fa-calendar-plus me-1"></i>Sent on <?= date("d M Y, h:i A", strtotime($a["created_at"])) ?></div>
            </div>
            <button type="button" class="btn-del" style="flex-shrink:0;align-self:flex-start;"
              onclick="openDelModal(<?= $a['id'] ?>, '<?= addslashes(htmlspecialchars($a['title'])) ?>')">
              <i class="fas fa-trash"></i>
            </button>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- ── Delete confirmation modal ── -->
<div class="del-overlay" id="delModal">
  <div class="del-box">
    <div class="del-icon-wrap"><i class="fas fa-trash"></i></div>
    <div class="del-title">Delete Assignment?</div>
    <p class="del-msg" id="delModalMsg">This will permanently remove the assignment.</p>
    <div class="del-actions">
      <button type="button" class="btn-cancel-del" onclick="closeDelModal()">Cancel</button>
      <button type="button" class="btn-confirm-del" onclick="submitDelete()">Yes, Delete</button>
    </div>
  </div>
</div>

<!-- Single shared delete form -->
<form method="POST" id="delForm" style="display:none;">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="delFormId" value="">
</form>

<script>
function openDelModal(id, title) {
  document.getElementById('delFormId').value = id;
  document.getElementById('delModalMsg').textContent = 'Are you sure you want to delete "' + title + '"? This cannot be undone.';
  document.getElementById('delModal').classList.add('show');
}
function closeDelModal() {
  document.getElementById('delModal').classList.remove('show');
}
function submitDelete() {
  document.getElementById('delForm').submit();
}
document.getElementById('delModal').addEventListener('click', function(e) {
  if (e.target === this) closeDelModal();
});
</script>
<script src="logout-modal.js"></script>
</body>
</html>
