<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

$totalUsers = 0;
$totalStudents = 0;
$totalTeachers = 0;
$totalClasses = 0;
$totalEarnings = 0;
$totalAvailableSlots = 0;

$recentUsers = [];
$recentClasses = [];
$recentEarnings = [];

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($result && $row = $result->fetch_assoc()) {
    $totalUsers = (int)$row["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='student'");
if ($result && $row = $result->fetch_assoc()) {
    $totalStudents = (int)$row["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='teacher'");
if ($result && $row = $result->fetch_assoc()) {
    $totalTeachers = (int)$row["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM classes");
if ($result && $row = $result->fetch_assoc()) {
    $totalClasses = (int)$row["total"];
}

$result = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM teacher_earnings");
if ($result && $row = $result->fetch_assoc()) {
    $totalEarnings = (float)$row["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM teacher_availability WHERE status='available'");
if ($result && $row = $result->fetch_assoc()) {
    $totalAvailableSlots = (int)$row["total"];
}

$result = $conn->query("SELECT id, username, role FROM users ORDER BY id DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentUsers[] = $row;
    }
}

$result = $conn->query("SELECT id, student_name, class_time, type FROM classes ORDER BY id DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentClasses[] = $row;
    }
}

$result = $conn->query("SELECT id, lesson_title, amount FROM teacher_earnings ORDER BY id DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentEarnings[] = $row;
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
  <title>Reports | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --secondary: #38bdf8;
      --dark: #0f172a;
      --muted: #64748b;
      --soft: #eff6ff;
      --soft-2: #f8fbff;
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
      background: white;
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

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px;
      padding: 18px 20px;
      background: rgba(255,255,255,0.85);
      border: 1px solid #edf4ff;
      border-radius: 22px;
      box-shadow: var(--shadow);
    }

    .topbar h1 {
      font-size: 1.8rem;
      font-weight: 900;
      margin: 0;
    }

    .topbar p {
      margin: 4px 0 0;
      color: var(--muted);
    }

    .admin-badge {
      background: var(--soft);
      color: var(--primary-dark);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 10px 16px;
      font-weight: 800;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 18px;
      margin-bottom: 24px;
    }

    .stat-card, .panel-card {
      background: white;
      border-radius: 22px;
      padding: 22px;
      box-shadow: var(--shadow);
      border: 1px solid #edf4ff;
    }

    .stat-label {
      color: var(--muted);
      font-weight: 700;
      margin-bottom: 8px;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 900;
    }

    .content-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .panel-title {
      font-size: 1.15rem;
      font-weight: 900;
      margin-bottom: 16px;
    }

    .table {
      margin-bottom: 0;
    }

    .table thead th {
      background: #f8fbff;
      font-weight: 800;
    }

    @media (max-width: 1100px) {
      .stats-grid, .content-grid {
        grid-template-columns: 1fr;
      }
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
    }
  </style>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand-box">
        <img src="images/logo.png" class="logo-img" alt="Logo">
        <div>
          <div class="brand-title">JuniorCode</div>
          <div class="brand-sub">Admin Panel</div>
        </div>
      </div>

      <div class="nav-title">Main</div>
      <div class="nav-custom">
        <a href="admin_dashboard.php" class="nav-link-custom <?php echo isActive('admin_dashboard.php', $currentPage); ?>">
          <span class="nav-icon">🏠</span>
          <span>Dashboard</span>
        </a>

        <a href="manage_users.php" class="nav-link-custom">
          <span class="nav-icon">👥</span>
          <span>Manage Users</span>
        </a>

        <a href="manage_classes.php" class="nav-link-custom">
          <span class="nav-icon">📚</span>
          <span>Manage Classes</span>
        </a>

        <a href="teacher_earnings.php" class="nav-link-custom">
          <span class="nav-icon">💰</span>
          <span>Teacher Earnings</span>
        </a>

        <a href="available_slots.php" class="nav-link-custom">
          <span class="nav-icon">📅</span>
          <span>Available Slots</span>
        </a>

        <a href="courses.php" class="nav-link-custom">
          <span class="nav-icon">🎓</span>
          <span>Courses</span>
        </a>

        <a href="reports.php" class="nav-link-custom active">
          <span class="nav-icon">📊</span>
          <span>Reports</span>
        </a>

        <a href="settings.php" class="nav-link-custom">
          <span class="nav-icon">⚙️</span>
          <span>Settings</span>
        </a>

        <a href="logout.php" class="nav-link-custom">
          <span class="nav-icon">🚪</span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <main class="main-content">
      <div class="topbar">
        <div>
          <h1>Reports</h1>
          <p>System reports and recent activity overview.</p>
        </div>
        <div class="admin-badge">
          Hello, <?php echo htmlspecialchars($adminName); ?>
        </div>
      </div>

      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total Users</div>
          <div class="stat-value"><?php echo $totalUsers; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Total Classes</div>
          <div class="stat-value"><?php echo $totalClasses; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Total Earnings</div>
          <div class="stat-value">$<?php echo number_format($totalEarnings, 2); ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Students</div>
          <div class="stat-value"><?php echo $totalStudents; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Teachers</div>
          <div class="stat-value"><?php echo $totalTeachers; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-label">Available Slots</div>
          <div class="stat-value"><?php echo $totalAvailableSlots; ?></div>
        </div>
      </section>

      <section class="content-grid">
        <div class="panel-card">
          <div class="panel-title">Recent Users</div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Username</th>
                  <th>Role</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentUsers)): ?>
                  <?php foreach ($recentUsers as $user): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($user["id"]); ?></td>
                      <td><?php echo htmlspecialchars($user["username"]); ?></td>
                      <td><?php echo htmlspecialchars($user["role"]); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="3">No users found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel-card">
          <div class="panel-title">Recent Classes</div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Student</th>
                  <th>Time</th>
                  <th>Type</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentClasses)): ?>
                  <?php foreach ($recentClasses as $class): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($class["id"]); ?></td>
                      <td><?php echo htmlspecialchars($class["student_name"]); ?></td>
                      <td><?php echo htmlspecialchars($class["class_time"]); ?></td>
                      <td><?php echo htmlspecialchars($class["type"]); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4">No classes found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel-card">
          <div class="panel-title">Recent Teacher Earnings</div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Lesson</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentEarnings)): ?>
                  <?php foreach ($recentEarnings as $earning): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($earning["id"]); ?></td>
                      <td><?php echo htmlspecialchars($earning["lesson_title"]); ?></td>
                      <td>$<?php echo number_format((float)$earning["amount"], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="3">No earnings found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel-card">
          <div class="panel-title">Recent Available Slots</div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Teacher</th>
                  <th>Date</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentAvailability)): ?>
                  <?php foreach ($recentAvailability as $slot): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($slot["id"]); ?></td>
                      <td><?php echo htmlspecialchars($slot["teacher_name"]); ?></td>
                      <td><?php echo htmlspecialchars($slot["available_date"]); ?></td>
                      <td><?php echo htmlspecialchars($slot["available_time"]); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4">No available slots found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </main>
  </div>
</body>
</html>