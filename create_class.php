<?php
session_start();
require_once "db.php";
require_once "zoom_helper.php";
require_once "admin_prefs.php";
require_once "mailer.php";
require_once "notifications.php";
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";
$id = (int)($_GET["id"] ?? 0);

$chkZoom = $conn->query("SHOW COLUMNS FROM users LIKE 'zoom_personal_link'");
if ($chkZoom && $chkZoom->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN zoom_personal_link TEXT NOT NULL DEFAULT ''");
}

// Ensure classes table has all required columns
$chkType = $conn->query("SHOW COLUMNS FROM classes LIKE 'type'");
if ($chkType && $chkType->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN type VARCHAR(100) NOT NULL DEFAULT ''");
}
$chkDetails = $conn->query("SHOW COLUMNS FROM classes LIKE 'details'");
if ($chkDetails && $chkDetails->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN details TEXT NOT NULL DEFAULT ''");
}
$chkZoomLink = $conn->query("SHOW COLUMNS FROM classes LIKE 'zoom_link'");
if ($chkZoomLink && $chkZoomLink->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN zoom_link TEXT NOT NULL DEFAULT ''");
}

$stmt = $conn->prepare("
    SELECT
        ta.id,
        ta.teacher_id,
        ta.available_date,
        ta.available_time,
        ta.status,
        u.username         AS teacher_name,
        u.email            AS teacher_email,
        u.zoom_personal_link AS teacher_zoom_link
    FROM teacher_availability ta
    JOIN users u ON ta.teacher_id = u.id
    WHERE ta.id = ?
");
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("i", $id);
$stmt->execute();
$slot = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$slot) die("Slot not found");

$zoomReady = zoomCredentialsSet($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacherId   = (int)$slot["teacher_id"];
    $teacherName = $slot["teacher_name"];
    $date        = $slot["available_date"];
    $time        = $slot["available_time"];

    $student      = trim($_POST["student_name"]  ?? "");
    $type         = trim($_POST["type"]          ?? "");
    $details      = trim($_POST["details"]       ?? "");
    $zoom_link    = trim($_POST["zoom_link"]     ?? "");
    $teacherEmail = trim($_POST["teacher_email"] ?? "");
    $duration     = (int)($_POST["duration"]     ?? 60);

    if ($student === "" || $type === "") {
        die("Student name and type are required.");
    }

    // Auto-generate Zoom link via API if not manually provided
    if ($zoom_link === "" && $zoomReady) {
        $topic   = "JuniorCode — " . $teacherName . " & " . $student;
        $joinUrl = createZoomMeeting($conn, $topic, $date, $time, $duration, $zoomErr);
        if ($joinUrl) {
            $zoom_link = $joinUrl;
        }
    }

    $insert = $conn->prepare("
        INSERT INTO classes (teacher_id, teacher_name, student_name, class_date, class_time, type, details, zoom_link)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$insert) die("Insert prepare failed: " . $conn->error);
    $insert->bind_param("isssssss", $teacherId, $teacherName, $student, $date, $time, $type, $details, $zoom_link);
    $insert->execute();
    $insert->close();

    // In-app notification — teacher
    $classLabel = date("d M Y", strtotime($date)) . " at " . date("g:i A", strtotime($time));
    addNotification($conn, $teacherId, "class",
        "New class scheduled",
        "A new class with student <strong>" . htmlspecialchars($student) . "</strong> has been scheduled for $classLabel."
    );

    // In-app notification — student
    $sIdStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'student' LIMIT 1");
    if ($sIdStmt) {
        $sIdStmt->bind_param("s", $student);
        $sIdStmt->execute();
        $sIdRow = $sIdStmt->get_result()->fetch_assoc();
        $sIdStmt->close();
        if ($sIdRow) {
            addNotification($conn, (int)$sIdRow["id"], "class",
                "New class scheduled",
                "A new class with <strong>" . htmlspecialchars($teacherName) . "</strong> has been scheduled for $classLabel."
            );
        }
    }

    // Load SMTP settings once
    $smtpHost = getAdminSetting($conn, "smtp_host",      "");
    $smtpPort = (int)getAdminSetting($conn, "smtp_port", 587);
    $smtpUser = getAdminSetting($conn, "smtp_user",      "");
    $smtpPass = getAdminSetting($conn, "smtp_pass",      "");
    $fromName = getAdminSetting($conn, "smtp_from_name", "JuniorCode");
    $smtpReady = $smtpHost && $smtpUser && $smtpPass;

    // Notify teacher
    $teacherEmail = trim($slot["teacher_email"] ?? "");
    if ($smtpReady && $teacherEmail) {
        $html   = buildClassNotificationEmail($teacherName, $date, $time, $student, $type, $details, $zoom_link);
        $mailer = new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName);
        $mailer->send($teacherEmail, $teacherName, "New class scheduled — $date at " . date("h:i A", strtotime($time)), $html);
    }

    // Notify student via email (reuse ID row fetched above for in-app notif)
    if ($smtpReady) {
        $sEmailStmt = $conn->prepare("SELECT email FROM users WHERE username = ? AND role = 'student' LIMIT 1");
        if ($sEmailStmt) {
            $sEmailStmt->bind_param("s", $student);
            $sEmailStmt->execute();
            $sEmailRow    = $sEmailStmt->get_result()->fetch_assoc();
            $studentEmail = trim($sEmailRow["email"] ?? "");
            $sEmailStmt->close();
            if ($studentEmail) {
                $html   = buildStudentClassNotificationEmail($student, $teacherName, $date, $time, $type, $zoom_link);
                $mailer = new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName);
                $mailer->send($studentEmail, $student, "Your class is confirmed — $date at " . date("h:i A", strtotime($time)), $html);
            }
        }
    }

    // Session milestone check — notify student every 8 sessions
    $cntStmt = $conn->prepare("SELECT COUNT(*) AS total FROM classes WHERE student_name = ?");
    if ($cntStmt) {
        $cntStmt->bind_param("s", $student);
        $cntStmt->execute();
        $sessionCount = (int)$cntStmt->get_result()->fetch_assoc()["total"];
        $cntStmt->close();
        if ($sessionCount > 0 && $sessionCount % 8 === 0) {
            $msStmt = $conn->prepare("SELECT id, email FROM users WHERE username = ? AND role = 'student' LIMIT 1");
            if ($msStmt) {
                $msStmt->bind_param("s", $student);
                $msStmt->execute();
                $msRow = $msStmt->get_result()->fetch_assoc();
                $msStmt->close();
                if ($msRow) {
                    addNotification($conn, (int)$msRow["id"], "session",
                        "Session milestone reached!",
                        "You've completed <strong>$sessionCount sessions</strong>! Please renew your registration to continue."
                    );
                    if ($smtpReady) {
                        $msEmail = trim($msRow["email"] ?? "");
                        if ($msEmail) {
                            $html = buildSessionMilestoneEmail($student, $sessionCount);
                            (new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName))
                                ->send($msEmail, $student, "You've completed $sessionCount sessions — time to renew!", $html);
                        }
                    }
                }
            }
        }
    }

    $delete = $conn->prepare("DELETE FROM teacher_availability WHERE id = ?");
    $delete->bind_param("i", $id);
    $delete->execute();
    $delete->close();

    header("Location: manage_classes.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Class | Admin</title>
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
  position: sticky; top: 0; height: 100vh;
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
.card-box { background: white; padding: 32px; border-radius: 24px; box-shadow: var(--shadow); border: 1px solid #edf4ff; max-width: 700px; }
.form-label { font-weight: 800; color: #334155; margin-bottom: 6px; display: block; }
.form-control, .form-select {
  height: 50px; border-radius: 14px; font-size: 0.97rem;
  padding: 10px 16px; border: 1px solid #dbeafe;
}
.form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(62,80,119,0.13); }
textarea.form-control { height: auto; }
.btn-main {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: white; border: none; border-radius: 14px;
  padding: 13px 24px; font-weight: 900; cursor: pointer; text-decoration: none; display: inline-block;
}
.btn-main:hover { color: white; opacity: 0.93; }
.btn-back { background: #64748b; color: white; border: none; border-radius: 14px; padding: 13px 24px; font-weight: 900; text-decoration: none; display: inline-block; }
.btn-back:hover { color: white; background: #475569; }

/* Info row (teacher/date/time) */
.info-row {
  display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 22px;
  padding: 16px 18px; background: #f0f7ff; border: 1px solid #bfdbfe;
  border-radius: 16px;
}
.info-item { display: flex; flex-direction: column; gap: 2px; }
.info-label { font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.8px; }
.info-value { font-weight: 800; color: var(--dark); font-size: 0.97rem; }

/* Zoom section */
.zoom-section {
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 16px;
  padding: 18px 20px;
  margin-bottom: 20px;
}
.zoom-section-title {
  font-weight: 900; font-size: 0.95rem; color: #15803d;
  margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
}
.zoom-not-ready {
  background: #fef9c3; border: 1px solid #fde047;
  border-radius: 16px; padding: 14px 18px; margin-bottom: 20px;
  font-size: 0.9rem; color: #854d0e; font-weight: 700;
  display: flex; align-items: center; gap: 10px;
}

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
      <a href="manage_projects.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-folder-open"></i></span><span>Projects</span></a>
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
        <h1>Create Class</h1>
        <p>Fill in the details and generate a Zoom link for the session.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <div class="card-box">

      <!-- Slot info -->
      <div class="info-row">
        <div class="info-item">
          <span class="info-label">Teacher</span>
          <span class="info-value"><?= htmlspecialchars($slot["teacher_name"]) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Date</span>
          <span class="info-value"><?= htmlspecialchars($slot["available_date"]) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Time</span>
          <span class="info-value"><?= htmlspecialchars($slot["available_time"]) ?></span>
        </div>
      </div>

      <form method="POST" id="classForm">

        <div class="mb-3">
          <label class="form-label">Student Name</label>
          <input type="text" name="student_name" class="form-control" placeholder="Enter student name" required>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Type</label>
            <select name="type" class="form-select" required>
              <option value="Paid">Paid</option>
              <option value="Demo">Demo</option>
              <option value="Half Pay">Half Pay</option>
              <option value="No Pay">No Pay</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Duration (minutes)</label>
            <input type="number" name="duration" class="form-control" value="60" min="15" max="300">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Details</label>
          <textarea name="details" class="form-control" rows="3" placeholder="Optional notes…"></textarea>
        </div>

        <input type="hidden" name="teacher_email" value="<?= htmlspecialchars($slot["teacher_email"]) ?>">
        <?php if ($zoomReady): ?>
          <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:12px 16px;font-size:0.88rem;color:#166534;font-weight:700;">
            <i class="fas fa-wand-magic-sparkles me-1"></i>
            A Zoom meeting will be created automatically when you save the class.
          </div>
        <?php endif; ?>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;">
          <button type="submit" class="btn-main">
            <i class="fas fa-plus me-1"></i> Create Class
          </button>
          <a href="manage_classes.php" class="btn-back">Cancel</a>
        </div>

      </form>
    </div>
  </main>
</div>

<script src="logout-modal.js"></script>
</body>
</html>


