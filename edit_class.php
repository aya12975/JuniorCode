<?php
session_start();
require_once "db.php";
require_once "admin_prefs.php";
require_once "mailer.php";
require_once "notifications.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName   = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: manage_classes.php");
    exit();
}

$id      = (int)$_GET["id"];
$message = "";

// Ensure required columns exist
foreach ([
    "zoom_link"  => "ALTER TABLE classes ADD COLUMN zoom_link VARCHAR(500) DEFAULT NULL",
    "teacher_id" => "ALTER TABLE classes ADD COLUMN teacher_id INT DEFAULT NULL",
    "type"       => "ALTER TABLE classes ADD COLUMN type VARCHAR(100) NOT NULL DEFAULT ''",
    "details"    => "ALTER TABLE classes ADD COLUMN details TEXT NOT NULL DEFAULT ''",
] as $col => $sql) {
    $chk = $conn->query("SHOW COLUMNS FROM classes LIKE '$col'");
    if ($chk && $chk->num_rows === 0) $conn->query($sql);
}

// Fetch teachers for dropdown
$teachers = [];
$tRes = $conn->query("SELECT id, username FROM users WHERE role = 'teacher' ORDER BY username ASC");
if ($tRes) while ($r = $tRes->fetch_assoc()) $teachers[] = $r;

$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();

if (!$class) {
    header("Location: manage_classes.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacher_id   = (int)($_POST["teacher_id"] ?? 0);
    $teacher_name = trim($_POST["teacher_name"] ?? "");
    $student_name = trim($_POST["student_name"] ?? "");
    $class_date   = $_POST["class_date"]  ?? "";
    $class_time   = $_POST["class_time"]  ?? "";
    $type         = trim($_POST["type"]   ?? "");
    $details      = trim($_POST["details"] ?? "");
    $zoom_link    = trim($_POST["zoom_link"] ?? "");

    // If teacher_id not submitted, look it up by name
    if ($teacher_id === 0 && $teacher_name !== "") {
        $tLookup = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'teacher' LIMIT 1");
        if ($tLookup) {
            $tLookup->bind_param("s", $teacher_name);
            $tLookup->execute();
            $tRow = $tLookup->get_result()->fetch_assoc();
            if ($tRow) $teacher_id = (int)$tRow["id"];
        }
    }

    if ($teacher_name !== "" && $student_name !== "" && $class_date !== "" && $class_time !== "" && $type !== "") {
        $stmt2 = $conn->prepare("UPDATE classes SET teacher_id = ?, teacher_name = ?, student_name = ?, class_date = ?, class_time = ?, type = ?, details = ?, zoom_link = ? WHERE id = ?");
        if (!$stmt2) {
            $message = "Prepare failed: " . $conn->error;
        } else {
            $stmt2->bind_param("isssssssi", $teacher_id, $teacher_name, $student_name, $class_date, $class_time, $type, $details, $zoom_link, $id);
            if ($stmt2->execute()) {
                /* ── Detect what changed ── */
                $changes = [];
                if ($class["class_date"] !== $class_date)
                    $changes[] = "date: " . date("d M Y", strtotime($class["class_date"])) . " → " . date("d M Y", strtotime($class_date));
                if ($class["class_time"] !== $class_time)
                    $changes[] = "time: " . date("g:i A", strtotime($class["class_time"])) . " → " . date("g:i A", strtotime($class_time));
                if (strtolower($class["teacher_name"]) !== strtolower($teacher_name))
                    $changes[] = "teacher: {$class['teacher_name']} → $teacher_name";
                if ($class["student_name"] !== $student_name)
                    $changes[] = "student: {$class['student_name']} → $student_name";
                if ($class["type"] !== $type)
                    $changes[] = "type: {$class['type']} → $type";

                $changesSummary = $changes ? implode("; ", $changes) : "details updated";

                /* ── SMTP config ── */
                $smtpHost  = getAdminSetting($conn, "smtp_host", "");
                $smtpPort  = (int)getAdminSetting($conn, "smtp_port", 587);
                $smtpUser  = getAdminSetting($conn, "smtp_user", "");
                $smtpPass  = getAdminSetting($conn, "smtp_pass", "");
                $fromName  = getAdminSetting($conn, "smtp_from_name", "JuniorCode");
                $smtpReady = $smtpHost && $smtpUser && $smtpPass;

                $notifMsg = "Your class (" . htmlspecialchars($student_name) . " / " . htmlspecialchars($teacher_name) . ") was updated: $changesSummary.";

                /* ── Notify teacher ── */
                if ($teacher_id > 0) {
                    addNotification($conn, $teacher_id, "class", "Class updated", $notifMsg);
                    if ($smtpReady) {
                        $tStmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                        if ($tStmt) {
                            $tStmt->bind_param("i", $teacher_id);
                            $tStmt->execute();
                            $tEmail = trim($tStmt->get_result()->fetch_assoc()["email"] ?? "");
                            $tStmt->close();
                            if ($tEmail) {
                                $html = buildClassUpdatedEmail($teacher_name, $teacher_name, $student_name, $class_date, $class_time, $type, $changesSummary);
                                (new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName))
                                    ->send($tEmail, $teacher_name, "Class updated — $student_name on " . date("d M Y", strtotime($class_date)), $html);
                            }
                        }
                    }
                }

                /* ── Notify student ── */
                $sStmt = $conn->prepare("SELECT id, email FROM users WHERE username = ? AND role = 'student' LIMIT 1");
                if ($sStmt) {
                    $sStmt->bind_param("s", $student_name);
                    $sStmt->execute();
                    $sRow = $sStmt->get_result()->fetch_assoc();
                    $sStmt->close();
                    if ($sRow) {
                        addNotification($conn, (int)$sRow["id"], "class", "Class updated", $notifMsg);
                        if ($smtpReady) {
                            $sEmail = trim($sRow["email"] ?? "");
                            if ($sEmail) {
                                $html = buildClassUpdatedEmail($student_name, $teacher_name, $student_name, $class_date, $class_time, $type, $changesSummary);
                                (new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName))
                                    ->send($sEmail, $student_name, "Class updated — " . date("d M Y", strtotime($class_date)), $html);
                            }
                        }
                    }
                }

                header("Location: manage_classes.php?updated=1");
                exit();
            } else {
                $message = "Error updating class: " . $stmt2->error;
            }
        }
    } else {
        $message = "Please fill all required fields.";
    }
}

function isActive($page, $current) { return $page === $current ? "active" : ""; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Class | Admin</title>
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
        radial-gradient(circle at top left, rgba(37,99,235,0.08), transparent 22%),
        radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
        linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
      color: var(--dark);
    }
    .app-shell { min-height: 100vh; display: flex; }
    .sidebar {
      width: 285px;
      background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
      color: white; padding:  0;
      position: sticky; top: 0; height: 100vh; flex-shrink: 0;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow-y: auto;
  display: flex; flex-direction: column;
    }
    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow-y: auto; }
.sidebar-bottom { padding: 16px 18px; }
    .sidebar-top-area { padding: 0 18px 18px; }
    .brand-box { display: flex; align-items: center; gap: 12px; padding: 0 4px 22px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }
    .logo-img { width: 55px; height: 55px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
    .brand-title { font-weight: 900; font-size: 1.1rem; line-height: 1.15; }
    .brand-sub { font-size: 0.75rem; color: rgba(255,255,255,0.55); letter-spacing: 1px; margin-top: 3px; }
    .nav-title { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1.3px; color: rgba(255,255,255,0.45); margin: 20px 10px 10px; font-weight: 700; }
    .nav-custom { display: flex; flex-direction: column; gap: 4px; }
    .nav-link-custom {
      display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.78);
      text-decoration: none; padding: 12px 14px; border-radius: 14px; transition: all 0.25s; font-weight: 700;
    }
    .nav-link-custom:hover { background: rgba(255,255,255,0.08); color: white; }
    .nav-link-custom.active { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
    .nav-icon { width: 32px; height: 32px; border-radius: 10px; background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .main-content { flex: 1; padding: 26px; }
    .hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
    .hamburger-btn:hover { background:#f1f5f9; }
    .hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }
    .topbar {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 24px; padding: 18px 20px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 22px; box-shadow: var(--shadow);
    }
    .topbar h1 { font-size: 1.6rem; font-weight: 900; margin: 0; color: white; }
    .topbar p  { margin: 4px 0 0; color: rgba(255,255,255,0.8); }
    .admin-badge { background: rgba(255,255,255,0.15); color: white; border-radius: 12px; border: 1px solid rgba(255,255,255,0.2); padding: 10px 18px; font-weight: 800; white-space: nowrap; }
    .card-box { background: white; padding: 28px; border-radius: 24px; box-shadow: var(--shadow); border: 1px solid #edf4ff; max-width: 750px; }
    .panel-title { font-size: 1.15rem; font-weight: 900; margin-bottom: 22px; color: var(--dark); padding-bottom: 14px; border-bottom: 1px solid #edf4ff; }
    .form-label { font-weight: 800; color: #334155; margin-bottom: 6px; }
    .form-control, .form-select {
      border-radius: 14px; font-size: 0.97rem;
      padding: 12px 16px; border: 1px solid #dbeafe; height: auto;
    }
    .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(62,80,119,0.13); }
    .btn-main {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white; border: none; border-radius: 14px;
      padding: 12px 24px; font-weight: 900; cursor: pointer; font-size: 0.95rem;
    }
    .btn-main:hover { color: white; opacity: 0.93; }
    .btn-back { background: #f1f5f9; color: #334155; border: none; border-radius: 14px; padding: 12px 24px; font-weight: 800; text-decoration: none; display: inline-block; font-size: 0.95rem; }
    .btn-back:hover { background: #e2e8f0; color: #0f172a; }

    /* Zoom preview */
    .zoom-preview {
      display: flex; align-items: center; gap: 10px;
      background: #f0fdf4; border: 1px solid #86efac;
      border-radius: 14px; padding: 14px 16px; margin-top: 10px;
    }
    .zoom-preview a {
      display: inline-flex; align-items: center; gap: 8px;
      background: #2D8CFF; color: white; font-weight: 800;
      border-radius: 10px; padding: 9px 18px; font-size: 0.92rem;
      text-decoration: none; white-space: nowrap;
    }
    .zoom-preview a:hover { background: #1a6fd4; color: white; }
    .zoom-url {
      flex: 1; font-size: 0.82rem; color: #166534;
      word-break: break-all; font-family: monospace;
    }
    .btn-copy {
      background: #dcfce7; border: 1px solid #86efac; border-radius: 8px;
      padding: 6px 10px; color: #15803d; cursor: pointer; font-size: 0.82rem; font-weight: 700;
      white-space: nowrap;
    }
    .btn-copy:hover { background: #bbf7d0; }
    .btn-copy.copied { background: #15803d; color: #fff; border-color: #15803d; }

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
      <a href="manage_classes.php"   class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
      <a href="teacher_earnings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
      <a href="available_slots.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
      <a href="courses_home.php"          class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
      <a href="reports.php"          class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
      <a href="admin_certificates.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
    </div>
      </div>
      <div class="sidebar-bottom">
        <a href="settings.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
        <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
        <a href="logout.php"           class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
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
        <h1>Edit Class</h1>
        <p>Update class details.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <div class="card-box">
      <div class="panel-title"><i class="fas fa-pen me-2" style="color:var(--primary)"></i>Class #<?= $id ?></div>

      <?php if ($message !== ""): ?>
        <div class="alert alert-danger mb-3"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="row g-3">

          <div class="col-md-6">
            <label class="form-label">Teacher</label>
            <select name="teacher_id" class="form-select" required onchange="syncTeacher(this)">
              <option value="">Select teacher…</option>
              <?php foreach ($teachers as $t):
                $selectedByid  = !empty($class['teacher_id']) && (int)$class['teacher_id'] === (int)$t['id'];
                $selectedByName = !$selectedByid && strtolower($class['teacher_name']) === strtolower($t['username']);
              ?>
                <option value="<?= $t['id'] ?>"
                        data-name="<?= htmlspecialchars($t['username']) ?>"
                        <?= ($selectedByid || $selectedByName) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['username']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="teacher_name" id="teacher_name_hidden"
                   value="<?= htmlspecialchars($class['teacher_name']) ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Student Name</label>
            <input type="text" name="student_name" class="form-control"
                   value="<?= htmlspecialchars($class["student_name"]) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Class Date</label>
            <input type="date" name="class_date" class="form-control"
                   value="<?= htmlspecialchars($class["class_date"]) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Class Time</label>
            <input type="time" name="class_time" class="form-control"
                   value="<?= htmlspecialchars($class["class_time"]) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="type" class="form-select" required>
              <?php foreach (["Paid","Demo","Half Pay","No Pay","Demo Enrolled","Demo Pending","Demo Other"] as $opt): ?>
                <option value="<?= $opt ?>" <?= $class["type"] === $opt ? "selected" : "" ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Details</label>
            <textarea name="details" class="form-control" rows="3"><?= htmlspecialchars($class["details"]) ?></textarea>
          </div>

          <input type="hidden" name="zoom_link" value="<?= htmlspecialchars($class["zoom_link"] ?? "") ?>">

          <div class="col-12 d-flex gap-3 flex-wrap mt-2">
            <button type="submit" class="btn-main">
              <i class="fas fa-floppy-disk me-1"></i> Update Class
            </button>
            <a href="manage_classes.php" class="btn-back">Cancel</a>
          </div>

        </div>
      </form>
    </div>
  </main>
</div>

<script>
function syncTeacher(select) {
  const opt = select.options[select.selectedIndex];
  document.getElementById('teacher_name_hidden').value = opt.dataset.name || '';
}

function updateZoomPreview(url) {
  const preview = document.getElementById('zoomPreview');
  const openBtn = document.getElementById('zoomOpenBtn');
  const urlText = document.getElementById('zoomUrlText');

  if (url.trim()) {
    preview.style.display = 'flex';
    openBtn.href = url;
    urlText.textContent = url;
  } else {
    preview.style.display = 'none';
  }
}

function copyZoom() {
  const url = document.getElementById('zoomInput').value;
  navigator.clipboard.writeText(url).then(() => {
    const btn = document.getElementById('copyBtn');
    btn.classList.add('copied');
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(() => {
      btn.classList.remove('copied');
      btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
    }, 2000);
  });
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>


