<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$userId      = $_SESSION["user_id"]  ?? 0;
$studentName = $_SESSION["username"] ?? "Student";

// Ensure columns exist
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(300) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS zoom_personal_link TEXT NOT NULL DEFAULT ''");

// Fetch full user record
$stmt = $conn->prepare("SELECT id, username, role, email, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: logout.php");
    exit();
}

$success = "";
$error   = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (($_POST["action"] ?? "") === "delete_picture") {
        // ── Delete profile picture ──
        if (!empty($user["profile_picture"])) {
            $old = __DIR__ . "/uploads/profiles/" . $user["profile_picture"];
            if (file_exists($old)) @unlink($old);
        }
        $d = $conn->prepare("UPDATE users SET profile_picture = '' WHERE id = ?");
        $d->bind_param("i", $userId);
        $d->execute();
        $_SESSION["profile_picture"] = "";
        header("Location: student_profile.php?deleted=1");
        exit();

    } elseif (!empty($_FILES["profile_picture"]["name"])) {
        // ── Upload new profile picture ──
        $file    = $_FILES["profile_picture"];
        $allowed = ["image/jpeg", "image/png", "image/webp", "image/gif"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            $error = "Upload failed. Please try again.";
        } elseif (!in_array($file["type"], $allowed)) {
            $error = "Only JPG, PNG, WEBP, or GIF images are allowed.";
        } elseif ($file["size"] > 3 * 1024 * 1024) {
            $error = "Image must be under 3 MB.";
        } else {
            $ext      = pathinfo($file["name"], PATHINFO_EXTENSION);
            $filename = "user_" . $userId . "_" . time() . "." . strtolower($ext);
            $dest     = __DIR__ . "/uploads/profiles/" . $filename;

            if (!empty($user["profile_picture"])) {
                $old = __DIR__ . "/uploads/profiles/" . $user["profile_picture"];
                if (file_exists($old)) @unlink($old);
            }

            if (move_uploaded_file($file["tmp_name"], $dest)) {
                $u = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $u->bind_param("si", $filename, $userId);
                $u->execute();
                $_SESSION["profile_picture"] = $filename;
                header("Location: student_profile.php?uploaded=1");
                exit();
            } else {
                $error = "Failed to save the image. Please try again.";
            }
        }
    }
}

if (isset($_GET["deleted"]))  $success = "Profile picture removed.";
if (isset($_GET["uploaded"])) $success = "Profile picture updated successfully.";

// Re-fetch after possible update
$stmt = $conn->prepare("SELECT id, username, role, email, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$profilePic = !empty($user["profile_picture"])
    ? "uploads/profiles/" . htmlspecialchars($user["profile_picture"])
    : null;

$sidebarPic = $profilePic;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | Student</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
  --primary: #3e5077;
  --secondary: #143674;
  --dark: #0f172a;
  --muted: #64748b;
  --border: #dbeafe;
  --shadow: 0 18px 45px rgba(37,99,235,0.08);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; background: #f0f4ff; color: var(--dark); display: flex; min-height: 100vh; }

/* ── Sidebar ── */
.sidebar {
  width: 255px; flex-shrink: 0;
  background: linear-gradient(180deg, #0f172a 0%, #1e3a5f 100%);
  display: flex; flex-direction: column;
  padding: 22px 16px 18px; position: sticky; top: 0; height: 100vh; overflow-y: auto;
  transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease;
}
body.sidebar-collapsed .sidebar { width: 0; padding: 0; min-width: 0; overflow: hidden; }

/* ── Hamburger ── */
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:background 0.2s; }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }
.brand-wrap { display: flex; align-items: center; gap: 10px; padding-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 18px; }
.brand-logo-img { width: 44px; height: 44px; object-fit: contain; border-radius: 10px; flex-shrink: 0; }
.brand-title    { font-size: 1.05rem; font-weight: 900; margin: 0; color: #fff; line-height: 1.2; }
.brand-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.55); margin: 3px 0 0; letter-spacing: 1px; }
.student-box { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 14px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); margin-bottom: 18px; }
.student-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 18px; flex-shrink: 0; overflow: hidden; }
.student-avatar img { width: 100%; height: 100%; object-fit: cover; }
.student-name { font-weight: 800; margin: 0; color: #fff; }
.student-role { margin: 0; color: rgba(255,255,255,0.55); font-size: 0.85rem; }
.nav-link-custom { display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.8); text-decoration: none; padding: 11px 13px; border-radius: 12px; font-weight: 700; transition: background 0.2s; margin-bottom: 4px; }
.nav-link-custom:hover { background: rgba(255,255,255,0.09); color: #fff; }
.nav-link-custom.active { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; }
.nav-icon { width: 32px; height: 32px; border-radius: 9px; background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.nav-link-custom.active .nav-icon { background: rgba(255,255,255,0.18); }
.sidebar-bottom { margin-top: auto; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.08); }

/* ── Main ── */
.main { flex: 1; padding: 28px; overflow-y: auto; }
.topbar {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  border-radius: 20px; padding: 22px 26px; margin-bottom: 26px;
  display: flex; align-items: center; justify-content: space-between; gap: 14px;
  box-shadow: var(--shadow);
}
.topbar-title { font-size: 1.5rem; font-weight: 900; color: #fff; }
.topbar-sub   { color: rgba(255,255,255,0.75); font-size: 0.9rem; margin-top: 3px; }

/* ── Cards ── */
.profile-grid { display: grid; grid-template-columns: 280px 1fr; gap: 22px; align-items: start; }
.panel { background: white; border-radius: 20px; border: 1px solid #e2ecff; padding: 26px; box-shadow: var(--shadow); }
.panel-title { font-size: 1rem; font-weight: 900; color: var(--dark); padding-bottom: 14px; border-bottom: 1px solid #edf4ff; margin-bottom: 20px; }

/* ── Avatar card ── */
.avatar-wrap { display: flex; flex-direction: column; align-items: center; gap: 16px; }
.big-avatar {
  width: 120px; height: 120px; border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: #fff; display: flex; align-items: center; justify-content: center;
  font-size: 2.8rem; font-weight: 900; flex-shrink: 0;
  border: 4px solid #dbeafe; overflow: hidden;
}
.big-avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-name { font-weight: 900; font-size: 1.15rem; color: var(--dark); text-align: center; }
.avatar-role { font-size: 0.85rem; color: var(--muted); text-align: center; }
.upload-zone {
  width: 100%; border: 2px dashed #bfdbfe; border-radius: 14px;
  padding: 16px; text-align: center; cursor: pointer;
  transition: border-color 0.2s, background 0.2s; background: #f8fbff;
}
.upload-zone:hover { border-color: var(--primary); background: #eff6ff; }
.upload-zone input { display: none; }
.upload-label { font-size: 0.85rem; font-weight: 700; color: var(--muted); cursor: pointer; display: block; }
.upload-icon { font-size: 1.6rem; color: #93c5fd; margin-bottom: 6px; }
.btn-upload {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: white; border: none; border-radius: 10px;
  padding: 9px 20px; font-weight: 800; cursor: pointer; font-size: 0.88rem;
  width: 100%; margin-top: 10px;
}
.btn-upload:hover { opacity: 0.9; }
#preview-name { font-size: 0.8rem; color: var(--muted); margin-top: 6px; }
.btn-delete-pic {
  background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;
  border-radius: 10px; padding: 9px 20px; font-weight: 800;
  cursor: pointer; font-size: 0.88rem; width: 100%; margin-top: 8px;
  transition: background 0.2s;
}
.btn-delete-pic:hover { background: #fecaca; }

/* ── Info rows ── */
.info-row { display: flex; align-items: center; gap: 12px; padding: 13px 16px; border-radius: 12px; background: #f8fbff; border: 1px solid #e2e8f0; margin-bottom: 10px; }
.info-icon { width: 34px; height: 34px; border-radius: 10px; background: #dbeafe; color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.9rem; }
.info-label { font-size: 0.78rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
.info-val   { font-weight: 700; color: var(--dark); margin-top: 1px; }
.readonly-badge { background: #f1f5f9; color: #64748b; border-radius: 999px; padding: 2px 10px; font-size: 0.72rem; font-weight: 700; margin-left: 8px; }

@media (max-width: 900px) {
  .profile-grid { grid-template-columns: 1fr; }
  body { flex-direction: column; }
  .sidebar { width: 100%; height: auto; position: relative; }
}
</style>
</head>
<body>

<div class="sidebar">
  <div class="brand-wrap">
    <img src="images/robot2.png.png" class="brand-logo-img" alt="Logo">
    <div>
      <p class="brand-title">JuniorCode</p>
      <p class="brand-subtitle">STUDENT PANEL</p>
    </div>
  </div>

  <div class="student-box">
    <div class="student-avatar">
      <?php if ($sidebarPic): ?>
        <img src="<?= $sidebarPic ?>" alt="Profile">
      <?php else: ?>
        <?= strtoupper(substr($studentName, 0, 1)) ?>
      <?php endif; ?>
    </div>
    <div>
      <p class="student-name"><?= htmlspecialchars($studentName) ?></p>
      <p class="student-role">Student</p>
    </div>
  </div>

  <a href="student_dashboard.php" class="nav-link-custom">
    <span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span>
  </a>
    <a href="student_courses.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>My Courses</span>
    </a>
  <a href="student_classes.php" class="nav-link-custom">
    <span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span>
  </a>
  <a href="student_assignments.php" class="nav-link-custom">
    <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span>
  </a>
  <a href="student_quizzes.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span></a>
      <a href="student_certificates.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
<a href="student_chat.php" class="nav-link-custom">
    <span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span>
  </a>
  <a href="student_contact.php" class="nav-link-custom">
    <span class="nav-icon"><i class="fas fa-comments"></i></span><span>Contact Admin</span>
  </a>
  <div class="sidebar-bottom">
    <a href="student_profile.php" class="nav-link-custom active">
      <span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span>
    </a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom">
      <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span>
    </a>
  </div>
</div>

<div class="main">

  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
    <div class="hamburger-line"></div>
  </div>

  <div class="topbar">
    <div>
      <div class="topbar-title">My Profile</div>
      <div class="topbar-sub">View your account information and upload a profile picture.</div>
    </div>
    <i class="fas fa-user-circle" style="font-size:2rem;color:rgba(255,255,255,0.3)"></i>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success mb-3"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="profile-grid">

    <!-- Avatar + upload -->
    <div class="panel">
      <div class="panel-title"><i class="fas fa-camera me-2" style="color:var(--primary)"></i>Profile Picture</div>
      <div class="avatar-wrap">
        <div class="big-avatar" id="bigAvatar">
          <?php if ($profilePic): ?>
            <img src="<?= $profilePic ?>" alt="Profile" id="avatarImg">
          <?php else: ?>
            <span id="avatarLetter"><?= strtoupper(substr($studentName, 0, 1)) ?></span>
          <?php endif; ?>
        </div>
        <div class="avatar-name"><?= htmlspecialchars($user["username"]) ?></div>
        <div class="avatar-role">Student</div>

        <form method="POST" enctype="multipart/form-data" style="width:100%">
          <div class="upload-zone" onclick="document.getElementById('picInput').click()">
            <div class="upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
            <label class="upload-label">Click to choose a photo</label>
            <div style="font-size:0.75rem;color:#94a3b8;margin-top:4px;">JPG, PNG, WEBP — max 3 MB</div>
            <input type="file" id="picInput" name="profile_picture" accept="image/*" onchange="previewPic(this)">
          </div>
          <div id="preview-name"></div>
          <button type="submit" class="btn-upload"><i class="fas fa-upload me-1"></i> Upload Photo</button>
        </form>

        <?php if (!empty($user["profile_picture"])): ?>
        <form method="POST" style="width:100%" id="deletePhotoForm">
          <input type="hidden" name="action" value="delete_picture">
          <button type="button" class="btn-delete-pic" onclick="document.getElementById('confirmModal').style.display='flex'">
            <i class="fas fa-trash me-1"></i> Remove Photo
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Info -->
    <div class="panel">
      <div class="panel-title">
        <i class="fas fa-id-card me-2" style="color:var(--primary)"></i>Account Information
        <span class="readonly-badge"><i class="fas fa-lock me-1"></i>Read only</span>
      </div>

      <div class="info-row">
        <div class="info-icon"><i class="fas fa-user"></i></div>
        <div>
          <div class="info-label">Username</div>
          <div class="info-val"><?= htmlspecialchars($user["username"]) ?></div>
        </div>
      </div>

      <div class="info-row">
        <div class="info-icon"><i class="fas fa-shield-halved"></i></div>
        <div>
          <div class="info-label">Role</div>
          <div class="info-val" style="text-transform:capitalize"><?= htmlspecialchars($user["role"]) ?></div>
        </div>
      </div>

      <div class="info-row">
        <div class="info-icon"><i class="fas fa-envelope"></i></div>
        <div>
          <div class="info-label">Email</div>
          <div class="info-val"><?= !empty($user["email"]) ? htmlspecialchars($user["email"]) : '<span style="color:#94a3b8;font-weight:600">Not set</span>' ?></div>
        </div>
      </div>

      <div class="info-row">
        <div class="info-icon"><i class="fas fa-hashtag"></i></div>
        <div>
          <div class="info-label">Account ID</div>
          <div class="info-val">#<?= $user["id"] ?></div>
        </div>
      </div>

      <div style="margin-top:20px;padding:14px 16px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;font-size:0.85rem;font-weight:700;color:#9a3412;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-circle-info" style="flex-shrink:0"></i>
        To update your account details, please contact your admin.
      </div>
    </div>

  </div>
</div>

<!-- Confirm remove photo modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;padding:28px 28px 22px;max-width:340px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.18);text-align:center;">
    <div style="width:52px;height:52px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
      <i class="fas fa-trash" style="color:#dc2626;font-size:1.2rem;"></i>
    </div>
    <div style="font-size:1rem;font-weight:900;color:#0f172a;margin-bottom:8px;">Remove Photo?</div>
    <div style="font-size:0.87rem;color:#64748b;margin-bottom:22px;">Your profile picture will be permanently removed.</div>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button onclick="document.getElementById('confirmModal').style.display='none'"
        style="flex:1;padding:10px;border-radius:10px;border:1px solid #e2e8f0;background:#f1f5f9;color:#334155;font-weight:800;cursor:pointer;font-size:0.88rem;">
        Cancel
      </button>
      <button onclick="document.getElementById('deletePhotoForm').submit()"
        style="flex:1;padding:10px;border-radius:10px;border:none;background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff;font-weight:800;cursor:pointer;font-size:0.88rem;">
        Yes, Remove
      </button>
    </div>
  </div>
</div>

<script>
function previewPic(input) {
  const file = input.files[0];
  if (!file) return;
  document.getElementById('preview-name').textContent = file.name;
  const reader = new FileReader();
  reader.onload = function(e) {
    const ba = document.getElementById('bigAvatar');
    ba.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width:100%;height:100%;object-fit:cover;">';
  };
  reader.readAsDataURL(file);
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>
