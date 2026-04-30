<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $role = trim($_POST["role"] ?? "");

    if ($username === "" || $password === "" || $role === "") {
        header("Location: add_user.php?error=1");
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hashedPassword, $role);

    if ($stmt->execute()) {
        header("Location: manage_users.php?success=1");
        exit();
    } else {
        header("Location: add_user.php?error=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add User | Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root {
  --primary: #3e5077;
  --primary-dark: #152c6b;
  --secondary: #143674;
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

.logo-img {
  width: 55px;
  height: 55px;
  object-fit: contain;
  border-radius: 8px;
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
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  border-radius: 22px;
  box-shadow: 0 12px 28px rgba(37, 99, 235, 0.3);
}

.topbar h1 {
  color: white;
  margin: 0;
  font-weight: 900;
}

.topbar p {
  margin: 4px 0 0;
  color: rgba(255,255,255,0.8);
}

.admin-badge {
  background: rgba(255,255,255,0.15);
  color: white;
  border-radius: 999px;
  padding: 10px 16px;
  font-weight: 800;
  white-space: nowrap;
}

.form-wrapper {
  display: flex;
  justify-content: flex-start;
}

.card-box {
  background: white;
  padding: 40px;
  border-radius: 24px;
  box-shadow: var(--shadow);
  border: 1px solid #edf4ff;
  max-width: 850px;
  width: 100%;
}

.card-box h2 {
  font-weight: 900;
  margin-bottom: 24px;
}

.form-label {
  font-weight: 800;
  color: #334155;
  margin-bottom: 8px;
}

.form-control,
.form-select {
  height: 58px;
  border-radius: 14px;
  font-size: 1rem;
  padding: 14px 16px;
  border: 1px solid #dbeafe;
}

.form-control:focus,
.form-select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 0.2rem rgba(62, 80, 119, 0.15);
}

.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
  flex-wrap: wrap;
}

.btn-main {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: white;
  border: none;
  border-radius: 14px;
  padding: 13px 24px;
  font-weight: 900;
  text-decoration: none;
}

.btn-main:hover {
  color: white;
  transform: translateY(-1px);
}

.btn-back {
  background: #64748b;
  color: white;
  border: none;
  border-radius: 14px;
  padding: 13px 24px;
  font-weight: 900;
  text-decoration: none;
}

.btn-back:hover {
  color: white;
  background: #475569;
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

      <a href="manage_users.php" class="nav-link-custom active">
        <span class="nav-icon">👥</span>
        <span>Manage Users</span>
      </a>

      <a href="manage_classes.php" class="nav-link-custom <?php echo isActive('manage_classes.php', $currentPage); ?>">
        <span class="nav-icon">📚</span>
        <span>Manage Classes</span>
      </a>

      <a href="teacher_earnings.php" class="nav-link-custom <?php echo isActive('teacher_earnings.php', $currentPage); ?>">
        <span class="nav-icon">💰</span>
        <span>Teacher Earnings</span>
      </a>

      <a href="available_slots.php" class="nav-link-custom <?php echo isActive('available_slots.php', $currentPage); ?>">
        <span class="nav-icon">📅</span>
        <span>Available Slots</span>
      </a>

      <a href="courses.php" class="nav-link-custom <?php echo isActive('courses.php', $currentPage); ?>">
        <span class="nav-icon">🎓</span>
        <span>Courses</span>
      </a>

      <a href="reports.php" class="nav-link-custom <?php echo isActive('reports.php', $currentPage); ?>">
        <span class="nav-icon">📊</span>
        <span>Reports</span>
      </a>

      <a href="settings.php" class="nav-link-custom <?php echo isActive('settings.php', $currentPage); ?>">
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
      Add new admins, teachers, or students from this page.
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div>
        <h1>Add User</h1>
        <p>Create a new admin, teacher, or student account.</p>
      </div>

      <div class="admin-badge">
        Hello, <?php echo htmlspecialchars($adminName); ?>
      </div>
    </div>

    <div class="form-wrapper">
      <div class="card-box">
        <h2>Add User</h2>

        <?php if (isset($_GET["error"])): ?>
          <div class="alert alert-danger">Please fill all fields correctly.</div>
        <?php endif; ?>

        <form method="POST">
          <div class="mb-4">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
          </div>

          <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>

          <div class="mb-4">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" required>
              <option value="">Select role</option>
              <option value="admin">Admin</option>
              <option value="teacher">Teacher</option>
              <option value="student">Student</option>
            </select>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-main">Add User</button>
            <a href="manage_users.php" class="btn-back">Back</a>
          </div>
        </form>
      </div>
    </div>
  </main>

</div>

</body>
</html>