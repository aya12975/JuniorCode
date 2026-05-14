<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

/* ── AJAX: auto-create class from slot ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "create_class") {
    header("Content-Type: application/json");
    $slotId = (int)($_POST["slot_id"] ?? 0);

    /* Ensure classes table has all needed columns */
    foreach ([
        "type"      => "VARCHAR(100) NOT NULL DEFAULT ''",
        "details"   => "TEXT NOT NULL DEFAULT ''",
        "zoom_link" => "TEXT NOT NULL DEFAULT ''",
    ] as $col => $def) {
        $chk = $conn->query("SHOW COLUMNS FROM classes LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE classes ADD COLUMN $col $def");
        }
    }

    /* Fetch slot + teacher */
    $stmt = $conn->prepare("
        SELECT ta.id, ta.teacher_id, ta.available_date, ta.available_time,
               u.username AS teacher_name
        FROM teacher_availability ta
        JOIN users u ON ta.teacher_id = u.id
        WHERE ta.id = ?
    ");
    if (!$stmt) { echo json_encode(["success" => false, "message" => $conn->error]); exit(); }
    $stmt->bind_param("i", $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot) { echo json_encode(["success" => false, "message" => "Slot not found"]); exit(); }

    $student = "TBD";
    $type    = "Demo";
    $details = "";
    $zoom    = "";

    $ins = $conn->prepare("
        INSERT INTO classes (teacher_id, teacher_name, student_name, class_date, class_time, type, details, zoom_link)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$ins) { echo json_encode(["success" => false, "message" => $conn->error]); exit(); }
    $ins->bind_param("isssssss",
        $slot["teacher_id"], $slot["teacher_name"],
        $student, $slot["available_date"], $slot["available_time"],
        $type, $details, $zoom
    );
    if (!$ins->execute()) { echo json_encode(["success" => false, "message" => $ins->error]); exit(); }
    $classId = $conn->insert_id;
    $ins->close();

    /* Remove the slot */
    $del = $conn->prepare("DELETE FROM teacher_availability WHERE id = ?");
    $del->bind_param("i", $slotId);
    $del->execute();
    $del->close();

    echo json_encode(["success" => true, "class_id" => $classId]);
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName = $_SESSION["username"] ?? "Admin";

$slots = [];

$result = $conn->query("
    SELECT 
        ta.id,
        ta.available_date,
        ta.available_time,
        ta.status,
        u.username AS teacher_name
    FROM teacher_availability ta
    JOIN users u ON ta.teacher_id = u.id
    WHERE ta.status = 'available'
    ORDER BY ta.available_date ASC, ta.available_time ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $slots[] = $row;
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
  <title>Available Slots | JuniorCode Admin</title>
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
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow: hidden;
      display: flex; flex-direction: column;
    }
    .sidebar-bottom { padding: 16px 18px; border-top: 1px solid rgba(255,255,255,0.1); }

    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow: hidden; }

    .sidebar-top-area { padding: 0 18px 18px; flex: 1; overflow-y: auto; }
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
    }

    .panel-title {
      font-size: 1.2rem;
      font-weight: 900;
      margin: 0 0 18px 0;
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

    .slot-badge {
      background: #dcfce7;
      color: #166534;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 800;
      display: inline-block;
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

    .empty-box {
      text-align: center;
      padding: 26px 18px;
      border-radius: 18px;
      background: #f8fbff;
      color: var(--muted);
      border: 1px dashed #d9e9ff;
      font-weight: 700;
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

        <a href="courses.php" class="nav-link-custom <?php echo isActive('courses.php', $currentPage); ?>">
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
<a href="admin_email_notifications.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-envelope"></i></span>
          <span>Email Notifications</span>
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
          <h1>Available Slots</h1>
          <p>View teacher availability and create classes from open time slots.</p>
        </div>
        <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?php echo htmlspecialchars($adminName); ?> &nbsp;·&nbsp; <?php echo date("d M Y"); ?></div>
      </div>

      <section class="panel-card">
        <h2 class="panel-title">Teacher Available Slots</h2>

        <?php if (!empty($slots)): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Teacher</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($slots as $slot): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($slot["id"]); ?></td>
                    <td><?php echo htmlspecialchars($slot["teacher_name"]); ?></td>
                    <td><?php echo htmlspecialchars($slot["available_date"]); ?></td>
                    <td><?php echo htmlspecialchars($slot["available_time"]); ?></td>
                    <td>
                      <span class="slot-badge"><?php echo htmlspecialchars(ucfirst($slot["status"])); ?></span>
                    </td>
                    <td>
                      <button onclick="createClass(<?php echo $slot['id']; ?>, this)" class="btn-main">
                        <i class="fas fa-plus"></i> Create Class
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-box">No available slots found.</div>
        <?php endif; ?>
      </section>
    </main>
  </div>
<script>
function createClass(slotId, btn) {
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';

  fetch("available_slots.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "action=create_class&slot_id=" + encodeURIComponent(slotId)
  })
  .then(r => r.text())
  .then(text => {
    let data;
    try { data = JSON.parse(text); }
    catch(e) { throw new Error("Server error: " + text.substring(0, 200)); }

    const row = btn.closest("tr");
    if (data.success) {
      row.style.transition = "background 0.3s";
      row.style.background = "#dcfce7";
      row.cells[row.cells.length - 1].innerHTML =
        '<span style="color:#166534;font-weight:800"><i class="fas fa-check-circle"></i> Class Created</span>';
      setTimeout(() => {
        row.style.transition = "opacity 0.4s";
        row.style.opacity = "0";
        setTimeout(() => row.remove(), 400);
      }, 1200);
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-plus"></i> Create Class';
      alert("Error: " + (data.message || "Unknown error"));
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-plus"></i> Create Class';
    alert(err.message || "Request failed. Please try again.");
  });
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>