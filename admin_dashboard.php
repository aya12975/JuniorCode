<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

/* -----------------------------
   Statistics
----------------------------- */
$totalUsers = 0;
$totalStudents = 0;
$totalTeachers = 0;
$totalClasses = 0;
$totalEarnings = 0;
$totalAvailableSlots = 0;
$todayAvailableSlots = 0;

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

$today = date("Y-m-d");
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM teacher_availability WHERE status='available' AND available_date=?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $todayAvailableSlots = (int)$row["total"];
}

/* -----------------------------
   Recent users
----------------------------- */
$recentUsers = [];
$result = $conn->query("
    SELECT id, username, role
    FROM users
    ORDER BY id DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentUsers[] = $row;
    }
}

/* -----------------------------
   Recent earnings
----------------------------- */
$recentEarnings = [];
$result = $conn->query("
    SELECT id, lesson_title, amount
    FROM teacher_earnings
    ORDER BY id DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentEarnings[] = $row;
    }
}

/* -----------------------------
   Recent available slots
----------------------------- */
$recentAvailability = [];
$result = $conn->query("
    SELECT id, teacher_name, available_date, available_time
    FROM teacher_availability
    WHERE status='available'
    ORDER BY available_date ASC, available_time ASC
    LIMIT 8
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentAvailability[] = $row;
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
  <title>Admin Dashboard | JuniorCode Academy</title>
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
      --white: #ffffff;
      --shadow: 0 18px 45px rgba(37, 99, 235, 0.08);
    }

    * {
      box-sizing: border-box;
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

    .brand-icon {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      background: rgba(255,255,255,0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
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

    .nav-link-custom.active .nav-icon {
      background: rgba(255,255,255,0.18);
    }

    .sidebar-note {
      margin-top: 28px;
      padding: 14px;
      border-radius: 16px;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.08);
      color: rgba(255,255,255,0.82);
      font-size: 0.92rem;
      line-height: 1.7;
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
      backdrop-filter: blur(10px);
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
      white-space: nowrap;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 18px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: white;
      border-radius: 22px;
      padding: 22px;
      box-shadow: var(--shadow);
      border: 1px solid #edf4ff;
    }

    .stat-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 14px;
    }

    .stat-label {
      font-weight: 700;
      color: var(--muted);
      font-size: 0.95rem;
    }

    .stat-icon {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: var(--soft);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 900;
      line-height: 1;
      margin-bottom: 8px;
    }

    .stat-note {
      color: var(--muted);
      font-size: 0.92rem;
    }

    .content-grid {
      display: grid;
      grid-template-columns: 1.15fr 1fr;
      gap: 20px;
    }

    .panel-card {
      background: white;
      border-radius: 24px;
      padding: 22px;
      box-shadow: var(--shadow);
      border: 1px solid #edf4ff;
      height: 100%;
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
      gap: 12px;
    }

    .panel-title {
      font-size: 1.15rem;
      font-weight: 900;
      margin: 0;
    }
    .logo-img {
  width: 30spx;
  height: 55px;
  object-fit: contain;
  border-radius: 5px;
  background: none;
  padding: 6px;
  weight:70px;
}

    .panel-link {
      text-decoration: none;
      color: var(--primary);
      font-weight: 800;
      font-size: 0.92rem;
    }

    .table-responsive {
      border-radius: 18px;
      overflow: hidden;
    }

    .table {
      margin-bottom: 0;
    }

    .table thead th {
      background: #f8fbff;
      color: var(--dark);
      font-size: 0.9rem;
      font-weight: 800;
      border-bottom: 1px solid #e6eefb;
    }

    .table td {
      vertical-align: middle;
      color: #334155;
    }

    .small-box-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 18px;
      margin-bottom: 20px;
    }

    .mini-stat-card {
      background: white;
      border-radius: 22px;
      padding: 20px;
      box-shadow: var(--shadow);
      border: 1px solid #edf4ff;
    }

    .mini-stat-title {
      color: var(--muted);
      font-weight: 700;
      margin-bottom: 8px;
    }

    .mini-stat-value {
      font-size: 2rem;
      font-weight: 900;
      margin: 0;
    }

    .empty-box {
      text-align: center;
      padding: 24px 18px;
      border-radius: 18px;
      background: var(--soft-2);
      color: var(--muted);
      border: 1px dashed #d9e9ff;
      font-weight: 700;
    }

    @media (max-width: 1200px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .content-grid {
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

      .main-content {
        padding: 18px;
      }

      .topbar {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (max-width: 767px) {
      .stats-grid,
      .small-box-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand-box">
        <img src="images/robot2.png.png" class="logo-img" alt="logo">
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

        <a href="reports.php" class="nav-link-custom">
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

      <div class="sidebar-note">
        Welcome back, <strong><?php echo htmlspecialchars($adminName); ?></strong>.<br>
        Monitor users, classes, earnings, and teacher availability from one dashboard.
      </div>
    </aside>

    <main class="main-content">
      <div class="topbar">
        <div>
          <h1>Admin Dashboard</h1>
          <p>Welcome back, Admin. Here is your full system overview.</p>
        </div>
        <div class="admin-badge">
          Hello, <?php echo htmlspecialchars($adminName); ?>
        </div>
      </div>

      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-head">
            <div class="stat-label">Total Users</div>
            <div class="stat-icon">👥</div>
          </div>
          <div class="stat-value"><?php echo $totalUsers; ?></div>
          <div class="stat-note">All registered accounts</div>
        </div>

        <div class="stat-card">
          <div class="stat-head">
            <div class="stat-label">Students</div>
            <div class="stat-icon">👨‍🎓</div>
          </div>
          <div class="stat-value"><?php echo $totalStudents; ?></div>
          <div class="stat-note">Student accounts</div>
        </div>

        <div class="stat-card">
          <div class="stat-head">
            <div class="stat-label">Teachers</div>
            <div class="stat-icon">👩‍🏫</div>
          </div>
          <div class="stat-value"><?php echo $totalTeachers; ?></div>
          <div class="stat-note">Teacher accounts</div>
        </div>

        <div class="stat-card">
          <div class="stat-head">
            <div class="stat-label">Classes</div>
            <div class="stat-icon">📘</div>
          </div>
          <div class="stat-value"><?php echo $totalClasses; ?></div>
          <div class="stat-note">Total classes in system</div>
        </div>
      </section>

      <section class="small-box-grid">
        <div class="mini-stat-card">
          <div class="mini-stat-title">Teacher Earnings</div>
          <p class="mini-stat-value">$<?php echo number_format($totalEarnings, 2); ?></p>
        </div>

        <div class="mini-stat-card">
          <div class="mini-stat-title">Available Slots</div>
          <p class="mini-stat-value"><?php echo $totalAvailableSlots; ?></p>
        </div>

        <div class="mini-stat-card">
          <div class="mini-stat-title">Today's Available Slots</div>
          <p class="mini-stat-value"><?php echo $todayAvailableSlots; ?></p>
        </div>

        <div class="mini-stat-card">
          <div class="mini-stat-title">System Status</div>
          <p class="mini-stat-value" style="font-size:1.3rem;">Running</p>
        </div>
      </section>

      <section class="content-grid">
        <div class="panel-card">
          <div class="panel-header">
            <h2 class="panel-title">Recent Users</h2>
            <a href="manage_users.php" class="panel-link">View all users</a>
          </div>

          <?php if (!empty($recentUsers)): ?>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentUsers as $user): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($user["id"]); ?></td>
                      <td><?php echo htmlspecialchars($user["username"]); ?></td>
                      <td><?php echo htmlspecialchars($user["role"]); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-box">No recent users found.</div>
          <?php endif; ?>
        </div>

        <div class="panel-card">
          <div class="panel-header">
            <h2 class="panel-title">Recent Teacher Earnings</h2>
            <a href="teacher_earnings.php" class="panel-link">View earnings</a>
          </div>

          <?php if (!empty($recentEarnings)): ?>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Lesson</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentEarnings as $earning): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($earning["id"]); ?></td>
                      <td><?php echo htmlspecialchars($earning["lesson_title"]); ?></td>
                      <td>$<?php echo number_format((float)$earning["amount"], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-box">No earnings records found.</div>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel-card" style="margin-top:20px;">
        <div class="panel-header">
          <h2 class="panel-title">Recent Available Slots</h2>
          <a href="available_slots.php" class="panel-link">View all slots</a>
        </div>

        <?php if (!empty($recentAvailability)): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Teacher</th>
                  <th>Date</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentAvailability as $slot): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($slot["id"]); ?></td>
                    <td><?php echo htmlspecialchars($slot["teacher_name"]); ?></td>
                    <td><?php echo htmlspecialchars($slot["available_date"]); ?></td>
                    <td><?php echo htmlspecialchars($slot["available_time"]); ?></td>
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
</body>
</html>