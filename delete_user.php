<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$id = $_GET["id"] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: manage_users.php?error=1");
    exit();
}
$id = (int)$id;
$adminName = $_SESSION["username"] ?? "Admin";

$stmt = $conn->prepare("SELECT id, username, role, email FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: manage_users.php?error=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $del = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        header("Location: manage_users.php?success=1");
        exit();
    } else {
        header("Location: manage_users.php?error=1");
        exit();
    }
}

$roleColors = [
    "admin"   => ["bg" => "#ede9fe", "color" => "#5b21b6"],
    "teacher" => ["bg" => "#dbeafe", "color" => "#1d4ed8"],
    "student" => ["bg" => "#dcfce7", "color" => "#166534"],
];
$rc = $roleColors[$user["role"]] ?? ["bg" => "#f1f5f9", "color" => "#334155"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delete User | JuniorCode Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
  --primary: #3e5077;
  --secondary: #143674;
  --dark: #0f172a;
  --muted: #64748b;
  --shadow: 0 18px 45px rgba(37,99,235,0.08);
}
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: Arial, Helvetica, sans-serif;
  background:
    radial-gradient(circle at top left, rgba(37,99,235,0.08), transparent 22%),
    radial-gradient(circle at bottom right, rgba(56,189,248,0.08), transparent 22%),
    linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
  color: var(--dark);
}
.app-shell { min-height: 100vh; display: flex; }
.sidebar {
  width: 285px;
  background: linear-gradient(180deg, #0f172a 0%, #172554 100%);
  color: white; padding: 24px 18px;
  position: sticky; top: 0; height: 100vh; overflow-y: auto; flex-shrink: 0;
  transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow: hidden;
}
body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow: hidden; }
.brand-box {
  display: flex; align-items: center; gap: 12px; margin-bottom: 28px;
  padding: 10px 12px; border-radius: 18px;
  background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.08);
}
.logo-img { width: 55px; height: 55px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
.brand-title { font-weight: 900; font-size: 1.1rem; line-height: 1.15; }
.brand-sub { font-size: 0.78rem; color: rgba(255,255,255,0.75); letter-spacing: 1px; margin-top: 3px; }
.nav-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.3px; color: rgba(255,255,255,0.55); margin: 18px 10px 10px; font-weight: 700; }
.nav-custom { display: flex; flex-direction: column; gap: 8px; }
.nav-link-custom {
  display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.88);
  text-decoration: none; padding: 13px 14px; border-radius: 14px; transition: all 0.25s; font-weight: 700;
}
.nav-link-custom:hover { background: rgba(255,255,255,0.08); color: white; }
.nav-link-custom.active { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
.nav-icon { width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.main-content { flex: 1; padding: 26px; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }
.topbar {
  display: flex; justify-content: space-between; align-items: center; gap: 16px;
  margin-bottom: 24px; padding: 18px 20px;
  background: linear-gradient(135deg, #7f1d1d, #991b1b);
  border-radius: 22px; box-shadow: var(--shadow);
}
.topbar h1 { font-size: 1.6rem; font-weight: 900; margin: 0; color: white; }
.topbar p  { margin: 4px 0 0; color: rgba(255,255,255,0.8); }
.admin-badge { background: rgba(255,255,255,0.15); color: white; border-radius: 999px; padding: 10px 16px; font-weight: 800; white-space: nowrap; }
.panel-card { background: white; border: 1px solid #fecaca; border-radius: 22px; padding: 28px; box-shadow: var(--shadow); max-width: 520px; }
.panel-title { font-size: 1.1rem; font-weight: 900; margin-bottom: 20px; color: #991b1b; padding-bottom: 14px; border-bottom: 1px solid #fee2e2; }
.user-preview {
  display: flex; align-items: center; gap: 16px;
  background: #f8fbff; border: 1px solid #e2e8f0;
  border-radius: 16px; padding: 18px 20px; margin-bottom: 14px;
}
.user-avatar {
  width: 54px; height: 54px; border-radius: 14px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: white; display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; font-weight: 900; flex-shrink: 0;
}
.user-name { font-weight: 900; font-size: 1.05rem; color: var(--dark); }
.user-meta { font-size: 0.85rem; color: var(--muted); margin-top: 2px; }
.info-row {
  display: flex; align-items: center; gap: 10px;
  padding: 11px 16px; border-radius: 12px;
  background: #f8fbff; border: 1px solid #e2e8f0;
  margin-bottom: 10px; font-size: 0.93rem;
}
.info-label { font-weight: 800; color: var(--muted); min-width: 80px; }
.info-val { font-weight: 700; color: var(--dark); }
.warn-box {
  background: #fff7ed; border: 1px solid #fed7aa;
  border-radius: 14px; padding: 14px 18px;
  color: #9a3412; font-weight: 700; font-size: 0.9rem;
  margin: 18px 0 22px; display: flex; align-items: center; gap: 10px;
}
.btn-delete {
  background: linear-gradient(135deg, #dc2626, #991b1b);
  border: none; color: white; font-weight: 800; border-radius: 12px;
  padding: 11px 24px; cursor: pointer; font-size: 0.95rem;
  box-shadow: 0 4px 14px rgba(220,38,38,0.3);
}
.btn-delete:hover { opacity: 0.9; }
.btn-back {
  background: #f1f5f9; color: #334155; border: none; border-radius: 12px;
  padding: 11px 24px; font-weight: 800; text-decoration: none; display: inline-block; font-size: 0.95rem;
}
.btn-back:hover { background: #e2e8f0; color: #0f172a; }
@media (max-width: 991px) {
  .app-shell { flex-direction: column; }
  .sidebar { width: 100%; height: auto; position: relative; }
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
      <a href="admin_dashboard.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
      <a href="manage_users.php"     class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-users"></i></span><span>Manage Users</span></a>
      <a href="manage_classes.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
      <a href="teacher_earnings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
      <a href="available_slots.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
      <a href="courses.php"          class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
      <a href="reports.php"          class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
      <a href="settings.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
      <a href="logout.php"           class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
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
        <h1><i class="fas fa-trash me-2"></i>Delete User</h1>
        <p>This action cannot be undone.</p>
      </div>
      <div class="admin-badge">Hello, <?= htmlspecialchars($adminName) ?></div>
    </div>

    <div class="panel-card">
      <div class="panel-title"><i class="fas fa-triangle-exclamation me-2"></i>Confirm Deletion</div>

      <div class="user-preview">
        <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($user["username"]) ?></div>
          <div class="user-meta"><?= !empty($user["email"]) ? htmlspecialchars($user["email"]) : "No email on file" ?></div>
        </div>
      </div>

      <div class="info-row">
        <span class="info-label"><i class="fas fa-shield me-1"></i> Role</span>
        <span style="background:<?= $rc['bg'] ?>;color:<?= $rc['color'] ?>;padding:4px 12px;border-radius:999px;font-size:0.82rem;font-weight:800;">
          <?= ucfirst(htmlspecialchars($user["role"])) ?>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label"><i class="fas fa-id-badge me-1"></i> User ID</span>
        <span class="info-val">#<?= $user["id"] ?></span>
      </div>

      <div class="warn-box">
        <i class="fas fa-exclamation-circle" style="font-size:1.2rem;flex-shrink:0"></i>
        Deleting this user will permanently remove their account and all associated data. This cannot be undone.
      </div>

      <form method="POST" style="display:flex;gap:10px;">
        <button type="submit" class="btn-delete"><i class="fas fa-trash me-1"></i> Yes, Delete</button>
        <a href="manage_users.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i> Cancel</a>
      </form>
    </div>
  </main>
</div>
</body>
</html>
