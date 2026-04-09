<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName = $_SESSION["username"] ?? "Admin";

$filterRole = $_GET["role"] ?? "";
$search = trim($_GET["search"] ?? "");

$sql = "SELECT id, username, role FROM users WHERE 1=1";
$params = [];
$types = "";

if ($filterRole !== "") {
    $sql .= " AND role = ?";
    $params[] = $filterRole;
    $types .= "s";
}

if ($search !== "") {
    $sql .= " AND username LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users | JuniorCode Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #2563eb;
      --secondary: #38bdf8;
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
      color: #1d4ed8;
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 10px 16px;
      font-weight: 800;
    }

    .panel-card {
      padding: 22px;
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

    .filters {
      display: grid;
      grid-template-columns: 1fr 180px 160px;
      gap: 12px;
      margin-bottom: 18px;
    }

    .form-control, .form-select {
      border-radius: 14px;
      padding: 12px 14px;
      border: 1px solid #dbe4f0;
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

    .role-badge {
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 800;
      display: inline-block;
    }

    .role-admin {
      background: #fee2e2;
      color: #991b1b;
    }

    .role-teacher {
      background: #dbeafe;
      color: #1d4ed8;
    }

    .role-student {
      background: #dcfce7;
      color: #166534;
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

      .filters {
        grid-template-columns: 1fr;
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
        <a href="admin_dashboard.php" class="nav-link-custom">
          <span class="nav-icon">🏠</span>
          <span>Dashboard</span>
        </a>

        <a href="manage_users.php" class="nav-link-custom active">
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
    </aside>

    <main class="main-content">
      <div class="topbar">
        <div>
          <h1>Manage Users</h1>
          <p>Add, edit, search, and remove users from the system.</p>
        </div>
        <div class="admin-badge">
          Hello, <?php echo htmlspecialchars($adminName); ?>
        </div>
      </div>

      <?php if (isset($_GET["success"])): ?>
        <div class="alert alert-success">Action completed successfully.</div>
      <?php endif; ?>

      <?php if (isset($_GET["error"])): ?>
        <div class="alert alert-danger">Something went wrong. Please try again.</div>
      <?php endif; ?>

      <section class="panel-card">
        <div class="panel-header">
          <h2 class="panel-title">Users List</h2>
          <a href="add_user.php" class="btn-main">+ Add User</a>
        </div>

        <form method="GET" class="filters">
          <input
            type="text"
            name="search"
            class="form-control"
            placeholder="Search by username"
            value="<?php echo htmlspecialchars($search); ?>"
          >

          <select name="role" class="form-select">
            <option value="">All Roles</option>
            <option value="admin" <?php echo $filterRole === "admin" ? "selected" : ""; ?>>Admin</option>
            <option value="teacher" <?php echo $filterRole === "teacher" ? "selected" : ""; ?>>Teacher</option>
            <option value="student" <?php echo $filterRole === "student" ? "selected" : ""; ?>>Student</option>
          </select>

          <button type="submit" class="btn-main">Filter</button>
        </form>

        <?php if ($result && $result->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Username</th>
                  <th>Role</th>
                  <th style="width: 180px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($user = $result->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($user["id"]); ?></td>
                    <td><?php echo htmlspecialchars($user["username"]); ?></td>
                    <td>
                      <span class="role-badge role-<?php echo htmlspecialchars($user["role"]); ?>">
                        <?php echo htmlspecialchars(ucfirst($user["role"])); ?>
                      </span>
                    </td>
                    <td>
                      <a href="edit_user.php?id=<?php echo $user["id"]; ?>" class="action-btn edit-btn">Edit</a>
                      <a
                        href="delete_user.php?id=<?php echo $user["id"]; ?>"
                        class="action-btn delete-btn"
                        onclick="return confirm('Are you sure you want to delete this user?');"
                      >
                        Delete
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-box">No users found.</div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>