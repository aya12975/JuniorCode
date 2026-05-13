<?php
session_start();
require_once "db.php";
require_once "admin_prefs.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$adminName   = $_SESSION["username"] ?? "Admin";
$currentPage = basename($_SERVER["PHP_SELF"]);

// Create tables if needed
$conn->query("CREATE TABLE IF NOT EXISTS quizzes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    topic      VARCHAR(255) NOT NULL,
    section    VARCHAR(50)  NOT NULL DEFAULT 'kids',
    difficulty VARCHAR(50)  NOT NULL DEFAULT 'beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS time_limit INT DEFAULT NULL");
$conn->query("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS created_by VARCHAR(255) NOT NULL DEFAULT ''");

$conn->query("CREATE TABLE IF NOT EXISTS quiz_questions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id        INT  NOT NULL,
    question       TEXT NOT NULL,
    choice_a       TEXT NOT NULL,
    choice_b       TEXT NOT NULL,
    choice_c       TEXT NOT NULL,
    choice_d       TEXT NOT NULL,
    correct_answer CHAR(1) NOT NULL,
    explanation    TEXT NOT NULL DEFAULT '',
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS quiz_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY, quiz_id INT NOT NULL,
    student_username VARCHAR(255) NOT NULL, assigned_by VARCHAR(255) NOT NULL DEFAULT '',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assign (quiz_id, student_username),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$students = [];
$sRes = $conn->query("SELECT username FROM users WHERE role='student' ORDER BY username ASC");
if ($sRes) while ($row = $sRes->fetch_assoc()) $students[] = $row['username'];

$flash = "";

// Save quiz
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_quiz") {
    $title      = trim($_POST["title"]      ?? "");
    $topic      = trim($_POST["topic"]      ?? "");
    $section    = trim($_POST["section"]    ?? "kids");
    $difficulty = trim($_POST["difficulty"] ?? "beginner");
    $timeLimit  = (int)($_POST["time_limit"] ?? 0);
    $questions  = json_decode($_POST["questions_json"] ?? "[]", true);

    if ($title && $topic && is_array($questions) && count($questions) > 0) {
        $ins = $conn->prepare("INSERT INTO quizzes (title, topic, section, difficulty, time_limit) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param("ssssi", $title, $topic, $section, $difficulty, $timeLimit);
        $ins->execute();
        $qid = $conn->insert_id;

        $insQ = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, choice_a, choice_b, choice_c, choice_d, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($questions as $q) {
            $qs  = (string)($q['question'] ?? '');
            $ca  = (string)($q['choices']['A'] ?? '');
            $cb  = (string)($q['choices']['B'] ?? '');
            $cc  = (string)($q['choices']['C'] ?? '');
            $cd  = (string)($q['choices']['D'] ?? '');
            $ans = strtoupper(substr((string)($q['answer'] ?? 'A'), 0, 1));
            $exp = (string)($q['explanation'] ?? '');
            $insQ->bind_param("isssssss", $qid, $qs, $ca, $cb, $cc, $cd, $ans, $exp);
            $insQ->execute();
        }
        $flash = "saved";
    }
    header("Location: admin_quiz_generator.php?saved=1");
    exit();
}

// Delete quiz
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_quiz") {
    $delId = (int)($_POST["quiz_id"] ?? 0);
    if ($delId > 0) {
        $del = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
        $del->bind_param("i", $delId);
        $del->execute();
    }
    header("Location: admin_quiz_generator.php?deleted=1");
    exit();
}

// Load saved quizzes
$quizzes = [];
$qRes = $conn->query("SELECT q.*, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS q_count FROM quizzes q ORDER BY q.created_at DESC");
if ($qRes) {
    while ($row = $qRes->fetch_assoc()) $quizzes[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Quiz Generator | JuniorCode</title>
<?= darkModeCSS() ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#3e5077; --secondary:#143674; --dark:#0f172a; --muted:#64748b; --shadow:0 18px 45px rgba(37,99,235,0.08); }
* { box-sizing:border-box; }
body { margin:0; font-family:Arial,Helvetica,sans-serif; background: radial-gradient(circle at top left,rgba(37,99,235,0.08),transparent 22%), radial-gradient(circle at bottom right,rgba(56,189,248,0.08),transparent 22%), linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%); color:var(--dark); }
.app-shell { min-height:100vh; display:flex; }

.sidebar { width:285px; background:linear-gradient(180deg,#0f172a 0%,#172554 100%); color:white; position:sticky; top:0; height:100vh; display:flex; flex-direction:column; transition:width 0.3s; overflow:hidden; }
body.sidebar-collapsed .sidebar { width:0; }
.sidebar-top-area { padding:0 18px 18px; flex:1; overflow-y:auto; }
.sidebar-bottom { padding:16px 18px; border-top:1px solid rgba(255,255,255,0.1); }
.brand-box { display:flex; align-items:center; gap:12px; padding:0 4px 22px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px; }
.logo-img { width:55px; height:55px; object-fit:contain; border-radius:12px; flex-shrink:0; }
.brand-title { font-weight:900; font-size:1.1rem; }
.brand-sub { font-size:0.75rem; color:rgba(255,255,255,0.55); letter-spacing:1px; margin-top:3px; }
.nav-title { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.3px; color:rgba(255,255,255,0.45); margin:20px 10px 10px; font-weight:700; }
.nav-link-custom { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,0.78); text-decoration:none; padding:12px 14px; border-radius:14px; transition:all 0.25s; font-weight:700; }
.nav-link-custom:hover { background:rgba(255,255,255,0.08); color:white; }
.nav-link-custom.active { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; box-shadow:0 10px 24px rgba(37,99,235,0.28); }
.nav-icon { width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }

.main-content { flex:1; padding:26px; }
.hamburger-btn { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:18px; width:fit-content; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.hamburger-btn:hover { background:#f1f5f9; }
.hamburger-line { width:22px; height:2.5px; background:#334155; border-radius:2px; }

.topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:24px; padding:18px 20px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:22px; box-shadow:var(--shadow); }
.topbar h1 { font-size:1.8rem; font-weight:900; margin:0; color:white; }
.topbar p  { margin:4px 0 0; color:rgba(255,255,255,0.8); }
.admin-badge { background:rgba(255,255,255,0.15); color:#f6f8fc; border-radius:12px; border:1px solid rgba(255,255,255,0.2); padding:10px 18px; font-weight:800; white-space:nowrap; }

.panel-card { background:white; border:1px solid #edf4ff; border-radius:22px; padding:22px; margin-bottom:22px; box-shadow:var(--shadow); }
.panel-title { font-size:1.15rem; font-weight:900; color:var(--primary); margin-bottom:16px; }
.panel-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
.panel-header .panel-title { margin-bottom:0; }

.form-label { font-weight:700; color:#334155; font-size:0.9rem; margin-bottom:6px; display:block; }
.form-control, .form-select { border-radius:12px; padding:11px 14px; border:1px solid #dbe4f0; width:100%; font-size:0.95rem; }
.form-control:focus, .form-select:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(62,80,119,0.12); }

.btn-main { background:linear-gradient(135deg,var(--primary),var(--secondary)); border:none; color:white; font-weight:800; border-radius:14px; padding:12px 22px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; font-size:0.95rem; text-decoration:none; }
.btn-main:hover { opacity:0.93; color:white; }
.btn-main:disabled { opacity:0.55; cursor:not-allowed; }

.btn-danger { background:linear-gradient(135deg,#dc2626,#b91c1c); border:none; color:white; font-weight:800; border-radius:12px; padding:9px 18px; cursor:pointer; display:inline-flex; align-items:center; gap:7px; font-size:0.85rem; }
.btn-danger:hover { opacity:0.9; }

.gen-form { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:640px) { .gen-form { grid-template-columns:1fr; } }

/* Preview area */
.preview-wrap { margin-top:24px; display:none; }
.preview-wrap.visible { display:block; }

.q-card { background:#f8fbff; border:1px solid #dbeafe; border-radius:16px; padding:18px 20px; margin-bottom:14px; }
.q-num { font-size:0.78rem; font-weight:800; color:var(--muted); margin-bottom:6px; }
.q-text { font-weight:800; color:#0f172a; font-size:1rem; margin-bottom:14px; line-height:1.5; }
.q-choices { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
@media(max-width:540px) { .q-choices { grid-template-columns:1fr; } }
.q-choice { padding:9px 14px; border-radius:10px; border:2px solid #e2e8f0; font-size:0.88rem; display:flex; gap:8px; align-items:flex-start; }
.q-choice.correct { border-color:#22c55e; background:#f0fdf4; }
.q-choice-label { font-weight:900; color:var(--primary); flex-shrink:0; }
.q-explain { margin-top:12px; font-size:0.85rem; color:#475569; background:#f0f7ff; border-radius:10px; padding:10px 14px; border-left:3px solid #3b82f6; }

.save-bar { background:white; border:1px solid #edf4ff; border-radius:18px; padding:18px 20px; display:none; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:22px; box-shadow:var(--shadow); }
.save-bar.visible { display:flex; }

/* Quiz list */
.quiz-item { display:flex; align-items:center; gap:14px; background:#f8fbff; border:1px solid #dbeafe; border-radius:14px; padding:14px 18px; margin-bottom:10px; flex-wrap:wrap; }
.quiz-badge { display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,var(--primary),var(--secondary)); color:white; font-size:0.95rem; flex-shrink:0; }
.quiz-meta { flex:1; min-width:0; }
.quiz-title { font-weight:900; color:#0f172a; font-size:0.97rem; }
.quiz-sub { font-size:0.82rem; color:var(--muted); margin-top:3px; }
.quiz-actions { display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap; }
.btn-take { background:linear-gradient(135deg,#22c55e,#16a34a); color:white; border:none; font-weight:800; border-radius:10px; padding:9px 16px; cursor:pointer; text-decoration:none; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; }
.btn-take:hover { opacity:0.9; color:white; }

.spinner { display:none; }
.spinner.visible { display:inline-block; }
.btn-send { background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:white; border:none; font-weight:800; border-radius:10px; padding:9px 14px; cursor:pointer; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; }
.btn-send:hover { opacity:0.9; }
.modal-overlay { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.45); align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:white; border-radius:22px; padding:28px; max-width:460px; width:92%; max-height:80vh; overflow-y:auto; box-shadow:0 24px 60px rgba(0,0,0,0.2); }
.modal-title { font-size:1.1rem; font-weight:900; color:var(--primary); margin-bottom:4px; }
.modal-sub { font-size:0.88rem; color:var(--muted); font-weight:700; margin-bottom:16px; padding:8px 12px; background:#f8fbff; border-radius:10px; border:1px solid #dbeafe; }
.student-check-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:12px; background:#f8fbff; border:1px solid #e2ecff; margin-bottom:7px; cursor:pointer; transition:background 0.15s; }
.student-check-item:hover { background:#eff6ff; }
.student-check-item input[type=checkbox] { width:18px; height:18px; accent-color:var(--primary); flex-shrink:0; cursor:pointer; }
.student-check-item label { font-weight:700; color:#0f172a; cursor:pointer; flex:1; margin:0; }
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
      <a href="admin_dashboard.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-house"></i></span><span>Dashboard</span></a>
      <a href="manage_users.php"    class="nav-link-custom"><span class="nav-icon"><i class="fas fa-users"></i></span><span>Manage Users</span></a>
      <a href="admin_teacher_students.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chalkboard-user"></i></span><span>Teacher Students</span></a>
          <a href="admin_enrollments.php" class="nav-link-custom">
            <span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Enrollments</span>
          </a>
      <a href="manage_classes.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Manage Classes</span></a>
      <a href="teacher_earnings.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-dollar-sign"></i></span><span>Teacher Earnings</span></a>
      <a href="available_slots.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-calendar-days"></i></span><span>Available Slots</span></a>
      <a href="courses.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span>Courses</span></a>
      <a href="admin_quiz_generator.php" class="nav-link-custom active"><span class="nav-icon"><i class="fas fa-circle-question"></i></span><span>AI Quiz Generator</span></a>
      <a href="reports.php"         class="nav-link-custom"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span>Reports</span></a>
      <a href="admin_certificates.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-award"></i></span><span>Certificates</span></a>
      <a href="admin_ai_settings.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-robot"></i></span><span>AI Tutor</span></a>
      <a href="admin_email_notifications.php" class="nav-link-custom"><span class="nav-icon"><i class="fas fa-envelope"></i></span><span>Email Notifications</span></a>
    </div>
    <div class="sidebar-bottom">
      <a href="settings.php"  class="nav-link-custom"><span class="nav-icon"><i class="fas fa-gear"></i></span><span>Settings</span></a>
      <div style="height:1px;background:rgba(255,255,255,0.1);margin:8px 0;"></div>
      <a href="logout.php"    class="nav-link-custom"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span>Logout</span></a>
    </div>
  </aside>

  <main class="main-content">
    <div class="hamburger-btn" onclick="document.body.classList.toggle('sidebar-collapsed')">
      <div class="hamburger-line"></div><div class="hamburger-line"></div><div class="hamburger-line"></div>
    </div>
    <div class="topbar">
      <div>
        <h1><i class="fas fa-circle-question me-2" style="font-size:1.5rem;"></i>AI Quiz Generator</h1>
        <p>Generate multiple-choice quizzes instantly using AI, then share them with students.</p>
      </div>
      <div class="admin-badge"><i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?></div>
    </div>

    <?php if (isset($_GET["saved"])): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Quiz saved successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET["deleted"])): ?>
      <div class="alert alert-info"><i class="fas fa-trash me-2"></i>Quiz deleted.</div>
    <?php endif; ?>

    <!-- Generate form -->
    <div class="panel-card">
      <div class="panel-title"><i class="fas fa-wand-magic-sparkles me-2" style="color:#7c3aed;"></i>Generate a New Quiz</div>

      <div class="gen-form">
        <div>
          <label class="form-label">Topic / Subject</label>
          <input type="text" id="topic" class="form-control" placeholder="e.g. Python loops, Scratch sprites, HTML tags…">
        </div>
        <div>
          <label class="form-label">Section</label>
          <select id="section" class="form-select">
            <option value="kids">Kids (ages 8–12)</option>
            <option value="junior">Junior (ages 12–16)</option>
            <option value="demo">Demo / Beginner</option>
          </select>
        </div>
        <div>
          <label class="form-label">Difficulty</label>
          <select id="difficulty" class="form-select">
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>
        <div>
          <label class="form-label">Number of Questions</label>
          <select id="count" class="form-select">
            <option value="3">3 questions</option>
            <option value="5" selected>5 questions</option>
            <option value="7">7 questions</option>
            <option value="10">10 questions</option>
          </select>
        </div>
        <div>
          <label class="form-label"><i class="fas fa-clock me-1" style="color:#7c3aed;"></i>Time Limit</label>
          <select id="time-limit" class="form-select">
            <option value="0">No timer</option>
            <option value="300">5 minutes</option>
            <option value="600">10 minutes</option>
            <option value="900">15 minutes</option>
            <option value="1200">20 minutes</option>
            <option value="1800">30 minutes</option>
          </select>
        </div>
      </div>

      <div style="margin-top:20px;">
        <button class="btn-main" id="gen-btn" onclick="generateQuiz()">
          <i class="fas fa-wand-magic-sparkles"></i>
          <span id="gen-label">Generate Quiz</span>
          <span class="spinner visible" id="gen-spinner" style="display:none;"><i class="fas fa-spinner fa-spin"></i></span>
        </button>
        <span id="gen-error" style="color:#dc2626;font-weight:700;margin-left:14px;font-size:0.9rem;display:none;"></span>
      </div>
    </div>

    <!-- Save bar (appears after preview) -->
    <div class="save-bar" id="save-bar">
      <div style="flex:1;min-width:200px;">
        <label class="form-label" style="margin-bottom:6px;">Quiz Title</label>
        <input type="text" id="quiz-title" class="form-control" placeholder="e.g. Python Basics — Week 1">
      </div>
      <button class="btn-main" style="margin-top:22px;" onclick="saveQuiz()">
        <i class="fas fa-floppy-disk"></i> Save Quiz
      </button>
      <button onclick="clearPreview()" style="margin-top:22px;background:#e2e8f0;border:none;color:#334155;font-weight:800;border-radius:12px;padding:12px 18px;cursor:pointer;">
        <i class="fas fa-xmark"></i> Clear
      </button>
    </div>

    <!-- Preview -->
    <div class="preview-wrap" id="preview-wrap"></div>

    <!-- Saved quizzes list -->
    <div class="panel-card">
      <div class="panel-header">
        <div class="panel-title"><i class="fas fa-list-check me-2" style="color:#2563eb;"></i>Saved Quizzes</div>
        <span style="color:var(--muted);font-size:0.88rem;font-weight:700;"><?= count($quizzes) ?> quiz<?= count($quizzes) !== 1 ? 'zes' : '' ?></span>
      </div>

      <?php if (empty($quizzes)): ?>
        <div class="empty-box">No quizzes saved yet. Generate one above!</div>
      <?php else: ?>
        <?php foreach ($quizzes as $qz): ?>
        <div class="quiz-item">
          <div class="quiz-badge"><i class="fas fa-circle-question"></i></div>
          <div class="quiz-meta">
            <div class="quiz-title"><?= htmlspecialchars($qz["title"]) ?></div>
            <div class="quiz-sub">
              <?= htmlspecialchars(ucfirst($qz["section"])) ?> &nbsp;·&nbsp;
              <?= htmlspecialchars(ucfirst($qz["difficulty"])) ?> &nbsp;·&nbsp;
              <?= (int)$qz["q_count"] ?> questions &nbsp;·&nbsp;
              <?= date("d M Y", strtotime($qz["created_at"])) ?>
            </div>
          </div>
          <div class="quiz-actions">
            <button class="btn-send" onclick="openSendModal(<?= $qz['id'] ?>, <?= htmlspecialchars(json_encode($qz['title']), ENT_QUOTES) ?>)" title="Send to students">
              <i class="fas fa-paper-plane"></i> Send
            </button>
            <a href="quiz_take.php?id=<?= $qz["id"] ?>" target="_blank" class="btn-take">
              <i class="fas fa-play"></i> Take Quiz
            </a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this quiz permanently?')">
              <input type="hidden" name="action"  value="delete_quiz">
              <input type="hidden" name="quiz_id" value="<?= $qz["id"] ?>">
              <button type="submit" class="btn-danger"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Send to Student Modal -->
<div class="modal-overlay" id="send-modal" onclick="if(event.target===this)closeSendModal()">
  <div class="modal-box">
    <div class="modal-title"><i class="fas fa-paper-plane me-2" style="color:#3b82f6;"></i>Send Quiz to Students</div>
    <div class="modal-sub" id="send-modal-subtitle"></div>
    <input type="text" id="student-search" placeholder="🔍 Search student by name…" oninput="filterStudents(this.value)" style="width:100%;padding:10px 14px;border:1.5px solid #3b82f6;border-radius:10px;font-size:0.93rem;font-weight:600;margin-bottom:12px;outline:none;display:block;box-sizing:border-box;background:#f8fbff;">
    <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #e2e8f0;">
      <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;font-weight:700;color:var(--muted);cursor:pointer;">
        <input type="checkbox" id="select-all-students" onchange="toggleSelectAll(this)" style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer;">
        Select all students
      </label>
    </div>
    <div id="send-student-list" style="max-height:260px;overflow-y:auto;"></div>
    <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <button class="btn-main" id="send-submit-btn" onclick="submitSend()"><i class="fas fa-paper-plane"></i> Send</button>
      <button onclick="closeSendModal()" style="background:#e2e8f0;border:none;color:#334155;font-weight:800;border-radius:12px;padding:12px 18px;cursor:pointer;"><i class="fas fa-xmark"></i> Cancel</button>
      <span id="send-result" style="font-size:0.9rem;font-weight:700;display:none;margin-left:4px;"></span>
    </div>
  </div>
</div>

<!-- Hidden save form -->
<form id="save-form" method="POST" style="display:none;">
  <input type="hidden" name="action"         value="save_quiz">
  <input type="hidden" name="title"          id="sf-title">
  <input type="hidden" name="topic"          id="sf-topic">
  <input type="hidden" name="section"        id="sf-section">
  <input type="hidden" name="difficulty"     id="sf-difficulty">
  <input type="hidden" name="time_limit"     id="sf-time-limit">
  <input type="hidden" name="questions_json" id="sf-questions">
</form>

<script>
let generatedQuestions = [];

async function generateQuiz() {
  const topic      = document.getElementById('topic').value.trim();
  const section    = document.getElementById('section').value;
  const difficulty = document.getElementById('difficulty').value;
  const count      = parseInt(document.getElementById('count').value);
  const errEl      = document.getElementById('gen-error');
  const btn        = document.getElementById('gen-btn');
  const spinner    = document.getElementById('gen-spinner');
  const label      = document.getElementById('gen-label');

  errEl.style.display = 'none';
  if (!topic) { errEl.textContent = 'Please enter a topic first.'; errEl.style.display = 'inline'; return; }

  btn.disabled = true;
  spinner.style.display = 'inline';
  label.textContent = 'Generating…';

  try {
    const res = await fetch('ai_quiz_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ topic, section, difficulty, count }),
    });
    const data = await res.json();

    if (data.error) { errEl.textContent = data.error; errEl.style.display = 'inline'; return; }

    generatedQuestions = data.questions;
    renderPreview(generatedQuestions, topic);

    document.getElementById('save-bar').classList.add('visible');
    document.getElementById('quiz-title').value = capitalize(topic) + ' Quiz';

  } catch(e) {
    errEl.textContent = 'Network error. Please try again.';
    errEl.style.display = 'inline';
  } finally {
    btn.disabled = false;
    spinner.style.display = 'none';
    label.textContent = 'Generate Quiz';
  }
}

function renderPreview(questions, topic) {
  const wrap = document.getElementById('preview-wrap');
  wrap.innerHTML = '';

  const heading = document.createElement('div');
  heading.className = 'panel-title';
  heading.style.marginBottom = '14px';
  heading.innerHTML = '<i class="fas fa-eye me-2" style="color:#7c3aed;"></i>Preview — ' + escHtml(capitalize(topic));
  wrap.appendChild(heading);

  questions.forEach((q, i) => {
    const card = document.createElement('div');
    card.className = 'q-card';

    let choicesHtml = '<div class="q-choices">';
    ['A','B','C','D'].forEach(letter => {
      const isCorrect = q.answer === letter;
      choicesHtml += `<div class="q-choice${isCorrect ? ' correct' : ''}">
        <span class="q-choice-label">${letter}.</span>
        <span>${escHtml(q.choices[letter] || '')}</span>
      </div>`;
    });
    choicesHtml += '</div>';

    const explainHtml = q.explanation
      ? `<div class="q-explain"><i class="fas fa-lightbulb me-1" style="color:#f59e0b;"></i>${escHtml(q.explanation)}</div>`
      : '';

    card.innerHTML = `
      <div class="q-num">Question ${i + 1}</div>
      <div class="q-text">${escHtml(q.question)}</div>
      ${choicesHtml}
      ${explainHtml}
    `;
    wrap.appendChild(card);
  });

  wrap.classList.add('visible');
}

function saveQuiz() {
  const title = document.getElementById('quiz-title').value.trim();
  if (!title) { alert('Please enter a quiz title.'); return; }
  if (generatedQuestions.length === 0) { alert('No questions to save.'); return; }

  document.getElementById('sf-title').value     = title;
  document.getElementById('sf-topic').value     = document.getElementById('topic').value;
  document.getElementById('sf-section').value    = document.getElementById('section').value;
  document.getElementById('sf-difficulty').value = document.getElementById('difficulty').value;
  document.getElementById('sf-time-limit').value = document.getElementById('time-limit').value;
  document.getElementById('sf-questions').value  = JSON.stringify(generatedQuestions);
  document.getElementById('save-form').submit();
}

function clearPreview() {
  generatedQuestions = [];
  document.getElementById('preview-wrap').innerHTML = '';
  document.getElementById('preview-wrap').classList.remove('visible');
  document.getElementById('save-bar').classList.remove('visible');
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function capitalize(s) {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

// --- Send modal ---
let sendQuizId = null;
const allStudents = <?= json_encode($students) ?>;

function openSendModal(quizId, quizTitle) {
  sendQuizId = quizId;
  document.getElementById('send-modal-subtitle').textContent = quizTitle;
  document.getElementById('select-all-students').checked = false;
  document.getElementById('student-search').value = '';
  document.getElementById('send-result').style.display = 'none';
  const list = document.getElementById('send-student-list');
  if (!allStudents.length) {
    list.innerHTML = '<div style="text-align:center;padding:24px;color:var(--muted);font-weight:700;">No students found.</div>';
  } else {
    list.innerHTML = allStudents.map(s =>
      `<div class="student-check-item">
        <input type="checkbox" id="stu-${escHtml(s)}" value="${escHtml(s)}">
        <label for="stu-${escHtml(s)}">${escHtml(s)}</label>
      </div>`
    ).join('');
  }
  document.getElementById('send-modal').classList.add('open');
}

function closeSendModal() {
  document.getElementById('send-modal').classList.remove('open');
  sendQuizId = null;
}

function filterStudents(query) {
  const q = query.toLowerCase();
  document.querySelectorAll('#send-student-list .student-check-item').forEach(item => {
    const name = item.querySelector('label').textContent.toLowerCase();
    item.style.display = name.includes(q) ? 'flex' : 'none';
  });
}

function toggleSelectAll(cb) {
  document.querySelectorAll('#send-student-list .student-check-item:not([style*="none"]) input[type=checkbox]').forEach(i => i.checked = cb.checked);
}

async function submitSend() {
  const checked = [...document.querySelectorAll('#send-student-list input:checked')].map(i => i.value);
  const resEl = document.getElementById('send-result');
  const btn   = document.getElementById('send-submit-btn');
  resEl.style.display = 'none';
  if (!checked.length) {
    resEl.textContent = 'Please select at least one student.';
    resEl.style.color = '#dc2626';
    resEl.style.display = 'inline';
    return;
  }
  btn.disabled = true;
  try {
    const res  = await fetch('quiz_assign_handler.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({quiz_id: sendQuizId, students: checked}),
    });
    const data = await res.json();
    if (data.error) {
      resEl.textContent = data.error;
      resEl.style.color = '#dc2626';
    } else {
      resEl.textContent = `Sent to ${data.assigned} student${data.assigned !== 1 ? 's' : ''}!`;
      resEl.style.color = '#16a34a';
      setTimeout(closeSendModal, 1600);
    }
    resEl.style.display = 'inline';
  } catch(e) {
    resEl.textContent = 'Network error. Please try again.';
    resEl.style.color = '#dc2626';
    resEl.style.display = 'inline';
  } finally {
    btn.disabled = false;
  }
}
</script>
<script src="logout-modal.js"></script>
</body>
</html>
