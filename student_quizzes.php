<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION["username"] ?? "Student";

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL,
    topic VARCHAR(255) NOT NULL, section VARCHAR(50) NOT NULL DEFAULT 'kids',
    difficulty VARCHAR(50) NOT NULL DEFAULT 'beginner',
    created_by VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY, quiz_id INT NOT NULL,
    question TEXT NOT NULL, choice_a TEXT NOT NULL, choice_b TEXT NOT NULL,
    choice_c TEXT NOT NULL, choice_d TEXT NOT NULL, correct_answer CHAR(1) NOT NULL,
    explanation TEXT NOT NULL DEFAULT '',
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS quiz_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY, quiz_id INT NOT NULL,
    student_username VARCHAR(255) NOT NULL, assigned_by VARCHAR(255) NOT NULL DEFAULT '',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assign (quiz_id, student_username),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$quizzes = [];
$stmt = $conn->prepare(
    "SELECT q.*, qa.assigned_by, qa.assigned_at AS assigned_time,
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS q_count
     FROM quizzes q
     JOIN quiz_assignments qa ON qa.quiz_id = q.id
     WHERE qa.student_username = ?
     ORDER BY qa.assigned_at DESC"
);
$stmt->bind_param("s", $studentName);
$stmt->execute();
$qRes = $stmt->get_result();
while ($row = $qRes->fetch_assoc()) $quizzes[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quizzes | Student</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --shadow:0 18px 45px rgba(37,99,235,0.08); }
* { box-sizing:border-box; }
body { margin:0; font-family:Arial,Helvetica,sans-serif; color:var(--dark); background: radial-gradient(circle at top left,rgba(29,78,216,0.07),transparent 25%), radial-gradient(circle at bottom right,rgba(14,165,233,0.07),transparent 25%), linear-gradient(180deg,#f8fbff 0%,#eaf4ff 100%); }

.sidebar { position:fixed; top:0; left:0; width:260px; height:100vh; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); display:flex; flex-direction:column; justify-content:space-between; z-index:1000; overflow-y:auto; transition:transform 0.3s; }
body.sidebar-collapsed .sidebar { transform:translateX(-260px); }
.sidebar-top { padding:20px 16px; }
.brand { display:flex; align-items:center; gap:12px; padding:10px 10px 20px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:16px; }
.brand-logo-img { width:55px; height:55px; object-fit:contain; flex-shrink:0; }
.brand-title { font-size:1.05rem; font-weight:900; margin:0; color:#fff; line-height:1.2; }
.brand-subtitle { font-size:0.75rem; color:rgba(255,255,255,0.55); margin:3px 0 0; letter-spacing:1px; }
.student-box { display:flex; align-items:center; gap:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:14px; margin-bottom:18px; }
.student-avatar { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; font-weight:bold; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; overflow:hidden; }
.student-avatar img { width:100%; height:100%; object-fit:cover; }
.student-name { font-weight:800; margin:0; color:#fff; }
.student-role { margin:0; color:rgba(255,255,255,0.55); font-size:0.85rem; }
.nav-link-custom { display:flex; align-items:center; gap:12px; text-decoration:none; color:rgba(255,255,255,0.78); padding:12px 14px; border-radius:14px; margin:4px 0; font-weight:700; transition:all 0.22s; }
.nav-link-custom:hover { background:rgba(255,255,255,0.09); color:#fff; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; box-shadow:0 8px 20px rgba(30,50,100,0.35); }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.nav-link-custom.active .nav-icon { background:rgba(255,255,255,0.18); }
.sidebar-bottom { padding:16px; border-top:1px solid rgba(255,255,255,0.1); }

.main { margin-left:260px; padding:26px; min-height:100vh; transition:margin-left 0.3s; }
body.sidebar-collapsed .main { margin-left:0; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

.hero { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; border-radius:22px; padding:18px 20px; margin-bottom:22px; box-shadow:0 12px 28px rgba(37,99,235,0.3); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; }
.hero h2 { margin:0; font-size:1.5rem; font-weight:900; }
.hero p  { margin:4px 0 0; color:rgba(255,255,255,0.8); }

.panel-card { background:white; border:1px solid #edf4ff; border-radius:22px; padding:22px; margin-bottom:22px; box-shadow:var(--shadow); position:relative; overflow:hidden; }
.panel-card::before { content:''; display:block; height:5px; background:linear-gradient(135deg,var(--primary),var(--secondary)); position:absolute; top:0; left:0; right:0; border-radius:22px 22px 0 0; }
.panel-title { font-size:1.1rem; font-weight:900; color:var(--primary); margin-bottom:14px; }
.panel-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
.panel-header .panel-title { margin-bottom:0; }

.quiz-item { display:flex; align-items:center; gap:14px; background:#f8fbff; border:1px solid #dbeafe; border-radius:14px; padding:14px 18px; margin-bottom:10px; flex-wrap:wrap; }
.quiz-badge { display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; font-size:1.1rem; flex-shrink:0; }
.quiz-meta { flex:1; min-width:0; }
.quiz-title-text { font-weight:900; color:#0f172a; font-size:0.97rem; }
.quiz-sub { font-size:0.82rem; color:var(--muted); margin-top:6px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; }

.diff-chip { display:inline-block; padding:4px 12px; border-radius:999px; font-size:0.78rem; font-weight:800; }
.diff-beginner     { background:#dcfce7; color:#166534; }
.diff-intermediate { background:#fef9c3; color:#854d0e; }
.diff-advanced     { background:#fee2e2; color:#991b1b; }
.sec-chip { display:inline-block; padding:4px 12px; border-radius:999px; font-size:0.78rem; font-weight:800; background:#dbeafe; color:#1d4ed8; }

.btn-take { background:linear-gradient(135deg,#22c55e,#16a34a); color:white; border:none; font-weight:800; border-radius:12px; padding:11px 20px; cursor:pointer; text-decoration:none; font-size:0.9rem; display:inline-flex; align-items:center; gap:7px; }
.btn-take:hover { opacity:0.9; color:white; }

.empty-box { text-align:center; padding:48px 18px; border-radius:18px; background:#f8fbff; color:var(--muted); border:1px dashed #d9e9ff; font-weight:700; }
</style>
</head>
<body>
<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand">
      <img src="images/robot2.png.png" class="brand-logo-img" alt="JuniorCode">
      <div>
        <p class="brand-title">JuniorCode</p>
        <p class="brand-subtitle">STUDENT PORTAL</p>
      </div>
    </div>
    <div class="student-box">
      <div class="student-avatar">
        <?php if (!empty($_SESSION["profile_picture"])): ?>
          <img src="uploads/profiles/<?= htmlspecialchars($_SESSION["profile_picture"]) ?>" alt="Profile">
        <?php else: ?>
          <?= strtoupper(substr($studentName, 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <p class="student-name"><?= htmlspecialchars($studentName) ?></p>
        <p class="student-role">Student</p>
      </div>
    </div>
    <a href="student_dashboard.php"   class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
    <a href="student_classes.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>My Classes</span></a>
    <a href="student_assignments.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-clipboard-list"></i></span><span>My Assignments</span></a>
    <a href="student_quizzes.php"     class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>Quizzes</span></a>
    <a href="student_certificates.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
    <a href="student_chat.php"        class="nav-link-custom"><span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span></a>
    <a href="student_contact.php"     class="nav-link-custom"><span class="nav-icon"><i class="fas fa-comments"></i></span><span>Contact Admin</span></a>
  </div>
  <div class="sidebar-bottom">
    <a href="student_profile.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
    <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
    <a href="logout.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
  </div>
</div>

<div class="main">
  <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
    <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
  </div>

  <div class="hero">
    <div>
      <h2><i class="fas fa-circle-question me-2"></i>Quizzes</h2>
      <p>Test your knowledge with AI-generated quizzes from your teachers</p>
    </div>
    <div style="background:rgba(255,255,255,0.15);color:white;border-radius:999px;padding:10px 16px;font-weight:800;">
      <?= htmlspecialchars($studentName) ?>
    </div>
  </div>

  <div class="panel-card">
    <div class="panel-header">
      <div class="panel-title"><i class="fas fa-list-check me-2" style="color:#2563eb;"></i>Available Quizzes</div>
      <span style="color:var(--muted);font-size:0.88rem;font-weight:700;"><?= count($quizzes) ?> quiz<?= count($quizzes) !== 1 ? 'zes' : '' ?></span>
    </div>

    <?php if (empty($quizzes)): ?>
      <div class="empty-box">
        <i class="fas fa-circle-question" style="font-size:2.5rem;color:#bfdbfe;margin-bottom:14px;display:block;"></i>
        No quizzes assigned to you yet.<br>
        <span style="font-size:0.88rem;font-weight:600;color:#94a3b8;margin-top:6px;display:block;">Your teacher will send you a quiz soon!</span>
      </div>
    <?php else: ?>
      <?php foreach ($quizzes as $qz): ?>
      <div class="quiz-item">
        <div class="quiz-badge"><i class="fas fa-circle-question"></i></div>
        <div class="quiz-meta">
          <div class="quiz-title-text"><?= htmlspecialchars($qz["title"]) ?></div>
          <div class="quiz-sub">
            <span class="sec-chip"><?= htmlspecialchars(ucfirst($qz["section"])) ?></span>
            <span class="diff-chip diff-<?= htmlspecialchars($qz["difficulty"]) ?>"><?= htmlspecialchars(ucfirst($qz["difficulty"])) ?></span>
            <span><?= (int)$qz["q_count"] ?> questions</span>
            <?php if (!empty($qz["assigned_by"])): ?>
              <span>· from <strong><?= htmlspecialchars($qz["assigned_by"]) ?></strong></span>
            <?php endif; ?>
            <?php if (!empty($qz["assigned_time"])): ?>
              <span>· <?= date("d M Y", strtotime($qz["assigned_time"])) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <a href="quiz_take.php?id=<?= $qz["id"] ?>" class="btn-take">
          <i class="fas fa-play"></i> Start Quiz
        </a>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<script src="logout-modal.js"></script>
</body>
</html>
