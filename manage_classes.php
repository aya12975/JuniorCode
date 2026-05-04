<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName = $_SESSION["username"] ?? "Admin";
$message = "";

/* Auto-add missing columns */
$colCheck = $conn->query("SHOW COLUMNS FROM classes LIKE 'zoom_link'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN zoom_link VARCHAR(500) DEFAULT NULL");
}
$colCheck2 = $conn->query("SHOW COLUMNS FROM classes LIKE 'teacher_id'");
if ($colCheck2 && $colCheck2->num_rows === 0) {
    $conn->query("ALTER TABLE classes ADD COLUMN teacher_id INT DEFAULT NULL");
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
      padding: 24px 18px;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      flex-shrink: 0;
    }

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
      border-radius: 999px;
      padding: 10px 16px;
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
      <div class="topbar">
        <div>
          <h1>Manage Classes</h1>
          <p>Admin can add, edit, and delete teacher classes from here.</p>
        </div>
        <div class="admin-badge">
          Hello, <?php echo htmlspecialchars($adminName); ?>
        </div>
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

            <div class="col-md-8">
              <label class="form-label">Zoom Link <span class="text-muted fw-normal">(optional)</span></label>
              <input type="url" name="zoom_link" class="form-control" placeholder="https://zoom.us/j/...">
            </div>

            <div class="col-12">
              <button type="submit" class="btn-main">Add Class</button>
            </div>
          </div>
        </form>
      </section>

      <section class="panel-card">
        <h2 class="panel-title">All Classes</h2>

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
                <?php foreach ($classes as $class): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($class["id"]); ?></td>
                    <td><?php echo htmlspecialchars($class["teacher_name"]); ?></td>
                    <td><?php echo htmlspecialchars($class["student_name"]); ?></td>
                    <td><?php echo htmlspecialchars($class["class_date"]); ?></td>
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
                    <td>
                      <?php if (!empty($class["zoom_link"])): ?>
                        <a href="<?php echo htmlspecialchars($class["zoom_link"]); ?>" target="_blank" rel="noopener" class="btn-zoom">
                          <i class="fas fa-video"></i> Open Zoom
                        </a>
                      <?php else: ?>
                        <span class="zoom-empty">— No link</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <a href="edit_class.php?id=<?php echo $class["id"]; ?>" class="btn btn-sm btn-warning">Edit</a>
                      <a href="delete_class.php?id=<?php echo $class["id"]; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this class?');">Delete</a>
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
</script>

</body>
</html>