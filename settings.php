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

/* create settings table if not exists */
$conn->query("
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL
    )
");

/* helper functions */
function isActive($page, $currentPage) {
    return $page === $currentPage ? "active" : "";
}

function getSetting($conn, $key, $default = "") {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        return $row["setting_value"];
    }
    return $default;
}

function saveSetting($conn, $key, $value) {
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $key, $value);
    return $stmt->execute();
}

/* save settings */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $academy_name      = trim($_POST["academy_name"] ?? "");
    $admin_email       = trim($_POST["admin_email"] ?? "");
    $whatsapp_number   = trim($_POST["whatsapp_number"] ?? "");
    $currency          = trim($_POST["currency"] ?? "");
    $timezone          = trim($_POST["timezone"] ?? "");
    $trial_message     = trim($_POST["trial_message"] ?? "");
    $welcome_message   = trim($_POST["welcome_message"] ?? "");
    $system_status     = trim($_POST["system_status"] ?? "");
    $class_duration    = trim($_POST["class_duration"] ?? "");
    $session_timeout   = trim($_POST["session_timeout"] ?? "");

    $ok = true;
    $ok = $ok && saveSetting($conn, "academy_name", $academy_name);
    $ok = $ok && saveSetting($conn, "admin_email", $admin_email);
    $ok = $ok && saveSetting($conn, "whatsapp_number", $whatsapp_number);
    $ok = $ok && saveSetting($conn, "currency", $currency);
    $ok = $ok && saveSetting($conn, "timezone", $timezone);
    $ok = $ok && saveSetting($conn, "trial_message", $trial_message);
    $ok = $ok && saveSetting($conn, "welcome_message", $welcome_message);
    $ok = $ok && saveSetting($conn, "system_status", $system_status);
    $ok = $ok && saveSetting($conn, "class_duration", $class_duration);
    $ok = $ok && saveSetting($conn, "session_timeout", $session_timeout);

    $message = $ok ? "Settings saved successfully." : "Failed to save some settings.";
}

/* load settings */
$academy_name    = getSetting($conn, "academy_name", "JuniorCode Academy");
$admin_email     = getSetting($conn, "admin_email", "admin@juniorcode.com");
$whatsapp_number = getSetting($conn, "whatsapp_number", "+961");
$currency        = getSetting($conn, "currency", "USD");
$timezone        = getSetting($conn, "timezone", "Asia/Beirut");
$trial_message   = getSetting($conn, "trial_message", "The admin will contact you soon with the next steps.");
$welcome_message = getSetting($conn, "welcome_message", "Welcome to JuniorCode Academy.");
$system_status   = getSetting($conn, "system_status", "active");
$class_duration  = getSetting($conn, "class_duration", "60");
$session_timeout = getSetting($conn, "session_timeout", "30");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings | JuniorCode Admin</title>
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
      margin-bottom: 22px;
    }

    .panel-title {
      font-size: 1.15rem;
      font-weight: 900;
      margin: 0 0 18px 0;
    }

    .form-control,
    .form-select,
    textarea {
      border-radius: 14px;
      padding: 12px 14px;
      border: 1px solid #dbe4f0;
    }

    textarea {
      min-height: 110px;
    }

    .btn-main {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border: none;
      color: white;
      font-weight: 800;
      border-radius: 14px;
      padding: 12px 18px;
      text-decoration: none;
      display: inline-block;
    }

    .btn-main:hover {
      color: white;
      opacity: 0.95;
    }

    .settings-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 22px;
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

      .settings-grid {
        grid-template-columns: 1fr;
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
          <h1>Settings</h1>
          <p>Manage platform information, messages, and system preferences.</p>
        </div>
        <div class="admin-badge">
          Hello, <?php echo htmlspecialchars($adminName); ?>
        </div>
      </div>

      <?php if ($message !== ""): ?>
        <div class="alert <?php echo str_contains($message, 'successfully') ? 'alert-success' : 'alert-danger'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="settings-grid">
          <section class="panel-card">
            <h2 class="panel-title">Website Information</h2>

            <div class="mb-3">
              <label class="form-label">Academy Name</label>
              <input type="text" name="academy_name" class="form-control" value="<?php echo htmlspecialchars($academy_name); ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Admin Email</label>
              <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($admin_email); ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">WhatsApp Number</label>
              <input type="text" name="whatsapp_number" class="form-control" value="<?php echo htmlspecialchars($whatsapp_number); ?>">
            </div>
          </section>

          <section class="panel-card">
            <h2 class="panel-title">System Preferences</h2>

            <div class="mb-3">
              <label class="form-label">Currency</label>
              <select name="currency" class="form-select">
                <option value="USD" <?php echo $currency === "USD" ? "selected" : ""; ?>>USD</option>
                <option value="LBP" <?php echo $currency === "LBP" ? "selected" : ""; ?>>LBP</option>
                <option value="EUR" <?php echo $currency === "EUR" ? "selected" : ""; ?>>EUR</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Time Zone</label>
              <input type="text" name="timezone" class="form-control" value="<?php echo htmlspecialchars($timezone); ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">System Status</label>
              <select name="system_status" class="form-select">
                <option value="active" <?php echo $system_status === "active" ? "selected" : ""; ?>>Active</option>
                <option value="maintenance" <?php echo $system_status === "maintenance" ? "selected" : ""; ?>>Maintenance</option>
              </select>
            </div>
          </section>

          <section class="panel-card">
            <h2 class="panel-title">Class Settings</h2>

            <div class="mb-3">
              <label class="form-label">Default Class Duration (minutes)</label>
              <input type="number" name="class_duration" class="form-control" value="<?php echo htmlspecialchars($class_duration); ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Session Timeout (minutes)</label>
              <input type="number" name="session_timeout" class="form-control" value="<?php echo htmlspecialchars($session_timeout); ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Welcome Message</label>
              <textarea name="welcome_message" class="form-control"><?php echo htmlspecialchars($welcome_message); ?></textarea>
            </div>
          </section>

          <section class="panel-card">
            <h2 class="panel-title">Trial Form Settings</h2>

            <div class="mb-3">
              <label class="form-label">Trial Success Message</label>
              <textarea name="trial_message" class="form-control"><?php echo htmlspecialchars($trial_message); ?></textarea>
            </div>
          </section>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn-main">Save Settings</button>
        </div>
      </form>
    </main>
  </div>
</body>
</html>