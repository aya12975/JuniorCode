<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

/* ── Ensure required columns exist ── */
foreach ([
    "teacher_id   INT DEFAULT NULL",
    "class_id     INT DEFAULT NULL",
    "teacher_name VARCHAR(255) DEFAULT NULL",
    "lesson_title VARCHAR(500) DEFAULT NULL",
    "lesson_date  DATE DEFAULT NULL",
    "notes        TEXT DEFAULT NULL",
    "amount       DECIMAL(10,2) DEFAULT 0"
] as $col) {
    $colName = explode(" ", $col)[0];
    $chk = $conn->query("SHOW COLUMNS FROM teacher_earnings LIKE '$colName'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE teacher_earnings ADD COLUMN $col");
    }
}

/* ── AJAX: add earning from a class ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_earning") {
    header("Content-Type: application/json");
    $classId    = (int)($_POST["class_id"]    ?? 0);
    $teacherId  = (int)($_POST["teacher_id"]  ?? 0);
    $teacherName = trim($_POST["teacher_name"] ?? "");
    $lessonTitle = trim($_POST["lesson_title"] ?? "");
    $lessonDate  = trim($_POST["lesson_date"]  ?? "");
    $amount      = (float)($_POST["amount"]    ?? 0);
    $notes       = trim($_POST["notes"]        ?? "");

    if ($amount <= 0) {
        echo json_encode(["success" => false, "message" => "Amount must be greater than 0"]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO teacher_earnings (teacher_id, teacher_name, lesson_title, amount, lesson_date, notes, class_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) { echo json_encode(["success" => false, "message" => $conn->error]); exit(); }
    $stmt->bind_param("issdssi", $teacherId, $teacherName, $lessonTitle, $amount, $lessonDate, $notes, $classId);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();

    echo json_encode(["success" => true, "id" => $newId, "amount" => number_format($amount, 2)]);
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName = $_SESSION["username"] ?? "Admin";

$earnings = [];
$result = $conn->query("SELECT * FROM teacher_earnings ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) $earnings[] = $row;
}

/* ── Load all classes for the "Add Earning" panel ── */
$classes = [];
$result2 = $conn->query("
    SELECT c.id, c.teacher_id, c.teacher_name, c.student_name, c.class_date, c.class_time, c.type,
           (SELECT COUNT(*) FROM teacher_earnings te WHERE te.class_id = c.id) AS has_earning
    FROM classes c
    ORDER BY c.class_date DESC, c.class_time ASC
");
if ($result2) {
    while ($row = $result2->fetch_assoc()) $classes[] = $row;
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
  <title>Teacher Earnings | JuniorCode Admin</title>
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
      padding: 24px 18px;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      flex-shrink: 0;
      transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow: hidden;
    }

    body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow: hidden; }

    .brand-box {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 28px;
      padding: 10px 12px;
      border-radius: 18px;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.08);
    }

    .logo-img {
      width: 55px;
      height: 55px;
      object-fit: contain;
      border-radius: 12px;
      background: none;
      padding: 6px;
      flex-shrink: 0;
    }

    .brand-title {
      font-weight: 900;
      font-size: 1.1rem;
      line-height: 1.15;
    }

    .brand-sub {
      font-size: 0.78rem;
      color: rgba(255,255,255,0.75);
      letter-spacing: 1px;
      margin-top: 3px;
    }

    .nav-title {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 1.3px;
      color: rgba(255,255,255,0.55);
      margin: 18px 10px 10px;
      font-weight: 700;
    }

    .nav-custom {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .nav-link-custom {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(255,255,255,0.88);
      text-decoration: none;
      padding: 13px 14px;
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
      width: 34px;
      height: 34px;
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
      background: white;
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
      border-radius: 999px;
      padding: 10px 16px;
      font-weight: 800;
    }

    .panel-card {
      padding: 22px;
      border-top: 3px solid var(--primary);
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 18px;
      flex-wrap: wrap;
    }

    .panel-title {
      font-size: 1.2rem;
      font-weight: 900;
      color: var(--primary);
      margin: 0;
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

    .money-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 999px;
      background: #ecfdf5;
      color: #065f46;
      font-weight: bold;
      font-size: 0.85rem;
    }

    .action-btn {
      text-decoration: none;
      font-weight: 700;
      margin-right: 10px;
    }

    .edit-btn {
      color: #2563eb;
    }

    .delete-btn {
      color: #dc2626;
    }

    .badge-paid  { background:#dcfce7;color:#166534;padding:5px 11px;border-radius:999px;font-size:0.8rem;font-weight:800; }
    .badge-demo  { background:#fef3c7;color:#92400e;padding:5px 11px;border-radius:999px;font-size:0.8rem;font-weight:800; }
    .badge-other { background:#e0e7ff;color:#3730a3;padding:5px 11px;border-radius:999px;font-size:0.8rem;font-weight:800; }

    .modal-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(15,23,42,0.55);
      backdrop-filter: blur(4px);
      z-index: 9000;
      align-items: center;
      justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: white;
      border-radius: 24px;
      padding: 32px;
      width: 100%;
      max-width: 460px;
      box-shadow: 0 32px 80px rgba(15,23,42,0.22);
    }
    .modal-title { font-size: 1.2rem; font-weight: 900; color: var(--primary); margin: 0 0 20px; }
    .info-row { background: #f8fbff; border-radius: 12px; padding: 10px 14px; margin-bottom: 10px; font-size: 0.9rem; }
    .info-row strong { color: var(--primary); }
    .modal-input { border-radius: 14px; padding: 12px 14px; border: 1px solid #dbe4f0; width: 100%; font-size: 1rem; margin-bottom: 12px; box-sizing: border-box; }
    .modal-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(62,80,119,0.12); }
    .modal-btns { display: flex; gap: 10px; margin-top: 4px; }
    .btn-cancel { background: #f1f5f9; color: #334155; border: none; border-radius: 14px; padding: 11px 20px; font-weight: 800; cursor: pointer; }
    .btn-cancel:hover { background: #e2e8f0; }

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

        <a href="settings.php" class="nav-link-custom <?php echo isActive('settings.php', $currentPage); ?>">
          <span class="nav-icon"><i class="fas fa-gear"></i></span>
          <span>Settings</span>
        </a>


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
          <h1>Teacher Earnings</h1>
          <p>Admin can view and manage all teacher earning records here.</p>
        </div>
        <div class="admin-badge">
          Hello, <?php echo htmlspecialchars($adminName); ?>
        </div>
      </div>

      <?php if (isset($_GET["updated"])): ?>
        <div class="alert alert-success">Earning updated successfully.</div>
      <?php endif; ?>

      <?php if (isset($_GET["deleted"])): ?>
        <div class="alert alert-success">Earning deleted successfully.</div>
      <?php endif; ?>

      <?php if (isset($_GET["added"])): ?>
        <div class="alert alert-success">Earning added successfully.</div>
      <?php endif; ?>

      <!-- Classes panel: add earning from a class -->
      <section class="panel-card" style="margin-bottom:24px;">
        <div class="panel-header">
          <h2 class="panel-title">Add Earning from Class</h2>
          <span class="text-muted" style="font-size:0.9rem"><?php echo count($classes); ?> classes</span>
        </div>
        <?php if (!empty($classes)): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Teacher</th>
                  <th>Student</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Type</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($classes as $cls): ?>
                  <tr id="class-row-<?php echo $cls['id']; ?>">
                    <td><strong><?php echo htmlspecialchars($cls['teacher_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($cls['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($cls['class_date']); ?></td>
                    <td><?php echo htmlspecialchars($cls['class_time']); ?></td>
                    <td>
                      <?php
                        $t = strtolower($cls['type']);
                        $bc = $t === 'paid' ? 'badge-paid' : ($t === 'demo' ? 'badge-demo' : 'badge-other');
                        echo '<span class="' . $bc . '">' . htmlspecialchars($cls['type']) . '</span>';
                      ?>
                    </td>
                    <td>
                      <?php if ($cls['has_earning'] > 0): ?>
                        <span style="color:#166534;font-weight:800;font-size:0.85rem"><i class="fas fa-check-circle"></i> Earning Added</span>
                      <?php else: ?>
                        <button class="btn-main" style="padding:7px 14px;font-size:0.85rem"
                          onclick="openEarningModal(
                            <?php echo $cls['id']; ?>,
                            <?php echo (int)$cls['teacher_id']; ?>,
                            '<?php echo addslashes($cls['teacher_name']); ?>',
                            '<?php echo addslashes($cls['student_name']); ?>',
                            '<?php echo $cls['class_date']; ?>',
                            '<?php echo addslashes($cls['type']); ?>'
                          )">
                          <i class="fas fa-plus"></i> Add Earning
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-box">No classes found. Add classes in Manage Classes first.</div>
        <?php endif; ?>
      </section>

      <section class="panel-card">
        <div class="panel-header">
          <h2 class="panel-title">All Teacher Earnings</h2>
        </div>

        <?php if (!empty($earnings)): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Teacher Name</th>
                  <th>Lesson Title</th>
                  <th>Amount</th>
                  <th>Lesson Date</th>
                  <th>Notes</th>
                  <th style="width: 180px;">Actions</th>
                </tr>
              </thead>
              <tbody id="earnings-tbody">
                <?php foreach ($earnings as $earning): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($earning["id"]); ?></td>
                    <td><?php echo htmlspecialchars($earning["teacher_name"]); ?></td>
                    <td><?php echo htmlspecialchars($earning["lesson_title"]); ?></td>
                    <td>
                      <span class="money-badge">
                        $<?php echo number_format((float)$earning["amount"], 2); ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars($earning["lesson_date"]); ?></td>
                    <td><?php echo htmlspecialchars($earning["notes"]); ?></td>
                    <td>
                      <a href="edit_earning.php?id=<?php echo $earning["id"]; ?>" class="action-btn edit-btn">Edit</a>
                      <a href="delete_earning.php?id=<?php echo $earning["id"]; ?>" class="action-btn delete-btn">Delete</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-box">No earning records found.</div>
        <?php endif; ?>
      </section>
    </main>
  </div>
<!-- Add Earning Modal -->
<div class="modal-overlay" id="earningModal" onclick="if(event.target===this)closeEarningModal()">
  <div class="modal-box">
    <div class="modal-title"><i class="fas fa-dollar-sign"></i> Add Earning</div>
    <div class="info-row"><strong>Teacher:</strong> <span id="m-teacher"></span></div>
    <div class="info-row"><strong>Student:</strong> <span id="m-student"></span></div>
    <div class="info-row"><strong>Date:</strong> <span id="m-date"></span> &nbsp;|&nbsp; <strong>Type:</strong> <span id="m-type"></span></div>
    <input type="hidden" id="m-class-id">
    <input type="hidden" id="m-teacher-id">
    <label style="font-weight:800;color:#334155;display:block;margin-bottom:6px">Amount ($) <span style="color:#ef4444">*</span></label>
    <input type="number" id="m-amount" class="modal-input" step="0.01" min="0.01" placeholder="e.g. 25.00">
    <label style="font-weight:800;color:#334155;display:block;margin-bottom:6px">Notes <span style="font-weight:400;color:#64748b">(optional)</span></label>
    <input type="text" id="m-notes" class="modal-input" placeholder="e.g. Paid class - Python basics">
    <div id="m-error" style="color:#ef4444;font-size:0.88rem;margin-bottom:8px;display:none"></div>
    <div class="modal-btns">
      <button class="btn-main" id="m-save-btn" onclick="saveEarning()"><i class="fas fa-check"></i> Save Earning</button>
      <button class="btn-cancel" onclick="closeEarningModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
function openEarningModal(classId, teacherId, teacherName, studentName, classDate, classType) {
  document.getElementById('m-class-id').value   = classId;
  document.getElementById('m-teacher-id').value = teacherId;
  document.getElementById('m-teacher').textContent = teacherName;
  document.getElementById('m-student').textContent = studentName;
  document.getElementById('m-date').textContent    = classDate;
  document.getElementById('m-type').textContent    = classType;
  document.getElementById('m-amount').value = '';
  document.getElementById('m-notes').value  = '';
  document.getElementById('m-error').style.display = 'none';
  document.getElementById('earningModal').classList.add('open');
  setTimeout(() => document.getElementById('m-amount').focus(), 100);
}

function closeEarningModal() {
  document.getElementById('earningModal').classList.remove('open');
}

function saveEarning() {
  const classId    = document.getElementById('m-class-id').value;
  const teacherId  = document.getElementById('m-teacher-id').value;
  const teacherName = document.getElementById('m-teacher').textContent;
  const amount     = parseFloat(document.getElementById('m-amount').value);
  const notes      = document.getElementById('m-notes').value;
  const date       = document.getElementById('m-date').textContent;
  const type       = document.getElementById('m-type').textContent;
  const errEl      = document.getElementById('m-error');

  if (!amount || amount <= 0) {
    errEl.textContent = 'Please enter a valid amount.';
    errEl.style.display = 'block';
    return;
  }

  const btn = document.getElementById('m-save-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  const lessonTitle = teacherName + ' — ' + type + ' (' + date + ')';

  fetch('teacher_earnings.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      action: 'add_earning',
      class_id: classId,
      teacher_id: teacherId,
      teacher_name: teacherName,
      lesson_title: lessonTitle,
      lesson_date: date,
      amount: amount,
      notes: notes
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeEarningModal();
      /* Mark the class row as earned */
      const cell = document.querySelector('#class-row-' + classId + ' td:last-child');
      if (cell) cell.innerHTML = '<span style="color:#166534;font-weight:800;font-size:0.85rem"><i class="fas fa-check-circle"></i> Earning Added</span>';
      /* Prepend to earnings table */
      const tbody = document.querySelector('#earnings-tbody');
      if (tbody) {
        const tr = document.createElement('tr');
        tr.style.background = '#f0fdf4';
        tr.innerHTML = `<td>${data.id}</td><td>${teacherName}</td><td>${lessonTitle}</td>
          <td><span class="money-badge">$${data.amount}</span></td>
          <td>${date}</td><td>${notes || '—'}</td>
          <td><a href="edit_earning.php?id=${data.id}" class="action-btn edit-btn">Edit</a>
              <a href="delete_earning.php?id=${data.id}" class="action-btn delete-btn">Delete</a></td>`;
        tbody.insertBefore(tr, tbody.firstChild);
        setTimeout(() => tr.style.background = '', 1500);
      }
    } else {
      errEl.textContent = data.message || 'Error saving earning.';
      errEl.style.display = 'block';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Save Earning';
  })
  .catch(() => {
    errEl.textContent = 'Request failed. Please try again.';
    errEl.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Save Earning';
  });
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>