<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

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

function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
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
  --secondary: #143674;
  --soft: #eff6ff;
  --dark: #0f172a;
  --muted: #64748b;
}

/* Layout */
body {
  margin: 0;
  font-family: Arial;
  background: #f4f8ff;
}

.app-shell {
  display: flex;
  min-height: 100vh;
}

/* Sidebar */
.sidebar {
  width: 260px;
  background: linear-gradient(180deg, #0f172a, #172554);
  color: white;
  padding: 20px;
}

.nav-link-custom {
  display: flex;
  gap: 10px;
  padding: 12px;
  border-radius: 10px;
  text-decoration: none;
  color: white;
  margin-bottom: 6px;
}

.nav-link-custom.active,
.nav-link-custom:hover {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
}

/* Main */
.main-content {
  flex: 1;
  padding: 25px;
}

/* Topbar */
.topbar {
  display: flex;
  justify-content: space-between;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  padding: 18px;
  border-radius: 18px;
  color: white;
  margin-bottom: 20px;
}

/* Card */
.card-box {
  background: white;
  padding: 30px;
  border-radius: 20px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.08);
  max-width: 600px;
}

/* Button */
.btn-main {
  background: linear-gradient(135deg, #2563eb, #38bdf8);
  color: white;
  border: none;
  border-radius: 12px;
  padding: 10px 18px;
  font-weight: bold;
}
</style>
</head>

<body>

<div class="app-shell">

<!-- SIDEBAR -->
<aside class="sidebar">
  <h4>JuniorCode</h4>
  <p style="color:#ccc;">Admin Panel</p>

  <a href="admin_dashboard.php" class="nav-link-custom">🏠 Dashboard</a>
  <a href="manage_users.php" class="nav-link-custom active">👥 Manage Users</a>
  <a href="manage_classes.php" class="nav-link-custom">📚 Classes</a>
  <a href="logout.php" class="nav-link-custom">🚪 Logout</a>
</aside>

<!-- MAIN -->
<main class="main-content">

  <!-- TOPBAR -->
  <div class="topbar">
    <div>
      <h3>Add User</h3>
      <small>Create new account</small>
    </div>
    <div>
      Hello, <?php echo htmlspecialchars($adminName); ?>
    </div>
  </div>

  <!-- FORM -->
  <div class="card-box">

    <?php if (isset($_GET["error"])): ?>
      <div class="alert alert-danger">Please fill all fields correctly.</div>
    <?php endif; ?>

    <form method="POST">

      <div class="mb-3">
        <label>Username</label>
        <input type="text" name="username" class="form-control" required>
      </div>

      <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <div class="mb-3">
        <label>Role</label>
        <select name="role" class="form-select" required>
          <option value="">Select role</option>
          <option value="admin">Admin</option>
          <option value="teacher">Teacher</option>
          <option value="student">Student</option>
        </select>
      </div>

      <button type="submit" class="btn-main">Add User</button>
      <a href="manage_users.php" class="btn btn-secondary ms-2">Back</a>

    </form>

  </div>

</main>
</div>

</body>
</html>