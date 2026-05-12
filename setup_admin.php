<?php
require_once "db.php";
$steps = [];

// ── Get MySQL data directory ──────────────────────────────
$r = $conn->query("SELECT @@datadir AS d");
$datadir = $r ? rtrim($r->fetch_assoc()['d'], '/\\') : '';
$dbDir   = $datadir . DIRECTORY_SEPARATOR . 'juniorcode_db2';
$steps[] = "📁 Data dir: <code>$dbDir</code>";

// ── Disable FK checks ────────────────────────────────────
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// ── Drop all existing tables ─────────────────────────────
$tables = [];
$res = $conn->query("SHOW TABLES");
if ($res) while ($row = $res->fetch_row()) $tables[] = $row[0];
foreach ($tables as $tbl) $conn->query("DROP TABLE IF EXISTS `$tbl`");
$steps[] = "🗑️ Dropped " . count($tables) . " table(s)";

// ── Delete orphaned InnoDB files ─────────────────────────
$deleted = 0; $failed = [];
if (is_dir($dbDir)) {
    foreach (scandir($dbDir) as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['ibd', 'frm', 'myd', 'myi'])) {
            $path = $dbDir . DIRECTORY_SEPARATOR . $file;
            if (unlink($path)) $deleted++;
            else $failed[] = $file;
        }
    }
}
$steps[] = $deleted ? "✅ Deleted $deleted orphaned file(s)" : "ℹ️ No orphaned files to delete";
if ($failed) $steps[] = "⚠️ Could not delete: " . implode(', ', $failed);

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// ── Create all tables ────────────────────────────────────
$creates = [

'users' => "CREATE TABLE users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(255) NOT NULL UNIQUE,
    password         VARCHAR(255) NOT NULL,
    plain_password   VARCHAR(255) NOT NULL DEFAULT '',
    role             VARCHAR(50)  NOT NULL DEFAULT 'student',
    email            VARCHAR(255) NOT NULL DEFAULT '',
    zoom_personal_link TEXT       NOT NULL DEFAULT '',
    profile_picture  VARCHAR(300) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'classes' => "CREATE TABLE classes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT          DEFAULT NULL,
    teacher_name VARCHAR(255) NOT NULL DEFAULT '',
    student_name VARCHAR(255) NOT NULL DEFAULT '',
    class_date   DATE         DEFAULT NULL,
    class_time   TIME         DEFAULT NULL,
    type         VARCHAR(100) NOT NULL DEFAULT '',
    details      TEXT         NOT NULL DEFAULT '',
    zoom_link    TEXT         DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'teacher_earnings' => "CREATE TABLE teacher_earnings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT          DEFAULT NULL,
    teacher_name VARCHAR(255) NOT NULL DEFAULT '',
    lesson_title VARCHAR(255) NOT NULL DEFAULT '',
    amount       DECIMAL(10,2) NOT NULL DEFAULT 0,
    lesson_date  DATE         DEFAULT NULL,
    notes        TEXT         NOT NULL DEFAULT '',
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'teacher_availability' => "CREATE TABLE teacher_availability (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id     INT          NOT NULL DEFAULT 0,
    teacher_name   VARCHAR(255) NOT NULL DEFAULT '',
    available_date DATE         NOT NULL,
    available_time TIME         NOT NULL,
    status         VARCHAR(50)  NOT NULL DEFAULT 'available',
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'settings' => "CREATE TABLE settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT         NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'assignments' => "CREATE TABLE assignments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT          NOT NULL DEFAULT 0,
    teacher_name VARCHAR(255) NOT NULL DEFAULT '',
    student_name VARCHAR(255) NOT NULL DEFAULT '',
    title        VARCHAR(255) NOT NULL DEFAULT '',
    description  TEXT         NOT NULL DEFAULT '',
    due_date     DATE         DEFAULT NULL,
    file_name    VARCHAR(255) NOT NULL DEFAULT '',
    link         VARCHAR(500) NOT NULL DEFAULT '',
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'certificates' => "CREATE TABLE certificates (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(255) NOT NULL DEFAULT '',
    course_name  VARCHAR(255) NOT NULL DEFAULT '',
    teacher_name VARCHAR(255) NOT NULL DEFAULT '',
    issued_date  DATE         DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'course_projects' => "CREATE TABLE course_projects (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    section    VARCHAR(50)  NOT NULL DEFAULT 'kids',
    category   VARCHAR(100) NOT NULL DEFAULT 'Game Development',
    title      VARCHAR(255) NOT NULL,
    url        TEXT         NOT NULL,
    image      TEXT         NOT NULL DEFAULT '',
    pdf_url    TEXT         NOT NULL DEFAULT '',
    sort_order INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'quizzes' => "CREATE TABLE quizzes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    topic      VARCHAR(255) NOT NULL,
    section    VARCHAR(50)  NOT NULL DEFAULT 'kids',
    difficulty VARCHAR(50)  NOT NULL DEFAULT 'beginner',
    created_by VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'quiz_questions' => "CREATE TABLE quiz_questions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'quiz_assignments' => "CREATE TABLE quiz_assignments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id          INT          NOT NULL,
    student_username VARCHAR(255) NOT NULL,
    assigned_by      VARCHAR(255) NOT NULL DEFAULT '',
    assigned_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assign (quiz_id, student_username),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

];

foreach ($creates as $name => $sql) {
    $ok = $conn->query($sql);
    $steps[] = $ok ? "✅ Created <b>$name</b>" : "❌ Failed <b>$name</b>: " . $conn->error;
}

// ── Create admin account ─────────────────────────────────
$hash  = password_hash("admin123", PASSWORD_DEFAULT);
$admin = "admin";
$stmt  = $conn->prepare("INSERT INTO users (username, password, plain_password, role, email) VALUES (?, ?, 'admin123', 'admin', '')");
if ($stmt) {
    $stmt->bind_param("ss", $admin, $hash);
    $ok = $stmt->execute();
    $steps[] = $ok
        ? "✅ Admin account ready — <b>username:</b> admin &nbsp;|&nbsp; <b>password:</b> admin123"
        : "❌ Admin insert failed: " . $stmt->error;
} else {
    $steps[] = "❌ Prepare failed: " . $conn->error;
}

$allOk = !array_filter($steps, fn($s) => strpos($s, '❌') !== false);
?>
<!DOCTYPE html>
<html>
<head><title>JuniorCode Setup</title>
<style>
body { font-family: Arial, sans-serif; padding: 40px; max-width: 750px; margin: auto; background:#f8fafc; }
h2   { color:#0f172a; margin-bottom:24px; }
ul   { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:24px 28px; list-style:none; }
li   { margin: 10px 0; font-size: 1.05rem; border-bottom:1px solid #f1f5f9; padding-bottom:10px; }
li:last-child { border-bottom:none; margin-bottom:0; padding-bottom:0; }
code { background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:0.85rem; word-break:break-all; }
.go  { display:inline-block; margin-top:24px; padding:13px 30px; background:#2563eb; color:white; border-radius:12px; text-decoration:none; font-weight:bold; font-size:1.05rem; }
.warn{ margin-top:16px; color:#ef4444; font-size:0.9rem; }
</style>
</head>
<body>
<h2>JuniorCode Setup</h2>
<ul>
<?php foreach ($steps as $s) echo "<li>$s</li>"; ?>
</ul>
<?php if ($allOk): ?>
<a class="go" href="login.php">✅ Go to Login</a>
<?php else: ?>
<p style="margin-top:16px;color:#b45309;font-weight:bold;">⚠️ Some steps failed — check messages above and try refreshing.</p>
<?php endif; ?>
<p class="warn">Delete <code>setup_admin.php</code> after logging in successfully.</p>
</body>
</html>
