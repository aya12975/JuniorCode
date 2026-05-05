<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$adminName = $_SESSION["username"] ?? "Admin";

// Handle inline edit
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_user_id"])) {
    $editId   = (int)$_POST["edit_user_id"];
    $username = trim($_POST["username"] ?? "");
    $role     = trim($_POST["role"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if ($username !== "" && $role !== "") {
        if ($password !== "") {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, plain_password=?, role=? WHERE id=?");
            $stmt->bind_param("ssssi", $username, $hashed, $password, $role, $editId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, role=? WHERE id=?");
            $stmt->bind_param("ssi", $username, $role, $editId);
        }
        $stmt->execute();
    }
    header("Location: manage_users.php?success=1");
    exit();
}

$filterRole = $_GET["role"] ?? "";
$search = trim($_GET["search"] ?? "");

// Ensure plain_password column exists
$chk = $conn->query("SHOW COLUMNS FROM users LIKE 'plain_password'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) NOT NULL DEFAULT ''");
}

$sql = "SELECT id, username, plain_password, role FROM users WHERE 1=1";
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
        background: linear-gradient(135deg, var(--primary), var(--secondary));;
        
    }

    .topbar h1 {
      font-size: 1.8rem;
      font-weight: 900;
      margin: 0;
      color: white;
    }

    .topbar p {
      margin: 4px 0 0;
      color: var(--muted);
    }

    .admin-badge {
      background: rgba(255,255,255,0.15);
      color: #f6f8fc;
      
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

    .pwd-cell { display:flex; align-items:center; gap:8px; }
    .pwd-text  { font-family:monospace; font-size:.9rem; letter-spacing:.05em; }
    .eye-btn   {
      background:none; border:none; cursor:pointer;
      color:var(--muted); padding:2px 6px; border-radius:6px;
      transition:color .15s;
    }
    .eye-btn:hover { color:var(--primary); }

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
<script>(function(){var t=localStorage.getItem("jc-theme");if(t==="dark")document.documentElement.classList.add("dark");})();</script><style>html.dark body{background:#0f172a!important;color:#e2e8f0!important}html.dark .sidebar{background:linear-gradient(180deg,#020817 0%,#0c1226 100%)!important}html.dark .panel-card,html.dark .stat-card{background:#1e293b!important;border-color:#334155!important;color:#e2e8f0}html.dark .stat-label{color:#94a3b8!important}html.dark .stat-value{color:#f1f5f9!important}html.dark .form-control,html.dark .form-select,html.dark textarea{background:#1e293b!important;border-color:#475569!important;color:#e2e8f0!important}html.dark .topbar{background:linear-gradient(135deg,#1e3a6e 0%,#0f2456 100%)!important}html.dark .table thead th{background:#1e293b!important;color:#94a3b8!important;border-color:#334155!important}html.dark .table td{color:#cbd5e1!important;border-color:#334155!important}html.dark .panel-title{color:#f1f5f9!important}</style>
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
        <a href="admin_dashboard.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-house"></i></span>
          <span>Dashboard</span>
        </a>

        <a href="manage_users.php" class="nav-link-custom active">
          <span class="nav-icon"><i class="fas fa-users"></i></span>
          <span>Manage Users</span>
        </a>

        <a href="manage_classes.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-book"></i></span>
          <span>Manage Classes</span>
        </a>

        <a href="teacher_earnings.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-dollar-sign"></i></span>
          <span>Teacher Earnings</span>
        </a>

        <a href="available_slots.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-calendar-days"></i></span>
          <span>Available Slots</span>
        </a>

        <a href="courses.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span>
          <span>Courses</span>
        </a>

        <a href="reports.php" class="nav-link-custom">
          <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
          <span>Reports</span>
        </a>

        <a href="settings.php" class="nav-link-custom">
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
                  <th>Password</th>
                  <th>Role</th>
                  <th style="width: 160px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php $rowIdx = 0; while ($user = $result->fetch_assoc()): $rowIdx++; ?>
                  <tr>
                    <td><?php echo htmlspecialchars($user["id"]); ?></td>
                    <td><?php echo htmlspecialchars($user["username"]); ?></td>
                    <td>
                      <?php if ($user["role"] === "admin"): ?>
                        <span style="color:var(--muted);font-size:.85rem;">—</span>
                      <?php else: ?>
                        <div class="pwd-cell">
                          <span class="pwd-text" id="pwd-<?= $rowIdx ?>" data-val="<?= htmlspecialchars($user['plain_password']) ?>">
                            <?= $user['plain_password'] !== '' ? '••••••••' : '<em style="color:var(--muted);font-size:.82rem">not set</em>' ?>
                          </span>
                          <?php if ($user['plain_password'] !== ''): ?>
                            <button class="eye-btn" onclick="togglePwd(<?= $rowIdx ?>)" title="Show/hide password">
                              <i class="fas fa-eye" id="eye-<?= $rowIdx ?>"></i>
                            </button>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="role-badge role-<?php echo htmlspecialchars($user["role"]); ?>">
                        <?php echo htmlspecialchars(ucfirst($user["role"])); ?>
                      </span>
                    </td>
                    <td>
                      <button class="action-btn edit-btn" style="background:none;border:none;padding:0;cursor:pointer;"
                        onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['username'])) ?>', '<?= htmlspecialchars($user['role']) ?>')">Edit</button>
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
<!-- Edit User Modal -->
<div id="editModal" style="
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(15,23,42,0.55); backdrop-filter:blur(4px);
  align-items:center; justify-content:center;
">
  <div style="
    background:#fff; border-radius:24px; padding:36px 32px;
    width:100%; max-width:480px; margin:16px;
    box-shadow:0 24px 60px rgba(15,23,42,0.2);
    animation: modalIn .25s ease;
  ">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <h2 style="margin:0;font-weight:900;font-size:1.35rem;color:#0f172a;">Edit User</h2>
      <button onclick="closeEditModal()" style="background:none;border:none;cursor:pointer;font-size:1.4rem;color:#64748b;line-height:1;">&times;</button>
    </div>

    <form method="POST" id="editForm">
      <input type="hidden" name="edit_user_id" id="modal_id">

      <div style="margin-bottom:18px;">
        <label style="font-weight:800;color:#334155;display:block;margin-bottom:8px;">Username</label>
        <input type="text" name="username" id="modal_username" class="form-control" required>
      </div>

      <div style="margin-bottom:18px;">
        <label style="font-weight:800;color:#334155;display:block;margin-bottom:8px;">New Password <span style="font-weight:400;color:#94a3b8;font-size:0.85rem;">(leave blank to keep current)</span></label>
        <div class="input-group">
          <input type="password" name="password" id="modal_password" class="form-control" placeholder="Enter new password">
          <button type="button" class="btn btn-outline-secondary" onclick="toggleModalPassword()">
            <i class="fas fa-eye" id="modal_eye"></i>
          </button>
        </div>
      </div>

      <div style="margin-bottom:28px;">
        <label style="font-weight:800;color:#334155;display:block;margin-bottom:8px;">Role</label>
        <select name="role" id="modal_role" class="form-select" required>
          <option value="admin">Admin</option>
          <option value="teacher">Teacher</option>
          <option value="student">Student</option>
        </select>
      </div>

      <div style="display:flex;gap:12px;">
        <button type="submit" style="
          flex:1; height:50px; border:none; border-radius:14px;
          background:linear-gradient(135deg,#3e5077,#143674);
          color:#fff; font-weight:900; font-size:1rem; cursor:pointer;
        ">Save Changes</button>
        <button type="button" onclick="closeEditModal()" style="
          height:50px; padding:0 22px; border:2px solid #e2e8f0;
          border-radius:14px; background:#fff; font-weight:700;
          color:#64748b; cursor:pointer;
        ">Cancel</button>
      </div>
    </form>
  </div>
</div>

<style>
@keyframes modalIn {
  from { opacity:0; transform:translateY(-16px) scale(.97); }
  to   { opacity:1; transform:translateY(0)     scale(1);   }
}
</style>

<script>
function openEditModal(id, username, role) {
  document.getElementById('modal_id').value       = id;
  document.getElementById('modal_username').value = username;
  document.getElementById('modal_role').value     = role;
  document.getElementById('modal_password').value = '';
  document.getElementById('modal_eye').className  = 'fas fa-eye';
  document.getElementById('modal_password').type  = 'password';
  const m = document.getElementById('editModal');
  m.style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

function toggleModalPassword() {
  const input = document.getElementById('modal_password');
  const icon  = document.getElementById('modal_eye');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}

// Close on backdrop click
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});

function togglePwd(idx) {
  const span = document.getElementById('pwd-' + idx);
  const icon  = document.getElementById('eye-' + idx);
  if (span.dataset.visible === '1') {
    span.textContent = '••••••••';
    span.dataset.visible = '0';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  } else {
    span.textContent = span.dataset.val;
    span.dataset.visible = '1';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  }
}
</script>
</body>
</html>