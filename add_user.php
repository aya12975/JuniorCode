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

$newUser   = null;
$formError = isset($_GET['error']);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $role     = trim($_POST["role"] ?? "");

    if ($username === "" || $password === "" || $role === "") {
        $formError = true;
    } else {
        $chk = $conn->query("SHOW COLUMNS FROM users LIKE 'plain_password'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) NOT NULL DEFAULT ''");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, plain_password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $hashedPassword, $password, $role);

        if ($stmt->execute()) {
            $newUser = ['username' => $username, 'password' => $password, 'role' => $role];
        } else {
            $formError = true;
        }
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
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

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
  box-shadow: var(--shadow);
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

/* ── Credentials card ── */
.credentials-card {
  background: #f0fdf4;
  border: 2px solid #86efac;
  border-radius: 20px;
  padding: 28px 28px 22px;
  margin-bottom: 24px;
}

.cred-header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 900;
  font-size: 1.1rem;
  color: #15803d;
  margin-bottom: 10px;
}

.cred-note {
  color: #166534;
  font-size: 0.9rem;
  margin-bottom: 20px;
  font-weight: 600;
}

.cred-row {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #fff;
  border: 1px solid #bbf7d0;
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 10px;
}

.cred-label {
  font-weight: 800;
  color: #374151;
  min-width: 90px;
  font-size: 0.9rem;
}

.cred-value {
  flex: 1;
  font-family: 'Courier New', monospace;
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
  word-break: break-all;
}

.copy-btn {
  background: #dcfce7;
  border: 1px solid #86efac;
  border-radius: 8px;
  padding: 6px 10px;
  color: #15803d;
  cursor: pointer;
  transition: background 0.2s;
  font-size: 0.85rem;
}
.copy-btn:hover { background: #bbf7d0; }
.copy-btn.copied { background: #15803d; color: #fff; border-color: #15803d; }

.role-badge {
  font-family: Arial, sans-serif;
  font-size: 0.85rem;
  font-weight: 800;
  padding: 4px 14px;
  border-radius: 999px;
}
.role-admin   { background: #dbeafe; color: #1e40af; }
.role-teacher { background: #fef9c3; color: #854d0e; }
.role-student { background: #f3e8ff; color: #6b21a8; }

.cred-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  flex-wrap: wrap;
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
      <img src="images/robot2.png.png" class="logo-img" alt="logo">
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

      <a href="manage_users.php" class="nav-link-custom active">
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

        <?php if ($formError): ?>
          <div class="alert alert-danger">Please fill all fields correctly.</div>
        <?php endif; ?>

        <?php if ($newUser !== null): $nu = $newUser; ?>
        <div class="credentials-card">
          <div class="cred-header">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            User created successfully!
          </div>
          <p class="cred-note">Save these credentials — the password won't be shown again after you leave this page.</p>
          <div class="cred-row">
            <span class="cred-label">Username</span>
            <span class="cred-value" id="cred-username"><?= htmlspecialchars($nu['username']) ?></span>
            <button type="button" class="copy-btn" onclick="copyText('cred-username', this)"><i class="fas fa-copy"></i></button>
          </div>
          <div class="cred-row">
            <span class="cred-label">Password</span>
            <span class="cred-value" id="cred-password"><?= htmlspecialchars($nu['password']) ?></span>
            <button type="button" class="copy-btn" onclick="copyText('cred-password', this)"><i class="fas fa-copy"></i></button>
          </div>
          <div class="cred-row">
            <span class="cred-label">Role</span>
            <span class="cred-value role-badge role-<?= htmlspecialchars($nu['role']) ?>"><?= ucfirst(htmlspecialchars($nu['role'])) ?></span>
          </div>
          <div class="cred-actions">
            <a href="manage_users.php" class="btn-main" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
              <i class="fas fa-users"></i> Go to Manage Users
            </a>
            <a href="add_user.php" class="btn-back" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
              <i class="fas fa-plus"></i> Add Another User
            </a>
          </div>
        </div>
        <?php else: ?>

        <form method="POST">
          <div class="mb-4">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
          </div>

          <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
              <input type="password" name="password" id="passwordInput" class="form-control" required>
              <button type="button" class="btn btn-outline-secondary" id="eyeToggleBtn" onclick="toggleAddUserPassword()" tabindex="-1">
                <i class="fas fa-eye" id="eyeIcon"></i>
              </button>
            </div>
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
        <?php endif; ?>
      </div>
    </div>
  </main>

</div>

<script>
function toggleAddUserPassword() {
  const input = document.getElementById('passwordInput');
  const icon  = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}

function copyText(id, btn) {
  const text = document.getElementById(id).textContent;
  navigator.clipboard.writeText(text).then(() => {
    btn.classList.add('copied');
    btn.innerHTML = '<i class="fas fa-check"></i>';
    setTimeout(() => {
      btn.classList.remove('copied');
      btn.innerHTML = '<i class="fas fa-copy"></i>';
    }, 1800);
  });
}
</script>
</body>
</html>