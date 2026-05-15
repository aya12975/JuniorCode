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

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName = $_SESSION["username"] ?? "Admin";
$message = "";

/* Remove any auto-generated Jitsi links left over from previous version */
$conn->query("UPDATE classes SET zoom_link = NULL WHERE zoom_link LIKE '%meet.jit.si%'");

/* Auto-add missing columns */
$colCheck = $conn->query("SHOW COLUMNS FROM classes LIKE 'zoom_link'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN zoom_link VARCHAR(500) DEFAULT NULL");
}
$colCheck2 = $conn->query("SHOW COLUMNS FROM classes LIKE 'teacher_id'");
if ($colCheck2 && $colCheck2->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN teacher_id INT DEFAULT NULL");
}
$colCheck3 = $conn->query("SHOW COLUMNS FROM classes LIKE 'type'");
if ($colCheck3 && $colCheck3->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN type VARCHAR(100) NOT NULL DEFAULT ''");
}
$colCheck4 = $conn->query("SHOW COLUMNS FROM classes LIKE 'details'");
if ($colCheck4 && $colCheck4->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN details TEXT NOT NULL DEFAULT ''");
}
/* Backfill teacher_id for any existing rows that were added before this fix */
$conn->query("
    UPDATE classes c
    JOIN users u ON LOWER(u.username) = LOWER(c.teacher_name)
    SET c.teacher_id = u.id
    WHERE c.teacher_id IS NULL AND u.role = 'teacher'
");

/* Fetch real teachers for the dropdown */
$teachers = [];
$tResult = $conn->query("SELECT id, username FROM users WHERE role = 'teacher' ORDER BY username ASC");
if ($tResult) {
    while ($row = $tResult->fetch_assoc()) {
        $teachers[] = $row;
    }
}

/* Add new class */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacher_id   = (int)($_POST["teacher_id"] ?? 0);
    $teacher_name = trim($_POST["teacher_name"] ?? "");
    $student_name = trim($_POST["student_name"] ?? "");
    $class_date   = $_POST["class_date"] ?? "";
    $class_time   = $_POST["class_time"] ?? "";
    $type         = trim($_POST["type"] ?? "");
    $details      = trim($_POST["details"] ?? "");
    $zoom_link    = trim($_POST["zoom_link"] ?? "");

    if ($teacher_id > 0 && $student_name !== "" && $class_date !== "" && $class_time !== "" && $type !== "") {
        $stmt = $conn->prepare("
            INSERT INTO classes (teacher_id, teacher_name, student_name, class_date, class_time, type, details, zoom_link)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {
            $stmt->bind_param("isssssss", $teacher_id, $teacher_name, $student_name, $class_date, $class_time, $type, $details, $zoom_link);

            if ($stmt->execute()) {
                $smtpHost  = getAdminSetting($conn, "smtp_host",      "");
                $smtpPort  = (int)getAdminSetting($conn, "smtp_port", 587);
                $smtpUser  = getAdminSetting($conn, "smtp_user",      "");
                $smtpPass  = getAdminSetting($conn, "smtp_pass",      "");
                $fromName  = getAdminSetting($conn, "smtp_from_name", "JuniorCode");
                $smtpReady = $smtpHost && $smtpUser && $smtpPass;
                $timeLabel = date("h:i A", strtotime($class_time));
                $classLabel = date("d M Y", strtotime($class_date)) . " at " . date("g:i A", strtotime($class_time));

                // In-app — teacher
                addNotification($conn, $teacher_id, "class",
                    "New class scheduled",
                    "A new class with student <strong>" . htmlspecialchars($student_name) . "</strong> has been scheduled for $classLabel."
                );

                // In-app + email — student
                $sRow = $conn->prepare("SELECT id, email FROM users WHERE username = ? AND role = 'student' LIMIT 1");
                $sEmail = "";
                if ($sRow) {
                    $sRow->bind_param("s", $student_name);
                    $sRow->execute();
                    $sData = $sRow->get_result()->fetch_assoc();
                    $sRow->close();
                    if ($sData) {
                        addNotification($conn, (int)$sData["id"], "class",
                            "New class scheduled",
                            "A new class with <strong>" . htmlspecialchars($teacher_name) . "</strong> has been scheduled for $classLabel."
                        );
                        $sEmail = trim($sData["email"] ?? "");
                    }
                }

                if ($smtpReady) {
                    $subject = "New class scheduled — $class_date at $timeLabel";

                    // Email teacher
                    $tRow = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                    if ($tRow) {
                        $tRow->bind_param("i", $teacher_id);
                        $tRow->execute();
                        $tEmail = trim($tRow->get_result()->fetch_assoc()["email"] ?? "");
                        $tRow->close();
                        if ($tEmail) {
                            $html = buildClassNotificationEmail($teacher_name, $class_date, $class_time, $student_name, $type, $details, $zoom_link);
                            (new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName))->send($tEmail, $teacher_name, $subject, $html);
                        }
                    }

                    // Email student
                    if ($sEmail) {
                        $html = buildStudentClassNotificationEmail($student_name, $teacher_name, $class_date, $class_time, $type, $zoom_link);
                        (new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName))->send($sEmail, $student_name, "Your class is confirmed — $class_date at $timeLabel", $html);
                    }
                }

                // Session milestone check — notify student every 8 sessions
                $cntStmt = $conn->prepare("SELECT COUNT(*) AS total FROM classes WHERE student_name = ?");
                if ($cntStmt) {
                    $cntStmt->bind_param("s", $student_name);
                    $cntStmt->execute();
                    $sessionCount = (int)$cntStmt->get_result()->fetch_assoc()["total"];
                    $cntStmt->close();
                    if ($sessionCount > 0 && $sessionCount % 8 === 0) {
                        $msStmt = $conn->prepare("SELECT id, email FROM users WHERE username = ? AND role = 'student' LIMIT 1");
                        if ($msStmt) {
                            $msStmt->bind_param("s", $student_name);
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
                                        $html = buildSessionMilestoneEmail($student_name, $sessionCount);
                                        (new Mailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName))
                                            ->send($msEmail, $student_name, "You've completed $sessionCount sessions — time to renew!", $html);
                                    }
                                }
                            }
                        }
                    }
                }

                header("Location: manage_classes.php?added=1");
                exit();
            } else {
                $message = "Error adding class.";
            }
        } else {
            $message = "Prepare failed: " . $conn->error;
        }
    } else {
        $message = "Please fill all required fields.";
    }
}

/* Get all classes */
$classes = [];
$result = $conn->query("SELECT * FROM classes ORDER BY class_date DESC, class_time ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Classes | JuniorCode Admin</title>
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
      flex-shrink: 0;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow-y: auto;
      display: flex; flex-direction: column;
    }
    .sidebar-bottom { padding: 16px 18px; }

    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow-y: auto; }

    .sidebar-top-area { padding: 0 18px 18px; }
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

    .topbar,
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
    }

    .topbar h1 {
      font-size: 1.8rem;
      font-weight: 900;
      margin: 0;
      color: white;
    }

    .topbar p {
      margin: 4px 0 0;
      color: rgba(255,255,255,0.85);
    }

    .admin-badge {
      background: rgba(255,255,255,0.15);
      color: white;
      border-radius: 12px; border: 1px solid rgba(255,255,255,0.2);
      padding: 10px 18px;
      font-weight: 800;
    }

    .panel-card {
      padding: 22px;
      margin-bottom: 24px;
    }

    .panel-title {
      font-size: 1.2rem;
      font-weight: 900;
      margin: 0 0 18px 0;
    }

    .form-control,
    .form-select {
      border-radius: 14px;
      padding: 12px 14px;
      border: 1px solid #dbe4f0;
    }

    .btn-main {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border: none;
      color: white;
      font-weight: 800;
      border-radius: 14px;
      padding: 10px 18px;
      text-decoration: none;
      display: inline-block;
    }

    .btn-main:hover {
      color: white;
      opacity: 0.95;
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
    }

    .badge-paid {
      background: #dcfce7;
      color: #166534;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 800;
    }

    .badge-demo {
      background: #fef3c7;
      color: #92400e;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 800;
    }

    .badge-other {
      background: #e0e7ff;
      color: #3730a3;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 800;
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

    .btn-zoom {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #2D8CFF;
      color: white;
      font-weight: 700;
      border-radius: 10px;
      padding: 6px 12px;
      font-size: 0.82rem;
      text-decoration: none;
      transition: all 0.2s ease;
      white-space: nowrap;
    }

    .btn-zoom:hover {
      background: #1a6fd4;
      color: white;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(45,140,255,0.3);
    }

    .zoom-empty {
      color: #94a3b8;
      font-size: 0.85rem;
    }

    .filter-tab {
      border: 2px solid var(--border);
      background: white;
      border-radius: 999px;
      padding: 7px 16px;
      font-weight: 700;
      font-size: 0.85rem;
      color: var(--muted);
      cursor: pointer;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .filter-tab:hover { border-color: var(--primary); color: var(--primary); }
    .filter-tab.active { background: var(--primary); border-color: var(--primary); color: white; }
    .filter-tab.past-tab.active { background: #64748b; border-color: #64748b; }
    .tab-count {
      background: rgba(0,0,0,0.12);
      border-radius: 999px;
      padding: 1px 7px;
      font-size: 0.78rem;
    }
    .filter-tab.active .tab-count { background: rgba(255,255,255,0.25); }

    .btn-gen-zoom {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: #f0fdf4;
      border: 1px solid #86efac;
      color: #16a34a;
      font-weight: 700;
      border-radius: 10px;
      padding: 5px 11px;
      font-size: 0.8rem;
      cursor: pointer;
      transition: all 0.2s;
      white-space: nowrap;
    }
    .btn-gen-zoom:hover { background: #dcfce7; }
    .btn-gen-zoom:disabled { opacity: 0.6; cursor: not-allowed; }

    @media (max-width: 991px) {
      .app-shell {
        flex-direction: column;
      }

      .sidebar {
        width: 100%;
        height: auto;
        position: relative;
      }

      .main-content {
        padding: 18px;
      }

      .topbar {
        flex-direction: column;
        align-items: flex-start;
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
        <a href="admin_dashboard.php" class="nav-link-custom <?php echo isActive('admin_dashboard.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-house"></i></span>
          <span>Dashboard</span>
        </a>

        <a href="manage_users.php" class="nav-link-custom <?php echo isActive('manage_users.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-users"></i></span>
          <span>Manage Users</span>
        </a>

        <a href="admin_teacher_students.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span>
        </a>

          <a href="admin_enrollments.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span>
          </a>

        <a href="manage_classes.php" class="nav-link-custom <?php echo isActive('manage_classes.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-book"></i></span>
          <span>Manage Classes</span>
        </a>

        <a href="teacher_earnings.php" class="nav-link-custom <?php echo isActive('teacher_earnings.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
          <span>Teacher Earnings</span>
        </a>

        <a href="available_slots.php" class="nav-link-custom <?php echo isActive('available_slots.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
          <span>Available Slots</span>
        </a>

        <a href="courses_home.php" class="nav-link-custom <?php echo isActive('courses.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
          <span>Courses</span>
        </a>

        <a href="reports.php" class="nav-link-custom <?php echo isActive('reports.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
          <span>Reports</span>
        </a>
        <a href="admin_certificates.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-award"></i></span>
          <span>Certificates</span>
        </a>

      </div>
      </div>
      <div class="sidebar-bottom">
        <a href="settings.php" class="nav-link-custom <?php echo isActive('settings.php', $currentPage); ?>">
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
          <h1>Manage Classes</h1>
          <p>Admin can add, edit, and delete teacher classes from here.</p>
        </div>
        <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?php echo htmlspecialchars($adminName); ?> &nbsp;·&nbsp; <?php echo date("d M Y"); ?></div>
      </div>

      <?php if (isset($_GET["added"])): ?>
        <div class="alert alert-success">Class added successfully.</div>
      <?php endif; ?>

      <?php if (isset($_GET["updated"])): ?>
        <div class="alert alert-success">Class updated successfully.</div>
      <?php endif; ?>

      <?php if (isset($_GET["deleted"])): ?>
        <div class="alert alert-success">Class deleted successfully.</div>
      <?php endif; ?>

      <?php if ($message !== ""): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <section class="panel-card">
        <h2 class="panel-title">Add New Class</h2>

        <form method="POST">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Teacher</label>
              <select name="teacher_id" class="form-select" required onchange="syncTeacherName(this)">
                <option value="">Choose teacher</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?php echo $t['id']; ?>"
                          data-name="<?php echo htmlspecialchars($t['username']); ?>">
                    <?php echo htmlspecialchars($t['username']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="teacher_name" id="teacher_name_hidden">
            </div>

            <div class="col-md-4">
              <label class="form-label">Student Name</label>
              <input type="text" name="student_name" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Type</label>
              <select name="type" class="form-select" required>
                <option value="">Choose type</option>
                <option value="Paid">Paid</option>
                <option value="Demo">Demo</option>
                <option value="Half Pay">Half Pay</option>
                <option value="No Pay">No Pay</option>
                <option value="Demo Enrolled">Demo Enrolled</option>
                <option value="Demo Pending">Demo Pending</option>
                <option value="Demo Other">Demo Other</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Class Date</label>
              <input type="date" name="class_date" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Class Time</label>
              <input type="time" name="class_time" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Details</label>
              <input type="text" name="details" class="form-control" placeholder="Python basics / Demo class / etc.">
            </div>

            <div class="col-12">
              <button type="submit" class="btn-main">Add Class</button>
            </div>
          </div>
        </form>
      </section>

      <section class="panel-card">
        <?php
          $today = date("Y-m-d");
          $counts = ['all' => count($classes), 'upcoming' => 0, 'today' => 0, 'past' => 0];
          foreach ($classes as $c) {
              $d = $c["class_date"] ?? "";
              if ($d === $today)        $counts['today']++;
              elseif ($d > $today)      $counts['upcoming']++;
              else                      $counts['past']++;
          }
        ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
          <h2 class="panel-title mb-0">All Classes</h2>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="filter-tab active" onclick="filterTab('all',this)">All <span class="tab-count"><?php echo $counts['all']; ?></span></button>
            <button class="filter-tab" onclick="filterTab('today',this)">Today <span class="tab-count"><?php echo $counts['today']; ?></span></button>
            <button class="filter-tab" onclick="filterTab('upcoming',this)">Upcoming <span class="tab-count"><?php echo $counts['upcoming']; ?></span></button>
            <button class="filter-tab past-tab" onclick="filterTab('past',this)">Past <span class="tab-count"><?php echo $counts['past']; ?></span></button>
          </div>
        </div>

        <?php if (!empty($classes)): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Teacher</th>
                  <th>Student</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Type</th>
                  <th>Details</th>
                  <th>Zoom</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($classes as $class):
                  $cd = $class["class_date"] ?? "";
                  if ($cd === $today)   $when = "today";
                  elseif ($cd > $today) $when = "upcoming";
                  else                  $when = "past";
                ?>
                  <tr data-when="<?php echo $when; ?>" <?php echo $when === 'past' ? 'style="opacity:0.72"' : ''; ?>>
                    <td><?php echo htmlspecialchars($class["id"]); ?></td>
                    <td><?php echo htmlspecialchars($class["teacher_name"]); ?></td>
                    <td><?php echo htmlspecialchars($class["student_name"]); ?></td>
                    <td>
                      <?php echo htmlspecialchars($class["class_date"]); ?>
                      <?php if ($when === 'today'): ?>
                        <span style="background:#2563eb;color:#fff;border-radius:999px;font-size:0.68rem;padding:2px 7px;font-weight:700;margin-left:4px;">Today</span>
                      <?php elseif ($when === 'past'): ?>
                        <span style="background:#f1f5f9;color:#94a3b8;border-radius:999px;font-size:0.68rem;padding:2px 7px;font-weight:700;margin-left:4px;">Past</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($class["class_time"]); ?></td>
                    <td>
                      <?php
                        $type = strtolower(trim($class["type"]));
                        if ($type === "paid") {
                            echo '<span class="badge-paid">Paid</span>';
                        } elseif ($type === "demo") {
                            echo '<span class="badge-demo">Demo</span>';
                        } else {
                            echo '<span class="badge-other">' . htmlspecialchars($class["type"]) . '</span>';
                        }
                      ?>
                    </td>
                    <td><?php echo htmlspecialchars($class["details"]); ?></td>
                    <td id="zoom-cell-<?php echo $class['id']; ?>">
                      <?php if (!empty($class["zoom_link"])): ?>
                        <a href="<?php echo htmlspecialchars($class["zoom_link"]); ?>" target="_blank" rel="noopener" class="btn-zoom">
                          <i class="fas fa-video"></i> Open Zoom
                        </a>
                      <?php else: ?>
                        <button class="btn-gen-zoom" onclick="generateZoom(<?php echo $class['id']; ?>, this)">
                          <i class="fas fa-wand-magic-sparkles"></i> Generate
                        </button>
                      <?php endif; ?>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-warning" onclick="openEditModal(<?php echo $class['id']; ?>)">Edit</button>
                      <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars(addslashes($class['teacher_name'])); ?>', '<?php echo htmlspecialchars(addslashes($class['student_name'])); ?>', '<?php echo htmlspecialchars($class['class_date']); ?>')">Delete</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-box">No classes found.</div>
        <?php endif; ?>
      </section>
    </main>
  </div>

<script>
  function syncTeacherName(select) {
    const opt = select.options[select.selectedIndex];
    document.getElementById('teacher_name_hidden').value = opt.dataset.name || '';
  }

  function filterTab(filter, btn) {
    document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('tbody tr[data-when]').forEach(row => {
      row.style.display = (filter === 'all' || row.dataset.when === filter) ? '' : 'none';
    });
  }

  function generateZoom(classId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…';

    fetch('update_class_zoom.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'class_id=' + classId
    })
    .then(r => r.json())
    .then(data => {
      const cell = document.getElementById('zoom-cell-' + classId);
      if (data.success) {
        cell.innerHTML = '<a href="' + data.join_url + '" target="_blank" rel="noopener" class="btn-zoom"><i class="fas fa-video"></i> Open Zoom</a>';
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate';
        alert('Error: ' + (data.message || 'Could not generate link'));
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate';
      alert('Network error. Please try again.');
    });
  }
</script>

<script>
const classesData  = <?= json_encode(array_column($classes ?? [], null, 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const teachersData = <?= json_encode($teachers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const typeOptions  = ["Paid","Demo","Half Pay","No Pay","Demo Enrolled","Demo Pending","Demo Other"];

function openEditModal(classId) {
  const c = classesData[classId];
  if (!c) return;

  document.getElementById('em-id').value           = c.id;
  document.getElementById('em-student').value      = c.student_name  || '';
  document.getElementById('em-date').value         = c.class_date    || '';
  document.getElementById('em-time').value         = c.class_time    || '';
  document.getElementById('em-details').value      = c.details       || '';

  // Build teacher dropdown
  const tSel = document.getElementById('em-teacher');
  tSel.innerHTML = '<option value="">Select teacher…</option>';
  teachersData.forEach(t => {
    const opt = document.createElement('option');
    opt.value        = t.id;
    opt.dataset.name = t.username;
    opt.textContent  = t.username;
    if (t.id == c.teacher_id || t.username === c.teacher_name) opt.selected = true;
    tSel.appendChild(opt);
  });

  // Build type dropdown
  const typeSel = document.getElementById('em-type');
  typeSel.innerHTML = '';
  typeOptions.forEach(opt => {
    const o = document.createElement('option');
    o.value = opt; o.textContent = opt;
    if (opt === c.type) o.selected = true;
    typeSel.appendChild(o);
  });

  document.getElementById('em-error').textContent = '';
  document.getElementById('edit-modal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('edit-modal').style.display = 'none';
}

function saveEdit() {
  const tSel   = document.getElementById('em-teacher');
  const selOpt = tSel.options[tSel.selectedIndex];
  const body   = new URLSearchParams({
    id:           document.getElementById('em-id').value,
    teacher_id:   tSel.value,
    teacher_name: selOpt ? (selOpt.dataset.name || selOpt.textContent) : '',
    student_name: document.getElementById('em-student').value,
    class_date:   document.getElementById('em-date').value,
    class_time:   document.getElementById('em-time').value,
    type:         document.getElementById('em-type').value,
    details:      document.getElementById('em-details').value,
  });

  const saveBtn = document.getElementById('em-save-btn');
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

  fetch('update_class_handler.php', { method:'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        closeEditModal();
        location.reload();
      } else {
        document.getElementById('em-error').textContent = data.message || 'Update failed.';
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-floppy-disk"></i> Save Changes';
      }
    })
    .catch(() => {
      document.getElementById('em-error').textContent = 'Network error. Please try again.';
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="fas fa-floppy-disk"></i> Save Changes';
    });
}
</script>

<!-- Edit Class Modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:22px;padding:0;max-width:580px;width:94%;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,0.22);">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#3e5077,#143674);border-radius:22px 22px 0 0;padding:22px 28px;display:flex;justify-content:space-between;align-items:center;">
      <div>
        <div style="font-size:1.1rem;font-weight:900;color:#fff;"><i class="fas fa-pen me-2"></i>Edit Class</div>
        <div style="color:rgba(255,255,255,0.7);font-size:0.82rem;margin-top:2px;">Update class details below</div>
      </div>
      <button onclick="closeEditModal()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;border-radius:10px;width:34px;height:34px;cursor:pointer;font-size:1.1rem;">&times;</button>
    </div>

    <!-- Body -->
    <div style="padding:24px 28px;">
      <input type="hidden" id="em-id">
      <div id="em-error" style="color:#dc2626;font-weight:700;font-size:0.88rem;margin-bottom:10px;"></div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">

        <div>
          <label style="font-weight:800;font-size:0.88rem;color:#334155;display:block;margin-bottom:5px;">Teacher</label>
          <select id="em-teacher" style="width:100%;border:1px solid #dbeafe;border-radius:12px;padding:10px 12px;font-size:0.93rem;">
            <option value="">Select teacher…</option>
          </select>
        </div>

        <div>
          <label style="font-weight:800;font-size:0.88rem;color:#334155;display:block;margin-bottom:5px;">Student Name</label>
          <input id="em-student" type="text" style="width:100%;border:1px solid #dbeafe;border-radius:12px;padding:10px 12px;font-size:0.93rem;box-sizing:border-box;">
        </div>

        <div>
          <label style="font-weight:800;font-size:0.88rem;color:#334155;display:block;margin-bottom:5px;">Date</label>
          <input id="em-date" type="date" style="width:100%;border:1px solid #dbeafe;border-radius:12px;padding:10px 12px;font-size:0.93rem;box-sizing:border-box;">
        </div>

        <div>
          <label style="font-weight:800;font-size:0.88rem;color:#334155;display:block;margin-bottom:5px;">Time</label>
          <input id="em-time" type="time" style="width:100%;border:1px solid #dbeafe;border-radius:12px;padding:10px 12px;font-size:0.93rem;box-sizing:border-box;">
        </div>

        <div>
          <label style="font-weight:800;font-size:0.88rem;color:#334155;display:block;margin-bottom:5px;">Type</label>
          <select id="em-type" style="width:100%;border:1px solid #dbeafe;border-radius:12px;padding:10px 12px;font-size:0.93rem;"></select>
        </div>

        <div style="grid-column:1/-1;">
          <label style="font-weight:800;font-size:0.88rem;color:#334155;display:block;margin-bottom:5px;">Details</label>
          <textarea id="em-details" rows="3" style="width:100%;border:1px solid #dbeafe;border-radius:12px;padding:10px 12px;font-size:0.93rem;resize:vertical;box-sizing:border-box;"></textarea>
        </div>

      </div>

      <!-- Footer buttons -->
      <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
        <button onclick="closeEditModal()" style="background:#f1f5f9;color:#334155;border:none;border-radius:12px;padding:11px 22px;font-weight:800;cursor:pointer;">Cancel</button>
        <button id="em-save-btn" onclick="saveEdit()" style="background:linear-gradient(135deg,#3e5077,#143674);color:#fff;border:none;border-radius:12px;padding:11px 22px;font-weight:800;cursor:pointer;">
          <i class="fas fa-floppy-disk"></i> Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:22px;padding:0;max-width:420px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,0.22);">

    <div style="background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:22px 22px 0 0;padding:22px 28px;display:flex;justify-content:space-between;align-items:center;">
      <div style="font-size:1.1rem;font-weight:900;color:#fff;"><i class="fas fa-trash me-2"></i>Delete Class</div>
      <button onclick="closeDeleteModal()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;border-radius:10px;width:34px;height:34px;cursor:pointer;font-size:1.1rem;">&times;</button>
    </div>

    <div style="padding:26px 28px;">
      <div style="font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px;">Are you sure you want to delete this class?</div>
      <div id="dm-info" style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:12px 16px;font-size:0.9rem;color:#92400e;font-weight:600;margin-bottom:20px;"></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button onclick="closeDeleteModal()" style="background:#f1f5f9;color:#334155;border:none;border-radius:12px;padding:11px 22px;font-weight:800;cursor:pointer;">Cancel</button>
        <button id="dm-confirm-btn" onclick="confirmDelete()" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;border-radius:12px;padding:11px 22px;font-weight:800;cursor:pointer;">
          <i class="fas fa-trash"></i> Delete
        </button>
      </div>
    </div>

  </div>
</div>

<script>
let deleteTargetId = null;

function openDeleteModal(classId, teacher, student, date) {
  deleteTargetId = classId;
  document.getElementById('dm-info').innerHTML =
    '<i class="fas fa-calendar-day me-1"></i> ' + date +
    ' &nbsp;·&nbsp; <strong>' + teacher + '</strong> &amp; ' + student;
  document.getElementById('delete-modal').style.display = 'flex';
}

function closeDeleteModal() {
  deleteTargetId = null;
  document.getElementById('delete-modal').style.display = 'none';
}

function confirmDelete() {
  if (!deleteTargetId) return;
  const btn = document.getElementById('dm-confirm-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting…';

  fetch('delete_class_handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id=' + deleteTargetId
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeDeleteModal();
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Could not delete.'));
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-trash"></i> Delete';
    }
  })
  .catch(() => {
    alert('Network error. Please try again.');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-trash"></i> Delete';
  });
}
</script>

<script src="logout-modal.js"></script>
</body>
</html>

