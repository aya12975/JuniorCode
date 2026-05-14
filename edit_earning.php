<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: teacher_earnings.php");
    exit();
}

$id = (int) $_GET["id"];
$adminName = $_SESSION["username"] ?? "Admin";
$message = "";
$month = preg_match('/^\d{4}-\d{2}$/', $_GET["month"] ?? "") ? $_GET["month"] : date("Y-m");

$stmt = $conn->prepare("SELECT * FROM teacher_earnings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: teacher_earnings.php");
    exit();
}

$earning = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacher_name = trim($_POST["teacher_name"]);
    $lesson_title = trim($_POST["lesson_title"]);
    $amount       = trim($_POST["amount"]);
    $lesson_date  = trim($_POST["lesson_date"]);
    $notes        = trim($_POST["notes"]);

    $update = $conn->prepare("UPDATE teacher_earnings SET teacher_name = ?, lesson_title = ?, amount = ?, lesson_date = ?, notes = ? WHERE id = ?");
    $update->bind_param("ssdssi", $teacher_name, $lesson_title, $amount, $lesson_date, $notes, $id);

    if ($update->execute()) {
        header("Location: teacher_earnings.php?month=" . urlencode($month) . "&updated=1");
        exit();
    } else {
        $message = "Error updating earning.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Earning | JuniorCode Admin</title>
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
  color: white; padding:  0;
  position: sticky; top: 0; height: 100vh; flex-shrink: 0;
  transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease; overflow-y: auto;
  display: flex; flex-direction: column;
}
body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow-y: auto; }
.sidebar-bottom { padding: 16px 18px; }
.sidebar-top-area { padding: 0 18px 18px; }
.brand-box { display: flex; align-items: center; gap: 12px; padding: 0 4px 22px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }
.logo-img { width: 55px; height: 55px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
.brand-title { font-weight: 900; font-size: 1.1rem; line-height: 1.15; }
.brand-sub { font-size: 0.75rem; color: rgba(255,255,255,0.55); letter-spacing: 1px; margin-top: 3px; }
.nav-title { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1.3px; color: rgba(255,255,255,0.45); margin: 20px 10px 10px; font-weight: 700; }
.nav-custom { display: flex; flex-direction: column; gap: 4px; }
.nav-link-custom {
  display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.78);
  text-decoration: none; padding: 12px 14px; border-radius: 14px; transition: all 0.25s; font-weight: 700;
}
.nav-link-custom:hover { background: rgba(255,255,255,0.08); color: white; }
.nav-link-custom.active { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
.nav-icon { width: 32px; height: 32px; border-radius: 10px; background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.main-content { flex: 1; padding: 26px; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }
.topbar {
  display: flex; justify-content: space-between; align-items: center; gap: 16px;
  margin-bottom: 24px; padding: 18px 20px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  border-radius: 22px; box-shadow: var(--shadow);
}
.topbar h1 { font-size: 1.6rem; font-weight: 900; margin: 0; color: white; }
.topbar p  { margin: 4px 0 0; color: rgba(255,255,255,0.8); }
.admin-badge { background: rgba(255,255,255,0.15); color: white; border-radius: 12px; border: 1px solid rgba(255,255,255,0.2); padding: 10px 18px; font-weight: 800; white-space: nowrap; }
.panel-card { background: white; border: 1px solid #edf4ff; border-radius: 22px; padding: 28px; box-shadow: var(--shadow); max-width: 680px; }
.panel-title { font-size: 1.15rem; font-weight: 900; margin-bottom: 22px; color: var(--dark); padding-bottom: 14px; border-bottom: 1px solid #edf4ff; }
.form-label { font-weight: 800; color: var(--dark); font-size: 0.9rem; margin-bottom: 6px; }
.form-control {
  border-radius: 12px; padding: 11px 14px; border: 1px solid #dbe4f0;
  font-size: 0.95rem; width: 100%;
}
.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(62,80,119,0.13); outline: none; }
.btn-save {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  border: none; color: white; font-weight: 800; border-radius: 12px;
  padding: 11px 24px; cursor: pointer; font-size: 0.95rem;
}
.btn-save:hover { opacity: 0.92; }
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
      <a href="admin_dashboard.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
      <a href="manage_users.php"    class="nav-link-custom"><span class="nav-icon"><i class="fas fa-users"></i></span><span>Manage Users</span></a>
      <a href="manage_classes.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
      <a href="teacher_earnings.php" class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
      <a href="available_slots.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
      <a href="courses.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
      <a href="reports.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
      <a href="admin_certificates.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
    </div>
      </div>
      <div class="sidebar-bottom">
        <a href="settings.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
        <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
        <a href="logout.php"          class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
      </div>
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
        <h1>Edit Earning</h1>
        <p>Update the earning record details below.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i>Hello, <?= htmlspecialchars($adminName) ?> &nbsp;·&nbsp; <?= date("d M Y") ?></div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-danger mb-3"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="panel-card">
      <div class="panel-title"><i class="fas fa-pen me-2" style="color:var(--primary)"></i>Earning #<?= $id ?></div>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Teacher Name</label>
          <input type="text" name="teacher_name" class="form-control"
                 value="<?= htmlspecialchars($earning["teacher_name"]) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Lesson Title</label>
          <input type="text" name="lesson_title" class="form-control"
                 value="<?= htmlspecialchars($earning["lesson_title"]) ?>" required>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Amount ($)</label>
            <input type="number" step="0.01" name="amount" class="form-control"
                   value="<?= htmlspecialchars($earning["amount"]) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Lesson Date</label>
            <input type="date" name="lesson_date" class="form-control"
                   value="<?= htmlspecialchars($earning["lesson_date"]) ?>" required>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Notes <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
          <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($earning["notes"]) ?></textarea>
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn-save"><i class="fas fa-check me-1"></i> Save Changes</button>
          <a href="teacher_earnings.php?month=<?= urlencode($month) ?>" class="btn-back"><i class="fas fa-arrow-left me-1"></i> Cancel</a>
        </div>
      </form>
    </div>
  </main>
</div>
<script src="logout-modal.js"></script>
</body>
</html>


